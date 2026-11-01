<?php use App\Datos; $inf = Datos::informe(); ?>
<p class="antetitulo">Metodología</p>
<h1 class="titulo">Cómo trabajamos.</h1>
<p class="bajada">DipuData publica información sobre el Congreso solo cuando puede decir de dónde sale. Esta página explica qué hay, qué no hay todavía y por qué.</p>
<div class="articulo">
  <h2>Qué publicamos hoy</h2>
  <p>El padrón de legisladores en ejercicio con su cámara, su provincia, su bloque y su mandato; los registros administrativos y parlamentarios del Senado; y una guía sobre cómo funciona el Congreso. Los bloques y las provincias se calculan a partir del padrón: nunca se cargan a mano.</p>
  <h2>De dónde salen los datos</h2>
  <ul>
    <li><strong>Diputados:</strong> padrón oficial de diputados en ejercicio publicado por la Cámara de Diputados en sus datos abiertos (datos.hcdn.gob.ar).</li>
    <li><strong>Senadores:</strong> padrón de senadores en ejercicio que publica el Senado de la Nación como dato abierto: nombre, provincia, bloque, mandato, foto y contacto institucional.</li>
    <li><strong>Registros del Senado:</strong> versiones taquigráficas, asuntos entrados, boletines, decretos, resoluciones, disposiciones, normativa y licitaciones, tal como los publica el Senado. Los textos no se modifican.</li>
    <li><strong>Fotos:</strong> ArgentinaDatos. Si una foto falta, se muestran las iniciales.</li>
    <li><strong>Textos sobre el Congreso:</strong> redactados a partir de la Constitución Nacional.</li>
  </ul>
  <h2>Qué retiramos y por qué</h2>
  <p>Una auditoría interna encontró datos sin respaldo en la versión anterior del sitio: números de CUIT generados al azar, porcentajes de asistencia y de lealtad de bloque con valores de relleno, montos de patrimonio sin fuente y alertas que se atribuían a legisladores reales sin evidencia. Todo eso se retiró. Las secciones de votaciones, rankings, comparaciones y notas se vuelven a publicar solo cuando sus datos estén calculados desde las actas oficiales.</p>
  <h2>Qué viene</h2>
  <ul>
    <li>Asistencia y votaciones calculadas desde las actas nominales de cada cámara.</li>
    <li>Integrantes de las comisiones de ambas cámaras.</li>
    <li>Registros equivalentes de la Cámara de Diputados.</li>
  </ul>
  <h2>Correcciones</h2>
  <p>Si encontrás un dato incorrecto, avisanos con el enlace de la ficha y la fuente que lo desmiente. Corregimos y dejamos constancia.</p>
  <?php if (!empty($inf['generado'])): ?><p class="fuente-pie">Última actualización de datos: <?= e(fecha_es($inf['generado'])) ?>.</p><?php endif; ?>
</div>
