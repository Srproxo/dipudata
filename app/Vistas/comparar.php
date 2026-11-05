<?php
use App\Datos;
/** @var array<string,array> $sel legisladores elegidos (a..d) @var list<array> $todosLeg */
$n = count($sel);
$datos = [];
foreach ($sel as $p => $lg) {
    $datos[$p] = ['l' => $lg, 'ma' => Datos::mandatoActual($lg['camara'], $lg['slug']), 'py' => Datos::proyectos($lg['slug'], $lg['camara']), 'sp' => serie_patrimonio($lg)];
}
$claves = array_map('clave_legislador', array_values($sel));
$mesBase = '';
foreach ($datos as $d) { if ($d['sp']) { $mesBase = mes_es($d['sp']['mes_base']); break; } }
$fila = static function (string $titulo, callable $celda) use ($datos): string {
    $h = '<tr><th scope="row">' . e($titulo) . '</th>';
    foreach ($datos as $d) { $h .= '<td class="num" data-etiqueta="' . e($d['l']['nombre']) . '">' . $celda($d) . '</td>'; }
    return $h . '</tr>';
};
$nada = '<span class="tenue">—</span>';
?>
<p class="antetitulo">Comparar</p>
<h1 class="titulo">Compará legisladores.</h1>
<p class="bajada">Elegí hasta cuatro diputados o senadores y mirá lado a lado su patrimonio declarado, cómo votaron desde el 10 de diciembre de 2025 y sus proyectos. Todo con datos oficiales y la fuente de cada dato.</p>

<form class="filtros comparador" method="get" action="/comparar" style="margin-top:1.5rem">
  <?php foreach (['a' => 'Primero', 'b' => 'Segundo', 'c' => 'Tercero', 'd' => 'Cuarto'] as $p => $et): $actual = isset($sel[$p]) ? clave_legislador($sel[$p]) : ''; ?>
  <label><?= $et ?>
    <select name="<?= $p ?>"><option value="">—</option>
      <?php foreach (['Diputados' => 'Diputados', 'Senadores' => 'Senado'] as $cam => $tit): ?>
      <optgroup label="<?= e($tit) ?>">
        <?php foreach ($todosLeg as $lg): if ($lg['camara'] !== $cam) continue; $k = clave_legislador($lg); ?>
          <option value="<?= e($k) ?>"<?= $actual === $k ? ' selected' : '' ?>><?= e($lg['nombre']) ?> (<?= e($lg['provincia']) ?>)</option>
        <?php endforeach; ?>
      </optgroup>
      <?php endforeach; ?>
    </select>
  </label>
  <?php endforeach; ?>
  <button class="boton" type="submit">Comparar</button>
  <?php if ($n): ?><a class="enlace-limpiar" href="/comparar">Empezar de nuevo</a><?php endif; ?>
</form>

<?php if ($n === 0): ?>
  <p class="vacio-txt">Elegí al menos un legislador. También podés tocar "Comparar" en la tabla de <a href="/patrimonio">patrimonio</a> o en el perfil de cada uno.</p>
<?php else: ?>

<div class="comp-cabezas" style="--cols: <?= $n ?>">
  <?php foreach ($datos as $d): $lg = $d['l']; ?>
  <div class="comp-cabeza">
    <?= avatar($lg, 64) ?>
    <div><a class="comp-nombre" href="<?= e(Datos::urlLegislador($lg)) ?>"><?= e($lg['nombre']) ?></a>
      <span class="tenue pequeno block"><?= $lg['camara'] === 'Senadores' ? 'Senado' : 'Diputados' ?> · <?= e($lg['provincia']) ?></span>
      <span class="pequeno block"><?= etiqueta_bloque($lg) ?></span></div>
  </div>
  <?php endforeach; ?>
</div>

