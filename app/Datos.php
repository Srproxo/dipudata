<?php
declare(strict_types=1);

namespace App;

/**
 * Acceso de solo lectura a storage/verificado/ (lo genera scripts/construir_datos.py).
 * Es la única fuente de datos de legisladores del sitio.
 */
final class Datos
{
    private static array $cache = [];

    private static function leer(string $nombre): array
    {
        if (!isset(self::$cache[$nombre])) {
            $ruta = RAIZ . "/storage/verificado/{$nombre}.json";
            $json = is_file($ruta) ? file_get_contents($ruta) : false;
            $datos = $json === false ? null : json_decode($json, true);
            self::$cache[$nombre] = is_array($datos) ? $datos : [];
        }
        return self::$cache[$nombre];
    }

    /** @return list<array> */
    public static function legisladores(?string $camara = null): array
    {
        $todos = array_map(static function (array $l): array {
            // Solo se muestran fotos servidas por el host que declara la CSP.
            if (!empty($l['foto']) && !preg_match('#^https://(api\.argentinadatos\.com|www\.senado\.gob\.ar)/#', (string)$l['foto'])) {
                $l['foto'] = null;
            }
            return $l;
        }, self::leer('legisladores'));
        return $camara === null ? $todos : array_values(array_filter($todos, static fn($l) => $l['camara'] === $camara));
    }

    public static function legislador(string $camara, string $slug): ?array
    {
        foreach (self::legisladores($camara) as $l) {
            if ($l['slug'] === $slug) {
                return $l;
            }
        }
        return null;
    }

    /** @return list<array> */
    public static function bloques(?string $camara = null): array
    {
        $b = self::leer('bloques');
        return $camara === null ? $b : array_values(array_filter($b, static fn($x) => $x['camara'] === $camara));
    }

    public static function bloque(string $camara, string $slug): ?array
    {
        foreach (self::bloques($camara) as $b) {
            if ($b['slug'] === $slug) {
                return $b;
            }
        }
        return null;
    }

    /** @return list<array> miembros de un bloque */
    public static function miembros(array $bloque): array
    {
        return array_values(array_filter(
            self::legisladores($bloque['camara']),
            static fn($l) => $l['bloque_oficial'] === $bloque['nombre_oficial']
        ));
    }

    /** @return list<array{0:int,1:string,2:string,3:string}> [numero, titulo, fecha sancion, referencias] */
    public static function leyes(): array
    {
        $ruta = RAIZ . '/storage/verificado/leyes/indice.json';
        $d = is_file($ruta) ? json_decode((string)file_get_contents($ruta), true) : null;
        return is_array($d) ? $d : [];
    }

    public static function ley(int $n): ?array
    {
        $ruta = RAIZ . "/storage/verificado/leyes/ley/{$n}.json";
        $d = is_file($ruta) ? json_decode((string)file_get_contents($ruta), true) : null;
        return is_array($d) ? $d : null;
    }

    /** @return array<string,array> resumen por año de subsidios (y 'covid') */
    public static function subsidiosIndice(): array
    {
        $ruta = RAIZ . '/storage/verificado/subsidios/indice.json';
        $d = is_file($ruta) ? json_decode((string)file_get_contents($ruta), true) : null;
        return is_array($d) ? $d : [];
    }

    public static function subsidios(string $anio): array
    {
        if (!preg_match('/^(\d{4}|covid)$/', $anio)) {
            return [];
        }
        $ruta = RAIZ . "/storage/verificado/subsidios/{$anio}.json";
        $d = is_file($ruta) ? json_decode((string)file_get_contents($ruta), true) : null;
        return is_array($d) ? $d : [];
    }

    /** @return list<array> declaraciones juradas patrimoniales de un legislador actual, de la más reciente a la más antigua */
    public static function ddjj(string $camara, string $slug): array
    {
        return self::leer('ddjj')[($camara === 'Senadores' ? 's:' : '') . $slug] ?? [];
    }

    /** @return array<string,array{n:int,total:float}> subsidios por año de un diputado actual */
    public static function subsidiosDiputado(string $slug): array
    {
        return self::leer('subsidios_diputados')[$slug] ?? [];
    }

    /** @return list<array> cabeceras de las votaciones nominales, de la más reciente a la más antigua */
    public static function votaciones(): array
    {
        $ruta = RAIZ . '/storage/verificado/votaciones/indice.json';
        $d = is_file($ruta) ? json_decode((string)file_get_contents($ruta), true) : null;
        return is_array($d) ? $d : [];
    }

    /** @return array{bloques:array,votos:list<array>}|null */
    public static function votacion(string $id): ?array
    {
        if (!preg_match('/^\d{1,6}$/', $id)) {
            return null;
        }
        $ruta = RAIZ . "/storage/verificado/votaciones/actas/{$id}.json";
        $d = is_file($ruta) ? json_decode((string)file_get_contents($ruta), true) : null;
        return is_array($d) ? $d : null;
    }

    /** Resumen de participación en votaciones nominales de un diputado actual (o null). */
    public static function participacion(string $slug): ?array
    {
        return self::leer('votaciones_diputados')[$slug] ?? null;
    }

    /** @return list<array> */
    public static function interbloques(): array
    {
        return self::leer('interbloques');
    }

    /** @return list<array> */
    public static function provincias(): array
    {
        return self::leer('provincias');
    }

    public static function provincia(string $slug): ?array
    {
        foreach (self::provincias() as $p) {
            if ($p['slug'] === $slug) {
                return $p;
            }
        }
        return null;
    }

    public static function informe(): array
    {
        return self::leer('informe');
    }

    /** @return array<string,array> tipo => {titulo, familia, descripcion, total, anios, fuente} */
    public static function docsIndice(): array
    {
        $ruta = RAIZ . '/storage/verificado/senado_docs/indice.json';
        $d = is_file($ruta) ? json_decode((string)file_get_contents($ruta), true) : null;
        return is_array($d) ? $d : [];
    }

    /** Registros de un tipo y año (o de todos los años si $anio === 'todos'). */
    public static function docs(string $tipo, string $anio): array
    {
        $idx = self::docsIndice();
        if (!isset($idx[$tipo]) || !preg_match('/^(\d{4}|todos)$/', $anio)) {
            return [];
        }
        $anios = $anio === 'todos' ? array_keys($idx[$tipo]['anios']) : [$anio];
        $out = [];
        foreach ($anios as $a) {
            $ruta = RAIZ . "/storage/verificado/senado_docs/{$tipo}/{$a}.json";
            $d = is_file($ruta) ? json_decode((string)file_get_contents($ruta), true) : null;
            if (is_array($d)) {
                array_push($out, ...$d);
            }
        }
        return $out;
    }

    /** @return list<array{nombre:string,tipo:string}> */
    public static function comisionesSenado(): array
    {
        return self::leer('comisiones_senado');
    }

    public static function urlLegislador(array $l): string
    {
        return ($l['camara'] === 'Senadores' ? '/senador/' : '/diputado/') . $l['slug'];
    }

    public static function urlBloque(array $b): string
    {
        return '/congreso/bloques/' . ($b['camara'] === 'Senadores' ? 'senado' : 'diputados') . '/' . $b['slug'];
    }

    public static function normalizar(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u','â'=>'a','ê'=>'e','ô'=>'o','ç'=>'c']);
        return trim((string)preg_replace('/[^a-z0-9]+/', ' ', $s));
    }
}
