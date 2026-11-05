<p class="antetitulo">Información legal</p>
<h1 class="titulo">Política de cookies.</h1>
<p class="bajada">Última actualización: <?= e(fecha_es(LEGAL_ACTUALIZADO)) ?>. Una cookie es un archivo pequeño que el sitio guarda en tu navegador. Acá te contamos si DipuData usa cookies, para qué y cómo elegir.</p>

<?php if (GA_ID === ''): ?>
<section class="seccion" aria-labelledby="c-ninguna">
  <h2 id="c-ninguna" class="seccion-titulo">DipuData no usa cookies</h2>
  <p>Hoy el sitio no guarda cookies en tu navegador ni usa herramientas de análisis, de publicidad o de seguimiento de terceros. No hay nada que aceptar ni rechazar. Si esto cambia, vamos a actualizar esta página y a pedirte tu consentimiento antes de usar cualquier cookie que no sea estrictamente necesaria.</p>
  <p>Como cualquier sitio, el servidor recibe datos técnicos de cada visita (dirección IP, fecha y hora, página pedida y navegador); está explicado en la <a href="/privacidad">Política de privacidad</a>.</p>
</section>
<?php else: ?>
<section class="seccion" aria-labelledby="c-cuales">
  <h2 id="c-cuales" class="seccion-titulo">Qué cookies usa el sitio</h2>
  <div class="tabla-caja"><table class="tabla">
    <thead><tr><th scope="col">Cookie</th><th scope="col">Quién</th><th scope="col">Para qué</th><th scope="col">Duración</th></tr></thead>
    <tbody>
      <tr><td data-etiqueta="Cookie"><code>dd_cookies</code></td><td data-etiqueta="Quién">DipuData</td><td data-etiqueta="Para qué">Recuerda si aceptaste o rechazaste las cookies de análisis. Es necesaria para respetar tu elección.</td><td data-etiqueta="Duración">180 días</td></tr>
      <tr><td data-etiqueta="Cookie"><code>_ga</code> y <code>_ga_*</code></td><td data-etiqueta="Quién">Google Analytics</td><td data-etiqueta="Para qué">Miden de forma agregada cómo se usa el sitio: páginas vistas, origen de la visita y tipo de dispositivo. Solo se guardan si las aceptás.</td><td data-etiqueta="Duración">Hasta 2 años</td></tr>
    </tbody>
  </table></div>
  <p>Google Analytics es un servicio de Google LLC. Sus datos pueden procesarse fuera de la Argentina. Más información: <a href="https://policies.google.com/technologies/partner-sites" rel="noopener">cómo usa Google los datos de los sitios que usan sus servicios</a>.</p>
</section>

<section class="seccion" aria-labelledby="c-elegir">
  <h2 id="c-elegir" class="seccion-titulo">Tu elección</h2>
  <p>Las cookies de análisis no se cargan hasta que las aceptás. Podés cambiar tu elección cuando quieras:</p>
  <p class="botones-cookies">
    <button type="button" class="boton" data-cookies="si">Aceptar cookies de análisis</button>
    <button type="button" class="boton boton-sec" data-cookies="no">Rechazar</button>
  </p>
  <p class="tenue pequeno" data-cookies-estado aria-live="polite"></p>
  <p class="tenue pequeno">Estos botones necesitan JavaScript. Sin JavaScript no se carga ninguna cookie de análisis. También podés borrar las cookies desde la configuración de tu navegador.</p>
</section>
<?php endif; ?>
