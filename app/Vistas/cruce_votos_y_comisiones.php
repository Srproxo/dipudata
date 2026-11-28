<?php
use App\Datos;
/** @var array $cr @var array $info */
$pc = $cr['presentismo_comisiones'] ?? [];
$min = 5;
$filas = []; $tot = ['t' => 0, 'p' => 0, 'ct' => 0, 'cp' => 0];
foreach (Datos::legisladores('Diputados') as $l) {
    $s = $pc[$l['slug']] ?? null;
    if (!$s) { continue; }
    $filas[] = ['l' => $l, 's' => $s,
        'pc' => $s['com_total'] >= $min ? $s['com_presente'] / $s['com_total'] * 100 : null,
        'pt' => $s['presente'] / $s['total'] * 100];
    $tot['t'] += $s['total']; $tot['p'] += $s['presente']; $tot['ct'] += $s['com_total']; $tot['cp'] += $s['com_presente'];
}
foreach ($filas as &$f) { $f['dif'] = $f['pc'] !== null ? $f['pc'] - $f['pt'] : null; } unset($f);
[$orden, $dir] = orden_tabla(['nombre', 'com', 'todas', 'dif', 'ncom'], 'nombre', 'asc');
$filas = ordenar_filas($filas, static fn($f) => match ($orden) {
    'com' => $f['pc'], 'todas' => $f['pt'], 'dif' => $f['dif'], 'ncom' => $f['s']['com_total'],
    default => Datos::normalizar(($f['l']['apellido'] ?? '') . ' ' . $f['l']['nombre']),
}, $dir);
$m = $cr['_meta'] ?? [];
$sinCom = count(array_filter($filas, static fn($f) => $f['s']['com_total'] < $min));
?>
<nav class="migas" aria-label="Ubicación"><a href="/cruces">Cruces de datos</a> / Votaciones de sus comisiones</nav>
<p class="antetitulo">Cruce de datos · Diputados</p>
<h1 class="titulo">Votaciones de los temas de sus comisiones.</h1>
<p class="bajada">Si cada diputado votó en las votaciones de los dictámenes que firmaron las comisiones que integra, comparado con todas las votaciones nominales del mandato actual.</p>

<dl class="ficha" style="max-width:36rem;margin-top:1.5rem">
  <div><dt>Votaciones de Diputados del mandato</dt><dd><?= (int)($m['votaciones_validas']['D'] ?? 0) ?></dd></div>
  <div><dt>Con orden del día y comisiones identificadas</dt><dd><?= (int)($m['diputados_con_od'] ?? 0) ?></dd></div>
  <div><dt>Todos los diputados votaron en</dt><dd><?= e(pct_txt($tot['p'], $tot['t'])) ?> de las votaciones</dd></div>
  <div><dt>En las de sus propias comisiones</dt><dd><?= e(pct_txt($tot['cp'], $tot['ct'])) ?></dd></div>
</dl>

<?php [$filasPag, $pagina, $paginas] = paginar($filas); ?>
<div class="tabla-caja" style="margin-top:2rem"><table class="tabla tabla-cifras">
  <thead><tr>
    <?= th_orden('nombre', 'Diputado', $orden, $dir, false) ?>
    <?= th_orden('ncom', 'Votaciones de sus comisiones', $orden, $dir) ?>
    <?= th_orden('com', 'Votó en las de sus comisiones', $orden, $dir) ?>
    <?= th_orden('todas', 'Votó en todas', $orden, $dir) ?>
    <?= th_orden('dif', 'Diferencia', $orden, $dir) ?>
  </tr></thead>
  <tbody>
  <?php foreach ($filasPag as $f): $lg = $f['l']; $s = $f['s']; ?>
    <tr>
      <td class="celda-nombre-td"><span class="celda-nombre"><?= avatar($lg, 32) ?><span><a href="<?= e(Datos::urlLegislador($lg)) ?>?tab=votaciones"><?= e($lg['nombre']) ?></a><span class="tenue pequeno block"><?= e($lg['bloque']) ?> · <?= count($s['comisiones']) ?> <?= count($s['comisiones']) === 1 ? 'comisión' : 'comisiones' ?></span></span></span></td>
      <td class="der num" data-etiqueta="Votaciones de sus comisiones"><?= (int)$s['com_total'] ?></td>
      <td class="der num" data-etiqueta="Votó en las de sus comisiones"><span class="celda-valor"><?= $f['pc'] !== null ? e(pct_txt($s['com_presente'], $s['com_total'], 0)) . '<span class="tenue pequeno block">' . (int)$s['com_presente'] . ' de ' . (int)$s['com_total'] . '</span>' : '<span class="tenue">menos de ' . $min . '</span>' ?></span></td>
      <td class="der num" data-etiqueta="Votó en todas"><span class="celda-valor"><?= e(pct_txt($s['presente'], $s['total'], 0)) ?><span class="tenue pequeno block"><?= (int)$s['presente'] ?> de <?= (int)$s['total'] ?></span></span></td>
      <td class="der num" data-etiqueta="Diferencia"><?= $f['dif'] !== null ? e(($f['dif'] >= 0 ? '+' : '−') . number_format(abs($f['dif']), 0, ',', '.')) . ' pts' : '—' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?= nav_paginas($pagina, $paginas) ?>

<div class="aviso">
  <p style="margin:0 0 .5rem"><strong>Cómo leer este cruce.</strong></p>
  <ul class="lista-simple">
    <li><strong>Votaciones de sus comisiones</strong>: las votaciones cuyo orden del día (el número que trae el título) tiene un dictamen firmado por alguna comisión que el diputado integra hoy, según el archivo oficial de dictámenes. Cuenta cada votación, también las «en particular» de un mismo dictamen.</li>
    <li>La composición de las comisiones es la que publica hoy la Cámara: si alguien se sumó o dejó una comisión durante el año, el cruce no lo sabe.</li>
    <li><strong>Votó</strong> quiere decir que emitió un voto (a favor, en contra o abstención). Quien preside la sesión no se cuenta. No votar no es lo mismo que no estar en el recinto: la Cámara registra al ausente en el momento de la votación, sin decir el motivo (licencia, comisión oficial, enfermedad u otro).</li>
    <li>Con menos de <?= $min ?> votaciones de sus comisiones (<?= (int)$sinCom ?> diputados) no se calcula el porcentaje: con tan pocas, un solo voto cambia mucho el número.</li>
    <?php if (!empty($m['od_no_hallados'])): ?><li><?= count($m['od_no_hallados']) ?> votaciones nombran un orden del día que todavía no figura en el archivo de dictámenes (es más nuevo que la última actualización); quedan fuera de «sus comisiones».</li><?php endif; ?>
  </ul>
</div>
<p class="fuente-pie">Fuentes: votaciones nominales de la Cámara de Diputados; dictámenes e integrantes de comisiones de la Cámara de Diputados (datos.hcdn.gob.ar).</p>
