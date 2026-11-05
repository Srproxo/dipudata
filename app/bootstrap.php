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
/**
 * Configuración que no va al repositorio: un archivo `config.local.php` en la raíz del sitio (git lo ignora; se sube al servidor aparte)
 * que devuelve ['GA_ID' => '...', 'GSC_VERIFICACION' => '...']. También se aceptan las variables de entorno DIPUDATA_GA_ID y DIPUDATA_GSC
 * (en el hosting compartido de Hostinger las variables de entorno no llegan a PHP, por eso el archivo).
 */
$cfgLocal = is_file(__DIR__ . '/../config.local.php') ? (array)(require __DIR__ . '/../config.local.php') : [];
/** ID de medición de Google Analytics 4 (empieza con G-): vacío = sin analítica y sin cookies. Solo se carga después de que la persona la acepte. */
$gaId = (string)($cfgLocal['GA_ID'] ?? (getenv('DIPUDATA_GA_ID') ?: ''));
define('GA_ID', preg_match('/^G-[A-Z0-9]{6,14}$/', $gaId) ? $gaId : '');
/** Código de verificación de Google Search Console (contenido de <meta name="google-site-verification">). Vacío = no se emite. */
$gscToken = (string)($cfgLocal['GSC_VERIFICACION'] ?? (getenv('DIPUDATA_GSC') ?: ''));
define('GSC_VERIFICACION', preg_match('/^[A-Za-z0-9_-]{20,80}$/', $gscToken) ? $gscToken : '');

require_once __DIR__ . '/Datos.php';
require_once __DIR__ . '/Web.php';
require_once __DIR__ . '/Contenido.php';
