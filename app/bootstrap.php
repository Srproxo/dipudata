<?php
declare(strict_types=1);

const RAIZ = __DIR__ . '/..';
const SITE_URL = 'https://dipudata.com';
/** Correo para pedidos de corrección o baja. Vacío = no se muestra y queda solo el pedido público en GitHub. */
const CONTACTO_EMAIL = '';
const PEDIDOS_URL = 'https://github.com/Srproxo/dipudata/issues/new';

require_once __DIR__ . '/Datos.php';
require_once __DIR__ . '/Web.php';
require_once __DIR__ . '/Contenido.php';
