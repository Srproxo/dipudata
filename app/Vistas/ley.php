<?php use App\Datos;
/** @var array $ley */
$refs = array_filter(preg_split('/\s+/', (string)($ley['r'] ?? '')) ?: []);
$n = (int)$ley['n'];
$deInfoleg = ($ley['fuente'] ?? '') === 'infoleg';
$num = static fn(int $x): string => number_format($x, 0, ',', '.');
$vin = Datos::leyVinculos($n);
$inf = $vin['infoleg'] ?? null;
$votos = $vin['votaciones'] ?? [];
$relac = Datos::leyesRelacionadas($n);
$anio = substr((string)($ley['fs'] ?? ''), 0, 4);
$delAnio = $anio !== '' ? count(array_filter(Datos::leyes(), static fn($l) => substr((string)$l[2], 0, 4) === $anio)) : 0;
$dias = (!empty($ley['fs']) && !empty($ley['fp'])) ? (int)((strtotime($ley['fp']) - strtotime($ley['fs'])) / 86400) : null;
$frase = static fn(string $t): string => mb_strtoupper(mb_substr($t, 0, 1, 'UTF-8'), 'UTF-8') . mb_strtolower(mb_substr($t, 1, null, 'UTF-8'), 'UTF-8');
$enlaceLey = static function (int $x) use ($num, $frase): string {
    $l = Datos::leyIndice($x);
    return $l ? '<a href="/leyes/' . $x . '">Ley ' . $num($x) . '</a> <span class="tenue">· ' . e(mb_strimwidth($frase((string)$l[1]), 0, 100, '…', 'UTF-8')) . '</span>' : 'Ley ' . $num($x);
};
$tipos = ['Decreto' => ['decreto', 'decretos'], 'Resolución' => ['resolución', 'resoluciones'], 'Disposición' => ['disposición', 'disposiciones'], 'Decisión Administrativa' => ['decisión administrativa', 'decisiones administrativas'], 'Resolución Conjunta' => ['resolución conjunta', 'resoluciones conjuntas']];
$otras = [];
foreach (($vin['otras_normas'] ?? []) as $t => $c) { $otras[] = $c . ' ' . ($tipos[$t][$c === 1 ? 0 : 1] ?? mb_strtolower($t, 'UTF-8') . ($c === 1 ? '' : 's')); }
$totalOtras = array_sum($vin['otras_normas'] ?? []);
// Medio círculo con la proporción de votos a favor (verde) y en contra (rojo): gráfico propio, sin imágenes de terceros.
$semi = static function (int $a, int $c): string {
    $t = $a + $c;
    if ($t <= 0) { return ''; }
    $L = M_PI * 30;
    return '<svg class="semi-voto" viewBox="0 0 68 38" width="68" height="38" role="img" aria-label="' . $a . ' a favor y ' . $c . ' en contra">'
        . '<path d="M4 34 A30 30 0 0 1 64 34" fill="none" stroke="#991b1b" stroke-width="8"/>'
        . '<path d="M4 34 A30 30 0 0 1 64 34" fill="none" stroke="#166534" stroke-width="8" stroke-dasharray="' . round($L * $a / $t, 2) . ' ' . round($L, 2) . '"/>'
        . '</svg>';
};
$fechaCorta = static fn(?string $f): string => $f ? (int)substr($f, 8, 2) . ' ' . ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][(int)substr($f, 5, 2) - 1] . ' ' . substr($f, 0, 4) : '';
$lista = static function (array $xs): string {
    if (count($xs) <= 1) { return implode('', $xs); }
    return implode(', ', array_slice($xs, 0, -1)) . ' y ' . end($xs);
};
?>
<nav class="migas" aria-label="Ubicación"><a href="/leyes">Leyes</a> <span aria-hidden="true">/</span> <span>Ley <?= $num($n) ?></span></nav>
<form class="buscar-ley" method="get" action="/leyes" role="search">
  <label for="buscar-ley-q" class="sr-solo">Buscar otra ley</label>
  <input id="buscar-ley-q" type="search" name="q" maxlength="80" placeholder="Buscar otra ley" autocomplete="off">
  <button class="boton boton-sec" type="submit">Buscar</button>
