<?php
use App\Datos;
/** @var string $modo 'indice' | 'slide'  @var array $meta */
$tit = static function (string $t): string { $t = mb_strtolower(trim($t)); $t = mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1); return mb_strlen($t) > 100 ? mb_substr($t, 0, 97) . '…' : $t; };
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($meta['title']) ?></title>
<meta name="robots" content="<?= e($meta['robots']) ?>">
<?php if ($modo === 'slide' && !$op['pausa']): ?><meta http-equiv="refresh" content="<?= (int)$op['seg'] ?>;url=<?= e($sig) ?>"><?php endif; ?>
<link rel="stylesheet" href="/assets/css/tv.css?v=<?= e((string)(@filemtime(RAIZ . '/assets/css/tv.css') ?: 1)) ?>">
<?php if ($modo === 'slide'): ?><style>:root{--bloque:<?= e(color_bloque($lg['bloque'])) ?>}</style><?php endif; ?>
</head>
<body>
<?php if ($modo === 'indice'): ?>
<main class="tv-indice">
  <p class="tv-sup">DipuData</p>
  <h1>Modo TV</h1>
  <p class="tv-sub">Diapositivas a pantalla completa con el perfil de cada legislador: quién es, cómo votó, qué proyectos tiene y qué declaró. Pasan solas, sin tocar nada.</p>
  <form method="get" action="/tv">
    <input type="hidden" name="iniciar" value="1">
    <label for="tv-lista">Qué mostrar</label>
    <select id="tv-lista" name="lista"><option value="todos">Diputados y senadores</option><option value="diputados">Solo diputados</option><option value="senado">Solo senadores</option></select>
    <label for="tv-seg">Segundos por diapositiva</label>
    <select id="tv-seg" name="seg"><?php foreach ([8, 12, 15, 20, 30] as $s): ?><option value="<?= $s ?>"<?= $s === 12 ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
    <p><button type="submit">Empezar</button></p>
  </form>
  <p class="tv-nota">Para pantalla completa, presioná F11 en el navegador (o el botón de pantalla completa de tu TV). Con el control remoto podés ir a la diapositiva anterior o siguiente y pausar. También podés ver un solo legislador desde su perfil. Todos los datos son públicos y salen de fuentes oficiales; cada diapositiva dice la fuente. <a href="/">Volver al sitio</a>.</p>
</main>
<?php else:
$sl = $dia[$nn - 1]; $nTot = count($dia);
$cargo = $lg['camara'] === 'Senadores' ? 'Senador/a nacional' : 'Diputado/a nacional';
$base = '/' . ($lg['camara'] === 'Senadores' ? 'senador' : 'diputado') . '/' . $lg['slug'];
$pausaUrl = tv_url($lg, $nn, ['pausa' => !$op['pausa']] + $op);
?>
<div class="tv">
<main class="tv-cuerpo <?= $sl['tipo'] === 'portada' ? 'tv-portada' : '' ?>">
<?php if ($sl['tipo'] === 'portada'): ?>
  <?php if (!empty($lg['foto'])): ?><img class="tv-foto" src="<?= e($lg['foto']) ?>" alt="Foto oficial de <?= e($lg['nombre']) ?>"><?php else: ?><span class="tv-foto tv-foto-vacia" aria-hidden="true"><?= e(iniciales($lg['nombre'])) ?></span><?php endif; ?>
  <div class="tv-cuerpo">
    <p class="tv-sup"><?= e($cargo) ?> por <?= e($lg['provincia']) ?></p>
    <h1 class="tv-titulo"><?= e($lg['nombre']) ?></h1>
    <p><span class="tv-chip"><?= e($lg['bloque']) ?></span></p>
    <?php if (!empty($lg['mandato'])): ?><p class="tv-sub">Mandato <?= e($lg['mandato']) ?><?= !empty($lg['jura']) ? ' · juró el ' . e(fecha_es($lg['jura'])) : '' ?></p><?php endif; ?>
    <p class="tv-nota">Fuente: registros oficiales de la <?= $lg['camara'] === 'Senadores' ? 'Honorable Cámara de Senadores' : 'Honorable Cámara de Diputados' ?> de la Nación.</p>
  </div>
<?php elseif ($sl['tipo'] === 'votos'): $mv = $sl['mv']; $mt = $sl['meta']; ?>
  <p class="tv-sup">En el recinto desde el <?= e(fecha_es($mt['desde'] ?? '2025-12-10')) ?></p>
  <h1 class="tv-titulo">Votó en <?= (int)$mv['emitio'] ?> de <?= (int)$mv['votaciones'] ?> votaciones nominales</h1>
  <div class="tv-nums">
    <div class="tv-num"><b><?= (int)$mv['afirmativos'] ?></b><span>afirmativos</span></div>
    <div class="tv-num"><b><?= (int)$mv['negativos'] ?></b><span>negativos</span></div>
    <div class="tv-num"><b><?= (int)$mv['abstenciones'] ?></b><span>abstenciones</span></div>
    <div class="tv-num"><b><?= (int)$mv['ausente'] ?></b><span>ausente</span></div>
  </div>
  <?php if (($mv['coincidencia_bloque_n'] ?? 0) >= 20 && isset($mv['coincidencia_bloque_pct'])): ?><p class="tv-sub">Votó como la mayoría de su bloque en el <?= e(number_format((float)$mv['coincidencia_bloque_pct'], 0, ',', '')) ?> % de las <?= (int)$mv['coincidencia_bloque_n'] ?> votaciones en que se pudo comparar.</p><?php endif; ?>
  <p class="tv-nota">Fuente: votaciones nominales publicadas por la Cámara, hasta el <?= e(fecha_es($mt['hasta'] ?? '')) ?>. Una cifra de votos no explica los motivos de ningún voto.</p>
