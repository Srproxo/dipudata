<?php
declare(strict_types=1);

const RAIZ = __DIR__ . '/..';
const SITE_URL = 'https://dipudata.com';
/** Correo para pedidos de corrección o baja. Vacío = no se muestra y queda solo el pedido público en GitHub. */
const CONTACTO_EMAIL = 'correcciones@dipudata.com';
const PEDIDOS_URL = 'https://github.com/Srproxo/dipudata/issues/new';
/** Fecha de la última revisión de los textos legales (AAAA-MM-DD). */
const LEGAL_ACTUALIZADO = '2026-11-05';
/** Quién edita el sitio (Ley 25.326, art. 6: identidad y domicilio del responsable). Vacío = se dice solo "el equipo editor de DipuData". */
const TITULAR_NOMBRE = '';
const TITULAR_DOMICILIO = '';
/** ID de medición de Google Analytics 4 (empieza con G-): vacío = sin analítica y sin cookies. Solo se carga después de que la persona la acepte. */
define('GA_ID', preg_match('/^G-[A-Z0-9]{6,14}$/', (string)getenv('DIPUDATA_GA_ID')) ? (string)getenv('DIPUDATA_GA_ID') : '');
/** Código de verificación de Google Search Console (contenido de <meta name="google-site-verification">). Vacío = no se emite. */
define('GSC_VERIFICACION', preg_match('/^[A-Za-z0-9_-]{20,80}$/', (string)getenv('DIPUDATA_GSC')) ? (string)getenv('DIPUDATA_GSC') : '');

require_once __DIR__ . '/Datos.php';
require_once __DIR__ . '/Web.php';
require_once __DIR__ . '/Contenido.php';
