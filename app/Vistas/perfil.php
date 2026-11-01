<?php
use App\Datos;
/** @var array $l legislador */
$esSen = $l['camara'] === 'Senadores';
$bloque = null;
foreach (Datos::bloques($l['camara']) as $b) { if ($b['nombre_oficial'] === $l['bloque_oficial']) { $bloque = $b; break; } }
$provSlug = slug_provincia($l['provincia']);
$mismoBloque = $bloque ? count(Datos::miembros($bloque)) - 1 : 0;
$mismaProv = array_values(array_filter(Datos::legisladores($l['camara']), static fn($x) => $x['provincia'] === $l['provincia'] && $x['slug'] !== $l['slug']));
$cargo = $esSen ? 'Senador/a nacional' : 'Diputado/a nacional';
?>
<nav class="migas" aria-label="Ubicación"><a href="/">Legisladores</a> <span aria-hidden="true">/</span> <span><?= $esSen ? 'Senado' : 'Diputados' ?></span></nav>

<header class="perfil-cabeza">
  <?= avatar($l, 112) ?>
  <div>
    <p class="antetitulo"><?= e($cargo) ?> por <a href="/congreso/provincias/<?= e($provSlug) ?>"><?= e($l['provincia']) ?></a></p>
    <h1 class="titulo titulo-perfil"><?= e($l['nombre']) ?></h1>
    <p class="perfil-bloque"><?= etiqueta_bloque($l) ?>
      <?php if ($bloque): ?><span class="tenue">· <a href="<?= e(Datos::urlBloque($bloque)) ?>">ver los <?= (int)$bloque['cantidad'] ?> integrantes del bloque</a></span><?php endif; ?>
    </p>
  </div>
</header>


