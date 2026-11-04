<?php
use App\Datos;
/** @var array $p @var list<array> $dip @var list<array> $sen */
$res = Datos::resultadosProvincia($p['slug']); $resMeta = Datos::resultados2023()['_meta'] ?? [];
$ent = static fn(int $n): string => number_format($n, 0, ',', '.');
$pct = static fn(int $n, int $d): string => $d > 0 ? number_format(100 * $n / $d, 1, ',', '.') . ' %' : '';
?>
<nav class="migas" aria-label="Ubicación"><a href="/congreso/provincias">Provincias</a> <span aria-hidden="true">/</span> <span><?= e($p['nombre']) ?></span></nav>
<p class="antetitulo">Delegación en el Congreso</p>
<h1 class="titulo"><?= e($p['nombre']) ?></h1>
<p class="bajada"><?= count($dip) ?> <?= count($dip) === 1 ? 'diputado' : 'diputados' ?> y <?= count($sen) ?> <?= count($sen) === 1 ? 'senador' : 'senadores' ?> en ejercicio.</p>
<?php foreach (['Senado' => $sen, 'Diputados' => $dip] as $titulo => $lista): if (!$lista) { continue; } ?>
<section class="seccion" aria-labelledby="s-<?= e(strtolower($titulo)) ?>">
  <h2 id="s-<?= e(strtolower($titulo)) ?>" class="seccion-titulo"><?= e($titulo) ?></h2>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Legislador</th><th scope="col">Bloque</th></tr></thead>
    <tbody>
    <?php foreach ($lista as $l): ?>
      <tr><td class="celda-nombre"><?= avatar($l, 32) ?><a href="<?= e(Datos::urlLegislador($l)) ?>"><?= e($l['nombre']) ?></a></td><td data-etiqueta="Bloque"><?= etiqueta_bloque($l) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endforeach; ?>
<?php if ($res): ?>
<section class="seccion" aria-labelledby="s-elecciones">
  <h2 id="s-elecciones" class="seccion-titulo">Elecciones generales de 2023</h2>
  <p>Votos que recibió cada agrupación en <?= e($p['nombre']) ?> el 22 de octubre de 2023, según el escrutinio provisorio publicado por la Dirección Nacional Electoral. El archivo informa votos por agrupación: no dice qué personas ocuparon cada banca, por eso acá no se las relaciona. Los porcentajes son sobre los votos positivos (los de agrupaciones) y se muestran en el orden de votos que figura en el resultado.</p>
  <p class="fuente-pie" style="margin-top:.5rem;padding-top:0;border-top:0">Mesas del archivo: <?= $ent($res['mesas']) ?>; electores de esas mesas: <?= $ent($res['electores']) ?>.</p>
  <?php foreach (['diputados' => 'Diputados nacionales', 'senadores' => 'Senadores nacionales'] as $k => $titulo): if (empty($res[$k])) { continue; } $r = $res[$k]; ?>
  <h3><?= e($titulo) ?></h3>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Agrupación</th><th scope="col" class="der">Votos</th><th scope="col" class="der">% de votos positivos</th></tr></thead>
    <tbody>
    <?php foreach ($r['agrupaciones'] as $a): ?>
      <tr><td class="celda-nombre"><?= e($a['nombre']) ?></td><td class="der num" data-etiqueta="Votos"><?= $ent($a['votos']) ?></td><td class="der num" data-etiqueta="% de votos positivos"><?= e($pct($a['votos'], $r['positivos'])) ?></td></tr>
    <?php endforeach; ?>
      <tr><td class="celda-nombre"><strong>Votos positivos</strong></td><td class="der num" data-etiqueta="Votos"><strong><?= $ent($r['positivos']) ?></strong></td><td class="der num"></td></tr>
      <tr><td class="celda-nombre">En blanco</td><td class="der num" data-etiqueta="Votos"><?= $ent($r['otros']['EN BLANCO']) ?></td><td class="der num"></td></tr>
      <tr><td class="celda-nombre">Nulos</td><td class="der num" data-etiqueta="Votos"><?= $ent($r['otros']['NULO']) ?></td><td class="der num"></td></tr>
      <tr><td class="celda-nombre">Impugnados, recurridos y de comando</td><td class="der num" data-etiqueta="Votos"><?= $ent($r['otros']['IMPUGNADO'] + $r['otros']['RECURRIDO'] + $r['otros']['COMANDO']) ?></td><td class="der num"></td></tr>
    </tbody>
  </table></div>
  <?php endforeach; ?>
  <p class="fuente-pie">Fuente: <a href="<?= e($resMeta['pagina'] ?? '') ?>" rel="noopener"><?= e($resMeta['fuente'] ?? '') ?></a>, elecciones generales 2023, escrutinio provisorio a nivel de mesa (mesas de padrón común). Es el recuento provisorio, que puede diferir del definitivo. Más detalle en la <a href="/metodologia#elecciones">metodología</a>.</p>
</section>
<?php endif; ?>
