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
<p class="fuente-pie">Fuente: padrón oficial de diputados en ejercicio (HCDN) y padrón de senadores en ejercicio (Senado de la Nación). Las cantidades se calculan a partir del padrón, no se cargan a mano.</p>
