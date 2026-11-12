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
        // Las fotos se sirven desde este mismo sitio (scripts/bajar_fotos.py las copia a assets/fotos/); nunca se piden a un tercero.
        $fotos = self::leer('fotos');
        $todos = array_map(static function (array $l) use ($fotos): array {
            $l['foto'] = $fotos[($l['camara'] === 'Senadores' ? 's:' : '') . $l['slug']] ?? null;
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

    /** Datos extra de una ley (scripts/construir_leyes_extra.py): texto oficial en Infoleg, normas vinculadas y votaciones del día de la sanción. */
    public static function leyVinculos(int $n): array
    {
        $d = self::leer('leyes/vinculos');
        return $d['leyes'][(string)$n] ?? [];
    }

    /** @return list<int> leyes cuya votación del día de la sanción es esta (cruce de construir_leyes_extra.py) */
    public static function leyesDeVotacion(string $id): array
    {
        if (!isset(self::$cache['_ley_por_voto'])) {
            self::$cache['_ley_por_voto'] = [];
            foreach ((self::leer('leyes/vinculos')['leyes'] ?? []) as $n => $e) {
                foreach ($e['votaciones'] ?? [] as $v) { self::$cache['_ley_por_voto'][$v['id']][] = (int)$n; }
            }
        }
        return self::$cache['_ley_por_voto'][$id] ?? [];
    }

    /** @return array{0:int,1:string,2:string,3:string}|null fila del índice de una ley (número, título, sanción, referencias) */
    public static function leyIndice(int $n): ?array
    {
        if (!isset(self::$cache['_leyes_por_n'])) {
            self::$cache['_leyes_por_n'] = [];
            foreach (self::leyes() as $l) { self::$cache['_leyes_por_n'][(int)$l[0]] = $l; }
        }
        return self::$cache['_leyes_por_n'][$n] ?? null;
    }

    /** @return list<array> hasta $max leyes del sitio con más palabras clave en común (mínimo 2), de la más reciente a la más vieja a igual coincidencia */
    public static function leyesRelacionadas(int $n, int $max = 6): array
    {
        $yo = self::leyIndice($n);
        $mias = $yo ? array_unique(array_filter(preg_split('/\s+/', (string)$yo[3]) ?: [], static fn($w) => mb_strlen($w) > 3 && !ctype_digit($w))) : [];
        if (count($mias) < 2) {
            return [];
        }
        $puntaje = [];
        foreach (self::leyes() as $l) {
            if ((int)$l[0] === $n) { continue; }
            $c = count(array_intersect($mias, preg_split('/\s+/', (string)$l[3]) ?: []));
            if ($c >= 2) { $puntaje[] = [$c, (int)$l[0], $l]; }
        }
        usort($puntaje, static fn($a, $b) => [$b[0], $b[1]] <=> [$a[0], $a[1]]);
        return array_map(static fn($x) => $x[2], array_slice($puntaje, 0, $max));
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

    /** @return list<array{declarante:string,familiar:string,vinculo:string,reciproco:bool,anio:int}> vínculos familiares declarados entre legisladores en ejercicio, confirmados por CUIT (scripts/verificar_parentescos_legisladores.py); claves como en ddjj.json */
    public static function parentescos(): array
    {
        return self::leer('parentescos');
    }

    /** @return array{sin_totales?:list<array{anio:int,tipo:string}>,ambiguo?:bool} casos en que la declaración figura pero no se publican sus totales, o el nombre es ambiguo */
    public static function ddjjEstado(string $camara, string $slug): array
    {
        return self::leer('ddjj_estado')[($camara === 'Senadores' ? 's:' : '') . $slug] ?? [];
    }

    /** @return array<string,array{n:int,total:float}> subsidios por año de un diputado actual */
    public static function subsidiosDiputado(string $slug): array
    {
        return self::leer('subsidios_diputados')[$slug] ?? [];
    }

    /** @return list<array> comisiones de Diputados con sus integrantes */
    public static function comisionesDiputados(): array
    {
        return self::leer('comisiones_diputados');
    }

    public static function comision(string $slug): ?array
    {
        foreach (self::comisionesDiputados() as $c) {
            if ($c['slug'] === $slug) {
                return $c;
            }
        }
        return null;
    }

    /** @return list<array{nombre:string,slug:string,cargo:string}> comisiones en las que está un diputado */
    public static function comisionesDe(string $slug): array
    {
        $out = [];
        foreach (self::comisionesDiputados() as $c) {
            foreach ($c['integrantes'] as $m) {
                if ($m['slug'] === $slug) {
                    $out[] = ['nombre' => $c['nombre'], 'slug' => $c['slug'], 'cargo' => $m['cargo']];
                }
            }
        }
        return $out;
    }

    /** @return array{semestres:array,desde_mandato_actual:string,actual:array,por_diputado:array} */
    public static function viajes(): array
    {
        return self::leer('viajes');
    }

    /**
     * Rutas de los pasajes de un diputado desde el 10/12/2025, ya ubicadas en el mapa (rutas_pasajes.json).
     * @return array{rutas:list<array>,total:int,sin_ubicar:int,misma_localidad:int,lugares:array,provincias:list<array>,meta:array}|null
     */
    public static function rutasPasajes(string $slug): ?array
    {
        $d = self::leer('rutas_pasajes');
        $r = $d['por_diputado'][$slug] ?? null;
        if (!$r || !$r['rutas']) {
            return null;
        }
        return $r + ['lugares' => $d['lugares'], 'provincias' => $d['provincias'], 'meta' => $d['_meta']];
    }

    /** @return list<array> */
    public static function misiones(): array
    {
        return self::leer('misiones');
    }

    public static function remuneraciones(): array
    {
        return self::leer('remuneraciones');
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
        if (!preg_match('/^S?\d{1,6}$/', $id)) {
            return null;
        }
        $ruta = RAIZ . "/storage/verificado/votaciones/actas/{$id}.json";
        $d = is_file($ruta) ? json_decode((string)file_get_contents($ruta), true) : null;
        return is_array($d) ? $d : null;
    }

    /** Estadísticas de votación desde el 10/12/2025 de un legislador actual (clave: slug; senadores 's:slug'). */
    public static function mandatoActual(string $camara, string $slug): ?array
    {
        $r = self::leer('votaciones_mandato_actual')[($camara === 'Senadores' ? 's:' : '') . $slug] ?? null;
        return $r && ($r['votaciones'] ?? 0) > 0 ? $r : null;
    }

    public static function mandatoActualMeta(): array
    {
        return self::leer('votaciones_mandato_actual')['_meta'] ?? [];
    }

    /** @return array{resumen:array,proyectos:list<array>}|null proyectos de un legislador actual (donde figura como autor o firmante) */
    public static function proyectos(string $slug, string $camara = 'Diputados'): ?array
    {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return null;
        }
        $ruta = RAIZ . '/storage/verificado/proyectos/' . ($camara === 'Senadores' ? 's-' : '') . "{$slug}.json";
        $d = is_file($ruta) ? json_decode((string)file_get_contents($ruta), true) : null;
        return is_array($d) ? $d : null;
    }

    /** Ejes temáticos de un legislador actual (clave: slug, o "s:slug" para senadores): proyectos y votos por eje, o null. */
    public static function ejes(string $camara, string $slug): ?array
    {
        $r = self::leer('ejes')[($camara === 'Senadores' ? 's:' : '') . $slug] ?? null;
        return is_array($r) ? $r : null;
    }

    /** IPC Nivel General Nacional del INDEC (base dic 2016 = 100): ["_meta" => ..., "mensual" => ["AAAA-MM" => índice]]. */
    public static function ipc(): array
    {
        return self::leer('ipc');
    }

    /** Dólar oficial del BCRA al cierre de cada año: ["_meta" => ..., "cierre_anual" => ["AAAA" => ["fecha" => ..., "valor" => pesos por dólar]]]. */
    public static function dolar(): array
    {
        return self::leer('dolar');
    }

    /** Dólar MEP de ArgentinaDatos (no oficial) al cierre de cada año, mismo formato que dolar(). */
    public static function dolarMep(): array
    {
        return self::leer('dolar_mep');
    }

    /** Coautorías de un senador en ejercicio con otros senadores en ejercicio desde el 10/12/2025, o null. */
    public static function coautorias(string $slug): ?array
    {
        $r = self::leer('coautorias_senado')['s:' . $slug] ?? null;
        return is_array($r) ? $r : null;
    }

    /** Aportes privados a agrupaciones políticas (CNE), por destino: ["_meta" => ..., "destinos" => [...]]. Sin datos de aportantes. */
    public static function aportes(): array
    {
        return self::leer('aportes');
    }

    /** Persona jurídica aportante por slug (de aportes.json). */
    public static function empresaAportante(string $slug): ?array
    {
        foreach (self::aportes()['empresas'] ?? [] as $e) { if ($e['slug'] === $slug) { return $e; } }
        return null;
    }

    public static function ejesMeta(): array
    {
        return self::leer('ejes')['_meta'] ?? [];
    }

    /** @return array{firmantes_disponibles:bool,anios:array<string,array>} proyectos por año de toda la cámara: total, legisladores en funciones y promedio por legislador */
    public static function proyectosPromedios(string $camara): array
    {
        $ruta = RAIZ . '/storage/verificado/proyectos/_promedios.json';
        $d = is_file($ruta) ? json_decode((string)file_get_contents($ruta), true) : null;
        return is_array($d) ? ($d[$camara] ?? []) : [];
    }

    public static function proyectosMeta(): array
    {
        $ruta = RAIZ . '/storage/verificado/proyectos/_meta.json';
        $d = is_file($ruta) ? json_decode((string)file_get_contents($ruta), true) : null;
        return is_array($d) ? $d : [];
    }

    /** @return list<array> estadísticas de un senador actual en mandatos anteriores al 10/12/2025 (una fila por mandato) */
    public static function senadoAnteriores(string $slug): array
    {
        $r = self::leer('votaciones_senado_anteriores')['s:' . $slug] ?? [];
        return array_values(array_filter($r, static fn($x) => ($x['votaciones'] ?? 0) > 0));
    }

    public static function senadoAnterioresMeta(): array
    {
        return self::leer('votaciones_senado_anteriores')['_meta'] ?? [];
    }

    /** Resumen de participación en votaciones nominales de un diputado actual (o null). */
    public static function participacion(string $slug): ?array
    {
        return self::leer('votaciones_diputados')[$slug] ?? null;
    }

    /** Ejecución presupuestaria de la Cámara de Diputados (programa 17) por ejercicio: ["_meta" => ..., "anios" => [...]]. */
    public static function presupuestoCamara(): array
    {
        return self::leer('presupuesto_camara');
    }

    /** Teléfono, dirección y fuente del conmutador de cada cámara (el interno de cada legislador va aparte). */
    public static function contactoCamara(string $camara): array
    {
        return self::leer('contacto_camaras')[$camara] ?? [];
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

    /** @return list<array{_meta:array,provincias:array}> elecciones nacionales (DINE, escrutinio provisorio), de la más reciente a la más antigua */
    public static function resultadosElecciones(): array
    {
        return self::leer('resultados_elecciones')['elecciones'] ?? [];
    }

    /** @return list<array{meta:array,datos:array}> resultado de la provincia en cada elección (diputados y, donde hubo, senadores nacionales) */
    public static function resultadosProvincia(string $slug): array
    {
        $out = [];
        foreach (self::resultadosElecciones() as $e) {
            if (isset($e['provincias'][$slug])) {
                $out[] = ['meta' => $e['_meta'], 'datos' => $e['provincias'][$slug]];
            }
        }
        return $out;
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

    /**
     * Resumen en lenguaje simple de la ley tratada en una votación (storage/editorial/resumenes_leyes.json), o null.
     * Se escribe a mano a partir del texto oficial; se aplica por cámara, texto del título y rango de fechas.
     */
    public static function resumenLey(array $cab): ?array
    {
        if (!isset(self::$cache['_resumenes'])) {
            $ruta = RAIZ . '/storage/editorial/resumenes_leyes.json';
            $d = is_file($ruta) ? json_decode((string)file_get_contents($ruta), true) : null;
            self::$cache['_resumenes'] = is_array($d['resumenes'] ?? null) ? $d['resumenes'] : [];
        }
        $t = self::normalizar((string)($cab['t'] ?? ''));
        foreach (self::$cache['_resumenes'] as $r) {
            foreach ($r['votaciones'] ?? [] as $m) {
                if (($cab['c'] ?? '') === $m['c'] && $cab['f'] >= $m['desde'] && $cab['f'] <= $m['hasta'] && str_contains($t, self::normalizar($m['titulo']))) {
                    return $r;
                }
            }
        }
        return null;
    }

    /** @return list<array> todos los resúmenes de leyes */
    public static function resumenesLeyes(): array
    {
        self::resumenLey(['t' => '', 'c' => '', 'f' => '']);
        return self::$cache['_resumenes'];
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
