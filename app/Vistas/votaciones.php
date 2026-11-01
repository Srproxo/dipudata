<?php /** @var list<array> $lista @var list<string> $anios @var string $anio @var string $res @var string $cam @var string $q @var int $total @var int $pagina @var int $paginas */ ?>
<p class="antetitulo">Congreso de la Nación</p>
<h1 class="titulo">Votaciones nominales.</h1>
<p class="bajada">Cada vez que una cámara vota con registro nominal, queda anotado cómo votó cada legislador. Acá están las de Diputados y las del Senado, para buscar por tema y abrir una por una.</p>
<p class="aviso"><strong>Qué períodos hay.</strong> Diputados: de marzo de 2011 a enero de 2020 (datos abiertos de la Cámara) y desde el 10 de diciembre de 2025. Senado: desde el 10 de diciembre de 2025. Entre 2020 y 2025 la Cámara no publica sus votaciones como datos abiertos, y esas no están acá.</p>

<form class="filtros" method="get" action="/votaciones" style="margin-top:1.5rem">
  <label>Buscar en el título
    <input type="search" name="q" value="<?= e($q) ?>" maxlength="80" placeholder="Tema o número de expediente" autocomplete="off">
  </label>
  <label>Cámara
    <select name="camara"><option value="">Ambas</option>
      <option value="D"<?= $cam === 'D' ? ' selected' : '' ?>>Diputados</option>
      <option value="S"<?= $cam === 'S' ? ' selected' : '' ?>>Senado</option>
    </select>
  </label>
  <label>Año
    <select name="anio"><option value="">Todos</option>
      <?php foreach ($anios as $a): ?><option value="<?= e($a) ?>"<?= $anio === $a ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Resultado
    <select name="resultado"><option value="">Todos</option>
      <option value="AFIRMATIVO"<?= $res === 'AFIRMATIVO' ? ' selected' : '' ?>>Afirmativo</option>
      <option value="NEGATIVO"<?= $res === 'NEGATIVO' ? ' selected' : '' ?>>Negativo</option>
    </select>
  </label>
  <button class="boton" type="submit">Filtrar</button>
  <?php if ($q !== '' || $anio || $res || $cam): ?><a class="enlace-limpiar" href="/votaciones">Quitar filtros</a><?php endif; ?>
</form>

<p class="conteo" aria-live="polite"><?= number_format($total, 0, ',', '.') ?> <?= $total === 1 ? 'votación' : 'votaciones' ?></p>
<?php if (!$lista): ?>
  <p class="vacio-txt">No hay votaciones con esos filtros.</p>
<?php else: ?>
<div class="tabla-caja"><table class="tabla tabla-docs">
  <thead><tr><th scope="col">Fecha</th><th scope="col">Cámara</th><th scope="col">Votación</th><th scope="col">Resultado</th><th scope="col" class="der">A favor</th><th scope="col" class="der">En contra</th><th scope="col" class="der">Abst.</th><th scope="col" class="der">Aus.</th></tr></thead>
  <tbody>
  <?php foreach ($lista as $v): ?>
    <tr>
      <td class="num nowrap" data-etiqueta="Fecha"><?= e(date('d/m/Y', strtotime($v['f']))) ?></td>
      <td data-etiqueta="Cámara"><?= ($v['c'] ?? 'D') === 'S' ? 'Senado' : 'Diputados' ?></td>
      <td><a href="/votaciones/<?= e($v['id']) ?>"><?= e(mb_strlen($v['t']) > 170 ? mb_substr($v['t'], 0, 170) . '…' : $v['t']) ?></a>
          <span class="tenue pequeno block"><?= !empty($v['p']) ? 'Período ' . (int)$v['p'] . ' · ' : '' ?><?= !empty($v['s']) ? 'sesión ' . e(strtolower($v['s'])) . ' · ' : '' ?><?= e($v['m']) ?></span></td>
      <td data-etiqueta="Resultado"><b class="res-<?= $v['res'] === 'AFIRMATIVO' ? 'si' : 'no' ?>"><?= $v['res'] === 'AFIRMATIVO' ? 'Afirmativo' : 'Negativo' ?></b></td>
      <td class="der num" data-etiqueta="A favor"><?= (int)$v['a'] ?></td>
      <td class="der num" data-etiqueta="En contra"><?= (int)$v['n'] ?></td>
      <td class="der num" data-etiqueta="Abstenciones"><?= (int)$v['b'] ?></td>
      <td class="der num" data-etiqueta="Ausentes"><?= (int)$v['u'] ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($paginas > 1): ?>
<nav class="paginas" aria-label="Paginación">
  <?php if ($pagina > 1): ?><a href="<?= e(url_con(['pagina' => $pagina - 1])) ?>" rel="prev">← Anterior</a><?php endif; ?>
  <span class="num">Página <?= $pagina ?> de <?= $paginas ?></span>
  <?php if ($pagina < $paginas): ?><a href="<?= e(url_con(['pagina' => $pagina + 1])) ?>" rel="next">Siguiente →</a><?php endif; ?>
</nav>
<?php endif; endif; ?>
<p class="fuente-pie">Fuentes: votaciones nominales de la Cámara de Diputados (datos.hcdn.gob.ar) hasta 2020; desde diciembre de 2025, las de Diputados a través de ArgentinaDatos y las del Senado directamente de senado.gob.ar. Cada votación se verifica voto por voto contra sus totales.</p>
