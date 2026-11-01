<?php use App\Datos; $r = Datos::remuneraciones();
$linea = static function (array $items): string { $h = ''; foreach ($items as $i) { $h .= '<tr><td>' . e($i['concepto']) . '</td><td class="der num" data-etiqueta="Importe">' . e(($i['importe'] < 0 ? '− ' : '') . monto_ars((string)abs($i['importe']))) . '</td></tr>'; } return $h; }; ?>
<p class="antetitulo">Cámara de Diputados</p>
<h1 class="titulo">Cuánto cobra un legislador.</h1>
<p class="bajada">La Cámara publica los importes que se liquidan a los legisladores nacionales: la dieta, los gastos de representación, el desarraigo, la movilidad y los descuentos. Son los mismos para todos; lo que cambia es cuáles corresponden a cada uno.</p>
<p class="aviso"><strong>Cómo leerlo.</strong> Son los valores que publica la Cámara de Diputados en sus datos abiertos (actualizados en agosto de 2026). El desarraigo corresponde a quienes viven lejos de la Capital, y la movilidad depende de la opción que elige cada legislador. Ni la Cámara ni este sitio informan acá cuánto percibe en total cada persona.</p>

<?php foreach (['remuneraciones' => 'Remuneración', 'desarraigo' => 'Desarraigo', 'movilidad' => 'Movilidad (según la opción elegida)', 'descuentos' => 'Descuentos'] as $k => $t): if (empty($r[$k])) continue; ?>
<section class="seccion" aria-labelledby="r-<?= e($k) ?>">
  <h2 id="r-<?= e($k) ?>" class="seccion-titulo"><?= e($t) ?></h2>
  <div class="tabla-caja"><table class="tabla" style="max-width:36rem"><thead><tr><th scope="col">Concepto</th><th scope="col" class="der">Importe</th></tr></thead><tbody><?= $linea($r[$k]) ?></tbody></table></div>
</section>
<?php endforeach; ?>
<p class="fuente-pie">Fuente: Dietas y gastos de representación de legisladores/as, Cámara de Diputados de la Nación (datos.hcdn.gob.ar). Montos en pesos.</p>
