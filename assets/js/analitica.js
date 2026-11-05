/* Consentimiento de cookies y Google Analytics 4. Solo se incluye en las páginas cuando hay un ID configurado (GA_ID).
   Google Analytics NO se carga hasta que la persona lo acepta; la elección se guarda en la cookie dd_cookies (180 días). */
(function () {
  var tag = document.currentScript;
  var id = tag && tag.getAttribute('data-id');
  if (!id) { return; }
  var cargado = false;

  function leer() {
    var m = document.cookie.match(/(?:^|; )dd_cookies=(si|no)/);
    return m ? m[1] : null;
  }
  function guardar(v) {
    document.cookie = 'dd_cookies=' + v + '; Max-Age=15552000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  }
  function borrarAnalitica() {
    var partes = document.cookie.split('; ');
    var dominios = [location.hostname, '.' + location.hostname.replace(/^www\./, '')];
    for (var i = 0; i < partes.length; i++) {
      var n = partes[i].split('=')[0];
      if (n === '_ga' || n.indexOf('_ga_') === 0) {
        document.cookie = n + '=; Max-Age=0; Path=/';
        for (var j = 0; j < dominios.length; j++) { document.cookie = n + '=; Max-Age=0; Path=/; Domain=' + dominios[j]; }
      }
    }
  }
  function cargar() {
    if (cargado) { return; }
    cargado = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', id, { anonymize_ip: true });
    var g = document.createElement('script');
    g.async = true;
    g.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    document.head.appendChild(g);
  }
  function cerrarAviso() {
    var a = document.getElementById('cookies-aviso');
    if (a && a.parentNode) { a.parentNode.removeChild(a); }
  }
  function estado(v) {
    var e = document.querySelector('[data-cookies-estado]');
    if (e) { e.textContent = v === 'si' ? 'Elegiste aceptar las cookies de análisis.' : v === 'no' ? 'Elegiste rechazar las cookies de análisis.' : 'Todavía no elegiste.'; }
  }
  function elegir(v) {
    guardar(v);
    if (v === 'si') { cargar(); } else { borrarAnalitica(); }
    cerrarAviso();
    estado(v);
  }
  function aviso() {
    var d = document.createElement('div');
    d.id = 'cookies-aviso';
    d.className = 'cookies-aviso';
    d.setAttribute('role', 'region');
    d.setAttribute('aria-label', 'Cookies');
    d.innerHTML = '<p>Usamos cookies de análisis (Google Analytics) para saber cómo se usa el sitio, solo si las aceptás. ' +
      '<a href="/cookies">Más información</a>.</p>' +
      '<p class="cookies-botones"><button type="button" class="boton" data-cookies="si">Aceptar</button> ' +
      '<button type="button" class="boton boton-sec" data-cookies="no">Rechazar</button></p>';
    document.body.appendChild(d);
    enlazar(d);
  }
  function enlazar(raiz) {
    var bs = raiz.querySelectorAll('[data-cookies]');
    for (var i = 0; i < bs.length; i++) {
      bs[i].addEventListener('click', function (ev) { elegir(ev.currentTarget.getAttribute('data-cookies')); });
    }
  }

  var actual = leer();
  if (actual === 'si') { cargar(); }
  enlazar(document);
  estado(actual);
  if (actual === null) { aviso(); }
})();
