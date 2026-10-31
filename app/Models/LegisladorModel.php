<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class LegisladorModel
{
    private ?PDO $pdo;
    private ?array $csvCache = null;
    private ?array $ejesCache = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Retorna todos los ejes temáticos activos desde la base de datos relacional (Fase 2)
     * con fallback automático a la matriz predeterminada de 11 ejes.
     * @return array<int, array{id:int, nombre_eje:string, descripcion:string}>
     */
    public function ejes(): array
    {
        if ($this->ejesCache !== null) {
            return $this->ejesCache;
        }

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query("SELECT id, nombre_eje, descripcion FROM ejes_tematicos WHERE activo = 1 ORDER BY id ASC");
                $rows = $stmt ? $stmt->fetchAll() : [];
                if (!empty($rows)) {
                    return $this->ejesCache = $rows;
                }
            } catch (\Throwable) {
            }
        }

        return $this->ejesCache = array_values(Database::getDefaultEjes());
    }

    /**
     * Lista plana de nombres de ejes temáticos activos.
     * @return list<string>
     */
    public function nombresEjes(): array
    {
        return array_column($this->ejes(), 'nombre_eje');
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
            try {
                $stmt = $this->pdo->prepare("
                    SELECT v.ley, COALESCE(e.nombre_eje, 'General') AS eje_tematico, v.eje_id, v.voto, v.fecha
                    FROM votaciones v
                    LEFT JOIN ejes_tematicos e ON v.eje_id = e.id
                    WHERE v.id_recinto = :id
                    ORDER BY v.fecha DESC
                ");
                $stmt->execute([':id' => $idRecinto]);
                return $stmt->fetchAll();
            } catch (\Throwable) {
                try {
                    $stmt = $this->pdo->prepare("SELECT ley, eje_tematico, voto, fecha FROM votaciones WHERE id_recinto = :id ORDER BY fecha DESC");
                    $stmt->execute([':id' => $idRecinto]);
                    return $stmt->fetchAll();
                } catch (\Throwable) {
                    return [];
                }
            }
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
     * Promedio dinámico de votos positivos por eje temático:
     * Afirmativos / Emitidos * 100 (excluye Ausente) iterando sobre los ejes activos de la base de datos.
     * @return array<string, float>
     */
    public function perfilTematico(string $idRecinto): array
    {
        $nombres = $this->nombresEjes();
        $conteo = array_fill_keys($nombres, ['afirmativos' => 0, 'emitidos' => 0]);
        foreach ($this->votaciones($idRecinto) as $v) {
            $eje = $v['eje_tematico'] ?? null;
            // Normalizar alias histórico si vino de datos antiguos
            if ($eje === 'Educación Pública' && !isset($conteo[$eje]) && isset($conteo['Educación'])) {
                $eje = 'Educación';
            }
            if ($eje === null || !isset($conteo[$eje]) || ($v['voto'] ?? '') === 'Ausente') {
                continue;
            }
            $conteo[$eje]['emitidos']++;
            if (($v['voto'] ?? '') === 'Afirmativo') {
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

        $ejesDefault = Database::getDefaultEjes();
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
            $ejeId = isset($r['eje_id']) && is_numeric($r['eje_id']) ? (int)$r['eje_id'] : 1;
            $ejeNombre = $r['eje_tematico'] ?? ($ejesDefault[$ejeId]['nombre_eje'] ?? 'General');
            $votaciones[$id][] = [
                'ley'          => $r['ley'],
                'eje_id'       => $ejeId,
                'eje_tematico' => $ejeNombre,
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
