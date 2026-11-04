<?php
/** @var array $pres */
$anios = $pres['anios'] ?? [];
$nombres = $pres['_meta']['incisos'] ?? [];
$cerrados = array_values(array_filter($anios, static fn($a) => $a['cerrado']));
$parcial = null;
foreach ($anios as $a) { if (!$a['cerrado']) { $parcial = $a; } }
$ult = $cerrados ? end($cerrados) : null;
$mm = static fn(float $v): string => '$ ' . number_format($v / 1e6, 0, ',', '.') . ' M';          // millones de pesos
$pct = static fn(float $a, float $b): string => $b > 0 ? number_format($a * 100 / $b, 1, ',', '.') . ' %' : '—';
$hoy = static fn(array $a, float $v): ?float => $a['factor_ipc'] ? $v * $a['factor_ipc'] : null;
$serieHoy = []; $serieNom = [];
foreach ($cerrados as $a) {
    $serieNom[(string)$a['anio']] = (float)$a['devengado'];
    if ($a['factor_ipc']) { $serieHoy[(string)$a['anio']] = $hoy($a, (float)$a['devengado']); }
}
$ultMes = $pres['_meta']['ipc_ultimo_mes'] ?? '';
?>
<p class="antetitulo">Cámara de Diputados</p>
<h1 class="titulo">Cuánto gasta la Cámara.</h1>
<p class="bajada">La Cámara de Diputados publica cuánto tiene autorizado gastar cada año y cuánto gastó, por tipo de gasto. Acá están sus propias cifras de 2016 a hoy, en pesos de cada año y en pesos de hoy para poder compararlas.</p>

<?php if (!$ult): ?>
  <p class="vacio-txt">Todavía no hay datos de ejecución presupuestaria cargados.</p>
<?php else: $tot = $ult['devengado']; ?>
<p class="aviso"><strong>En simple.</strong> En <?= (int)$ult['anio'] ?> la Cámara tenía autorizados <?= e($mm($ult['credito'])) ?> y gastó <?= e($mm($ult['devengado'])) ?> (<?= e($pct($ult['devengado'], $ult['credito'])) ?> de lo autorizado). La mayor parte fue personal: <?= e($pct($ult['incisos']['1'][2], $tot)) ?> del gasto se destinó a gastos en personal (sueldos y cargas sociales de quienes trabajan en la Cámara).
  <?php if ($parcial): ?>En <?= (int)$parcial['anio'] ?>, hasta el mes <?= (int)$parcial['trimestre'] * 3 ?>, ya se gastaron <?= e($mm($parcial['devengado'])) ?>.<?php endif; ?>
  Son cifras de la Cámara como institución: no son lo que cobra un legislador (eso está en <a href="/remuneraciones">Cuánto cobran</a>) y no se puede atribuir a ninguno.</p>

<section class="seccion" aria-labelledby="p-evo">
  <h2 id="p-evo" class="seccion-titulo">Cuánto gastó cada año</h2>
  <?php if (count($serieHoy) >= 2): ?>
  <figure class="grafico-caja">
    <p class="sub-titulo">Gasto devengado de cada año, en pesos de <?= e(mes_es($ultMes)) ?></p>
    <?= svg_linea($serieHoy, 'Gasto devengado de la Cámara de Diputados de ' . array_key_first($serieHoy) . ' a ' . array_key_last($serieHoy) . ', en pesos de ' . mes_es($ultMes) . ' con el IPC del INDEC') ?>
    <figcaption class="tenue pequeno">Lo gastado en cada año (devengado), llevado a pesos de <?= e(mes_es($ultMes)) ?> con el IPC del INDEC: importe del año × IPC del último mes ÷ promedio del IPC de ese año. 2016 no se ajusta porque la serie oficial del IPC empieza en diciembre de ese año. Un mismo importe en pesos vale distinto en cada año: por eso se muestra así y no en pesos corrientes.</figcaption>
  </figure>
  <?php endif; ?>
  <div class="tabla-caja"><table class="tabla tabla-cifras">
    <caption class="sr-solo">Presupuesto y gasto de la Cámara de Diputados por año</caption>
    <thead><tr>
      <th scope="col">Año</th><th scope="col" class="der">Autorizado</th><th scope="col" class="der">Gastado (devengado)</th><th scope="col" class="der">Gastado sobre lo autorizado</th><th scope="col" class="der">Gastado, en pesos de hoy</th>
    </tr></thead>
    <tbody>
    <?php foreach (array_reverse($anios) as $a): $h = $hoy($a, (float)$a['devengado']); ?>
      <tr>
        <th scope="row"><?= (int)$a['anio'] ?><?= $a['cerrado'] ? '' : ' <span class="tenue">(hasta el ' . ((int)$a['trimestre'] * 3) . 'º mes)</span>' ?></th>
        <td class="der num" data-etiqueta="Autorizado"><?= e($mm($a['credito'])) ?></td>
        <td class="der num" data-etiqueta="Gastado"><?= e($mm($a['devengado'])) ?></td>
        <td class="der num" data-etiqueta="Sobre lo autorizado"><?= e($pct($a['devengado'], $a['credito'])) ?></td>
        <td class="der num" data-etiqueta="En pesos de hoy"><?= $h !== null && $a['cerrado'] ? e($mm($h)) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="tenue pequeno">M = millones de pesos. "Autorizado" es el crédito vigente: el presupuesto del año con sus modificaciones. "Gastado" es el devengado: el gasto reconocido por servicios o bienes recibidos, aunque todavía no se haya pagado. El año en curso no se compara con los años cerrados, porque cuenta solo una parte del año.<?php if (array_filter($anios, static fn($a) => !empty($a['importes_reservados']))): ?> En 2018 el archivo oficial carga los importes en la etapa "reservado" y no en "consumido": se suman las dos y se muestran tal cual.<?php endif; ?></p>
