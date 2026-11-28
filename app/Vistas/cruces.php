<?php
/** @var array $lista @var array $cr */
$m = $cr['_meta'] ?? [];
?>
<p class="antetitulo">Cruces de datos</p>
<h1 class="titulo">Lo que aparece al cruzar los datos.</h1>
<p class="bajada">Siete cruces entre datos oficiales que el sitio ya publica por separado: declaraciones juradas, votaciones nominales, comisiones, pasajes, provincias y proyectos. Cada cruce dice de dónde sale cada número, cómo se calcula y qué no se puede concluir de él.</p>

<ul class="indice">
  <?php foreach ($lista as $slug => [$t, $d, $cam]): ?>
    <li><a href="/cruces/<?= e($slug) ?>"><span class="indice-t"><?= e($t) ?></span><span class="indice-d"><?= e($d) ?></span><span class="tenue pequeno"><?= e($cam) ?></span></a></li>
  <?php endforeach; ?>
</ul>

<div class="aviso">
  <p style="margin:0 0 .5rem"><strong>Antes de leer un cruce.</strong></p>
  <ul class="lista-simple">
    <li>Un cruce pone dos datos uno al lado del otro. No dice por qué pasó algo ni que un dato cause el otro.</li>
    <li>Salvo que se diga otra cosa, el período es el mandato actual: desde el 10 de diciembre de 2025<?= !empty($m['hasta']) ? ' hasta la última votación registrada, el ' . e(fecha_es($m['hasta'])) : '' ?>.</li>
    <li>Solo cuentan las votaciones nominales cuyo detalle coincide con sus totales oficiales: las mismas que usan las estadísticas de cada perfil.</li>
    <li>Varios cruces de Diputados usan el número de orden del día que trae el título de cada votación para llegar al dictamen y a las comisiones que lo firmaron (archivo oficial de dictámenes de la Cámara). El Senado no publica ese archivo, por eso esos cruces son solo de Diputados.</li>
  </ul>
</div>
<p class="fuente-pie">Fuentes: votaciones nominales de la Cámara de Diputados (datos.hcdn.gob.ar, votaciones.hcdn.gob.ar y ArgentinaDatos) y del Senado (senado.gob.ar); dictámenes, comisiones, proyectos y pasajes de la Cámara de Diputados (datos.hcdn.gob.ar); declaraciones juradas de la Oficina Anticorrupción; IPC del INDEC. Detalle en <a href="/metodologia#cruces">la metodología</a>.</p>
