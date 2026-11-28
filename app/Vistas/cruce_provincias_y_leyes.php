<?php
/** @var array $cr @var array $info */
$pr = $cr['provincias'] ?? [];
$filas = [];
foreach ($pr as $slug => $p) {
    $n = count($p['votaciones']); $igual = 0; $dp = $dt = $rp = $rt = 0; $porCam = ['D' => 0, 'S' => 0];
    foreach ($p['votaciones'] as $v) {
        $porCam[$v['c']]++;
        $d = $v['del']; $r = $v['resto'];
        $dt += $d['A'] + $d['N'] + $d['B'] + $d['U']; $dp += $d['A'] + $d['N'] + $d['B'];
        $rt += $r['A'] + $r['N'] + $r['B'] + $r['U']; $rp += $r['A'] + $r['N'] + $r['B'];
        $md = mayoria_cruce($d); $mr = mayoria_cruce($r);
        if ($md !== null && $md === $mr) { $igual++; }
    }
    $filas[] = compact('slug', 'p', 'n', 'igual', 'dp', 'dt', 'rp', 'rt', 'porCam');
}
usort($filas, static fn($a, $b) => strcmp(\App\Datos::normalizar($a['p']['nombre']), \App\Datos::normalizar($b['p']['nombre'])));
$total = array_sum(array_column($filas, 'n'));
?>
<nav class="migas" aria-label="Ubicación"><a href="/cruces">Cruces de datos</a> / Provincias</nav>
<p class="antetitulo">Cruce de datos · Ambas cámaras</p>
<h1 class="titulo">Provincias y las votaciones que las nombran.</h1>
<p class="bajada">Para cada provincia, las votaciones nominales cuyo título la nombra (una transferencia de inmuebles, una emergencia, un juzgado federal, una capital nacional) y cómo votó su delegación comparada con el resto de la cámara.</p>

<div class="tabla-caja" style="margin-top:2rem"><table class="tabla tabla-cifras">
  <thead><tr><th scope="col">Provincia</th><th scope="col" class="der">Votaciones</th><th scope="col" class="der">La delegación votó como el resto</th><th scope="col" class="der">Votó la delegación</th><th scope="col" class="der">Votó el resto</th></tr></thead>
  <tbody>
  <?php foreach ($filas as $f): ?>
    <tr>
      <td data-etiqueta="Provincia"><a href="/cruces/provincias-y-leyes/<?= e($f['slug']) ?>"><?= e($f['p']['nombre']) ?></a></td>
      <td class="der num" data-etiqueta="Votaciones"><span class="celda-valor"><?= (int)$f['n'] ?><span class="tenue pequeno block"><?= (int)$f['porCam']['D'] ?> en Diputados, <?= (int)$f['porCam']['S'] ?> en el Senado</span></span></td>
      <td class="der num" data-etiqueta="La delegación votó como el resto"><?= (int)$f['igual'] ?> de <?= (int)$f['n'] ?></td>
      <td class="der num" data-etiqueta="Votó la delegación"><?= e(pct_txt($f['dp'], $f['dt'], 0)) ?></td>
      <td class="der num" data-etiqueta="Votó el resto"><?= e(pct_txt($f['rp'], $f['rt'], 0)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p class="tenue pequeno" style="max-width:62ch"><?= number_format($total, 0, ',', '.') ?> casos (una votación que nombra a tres provincias cuenta en las tres). «Votó» es el porcentaje de votos emitidos sobre los integrantes de la delegación o del resto que figuran en cada acta.</p>

<div class="aviso">
  <p style="margin:0 0 .5rem"><strong>Cómo leer este cruce.</strong></p>
  <ul class="lista-simple">
    <li>Una votación entra si su título nombra a la provincia precedida de «provincia de», «prov.», «pcia.» o «provincias de» (o nombra a la Ciudad Autónoma de Buenos Aires). Así se evita confundir, por ejemplo, el submarino ARA San Juan o la batalla de Salta con la provincia. Por eso faltan votaciones que nombran solo una ciudad.</li>
    <li>Que el título nombre a una provincia no quiere decir que el proyecto afecte solo a esa provincia, ni que la beneficie o la perjudique: el cruce no evalúa el contenido.</li>
    <li><strong>La delegación</strong> son los legisladores de esa provincia que figuran en el acta de cada votación (diputados o senadores, según la cámara), con el distrito que registra el acta. <strong>Votó como el resto</strong>: la opción con más votos en la delegación fue la misma que la opción con más votos en el resto de la cámara.</li>
    <li>Período: todas las votaciones nominales con detalle que tiene el sitio (Diputados desde 2011 y Senado desde 2005), con los legisladores de cada momento.</li>
  </ul>
</div>
<p class="fuente-pie">Fuentes: votaciones nominales de la Cámara de Diputados (datos.hcdn.gob.ar, votaciones.hcdn.gob.ar y ArgentinaDatos) y del Senado de la Nación (senado.gob.ar).</p>
