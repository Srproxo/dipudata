<?php
use App\Datos;
/** @var array $cr @var array $info */
$pv = $cr['patrimonio_votos'] ?? ['votaciones' => [], 'votos' => []];
$vots = $pv['votaciones'];
$filas = []; $sinDj = 0;
foreach (Datos::legisladores('Diputados') as $l) {
    $sp = serie_patrimonio($l);
    $votos = $pv['votos'][$l['slug']] ?? [];
    if (!$sp || $sp['ultimo_hoy'] === null) { $sinDj++; continue; }
    $filas[] = ['l' => $l, 's' => $sp, 'v' => $votos];
}
[$orden, $dir] = orden_tabla(['nombre', 'hoy', 'var'], 'hoy');
$filas = ordenar_filas($filas, static fn($f) => match ($orden) {
    'nombre' => Datos::normalizar(($f['l']['apellido'] ?? '') . ' ' . $f['l']['nombre']),
    'var' => $f['s']['var_hoy']['pct'] ?? null,
    default => $f['s']['ultimo_hoy'],
}, $dir);
$mesBase = $filas ? mes_es($filas[0]['s']['mes_base']) : '';
// Mediana del patrimonio (pesos de hoy) de quienes votaron a favor y en contra de cada votación.
$porVoto = [];
foreach ($vots as $i => $v) {
    $g = ['A' => [], 'N' => [], 'B' => [], 'U' => []];
    foreach ($filas as $f) { $c = $f['v'][$v['id']] ?? null; if (isset($g[$c])) { $g[$c][] = $f['s']['ultimo_hoy']; } }
    $porVoto[$v['id']] = $g;
}
?>
<nav class="migas" aria-label="Ubicación"><a href="/cruces">Cruces de datos</a> / Patrimonio y votos</nav>
<p class="antetitulo">Cruce de datos · Diputados</p>
<h1 class="titulo">Patrimonio y votos en temas económicos.</h1>
<p class="bajada">Cómo votó cada diputado en las votaciones en general de temas económicos del mandato actual, al lado del patrimonio neto de su última declaración jurada publicada, en pesos de <?= e($mesBase) ?>.</p>

<h2 class="seccion-titulo" style="margin-top:2rem">Las <?= count($vots) ?> votaciones</h2>
<p class="tenue" style="max-width:62ch">Son las votaciones en general del mandato actual cuyo dictamen tuvo como comisión cabecera (la de menor orden de giro) a <?= e(implode(', ', array_slice($cr['_meta']['comisiones_economicas'] ?? [], 0, -1)) . ' o ' . (array_slice($cr['_meta']['comisiones_economicas'] ?? [''], -1)[0])) ?>.</p>
<div class="tabla-caja"><table class="tabla tabla-cifras">
  <thead><tr><th scope="col">#</th><th scope="col">Votación</th><th scope="col">Resultado</th><th scope="col" class="der">A favor</th><th scope="col" class="der">En contra</th></tr></thead>
  <tbody>
  <?php foreach ($vots as $i => $v): $g = $porVoto[$v['id']]; $ma = mediana($g['A']); $mn = mediana($g['N']); ?>
    <tr>
      <td data-etiqueta="#" class="num"><?= $i + 1 ?></td>
      <td data-etiqueta="Votación"><span><a href="/votaciones/<?= e($v['id']) ?>"><?= e(titulo_corto_votacion($v['t'], 90)) ?></a><span class="tenue pequeno block"><?= e(fecha_es($v['f'])) ?> · <?= e(implode(', ', $v['comisiones'])) ?></span></span></td>
      <td data-etiqueta="Resultado"><span class="celda-valor"><?= resultado_votacion($v['res']) ?><span class="tenue pequeno block"><?= (int)$v['a'] ?> a favor, <?= (int)$v['n'] ?> en contra</span></span></td>
      <td class="der num" data-etiqueta="Patrimonio mediano a favor"><span class="celda-valor"><?= $ma !== null ? e(monto_corto($ma)) : '—' ?><span class="tenue pequeno block"><?= count($g['A']) ?> con declaración</span></span></td>
      <td class="der num" data-etiqueta="Patrimonio mediano en contra"><span class="celda-valor"><?= $mn !== null ? e(monto_corto($mn)) : '—' ?><span class="tenue pequeno block"><?= count($g['N']) ?> con declaración</span></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p class="tenue pequeno" style="max-width:62ch">Las columnas «A favor» y «En contra» muestran la mediana del patrimonio neto, en pesos de <?= e($mesBase) ?>, de los diputados con declaración publicada que votaron así: la mitad declaró más y la otra mitad menos. No es el patrimonio de todos los que votaron.</p>

