<?php /** @var string $desde página desde la que se llegó (ruta del sitio, ya validada) */
$asunto = 'Corrección o baja' . ($desde !== '' ? ': ' . $desde : '');
$cuerpo = "Página: " . ($desde !== '' ? SITE_URL . $desde : '(completar)') . "\n\nQué dato está mal o querés que se retire:\n\n\nPor qué (y la fuente oficial que lo muestra, si la tenés):\n\n\nPedido: corrección / baja\n";
$urlPedido = PEDIDOS_URL . '?title=' . rawurlencode($asunto) . '&body=' . rawurlencode($cuerpo);
?>
<p class="antetitulo">Si algo está mal</p>
<h1 class="titulo">Pedir una corrección o la baja de un dato.</h1>
<p class="bajada">Todo lo que publica DipuData sale de una fuente oficial y cada dato muestra cuál es. Si algo está mal, o si querés pedir que un dato se retire, podés avisarnos acá.</p>

<section class="seccion" aria-labelledby="que">
  <h2 id="que" class="seccion-titulo">Qué podés pedir</h2>
  <ul>
    <li><strong>Una corrección:</strong> el dato no coincide con la fuente oficial, está atribuido a la persona equivocada (por ejemplo, por un homónimo) o quedó desactualizado.</li>
    <li><strong>La baja:</strong> que un dato se retire. Cada pedido se revisa de a uno, con la fuente a la vista.</li>
  </ul>
</section>

<section class="seccion" aria-labelledby="que-contar">
  <h2 id="que-contar" class="seccion-titulo">Qué conviene contarnos</h2>
  <ul>
    <li>En qué página está el dato<?php if ($desde !== ''): ?> (viniste desde <code><?= e($desde) ?></code>)<?php endif; ?>.</li>
    <li>Qué dato es y por qué creés que está mal o por qué pedís que se retire.</li>
    <li>La fuente oficial que lo muestra, si la tenés.</li>
    <li>No hace falta que mandes documentos personales ni CUIT: no los necesitamos para revisar el pedido.</li>
  </ul>
</section>

<section class="seccion" aria-labelledby="como">
  <h2 id="como" class="seccion-titulo">Cómo pedirlo</h2>
  <?php if (CONTACTO_EMAIL !== ''): ?>
  <p><strong>Por correo:</strong> <a href="mailto:<?= e(CONTACTO_EMAIL) ?>?subject=<?= e(rawurlencode($asunto)) ?>"><?= e(CONTACTO_EMAIL) ?></a>. Es la forma recomendada si no querés que el pedido sea público.</p>
  <?php endif; ?>
  <p><strong>Por GitHub:</strong> <a href="<?= e($urlPedido) ?>" rel="noopener">abrir un pedido ya redactado</a>. Ojo: los pedidos en GitHub son <strong>públicos</strong>, así que no escribas datos personales en ellos.</p>
</section>

<section class="seccion" aria-labelledby="despues">
  <h2 id="despues" class="seccion-titulo">Qué pasa después</h2>
  <ul>
    <li>Revisamos el pedido contra la fuente oficial.</li>
    <li>Si el dato es incorrecto, lo corregimos o lo retiramos y lo anotamos en <a href="/metodologia">la metodología</a>.</li>
    <li>Si el dato es correcto y es información pública oficial, queda con su fuente y te explicamos por qué.</li>
    <li>Los pedidos de personas que no son legisladoras y aparecen en algún dato se atienden con prioridad.</li>
  </ul>
</section>
