<?php
$base = SITE_URL;
?>
<p class="antetitulo">Datos abiertos</p>
<h1 class="titulo">Datos abiertos, API y uso por inteligencia artificial.</h1>
<p class="bajada">Todo lo que publica DipuData sale de fuentes oficiales y se puede reutilizar con la licencia Creative Commons Atribución 4.0. Acá están las formas de hacerlo: una API de solo lectura, perfiles en Markdown y un archivo <code>llms.txt</code> pensado para asistentes de IA.<?= !empty($inf['generado']) ? ' Datos generados el ' . e(fecha_es((string)$inf['generado'])) . '.' : '' ?></p>

<section class="seccion" aria-labelledby="d-licencia">
  <h2 id="d-licencia" class="seccion-titulo">Licencia y cómo citar</h2>
  <ul>
    <li>Los textos y la compilación propios están bajo <a href="https://creativecommons.org/licenses/by/4.0/deed.es" rel="license noopener">Creative Commons Atribución 4.0</a>: podés copiar, adaptar y usar con fines comerciales citando la fuente.</li>
    <li>Cómo citar: <strong>DipuData (dipudata.com)</strong>, con el enlace a la página de origen y la fecha de consulta. Los datos originales pertenecen a las fuentes oficiales que cada pantalla cita (Cámara de Diputados, Senado, Oficina Anticorrupción, INDEC, BCRA, DINE, entre otras).</li>
    <li>No es un sitio oficial. Si una respuesta de IA usa estos datos, conviene que muestre la fuente y la fecha, y que no afirme nada que el dato no diga.</li>
  </ul>
</section>

<section class="seccion" aria-labelledby="d-api">
  <h2 id="d-api" class="seccion-titulo">API pública (solo lectura)</h2>
  <p>Sin clave ni registro. Responde JSON con CORS abierto. Especificación OpenAPI: <a href="/api/openapi.json">/api/openapi.json</a>.</p>
  <div class="tabla-scroll"><table class="tabla">
    <thead><tr><th>Ruta</th><th>Qué devuelve</th></tr></thead>
    <tbody>
      <tr><td><a href="/api/legisladores?limite=5"><code>/api/legisladores</code></a></td><td>Legisladores en ejercicio. Filtros: <code>camara</code>, <code>provincia</code>, <code>bloque</code>, <code>q</code>, <code>pagina</code>, <code>limite</code> (máx. 100).</td></tr>
      <tr><td><code>/api/legisladores/{diputado|senador}/{slug}</code></td><td>Un legislador con sus fuentes.</td></tr>
      <tr><td><a href="/api/bloques"><code>/api/bloques</code></a></td><td>Bloques y cantidad de bancas.</td></tr>
      <tr><td><a href="/api/provincias"><code>/api/provincias</code></a></td><td>Provincias.</td></tr>
      <tr><td><a href="/api/votaciones?limite=5"><code>/api/votaciones</code></a></td><td>Cabeceras de las votaciones nominales. Filtros: <code>camara</code>, <code>anio</code>, <code>pagina</code>, <code>limite</code>.</td></tr>
      <tr><td><code>/api/votaciones/{id}</code></td><td>Una votación con el voto de cada legislador.</td></tr>
    </tbody>
  </table></div>
  <p>Ejemplo:</p>
  <pre><code>curl "<?= e($base) ?>/api/legisladores?camara=Senadores&amp;provincia=cordoba"</code></pre>
</section>

<section class="seccion" aria-labelledby="d-md">
  <h2 id="d-md" class="seccion-titulo">Perfiles en Markdown y llms.txt</h2>
  <ul>
    <li>Cada perfil de legislador tiene una versión en Markdown limpio agregando <code>?formato=md</code> a su dirección (por ejemplo <code><?= e($base) ?>/diputado/{slug}?formato=md</code>). Esa versión no se indexa en buscadores: apunta a la página canónica.</li>
    <li><a href="/llms.txt"><code>/llms.txt</code></a>: índice breve del sitio para asistentes de IA. <a href="/llms-full.txt"><code>/llms-full.txt</code></a>: versión extendida con el listado de legisladores.</li>
    <li><a href="/robots.txt"><code>/robots.txt</code></a> permite expresamente a los rastreadores de IA conocidos y publica el <a href="/sitemap.xml">sitemap</a>.</li>
  </ul>
</section>

<section class="seccion" aria-labelledby="d-limites">
  <h2 id="d-limites" class="seccion-titulo">Qué no hay en la API</h2>
  <p>No se publican CUIT, datos de familiares ni de personal de los legisladores, ni descripción de bienes de las declaraciones juradas. Más detalles en <a href="/metodologia">Metodología y fuentes</a>. Si encontrás un error o querés pedir una corrección, escribí desde <a href="/correccion">Corrección o baja</a>.</p>
</section>
