<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Models\LegisladorModel;

/**
 * Inyecta enlaces internos (Spoke -> Perfil) en el HTML de artículos del blog.
 * - Solo reemplaza en nodos de texto (nunca dentro de <a>, <h1-6>, <script>, <style> ni atributos).
 * - Enlaza solo la primera mención de cada legislador (evita sobre-optimización).
 * - Prioriza nombres más largos para evitar coincidencias parciales.
 */
class InternalLinker
{
    private const ZONAS_EXCLUIDAS = ['a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'script', 'style', 'code', 'pre'];

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
