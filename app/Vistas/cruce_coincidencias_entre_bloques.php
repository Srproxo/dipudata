<?php
use App\Datos;
/** @var array $cr @var array $info */
$esSen = ($_GET['camara'] ?? '') === 'senado';
$cam = $esSen ? 'S' : 'D';
$af = $cr['afinidad'][$cam] ?? ['disputadas' => 0, 'pares_evaluados' => 0, 'top' => [], 'bloques' => []];
$m = $cr['_meta'] ?? [];
$leg = static function (string $k) use ($esSen): ?array { return Datos::legislador($esSen ? 'Senadores' : 'Diputados', $esSen ? substr($k, 2) : $k); };
$bloques = $af['bloques'];
$mas = array_slice($bloques, 0, 10);
$menos = array_reverse(array_slice($bloques, -10));
// Para que la lista no la ocupe una sola persona, cada legislador aparece una sola vez (en su par con más coincidencia).
$pares = []; $vistos = [];
foreach ($af['top'] as $p) {
    if (isset($vistos[$p[0]]) || isset($vistos[$p[1]])) { continue; }
    $pares[] = $p; $vistos[$p[0]] = $vistos[$p[1]] = true;
    if (count($pares) === 20) { break; }
}
$umbral = (int)round((float)($m['umbral_disputada'] ?? .8) * 100);
$filaBloque = static function (array $b): string {
    return '<tr><td data-etiqueta="Bloques">' . e($b[0]) . ' <span class="tenue">y</span> ' . e($b[1]) . '</td><td class="der num" data-etiqueta="Votaron igual"><span class="pct-barra">' . e(number_format((float)$b[2], 1, ',', '.')) . ' %'
        . '<span class="barra-mini" aria-hidden="true"><span style="width:' . round((float)$b[2], 1) . '%;background:var(--acento)"></span></span></span></td><td class="der num" data-etiqueta="Votos comparados">' . number_format((int)$b[3], 0, ',', '.') . '</td></tr>';
};
?>
<nav class="migas" aria-label="Ubicación"><a href="/cruces">Cruces de datos</a> / Coincidencias entre bloques</nav>
<p class="antetitulo">Cruce de datos · <?= $esSen ? 'Senado' : 'Diputados' ?></p>
<h1 class="titulo">Coincidencias entre bloques distintos.</h1>
<p class="bajada">Qué <?= $esSen ? 'senadores' : 'diputados' ?> de bloques distintos votaron igual en las votaciones disputadas del mandato actual, y cuánto coinciden los bloques entre sí.</p>

<nav class="pestanas" aria-label="Cámara" style="margin-top:1.5rem">
  <a href="/cruces/coincidencias-entre-bloques"<?= !$esSen ? ' aria-current="page"' : '' ?>>Diputados</a>
  <a href="/cruces/coincidencias-entre-bloques?camara=senado"<?= $esSen ? ' aria-current="page"' : '' ?>>Senado</a>
</nav>
<p class="conteo">Se comparan <?= (int)$af['disputadas'] ?> votaciones disputadas: aquellas en las que la opción ganadora sacó menos del <?= $umbral ?> % de los votos emitidos. Las casi unánimes no se usan porque en ellas casi todos coinciden.</p>

<h2 class="seccion-titulo" style="margin-top:2rem">Legisladores de bloques distintos que más votaron igual</h2>
<div class="tabla-caja"><table class="tabla tabla-cifras">
  <thead><tr><th scope="col">Legisladores</th><th scope="col" class="der">Votaron igual</th><th scope="col"><span class="sr-solo">Comparar</span></th></tr></thead>
  <tbody>
  <?php foreach ($pares as [$a, $b, $coin, $com]): $la = $leg($a); $lb = $leg($b); if (!$la || !$lb) continue; ?>
    <tr>
      <td data-etiqueta="Legisladores"><span class="par-legisladores">
        <span><a href="<?= e(Datos::urlLegislador($la)) ?>"><?= e($la['nombre']) ?></a> <span class="tenue pequeno">(<?= e($la['bloque']) ?>)</span></span>
        <span><a href="<?= e(Datos::urlLegislador($lb)) ?>"><?= e($lb['nombre']) ?></a> <span class="tenue pequeno">(<?= e($lb['bloque']) ?>)</span></span>
      </span></td>
      <td class="der num" data-etiqueta="Votaron igual"><span class="celda-valor"><?= e(pct_txt($coin, $com)) ?><span class="tenue pequeno block"><?= (int)$coin ?> de <?= (int)$com ?></span></span></td>
      <td data-etiqueta=""><a href="/comparar?a=<?= e(clave_legislador($la)) ?>&amp;b=<?= e(clave_legislador($lb)) ?>">Comparar</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p class="tenue pequeno" style="max-width:62ch">De <?= number_format((int)$af['pares_evaluados'], 0, ',', '.') ?> pares de legisladores de bloques distintos con <?= (int)($m['min_comunes'][$cam] ?? 25) ?> votaciones disputadas o más en común. Cada legislador aparece una sola vez, en su par con más coincidencia; con empates, primero el par con más votaciones en común.</p>

<div class="dos-col" style="margin-top:2rem">
  <section>
    <h2 class="seccion-titulo">Bloques que más votaron igual</h2>
    <div class="tabla-caja"><table class="tabla tabla-cifras">
      <thead><tr><th scope="col">Bloques</th><th scope="col" class="der">Votaron igual</th><th scope="col" class="der">Votos comparados</th></tr></thead>
      <tbody><?php foreach ($mas as $b) { echo $filaBloque($b); } ?></tbody>
    </table></div>
  </section>
  <section>
    <h2 class="seccion-titulo">Bloques que menos votaron igual</h2>
    <div class="tabla-caja"><table class="tabla tabla-cifras">
      <thead><tr><th scope="col">Bloques</th><th scope="col" class="der">Votaron igual</th><th scope="col" class="der">Votos comparados</th></tr></thead>
      <tbody><?php foreach ($menos as $b) { echo $filaBloque($b); } ?></tbody>
    </table></div>
  </section>
</div>

<div class="aviso">
  <p style="margin:0 0 .5rem"><strong>Cómo leer este cruce.</strong></p>
  <ul class="lista-simple">
    <li><strong>Votaron igual</strong>: de las votaciones disputadas en las que los dos emitieron un voto, en cuántas votaron lo mismo (a favor, en contra o abstención). Si uno estuvo ausente, esa votación no se compara.</li>
    <li>Para los bloques se suman todos los pares de legisladores de un bloque con los del otro (solo bloques de dos o más integrantes). «Votos comparados» es la cantidad de comparaciones de a pares, no de votaciones.</li>
    <li>El bloque es el actual de cada legislador. Dos bloques pueden integrar un mismo interbloque o una misma alianza: votar igual no dice que haya un acuerdo entre ellos.</li>
    <li>Para comparar a cualquier par de legisladores en todas las votaciones del mandato, usá <a href="/comparar">Comparar</a>.</li>
  </ul>
</div>
<p class="fuente-pie">Fuentes: votaciones nominales de <?= $esSen ? 'el Senado de la Nación (senado.gob.ar)' : 'la Cámara de Diputados (votaciones.hcdn.gob.ar y ArgentinaDatos)' ?>; padrón de legisladores en ejercicio de cada cámara.</p>