<h2 class="seccion-titulo" style="margin-top:2.5rem">Diputado por diputado</h2>
<p class="tenue pequeno">Los números de las columnas son los de la lista de arriba. Sí: a favor; No: en contra; Abst.: abstención; Aus.: ausente; Pres.: presidió la sesión (no vota); —: no figura en el acta.</p>
<p class="conteo"><?= count($filas) ?> diputados con declaración publicada y conversión a pesos de hoy. Los otros <?= (int)$sinDj ?> no tienen una declaración con totales en el archivo de la Oficina Anticorrupción (por ejemplo, quienes asumieron el 10/12/2025): ver <a href="/metodologia#ddjj">la metodología</a>.</p>
<?php [$filasPag, $pagina, $paginas] = paginar($filas); ?>
<div class="tabla-caja"><table class="tabla tabla-cifras tabla-cruce-votos">
  <thead><tr>
    <?= th_orden('nombre', 'Diputado', $orden, $dir, false) ?>
    <?= th_orden('hoy', 'Patrimonio en $ de hoy', $orden, $dir) ?>
    <?= th_orden('var', 'Variación', $orden, $dir) ?>
    <?php foreach ($vots as $i => $v): ?><th scope="col" class="der" title="<?= e(titulo_corto_votacion($v['t'])) ?>"><?= $i + 1 ?></th><?php endforeach; ?>
  </tr></thead>
  <tbody>
  <?php foreach ($filasPag as $f): $lg = $f['l']; $sp = $f['s']; ?>
    <tr>
      <td class="celda-nombre-td"><span class="celda-nombre"><?= avatar($lg, 32) ?><span><a href="<?= e(Datos::urlLegislador($lg)) ?>?tab=patrimonio"><?= e($lg['nombre']) ?></a><span class="tenue pequeno block"><?= e($lg['bloque']) ?> · <?= e($lg['provincia']) ?></span></span></span></td>
      <td class="der num" data-etiqueta="Patrimonio en pesos de hoy"><span class="celda-valor"><?= e(monto_corto((float)$sp['ultimo_hoy'])) ?><span class="tenue pequeno block">declaración <?= (int)$sp['ultimo_anio'] ?></span></span></td>
      <td class="der num" data-etiqueta="Variación en pesos de hoy"><span class="celda-valor"><?= e(texto_variacion($sp['var_hoy'])) ?><?php if ($sp['var_hoy']): ?><span class="tenue pequeno block"><?= (int)$sp['var_hoy']['desde'] ?> a <?= (int)$sp['var_hoy']['hasta'] ?></span><?php endif; ?></span></td>
      <?php foreach ($vots as $i => $v): ?><td class="der" data-etiqueta="<?= ($i + 1) . '. ' . e(titulo_corto_votacion($v['t'], 40)) ?>"><?= chip_voto($f['v'][$v['id']] ?? null, true) ?></td><?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?= nav_paginas($pagina, $paginas) ?>

<div class="aviso">
  <p style="margin:0 0 .5rem"><strong>Cómo leer este cruce.</strong></p>
  <ul class="lista-simple">
    <li><strong>Qué votación es «económica».</strong> El título de cada votación de Diputados trae su número de orden del día. El archivo oficial de dictámenes de la Cámara dice qué comisiones firmaron ese dictamen y en qué orden se giró. Se toman las votaciones en general cuya comisión cabecera es una de las económicas. Las votaciones sin orden del día (mociones, tratamientos sobre tablas) no entran.</li>
    <li><strong>Patrimonio</strong>: el patrimonio neto (bienes menos deudas) de la última declaración publicada, actualizado con el IPC del INDEC, con las mismas cifras de la pestaña de declaración jurada de cada perfil. La variación compara la primera y la última declaración con conversión y dice qué años usa.</li>
    <li>El patrimonio es de un año anterior a las votaciones (el último archivo publicado es el de 2024). Poner los dos datos juntos no dice que uno explique al otro: no hay forma de saber, con estos datos, por qué alguien votó como votó.</li>
    <li>«—» quiere decir que el diputado no figura en el acta de esa votación.</li>
  </ul>
</div>
<p class="fuente-pie">Fuentes: votaciones nominales de la Cámara de Diputados; dictámenes de la Cámara de Diputados (datos.hcdn.gob.ar<?= !empty($cr['_meta']['dictamenes']['modificado_en_el_portal']) ? ', actualizado el ' . e(fecha_es($cr['_meta']['dictamenes']['modificado_en_el_portal'])) : '' ?>); Declaraciones Juradas Patrimoniales Integrales (Oficina Anticorrupción, datos.jus.gob.ar); IPC Nivel General Nacional (INDEC).</p>
