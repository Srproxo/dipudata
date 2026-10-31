<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class LegisladorModel
{
    public const EJES = ['Salud Pública', 'Educación Pública', 'Industria Nacional', 'Apertura Comercial'];

    private ?PDO $pdo;
    private ?array $csvCache = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function all(): array
    {
        if ($this->pdo) {
            return $this->pdo->query("SELECT * FROM legisladores_master WHERE estado = 'Activo' ORDER BY nombre")->fetchAll();
        }
        return array_values($this->csv()['legisladores']);
    }

    public function findBySlug(string $slug): ?array
    {
        if ($this->pdo) {
            $stmt = $this->pdo->prepare("SELECT * FROM legisladores_master WHERE slug = :slug LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            return $stmt->fetch() ?: null;
        }
        foreach ($this->csv()['legisladores'] as $leg) {
            if ($leg['slug'] === $slug) {
                return $leg;
            }
        }
        return null;
    }

    public function votaciones(string $idRecinto): array
    {
        if ($this->pdo) {
            $stmt = $this->pdo->prepare("SELECT ley, eje_tematico, voto, fecha FROM votaciones WHERE id_recinto = :id ORDER BY fecha DESC");
            $stmt->execute([':id' => $idRecinto]);
            return $stmt->fetchAll();
        }
        $rows = $this->csv()['votaciones'][$idRecinto] ?? [];
        usort($rows, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
        return $rows;
    }

    public function patrimonio(string $cuit): array
    {
        if ($this->pdo) {
            $stmt = $this->pdo->prepare("
                SELECT anio, monto_declarado, ajuste_inflacion_ipc, variacion_real_porcentaje
                FROM patrimonio_ddjj_cuit WHERE cuit = :cuit ORDER BY anio ASC
            ");
            $stmt->execute([':cuit' => $cuit]);
            return $stmt->fetchAll();
        }
        return $this->csv()['ddjj'][$cuit] ?? [];
    }

    /**
     * Crecimiento patrimonial real acumulado: monto final deflactado por el IPC
     * de cada año posterior al inicial, contra el monto inicial.
     */
    public function resumenPatrimonial(array $serie): array
    {
        if (count($serie) < 2) {
            return ['crecimiento_nominal_pct' => 0.0, 'crecimiento_real_pct' => 0.0, 'inflacion_acumulada_pct' => 0.0];
        }
        $inicial = (float)$serie[0]['monto_declarado'];
        $final = (float)end($serie)['monto_declarado'];
        $factorIpc = 1.0;
        foreach (array_slice($serie, 1) as $p) {
            $factorIpc *= 1 + ((float)$p['ajuste_inflacion_ipc'] / 100);
        }
        $nominal = $inicial > 0 ? ($final / $inicial - 1) * 100 : 0.0;
        $real = $inicial > 0 ? (($final / $factorIpc) / $inicial - 1) * 100 : 0.0;

        return [
            'crecimiento_nominal_pct' => round($nominal, 2),
            'crecimiento_real_pct'    => round($real, 2),
            'inflacion_acumulada_pct' => round(($factorIpc - 1) * 100, 2),
        ];
    }

    /**
     * Promedio de votos positivos por eje: Afirmativos / Emitidos * 100 (excluye Ausente).
     */
    public function perfilTematico(string $idRecinto): array
    {
        $conteo = array_fill_keys(self::EJES, ['afirmativos' => 0, 'emitidos' => 0]);
        foreach ($this->votaciones($idRecinto) as $v) {
            $eje = $v['eje_tematico'];
            if (!isset($conteo[$eje]) || $v['voto'] === 'Ausente') {
                continue;
            }
            $conteo[$eje]['emitidos']++;
            if ($v['voto'] === 'Afirmativo') {
                $conteo[$eje]['afirmativos']++;
            }
        }
        $vector = [];
        foreach ($conteo as $eje => $c) {
            $vector[$eje] = $c['emitidos'] > 0 ? round($c['afirmativos'] / $c['emitidos'] * 100, 2) : 0.0;
        }
        return $vector;
    }

    public static function urlPerfil(array $leg): string
    {
        $prefijo = $leg['camara'] === 'Senadores' ? '/senador/' : '/diputado/';
        return $prefijo . $leg['slug'];
    }

    private function csv(): array
    {
        if ($this->csvCache !== null) {
            return $this->csvCache;
        }
        $base = dirname(__DIR__, 2) . '/storage/csv/';
        $bios = Database::bios();
        $legisladores = [];
        $votaciones = [];
        $ddjj = [];

        foreach (self::readCsv($base . 'votaciones_datacp.csv') as $r) {
            $id = $r['id_recinto'];
            if (!isset($legisladores[$id])) {
                $legisladores[$id] = [
                    'id'                 => count($legisladores) + 1,
                    'nombre'             => $r['nombre'],
                    'slug'               => Database::slugify($r['nombre']),
                    'camara'             => $r['camara'],
                    'provincia'          => $r['provincia'],
                    'bloque'             => $r['bloque'],
                    'estado'             => $r['estado'],
                    'id_recinto'         => $id,
                    'cuit'               => $r['cuit'],
                    'nombre_wiki'        => $r['nombre_wiki'],
                    'biografia_extracto' => $bios[$id] ?? null,
                    'foto_url'           => null,
                    'indice_lealtad'     => (float)$r['indice_lealtad'],
                    'asistencia'         => (float)$r['asistencia'],
                ];
            }
            $votaciones[$id][] = [
                'ley'          => $r['ley'],
                'eje_tematico' => $r['eje_tematico'],
                'voto'         => $r['voto'],
                'fecha'        => $r['fecha'],
            ];
        }

        foreach (self::readCsv($base . 'ddjj_anticorrupcion.csv') as $r) {
            $ddjj[$r['cuit']][] = [
                'anio'                      => (int)$r['anio'],
                'monto_declarado'           => (float)$r['monto_declarado'],
                'ajuste_inflacion_ipc'      => (float)$r['ajuste_inflacion_ipc'],
                'variacion_real_porcentaje' => (float)$r['variacion_real_porcentaje'],
            ];
        }

        return $this->csvCache = compact('legisladores', 'votaciones', 'ddjj');
    }

    public static function readCsv(string $path): array
    {
        if (!is_readable($path) || ($h = fopen($path, 'r')) === false) {
            return [];
        }
        $headers = fgetcsv($h, null, ',', '"', '');
        $rows = [];
        while (($row = fgetcsv($h, null, ',', '"', '')) !== false) {
            if ($headers && count($row) === count($headers)) {
                $rows[] = array_map('trim', array_combine($headers, $row));
            }
        }
        fclose($h);
        return $rows;
    }
}
