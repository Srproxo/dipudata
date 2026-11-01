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
            if (!empty($l['foto']) && !str_starts_with((string)$l['foto'], 'https://api.argentinadatos.com/')) {
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
