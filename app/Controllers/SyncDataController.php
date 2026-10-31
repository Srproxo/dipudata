<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use LegisData\Security;
use PDO;
use Throwable;

/**
 * Sincronización de fuentes externas hacia legisladores_master / votaciones / patrimonio_ddjj_cuit.
 *
 * Uso (Cron Hostinger):
 *   php /home/u578788564/domains/dipudata.com/public_html/app/cron.php votaciones /ruta/votaciones.csv
 *   php .../app/cron.php ddjj /ruta/ddjj.csv
 *   php .../app/cron.php wikipedia
 *   php .../app/cron.php all
 */
class SyncDataController
{
    private const WIKI_API = 'https://es.wikipedia.org/w/api.php';
    private const USER_AGENT = 'DipuDataBot/1.0 (https://dipudata.com; transparencia legislativa)';

    private const EJES = [
        'salud publica'      => 'Salud Pública',
        'salud'              => 'Salud Pública',
        'educacion publica'  => 'Educación Pública',
        'educacion'          => 'Educación Pública',
        'industria nacional' => 'Industria Nacional',
        'industria'          => 'Industria Nacional',
        'proteccionismo'     => 'Industria Nacional',
        'apertura comercial' => 'Apertura Comercial',
        'mercadolibre'       => 'Apertura Comercial',
        'libre mercado'      => 'Apertura Comercial',
    ];

    private const VOTOS = [
        'afirmativo' => 'Afirmativo', 'si' => 'Afirmativo', 'positivo' => 'Afirmativo',
        'negativo'   => 'Negativo',   'no' => 'Negativo',
        'abstencion' => 'Abstención', 'abstenido' => 'Abstención',
        'ausente'    => 'Ausente',    'ausencia' => 'Ausente',
    ];

    private PDO $pdo;
    private array $log = [];

    public function __construct(?PDO $pdo = null)
    {
        Security::verifyCronAccess($pdo, 'SyncDataController');
        $pdo ??= Database::getConnection();
        if ($pdo === null) {
            throw new \RuntimeException('Sin conexión MySQL: la sincronización requiere base de datos.');
        }
        $this->pdo = $pdo;
    }

    public function run(string $tarea, ?string $ruta = null): array
    {
        $base = dirname(__DIR__, 2) . '/storage/csv/';
        match ($tarea) {
            'votaciones' => $this->syncVotacionesCsv($ruta ?? $base . 'votaciones_datacp.csv'),
            'ddjj'       => $this->syncDdjjCsv($ruta ?? $base . 'ddjj_anticorrupcion.csv'),
            'wikipedia'  => $this->syncWikipedia(),
            'all'        => [
                $this->syncVotacionesCsv($base . 'votaciones_datacp.csv'),
                $this->syncDdjjCsv($base . 'ddjj_anticorrupcion.csv'),
                $this->syncWikipedia(),
            ],
            default => throw new \InvalidArgumentException("Tarea desconocida: {$tarea}"),
        };

        Security::logAudit($this->pdo, 'CRON_SYNC_' . strtoupper($tarea), 'SyncDataController', 'INFO', $this->log);
        return $this->log;
    }

