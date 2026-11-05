<?php
use App\Datos;
/** @var string $modo 'indice' | 'form' */
if ($modo === 'indice'): ?>
<nav class="migas" aria-label="Ubicación"><a href="/">Legisladores</a> <span aria-hidden="true">/</span> <span>Test de afinidad por votos</span></nav>
<h1 class="titulo">¿Con qué legisladores coincidís?</h1>
<p class="bajada">Respondé a favor o en contra en diez votaciones reales del Congreso y mirá con qué diputados y senadores coincidís. No hay opiniones nuestras: la coincidencia sale de cómo votó cada uno.</p>
<p class="botones-cookies">
  <a class="boton" href="/afinidad/diputados">Empezar con diputados</a>
  <a class="boton boton-sec" href="/afinidad/senado">Empezar con senadores</a>
</p>
<section class="seccion" aria-labelledby="a-como">
  <h2 id="a-como" class="seccion-titulo">Cómo funciona</h2>
  <ul>
    <li>Te mostramos diez votaciones en general desde el 10 de diciembre de 2025. Marcás "a favor", "en contra" o "sin opinión".</li>
    <li>Para cada legislador contamos en cuántas de las votaciones que respondiste votó igual que vos. Solo cuentan las votaciones en que votó afirmativo o negativo: abstenciones y ausencias quedan afuera.</li>
    <li>Se muestran quienes votaron en al menos el 60 % de las que respondiste (y siempre cuatro o más), con el detalle de cuántas fueron.</li>
    <li>No hay cuentas ni se guarda nada: tus respuestas viajan solo en la dirección de la página.</li>
  </ul>
</section>
<section class="seccion" aria-labelledby="a-quien">
  <h2 id="a-quien" class="seccion-titulo">Cómo se eligieron las votaciones</h2>
  <p>Sin elección editorial: de todas las votaciones en general de cada cámara desde el 10 de diciembre de 2025, con al menos 80 % de presentes, se toman las diez en que la diferencia entre afirmativos y negativos fue menor, ordenadas por fecha. Más en la <a href="/metodologia#afinidad">metodología</a>.</p>
</section>
<?php else:
$niv = ['a' => 'A favor', 'n' => 'En contra', 's' => 'Sin opinión'];
$nomCam = $sel === 'senado' ? 'senadores' : 'diputados';
$pnom = static fn(string $id): string => 'p' . $id;
?>
<nav class="migas" aria-label="Ubicación"><a href="/">Legisladores</a> <span aria-hidden="true">/</span> <a href="/afinidad">Test de afinidad</a> <span aria-hidden="true">/</span> <span><?= e(ucfirst($nomCam)) ?></span></nav>
<h1 class="titulo">¿Con qué <?= e($nomCam) ?> coincidís?</h1>
<p class="bajada">Diez votaciones en general desde el 10 de diciembre de 2025, las más parejas. Respondé según tu opinión sobre cada proyecto.</p>