<?php
// Resumen: quién es, qué votó, qué proyectos tiene. Todo sale de los mismos datos que se detallan más abajo.
$rMa = Datos::mandatoActual($l['camara'], $l['slug']);
$rAnt = $esSen ? Datos::senadoAnteriores($l['slug']) : [];
$rPar = !$esSen ? Datos::participacion($l['slug']) : null;
$rPr = Datos::proyectosResumen($l['camara'], $l['slug']);
$rDj = Datos::ddjj($l['camara'], $l['slug']);
$pct = static fn($x) => number_format((float)$x, 1, ',', '') . '%';
$secciones = array_filter([
    'mandato-actual' => $rMa ? 'Votaciones' : null,
    'sen-anteriores' => $rAnt ? 'Mandatos anteriores' : null,
    'participacion' => $rPar ? 'Votaciones ' . substr($rPar['desde'], 0, 4) . '–' . substr($rPar['hasta'], 0, 4) : null,
    'proyectos' => 'Proyectos',
    'ddjj' => $rDj ? 'Declaración jurada' : null,
    'comisiones' => !$esSen && Datos::comisionesDe($l['slug']) ? 'Comisiones' : null,
    'pasajes' => !$esSen && (isset(Datos::viajes()['actual'][$l['slug']]) || !empty(Datos::viajes()['por_diputado'][$l['slug']])) ? 'Pasajes' : null,
    'ficha' => 'Ficha y fuentes',
]);
?>
<section class="resumen" aria-labelledby="resumen-t">
  <h2 id="resumen-t" class="resumen-titulo">En resumen</h2>
  <ul>
    <li><b>Quién es.</b> Integra <?= $esSen ? 'el Senado' : 'la Cámara de Diputados' ?> por <?= e($l['provincia']) ?><?= $l['inicio'] ? ' desde el ' . e(fecha_es($l['inicio'])) : '' ?><?= $l['mandato'] ? ' (mandato ' . e($l['mandato']) . ')' : '' ?>, en el bloque <?= e($l['bloque']) ?>.<?php
      if ($rAnt) { $prev = array_filter($rAnt, fn($m) => $m['mandato_hasta'] !== ''); if ($prev) { echo ' Antes ya había sido senador/a: ' . count($prev) . ' ' . (count($prev) === 1 ? 'mandato' : 'mandatos') . ' con votaciones registradas desde ' . e(substr(max(min(array_column($prev, 'mandato_desde')), (string)(Datos::senadoAnterioresMeta()['desde'] ?? '')), 0, 4)) . '.'; } }
      elseif (!$esSen && count($l['trayectoria'] ?? []) > 1) { echo ' Ya había sido diputado/a: la Cámara registra ' . (count($l['trayectoria']) - 1) . ' ' . (count($l['trayectoria']) - 1 === 1 ? 'mandato anterior' : 'mandatos anteriores') . ' desde 2007.'; }
    ?></li>
    <li><b>Qué votó.</b> <?php if ($rMa): ?>Desde el 10 de diciembre de 2025 votó en <?= (int)$rMa['emitio'] ?> de <?= (int)$rMa['votaciones'] ?> votaciones nominales (<?= e($pct($rMa['voto_pct'])) ?>): <?= (int)$rMa['afirmativos'] ?> a favor, <?= (int)$rMa['negativos'] ?> en contra y <?= (int)$rMa['abstenciones'] ?> <?= (int)$rMa['abstenciones'] === 1 ? 'abstención' : 'abstenciones' ?>.<?php if ($rMa['coincidencia_bloque_pct'] !== null): ?> Votó igual que la mayoría de su bloque en el <?= e($pct($rMa['coincidencia_bloque_pct'])) ?> de los casos.<?php endif; ?><?php else: ?>Todavía no hay votaciones nominales registradas en su mandato actual.<?php endif; ?></li>
    <li><b>Qué proyectos tiene.</b> <?php if ($rPr): ?><?php if ($esSen): ?>Figura como autor o coautor de <?= number_format((int)$rPr['total'], 0, ',', '.') ?> proyectos en el buscador del Senado, <?= number_format((int)$rPr['leyes'], 0, ',', '.') ?> de ley.<?php else: $ok = ($rPr['resultados']['SANCIONADO'] ?? 0) + ($rPr['resultados']['MEDIA SANCION'] ?? 0) + ($rPr['resultados']['APROBADO'] ?? 0); ?>Presentó <?= number_format((int)$rPr['total'], 0, ',', '.') ?> proyectos como primer firmante (<?= number_format((int)$rPr['leyes'], 0, ',', '.') ?> de ley); <?= $ok ?> <?= $ok === 1 ? 'tuvo' : 'tuvieron' ?> alguna aprobación registrada.<?php endif; ?><?php else: ?>No hay proyectos registrados a su nombre en los datos oficiales.<?php endif; ?></li>
  </ul>
  <nav class="saltos" aria-label="Secciones del perfil">
    <?php foreach ($secciones as $id => $t): ?><a href="#<?= e($id) ?>"><?= e($t) ?></a><?php endforeach; ?>
  </nav>
</section>


<?php $ma = Datos::mandatoActual($l['camara'], $l['slug']); $mm = Datos::mandatoActualMeta(); ?>
<?php if ($ma): ?>
<section class="seccion" aria-labelledby="mandato-actual">
  <h2 id="mandato-actual" class="seccion-titulo">Votaciones nominales desde el 10 de diciembre de 2025</h2>
  <dl class="participacion">
    <div><dt>Votaciones consideradas</dt><dd><?= (int)$ma['votaciones'] ?></dd></div>
    <div><dt>Emitió su voto</dt><dd><?= (int)$ma['emitio'] ?></dd></div>
    <div><dt>No votó</dt><dd><?= (int)$ma['ausente'] ?></dd></div>
    <?php if ($ma['voto_pct'] !== null): ?><div><dt>Votó en el</dt><dd><?= e(number_format((float)$ma['voto_pct'], 1, ',', '')) ?>%</dd></div><?php endif; ?>
    <?php if ($ma['coincidencia_bloque_pct'] !== null): ?><div><dt>Votó como la mayoría de su bloque</dt><dd><?= e(number_format((float)$ma['coincidencia_bloque_pct'], 1, ',', '')) ?>%</dd></div><?php endif; ?>
  </dl>
  <p class="tenue pequeno">De las votaciones consideradas, <?= (int)$ma['afirmativos'] ?> fueron votos afirmativos, <?= (int)$ma['negativos'] ?> negativos y <?= (int)$ma['abstenciones'] ?> <?= (int)$ma['abstenciones'] === 1 ? 'abstención' : 'abstenciones' ?>, hasta el <?= e(fecha_es($mm['hasta'] ?? null)) ?>. "No votó" incluye todas las veces que no figura un voto, sin importar el motivo (licencia, ausencia u otro). La coincidencia con el bloque usa el bloque actual y solo se calcula con 20 votaciones o más y bloques de 3 o más integrantes. No se cuentan las votaciones en las que el legislador presidía la sesión ni las que no se pueden verificar. <a href="/votaciones">Ver todas las votaciones</a>.</p>