    /** CSV DataCP: id_recinto,nombre,camara,provincia,bloque,estado,cuit,nombre_wiki,indice_lealtad,asistencia,ley,eje_tematico,voto,fecha */
    public function syncVotacionesCsv(string $ruta): void
    {
        $filas = $this->leerCsv($ruta);
        $insLeg = $this->pdo->prepare("
            INSERT INTO legisladores_master
                (nombre, slug, camara, provincia, bloque, estado, id_recinto, cuit, nombre_wiki, indice_lealtad, asistencia)
            VALUES (:nombre, :slug, :camara, :provincia, :bloque, :estado, :id_recinto, :cuit, :nombre_wiki, :lealtad, :asistencia)
            ON DUPLICATE KEY UPDATE
                nombre = VALUES(nombre), camara = VALUES(camara), provincia = VALUES(provincia),
                bloque = VALUES(bloque), estado = VALUES(estado), nombre_wiki = VALUES(nombre_wiki),
                indice_lealtad = VALUES(indice_lealtad), asistencia = VALUES(asistencia)
        ");
        $insVoto = $this->pdo->prepare("
            INSERT INTO votaciones (id_recinto, ley, eje_tematico, voto, fecha)
            VALUES (:id_recinto, :ley, :eje, :voto, :fecha)
            ON DUPLICATE KEY UPDATE voto = VALUES(voto), eje_tematico = VALUES(eje_tematico)
        ");

        $ok = $descartadas = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($filas as $n => $f) {
                $leg = $this->normalizarLegislador($f);
                $voto = $this->normalizarVoto($f);
                if ($leg === null || $voto === null) {
                    $descartadas++;
                    $this->log['errores'][] = "votaciones fila " . ($n + 2) . ": datos inválidos";
                    continue;
                }
                $insLeg->execute($leg);
                $insVoto->execute($voto + [':id_recinto' => $leg[':id_recinto']]);
                $ok++;
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        $this->log['votaciones'] = ['procesadas' => $ok, 'descartadas' => $descartadas];
    }

    /** CSV Oficina Anticorrupción + IPC INDEC: cuit,anio,monto_declarado,ajuste_inflacion_ipc[,variacion_real_porcentaje] */
    public function syncDdjjCsv(string $ruta): void
    {
        $filas = $this->leerCsv($ruta);
        $porCuit = [];
        foreach ($filas as $f) {
            $cuit = self::normalizarCuit($f['cuit'] ?? '');
            if ($cuit === null || !is_numeric($f['anio'] ?? null) || !is_numeric($f['monto_declarado'] ?? null)) {
                continue;
            }
            $porCuit[$cuit][(int)$f['anio']] = [
                'monto' => (float)$f['monto_declarado'],
                'ipc'   => (float)($f['ajuste_inflacion_ipc'] ?? 0),
            ];
        }

        $ins = $this->pdo->prepare("
            INSERT INTO patrimonio_ddjj_cuit (cuit, anio, monto_declarado, ajuste_inflacion_ipc, variacion_real_porcentaje)
            SELECT :cuit, :anio, :monto, :ipc, :var FROM legisladores_master WHERE cuit = :cuit2
            ON DUPLICATE KEY UPDATE monto_declarado = VALUES(monto_declarado),
                ajuste_inflacion_ipc = VALUES(ajuste_inflacion_ipc),
                variacion_real_porcentaje = VALUES(variacion_real_porcentaje)
        ");

        $ok = 0;
        foreach ($porCuit as $cuit => $anios) {
            ksort($anios);
            $previo = null;
            foreach ($anios as $anio => $d) {
                // Variación real = (Monto_t / Monto_t-1) / (1 + IPC_t) - 1
                $var = $previo !== null && $previo > 0
                    ? (($d['monto'] / $previo) / (1 + $d['ipc'] / 100) - 1) * 100
                    : 0.0;
                $ins->execute([
                    ':cuit' => $cuit, ':cuit2' => $cuit, ':anio' => $anio,
                    ':monto' => $d['monto'], ':ipc' => $d['ipc'], ':var' => round($var, 2),
                ]);
                $ok += $ins->rowCount() > 0 ? 1 : 0;
                $previo = $d['monto'];
            }
        }
        $this->log['ddjj'] = ['registros' => $ok, 'cuits' => count($porCuit)];
    }

    /** MediaWiki API: extracto introductorio + miniatura, en lotes de 20 títulos. */
    public function syncWikipedia(): void
    {
        $legs = $this->pdo->query("SELECT id, nombre_wiki FROM legisladores_master WHERE nombre_wiki IS NOT NULL AND nombre_wiki <> ''")->fetchAll();
        $upd = $this->pdo->prepare("UPDATE legisladores_master SET biografia_extracto = :bio, foto_url = COALESCE(:foto, foto_url) WHERE id = :id");
        $actualizados = 0;

        foreach (array_chunk($legs, 20) as $lote) {
            $titulos = array_map(fn($l) => str_replace('_', ' ', $l['nombre_wiki']), $lote);
            $data = $this->httpGetJson(self::WIKI_API . '?' . http_build_query([
                'action'      => 'query',
                'format'      => 'json',
                'prop'        => 'extracts|pageimages',
                'exintro'     => 1,
                'explaintext' => 1,
                'exsentences' => 5,
                'piprop'      => 'thumbnail',
                'pithumbsize' => 400,
                'redirects'   => 1,
                'titles'      => implode('|', $titulos),
            ]));
            if ($data === null) {
                $this->log['errores'][] = 'Wikipedia: lote sin respuesta';
                continue;
            }

            // Resolver normalizaciones/redirecciones: título solicitado -> título final
            $alias = [];
            foreach (array_merge($data['query']['normalized'] ?? [], $data['query']['redirects'] ?? []) as $r) {
                $alias[$r['from']] = $r['to'];
            }
            $paginas = [];
            foreach ($data['query']['pages'] ?? [] as $p) {
                if (!isset($p['missing'])) {
                    $paginas[$p['title']] = $p;
                }
            }

            foreach ($lote as $i => $leg) {
                $t = $titulos[$i];
                while (isset($alias[$t])) {
                    $t = $alias[$t];
                }
                $p = $paginas[$t] ?? null;
                $bio = isset($p['extract']) ? trim(preg_replace('/\s+/u', ' ', $p['extract'])) : '';
                if ($bio === '') {
                    continue;
                }
                $foto = $p['thumbnail']['source'] ?? null;
                if ($foto !== null && !str_starts_with($foto, 'https://upload.wikimedia.org/')) {
                    $foto = null;
                }
                $upd->execute([':bio' => mb_substr($bio, 0, 4000), ':foto' => $foto, ':id' => $leg['id']]);
                $actualizados++;
            }
            usleep(300_000);
        }
        $this->log['wikipedia'] = ['actualizados' => $actualizados, 'total' => count($legs)];
    }

    private function normalizarLegislador(array $f): ?array
    {
        $cuit = self::normalizarCuit($f['cuit'] ?? '');
        $idRecinto = preg_replace('/[^A-Za-z0-9\-]/', '', (string)($f['id_recinto'] ?? ''));
        $nombre = trim(preg_replace('/\s+/u', ' ', (string)($f['nombre'] ?? '')));
        $camara = self::sinAcentos($f['camara'] ?? '');
        $camara = str_starts_with($camara, 'sen') ? 'Senadores' : (str_starts_with($camara, 'dip') ? 'Diputados' : null);
        if ($cuit === null || $idRecinto === '' || $nombre === '' || $camara === null) {
            return null;
        }
        $estado = self::sinAcentos($f['estado'] ?? 'activo');
        return [
            ':nombre'      => $nombre,
            ':slug'        => Database::slugify($nombre),
            ':camara'      => $camara,
            ':provincia'   => trim((string)($f['provincia'] ?? '')),
            ':bloque'      => trim((string)($f['bloque'] ?? 'Sin bloque')),
            ':estado'      => str_starts_with($estado, 'inac') ? 'Inactivo' : (str_starts_with($estado, 'mand') ? 'Mandato Cumplido' : 'Activo'),
            ':id_recinto'  => $idRecinto,
            ':cuit'        => $cuit,
            ':nombre_wiki' => trim((string)($f['nombre_wiki'] ?? '')) ?: null,
            ':lealtad'     => self::porcentaje($f['indice_lealtad'] ?? 0),
            ':asistencia'  => self::porcentaje($f['asistencia'] ?? 0),
        ];
    }

    private function normalizarVoto(array $f): ?array
    {
        $eje = self::EJES[self::sinAcentos($f['eje_tematico'] ?? '')] ?? null;
        $voto = self::VOTOS[self::sinAcentos($f['voto'] ?? '')] ?? null;
        $fecha = date_create_immutable((string)($f['fecha'] ?? ''));
        $ley = trim((string)($f['ley'] ?? ''));
        if ($eje === null || $voto === null || $fecha === false || $ley === '') {
            return null;
        }
        return [':ley' => mb_substr($ley, 0, 255), ':eje' => $eje, ':voto' => $voto, ':fecha' => $fecha->format('Y-m-d')];
    }

    public static function normalizarCuit(string $cuit): ?string
    {
        $d = preg_replace('/\D/', '', $cuit);
        if (strlen($d) !== 11) {
            return null;
        }
        // Dígito verificador AFIP/ARCA (módulo 11)
        $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += (int)$d[$i] * $pesos[$i];
        }
        $dv = 11 - ($suma % 11);
        $dv = $dv === 11 ? 0 : ($dv === 10 ? 9 : $dv);
        if ($dv !== (int)$d[10]) {
            return null;
        }
        return substr($d, 0, 2) . '-' . substr($d, 2, 8) . '-' . $d[10];
    }

    private static function sinAcentos(string $s): string
    {
        return strtolower(trim(strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'ñ' => 'n', 'Ñ' => 'n'])));
    }

    private static function porcentaje(mixed $v): float
    {
        return max(0.0, min(100.0, (float)str_replace(',', '.', (string)$v)));
    }

    private function leerCsv(string $ruta): array
    {
        $real = realpath($ruta);
        if ($real === false || !is_readable($real) || !str_ends_with(strtolower($real), '.csv')) {
            throw new \RuntimeException("CSV no legible: {$ruta}");
        }
        $h = fopen($real, 'r');
        $primera = (string)fgets($h);
        $sep = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
        rewind($h);
        $headers = array_map(fn($x) => self::sinAcentos(str_replace("\xEF\xBB\xBF", '', $x)), fgetcsv($h, null, $sep, '"', '') ?: []);
        $filas = [];
        while (($row = fgetcsv($h, null, $sep, '"', '')) !== false) {
            if (count($row) === count($headers)) {
                $filas[] = array_combine($headers, array_map(fn($x) => mb_convert_encoding(trim($x), 'UTF-8', 'UTF-8, ISO-8859-1'), $row));
            }
        }
        fclose($h);
        return $filas;
    }

    private function httpGetJson(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false || $code !== 200) {
            return null;
        }
        $json = json_decode((string)$body, true);
        return is_array($json) ? $json : null;
    }
}
