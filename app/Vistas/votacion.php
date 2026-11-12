<?php use App\Datos;
/** @var array $cab @var array $det @var string $filtro */
$esSen = ($cab['c'] ?? 'D') === 'S';
$orden = ['A' => 0, 'N' => 1, 'B' => 2, 'U' => 3, 'P' => 4, 'E' => 5, 'S' => 6, 'X' => 7];
$votos = $det['votos'];
$grupoVoto = static fn(string $c): string => in_array($c, ['A', 'N', 'B'], true) ? $c : ($c === 'P' ? 'P' : 'U');
$cuenta = ['A' => 0, 'N' => 0, 'B' => 0, 'U' => 0];
foreach ($votos as $v) { $g = $grupoVoto($v[3]); if (isset($cuenta[$g])) { $cuenta[$g]++; } }
usort($votos, static fn($x, $y) => [$x[1], $orden[$x[3]] ?? 9, $x[0]] <=> [$y[1], $orden[$y[3]] ?? 9, $y[0]]);
$analisis = analisis_bloques($det);
$resumen = Datos::resumenLey($cab);
$conDistintos = array_filter($analisis, static fn($b) => $b['distintos'] !== []);
$hayDistintos = $conDistintos !== [];
$totalDistintos = array_sum(array_map(static fn($b) => count($b['distintos']), $analisis));
$bloquesConPostura = count(array_filter($analisis, static fn($b) => $b['presentes'] >= 2 && $b['postura'] !== null && $b['postura'] !== 'dividido'));
$divididos = count(array_filter($analisis, static fn($b) => $b['postura'] === 'dividido'));
?>
<nav class="migas" aria-label="Ubicación"><a href="/votaciones">Votaciones</a> <span aria-hidden="true">/</span> <span><?= e(fecha_es($cab['f'])) ?></span></nav>
<p class="antetitulo"><?= $esSen ? 'Senado de la Nación' : 'Cámara de Diputados' ?><?= !empty($cab['p']) ? ' · período ' . (int)$cab['p'] : '' ?><?= !empty($cab['r']) ? ' · reunión ' . (int)$cab['r'] : '' ?><?= !empty($cab['s']) && !$esSen ? ' · sesión ' . e(strtolower($cab['s'])) : '' ?></p>
<h1 class="titulo" style="max-width:34ch;font-size:clamp(1.5rem,3.5vw,2.25rem)"><?= e($cab['t']) ?></h1>

<dl class="ficha" style="max-width:40rem;margin-top:1.5rem">
  <div><dt>Fecha y hora</dt><dd><?= e(fecha_es($cab['f'])) ?>, <?= e($cab['h']) ?></dd></div>
  <div><dt>Resultado</dt><dd><?= resultado_votacion((string)$cab['res']) ?></dd></div>
  <div><dt>Mayoría requerida</dt><dd><?= e($cab['m']) ?></dd></div>
  <?php if (!empty($cab['pres'])): $urlPres = url_preside($cab, $det, $esSen); ?><div><dt>Preside</dt><dd><?= $urlPres ? '<a href="' . e($urlPres) . '">' . e(nombre_acta($cab['pres'])) . '</a>' : e(nombre_acta($cab['pres'])) ?></dd></div><?php endif; ?>
  <div><dt>Afirmativos · negativos · abstenciones · ausentes</dt><dd class="num"><?= (int)$cab['a'] ?> · <?= (int)$cab['n'] ?> · <?= (int)$cab['b'] ?> · <?= (int)$cab['u'] ?></dd></div>
</dl>
<?= barra_resultado((int)$cab['a'], (int)$cab['n'], (int)$cab['b'], (int)$cab['u']) ?>

