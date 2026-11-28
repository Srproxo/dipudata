<?php
use App\Datos;
/** @var array $cr @var array $info */
$pp = $cr['pasajes_presentismo'] ?? [];
$m = $cr['_meta']['pasajes'] ?? [];
$filas = []; $puntos = [];
foreach (Datos::legisladores('Diputados') as $l) {
    $s = $pp[$l['slug']] ?? null;
    if (!$s) { continue; }
    $pct = $s['presentes'] / $s['votaciones'] * 100;
    $filas[] = ['l' => $l, 's' => $s, 'pct' => $pct];
    $puntos[] = [$s['pasajes'], $pct, $l['nombre'] . ' (' . $l['provincia'] . '): ' . $s['pasajes'] . ' pasajes, votó en el ' . number_format($pct, 0, ',', '') . ' %', Datos::urlLegislador($l)];
}
[$orden, $dir] = orden_tabla(['nombre', 'provincia', 'pasajes', 'pct'], 'nombre', 'asc');
$filas = ordenar_filas($filas, static fn($f) => match ($orden) {
    'provincia' => Datos::normalizar($f['l']['provincia']) . ' ' . Datos::normalizar($f['l']['apellido'] ?? $f['l']['nombre']),
    'pasajes' => $f['s']['pasajes'], 'pct' => $f['pct'],
    default => Datos::normalizar(($f['l']['apellido'] ?? '') . ' ' . $f['l']['nombre']),
}, $dir);
// Mediana por provincia: los pasajes dependen sobre todo de la distancia.
$porProv = [];
foreach ($filas as $f) { $porProv[$f['l']['provincia']][] = $f['s']['pasajes']; }
ksort($porProv);
$hasta = !empty($m['fin_periodo']) ? fecha_es($m['fin_periodo']) : '';
?>
<nav class="migas" aria-label="Ubicación"><a href="/cruces">Cruces de datos</a> / Pasajes y votaciones</nav>
<p class="antetitulo">Cruce de datos · Diputados</p>
<h1 class="titulo">Pasajes emitidos y votaciones.</h1>
<p class="bajada">Los pasajes aéreos y terrestres que la Cámara emitió para cada diputado entre el 10 de diciembre de 2025 y el <?= e($hasta) ?>, al lado de en cuántas votaciones nominales de ese mismo período votó.</p>

<figure class="grafico-caja grafico-ancho" style="margin-top:2rem">
  <?= svg_dispersion($puntos, 'Cada punto es un diputado: pasajes emitidos (horizontal) y porcentaje de votaciones en que votó (vertical).', 'Pasajes emitidos', 'Votó en') ?>
  <figcaption class="tenue pequeno">Cada punto es un diputado; pasá el cursor o tocá un punto para ver quién es. No hay una línea de tendencia a propósito: los pasajes dependen de la distancia a la provincia, no de las votaciones.</figcaption>
</figure>

<?php [$filasPag, $pagina, $paginas] = paginar($filas); ?>
<div class="tabla-caja"><table class="tabla tabla-cifras">
  <thead><tr>
    <?= th_orden('nombre', 'Diputado', $orden, $dir, false) ?>
    <?= th_orden('provincia', 'Provincia', $orden, $dir, false) ?>
    <?= th_orden('pasajes', 'Pasajes emitidos', $orden, $dir) ?>
    <?= th_orden('pct', 'Votó en', $orden, $dir) ?>
  </tr></thead>
  <tbody>
  <?php foreach ($filasPag as $f): $lg = $f['l']; $s = $f['s']; ?>
    <tr>
      <td class="celda-nombre-td"><span class="celda-nombre"><?= avatar($lg, 32) ?><span><a href="<?= e(Datos::urlLegislador($lg)) ?>?tab=mas"><?= e($lg['nombre']) ?></a><span class="tenue pequeno block"><?= e($lg['bloque']) ?></span></span></span></td>
      <td data-etiqueta="Provincia"><?= e($lg['provincia']) ?></td>
      <td class="der num" data-etiqueta="Pasajes emitidos"><span class="celda-valor"><?= (int)$s['pasajes'] ?><span class="tenue pequeno block"><?= (int)$s['aereo'] ?> aéreos, <?= (int)$s['terrestre'] ?> terrestres</span></span></td>
      <td class="der num" data-etiqueta="Votó en"><span class="celda-valor"><?= e(pct_txt($s['presentes'], $s['votaciones'], 0)) ?><span class="tenue pequeno block"><?= (int)$s['presentes'] ?> de <?= (int)$s['votaciones'] ?></span></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?= nav_paginas($pagina, $paginas) ?>

<h2 class="seccion-titulo" style="margin-top:2.5rem">Pasajes por provincia</h2>
<p class="tenue" style="max-width:62ch">La mediana de pasajes emitidos por diputado en cada provincia muestra cuánto pesa la distancia.</p>
<div class="tabla-caja"><table class="tabla tabla-cifras tabla-chica">
  <thead><tr><th scope="col">Provincia</th><th scope="col" class="der">Diputados</th><th scope="col" class="der">Mediana de pasajes</th></tr></thead>
  <tbody>
  <?php foreach ($porProv as $p => $xs): ?>
    <tr><td data-etiqueta="Provincia"><?= e($p) ?></td><td class="der num" data-etiqueta="Diputados"><?= count($xs) ?></td><td class="der num" data-etiqueta="Mediana de pasajes"><?= e(number_format((float)mediana($xs), 0, ',', '.')) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<div class="aviso">
  <p style="margin:0 0 .5rem"><strong>Cómo leer este cruce.</strong></p>
  <ul class="lista-simple">
    <li>Son <strong>pasajes emitidos</strong> por la Cámara, no viajes realizados: la propia Cámara aclara que un pasaje emitido no implica que el viaje se haya hecho.</li>
    <li>Los pasajes se cuentan por el mes que informa la Cámara, de diciembre de 2025 a <?= e(mes_es((string)($m['hasta_mes'] ?? ''))) ?>; las votaciones, del 10 de diciembre de 2025 al <?= e($hasta) ?>.</li>
    <li>Un diputado de una provincia lejana necesita más pasajes para ir y volver del Congreso. Comparar a diputados de distintas provincias por su cantidad de pasajes no dice nada sobre su trabajo.</li>
    <li><strong>Votó</strong> quiere decir que emitió un voto (a favor, en contra o abstención). La Cámara no publica el motivo de una ausencia.</li>
  </ul>
</div>
<p class="fuente-pie">Fuentes: Viajes nacionales de la Cámara de Diputados (datos.hcdn.gob.ar); votaciones nominales de la Cámara de Diputados (votaciones.hcdn.gob.ar y ArgentinaDatos).</p>