</form>
<p class="antetitulo">Ley <?= $num($n) ?></p>
<h1 class="titulo" style="max-width:36ch;font-size:clamp(1.4rem,3.2vw,2.1rem)"><?= e($ley['t'] ?? 'Sin título registrado') ?></h1>

<dl class="ficha" style="max-width:36rem;margin-top:1.5rem">
  <div><dt>Número</dt><dd class="num"><?= $num($n) ?></dd></div>
  <?php if (!empty($ley['fs'])): ?><div><dt>Sancionada el</dt><dd><?= e(fecha_es($ley['fs'])) ?><?= !empty($inf['sancion']) ? '<br><span class="tenue pequeno">Infoleg registra el ' . e(fecha_es($inf['sancion'])) . '</span>' : '' ?></dd></div><?php endif; ?>
  <?php if (!empty($vin['promulgacion'])): ?><div><dt>Promulgada por</dt><dd><?= e($vin['promulgacion']) ?></dd></div><?php endif; ?>
  <?php if (!empty($ley['fp'])): ?><div><dt>Publicada en el Boletín Oficial</dt><dd><?= e(fecha_es($ley['fp'])) ?></dd></div><?php endif; ?>
  <?php if (!empty($inf['observaciones'])): ?><div><dt>Observaciones de Infoleg</dt><dd class="pequeno"><?= e($inf['observaciones']) ?></dd></div><?php endif; ?>
</dl>

<?php if ($inf && !empty($inf['original'])): ?>
<p class="acciones-ley">
  <a class="boton" href="<?= e($inf['original']) ?>" rel="noopener">Leer el texto completo</a>
  <?php if (!empty($inf['actualizado'])): ?><a class="boton boton-sec" href="<?= e($inf['actualizado']) ?>" rel="noopener">Texto actualizado con sus modificaciones</a><?php endif; ?>
  <span class="tenue pequeno">En Infoleg, la base oficial de normas del Ministerio de Justicia (argentina.gob.ar).</span>
</p>
<?php endif; ?>

<section class="seccion" aria-labelledby="como-se-voto">
  <h2 id="como-se-voto" class="seccion-titulo">Cómo se votó</h2>
  <?php if ($votos): ?>
    <p class="tenue pequeno">Votaciones nominales del día de la sanción cuyo título coincide con el de la ley. En cada una se ve cómo votó cada bloque y cada legislador.</p>
    <ul class="lista-votos-ley">
    <?php foreach ($votos as $v): ?>
      <li class="con-semi">
        <?= $semi((int)$v['a'], (int)$v['n']) ?>
        <span class="voto-ley-txt"><a href="/votaciones/<?= e($v['id']) ?>"><?= $v['c'] === 'S' ? 'Senado' : 'Cámara de Diputados' ?>: <?= e(mb_strimwidth($v['t'], 0, 140, '…', 'UTF-8')) ?></a>
        <span class="tenue pequeno"><?= strip_tags(resultado_votacion((string)$v['res'])) ?> · <?= (int)$v['a'] ?> a favor, <?= (int)$v['n'] ?> en contra<?= !empty($v['conjunto']) ? ' · se votó junto con otros proyectos' : '' ?></span></span>
      </li>
    <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p>No encontramos una votación nominal del día de la sanción con este título. Muchas leyes se aprueban a mano alzada, sin registro voto por voto, o en una votación conjunta cuyo acta no trae el título de cada proyecto. <a href="/votaciones?anio=<?= e($anio) ?>">Ver las votaciones de <?= e($anio) ?></a>.</p>
  <?php endif; ?>
</section>

