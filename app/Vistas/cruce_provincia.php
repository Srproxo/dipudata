<?php
/** @var array $pv @var string $slug */
$etq = ['A' => 'a favor', 'N' => 'en contra', 'B' => 'abstención', 'U' => 'ausentes'];
$fila = static function (array $c): string {
    $out = [];
    foreach (['A' => 'a favor', 'N' => 'en contra', 'B' => 'abst.', 'U' => 'aus.'] as $k => $t) { if ($c[$k]) { $out[] = '<span class="voto voto-' . $k . '">' . (int)$c[$k] . ' ' . $t . '</span>'; } }
    return implode(' ', $out) ?: '<span class="tenue">—</span>';
};
?>
<nav class="migas" aria-label="Ubicación"><a href="/cruces">Cruces de datos</a> / <a href="/cruces/provincias-y-leyes">Provincias</a> / <?= e($pv['nombre']) ?></nav>
<p class="antetitulo">Cruce de datos · <?= e($pv['nombre']) ?></p>
<h1 class="titulo">Votaciones que nombran a <?= e($pv['nombre']) ?>.</h1>
<p class="bajada"><?= count($pv['votaciones']) ?> votaciones nominales cuyo título nombra a <?= e($pv['nombre']) ?>, de la más reciente a la más antigua: cómo votó su delegación y cómo votó el resto de la cámara. Ver también <a href="/congreso/provincias/<?= e($slug) ?>">sus legisladores actuales</a>.</p>

<div class="tabla-caja" style="margin-top:2rem"><table class="tabla tabla-cifras tabla-provincia-votos">
  <thead><tr><th scope="col">Votación</th><th scope="col">Delegación de <?= e($pv['nombre']) ?></th><th scope="col">Resto de la cámara</th><th scope="col">Resultado</th></tr></thead>
  <tbody>
  <?php foreach ($pv['votaciones'] as $v): $md = mayoria_cruce($v['del']); $mr = mayoria_cruce($v['resto']); ?>
    <tr>
      <td class="td-bloque" data-etiqueta="Votación"><span><a href="/votaciones/<?= e($v['id']) ?>"><?= e(titulo_corto_votacion($v['t'], 140)) ?></a><span class="tenue pequeno block"><?= $v['c'] === 'S' ? 'Senado' : 'Diputados' ?> · <?= e(fecha_es($v['f'])) ?><?= $v['otras'] ? ' · también nombra a ' . e(implode(', ', $v['otras'])) : '' ?></span></span></td>
      <td class="td-bloque" data-etiqueta="Delegación"><span class="celda-valor"><?= $fila($v['del']) ?><?php if ($md !== null && $mr !== null && $md !== $mr): ?><span class="pequeno block"><strong>Votó distinto al resto</strong></span><?php endif; ?></span></td>
      <td class="td-bloque" data-etiqueta="Resto de la cámara"><?= $fila($v['resto']) ?></td>
      <td class="td-bloque" data-etiqueta="Resultado"><?= resultado_votacion($v['res']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p class="nota-metodo tenue pequeno">Una votación entra si su título nombra a la provincia precedida de «provincia de», «prov.», «pcia.» o «provincias de». Que el título la nombre no dice que el proyecto la beneficie o la perjudique. La delegación son los legisladores de la provincia que figuran en el acta de cada votación. Detalle en <a href="/cruces/provincias-y-leyes">el cruce por provincias</a>.</p>
<p class="fuente-pie">Fuentes: votaciones nominales de la Cámara de Diputados y del Senado de la Nación.</p>
