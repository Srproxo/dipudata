<?php use App\Datos;
/** @var array $cab @var array $det @var string $filtro */
$esSen = ($cab['c'] ?? 'D') === 'S';
$orden = ['A' => 0, 'N' => 1, 'B' => 2, 'U' => 3, 'P' => 4, 'E' => 5, 'S' => 6, 'X' => 7];
$votos = array_values(array_filter($det['votos'], static fn($v) => $filtro === '' || $v[3] === $filtro));
usort($votos, static fn($x, $y) => [$x[1], $orden[$x[3]] ?? 9, $x[0]] <=> [$y[1], $orden[$y[3]] ?? 9, $y[0]]);
?>
<nav class="migas" aria-label="Ubicación"><a href="/votaciones">Votaciones</a> <span aria-hidden="true">/</span> <span><?= e(fecha_es($cab['f'])) ?></span></nav>
<p class="antetitulo"><?= $esSen ? 'Senado de la Nación' : 'Cámara de Diputados' ?><?= !empty($cab['p']) ? ' · período ' . (int)$cab['p'] : '' ?><?= !empty($cab['r']) ? ' · reunión ' . (int)$cab['r'] : '' ?><?= !empty($cab['s']) && !$esSen ? ' · sesión ' . e(strtolower($cab['s'])) : '' ?></p>
<h1 class="titulo" style="max-width:34ch;font-size:clamp(1.5rem,3.5vw,2.25rem)"><?= e($cab['t']) ?></h1>

<dl class="ficha" style="max-width:40rem;margin-top:1.5rem">
  <div><dt>Fecha y hora</dt><dd><?= e(fecha_es($cab['f'])) ?>, <?= e($cab['h']) ?></dd></div>
  <div><dt>Resultado</dt><dd><?= resultado_votacion((string)$cab['res']) ?></dd></div>
  <div><dt>Mayoría requerida</dt><dd><?= e($cab['m']) ?></dd></div>
  <?php if (!empty($cab['pres'])): ?><div><dt>Preside</dt><dd><?= e(nombre_acta($cab['pres'])) ?></dd></div><?php endif; ?>
  <div><dt>Afirmativos · negativos · abstenciones · ausentes</dt><dd class="num"><?= (int)$cab['a'] ?> · <?= (int)$cab['n'] ?> · <?= (int)$cab['b'] ?> · <?= (int)$cab['u'] ?></dd></div>
</dl>
<?= barra_resultado((int)$cab['a'], (int)$cab['n'], (int)$cab['b'], (int)$cab['u']) ?>

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
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Bloque</th><th scope="col" class="der">A favor</th><th scope="col" class="der">En contra</th><th scope="col" class="der">Abst.</th><th scope="col" class="der">Ausentes</th></tr></thead>
    <tbody>
    <?php foreach ($det['bloques'] as $b => $c): ?>
      <tr><td><?= e($b) ?></td>
        <td class="der num" data-etiqueta="A favor"><?= (int)($c['A'] ?? 0) ?></td>
        <td class="der num" data-etiqueta="En contra"><?= (int)($c['N'] ?? 0) ?></td>
        <td class="der num" data-etiqueta="Abstenciones"><?= (int)($c['B'] ?? 0) ?></td>
        <td class="der num" data-etiqueta="Ausentes"><?= (int)($c['U'] ?? 0) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<section class="seccion" aria-labelledby="por-diputado">
  <h2 id="por-diputado" class="seccion-titulo">Cómo votó cada <?= $esSen ? 'senador' : 'diputado' ?></h2>
  <p class="conteo">
    Ver:
    <a href="/votaciones/<?= e($cab['id']) ?>"<?= $filtro === '' ? ' aria-current="true"' : '' ?>>todos</a> ·
    <?php foreach (['A' => 'afirmativos', 'N' => 'negativos', 'B' => 'abstenciones', 'U' => 'ausentes'] as $c => $t): ?>
      <a href="/votaciones/<?= e($cab['id']) ?>?voto=<?= $c ?>"<?= $filtro === $c ? ' aria-current="true"' : '' ?>><?= $t ?></a><?= $c !== 'U' ? ' ·' : '' ?>
    <?php endforeach; ?>
  </p>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col"><?= $esSen ? 'Senador' : 'Diputado' ?></th><th scope="col">Bloque</th><th scope="col">Distrito</th><th scope="col">Voto</th></tr></thead>
    <tbody>
    <?php foreach ($votos as $v): ?>
      <tr>
        <td><?= isset($v[4]) ? '<a href="' . ($esSen ? '/senador/' . e(substr($v[4], 2)) : '/diputado/' . e($v[4])) . '">' . e(nombre_acta($v[0])) . '</a>' : e(nombre_acta($v[0])) ?></td>
        <td class="tenue" data-etiqueta="Bloque"><?= e($v[1]) ?></td>
        <td class="tenue" data-etiqueta="Distrito"><?= e($v[2]) ?></td>
        <td data-etiqueta="Voto"><span class="voto voto-<?= e($v[3]) ?>"><?= e(etiqueta_voto($v[3])) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<p class="fuente-pie">Fuente: <?= !empty($det['camara']) ? ($esSen ? 'votaciones nominales publicadas por el Senado de la Nación (senado.gob.ar).' : 'votaciones nominales publicadas por la Cámara de Diputados, a través de ArgentinaDatos; los totales de las 141 votaciones coinciden con los de datacp.ar.') . (!empty($det['bloque_en_acta']) ? ' El bloque es el que figura en el acta oficial de esa votación.' : ' El bloque es el actual de cada legislador; quien ya no ejerce figura sin bloque.') : 'Votaciones nominales, Cámara de Diputados de la Nación (datos.hcdn.gob.ar). El bloque es el que tenía cada diputado al momento de la votación.' ?></p>