<section class="seccion" aria-labelledby="contexto">
  <h2 id="contexto" class="seccion-titulo">La ley en contexto</h2>
  <?php if (!empty($ley['fs'])): ?>
  <ol class="linea-ley" aria-label="Recorrido de la ley">
    <li><span class="punto" aria-hidden="true"></span><strong>Sanción</strong><span><?= e($fechaCorta($ley['fs'])) ?></span><span class="tenue pequeno">Congreso de la Nación</span></li>
    <?php if (!empty($vin['promulgacion'])): ?><li><span class="punto" aria-hidden="true"></span><strong>Promulgación</strong><span><?= e($vin['promulgacion']) ?></span><span class="tenue pequeno">Poder Ejecutivo</span></li><?php endif; ?>
    <?php if (!empty($ley['fp'])): ?><li><span class="punto" aria-hidden="true"></span><strong>Publicación</strong><span><?= e($fechaCorta($ley['fp'])) ?></span><span class="tenue pequeno">Boletín Oficial<?= $dias !== null && $dias >= 0 ? ' · ' . ($dias === 0 ? 'el mismo día' : ($dias === 1 ? '1 día después' : $dias . ' días después')) : '' ?></span></li><?php endif; ?>
  </ol>
  <?php endif; ?>
  <p class="contexto-ley">
    La Ley <?= $num($n) ?> fue sancionada por el Congreso de la Nación el <?= e(fecha_es($ley['fs'] ?? null)) ?><?php if (!empty($ley['fp'])): ?> y se publicó en el Boletín Oficial el <?= e(fecha_es($ley['fp'])) ?><?= $dias !== null && $dias >= 0 ? ', ' . ($dias === 0 ? 'el mismo día' : ($dias === 1 ? 'un día después' : $dias . ' días después')) : '' ?><?php endif; ?>.
    <?php if (!empty($vin['promulgacion'])): ?>El Poder Ejecutivo la promulgó con el <?= e($vin['promulgacion']) ?>.<?php endif; ?>
    <?php if ($delAnio > 1): ?>Es una de las <?= $num($delAnio) ?> leyes sancionadas en <?= e($anio) ?> que figuran en este sitio.<?php endif; ?>
  </p>
  <?php if ($inf): ?>
  <p class="contexto-ley">
    <?php if (!empty($vin['modifica_leyes'])): ?>Según Infoleg, esta ley modifica o complementa <?= count($vin['modifica_leyes']) === 1 ? 'una ley anterior' : count($vin['modifica_leyes']) . ' leyes anteriores' ?><?= !empty($vin['modifica_otras']) ? ' y ' . (int)$vin['modifica_otras'] . ' ' . ((int)$vin['modifica_otras'] === 1 ? 'norma de otro tipo' : 'normas de otro tipo') : '' ?>.<?php elseif (!empty($vin['modifica_otras'])): ?>Según Infoleg, esta ley modifica o complementa <?= (int)$vin['modifica_otras'] ?> <?= (int)$vin['modifica_otras'] === 1 ? 'norma' : 'normas' ?> que no son leyes.<?php else: ?>Infoleg no registra que esta ley modifique otras normas.<?php endif; ?>
    <?php if (!empty($vin['modificada_por_leyes']) || $totalOtras): ?>Después de su sanción, <?= $lista(array_merge(!empty($vin['modificada_por_leyes']) ? [count($vin['modificada_por_leyes']) === 1 ? 'una ley' : count($vin['modificada_por_leyes']) . ' leyes'] : [], $otras)) ?> la <?= (count($vin['modificada_por_leyes'] ?? []) + $totalOtras) === 1 ? 'modificó, reglamentó o complementó' : 'modificaron, reglamentaron o complementaron' ?>.<?php else: ?>Infoleg no registra normas posteriores que la modifiquen o reglamenten.<?php endif; ?>
  </p>
  <?php endif; ?>
  <?php if ($inf):
    $antes = count($vin['modifica_leyes'] ?? []) + (int)($vin['modifica_otras'] ?? 0);
    $despues = count($vin['modificada_por_leyes'] ?? []) + $totalOtras; ?>
  <div class="esquema-ley" role="img" aria-label="Modifica o complementa <?= $antes ?> normas anteriores; <?= $despues ?> normas posteriores la modificaron, reglamentaron o complementaron">
    <div class="caja"><span class="cifra"><?= $num($antes) ?></span><span>normas anteriores que modifica o complementa</span></div>
    <span class="flecha" aria-hidden="true"></span>
    <div class="caja caja-ley"><span class="cifra">Ley <?= $num($n) ?></span><span><?= e($anio) ?></span></div>
    <span class="flecha" aria-hidden="true"></span>
    <div class="caja"><span class="cifra"><?= $num($despues) ?></span><span>normas posteriores que la modifican, reglamentan o complementan</span></div>
  </div>
  <?php endif; ?>
  <?php if (!empty($vin['modifica_leyes']) || !empty($vin['modificada_por_leyes'])): ?>
  <div class="ley-vinculos">
    <?php if (!empty($vin['modifica_leyes'])): ?><div><h3>Leyes que modifica o complementa</h3><ul><?php foreach (array_slice($vin['modifica_leyes'], 0, 15) as $x): ?><li><?= $enlaceLey((int)$x) ?></li><?php endforeach; ?></ul><?php if (count($vin['modifica_leyes']) > 15): ?><p class="tenue pequeno">Y <?= count($vin['modifica_leyes']) - 15 ?> más (ver el texto completo).</p><?php endif; ?></div><?php endif; ?>
    <?php if (!empty($vin['modificada_por_leyes'])): ?><div><h3>Leyes posteriores que la modifican o complementan</h3><ul><?php foreach (array_slice($vin['modificada_por_leyes'], 0, 15) as $x): ?><li><?= $enlaceLey((int)$x) ?></li><?php endforeach; ?></ul><?php if (count($vin['modificada_por_leyes']) > 15): ?><p class="tenue pequeno">Y <?= count($vin['modificada_por_leyes']) - 15 ?> más.</p><?php endif; ?></div><?php endif; ?>
  </div>
  <?php endif; ?>
