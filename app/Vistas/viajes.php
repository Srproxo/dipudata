<?php use App\Datos;
/** @var list<array> $filas @var string $q @var string $provSel @var list<string> $provincias */
$v = Datos::viajes();
?>
<p class="antetitulo">Cámara de Diputados</p>
<h1 class="titulo">Pasajes y misiones oficiales.</h1>
<p class="bajada">La Cámara emite pasajes aéreos y terrestres a pedido de los diputados, y publica cada tramo. Acá están los emitidos desde el 10 de diciembre de 2025, y las misiones oficiales al exterior.</p>
<p class="aviso"><strong>Cómo leerlo.</strong> La propia Cámara aclara que los tramos "no implican viajes efectivamente realizados" por los diputados, pero sí que se emitieron y que la Cámara afrontó su costo. Un diputado de una provincia lejana necesita más pasajes que uno de la Capital para ir y volver al recinto, por eso la lista está en orden alfabético y no se ordena por cantidad. El costo de cada pasaje no se publica.</p>

<form class="filtros" method="get" action="/viajes" style="margin-top:1.5rem">
  <label>Buscar diputado<input type="search" name="q" value="<?= e($q) ?>" maxlength="80" placeholder="Apellido o nombre" autocomplete="off"></label>
  <label>Provincia
    <select name="provincia"><option value="">Todas</option>
      <?php foreach ($provincias as $p): ?><option value="<?= e($p['slug']) ?>"<?= $provSel === $p['slug'] ? ' selected' : '' ?>><?= e($p['nombre']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <button class="boton" type="submit">Filtrar</button>
  <?php if ($q !== '' || $provSel): ?><a class="enlace-limpiar" href="/viajes">Quitar filtros</a><?php endif; ?>
</form>
<p class="conteo" aria-live="polite"><?= count($filas) ?> <?= count($filas) === 1 ? 'diputado' : 'diputados' ?></p>
<div class="tabla-caja"><table class="tabla">
  <thead><tr><th scope="col">Diputado</th><th scope="col">Provincia</th><th scope="col" class="der">Aéreos</th><th scope="col" class="der">Terrestres</th><th scope="col" class="der">Total de tramos</th></tr></thead>
  <tbody>
  <?php foreach ($filas as [$l, $a]): ?>
    <tr><td><a href="<?= e(Datos::urlLegislador($l)) ?>"><?= e($l['nombre']) ?></a></td><td class="tenue" data-etiqueta="Provincia"><?= e($l['provincia']) ?></td>
      <td class="der num" data-etiqueta="Aéreos"><?= (int)$a['aereo'] ?></td><td class="der num" data-etiqueta="Terrestres"><?= (int)$a['terrestre'] ?></td><td class="der num" data-etiqueta="Total"><b><?= (int)$a['total'] ?></b></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<section class="seccion" aria-labelledby="misiones">
  <h2 id="misiones" class="seccion-titulo">Misiones oficiales</h2>
  <p class="tenue">Viajes al exterior en los que participan diputados invitados por instituciones u otros organismos, con los viáticos que otorga la Cámara. Tal como los publica la Cámara.</p>
  <div class="tabla-caja"><table class="tabla tabla-docs">
    <thead><tr><th scope="col">Fechas</th><th scope="col">Participa</th><th scope="col">Motivo y lugar</th></tr></thead>
    <tbody>
    <?php foreach (array_slice(Datos::misiones(), 0, 40) as $m): ?>
      <tr><td class="num nowrap" data-etiqueta="Fechas"><?= e(date('d/m/Y', strtotime($m['inicio']))) ?><?= $m['fin'] && $m['fin'] !== $m['inicio'] ? ' al ' . e(date('d/m/Y', strtotime($m['fin']))) : '' ?></td>
        <td data-etiqueta="Participa"><?= $m['slug'] ? '<a href="/diputado/' . e($m['slug']) . '">' . e($m['participa']) . '</a>' : e($m['participa']) ?></td>
        <td><?= e($m['motivo']) ?><span class="tenue pequeno block"><?= e($m['lugar']) ?><?= $m['invita'] ? ' · invita: ' . e($m['invita']) : '' ?></span></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<p class="fuente-pie">Fuentes: Viajes nacionales y Misiones oficiales, Cámara de Diputados de la Nación (datos.hcdn.gob.ar). Pasajes desde el 10 de diciembre de 2025 hasta fines de junio de 2026.</p>