<?php if ($resumen): ?>
<section class="seccion resumen-ley" aria-labelledby="simple">
  <p class="resumen-etiqueta">Resumen hecho por DipuData a partir del texto oficial</p>
  <h2 id="simple" class="seccion-titulo">En palabras simples: <?= e($resumen['nombre']) ?></h2>
  <h3>Para qué sirve</h3>
  <p><?= e($resumen['para_que']) ?></p>
  <h3>A quién alcanza, según el texto</h3>
  <ul class="resumen-alcanza">
  <?php foreach ($resumen['alcanza'] as $al): ?>
    <li><b><?= e($al['quien']) ?>.</b> <?= e($al['que']) ?> <span class="tenue nowrap">(<?= e($al['donde']) ?>)</span></li>
  <?php endforeach; ?>
  </ul>
  <p class="tenue pequeno">Este resumen describe qué cambia la ley y a quién se dirige, con los artículos donde está escrito. No evalúa si es buena o mala ni dice quién gana o pierde. Resume la Ley <?= number_format((int)$resumen['ley'], 0, ',', '.') ?> tal como se publicó en el Boletín Oficial el <?= e(fecha_es($resumen['publicacion'])) ?>; lo que se votó en esta instancia pudo tener diferencias con el texto final. Lo que vale es el texto oficial: <a href="<?= e($resumen['fuente']['url']) ?>" rel="noopener"><?= e($resumen['fuente']['nombre']) ?></a>. ¿Ves un error? <a href="/correccion?desde=<?= e(urlencode('/votaciones/' . $cab['id'])) ?>">Pedí una corrección</a>.</p>
</section>
<?php endif; ?>

<?php if (!empty($det['expedientes'])): ?>
<section class="seccion" aria-labelledby="exps">
  <h2 id="exps" class="seccion-titulo">Expedientes que se trataron</h2>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Expediente</th><th scope="col">Título</th><th scope="col">Orden del día</th></tr></thead>
    <tbody>
    <?php foreach ($det['expedientes'] as [$ex, $tt, $od]): ?>
      <tr><td class="num nowrap" data-etiqueta="Expediente"><?= e($ex) ?></td><td><?= e($tt) ?></td><td class="num" data-etiqueta="Orden del día"><?= e($od ?: '—') ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if (!empty($det['nota'])): ?><p class="aviso"><?= e($det['nota']) ?></p><?php endif; ?>

<section class="seccion" aria-labelledby="por-bloque">
  <h2 id="por-bloque" class="seccion-titulo">Cómo votó cada bloque<?= !empty($det['bloque_en_acta']) ? ' <span class="tenue pequeno">(según el bloque que figura en el acta)</span>' : (!empty($det['camara']) ? ' <span class="tenue pequeno">(según el bloque actual de cada legislador)</span>' : '') ?></h2>
  <figure class="camara-hemiciclo" id="hv">
    <?= hemiciclo_votacion($det, $analisis, 'hv-svg', $esSen) ?>
    <figcaption class="tenue">Cada círculo es un <?= $esSen ? 'senador' : 'diputado' ?> del acta, del color de su voto. El arco de afuera marca el bloque: van de mayor a menor, de izquierda a derecha; ese orden no indica posición política ni el lugar en el recinto.<?= $hayDistintos ? ' El borde oscuro señala a quien votó distinto a la mayoría de su bloque.' : '' ?></figcaption>
  </figure>
  <ul class="leyenda leyenda-voto" aria-label="Referencias del gráfico">
    <li><i class="lv-A"></i>A favor</li><li><i class="lv-N"></i>En contra</li><li><i class="lv-B"></i>Abstención</li><li><i class="lv-U"></i>Ausente o sin votar</li>
    <?php if (!empty($cab['pres']) || array_filter($det['votos'], static fn($v) => $v[3] === 'P')): ?><li><i class="lv-P"></i>Preside</li><?php endif; ?>
    <?php if ($hayDistintos): ?><li><i class="lv-D"></i>Votó distinto a su bloque</li><?php endif; ?>
  </ul>
  <div class="tabla-caja"><table class="tabla tabla-bloques-voto">
    <thead><tr><th scope="col">Bloque</th><th scope="col" class="der">A favor</th><th scope="col" class="der">En contra</th><th scope="col" class="der">Abst.</th><th scope="col" class="der">Ausentes</th><th scope="col">Postura del bloque</th><th scope="col" class="der">Votaron distinto</th></tr></thead>
    <tbody>
    <?php foreach ($analisis as $bi => $b): $c = $b['c']; $sd = bloque_sin_dato($b['nombre']); ?>
      <tr>
        <td class="celda-bloque"><i class="punto" style="background:<?= e($sd ? '#cbd5e1' : color_bloque(mb_strtoupper($b['nombre'], 'UTF-8'))) ?>"></i><?php $ubl = $sd ? null : url_bloque_nombre($b['nombre'], $esSen); ?><?= $ubl ? '<a class="enlace-suave" href="' . e($ubl) . '">' . e($b['nombre']) . '</a>' : e($sd ? ($b['nombre'] !== '' ? $b['nombre'] : 'Sin bloque') : $b['nombre']) ?>
          <span class="barra-mini" aria-hidden="true"><?php foreach (['A' => 'a', 'N' => 'n', 'B' => 'b'] as $k => $cl): if (!empty($c[$k])): ?><span class="br-<?= $cl ?>" style="width:<?= round($c[$k] * 100 / $b['total'], 2) ?>%"></span><?php endif; endforeach; ?></span></td>
        <td class="der num" data-etiqueta="A favor"><?= (int)($c['A'] ?? 0) ?></td>
        <td class="der num" data-etiqueta="En contra"><?= (int)($c['N'] ?? 0) ?></td>
        <td class="der num" data-etiqueta="Abstenciones"><?= (int)($c['B'] ?? 0) ?></td>
        <td class="der num" data-etiqueta="Ausentes"><?= (int)(($c['U'] ?? 0) + ($c['S'] ?? 0) + ($c['X'] ?? 0) + ($c['E'] ?? 0)) ?></td>
        <td data-etiqueta="Postura"><?= $sd ? '<span class="tenue">No es un bloque</span>' : ($b['postura'] && $b['postura'] !== 'dividido' ? '<span class="voto voto-' . e($b['postura']) . '">' . e(postura_bloque($b['postura'], $b['presentes'])) . '</span>' : '<span class="tenue">' . e(postura_bloque($b['postura'], $b['presentes'])) . '</span>') ?></td>
        <td class="der num" data-etiqueta="Votaron distinto"><?= $b['distintos'] ? '<a href="#distinto-' . $bi . '">' . count($b['distintos']) . '</a>' : '<span class="tenue">' . ($b['presentes'] >= 2 && $b['postura'] !== 'dividido' && !$sd ? '0' : '—') . '</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <style><?php foreach ($analisis as $bi => $b): ?>#hv:has(~ .tabla-caja tbody tr:nth-child(<?= $bi + 1 ?>):hover) .hemiciclo > :is(a,g,path):not(.b<?= $bi ?>){opacity:.15}<?php endforeach; ?></style>
