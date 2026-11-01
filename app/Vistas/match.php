<?php
use App\Datos;
/** @var array $pesos tema => 0..3 (validado en mvc.php)  @var string $camara '' | Diputados | Senadores  @var array $filas resultado de match_temas() */
$niveles = [0 => 'Nada', 1 => 'Poco', 2 => 'Bastante', 3 => 'Mucho'];
$hay = array_sum($pesos) > 0;
$mostrar = array_slice($filas, 0, 15);
?>
<nav class="migas" aria-label="Ubicación"><a href="/">Legisladores</a> <span aria-hidden="true">/</span> <span>Match por temas</span></nav>
<h1 class="titulo">¿Quién trabaja los temas que te importan?</h1>
<p class="bajada" style="margin-bottom:1.5rem">Marcá cuánto te importa cada tema y te mostramos qué legisladores en ejercicio concentran sus proyectos en esos mismos temas. Compara prioridades, no posiciones: que alguien presente muchos proyectos de salud no dice si están a favor o en contra de lo que vos pensás.</p>

<form class="filtros" method="get" action="/match" aria-label="Qué temas te importan">
  <?php foreach (temas_match() as $clave => $tema): ?>
    <label><?= e($tema) ?>
      <select name="<?= e($clave) ?>">
        <?php foreach ($niveles as $v => $txt): ?><option value="<?= $v ?>"<?= ($pesos[$tema] ?? 0) === $v ? ' selected' : '' ?>><?= e($txt) ?></option><?php endforeach; ?>
      </select>
    </label>
  <?php endforeach; ?>
  <label>Cámara
    <select name="camara">
      <option value="">Las dos</option>
      <option value="diputados"<?= $camara === 'Diputados' ? ' selected' : '' ?>>Diputados</option>
      <option value="senado"<?= $camara === 'Senadores' ? ' selected' : '' ?>>Senado</option>
    </select>
  </label>
  <div><button class="boton" type="submit">Ver coincidencias</button></div>
</form>

<?php if (!$hay): ?>
  <p class="tenue">Elegí al menos un tema con "Poco", "Bastante" o "Mucho" para ver resultados.</p>
<?php elseif (!$mostrar): ?>
  <p class="tenue">No hay legisladores con suficientes proyectos clasificados para comparar.</p>
<?php else: ?>
<section class="seccion" aria-labelledby="m-res">
  <div class="fila-titulo"><h2 id="m-res" class="seccion-titulo" style="margin:0">Mayor coincidencia</h2><p class="num"><?= count($mostrar) ?> de <?= count($filas) ?> legisladores comparados</p></div>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col" class="der">#</th><th scope="col">Legislador/a</th><th scope="col">Provincia</th><th scope="col">Sus temas principales</th><th scope="col" class="der">Coincidencia</th></tr></thead>
    <tbody>
    <?php foreach ($mostrar as $i => $f): $l = $f['l']; $vec = $f['vec']; arsort($vec); $top = array_slice(array_filter($vec, static fn($v) => $v > 0), 0, 3, true); ?>
      <tr>
        <td class="der num" data-etiqueta="Puesto"><?= $i + 1 ?></td>
        <td class="celda-nombre"><span><a href="<?= e(Datos::urlLegislador($l)) ?>?tab=proyectos"><?= e($l['nombre']) ?></a> <span class="tenue pequeno"><?= $l['camara'] === 'Senadores' ? 'Senado' : 'Diputados' ?> · <?= e($l['bloque']) ?></span></span></td>
        <td data-etiqueta="Provincia"><?= e($l['provincia']) ?></td>
        <td data-etiqueta="Sus temas"><span><?php $p = []; foreach ($top as $t => $v) { $p[] = e($t) . ' <span class="tenue">' . number_format($v * 100, 0, ',', '') . '%</span>'; } echo implode(', ', $p); ?></span></td>
        <td class="der num" data-etiqueta="Coincidencia"><strong><?= number_format($f['afinidad'] * 100, 0, ',', '') ?>%</strong></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="tenue pequeno">El porcentaje de cada tema es la parte de sus proyectos con tema que se giraron a una comisión de ese tema. La coincidencia va de 0% (presenta proyectos solo en temas que marcaste "Nada") a 100% (reparte sus proyectos entre los temas en la misma proporción que tus respuestas).</p>
</section>
<?php endif; ?>

<section class="seccion" aria-labelledby="m-como">
  <h2 id="m-como" class="seccion-titulo">Cómo se calcula</h2>
  <p>Tus respuestas se convierten en números (Nada 0, Poco 1, Bastante 2, Mucho 3). Para cada legislador se calcula qué parte de sus proyectos cae en cada uno de los 11 temas, según la comisión a la que se giró cada proyecto en primer lugar. La coincidencia es la similitud del coseno entre las dos listas de números.</p>
  <p>Solo se comparan los legisladores en ejercicio con al menos 10 proyectos con tema. No usa votaciones: las del Senado y las recientes de Diputados no traen el número de expediente, y sin él no se puede saber de qué tema era cada votación. Tampoco usa encuestas, declaraciones ni ideología. <a href="/metodologia#match">Más detalles en la metodología</a>.</p>
</section>
<p class="fuente-pie">Fuentes: proyectos y giros a comisiones de la Cámara de Diputados (datos.hcdn.gob.ar) y del Senado (senado.gob.ar). Tabla de temas por comisión: <a href="/metodologia#ejes">metodología</a>.</p>
