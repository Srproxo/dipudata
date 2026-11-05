<?php
$titular = TITULAR_NOMBRE !== '' ? TITULAR_NOMBRE : 'el equipo editor de DipuData';
?>
<p class="antetitulo">Información legal</p>
<h1 class="titulo">Términos y condiciones de uso.</h1>
<p class="bajada">Última actualización: <?= e(fecha_es(LEGAL_ACTUALIZADO)) ?>. Al usar dipudata.com aceptás estos términos. Si no estás de acuerdo, no uses el sitio.</p>

<section class="seccion" aria-labelledby="t-quienes">
  <h2 id="t-quienes" class="seccion-titulo">1. Quién edita el sitio</h2>
  <p>DipuData (dipudata.com) es un sitio de datos abiertos sobre el Congreso de la Nación Argentina, editado por <?= e($titular) ?><?= TITULAR_DOMICILIO !== '' ? ', con domicilio en ' . e(TITULAR_DOMICILIO) : '' ?>. Para cualquier consulta, corrección o pedido sobre estos términos podés escribir a <a href="mailto:<?= e(CONTACTO_EMAIL) ?>"><?= e(CONTACTO_EMAIL) ?></a>.</p>
</section>

<section class="seccion" aria-labelledby="t-natur">
  <h2 id="t-natur" class="seccion-titulo">2. Qué es y qué no es DipuData</h2>
  <ul>
    <li>Es un sitio informativo y gratuito, hecho para leer y entender información pública sobre diputados y senadores nacionales.</li>
    <li><strong>No es un sitio oficial.</strong> No pertenece a la Honorable Cámara de Diputados de la Nación, al Honorable Senado de la Nación, a la Oficina Anticorrupción ni a ningún otro organismo del Estado, ni tiene relación con ellos.</li>
    <li>No es asesoramiento legal, financiero, político ni de ningún otro tipo. No debe usarse como única base para tomar decisiones.</li>
  </ul>
</section>

<section class="seccion" aria-labelledby="t-fuentes">
  <h2 id="t-fuentes" class="seccion-titulo">3. Fuentes y exactitud de los datos</h2>
  <ul>
    <li>Solo se publica información que tiene una fuente oficial y que se pudo verificar. Cada dato indica de dónde sale; la metodología completa está en <a href="/metodologia">Metodología y fuentes</a>.</li>
    <li>Los datos se publican tal como los entrega la fuente. Las fuentes pueden tener errores, omisiones o demoras, y DipuData no las corrige por su cuenta: cuando una fuente trae valores inconsistentes, no los publica y lo explica.</li>
    <li>Que un dato no figure no quiere decir que no exista: solo se muestra lo que publicó la fuente oficial.</li>
    <li>Las cifras expresadas en pesos de hoy o en dólares son una conversión con índices y cotizaciones que se citan en cada pantalla; son una ayuda para comparar y no reemplazan el dato declarado.</li>
    <li>El sitio no afirma intenciones, causas ni responsabilidades de ninguna persona. Una cifra, un voto o un vínculo declarado no implican por sí mismos ninguna conducta.</li>
  </ul>
</section>

<section class="seccion" aria-labelledby="t-uso">
  <h2 id="t-uso" class="seccion-titulo">4. Uso permitido</h2>
  <p>Podés leer, citar y compartir el contenido. No podés usar el sitio para actividades ilícitas, intentar vulnerar su seguridad, sobrecargarlo con pedidos automáticos que afecten su funcionamiento, ni usar los datos de personas para acosarlas, hacerles publicidad no solicitada o cualquier fin contrario a la ley.</p>
</section>

<section class="seccion" aria-labelledby="t-pi">
  <h2 id="t-pi" class="seccion-titulo">5. Propiedad intelectual y reutilización</h2>
  <ul>
    <li><strong>Datos de origen oficial.</strong> Conservan las condiciones de su fuente. Por ejemplo, las declaraciones juradas de la Oficina Anticorrupción se publican en datos.jus.gob.ar con licencia Creative Commons Atribución 4.0. Cada pantalla indica la fuente.</li>
    <li><strong>Textos y compilación propios.</strong> Los textos, los cálculos y la forma en que DipuData organiza los datos se pueden reutilizar, también con fines comerciales, bajo la licencia <a href="https://creativecommons.org/licenses/by/4.0/deed.es" rel="noopener">Creative Commons Atribución 4.0 Internacional</a>, citando "DipuData (dipudata.com)" y enlazando a la página de origen.</li>
    <li>El nombre y el logotipo de DipuData no pueden usarse de una forma que sugiera una relación oficial o un respaldo que no existe.</li>
    <li>Las fotografías de los legisladores provienen de los sitios oficiales de cada Cámara.</li>
  </ul>
</section>

<section class="seccion" aria-labelledby="t-corr">
  <h2 id="t-corr" class="seccion-titulo">6. Correcciones y pedidos de baja</h2>
  <p>Si un dato está mal o querés pedir que se retire, podés hacerlo desde <a href="/correccion">Pedir una corrección o la baja de un dato</a>. Cómo se tratan los datos personales y cómo ejercer tus derechos está en la <a href="/privacidad">Política de privacidad</a>.</p>
</section>

<section class="seccion" aria-labelledby="t-enlaces">
  <h2 id="t-enlaces" class="seccion-titulo">7. Enlaces a otros sitios</h2>
  <p>DipuData enlaza a sitios oficiales y de terceros para que puedas ver la fuente. No controla su contenido ni su disponibilidad y no responde por ellos.</p>
</section>

<section class="seccion" aria-labelledby="t-resp">
  <h2 id="t-resp" class="seccion-titulo">8. Limitación de responsabilidad</h2>
  <p>El sitio se ofrece "tal cual está", sin garantías de disponibilidad continua ni de que esté libre de errores. En la medida que lo permita la ley, quien edita el sitio no responde por daños derivados del uso del contenido ni de la imposibilidad de usarlo. Esto no limita derechos que la ley no permite limitar.</p>
</section>

<section class="seccion" aria-labelledby="t-cambios">
  <h2 id="t-cambios" class="seccion-titulo">9. Cambios en el sitio y en estos términos</h2>
  <p>El contenido y estos términos pueden cambiar. La fecha de la última actualización figura al principio de la página. Si el cambio es importante, se avisará en el sitio.</p>
</section>

<section class="seccion" aria-labelledby="t-ley">
  <h2 id="t-ley" class="seccion-titulo">10. Ley aplicable y jurisdicción</h2>
  <p>Estos términos se rigen por las leyes de la República Argentina. Para cualquier controversia son competentes los tribunales del domicilio de quien edita el sitio, salvo que una norma imperativa disponga otra cosa.</p>
</section>
