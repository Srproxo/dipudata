<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Models\LegisladorModel;
use App\Models\LeyModel;

/**
 * Inyecta enlaces internos (Spoke -> Perfil, Ley, Eje) en el HTML.
 * - Solo reemplaza en nodos de texto (nunca dentro de <a>, <h1-6>, <script>, <style> ni atributos).
 * - Enlaza solo la primera mención de cada entidad (evita sobre-optimización).
 * - Prioriza cadenas más largas para evitar coincidencias parciales.
 */
class InternalLinker
{
    private const ZONAS_EXCLUIDAS = ['a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'script', 'style', 'code', 'pre'];

    /**
     * Enlace canónico a una ley.
     */
    public static function urlLey(string $slug): string
    {
        return '/ley/' . rawurlencode($slug);
    }

    /**
     * Enlaza automáticamente cualquier mención de una ley en un texto plano o HTML.
     */
    public static function linkearLey(string $nombreLey, ?string $textoVisible = null): string
    {
        $leyModel = new LeyModel();
        $ley = $leyModel->findByName($nombreLey) ?? $leyModel->findBySlug($nombreLey);
        $visible = $textoVisible ?? $nombreLey;

        if ($ley) {
            $url = self::urlLey($ley['slug']);
            $titulo = htmlspecialchars("Ficha técnica, votación y resumen de: {$ley['titulo']}", ENT_QUOTES, 'UTF-8');
            return sprintf(
                '<a href="%s" title="%s" class="text-blue-600 hover:text-blue-800 font-semibold underline decoration-blue-300 hover:decoration-blue-600 underline-offset-2 transition-colors">%s</a>',
                htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                $titulo,
                htmlspecialchars($visible, ENT_QUOTES, 'UTF-8')
            );
        }

        return htmlspecialchars($visible, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Inyecta enlaces a leyes en fragmentos HTML o de texto.
     */
    public static function injectLeyes(string $html, int $maxLinks = 10): string
    {
        $leyModel = new LeyModel();
        $catalogo = $leyModel->catalogo();

        $anclasLeyes = [];
        foreach ($catalogo as $ley) {
            $url = self::urlLey($ley['slug']);
            $anclaItem = [
                'url'    => $url,
                'titulo' => sprintf('Debate, resumen y votación nominal: %s (%s)', $ley['titulo_corto'], $ley['expediente']),
                'slug'   => $ley['slug'],
            ];
            $anclasLeyes[$ley['titulo']] = $anclaItem;
            $anclasLeyes[$ley['titulo_corto']] = $anclaItem;
            foreach ($ley['aliases'] as $alias) {
                if (!isset($anclasLeyes[$alias])) {
                    $anclasLeyes[$alias] = $anclaItem;
                }
            }
        }

        uksort($anclasLeyes, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $partes = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $pila = [];
        $enlazados = [];

        foreach ($partes as $i => $parte) {
            if ($parte === '') continue;
            if ($parte[0] === '<') {
                if (preg_match('#^<\s*(/)?\s*([a-z0-9]+)#i', $parte, $m)) {
                    $tag = strtolower($m[2]);
                    if (in_array($tag, self::ZONAS_EXCLUIDAS, true)) {
                        $m[1] === '/' ? array_pop($pila) : $pila[] = $tag;
                    }
                }
                continue;
            }
            if ($pila !== [] || count($enlazados) >= $maxLinks) {
                continue;
            }
            foreach ($anclasLeyes as $nombre => $a) {
                if (isset($enlazados[$a['slug']]) || count($enlazados) >= $maxLinks) {
                    continue;
                }
                $patron = '/(?<![\p{L}\p{N}])' . preg_quote(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'), '/') . '(?![\p{L}\p{N}])/u';
                $nuevo = preg_replace_callback($patron, function ($m) use ($a) {
                    return sprintf(
                        '<a href="%s" title="%s" class="text-blue-700 font-semibold underline decoration-blue-300 underline-offset-2 hover:decoration-blue-700">%s</a>',
                        htmlspecialchars($a['url'], ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars($a['titulo'], ENT_QUOTES, 'UTF-8'),
                        $m[0]
                    );
                }, $parte, 1, $count);
                if ($count > 0 && $nuevo !== null) {
                    $parte = $nuevo;
                    $enlazados[$a['slug']] = true;
                    $partes[$i] = $parte;
                    break;
                }
            }
        }

        return implode('', $partes);
    }

    /** @param array<int, array> $legisladores */
    public static function inject(string $html, array $legisladores, int $maxLinks = 10): array
    {
        $anclas = [];
        foreach ($legisladores as $leg) {
            $nombrePrincipal = (string)$leg['nombre'];
            $anclaItem = [
                'url'    => LegisladorModel::urlPerfil($leg),
                'titulo' => sprintf('Radiografía de %s (%s, %s)', $leg['nombre'], $leg['bloque'], $leg['provincia']),
                'leg'    => $leg,
            ];
            $anclas[$nombrePrincipal] = $anclaItem;

            if (!empty($leg['nombre_wiki'])) {
                $wikiNombre = str_replace('_', ' ', (string)$leg['nombre_wiki']);
                if ($wikiNombre !== $nombrePrincipal && !isset($anclas[$wikiNombre])) {
                    $anclas[$wikiNombre] = $anclaItem;
                }
            }

            // Variantes habituales sin segundo nombre (ej: Cristian Adrián Ritondo -> Cristian Ritondo)
            $tokens = explode(' ', $nombrePrincipal);
            if (count($tokens) >= 3) {
                $nombreCorto = $tokens[0] . ' ' . $tokens[count($tokens) - 1];
                if (!isset($anclas[$nombreCorto])) {
                    $anclas[$nombreCorto] = $anclaItem;
                }
                $nombreDosApellidos = $tokens[0] . ' ' . $tokens[1];
                if (!isset($anclas[$nombreDosApellidos])) {
                    $anclas[$nombreDosApellidos] = $anclaItem;
                }
            }
        }
        uksort($anclas, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $partes = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $pila = [];
        $enlazados = [];

        foreach ($partes as $i => $parte) {
            if ($parte === '') {
                continue;
            }
            if ($parte[0] === '<') {
                if (preg_match('#^<\s*(/)?\s*([a-z0-9]+)#i', $parte, $m)) {
                    $tag = strtolower($m[2]);
                    if (in_array($tag, self::ZONAS_EXCLUIDAS, true)) {
                        $m[1] === '/' ? array_pop($pila) : $pila[] = $tag;
                    }
                }
                continue;
            }
            if ($pila !== [] || count($enlazados) >= $maxLinks) {
                continue;
            }
            foreach ($anclas as $nombre => $a) {
                if (isset($enlazados[$nombre]) || count($enlazados) >= $maxLinks) {
                    continue;
                }
                $patron = '/(?<![\p{L}\p{N}])' . preg_quote(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'), '/') . '(?![\p{L}\p{N}])/u';
                $nuevo = preg_replace_callback($patron, function ($m) use ($a) {
                    return sprintf(
                        '<a href="%s" title="%s" class="text-blue-700 font-medium underline decoration-blue-200 underline-offset-2 hover:decoration-blue-600">%s</a>',
                        htmlspecialchars($a['url'], ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars($a['titulo'], ENT_QUOTES, 'UTF-8'),
                        $m[0]
                    );
                }, $parte, 1, $count);
                if ($count > 0 && $nuevo !== null) {
                    // Re-segmentar para no volver a reemplazar dentro del <a> recién creado
                    $parte = $nuevo;
                    $enlazados[$nombre] = $a['leg'];
                    $partes[$i] = self::protegerEnlaces($parte, $anclas, $enlazados, $maxLinks);
                    break;
                }
            }
        }

        return [
            'html'      => implode('', $partes),
            'enlazados' => array_values($enlazados),
        ];
    }

    /** Continúa enlazando otros nombres en el mismo nodo, sin tocar el <a> ya insertado. */
    private static function protegerEnlaces(string $fragmento, array $anclas, array &$enlazados, int $maxLinks): string
    {
        $sub = preg_split('/(<a\b[^>]*>.*?<\/a>)/us', $fragmento, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$fragmento];
        foreach ($sub as $j => $trozo) {
            if (str_starts_with($trozo, '<a')) {
                continue;
            }
            foreach ($anclas as $nombre => $a) {
                if (isset($enlazados[$nombre]) || count($enlazados) >= $maxLinks) {
                    continue;
                }
                $patron = '/(?<![\p{L}\p{N}])' . preg_quote(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'), '/') . '(?![\p{L}\p{N}])/u';
                $nuevo = preg_replace_callback($patron, fn($m) => sprintf(
                    '<a href="%s" title="%s" class="text-blue-700 font-medium underline decoration-blue-200 underline-offset-2 hover:decoration-blue-600">%s</a>',
                    htmlspecialchars($a['url'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($a['titulo'], ENT_QUOTES, 'UTF-8'),
                    $m[0]
                ), $trozo, 1, $count);
                if ($count > 0 && $nuevo !== null) {
                    $enlazados[$nombre] = $a['leg'];
                    $sub[$j] = self::protegerEnlaces($nuevo, $anclas, $enlazados, $maxLinks);
                    break;
                }
            }
        }
        return implode('', $sub);
    }

    /** Helper de texto ancla manual para usar en vistas: anchor('Martín Lousteau') */
    public static function anchor(string $nombre, array $legisladores, ?string $texto = null): string
    {
        foreach ($legisladores as $leg) {
            if (mb_strtolower($leg['nombre']) === mb_strtolower($nombre)) {
                return sprintf(
                    '<a href="%s" class="text-blue-700 font-medium hover:underline">%s</a>',
                    htmlspecialchars(LegisladorModel::urlPerfil($leg), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($texto ?? $leg['nombre'], ENT_QUOTES, 'UTF-8')
                );
            }
        }
        return htmlspecialchars($texto ?? $nombre, ENT_QUOTES, 'UTF-8');
    }
}