<?php elseif ($sl['tipo'] === 'proyectos'): $py = $sl['py']; $rs = $py['resumen']; $ult = array_slice($py['proyectos'], 0, 3); ?>
  <p class="tv-sup">Proyectos en el Congreso</p>
  <p class="tv-gran"><?= (int)$rs['total'] ?> <span class="tv-sub" style="font-size:.4em">proyectos presentados o firmados</span></p>
  <p class="tv-sub">Entre el <?= e(fecha_es($rs['desde'])) ?> y el <?= e(fecha_es($rs['hasta'])) ?>.</p>
  <ul class="tv-lista">
    <?php foreach ($ult as $p): ?><li><?= e($tit((string)$p[4])) ?><small><?= e(fecha_es((string)$p[1])) ?> · <?= e((string)$p[2]) ?></small></li><?php endforeach; ?>
  </ul>
  <p class="tv-nota">Fuente: proyectos parlamentarios oficiales. Más proyectos no quiere decir más trabajo: mide cuántos presentó, no su calidad ni su resultado.</p>
<?php elseif ($sl['tipo'] === 'ddjj'): $dj = $sl['dj']; $est = $sl['est'];
  $ult = null; foreach ($dj as $x) { if ($ult === null || (int)$x['anio'] > (int)$ult['anio']) { $ult = $x; } } ?>
  <p class="tv-sup">Declaración jurada patrimonial</p>
  <?php if ($ult): ?>
    <h1 class="tv-titulo">Año fiscal <?= (int)$ult['anio'] ?></h1>
    <div class="tv-nums">
      <div class="tv-num"><b><?= e(monto_ars((string)$ult['patrimonio_neto_final'])) ?></b><span>patrimonio neto declarado</span></div>
      <div class="tv-num"><b><?= e(monto_ars((string)$ult['bienes_final'])) ?></b><span>bienes</span></div>
      <div class="tv-num"><b><?= e(monto_ars((string)$ult['deudas_final'])) ?></b><span>deudas</span></div>
    </div>
    <p class="tv-nota">Fuente: declaración presentada ante la Oficina Anticorrupción (datos.jus.gob.ar). Pesos de ese año, tal como se declararon. Lo que un archivo no trae no quiere decir que no exista.</p>
  <?php else: ?>
    <h1 class="tv-titulo">Sin cifras publicadas</h1>
    <p class="tv-sub"><?php
      if (!empty($est['inconsistente'])) { echo 'El archivo oficial trae declaraciones del mismo año con montos distintos, y este sitio no elige una.'; }
      elseif (!empty($est['sin_totales'])) { echo 'Figura una declaración, pero el archivo oficial trae los totales vacíos.'; }
      elseif (!empty($est['ambiguo'])) { echo 'El nombre corresponde a más de una persona en el archivo y no se publica para no mezclarlas.'; }
      elseif (!empty($est['asumio_2025'])) { echo 'Asumió el 10 de diciembre de 2025: su primera declaración todavía no está publicada.'; }
      else { echo 'No figura en el archivo de declaraciones publicado. Eso no quiere decir que no la haya presentado.'; } ?></p>
    <p class="tv-nota">Solo se publica lo que se puede verificar con la fuente oficial.</p>
  <?php endif; ?>
<?php else: ?>
  <p class="tv-sup">Más sobre <?= e($lg['nombre']) ?></p>
  <h1 class="tv-titulo">dipudata.com<?= e($base) ?></h1>
  <p class="tv-sub">Votaciones, proyectos, declaraciones y las fuentes de cada dato.</p>
  <p class="tv-nota">Todos los datos son públicos y salen de fuentes oficiales; cada uno indica la suya. DipuData no es un sitio oficial. ¿Un dato está mal? dipudata.com/correccion</p>
<?php endif; ?>
</main>
<footer class="tv-pie">
  <span><strong>DipuData</strong> · dipudata.com · <?= e($lg['nombre']) ?> · <?= $pos + 1 ?> de <?= count($ls) ?></span>
  <span class="tv-puntos" aria-label="Diapositiva <?= $nn ?> de <?= $nTot ?>"><?php for ($i = 1; $i <= $nTot; $i++): ?><i<?= $i === $nn ? ' class="on"' : '' ?>></i><?php endfor; ?></span>
  <nav class="tv-ctl" aria-label="Controles">
    <a href="<?= e($ant) ?>" rel="prev">◀ Anterior</a>
    <a href="<?= e($pausaUrl) ?>"><?= $op['pausa'] ? '▶ Seguir' : '⏸ Pausar' ?></a>
    <a href="<?= e($sig) ?>" rel="next">Siguiente ▶</a>
    <a href="<?= e($base) ?>">Ver perfil</a>
    <a href="/tv">Salir</a>
  </nav>
</footer>
</div>
<?php endif; ?>
</body>
</html>
