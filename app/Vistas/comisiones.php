<?php use App\Datos; $dip = Datos::comisionesDiputados(); $sen = Datos::comisionesSenado(); $porTipo = []; foreach ($sen as $c) { $porTipo[$c['tipo']][] = $c['nombre']; } ?>
<nav class="migas" aria-label="Ubicación"><a href="/congreso">Cómo funciona el Congreso</a> <span aria-hidden="true">/</span> <span>Comisiones</span></nav>
<h1 class="titulo">Las comisiones.</h1>
<p class="bajada">Antes de llegar al recinto, cada proyecto se estudia en una o más comisiones. Acá están las comisiones permanentes de Diputados con sus integrantes, y las del Senado.</p>

<section class="seccion" aria-labelledby="c-dip">
  <div class="fila-titulo"><h2 id="c-dip" class="seccion-titulo" style="margin:0">Cámara de Diputados</h2><p class="num"><?= count($dip) ?> comisiones</p></div>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Comisión</th><th scope="col">Preside</th><th scope="col" class="der">Integrantes</th></tr></thead>
    <tbody>
    <?php foreach ($dip as $c): $pres = array_values(array_filter($c['integrantes'], static fn($m) => $m['cargo'] === 'Presidente'))[0] ?? null; ?>
      <tr>
        <td><a href="/comisiones/<?= e($c['slug']) ?>"><?= e($c['nombre']) ?></a></td>
        <td data-etiqueta="Preside"><?= $pres ? '<a href="/diputado/' . e($pres['slug']) . '">' . e($pres['nombre']) . '</a> <span class="tenue pequeno">' . e($pres['bloque']) . '</span>' : '<span class="tenue">—</span>' ?></td>
        <td class="der num" data-etiqueta="Integrantes"><?= count($c['integrantes']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="tenue pequeno">Integración publicada por la Cámara de Diputados en junio de 2026. Solo figuran los diputados en ejercicio.</p>
</section>

<section class="seccion" aria-labelledby="c-sen">
  <div class="fila-titulo"><h2 id="c-sen" class="seccion-titulo" style="margin:0">Senado de la Nación</h2><p class="num"><?= count($sen) ?> comisiones</p></div>
  <?php foreach ($porTipo as $tipo => $nombres): ?>
    <h3 class="sub" style="font:700 1.05rem/1.3 var(--sans);margin:1.25rem 0 .4rem"><?= e(ucfirst(mb_strtolower($tipo, 'UTF-8'))) ?> <span class="tenue num">(<?= count($nombres) ?>)</span></h3>
    <ul class="lista-simple"><?php foreach ($nombres as $n): ?><li><?= e(trim((string)preg_replace('/\s+/', ' ', mb_convert_case(mb_strtolower($n, 'UTF-8'), MB_CASE_TITLE, 'UTF-8')))) ?></li><?php endforeach; ?></ul>
  <?php endforeach; ?>
  <p class="tenue pequeno">El listado del Senado incluye solo el nombre y el tipo de cada comisión; no publica sus integrantes como dato abierto.</p>
</section>
<p class="fuente-pie">Fuentes: comisiones permanentes e integrantes de la Cámara de Diputados (datos.hcdn.gob.ar) y listado de comisiones del Senado de la Nación (datos abiertos).</p>
