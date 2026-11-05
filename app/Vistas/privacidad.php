<?php
$titular = TITULAR_NOMBRE !== '' ? TITULAR_NOMBRE : 'el equipo editor de DipuData';
?>
<p class="antetitulo">Información legal</p>
<h1 class="titulo">Política de privacidad.</h1>
<p class="bajada">Última actualización: <?= e(fecha_es(LEGAL_ACTUALIZADO)) ?>. Acá explicamos qué datos personales trata DipuData, con qué fundamento, con quién los comparte y cómo podés ejercer tus derechos, de acuerdo con la Ley 25.326 de Protección de los Datos Personales.</p>

<section class="seccion" aria-labelledby="p-resp">
  <h2 id="p-resp" class="seccion-titulo">1. Responsable</h2>
  <p>El responsable del tratamiento es <?= e($titular) ?><?= TITULAR_DOMICILIO !== '' ? ', con domicilio en ' . e(TITULAR_DOMICILIO) : '' ?>. Contacto para todo lo relativo a datos personales: <a href="mailto:<?= e(CONTACTO_EMAIL) ?>"><?= e(CONTACTO_EMAIL) ?></a>.</p>
</section>

<section class="seccion" aria-labelledby="p-datos">
  <h2 id="p-datos" class="seccion-titulo">2. Qué datos tratamos y para qué</h2>
  <ul>
    <li><strong>Datos públicos de legisladores y funcionarios.</strong> DipuData publica información sobre diputados y senadores nacionales (nombre, cargo, bloque, provincia, votaciones, proyectos, declaraciones juradas y otros datos de origen oficial). Es información de personas que ejercen funciones públicas, obtenida de fuentes públicas oficiales y publicada con su fuente por su interés público. Se aplica el artículo 5, inciso 2, de la Ley 25.326 (datos obtenidos de fuentes de acceso público irrestricto o recabados para el ejercicio de funciones de los poderes del Estado).</li>
    <li><strong>Datos que no se publican.</strong> No se publican CUIT, números de documento, domicilios, teléfonos ni correos particulares, ni datos de familiares que no sean legisladores, ni descripciones de bienes. Tampoco se afirma nada sobre el personal de un legislador.</li>
    <li><strong>Datos técnicos de quienes visitan el sitio.</strong> Al pedir una página, el servidor registra la dirección IP, la fecha y hora, la dirección pedida y el tipo de navegador. Se usan para que el sitio funcione, para seguridad y para detectar abusos.</li>
    <li><strong>Mensajes y pedidos.</strong> Si escribís a <?= e(CONTACTO_EMAIL) ?> o abrís un pedido de corrección o baja, tratamos tu dirección de correo y lo que cuentes en el mensaje, solo para responderte y atender el pedido.</li>
    <?php if (GA_ID !== ''): ?>
    <li><strong>Estadísticas de uso (opcional).</strong> Si aceptás las cookies de análisis, Google Analytics mide de forma agregada cómo se usa el sitio (páginas vistas, origen de la visita, tipo de dispositivo). Sin tu aceptación no se carga. Más en la <a href="/cookies">Política de cookies</a>.</li>
    <?php else: ?>
    <li><strong>Estadísticas y cookies.</strong> El sitio no usa cookies ni herramientas de análisis o publicidad de terceros. Más en la <a href="/cookies">Política de cookies</a>.</li>
    <?php endif; ?>
  </ul>
  <p>No pedimos datos sensibles a quienes visitan el sitio ni hay cuentas de usuario, formularios de registro ni newsletters.</p>
</section>

<section class="seccion" aria-labelledby="p-terceros">
  <h2 id="p-terceros" class="seccion-titulo">3. Con quién se comparten</h2>
  <ul>
    <li><strong>Hostinger</strong>: aloja el sitio y el correo <?= e(CONTACTO_EMAIL) ?>, y por eso procesa los datos técnicos y los mensajes.</li>
    <li><strong>GitHub</strong>: aloja el código del sitio. Si abrís un pedido desde allí, <strong>es público</strong> y queda asociado a tu usuario de GitHub; por eso, para pedidos que incluyan datos personales conviene usar el correo.</li>
    <?php if (GA_ID !== ''): ?><li><strong>Google</strong>: solo si aceptás las cookies de análisis, recibe los datos de uso descriptos en la Política de cookies.</li><?php endif; ?>
    <li>No vendemos datos ni los cedemos para publicidad. Podemos comunicarlos cuando una autoridad competente lo exija conforme a la ley.</li>
  </ul>
  <p>Algunos de estos proveedores pueden procesar datos fuera de la Argentina. En esos casos solo se les envían los datos técnicos y los mensajes descriptos arriba, y se usan para prestar el servicio.</p>
