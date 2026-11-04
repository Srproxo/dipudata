<?php
use App\Datos;
/** @var array $p @var list<array> $dip @var list<array> $sen */
$resultados = Datos::resultadosProvincia($p['slug']);
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
<?php if ($resultados): $primeraGeneral = true; ?>
<section class="seccion" aria-labelledby="s-elecciones">
  <h2 id="s-elecciones" class="seccion-titulo">Elecciones nacionales</h2>
  <p>Votos que recibió cada agrupación en <?= e($p['nombre']) ?> en las elecciones nacionales de <?= e(implode(', ', array_unique(array_map(static fn($r) => (string)$r['meta']['anio'], $resultados)))) ?>, según el escrutinio provisorio publicado por la Dirección Nacional Electoral. El archivo informa votos por agrupación: no dice qué personas ocuparon cada banca, por eso acá no se las relaciona. Las agrupaciones cambian de nombre y de alianza entre elecciones, por eso no se las empareja entre años: cada elección se muestra con los nombres de su archivo.</p>

  <h3>Resumen, elección por elección</h3>
  <div class="tabla-caja"><table class="tabla tabla-cifras">
    <thead><tr><th scope="col">Elección</th><th scope="col" class="der">Electores</th><th scope="col" class="der">Votos emitidos</th><th scope="col" class="der">Participación</th><th scope="col" class="der">En blanco</th><th scope="col">Agrupación más votada (Diputados)</th></tr></thead>
    <tbody>
    <?php foreach ($resultados as $r): $m = $r['meta']; $d = $r['datos']; $dp = $d['diputados']; $top = $dp['agrupaciones'][0] ?? null; ?>
      <tr>
        <td data-etiqueta="Elección"><a href="#e-<?= e($m['clave']) ?>"><?= e($m['tipo'] === 'PASO' ? 'PASO ' . $m['anio'] : 'Generales ' . $m['anio']) ?></a></td>
        <td class="der num" data-etiqueta="Electores"><?= $ent($d['electores']) ?></td>
        <td class="der num" data-etiqueta="Votos emitidos"><?= $ent($dp['votantes']) ?></td>
        <td class="der num" data-etiqueta="Participación"><?= e($pct($dp['votantes'], $d['electores'])) ?></td>
        <td class="der num" data-etiqueta="En blanco"><?= e($pct($dp['otros']['EN BLANCO'], $dp['votantes'])) ?></td>
        <td data-etiqueta="Más votada"><?= $top ? e($top['nombre']) . ' <span class="tenue pequeno">(' . e($pct($top['votos'], $dp['positivos'])) . ')</span>' : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="tenue pequeno">Electores: los de las mesas de padrón común del archivo. Votos emitidos: todos los votos del cargo de Diputados nacionales (positivos, en blanco, nulos, impugnados y recurridos). En blanco, sobre los votos emitidos; el porcentaje de la agrupación, sobre los votos positivos. En las PASO cada agrupación suma los votos de todas sus listas internas.</p>

  <?php foreach ($resultados as $r): $m = $r['meta']; $res = $r['datos']; $abierta = $m['tipo'] === 'GENERAL' && $primeraGeneral; if ($m['tipo'] === 'GENERAL') { $primeraGeneral = false; } ?>
  <details class="ver-datos eleccion" id="e-<?= e($m['clave']) ?>"<?= $abierta ? ' open' : '' ?>>
    <summary><strong><?= e($m['eleccion']) ?></strong></summary>
    <p class="fuente-pie" style="margin-top:.5rem;padding-top:0;border-top:0">Mesas del archivo: <?= $ent($res['mesas']) ?>; electores de esas mesas: <?= $ent($res['electores']) ?>.</p>
    <?php foreach (['diputados' => 'Diputados nacionales', 'senadores' => 'Senadores nacionales'] as $k => $titulo): if (empty($res[$k])) { continue; } $rr = $res[$k]; ?>
    <h4 class="sub-titulo"><?= e($titulo) ?></h4>
    <div class="tabla-caja"><table class="tabla">
      <thead><tr><th scope="col">Agrupación</th><th scope="col" class="der">Votos</th><th scope="col" class="der">% de votos positivos</th></tr></thead>
      <tbody>
      <?php foreach ($rr['agrupaciones'] as $a): ?>
        <tr><td class="celda-nombre"><?= e($a['nombre']) ?></td><td class="der num" data-etiqueta="Votos"><?= $ent($a['votos']) ?></td><td class="der num" data-etiqueta="% de votos positivos"><?= e($pct($a['votos'], $rr['positivos'])) ?></td></tr>
      <?php endforeach; ?>
        <tr><td class="celda-nombre"><strong>Votos positivos</strong></td><td class="der num" data-etiqueta="Votos"><strong><?= $ent($rr['positivos']) ?></strong></td><td class="der num"></td></tr>
        <tr><td class="celda-nombre">En blanco</td><td class="der num" data-etiqueta="Votos"><?= $ent($rr['otros']['EN BLANCO']) ?></td><td class="der num"></td></tr>
        <tr><td class="celda-nombre">Nulos</td><td class="der num" data-etiqueta="Votos"><?= $ent($rr['otros']['NULO']) ?></td><td class="der num"></td></tr>
        <tr><td class="celda-nombre">Impugnados, recurridos y de comando</td><td class="der num" data-etiqueta="Votos"><?= $ent($rr['otros']['IMPUGNADO'] + $rr['otros']['RECURRIDO'] + $rr['otros']['COMANDO']) ?></td><td class="der num"></td></tr>
      </tbody>
    </table></div>
    <?php endforeach; ?>
    <p class="fuente-pie">Fuente: <a href="<?= e($m['pagina']) ?>" rel="noopener"><?= e($m['fuente']) ?></a>, <?= e(lcfirst($m['eleccion'])) ?>, escrutinio provisorio a nivel de mesa (mesas de padrón común). Es el recuento provisorio, que puede diferir del definitivo.</p>
  </details>
  <?php endforeach; ?>
  <p class="tenue pequeno">Más detalle en la <a href="/metodologia#elecciones">metodología</a>.</p>
</section>
<?php endif; ?>
