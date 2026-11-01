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
    <p class="tenue pequeno" style="margin-top:1.25rem">Las métricas del mandato actual se publican cuando la Cámara libere sus votaciones recientes. <a href="/metodologia">Más sobre cómo trabajamos</a>.</p>
  </section>
</div>

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
