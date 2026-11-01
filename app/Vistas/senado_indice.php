<?php use App\Datos;
$idx = Datos::docsIndice();
$familias = [
    'sesiones' => ['Sesiones', 'Qué se dijo y qué se trató en cada sesión del Senado.'],
    'administracion' => ['Gestión administrativa', 'Decretos, resoluciones y disposiciones que ordenan el personal y los gastos del Senado.'],
    'compras' => ['Compras y contrataciones', 'Licitaciones del Senado, con las actas y los dictámenes cuando existen.'],
];
?>
<nav class="migas" aria-label="Ubicación"><a href="/congreso">Cómo funciona el Congreso</a> <span aria-hidden="true">/</span> <span>Registros del Senado</span></nav>
<h1 class="titulo">Los registros del Senado.</h1>
<p class="bajada">Documentos oficiales que el Senado de la Nación publica como datos abiertos: lo que dijeron sus integrantes en cada sesión, cómo se gestiona el personal y en qué se gasta. Acá se pueden buscar por año y por palabra.</p>
<p class="tenue pequeno">Los textos son los del propio Senado, sin cambios. Cada registro enlaza al documento original cuando el Senado lo publica.</p>

<?php foreach ($familias as $clave => [$titulo, $desc]): ?>
<section class="seccion" aria-labelledby="f-<?= e($clave) ?>">
  <div class="fila-titulo"><h2 id="f-<?= e($clave) ?>" class="seccion-titulo" style="margin:0"><?= e($titulo) ?></h2></div>
  <p class="tenue"><?= e($desc) ?></p>
  <ul class="indice" style="margin-top:.5rem;border-top:0">
    <?php foreach ($idx as $slug => $t): if ($t['familia'] !== $clave) continue; $anios = array_keys($t['anios']); ?>
      <li><a href="/senado/<?= e($slug) ?>"><span class="indice-t"><?= e($t['titulo']) ?></span>
        <span class="indice-d"><?= e($t['descripcion']) ?> <span class="num"><?= number_format((int)$t['total'], 0, ',', '.') ?> registros</span><?= $anios && $anios[0] !== '0000' ? ' · de ' . e(end($anios) === '0000' ? $anios[count($anios) - 2] ?? $anios[0] : end($anios)) . ' a ' . e($anios[0]) : '' ?></span></a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endforeach; ?>
<p class="fuente-pie">Fuente: Senado de la Nación, datos abiertos. Los listados se cargaron el <?= e(fecha_es(\App\Datos::informe()['generado'] ?? null)) ?>.</p>