</section>


<?php if (!empty($ley['s'])): ?>
<section class="seccion" aria-labelledby="sumario">
  <h2 id="sumario" class="seccion-titulo"><?= $deInfoleg ? 'Sumario de Infoleg' : 'Sumario oficial' ?></h2>
  <p class="sumario"><?= e($ley['s']) ?></p>
  <?php if ($refs): ?>
    <p class="tenue pequeno" style="margin-top:1rem">Palabras clave</p>
    <ul class="refs"><?php foreach (array_slice($refs, 0, 40) as $r): ?><li><a href="/leyes?q=<?= e(urlencode($r)) ?>" style="color:inherit;text-decoration:none"><?= e(mb_strtolower($r, 'UTF-8')) ?></a></li><?php endforeach; ?></ul>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($relac): ?>
<section class="seccion" aria-labelledby="relacionadas">
  <h2 id="relacionadas" class="seccion-titulo">Leyes sobre temas parecidos</h2>
  <ul class="lista-leyes-rel">
  <?php foreach ($relac as $l): ?>
    <li><a href="/leyes/<?= (int)$l[0] ?>">Ley <?= $num((int)$l[0]) ?></a> <span class="tenue">· <?= e(mb_strimwidth($frase((string)$l[1]), 0, 110, '…', 'UTF-8')) ?> · <?= e(substr((string)$l[2], 0, 4)) ?></span></li>
  <?php endforeach; ?>
  </ul>
  <p class="tenue pequeno">Elegidas por las palabras clave que la Cámara de Diputados asignó a cada ley.</p>
</section>
<?php endif; ?>

<p class="fuente-pie">Fuentes: <?= $deInfoleg ? 'título, sumario y fechas de la Base Infoleg de Normativa Nacional (la Cámara de Diputados todavía no publicó esta ley en sus datos abiertos); se muestran tal como los publica Infoleg' : 'Sumario de leyes y Leyes promulgadas, Cámara de Diputados de la Nación (datos.hcdn.gob.ar); el sumario se muestra tal como lo publica la Cámara' ?>. Texto completo, decreto de promulgación y normas vinculadas: Base Infoleg de Normativa Nacional, Ministerio de Justicia (datos.jus.gob.ar, CC BY 4.0). Votaciones: actas nominales de cada cámara, cruzadas por fecha y título (ver <a href="/metodologia">metodología</a>).</p>