<?php if ($res !== null): ?>
<section class="seccion" aria-labelledby="a-res">
  <?php if (count($resp) < 4): ?>
    <h2 id="a-res" class="seccion-titulo">Respondé al menos cuatro</h2>
    <p>Para comparar hace falta que marques "a favor" o "en contra" en al menos cuatro votaciones. Ajustá las respuestas abajo.</p>
  <?php elseif (!$res): ?>
    <h2 id="a-res" class="seccion-titulo">Sin resultados</h2>
    <p>Ningún legislador votó afirmativo o negativo en suficientes de las votaciones que respondiste (al menos el 60 %, y siempre cuatro o más).</p>
  <?php else: $top = array_slice($res, 0, 15); ?>
    <div class="fila-titulo"><h2 id="a-res" class="seccion-titulo" style="margin:0">Quiénes votaron más parecido a vos</h2><p class="num"><?= count($top) ?> de <?= count($res) ?> <?= e($nomCam) ?> comparados</p></div>
    <div class="tabla-caja"><table class="tabla">
      <thead><tr><th scope="col" class="der">#</th><th scope="col">Legislador/a</th><th scope="col">Provincia</th><th scope="col" class="der">Coincidencia</th><th scope="col" class="der">Votaciones</th></tr></thead>
      <tbody>
      <?php foreach ($top as $i => $f): $l = $f['l']; ?>
        <tr>
          <td class="der num" data-etiqueta="Puesto"><?= $i + 1 ?></td>
          <td class="celda-nombre"><?= avatar($l, 36) ?><span><a href="<?= e(Datos::urlLegislador($l)) ?>?tab=votaciones"><?= e($l['nombre']) ?></a> <span class="tenue pequeno"><?= e($l['bloque']) ?></span></span></td>
          <td data-etiqueta="Provincia"><?= e($l['provincia']) ?></td>
          <td class="der num" data-etiqueta="Coincidencia"><strong><?= number_format($f['pct'] * 100, 0, ',', '') ?>%</strong></td>
          <td class="der num" data-etiqueta="Votaciones"><?= (int)$f['coinc'] ?> de <?= (int)$f['cons'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php
      $porBloque = [];
      foreach ($res as $f) { $porBloque[$f['l']['bloque']][] = $f['pct']; }
      $porBloque = array_filter($porBloque, static fn($v) => count($v) >= 3);
      uasort($porBloque, static fn($a, $b) => array_sum($b) / count($b) <=> array_sum($a) / count($a));
    ?>
    <?php if ($porBloque): ?>
    <h3>Por bloque</h3>
    <div class="tabla-caja"><table class="tabla">
      <thead><tr><th scope="col">Bloque</th><th scope="col" class="der">Coincidencia promedio</th><th scope="col" class="der">Integrantes comparados</th></tr></thead>
      <tbody>
      <?php foreach ($porBloque as $bl => $v): ?>
        <tr><td><?= e($bl) ?></td><td class="der num" data-etiqueta="Coincidencia promedio"><?= number_format(100 * array_sum($v) / count($v), 0, ',', '') ?>%</td><td class="der num" data-etiqueta="Integrantes"><?= count($v) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
    <p class="tenue pequeno">Coincidir en una votación no quiere decir compartir las mismas razones, y solo se miran diez votaciones en general: es una muestra, no un perfil político. Abstenciones y ausencias no cuentan. Quienes asumieron después de alguna votación no figuran en ella.</p>
  <?php endif; ?>
</section>
<?php endif; ?>

<form method="get" action="/afinidad/<?= e($sel) ?>" aria-label="Tus respuestas">
  <?php foreach ($preg as $i => $q): $nm = $pnom($q['id']); $cur = $resp[$q['id']] ?? 's'; ?>
  <fieldset class="seccion" style="border:0;padding:0;margin:0 0 1.25rem">
    <legend class="seccion-titulo" style="font-size:1.1rem"><span class="num"><?= $i + 1 ?>.</span> <?= e($q['titulo']) ?></legend>
    <p class="tenue pequeno" style="margin:.1rem 0 .5rem"><?= e(fecha_es($q['f'])) ?> · <a href="/votaciones/<?= e($q['id']) ?>">ver la votación completa</a> · título oficial: <?= e($q['oficial']) ?></p>
    <p class="botones-cookies">
      <?php foreach ($niv as $v => $txt): ?>
        <label><input type="radio" name="<?= e($nm) ?>" value="<?= $v ?>"<?= $cur === $v ? ' checked' : '' ?>> <?= e($txt) ?></label>
      <?php endforeach; ?>
    </p>
  </fieldset>
  <?php endforeach; ?>
  <button class="boton" type="submit">Ver con quiénes coincido</button>
</form>

<?php if ($res !== null && count($resp) >= 4): ?>
<section class="seccion" aria-labelledby="a-resultado">
  <h2 id="a-resultado" class="seccion-titulo">Cómo salió cada votación</h2>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Votación</th><th scope="col" class="der">A favor</th><th scope="col" class="der">En contra</th><th scope="col" class="der">Abst.</th><th scope="col" class="der">Ausentes</th></tr></thead>
    <tbody>
    <?php foreach ($preg as $q): ?>
      <tr><td><a href="/votaciones/<?= e($q['id']) ?>"><?= e($q['titulo']) ?></a></td><td class="der num" data-etiqueta="A favor"><?= (int)$q['a'] ?></td><td class="der num" data-etiqueta="En contra"><?= (int)$q['n'] ?></td><td class="der num" data-etiqueta="Abstenciones"><?= (int)$q['b'] ?></td><td class="der num" data-etiqueta="Ausentes"><?= (int)$q['u'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<section class="seccion" aria-labelledby="a-met">
  <h2 id="a-met" class="seccion-titulo">Cómo se calcula</h2>
  <p>Para cada legislador se cuenta en cuántas de las votaciones que respondiste votó igual que vos (a favor con afirmativo, en contra con negativo), sobre el total en que votó afirmativo o negativo. Se muestran quienes votaron en al menos el 60 % de las que respondiste (mínimo cuatro), para que pocos votos no den una coincidencia de azar. Las diez votaciones se eligieron por un criterio objetivo (las votaciones en general más parejas desde el 10 de diciembre de 2025, con al menos 80 % de presentes): ver <a href="/metodologia#afinidad">metodología</a>.</p>
</section>
<p class="fuente-pie">Fuente: votaciones nominales de <?= $sel === 'senado' ? 'senado.gob.ar' : 'la Cámara de Diputados (votaciones.hcdn.gob.ar y datos abiertos)' ?>, tal como las publica cada cámara.</p>
<?php endif; ?>