</section>

<section class="seccion" aria-labelledby="distintos">
  <h2 id="distintos" class="seccion-titulo">Quiénes votaron distinto a su bloque</h2>
  <?php if ($bloquesConPostura === 0): ?>
    <p>Ningún bloque tuvo dos o más legisladores presentes con un voto mayoritario, así que no hay con qué comparar.</p>
  <?php elseif (!$hayDistintos): ?>
    <p>En los <?= $bloquesConPostura ?> bloques con dos o más legisladores presentes, todos votaron igual que la mayoría de su bloque.<?= $divididos ? ' ' . ($divididos === 1 ? 'Un bloque quedó dividido en partes iguales' : $divididos . ' bloques quedaron divididos en partes iguales') . ' y no se compara a nadie dentro de ' . ($divididos === 1 ? 'él' : 'ellos') . '.' : '' ?></p>
  <?php else: ?>
    <p><b class="num"><?= $totalDistintos ?></b> <?= $totalDistintos === 1 ? 'legislador votó' : 'legisladores votaron' ?> distinto a la mayoría de su bloque, en <?= count($conDistintos) ?> de los <?= $bloquesConPostura ?> bloques con dos o más presentes.<?= $divididos ? ' ' . ($divididos === 1 ? 'Otro bloque quedó dividido en partes iguales' : $divididos . ' bloques más quedaron divididos en partes iguales') . ': ahí no hay mayoría con qué comparar.' : '' ?></p>
    <?php foreach ($conDistintos as $bi => $b): ?>
      <div class="distinto-bloque" id="distinto-<?= $bi ?>">
        <h3><i class="punto" style="background:<?= e(color_bloque(mb_strtoupper($b['nombre'], 'UTF-8'))) ?>"></i><?= e($b['nombre']) ?></h3>
        <p class="tenue pequeno">La mayoría del bloque votó <b><?= e(mb_strtolower(postura_bloque($b['postura'], $b['presentes']), 'UTF-8')) ?></b>: <?= (int)$b['c'][$b['postura']] ?> de <?= (int)$b['presentes'] ?> presentes.</p>
        <ul class="lista-distintos">
        <?php foreach ($b['distintos'] as $v): ?>
          <li><?= isset($v[4]) ? '<a href="' . ($esSen ? '/senador/' . e(substr($v[4], 2)) : '/diputado/' . e($v[4])) . '">' . e(nombre_acta($v[0])) . '</a>' : e(nombre_acta($v[0])) ?> <span class="tenue">· <?= e($v[2]) ?></span> <span class="voto voto-<?= e($v[3]) ?>"><?= e(etiqueta_voto($v[3])) ?></span></li>
        <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  <p class="tenue pequeno nota-metodo">Cómo se calcula: la postura del bloque es el voto (a favor, en contra o abstención) que eligió la mayoría de sus presentes. Los ausentes no cuentan como voto distinto. Si dos opciones empatan, el bloque figura como dividido. Votar distinto no implica una ruptura: a veces un bloque deja libertad de acción, y el acta no lo dice.<?= !empty($det['camara']) && empty($det['bloque_en_acta']) ? ' En esta votación el bloque es el que cada legislador tiene hoy, no necesariamente el que tenía ese día.' : '' ?></p>
