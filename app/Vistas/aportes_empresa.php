<?php
/** @var array $emp persona jurídica aportante (aportes.json → empresas) */
$limpiar = static fn(string $n): string => (string)preg_replace(['/^\(\d+\)\s*/', '/^\d{4} - [^-]+ - /'], '', $n);
$destino = static function (string $n): string {
    $n = mb_convert_case(mb_strtolower($n, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    return strtr($n, ['(P.a.s.o.)' => 'PASO:', '(Generales)' => 'Generales:', '(Segunda Vuelta)' => 'Segunda vuelta:', ' De ' => ' de ', ' Del ' => ' del ']);
};
?>
<nav class="migas" aria-label="Ubicación"><a href="/aportes">Aportes a partidos</a> <span aria-hidden="true">/</span> <a href="/aportes/empresas">Empresas</a> <span aria-hidden="true">/</span> <span><?= e($emp['nombre']) ?></span></nav>
<p class="antetitulo">Persona jurídica aportante</p>
<h1 class="titulo"><?= e($emp['nombre']) ?></h1>
<?php if ($emp['actividad']): ?><p class="bajada">Actividad principal declarada ante ARCA: <?= e($emp['actividad']) ?>.</p><?php endif; ?>

<dl class="participacion" style="margin-top:1.5rem">
  <div><dt>Total aportado</dt><dd><?= e(monto_corto($emp['total'])) ?></dd></div>
  <div><dt>Aportes</dt><dd><?= (int)$emp['aportes'] ?></dd></div>
  <div><dt>Agrupaciones</dt><dd><?= (int)$emp['agrupaciones'] ?></dd></div>
</dl>

<section class="seccion" aria-labelledby="em-a">
  <h2 id="em-a" class="seccion-titulo">A quién aportó</h2>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Agrupación</th><th scope="col">Distrito</th><th scope="col">Destino</th><th scope="col" class="der">Monto</th><th scope="col" class="der">Último aporte</th></tr></thead>
    <tbody>
    <?php foreach ($emp['detalle'] as [$ag, $dis, $dest, $monto, $n, $fecha]): ?>
      <tr>
        <td><span><?= e($limpiar($ag)) ?><?= $n > 1 ? ' <span class="tenue pequeno">(' . (int)$n . ' aportes)</span>' : '' ?></span></td>
        <td class="tenue" data-etiqueta="Distrito"><?= e($dis) ?></td>
        <td data-etiqueta="Destino"><span><?= e($destino($dest)) ?></span></td>
        <td class="der num" data-etiqueta="Monto"><b style="white-space:nowrap">$ <?= number_format($monto, 0, ',', '.') ?></b></td>
        <td class="der num" data-etiqueta="Último aporte"><?= e(fecha_es($fecha)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="tenue pequeno">Montos en pesos de cada fecha, como se declararon. Un aporte dentro de los topes de la Ley 26.215 es legal y no indica que la empresa haya recibido algo a cambio.</p>
</section>
<p class="fuente-pie">Fuentes: Cámara Nacional Electoral, registro de aportantes (aportantes.electoral.gob.ar); actividad principal: Registro Nacional de Sociedades, Ministerio de Justicia (datos.jus.gob.ar). <a href="/metodologia#aportes">Metodología</a>.</p>