<section class="seccion" aria-labelledby="c-patrimonio">
  <h2 id="c-patrimonio" class="seccion-titulo">Patrimonio declarado</h2>
  <?php
  $serH = []; $serU = [];
  foreach ($datos as $d) { if ($d['sp'] && $d['sp']['hoy']) { $serH[$d['l']['nombre']] = $d['sp']['hoy']; } if ($d['sp'] && $d['sp']['usd']) { $serU[$d['l']['nombre']] = $d['sp']['usd']; } }
  $leyenda = static function (array $series): string {
      $h = '<ul class="leyenda leyenda-series">'; $k = 0;
      foreach (array_keys($series) as $nom) { $h .= '<li><i class="ley-serie ley-serie-' . ($k % 4) . '"></i>' . e($nom) . '</li>'; $k++; }
      return $h . '</ul>';
  };
  ?>
  <?php if (count($serH) >= 1 && $g = svg_lineas($serH, 'Patrimonio neto declarado al cierre de cada año fiscal, en pesos de ' . $mesBase . ', de ' . implode(', ', array_keys($serH)))): ?>
  <figure class="grafico-caja grafico-ancho">
    <p class="sub-titulo">En pesos de <?= e($mesBase) ?></p>
    <?= $g ?><?= $leyenda($serH) ?>
    <figcaption class="tenue pequeno">Patrimonio neto declarado al cierre de cada año fiscal, actualizado con el IPC del INDEC (desde 2016). Pasá el mouse o tocá un punto para ver el valor.</figcaption>
  </figure>
  <?php endif; ?>
  <?php if (count($serU) >= 1 && $g = svg_lineas($serU, 'Patrimonio neto declarado al cierre de cada año fiscal, en dólares oficiales, de ' . implode(', ', array_keys($serU)), 'US$')): ?>
  <figure class="grafico-caja grafico-ancho">
    <p class="sub-titulo">En dólares oficiales</p>
    <?= $g ?><?= $leyenda($serU) ?>
    <figcaption class="tenue pequeno">El mismo patrimonio neto pasado a dólares con el tipo de cambio oficial del Banco Central del último día hábil de cada año.</figcaption>
  </figure>
  <?php endif; ?>
  <div class="tabla-caja"><table class="tabla tabla-comparar">
    <thead><tr><th scope="col"></th><?php foreach ($datos as $d): ?><th scope="col" class="der"><?= e($d['l']['nombre']) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
      <?= $fila('Última declaración publicada', static fn($d) => $d['sp'] ? (int)$d['sp']['ultimo_anio'] . '<span class="tenue pequeno block">' . (int)$d['sp']['cantidad'] . ' ' . ($d['sp']['cantidad'] === 1 ? 'año publicado' : 'años publicados') . '</span>' : $nada) ?>
      <?= $fila('Patrimonio neto (pesos de ese año)', static fn($d) => $d['sp'] ? e(monto_ars((string)$d['sp']['ultimo'])) : $nada) ?>
      <?= $fila('En pesos de ' . ($mesBase ?: 'hoy'), static fn($d) => $d['sp'] && $d['sp']['ultimo_hoy'] !== null ? e(monto_ars((string)round($d['sp']['ultimo_hoy'], 2))) : $nada) ?>
      <?= $fila('En US$ oficiales', static fn($d) => $d['sp'] && $d['sp']['ultimo_usd'] !== null ? e(monto_usd($d['sp']['ultimo_usd'])) : $nada) ?>
      <?= $fila('Variación en pesos de hoy', static fn($d) => $d['sp'] && $d['sp']['var_hoy'] ? e(texto_variacion($d['sp']['var_hoy'])) . '<span class="tenue pequeno block">' . (int)$d['sp']['var_hoy']['desde'] . ' a ' . (int)$d['sp']['var_hoy']['hasta'] . '</span>' : $nada) ?>
      <?= $fila('Variación en US$', static fn($d) => $d['sp'] && $d['sp']['var_usd'] ? e(texto_variacion($d['sp']['var_usd'])) . '<span class="tenue pequeno block">' . (int)$d['sp']['var_usd']['desde'] . ' a ' . (int)$d['sp']['var_usd']['hasta'] . '</span>' : $nada) ?>
    </tbody>
  </table></div>
  <?php foreach ($datos as $d): if (!$d['sp']): $est = estado_ddjj($d['l'], []); ?>
    <p class="tenue pequeno"><strong><?= e($d['l']['nombre']) ?>:</strong>
      <?= $est['sin_totales'] ? 'presentó declaración pero el archivo oficial trae los totales vacíos.' : ($est['inconsistente'] ? 'el archivo oficial trae declaraciones del mismo año con montos distintos y no se muestra ninguna cifra.' : ($est['ambiguo'] ? 'el nombre corresponde a más de una persona en el archivo oficial.' : ($est['asumio_2025'] ? 'asumió el 10/12/2025 y su primera declaración todavía no está publicada.' : 'no figura en el archivo de declaraciones juradas publicado.'))) ?></p>
  <?php endif; endforeach; ?>
  <p class="tenue pequeno">Los períodos de cada persona son distintos: comparar variaciones solo tiene sentido si los años son parecidos. Un cambio puede deberse a cómo se valúan los bienes en la declaración y no solo a compras, ventas o ahorro. Fuente: declaraciones juradas de la Oficina Anticorrupción, IPC del INDEC y dólar oficial del Banco Central. <a href="/metodologia#ddjj">Cómo se calcula</a>.</p>