</section>

<section class="seccion" aria-labelledby="por-diputado">
  <h2 id="por-diputado" class="seccion-titulo">Cómo votó cada <?= $esSen ? 'senador' : 'diputado' ?></h2>
  <?php foreach (['T' => '', 'A' => 'A', 'N' => 'N', 'B' => 'B', 'U' => 'U'] as $k => $c): ?><input type="radio" name="ver-voto" id="fv-<?= $k ?>" class="fv-radio"<?= $filtro === $c ? ' checked' : '' ?>><?php endforeach; ?>
  <p class="conteo filtro-voto" role="group" aria-label="Filtrar por voto">
    Ver:
    <label for="fv-T">todos <span class="num">(<?= count($votos) ?>)</span></label>
    <?php foreach (['A' => 'afirmativos', 'N' => 'negativos', 'B' => 'abstenciones', 'U' => 'ausentes'] as $c => $t): ?>
      <label for="fv-<?= $c ?>"><?= $t ?> <span class="num">(<?= $cuenta[$c] ?>)</span></label>
    <?php endforeach; ?>
  </p>
  <div class="tabla-caja"><table class="tabla tabla-votos">
    <thead><tr><th scope="col"><?= $esSen ? 'Senador' : 'Diputado' ?></th><th scope="col">Bloque</th><th scope="col">Distrito</th><th scope="col">Voto</th></tr></thead>
    <tbody>
    <?php foreach ($votos as $v): $ub = url_bloque_nombre((string)$v[1], $esSen); $ud = url_distrito((string)$v[2]); ?>
      <tr class="fv-<?= $grupoVoto($v[3]) ?>">
        <td><?= isset($v[4]) ? '<a href="' . ($esSen ? '/senador/' . e(substr($v[4], 2)) : '/diputado/' . e($v[4])) . '">' . e(nombre_acta($v[0])) . '</a>' : e(nombre_acta($v[0])) ?></td>
        <td class="tenue" data-etiqueta="Bloque"><?= $ub ? '<a class="enlace-suave" href="' . e($ub) . '">' . e($v[1]) . '</a>' : e($v[1]) ?></td>
        <td class="tenue" data-etiqueta="Distrito"><?= $ud ? '<a class="enlace-suave" href="' . e($ud) . '">' . e($v[2]) . '</a>' : e($v[2]) ?></td>
        <td data-etiqueta="Voto"><span class="voto voto-<?= e($v[3]) ?>"><?= e(etiqueta_voto($v[3])) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<p class="fuente-pie">Fuente: <?= !empty($det['camara']) ? ($esSen ? 'votaciones nominales publicadas por el Senado de la Nación (senado.gob.ar).' : 'votaciones nominales publicadas por la Cámara de Diputados, a través de ArgentinaDatos; los totales de las 141 votaciones coinciden con los de datacp.ar.') . (!empty($det['bloque_en_acta']) ? ' El bloque es el que figura en el acta oficial de esa votación.' : ' El bloque es el actual de cada legislador; quien ya no ejerce figura sin bloque.') : 'Votaciones nominales, Cámara de Diputados de la Nación (datos.hcdn.gob.ar). El bloque es el que tenía cada diputado al momento de la votación.' ?></p>
