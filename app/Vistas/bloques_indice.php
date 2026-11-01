<?php use App\Datos; ?>
<nav class="migas" aria-label="Ubicación"><a href="/congreso">Cómo funciona el Congreso</a> <span aria-hidden="true">/</span> <span>Bloques</span></nav>
<h1 class="titulo">Los bloques legislativos.</h1>
<p class="bajada">Un bloque es el grupo de legisladores de una misma cámara que actúan en conjunto, por lo general porque pertenecen al mismo partido o a la misma alianza electoral. Cada cámara registra los suyos: acá figuran los que existen hoy y quiénes los integran.</p>
<p class="tenue">Un legislador puede cambiar de bloque durante su mandato, y hay bloques de una sola persona. Por eso la composición de las cámaras no coincide siempre con el resultado de la elección.</p>

<?php foreach (['Diputados' => 'diputados', 'Senadores' => 'senado'] as $camara => $ancla):
    $bl = Datos::bloques($camara); $tot = array_sum(array_column($bl, 'cantidad')); ?>
<section class="seccion" id="<?= e($ancla) ?>" aria-labelledby="t-<?= e($ancla) ?>">
  <div class="fila-titulo"><h2 id="t-<?= e($ancla) ?>" class="seccion-titulo"><?= $camara === 'Senadores' ? 'Senado' : 'Cámara de Diputados' ?></h2><p class="num"><?= (int)$tot ?> bancas · <?= count($bl) ?> bloques</p></div>
  <?= barra_composicion($bl) ?>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Bloque</th><th scope="col" class="der">Bancas</th><th scope="col" class="der">Proporción</th><th scope="col">Provincias</th></tr></thead>
    <tbody>
    <?php foreach ($bl as $b): ?>
      <tr>
        <td><i class="punto" style="background:<?= e(color_bloque($b['nombre_oficial'])) ?>"></i><a href="<?= e(Datos::urlBloque($b)) ?>"><?= e($b['nombre']) ?></a></td>
        <td class="der num" data-etiqueta="Bancas"><?= (int)$b['cantidad'] ?></td>
        <td class="der num" data-etiqueta="Proporción"><?= e(number_format($b['cantidad'] / max(1, $tot) * 100, 1, ',', '.')) ?>%</td>
        <td class="tenue" data-etiqueta="Provincias"><?= count($b['provincias']) ?> <?= count($b['provincias']) === 1 ? 'provincia' : 'provincias' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endforeach; ?>

<?php if (Datos::interbloques()): ?>
<section class="seccion" id="interbloques" aria-labelledby="t-inter">
  <div class="fila-titulo"><h2 id="t-inter" class="seccion-titulo" style="margin:0">Interbloques de Diputados</h2></div>
  <p class="tenue">Un interbloque reúne a varios bloques que actúan juntos en la Cámara.</p>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Interbloque</th><th scope="col" class="der">Diputados</th><th scope="col">Preside</th><th scope="col">Bloques que lo integran</th></tr></thead>
    <tbody>
    <?php foreach (Datos::interbloques() as $ib): ?>
      <tr>
        <td><b><?= e($ib['nombre']) ?></b></td>
        <td class="der num" data-etiqueta="Diputados"><?= (int)$ib['cantidad'] ?></td>
        <td data-etiqueta="Preside"><?= !empty($ib['presidente_slug']) ? '<a href="/diputado/' . e($ib['presidente_slug']) . '">' . e($ib['presidente']) . '</a>' : e($ib['presidente'] ?? '—') ?></td>
        <td class="tenue" data-etiqueta="Bloques"><?= e(implode(', ', $ib['bloques'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>
<p class="fuente-pie">Fuente: composición actual por bloques e interbloques de la Cámara de Diputados (datos.hcdn.gob.ar) y padrón de senadores en ejercicio (Senado de la Nación). Las cantidades se calculan a partir del padrón, no se cargan a mano.</p>
