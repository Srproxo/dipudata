<?php
/** @var array $meta @var string $contenido */
$nav = [
    'legisladores' => ['/', 'Legisladores'],
    'votaciones'   => ['/votaciones', 'Votaciones'],
    'leyes'        => ['/leyes', 'Leyes'],
    'bloques'      => ['/congreso/bloques', 'Bloques'],
    'senado'       => ['/senado', 'Senado'],
];
$mas = [
    'match'       => ['/match', 'Match por temas'],
    'comisiones'  => ['/comisiones', 'Comisiones'],
    'provincias'  => ['/congreso/provincias', 'Provincias'],
    'viajes'      => ['/viajes', 'Pasajes y misiones'],
    'remuneraciones' => ['/remuneraciones', 'Cuánto cobran'],
    'subsidios'   => ['/subsidios', 'Subsidios'],
    'congreso'    => ['/congreso', 'Cómo funciona el Congreso'],
    'metodologia' => ['/metodologia', 'Metodología'],
];
$masActivo = isset($mas[$meta['seccion']]);
$inf = \App\Datos::informe();
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($meta['title']) ?></title>
<meta name="description" content="<?= e($meta['description']) ?>">
<meta name="robots" content="<?= e($meta['robots']) ?>">
<link rel="canonical" href="<?= e($meta['canonical']) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="DipuData">
<meta property="og:locale" content="es_AR">
<meta property="og:title" content="<?= e($meta['title']) ?>">
<meta property="og:description" content="<?= e($meta['description']) ?>">
<meta property="og:url" content="<?= e($meta['canonical']) ?>">
<meta name="twitter:card" content="summary">
<meta name="theme-color" content="#0f172a">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/dipudata.css?v=<?= e(css_version()) ?>">
<?php if (!empty($meta['jsonld'])): ?>
<script type="application/ld+json"><?= json_encode($meta['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<header class="cabecera">
  <div class="contenedor cabecera-fila">
    <a class="marca" href="/" aria-label="DipuData, inicio">Dipu<span>Data</span></a>
    <nav class="menu" aria-label="Principal">
      <?php foreach ($nav as $clave => [$href, $texto]): ?>
        <a href="<?= e($href) ?>"<?= $meta['seccion'] === $clave ? ' aria-current="page"' : '' ?>><?= e($texto) ?></a>
      <?php endforeach; ?>
      <details class="mas"<?= $masActivo ? ' data-activo' : '' ?>>
        <summary>Más</summary>
        <div class="mas-panel">
          <?php foreach ($mas as $clave => [$href, $texto]): ?>
            <a href="<?= e($href) ?>"<?= $meta['seccion'] === $clave ? ' aria-current="page"' : '' ?>><?= e($texto) ?></a>
          <?php endforeach; ?>
        </div>
      </details>
    </nav>
  </div>
</header>
<main id="contenido" class="contenedor principal">
<?= $contenido ?>
</main>
<footer class="pie">
  <div class="contenedor pie-grilla">
    <div>
      <p class="pie-marca">DipuData</p>
      <p>Información pública sobre el Congreso de la Nación Argentina. Cada dato indica de dónde sale; lo que no tiene fuente, no se publica.</p>
      <?php if (!empty($inf['generado'])): ?><p>Datos actualizados el <?= e(fecha_es($inf['generado'])) ?>.</p><?php endif; ?>
    </div>
    <div>
      <p class="pie-titulo">Explorar</p>
      <ul>
        <li><a href="/">Legisladores</a></li>
        <li><a href="/congreso">Cómo funciona el Congreso</a></li>
        <li><a href="/congreso/bloques">Bloques</a></li>
        <li><a href="/congreso/provincias">Provincias</a></li>
        <li><a href="/votaciones">Votaciones nominales</a></li>
        <li><a href="/leyes">Leyes</a></li>
        <li><a href="/comisiones">Comisiones</a></li>
        <li><a href="/viajes">Pasajes y misiones</a></li>
        <li><a href="/remuneraciones">Cuánto cobran</a></li>
        <li><a href="/subsidios">Subsidios</a></li>
        <li><a href="/senado">Registros del Senado</a></li>
      </ul>
    </div>
    <div>
      <p class="pie-titulo">Fuentes</p>
      <ul>
        <li><a href="https://datos.hcdn.gob.ar/" rel="noopener">Datos abiertos de Diputados</a></li>
        <li><a href="https://www.senado.gob.ar/" rel="noopener">Senado de la Nación (datos abiertos)</a></li>
        <li><a href="https://www.congreso.gob.ar/constitucionNacional.php" rel="noopener">Constitución Nacional</a></li>
        <li><a href="/metodologia">Cómo trabajamos</a></li>
      </ul>
    </div>
  </div>
</footer>
</body>
</html>
