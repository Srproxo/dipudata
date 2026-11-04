<?php
use App\Datos;
/** @var list<array> $filas @var int $total @var int $conDatos @var string $q @var string $camaraSel @var string $provSel @var string $bloqueSel @var string $orden @var string $dir @var int $pagina @var int $paginas */
$mesBase = $filas ? mes_es($filas[0]['s']['mes_base']) : '';
$enlOrden = static function (string $col, string $titulo, bool $der = true) use ($orden, $dir): string {
    $activo = $orden === $col;
    $nuevo = $activo && $dir === 'desc' ? 'asc' : 'desc';
    $flecha = $activo ? ($dir === 'desc' ? ' ↓' : ' ↑') : '';
    return '<th scope="col" class="' . ($der ? 'der' : '') . '"' . ($activo ? ' aria-sort="' . ($dir === 'desc' ? 'descending' : 'ascending') . '"' : '') . '><a href="' . e(url_con(['orden' => $col, 'dir' => $nuevo, 'pagina' => 1])) . '">' . e($titulo) . $flecha . '</a></th>';
};
?>
<p class="antetitulo">Declaraciones juradas</p>
<h1 class="titulo">Patrimonio declarado.</h1>
<p class="bajada">El patrimonio neto que declararon diputados y senadores ante la Oficina Anticorrupción, en pesos de hoy y en dólares, con cuánto cambió entre su primera y su última declaración. Todo sale de datos públicos oficiales; ordená por cualquier columna y filtrá por cámara, provincia o bloque.</p>