</section>

<section class="seccion" aria-labelledby="p-en-que">
  <h2 id="p-en-que" class="seccion-titulo">En qué se gasta</h2>
  <p class="tenue">Reparto del gasto devengado por tipo de gasto (lo que el presupuesto llama "incisos").</p>
  <div class="tabla-caja"><table class="tabla tabla-cifras">
    <caption class="sr-solo">Gasto de la Cámara de Diputados por tipo de gasto</caption>
    <thead><tr><th scope="col">Tipo de gasto</th>
      <?php foreach (array_filter([$ult, $parcial]) as $a): ?><th scope="col" class="der"><?= (int)$a['anio'] ?><?= $a['cerrado'] ? '' : ' (parcial)' ?></th><th scope="col" class="der">% del total</th><?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($nombres as $n => $nombre): ?>
      <tr><th scope="row"><?= e($nombre) ?></th>
        <?php foreach (array_filter([$ult, $parcial]) as $a): ?>
        <td class="der num" data-etiqueta="<?= (int)$a['anio'] ?>"><?= e($mm($a['incisos'][$n][2])) ?></td><td class="der num" data-etiqueta="% del total"><?= e($pct($a['incisos'][$n][2], $a['devengado'])) ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
      <tr><th scope="row">Total</th>
        <?php foreach (array_filter([$ult, $parcial]) as $a): ?><td class="der num"><b><?= e($mm($a['devengado'])) ?></b></td><td class="der num">100 %</td><?php endforeach; ?>
      </tr>
    </tbody>
  </table></div>
  <details class="ver-datos">
    <summary>Ver todos los años por tipo de gasto</summary>
    <div class="tabla-caja"><table class="tabla tabla-cifras">
      <thead><tr><th scope="col">Año</th><?php foreach ($nombres as $nombre): ?><th scope="col" class="der"><?= e($nombre) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach (array_reverse($anios) as $a): ?>
        <tr><th scope="row"><?= (int)$a['anio'] ?><?= $a['cerrado'] ? '' : ' (parcial)' ?></th>
          <?php foreach ($nombres as $n => $nombre): ?><td class="der num" data-etiqueta="<?= e($nombre) ?>"><?= e($pct($a['incisos'][$n][2], $a['devengado'])) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="tenue pequeno">Porcentaje del gasto devengado de cada año.</p>
  </details>
</section>

<section class="seccion" aria-labelledby="p-leer">
  <h2 id="p-leer" class="seccion-titulo">Cómo leer estas cifras</h2>
  <ul class="lista-simple">
    <li>Son cifras de <strong>una sola partida</strong>: el programa 17, "Formación y Sanción Legislativa", que es el que la Cámara de Diputados publica como ejecución presupuestaria. No incluye al Senado ni a otros organismos del Congreso.</li>
    <li>Que el gasto suba en pesos no quiere decir que sea más alto en términos reales: por eso la comparación se hace en pesos de hoy.</li>
    <li>"Gastos en personal" incluye a todo el personal de la Cámara y las cargas sociales. Ni la Cámara ni este sitio informan acá cuánto va a cada legislador o a su equipo.</li>
    <li>Estas cifras dicen cuánto se gastó y en qué rubro. No dicen si el gasto fue necesario, alto o bajo, ni lo comparan con ninguna otra institución.</li>
  </ul>
</section>
<?php endif; ?>

<p class="fuente-pie">Fuente: <?= e($pres['_meta']['fuente'] ?? 'Cámara de Diputados de la Nación') ?>, conjunto "Ejecución presupuestaria de la Cámara de Diputados de la Nación" en <a href="https://datos.hcdn.gob.ar/dataset/ejecucion-presupuestaria-de-la-camara-de-diputados-de-la-nacion" rel="noopener">datos.hcdn.gob.ar</a>. Cierre de cada ejercicio (cuarto trimestre acumulado) y último acumulado del año en curso. Pesos de hoy con el IPC del INDEC. <a href="/metodologia#presupuesto">Cómo se calcula</a>.</p>