</section>
<?php endif; ?>

<?php $ant = $esSen ? Datos::senadoAnteriores($l['slug']) : []; $antM = Datos::senadoAnterioresMeta(); ?>
<?php if ($ant): ?>
<section class="seccion" aria-labelledby="sen-anteriores">
  <h2 id="sen-anteriores" class="seccion-titulo">Votaciones nominales anteriores al 10 de diciembre de 2025</h2>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Mandato</th><th scope="col" class="der">Votaciones</th><th scope="col" class="der">Votó</th><th scope="col" class="der">No votó</th><th scope="col" class="der">Votó en el</th><th scope="col" class="der">Afirm. · neg. · abst.</th></tr></thead>
    <tbody>
    <?php foreach ($ant as $m): ?>
      <tr>
        <td class="nowrap" data-etiqueta="Mandato"><?= e(fecha_es($m['mandato_desde'])) ?> al <?= $m['mandato_hasta'] ? e(fecha_es($m['mandato_hasta'])) : '9 de diciembre de 2025 <span class="tenue pequeno">(mandato actual)</span>' ?><?= $m['mandato_desde'] < ($antM['desde'] ?? '') ? ' <span class="tenue pequeno block">votaciones registradas desde el ' . e(fecha_es($antM['desde'])) . '</span>' : '' ?></td>
        <td class="der num" data-etiqueta="Votaciones"><?= number_format((int)$m['votaciones'], 0, ',', '.') ?></td>
        <td class="der num" data-etiqueta="Votó"><?= number_format((int)$m['emitio'], 0, ',', '.') ?></td>
        <td class="der num" data-etiqueta="No votó"><?= number_format((int)$m['ausente'], 0, ',', '.') ?></td>
        <td class="der num" data-etiqueta="Votó en el"><?= e(number_format((float)$m['voto_pct'], 1, ',', '')) ?>%</td>
        <td class="der num" data-etiqueta="Afirm. · neg. · abst."><?= (int)$m['afirmativos'] ?> · <?= (int)$m['negativos'] ?> · <?= (int)$m['abstenciones'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="tenue pequeno">Votaciones nominales publicadas por el Senado (senado.gob.ar) desde el <?= e(fecha_es($antM['desde'] ?? null)) ?>, agrupadas según las fechas de cada mandato del listado histórico del Senado. Solo cuentan las votaciones cuyo detalle coincide con sus totales. "No votó" incluye cualquier motivo (licencia, ausencia u otro). Si ejerció antes de <?= e(substr((string)($antM['desde'] ?? '2005'), 0, 4)) ?>, esas votaciones no están acá. <a href="/votaciones?camara=S">Ver las votaciones del Senado</a>.</p>
</section>
<?php endif; ?>

<?php $par = !$esSen ? Datos::participacion($l['slug']) : null; ?>
<?php if ($par): ?>
<section class="seccion" aria-labelledby="participacion">
  <h2 id="participacion" class="seccion-titulo">Votaciones nominales entre <?= e(substr($par['desde'], 0, 4)) ?> y <?= e(substr($par['hasta'], 0, 4)) ?></h2>
  <dl class="participacion">
    <div><dt>Votaciones registradas</dt><dd><?= number_format((int)$par['actas'], 0, ',', '.') ?></dd></div>
    <div><dt>Presente</dt><dd><?= number_format((int)$par['presente'], 0, ',', '.') ?></dd></div>
    <div><dt>Ausente</dt><dd><?= number_format((int)$par['ausente'], 0, ',', '.') ?></dd></div>
    <?php if ($par['asistencia_pct'] !== null): ?><div><dt>Presencia en las votaciones</dt><dd><?= e(number_format((float)$par['asistencia_pct'], 1, ',', '')) ?>%</dd></div><?php endif; ?>
    <?php if ($par['coincidencia_bloque_pct'] !== null): ?><div><dt>Votó como la mayoría de su bloque</dt><dd><?= e(number_format((float)$par['coincidencia_bloque_pct'], 1, ',', '')) ?>%</dd></div><?php endif; ?>
  </dl>
  <p class="tenue pequeno">Entre el <?= e(fecha_es($par['desde'])) ?> y el <?= e(fecha_es($par['hasta'])) ?>: <?= (int)$par['afirmativos'] ?> votos afirmativos, <?= (int)$par['negativos'] ?> negativos y <?= (int)$par['abstenciones'] ?> abstenciones. Cubre solo el tiempo en que ejerció en esos años, según los datos abiertos de la Cámara; no incluye su mandato actual. "Ausente" significa que no emitió voto en esa votación, sin importar el motivo. <a href="/votaciones">Ver todas las votaciones</a>.</p>
</section>
<?php endif; ?>

<?php $pr = Datos::proyectosResumen($l['camara'], $l['slug']); ?>
<section class="seccion" aria-labelledby="proyectos">
  <h2 id="proyectos" class="seccion-titulo">Proyectos presentados</h2>
<?php if (!$pr): ?>
  <p class="tenue"><?= $esSen ? 'El buscador oficial del Senado todavía no lo registra como autor de proyectos (pasa con quienes asumieron hace poco).' : 'No figura como primer firmante de ningún proyecto en los datos abiertos de la Cámara (hasta el ' . e(fecha_es(Datos::proyectosMeta()['hasta'] ?? null)) . '). Los datos abiertos registran un solo autor por proyecto, así que pudo haber acompañado otros como cofirmante.' ?></p>
<?php else: $ult = array_slice(Datos::proyectos($l['camara'], $l['slug']), 0, 5); ?>
  <dl class="participacion">
    <div><dt><?= $esSen ? 'Como autor o coautor' : 'Como primer firmante' ?></dt><dd><?= number_format((int)$pr['total'], 0, ',', '.') ?></dd></div>
    <div><dt>Proyectos de ley</dt><dd><?= number_format((int)$pr['leyes'], 0, ',', '.') ?></dd></div>
    <?php if (!$esSen): foreach (['SANCIONADO', 'MEDIA SANCION', 'APROBADO'] as $r): if (!empty($pr['resultados'][$r])): ?>
      <div><dt><?= e(etiqueta_resultado($r)) ?></dt><dd><?= (int)$pr['resultados'][$r] ?></dd></div>
    <?php endif; endforeach; endif; ?>
  </dl>
  <h3 class="seccion-sub">Los más recientes</h3>
  <ul class="lista-proyectos">
  <?php foreach ($ult as $p): ?>
    <?php if ($esSen): ?>
      <li><a href="https://www.senado.gob.ar<?= e($p[3]) ?>" rel="external" class="num"><?= e($p[0]) ?></a> <span class="tenue">· <?= e($p[1]) ?></span><br><?= e(mb_strimwidth($p[2], 0, 220, '…')) ?></li>
    <?php else: ?>
      <li><span class="num"><?= e($p[0]) ?></span> <span class="tenue">· <?= e($p[2]) ?> · <?= e(fecha_es($p[1])) ?> · <?= e(etiqueta_resultado($p[4])) ?></span><br><?= e(mb_strimwidth($p[3], 0, 220, '…')) ?></li>
    <?php endif; ?>
  <?php endforeach; ?>
  </ul>
  <p><a class="boton boton-sec" href="<?= e(Datos::urlLegislador($l)) ?>/proyectos">Ver los <?= number_format((int)$pr['total'], 0, ',', '.') ?> proyectos<?= $esSen ? '' : ' y qué pasó con cada uno' ?></a></p>
  <p class="tenue pequeno"><?= $esSen ? 'Según el buscador oficial del Senado, consultado por el ID del senador. Incluye todos sus mandatos.' : 'Según los datos abiertos de la Cámara de Diputados, que registran un solo autor por proyecto (el primer firmante). El cruce con el diputado se hace por su ID oficial.' ?> <a href="/metodologia#proyectos">Más detalles</a>.</p>
<?php endif; ?>
</section>

<?php $dj = Datos::ddjj($l['camara'], $l['slug']); ?>
<?php if ($dj): ?>
<section class="seccion" aria-labelledby="ddjj">
  <h2 id="ddjj" class="seccion-titulo">Declaración jurada patrimonial</h2>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Año fiscal</th><th scope="col" class="der">Bienes</th><th scope="col" class="der">Deudas</th><th scope="col" class="der">Patrimonio neto</th><th scope="col" class="der">Ingresos netos</th><th scope="col">Tipo</th></tr></thead>
    <tbody>
    <?php foreach ($dj as $d): ?>
      <tr>
        <td class="num" data-etiqueta="Año fiscal"><?= (int)$d['anio'] ?></td>
        <td class="der num" data-etiqueta="Bienes"><?= e(monto_ars((string)$d['bienes_final'])) ?></td>
        <td class="der num" data-etiqueta="Deudas"><?= e(monto_ars((string)$d['deudas_final'])) ?></td>
        <td class="der num" data-etiqueta="Patrimonio neto"><b><?= e(monto_ars((string)$d['patrimonio_neto_final'])) ?></b></td>
        <td class="der num" data-etiqueta="Ingresos netos"><?= $d['ingresos_netos'] !== null ? e(monto_ars((string)$d['ingresos_netos'])) : '—' ?></td>
        <td data-etiqueta="Tipo"><?= e($d['tipo']) ?><?= $d['rectificativa'] > 0 ? ' <span class="tenue pequeno">(rectificada ' . (int)$d['rectificativa'] . ' ' . ($d['rectificativa'] === 1 ? 'vez' : 'veces') . ')</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="tenue pequeno">Valores al cierre del año fiscal, según la declaración jurada que presentó ante la Oficina Anticorrupción (Ley 26.857). Son pesos de cada año, sin ajustar por inflación: no sirven para comparar un año con otro. Son los totales que declara la persona; este sitio no los verificó ni los interpreta. <a href="/metodologia#ddjj">Cómo se calculan</a>.</p>
</section>
<?php endif; ?>

<?php if (!$esSen): $com = Datos::comisionesDe($l['slug']); $vj = Datos::viajes(); $vjA = $vj['actual'][$l['slug']] ?? null; $vjS = $vj['por_diputado'][$l['slug']] ?? []; ?>
<?php if ($com): ?>
<section class="seccion" aria-labelledby="comisiones">
  <h2 id="comisiones" class="seccion-titulo">Comisiones</h2>
  <ul class="lista-chips">
    <?php foreach ($com as $c): ?><li><a href="/comisiones/<?= e($c['slug']) ?>"><?= e($c['nombre']) ?></a><span class="tenue"><?= e($c['cargo']) ?></span></li><?php endforeach; ?>
  </ul>
  <p class="tenue pequeno">Comisiones permanentes según la integración publicada por la Cámara de Diputados en junio de 2026.</p>
</section>
<?php endif; ?>
<?php if ($vjA || $vjS): ?>
<section class="seccion" aria-labelledby="pasajes">
  <h2 id="pasajes" class="seccion-titulo">Pasajes emitidos a su pedido</h2>
  <div class="tabla-caja"><table class="tabla" style="max-width:40rem">
    <thead><tr><th scope="col">Período</th><th scope="col" class="der">Aéreos</th><th scope="col" class="der">Terrestres</th><th scope="col" class="der">Total de tramos</th></tr></thead>
    <tbody>
    <?php if ($vjA): ?><tr><td><b>Desde el 10/12/2025</b></td><td class="der num" data-etiqueta="Aéreos"><?= (int)$vjA['aereo'] ?></td><td class="der num" data-etiqueta="Terrestres"><?= (int)$vjA['terrestre'] ?></td><td class="der num" data-etiqueta="Total"><b><?= (int)$vjA['total'] ?></b></td></tr><?php endif; ?>
    <?php krsort($vjS); foreach ($vjS as $sem => $c): [$an, $sm] = explode('_s', $sem); ?>
      <tr><td><?= $sm === '1' ? 'Enero a junio' : 'Julio a diciembre' ?> de <?= e($an) ?></td><td class="der num" data-etiqueta="Aéreos"><?= (int)($c['aereo'] ?? 0) ?></td><td class="der num" data-etiqueta="Terrestres"><?= (int)($c['terrestre'] ?? 0) ?></td><td class="der num" data-etiqueta="Total"><?= (int)$c['total'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="tenue pequeno">Cada tramo es un pasaje que la Cámara emitió a pedido del diputado. La Cámara aclara que los tramos "no implican viajes efectivamente realizados", y no publica su costo. Quien vive lejos de la Capital necesita más pasajes para ir y volver al recinto. <a href="/viajes">Más sobre pasajes y misiones oficiales</a>.</p>
</section>
<?php endif; ?>
<?php endif; ?>

<?php $sub = !$esSen ? Datos::subsidiosDiputado($l['slug']) : []; ?>
<?php if ($sub): ?>
<section class="seccion" aria-labelledby="subs">
  <h2 id="subs" class="seccion-titulo">Subsidios asociados en el registro de la Cámara</h2>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Año</th><th scope="col" class="der">Subsidios</th><th scope="col" class="der">Total</th><th scope="col"></th></tr></thead>
    <tbody>
    <?php foreach ($sub as $anio => $r): ?>
      <tr><td class="num"><?= e($anio) ?></td><td class="der num" data-etiqueta="Subsidios"><?= (int)$r['n'] ?></td><td class="der num" data-etiqueta="Total"><?= e(monto_ars((string)$r['total'])) ?></td>
          <td><a href="/subsidios?anio=<?= e($anio) ?>&amp;diputado=<?= e($l['slug']) ?>">Ver detalle</a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="tenue pequeno">Es lo que figura en el registro oficial de subsidios de la Cámara (2021 a 2023), asociado a este diputado. No indica cómo se decidió cada subsidio. <a href="/subsidios">Más sobre los subsidios</a>.</p>
</section>
<?php endif; ?>

<?php if (!empty($l['trayectoria'])): ?>
<section class="seccion" aria-labelledby="trayectoria">
  <h2 id="trayectoria" class="seccion-titulo">Trayectoria en la Cámara</h2>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Período</th><th scope="col">Distrito</th><th scope="col">Bloque</th></tr></thead>
    <tbody>
    <?php foreach ($l['trayectoria'] as $m): ?>
      <tr>
        <td class="num nowrap" data-etiqueta="Período"><?= e(substr($m['inicio'], 0, 4)) ?>–<?= e(substr($m['fin'], 0, 4)) ?></td>
        <td data-etiqueta="Distrito"><?= e($m['distrito']) ?></td>
        <td data-etiqueta="Bloque">
          <?php foreach ($m['bloques'] as $b): ?>
            <span class="block"><?= e($b['nombre']) ?><?= count($m['bloques']) > 1 ? ' <span class="tenue pequeno">(desde ' . e(fecha_es($b['desde'])) . ')</span>' : '' ?></span>
          <?php endforeach; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="tenue pequeno">Mandatos y bloques registrados por la Cámara de Diputados desde 2007. Si el legislador ejerció antes de esa fecha, esos mandatos no figuran acá.</p>
</section>
<?php endif; ?>

<section class="seccion">
<div class="dos-col perfil-grilla">
  <section aria-labelledby="ficha">
    <h2 id="ficha" class="seccion-titulo">Ficha</h2>
    <dl class="ficha">
      <div><dt>Cámara</dt><dd><?= $esSen ? 'Senado de la Nación' : 'Cámara de Diputados de la Nación' ?></dd></div>
      <div><dt>Distrito</dt><dd><?= e($l['provincia']) ?></dd></div>
      <div><dt>Bloque</dt><dd><?= e($l['bloque']) ?></dd></div>
      <?php if ($l['mandato']): ?><div><dt>Mandato</dt><dd class="num"><?= e($l['mandato']) ?></dd></div><?php endif; ?>
      <?php if (!empty($l['preside_bloque'])): ?><div><dt>Cargo en el bloque</dt><dd>Presidente del bloque</dd></div><?php endif; ?>
      <?php if (!empty($l['interbloque'])): ?><div><dt>Interbloque</dt><dd><?= e($l['interbloque']) ?></dd></div><?php endif; ?>
      <?php if (!empty($l['alianza'])): ?><div><dt>Alianza electoral</dt><dd><?= e($l['alianza']) ?></dd></div><?php endif; ?>
      <?php if ($l['jura']): ?><div><dt>Juró el</dt><dd><?= e(fecha_es($l['jura'])) ?></dd></div><?php endif; ?>
      <?php if ($l['inicio'] && $l['inicio'] !== $l['jura']): ?><div><dt>Inicio del mandato</dt><dd><?= e(fecha_es($l['inicio'])) ?></dd></div><?php endif; ?>
    </dl>
  </section>

  <section aria-labelledby="fuentes">
    <h2 id="fuentes" class="seccion-titulo">De dónde sale cada dato</h2>
    <ul class="fuentes">
      <?php foreach ($l['fuentes'] as $campo => $fuente): ?>
        <li><span class="fuente-campo"><?= e(ucfirst($campo)) ?></span><span class="fuente-origen"><?= e($fuente) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <?php if (!empty($l['email']) || !empty($l['telefono'])): ?>
      <h3 class="seccion-titulo" style="font-size:1.05rem;margin-top:1.5rem">Contacto institucional</h3>
      <ul class="contacto">
        <?php if (!empty($l['email'])): ?><li><span class="tenue">Correo</span><a href="mailto:<?= e($l['email']) ?>"><?= e($l['email']) ?></a></li><?php endif; ?>
        <?php if (!empty($l['telefono'])): ?><li><span class="tenue">Interno</span><span class="num"><?= e($l['telefono']) ?></span></li><?php endif; ?>
      </ul>
    <?php endif; ?>
    <p class="tenue pequeno" style="margin-top:1.25rem">Las votaciones, los proyectos y los demás datos de este perfil indican su fuente en cada sección. <a href="/metodologia">Más sobre cómo trabajamos</a>.</p>
  </section>
</div>
</section>

<?php if ($mismaProv): ?>
<section class="seccion" aria-labelledby="misma-prov">
  <h2 id="misma-prov" class="seccion-titulo">Otros <?= $esSen ? 'senadores' : 'diputados' ?> por <?= e($l['provincia']) ?></h2>
  <ul class="lista-chips">
    <?php foreach (array_slice($mismaProv, 0, 30) as $x): ?>
      <li><a href="<?= e(Datos::urlLegislador($x)) ?>"><?= e($x['nombre']) ?></a><span class="tenue"><?= e($x['bloque']) ?></span></li>
    <?php endforeach; ?>
  </ul>
  <?php if (count($mismaProv) > 30): ?><p><a href="/congreso/provincias/<?= e($provSlug) ?>">Ver los <?= count($mismaProv) + 1 ?> de la provincia</a></p><?php endif; ?>
</section>
<?php endif; ?>