<form class="filtros" method="get" action="/patrimonio" style="margin-top:1.5rem">
  <label>Buscar por nombre
    <input type="search" name="q" value="<?= e($q) ?>" maxlength="80" placeholder="Apellido o nombre" autocomplete="off">
  </label>
  <label>Cámara
    <select name="camara"><option value="">Ambas</option>
      <option value="Diputados"<?= $camaraSel === 'Diputados' ? ' selected' : '' ?>>Diputados</option>
      <option value="Senadores"<?= $camaraSel === 'Senadores' ? ' selected' : '' ?>>Senadores</option>
    </select>
  </label>
  <label>Provincia
    <select name="provincia"><option value="">Todas</option>
      <?php foreach (Datos::provincias() as $p): ?><option value="<?= e($p['slug']) ?>"<?= $provSel === $p['slug'] ? ' selected' : '' ?>><?= e($p['nombre']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Bloque
    <select name="bloque"><option value="">Todos</option>
      <?php $vistos = []; foreach (array_merge(Datos::bloques('Diputados'), Datos::bloques('Senadores')) as $b) { if (isset($vistos[$b['slug']])) continue; $vistos[$b['slug']] = 1; ?>
        <option value="<?= e($b['slug']) ?>"<?= $bloqueSel === $b['slug'] ? ' selected' : '' ?>><?= e($b['nombre']) ?></option>
      <?php } ?>
    </select>
  </label>
  <input type="hidden" name="orden" value="<?= e($orden) ?>"><input type="hidden" name="dir" value="<?= e($dir) ?>">
  <button class="boton" type="submit">Filtrar</button>
  <?php if ($q !== '' || $camaraSel || $provSel || $bloqueSel): ?><a class="enlace-limpiar" href="/patrimonio">Quitar filtros</a><?php endif; ?>
</form>

<p class="conteo" aria-live="polite"><?= number_format($total, 0, ',', '.') ?> <?= $total === 1 ? 'legislador' : 'legisladores' ?> con declaración publicada. De los <?= (int)count(Datos::legisladores()) ?> en ejercicio, <?= (int)$conDatos ?> figuran en total; los demás se explican en <a href="/metodologia#ddjj">la metodología</a>.</p>
<?php if (!$filas): ?>
  <p class="vacio-txt">Ningún legislador con declaración publicada coincide con esos filtros.</p>
<?php else: ?>
<div class="tabla-caja"><table class="tabla tabla-cifras tabla-patrimonio">
  <thead><tr>
    <?= $enlOrden('nombre', 'Legislador', false) ?>
    <?= $enlOrden('anio', 'Año') ?>
    <?= $enlOrden('corriente', 'Declarado') ?>
    <?= $enlOrden('hoy', 'En pesos de hoy') ?>
    <?= $enlOrden('usd', 'En US$') ?>
    <?= $enlOrden('var_hoy', 'Variación en $ de hoy') ?>
    <?= $enlOrden('var_usd', 'Variación en US$') ?>
    <th scope="col"><span class="sr-solo">Comparar</span></th>
  </tr></thead>
  <tbody>
  <?php foreach ($filas as $f): $lg = $f['l']; $sp = $f['s']; ?>
    <tr>
      <td class="celda-nombre" data-etiqueta="Legislador"><?= avatar($lg, 36) ?><span><a href="<?= e(Datos::urlLegislador($lg)) ?>?tab=patrimonio"><?= e($lg['nombre']) ?></a>
        <span class="tenue pequeno block"><?= $lg['camara'] === 'Senadores' ? 'Senado' : 'Diputados' ?> · <?= e($lg['provincia']) ?></span></span></td>
      <td class="der num" data-etiqueta="Última declaración"><?= (int)$sp['ultimo_anio'] ?><span class="tenue pequeno block"><?= (int)$sp['cantidad'] ?> <?= $sp['cantidad'] === 1 ? 'año' : 'años' ?></span></td>
      <td class="der num" data-etiqueta="Patrimonio neto"><?= e(monto_ars((string)$sp['ultimo'])) ?></td>
      <td class="der num" data-etiqueta="En pesos de hoy"><?= $sp['ultimo_hoy'] !== null ? e(monto_ars((string)round($sp['ultimo_hoy'], 2))) : '<span class="tenue">—</span>' ?></td>
      <td class="der num" data-etiqueta="En US$ oficiales"><?= $sp['ultimo_usd'] !== null ? e(monto_usd($sp['ultimo_usd'])) : '<span class="tenue">—</span>' ?></td>
      <td class="der num" data-etiqueta="Variación en pesos de hoy"><?= e(texto_variacion($sp['var_hoy'])) ?><?php if ($sp['var_hoy']): ?><span class="tenue pequeno block"><?= (int)$sp['var_hoy']['desde'] ?> a <?= (int)$sp['var_hoy']['hasta'] ?></span><?php endif; ?></td>
      <td class="der num" data-etiqueta="Variación en US$"><?= e(texto_variacion($sp['var_usd'])) ?><?php if ($sp['var_usd']): ?><span class="tenue pequeno block"><?= (int)$sp['var_usd']['desde'] ?> a <?= (int)$sp['var_usd']['hasta'] ?></span><?php endif; ?></td>
      <td data-etiqueta=""><a href="/comparar?a=<?= e(clave_legislador($lg)) ?>">Comparar</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($paginas > 1): ?>
<nav class="paginas" aria-label="Paginación">
  <?php if ($pagina > 1): ?><a href="<?= e(url_con(['pagina' => $pagina - 1])) ?>" rel="prev">← Anterior</a><?php endif; ?>
  <span class="num">Página <?= $pagina ?> de <?= $paginas ?></span>
  <?php if ($pagina < $paginas): ?><a href="<?= e(url_con(['pagina' => $pagina + 1])) ?>" rel="next">Siguiente →</a><?php endif; ?>
</nav>
<?php endif; endif; ?>

<div class="aviso">
  <p style="margin:0 0 .5rem"><strong>Cómo leer esta tabla.</strong></p>
  <ul class="lista-simple">
    <li><strong>Patrimonio neto</strong> es bienes menos deudas al cierre del año fiscal de la última declaración publicada, tal como lo declaró la persona. Puede ser por su cargo de legislador o por otra función pública o candidatura.</li>
    <li><strong>Declarado</strong> es el monto en pesos del año de esa declaración. <strong>En pesos de <?= e($mesBase) ?></strong> actualiza ese monto con el IPC del INDEC (la serie oficial empieza en diciembre de 2016, por eso no hay conversión para años anteriores). <strong>En US$</strong> usa el dólar oficial del Banco Central al último día hábil de ese año.</li>
    <li><strong>Variación</strong> compara la primera y la última declaración que tienen conversión, y siempre indica los años usados: dos personas con distintos períodos no son comparables entre sí. Un cambio puede deberse a cómo se valúan los bienes en la declaración (por ejemplo, inmuebles a valor fiscal o de compra, que no siguen a la inflación) y no solo a compras, ventas o ahorro. La tabla muestra lo que declaró cada persona; no dice por qué cambió.</li>
    <li>Cada fila usa la <strong>última declaración publicada</strong> de esa persona: quien tiene una de un año anterior aparece con ese patrimonio actualizado por inflación o por el dólar, no con su patrimonio de hoy. Por eso conviene mirar siempre la columna del año.</li>
    <li>Solo figuran quienes tienen declaración con totales publicada en el archivo de la Oficina Anticorrupción (años 2012 a 2024). Los demás, y por qué faltan, se explican en <a href="/metodologia#ddjj">la metodología</a>.</li>
  </ul>
</div>
<p class="fuente-pie">Fuentes: Declaraciones Juradas Patrimoniales Integrales (Oficina Anticorrupción, datos.jus.gob.ar, Ley 26.857); IPC Nivel General Nacional (INDEC); tipo de cambio de valuación (Banco Central, datos.gob.ar).</p>