</section>

<section class="seccion" aria-labelledby="p-conserv">
  <h2 id="p-conserv" class="seccion-titulo">4. Cuánto tiempo se conservan</h2>
  <ul>
    <li>Los registros técnicos se conservan por el plazo que establece el proveedor de alojamiento y se usan solo para seguridad y funcionamiento.</li>
    <li>Los mensajes y pedidos se conservan mientras sean necesarios para atenderlos y para dejar constancia de lo resuelto.</li>
    <li>Los datos de origen oficial se publican mientras la fuente los mantenga publicados o hasta que se atienda un pedido de baja que corresponda.</li>
  </ul>
</section>

<section class="seccion" aria-labelledby="p-derechos">
  <h2 id="p-derechos" class="seccion-titulo">5. Tus derechos</h2>
  <p>Como titular de datos personales podés pedir, de forma gratuita:</p>
  <ul>
    <li><strong>Acceso</strong>: saber qué datos tuyos tratamos (artículo 14 de la Ley 25.326). Respondemos dentro de los diez días corridos de recibido el pedido.</li>
    <li><strong>Rectificación y actualización</strong>: corregir datos inexactos o incompletos (artículo 16).</li>
    <li><strong>Supresión</strong>: que se quite la información (artículo 16).</li>
  </ul>
  <p>Rectificamos, actualizamos o suprimimos dentro de los cinco días hábiles de recibido el reclamo. <strong>Si quien lo pide tiene derecho a hacerlo, la información se quita.</strong></p>
  <p>Para ejercer estos derechos escribí a <a href="mailto:<?= e(CONTACTO_EMAIL) ?>"><?= e(CONTACTO_EMAIL) ?></a> o usá la página <a href="/correccion">Pedir una corrección o la baja de un dato</a>. Indicá qué dato es y en qué página está. Para evitar que otra persona pida cambios sobre tus datos, podemos pedirte que acredites quién sos, sin necesidad de enviar documentos más allá de lo indispensable.</p>
</section>

<section class="seccion" aria-labelledby="p-seg">
  <h2 id="p-seg" class="seccion-titulo">6. Seguridad</h2>
  <p>El sitio se sirve solo por conexión cifrada (HTTPS), no tiene bases de datos con información de visitantes ni cuentas de usuario, y limita los recursos que el navegador puede cargar. Ningún sistema es infalible; si detectás un problema de seguridad, avisanos por correo.</p>
</section>

<section class="seccion" aria-labelledby="p-menores">
  <h2 id="p-menores" class="seccion-titulo">7. Menores de edad</h2>
  <p>El sitio no está dirigido a menores de 13 años ni les pide datos. Si pensás que un menor nos envió datos personales, escribinos y los eliminamos.</p>
</section>

<section class="seccion" aria-labelledby="p-cambios">
  <h2 id="p-cambios" class="seccion-titulo">8. Cambios en esta política</h2>
  <p>Podemos actualizar esta política. La fecha de la última actualización figura al principio de la página.</p>
</section>

<section class="seccion" aria-labelledby="p-aaip">
  <h2 id="p-aaip" class="seccion-titulo">9. Autoridad de control</h2>
  <p>El titular de los datos personales tiene la facultad de ejercer el derecho de acceso a los mismos en forma gratuita a intervalos no inferiores a seis meses, salvo que se acredite un interés legítimo al efecto conforme lo establecido en el artículo 14, inciso 3 de la Ley Nº 25.326. La AGENCIA DE ACCESO A LA INFORMACIÓN PÚBLICA, en su carácter de Órgano de Control de la Ley Nº 25.326, tiene la atribución de atender las denuncias y reclamos que interpongan quienes resulten afectados en sus derechos por incumplimiento de las normas vigentes en materia de protección de datos personales.</p>
  <p>Agencia de Acceso a la Información Pública: Av. Pte. Gral. Julio A. Roca 710, piso 2, Ciudad Autónoma de Buenos Aires (C1067ABP). Teléfono (54-11) 3988-3968. Correo: <a href="mailto:datospersonales@aaip.gob.ar">datospersonales@aaip.gob.ar</a>. Más información: <a href="https://www.argentina.gob.ar/aaip/datospersonales" rel="noopener">argentina.gob.ar/aaip/datospersonales</a>.</p>
</section>
