<?php
use App\Datos;
/** @var list<array> $destinos @var array $dest destino elegido @var list<string> $distritos @var string $distSel */
$am = Datos::aportes()['_meta'] ?? [];
$nombreDestino = static function (string $n): string {
    $n = mb_convert_case(mb_strtolower($n, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    return strtr($n, ['(P.a.s.o.)' => 'PASO:', '(Generales)' => 'Generales:', '(Segunda Vuelta)' => 'Segunda vuelta:', 'Mercosur' => 'Mercosur', ' De ' => ' de ', ' Del ' => ' del ']);
};
$agrup = $distSel ? array_values(array_filter($dest['agrupaciones'], static fn($a) => $a['distrito'] === $distSel)) : $dest['agrupaciones'];
$tot = array_sum(array_column($agrup, 'total'));
$jur = array_sum(array_column($agrup, 'juridicas'));
$grupos = ['campaña' => 'Campañas', 'institucional' => 'Funcionamiento de los partidos (desenvolvimiento institucional)'];
?>
<nav class="migas" aria-label="Ubicación"><a href="/congreso">Cómo funciona el Congreso</a> <span aria-hidden="true">/</span> <span>Aportes a partidos</span></nav>
<h1 class="titulo">Aportes privados a los partidos.</h1>
<p class="bajada">Cada aporte privado a un partido o alianza se declara ante la Cámara Nacional Electoral, que publica quién aportó, cuánto y a qué agrupación. Acá están los totales por agrupación. No mostramos quién aportó: solo cuánto, cuántos aportes y cuántos aportantes distintos.</p>
<p class="aviso"><strong>Cómo leerlo.</strong> Una agrupación electoral no es lo mismo que un bloque del Congreso: las alianzas se arman para cada elección y en cada provincia, y un bloque puede reunir legisladores de varias. Los montos están en pesos de cada fecha, sin ajustar por inflación. Incluyen aportes en especie, valuados por la agrupación.</p>

<form class="filtros" method="get" action="/aportes" style="margin-top:1.5rem">
  <label>Destino del aporte
    <select name="destino">
      <?php foreach ($grupos as $tipo => $titulo): ?>
        <optgroup label="<?= e($titulo) ?>">
          <?php foreach ($destinos as $d): if ($d['tipo'] !== $tipo) continue; ?>
            <option value="<?= (int)$d['cod'] ?>"<?= $d['cod'] === $dest['cod'] ? ' selected' : '' ?>><?= e($nombreDestino($d['nombre'])) ?></option>
          <?php endforeach; ?>
        </optgroup>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Distrito
    <select name="distrito"><option value="">Todos</option>
      <?php foreach ($distritos as $di): ?><option value="<?= e($di) ?>"<?= $distSel === $di ? ' selected' : '' ?>><?= e($di) ?></option><?php endforeach; ?>
    </select>
  </label>
  <button class="boton" type="submit">Ver</button>
  <?php if (isset($_GET['destino']) || $distSel): ?><a class="enlace-limpiar" href="/aportes">Quitar filtros</a><?php endif; ?>
</form>

<section class="seccion" aria-labelledby="ap-t">
  <h2 id="ap-t" class="seccion-titulo"><?= e($nombreDestino($dest['nombre'])) ?><?= $distSel ? ' · ' . e($distSel) : '' ?></h2>
  <dl class="participacion">
    <div><dt>Total declarado</dt><dd><?= e(monto_corto($tot)) ?></dd></div>
    <div><dt>Aportes</dt><dd><?= number_format(array_sum(array_column($agrup, 'aportes')), 0, ',', '.') ?></dd></div>
    <div><dt>Agrupaciones</dt><dd><?= count($agrup) ?></dd></div>
    <div><dt>De empresas y entidades</dt><dd><?= $tot > 0 ? number_format($jur / $tot * 100, 1, ',', '') . '%' : '—' ?></dd></div>
  </dl>
  <p class="tenue pequeno">Aportes registrados entre el <?= e(fecha_es($dest['desde'])) ?> y el <?= e(fecha_es($dest['hasta'])) ?>.<?= $dest['tipo'] === 'institucional' && $dest['anio'] >= (int)substr((string)($am['hasta'] ?? ''), 0, 4) ? ' El año está en curso: el total sigue creciendo.' : '' ?></p>

  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Agrupación</th><th scope="col">Distrito</th><th scope="col" class="der">Total</th><th scope="col" class="der">Aportes</th><th scope="col" class="der">Aportantes</th><th scope="col" class="der">De empresas</th></tr></thead>
    <tbody>
    <?php $todas = isset($_GET['todas']) || count($agrup) <= 40; foreach ($todas ? $agrup : array_slice($agrup, 0, 30) as $a): ?>
      <tr>
        <td><span><?= e(preg_replace(['/^\(\d+\)\s*/', '/^\d{4} - [^-]+ - /'], '', $a['nombre'])) ?></span></td>
        <td class="tenue" data-etiqueta="Distrito"><?= e($a['distrito']) ?></td>
        <td class="der num" data-etiqueta="Total"><b style="white-space:nowrap">$ <?= number_format($a['total'], 0, ',', '.') ?></b></td>
        <td class="der num" data-etiqueta="Aportes"><?= number_format($a['aportes'], 0, ',', '.') ?></td>
        <td class="der num" data-etiqueta="Aportantes"><?= number_format($a['aportantes'], 0, ',', '.') ?></td>
        <td class="der num" data-etiqueta="De empresas"><?= $a['total'] > 0 && $a['juridicas'] != 0 ? number_format($a['juridicas'] / $a['total'] * 100, 0, ',', '') . '%' : '<span class="tenue">—</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if (!$todas): ?><p><a href="<?= e(url_con(['todas' => 1])) ?>">Ver las <?= count($agrup) ?> agrupaciones</a> <span class="tenue">(se muestran las 30 que más recibieron)</span></p><?php endif; ?>
  <p class="tenue pequeno">"Aportantes" cuenta personas o entidades distintas (por su CUIL o CUIT, que no se guarda ni se publica). "De empresas" es la parte del total aportada por personas jurídicas.<?= $dest['negativos'] ? ' Este destino incluye ' . (int)$dest['negativos'] . ' ' . ($dest['negativos'] === 1 ? 'registro' : 'registros') . ' con monto negativo (devoluciones), que se restan tal como figuran.' : '' ?></p>
</section>
<p class="fuente-pie">Fuente: Cámara Nacional Electoral, registro de aportantes (aportantes.electoral.gob.ar), Ley 26.215 de financiamiento de los partidos políticos. No se cuentan los <?= number_format((int)($am['anulados'] ?? 0), 0, ',', '.') ?> aportes que la Cámara marcó como anulados. <a href="/metodologia#aportes">Metodología</a>.</p>
