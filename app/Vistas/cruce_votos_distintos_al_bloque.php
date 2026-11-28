<?php
use App\Datos;
/** @var array $cr @var array $info */
$esSen = ($_GET['camara'] ?? '') === 'senado';
$camara = $esSen ? 'Senadores' : 'Diputados';
$min = 20;
$disc = $cr['disciplina'] ?? [];
$filas = [];
foreach (Datos::legisladores($camara) as $l) {
    $s = $disc[($esSen ? 's:' : '') . $l['slug']] ?? null;
    if (!$s || $s['con_postura'] < $min) { continue; }
    $filas[] = ['l' => $l, 's' => $s, 'pct' => $s['distintos'] / $s['con_postura'] * 100];
}
[$orden, $dir] = orden_tabla(['nombre', 'pct', 'n'], 'pct');
$filas = ordenar_filas($filas, static fn($f) => match ($orden) {
    'nombre' => Datos::normalizar(($f['l']['apellido'] ?? '') . ' ' . $f['l']['nombre']),
    'n' => $f['s']['distintos'],
    default => $f['pct'],
}, $dir);
$indice = [];
foreach (Datos::votaciones() as $v) { $indice[$v['id']] = $v; }
$etqVoto = ['A' => 'a favor', 'N' => 'en contra', 'B' => 'abstención'];
?>
<nav class="migas" aria-label="Ubicación"><a href="/cruces">Cruces de datos</a> / Votos distintos a su bloque</nav>
<p class="antetitulo">Cruce de datos · <?= $esSen ? 'Senado' : 'Diputados' ?></p>
<h1 class="titulo">Votos distintos a su bloque.</h1>
<p class="bajada">En cuántas votaciones del mandato actual cada <?= $esSen ? 'senador' : 'diputado' ?> votó distinto a la mayoría de su bloque<?= $esSen ? '' : ', y en qué temas' ?>. Votar distinto no es bueno ni malo: es un dato de cómo votó.</p>

<nav class="pestanas" aria-label="Cámara" style="margin-top:1.5rem">
  <a href="/cruces/votos-distintos-al-bloque"<?= !$esSen ? ' aria-current="page"' : '' ?>>Diputados</a>
  <a href="/cruces/votos-distintos-al-bloque?camara=senado"<?= $esSen ? ' aria-current="page"' : '' ?>>Senado</a>
</nav>

<p class="conteo"><?= count($filas) ?> <?= $esSen ? 'senadores' : 'diputados' ?> con <?= $min ?> votos o más en votaciones donde su bloque tuvo una postura.</p>
<?php [$filasPag, $pagina, $paginas] = paginar($filas); ?>
<div class="tabla-caja"><table class="tabla tabla-cifras">
  <thead><tr>
    <?= th_orden('nombre', $esSen ? 'Senador' : 'Diputado', $orden, $dir, false) ?>
    <?= th_orden('pct', 'Votó distinto', $orden, $dir) ?>
    <?= th_orden('n', 'Veces', $orden, $dir) ?>
    <th scope="col"><?= $esSen ? 'Votaciones' : 'Temas y votaciones' ?></th>
  </tr></thead>
  <tbody>
  <?php foreach ($filasPag as $f): $lg = $f['l']; $s = $f['s']; ?>
    <tr>
      <td class="celda-nombre-td"><span class="celda-nombre"><?= avatar($lg, 32) ?><span><a href="<?= e(Datos::urlLegislador($lg)) ?>?tab=votaciones"><?= e($lg['nombre']) ?></a><span class="tenue pequeno block"><?= e($lg['bloque']) ?> · <?= e($lg['provincia']) ?></span></span></span></td>
      <td class="der num" data-etiqueta="Votó distinto"><span class="pct-barra"><?= e(pct_txt($s['distintos'], $s['con_postura'])) ?><span class="barra-mini" aria-hidden="true"><span style="width:<?= round($f['pct'], 1) ?>%;background:var(--acento)"></span></span></span></td>
      <td class="der num" data-etiqueta="Veces"><?= (int)$s['distintos'] ?> de <?= (int)$s['con_postura'] ?></td>
      <td class="td-bloque" data-etiqueta="<?= $esSen ? 'Votaciones' : 'Temas' ?>">
        <?php if (!$s['distintos']): ?><span class="tenue">Siempre como su bloque</span><?php else: ?>
        <?php $ej = array_filter($s['ejes'] ?? [], static fn($x) => $x[0] > 0); if ($ej): arsort($ej); ?>
          <span class="pequeno"><?= e(implode(' · ', array_map(static fn($k, $x) => $k . ' ' . $x[0] . '/' . $x[1], array_keys($ej), $ej))) ?></span>
        <?php endif; ?>
        <details class="cruce-detalle"><summary><?= (int)$s['distintos'] ?> <?= $s['distintos'] === 1 ? 'votación' : 'votaciones' ?></summary>
          <ul class="lista-simple pequeno">
          <?php foreach (array_slice($s['lista'], 0, 15) as [$vid, $voto, $post, $bl]): $v = $indice[$vid] ?? null; if (!$v) continue; ?>
            <li><a href="/votaciones/<?= e($vid) ?>"><?= e(titulo_corto_votacion($v['t'], 80)) ?></a> <span class="tenue">(<?= e(fecha_es($v['f'])) ?>: votó <?= e($etqVoto[$voto] ?? $voto) ?>, su bloque <?= e($etqVoto[$post] ?? $post) ?>)</span></li>
          <?php endforeach; ?>
          <?php if (count($s['lista']) > 15): ?><li class="tenue">y <?= count($s['lista']) - 15 ?> más en <a href="<?= e(Datos::urlLegislador($lg)) ?>?tab=votaciones">su perfil</a>.</li><?php endif; ?>
          </ul>
        </details>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?= nav_paginas($pagina, $paginas) ?>

<div class="aviso">
  <p style="margin:0 0 .5rem"><strong>Cómo leer este cruce.</strong></p>
  <ul class="lista-simple">
    <li><strong>Postura del bloque</strong>: el voto que eligió la mayoría estricta de los presentes del bloque en esa votación (el bloque que figura en el acta), con al menos dos presentes. Si hubo empate, el bloque quedó dividido y esa votación no cuenta. Es la misma regla de la página de cada votación y del dato «votó como la mayoría de su bloque» de cada perfil.</li>
    <li>Solo cuentan los votos emitidos (a favor, en contra o abstención): un ausente no «vota distinto». Las abstenciones cuentan como un voto más.</li>
    <?php if (!$esSen): ?><li><strong>Temas</strong>: el eje temático de la comisión cabecera del dictamen que se votó (según su número de orden del día), con la tabla de ejes por comisión que usa todo el sitio. «Salud 1/4» quiere decir que votó distinto en 1 de 4 votaciones de ese eje. Las votaciones sin orden del día o de comisiones sin eje no tienen tema.</li>
    <?php else: ?><li>El Senado no publica dictámenes en formato abierto: por eso no se muestran temas.</li><?php endif; ?>
    <li>No se muestra a quien tiene menos de <?= $min ?> votos con postura de su bloque, por ejemplo quien integra un bloque de una sola persona.</li>
  </ul>
</div>
<p class="fuente-pie">Fuentes: votaciones nominales de <?= $esSen ? 'el Senado de la Nación (senado.gob.ar)' : 'la Cámara de Diputados (votaciones.hcdn.gob.ar y ArgentinaDatos); dictámenes de la Cámara de Diputados (datos.hcdn.gob.ar)' ?>.</p>