</section>

<section class="seccion" aria-labelledby="c-votos">
  <h2 id="c-votos" class="seccion-titulo">Votaciones desde el 10 de diciembre de 2025</h2>
  <div class="tabla-caja"><table class="tabla tabla-comparar">
    <thead><tr><th scope="col"></th><?php foreach ($datos as $d): ?><th scope="col" class="der"><?= e($d['l']['nombre']) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
      <?= $fila('Votaciones nominales en su cámara', static fn($d) => $d['ma'] ? (int)$d['ma']['votaciones'] : $nada) ?>
      <?= $fila('Emitió su voto', static fn($d) => $d['ma'] ? (int)$d['ma']['emitio'] . ($d['ma']['voto_pct'] !== null ? '<span class="tenue pequeno block">' . e(number_format((float)$d['ma']['voto_pct'], 1, ',', '')) . ' %</span>' : '') : $nada) ?>
      <?= $fila('Afirmativos · negativos · abstenciones', static fn($d) => $d['ma'] ? (int)$d['ma']['afirmativos'] . ' · ' . (int)$d['ma']['negativos'] . ' · ' . (int)$d['ma']['abstenciones'] : $nada) ?>
      <?= $fila('Votó como la mayoría de su bloque', static fn($d) => $d['ma'] && $d['ma']['coincidencia_bloque_pct'] !== null ? e(number_format((float)$d['ma']['coincidencia_bloque_pct'], 1, ',', '')) . ' %' : $nada) ?>
    </tbody>
  </table></div>
  <?php $co = $n >= 2 ? coincidencias_votos($claves) : []; ?>
  <?php if ($co): ?>
  <h3 class="sub-titulo" style="margin-top:1.5rem">Cuánto coinciden sus votos</h3>
  <div class="tabla-caja"><table class="tabla tabla-comparar">
    <thead><tr><th scope="col">Par</th><th scope="col" class="der">Votaron igual</th><th scope="col" class="der">Votaciones en que los dos votaron</th><th scope="col" class="der">Coincidencia</th></tr></thead>
    <tbody>
    <?php foreach ($co as $par => [$igual, $comunes]): [$ka, $kb] = explode('|', $par); $la = legislador_por_clave($ka); $lb = legislador_por_clave($kb); ?>
      <tr><th scope="row"><?= e($la['nombre']) ?> y <?= e($lb['nombre']) ?></th><td class="der num" data-etiqueta="Votaron igual"><?= (int)$igual ?></td><td class="der num" data-etiqueta="Votaciones en común"><?= (int)$comunes ?></td><td class="der num" data-etiqueta="Coincidencia"><?= $comunes ? e(number_format($igual * 100 / $comunes, 1, ',', '')) . ' %' : '—' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <p class="tenue pequeno">"No votó" incluye toda vez que no figura un voto, sin importar el motivo (licencia, ausencia u otro). La coincidencia cuenta solo votaciones válidas en que los dos emitieron voto afirmativo, negativo o abstención, y solo entre legisladores de la misma cámara. Fuentes: votaciones nominales de senado.gob.ar y de la Cámara de Diputados. <a href="/votaciones">Ver las votaciones</a>.</p>
</section>

<section class="seccion" aria-labelledby="c-proy">
  <h2 id="c-proy" class="seccion-titulo">Proyectos</h2>
  <div class="tabla-caja"><table class="tabla tabla-comparar">
    <thead><tr><th scope="col"></th><?php foreach ($datos as $d): ?><th scope="col" class="der"><?= e($d['l']['nombre']) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
      <?= $fila('Proyectos donde figura', static fn($d) => $d['py'] ? number_format((int)$d['py']['resumen']['total'], 0, ',', '.') : $nada) ?>
      <?= $fila('De ley', static fn($d) => $d['py'] ? (int)($d['py']['resumen']['por_tipo']['Ley'] ?? 0) : $nada) ?>
      <?= $fila('Primero y último', static fn($d) => $d['py'] ? e(fecha_es($d['py']['resumen']['desde'])) . '<span class="tenue pequeno block">' . e(fecha_es($d['py']['resumen']['hasta'])) . '</span>' : $nada) ?>
    </tbody>
  </table></div>
  <p class="tenue pequeno">En Diputados, el archivo oficial registra un solo autor por proyecto (sin cofirmantes); en el Senado, la lista de cada senador incluye los proyectos que firmó con otros. La cantidad de proyectos mide lo que se presentó, no su alcance ni su resultado. <a href="/metodologia">Cómo se arma</a>.</p>
</section>
<?php endif; ?>
