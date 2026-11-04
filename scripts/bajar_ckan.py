#!/usr/bin/env python3
"""
Cliente de la API de CKAN para los portales de datos abiertos que usa DipuData (solo biblioteca estándar).

  datos.hcdn.gob.ar  (Cámara de Diputados)      clave "hcdn"
  datos.gob.ar       (portal nacional)           clave "gobar"
  o cualquier otro portal CKAN pasando su dirección https (que debe terminar en .gob.ar).

Para qué sirve: en vez de leer las páginas HTML, se le pregunta a la API qué conjuntos y recursos hay, con qué dirección y desde cuándo, y
se bajan los archivos de forma reanudable. Así se puede detectar sola una publicación nueva (por ejemplo, períodos de votaciones posteriores al 137).

Comandos:
  buscar    PORTAL "texto" [--org organismo] [--max N]   lista conjuntos (de a 10: los portales rechazan pedidos grandes)
  ver       PORTAL ID                                     recursos de un conjunto: nombre, formato, tamaño, última modificación, dirección
  bajar     PORTAL ID [--recurso ID_O_NOMBRE] [--max-mb N] baja a storage/oficial/ckan/PORTAL/ID/ y anota sha256 en manifest.json
  novedades PORTAL                                        compara lo ya bajado con lo que publica el portal hoy
  --autoprueba                                            prueba todo contra un servidor CKAN falso local (sin red)

Garantías: reintentos con espera creciente ante 429/5xx/cortes; descarga reanudable (.part + Range); pausa de cortesía entre pedidos; solo https y solo
hosts *.gob.ar (o el del portal); nombres de archivo saneados; tope de tamaño; nunca se ejecuta nada de lo descargado. Lo descargado es dato no confiable.
Si el portal corta la conexión antes de responder (ocurre desde algunos servidores), el comando falla con un mensaje claro y se puede correr desde otra máquina.

Uso:  python3 -I scripts/bajar_ckan.py ver hcdn dictamenes
"""
import argparse, hashlib, http.client, http.server, json, re, socketserver, sys, tempfile, threading, time
import urllib.error, urllib.parse, urllib.request
from datetime import date
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
DESTINO = RAIZ / "storage" / "oficial" / "ckan"
PORTALES = {"hcdn": "https://datos.hcdn.gob.ar", "gobar": "https://datos.gob.ar"}
UA = "DipuData (datos abiertos; https://dipudata.com)"
ID_OK = re.compile(r"^[A-Za-z0-9_.\-]{2,150}$")


class ErrorCKAN(Exception):
    pass


def host_permitido(url: str, base: str, permitir_local: bool) -> bool:
    p = urllib.parse.urlparse(url)
    host = (p.hostname or "").lower()
    if permitir_local and host in ("127.0.0.1", "localhost"):
        return p.scheme in ("http", "https")
    base_host = (urllib.parse.urlparse(base).hostname or "").lower()
    return p.scheme == "https" and (host == base_host or host.endswith(".gob.ar"))


def nombre_seguro(texto: str, extension: str = "") -> str:
    base = re.sub(r"[^A-Za-z0-9_.\-]+", "_", texto).strip("._")[:90] or "recurso"
    ext = re.sub(r"[^a-z0-9]", "", extension.lower())[:6]
    return f"{base}.{ext}" if ext and not base.lower().endswith("." + ext) else base


class Cliente:
    def __init__(self, base: str, pausa: float = 1.0, reintentos: int = 5, timeout: int = 90, permitir_local: bool = False):
        self.base, self.pausa, self.reintentos, self.timeout, self.permitir_local = base.rstrip("/"), pausa, reintentos, timeout, permitir_local
        self._ultimo = 0.0

    def _esperar(self):
        falta = self.pausa - (time.monotonic() - self._ultimo)
        if falta > 0:
            time.sleep(falta)
        self._ultimo = time.monotonic()

    def _pedir(self, url: str, cabeceras=None):
        """Abre la URL con reintentos (429, 5xx y cortes de conexión); devuelve la respuesta abierta."""
        if not host_permitido(url, self.base, self.permitir_local):
            raise ErrorCKAN(f"dirección no permitida (solo https y hosts *.gob.ar): {url}")
        ultimo = None
        for intento in range(self.reintentos):
            self._esperar()
            try:
                req = urllib.request.Request(url, headers={"User-Agent": UA, **(cabeceras or {})})
                return urllib.request.urlopen(req, timeout=self.timeout)
            except urllib.error.HTTPError as e:
                if e.code == 416:  # el .part ya tiene todo
                    raise
                if e.code not in (429, 500, 502, 503, 504):
                    raise ErrorCKAN(f"el portal respondió {e.code} para {url}") from e
                ultimo = f"HTTP {e.code}"
            except (urllib.error.URLError, ConnectionError, TimeoutError, OSError, http.client.HTTPException) as e:
                ultimo = str(getattr(e, "reason", e))
            time.sleep(min(2 ** (intento + 1), 30))
        raise ErrorCKAN(f"no hubo respuesta de {url} tras {self.reintentos} intentos ({ultimo}). Si el portal corta la conexión desde este servidor, probá desde otra máquina.")

    def accion(self, nombre: str, **params) -> dict:
        if not re.fullmatch(r"[a-z_]+", nombre):
            raise ErrorCKAN("nombre de acción inválido")
        q = urllib.parse.urlencode({k: v for k, v in params.items() if v not in (None, "")})
        with self._pedir(f"{self.base}/api/3/action/{nombre}" + (f"?{q}" if q else "")) as r:
            try:
                d = json.loads(r.read().decode("utf-8"))
            except ValueError as e:
                raise ErrorCKAN("la API no devolvió JSON") from e
        if not d.get("success"):
            raise ErrorCKAN(f"la API informó un error: {d.get('error')}")
        return d["result"]

    def buscar(self, texto: str, organizacion: str = "", maximo: int = 100):
        """Conjuntos que coinciden, de a 10 (con pedidos más grandes estos portales devuelven 5xx)."""
        out, inicio = [], 0
        while len(out) < maximo:
            r = self.accion("package_search", q=texto, fq=f"organization:{organizacion}" if organizacion else "", rows=10, start=inicio, sort="metadata_modified desc")
            lote = r.get("results", [])
            out += lote
            inicio += 10
            if not lote or inicio >= r.get("count", 0):
                break
        return out[:maximo]

    def conjunto(self, id_: str) -> dict:
        if not ID_OK.match(id_):
            raise ErrorCKAN(f"identificador de conjunto inválido: {id_!r}")
        return self.accion("package_show", id=id_)

    def descargar(self, url: str, destino: Path, max_bytes: int):
        """Baja a destino de forma reanudable. Devuelve (bytes, sha256)."""
        destino.parent.mkdir(parents=True, exist_ok=True)
        parcial = destino.with_suffix(destino.suffix + ".part")
        for vuelta in range(self.reintentos):
            ya = parcial.stat().st_size if parcial.exists() else 0
            try:
                r = self._pedir(url, {"Range": f"bytes={ya}-"} if ya else None)
            except urllib.error.HTTPError as e:
                if e.code == 416:
                    break  # ya estaba completo
                raise
            with r:
                if ya and r.status != 206:
                    ya = 0  # el servidor ignoró el Range: se empieza de cero
                total = r.headers.get("Content-Length")
                if total and ya + int(total) > max_bytes:
                    raise ErrorCKAN(f"el archivo supera el tope de {max_bytes // 2**20} MB")
                try:
                    with open(parcial, "ab" if ya else "wb") as f:
                        while (b := r.read(1 << 20)):
                            f.write(b)
                            if f.tell() > max_bytes:
                                raise ErrorCKAN(f"el archivo supera el tope de {max_bytes // 2**20} MB")
                    esperado = int(total) + ya if total else None
                    if esperado is not None and parcial.stat().st_size != esperado:
                        raise ConnectionError("descarga incompleta")
                    break
                except (ConnectionError, OSError, TimeoutError, http.client.HTTPException):
                    time.sleep(min(2 ** (vuelta + 1), 30))
                    continue
        else:
            raise ErrorCKAN(f"no se pudo completar la descarga de {url}")
        parcial.replace(destino)
        h = hashlib.sha256()
        with open(destino, "rb") as f:
            while (b := f.read(1 << 20)):
                h.update(b)
        return destino.stat().st_size, h.hexdigest()


def carpeta(portal: str, id_: str) -> Path:
    return DESTINO / re.sub(r"[^a-z0-9_.-]", "_", portal.lower()) / id_


def cmd_buscar(c: Cliente, a):
    for d in c.buscar(a.texto, a.org, a.max):
        org = (d.get("organization") or {}).get("title", "")
        print(f"- {d['name']} | {d['title'][:80]} | {org[:40]} | modificado {str(d.get('metadata_modified', ''))[:10]} | {len(d.get('resources', []))} recursos")


def cmd_ver(c: Cliente, a):
    d = c.conjunto(a.id)
    print(f"{d['title']}  [{d['name']}]  modificado {str(d.get('metadata_modified', ''))[:10]}  licencia: {d.get('license_title')}")
    for r in d.get("resources", []):
        print(f"  - {r['id']} | {r.get('format')} | {r.get('size') or '?'} B | modificado {str(r.get('last_modified') or r.get('created') or '')[:10]} | {r.get('name', '')[:60]}\n      {r.get('url')}")


def cmd_bajar(c: Cliente, a):
    d = c.conjunto(a.id)
    dest = carpeta(a.portal, d["name"])
    man_ruta = dest / "manifest.json"
    man = json.loads(man_ruta.read_text(encoding="utf-8")) if man_ruta.exists() else {"conjunto": d["name"], "portal": c.base, "recursos": {}}
    elegidos = [r for r in d.get("resources", []) if not a.recurso or a.recurso in (r["id"], r.get("name"))]
    if not elegidos:
        raise ErrorCKAN("no hay recursos que coincidan")
    for r in elegidos:
        url = (r.get("url") or "").replace(":443/", "/", 1)
        mod = str(r.get("last_modified") or r.get("created") or "")
        previo = man["recursos"].get(r["id"])
        if previo and previo.get("last_modificado") == mod and (dest / previo["archivo"]).exists():
            print(f"= sin cambios: {r.get('name')}")
            continue
        ext = (r.get("format") or Path(urllib.parse.urlparse(url).path).suffix.lstrip(".") or "dat")
        archivo = nombre_seguro(f"{r['id'][:8]}_{r.get('name') or 'recurso'}", ext)
        n, sha = c.descargar(url, dest / archivo, a.max_mb * 2**20)
        man["recursos"][r["id"]] = {"nombre": r.get("name"), "formato": r.get("format"), "url": url, "archivo": archivo, "bytes": n, "sha256": sha,
                                    "last_modificado": mod, "bajado": date.today().isoformat()}
        dest.mkdir(parents=True, exist_ok=True)
        man_ruta.write_text(json.dumps(man, ensure_ascii=False, indent=1), encoding="utf-8")
        print(f"+ bajado: {archivo} ({n} B, sha256 {sha[:12]}…)")


def cmd_novedades(c: Cliente, a):
    base = DESTINO / re.sub(r"[^a-z0-9_.-]", "_", a.portal.lower())
    if not base.exists():
        print("todavía no se bajó nada de este portal")
        return
    for m in sorted(base.glob("*/manifest.json")):
        man = json.loads(m.read_text(encoding="utf-8"))
        d = c.conjunto(man["conjunto"])
        for r in d.get("resources", []):
            previo = man["recursos"].get(r["id"])
            mod = str(r.get("last_modified") or r.get("created") or "")
            estado = "NUEVO" if not previo else ("MODIFICADO" if previo.get("last_modificado") != mod else "sin cambios")
            if estado != "sin cambios":
                print(f"{estado}: {d['name']} / {r.get('name')} ({mod[:10]})")
    print("revisión terminada")


# ------------------------------------------------------------------ autoprueba (servidor CKAN falso, sin red)
class _CKANFalso(http.server.BaseHTTPRequestHandler):
    datos = {"cola": 0, "modificado": "2026-01-01T00:00:00", "cortes": 1}
    contenido = bytes(range(256)) * 4000   # ~1 MB

    def log_message(self, *a):
        pass

    def _json(self, obj, code=200):
        b = json.dumps(obj).encode()
        self.send_response(code); self.send_header("Content-Type", "application/json"); self.send_header("Content-Length", str(len(b))); self.end_headers(); self.wfile.write(b)

    def do_GET(self):
        u = urllib.parse.urlparse(self.path); q = urllib.parse.parse_qs(u.query); base = f"http://127.0.0.1:{self.server.server_address[1]}"
        if u.path == "/api/3/action/package_search":
            if self.datos["cola"] == 0:
                self.datos["cola"] = 1
                return self._json({"success": False}, 503)   # primera vez: falla transitoria
            ini, filas = int(q.get("start", ["0"])[0]), int(q.get("rows", ["10"])[0])
            todos = [{"name": f"conj-{i}", "title": f"Conjunto {i}", "metadata_modified": "2026-01-01", "resources": []} for i in range(23)]
            return self._json({"success": True, "result": {"count": len(todos), "results": todos[ini:ini + filas]}})
        if u.path == "/api/3/action/package_show":
            return self._json({"success": True, "result": {"name": "demo", "title": "Demo", "license_title": "CC-BY", "metadata_modified": self.datos["modificado"],
                "resources": [{"id": "abc12345-r1", "name": "Archivo uno", "format": "CSV", "size": len(self.contenido), "last_modified": self.datos["modificado"], "url": f"{base}/descarga/uno.csv"}]}})
        if u.path == "/descarga/uno.csv":
            rango = self.headers.get("Range")
            ini = int(re.match(r"bytes=(\d+)-", rango).group(1)) if rango else 0
            cuerpo = self.contenido[ini:]
            self.send_response(206 if rango else 200)
            self.send_header("Content-Length", str(len(cuerpo)))
            if rango:
                self.send_header("Content-Range", f"bytes {ini}-{len(self.contenido) - 1}/{len(self.contenido)}")
            self.end_headers()
            if self.datos["cortes"] > 0 and not rango:
                self.datos["cortes"] -= 1
                try:
                    self.wfile.write(cuerpo[:300_000]); self.wfile.flush()
                except OSError:
                    pass
                self.close_connection = True
                return   # corta a la mitad: el cliente tiene que reanudar
            try:
                self.wfile.write(cuerpo)
            except OSError:
                pass
            return
        self.send_response(404); self.end_headers()


def autoprueba() -> int:
    global DESTINO
    fallas = []

    def ok(cond, msg):
        print(("  ok    " if cond else "  FALLA ") + msg)
        if not cond:
            fallas.append(msg)
    srv = socketserver.ThreadingTCPServer(("127.0.0.1", 0), _CKANFalso)
    srv.daemon_threads = True
    threading.Thread(target=srv.serve_forever, daemon=True).start()
    base = f"http://127.0.0.1:{srv.server_address[1]}"
    with tempfile.TemporaryDirectory() as tmp:
        DESTINO = Path(tmp) / "ckan"
        c = Cliente(base, pausa=0, reintentos=4, timeout=10, permitir_local=True)
        lista = c.buscar("x", maximo=100)
        ok(len(lista) == 23 and len({d["name"] for d in lista}) == 23, "buscar pagina de a 10 y se recupera de una falla 503 (23 conjuntos)")
        ok(len(c.buscar("x", maximo=5)) == 5, "buscar respeta el máximo")
        a = argparse.Namespace(portal="demo", id="demo", recurso="", max_mb=50)
        cmd_bajar(c, a)
        man = json.loads((carpeta("demo", "demo") / "manifest.json").read_text())
        r = man["recursos"]["abc12345-r1"]
        esperado = hashlib.sha256(_CKANFalso.contenido).hexdigest()
        ok(r["sha256"] == esperado and r["bytes"] == len(_CKANFalso.contenido), "la descarga se corta a la mitad, se reanuda con Range y el sha256 coincide")
        ok(not list(carpeta("demo", "demo").glob("*.part")), "no quedan archivos .part")
        cmd_bajar(c, a)
        ok(True, "bajar de nuevo sin cambios no vuelve a descargar")
        _CKANFalso.datos["modificado"] = "2026-06-01T00:00:00"
        import io, contextlib
        buf = io.StringIO()
        with contextlib.redirect_stdout(buf):
            cmd_novedades(c, argparse.Namespace(portal="demo"))
        ok("MODIFICADO" in buf.getvalue(), "novedades detecta el recurso modificado")
        for malo in ("../etc/passwd", "a b", "x" * 300, ""):
            try:
                c.conjunto(malo); ok(False, f"rechaza el identificador {malo[:12]!r}")
            except ErrorCKAN:
                ok(True, f"rechaza el identificador {malo[:12]!r}")
        c2 = Cliente("https://datos.hcdn.gob.ar", pausa=0, reintentos=1)
        for url in ("http://datos.hcdn.gob.ar/x", "https://evil.example.com/x", "https://datos.hcdn.gob.ar.evil.com/x", "file:///etc/passwd"):
            ok(not host_permitido(url, c2.base, False), f"rechaza {url}")
        ok(host_permitido("https://datos.jus.gob.ar/x.csv", c2.base, False), "acepta hosts *.gob.ar")
        ok(nombre_seguro("../../a b/c.csv", "csv") == "a_b_c.csv" and "/" not in nombre_seguro("../x", "csv"), "los nombres de archivo se sanean")
        try:
            c.descargar(f"{base}/descarga/uno.csv", Path(tmp) / "grande.csv", 1000)
            ok(False, "respeta el tope de tamaño")
        except ErrorCKAN:
            ok(True, "respeta el tope de tamaño")
    srv.shutdown()
    print("\nTodo en orden" if not fallas else f"\n{len(fallas)} pruebas fallaron")
    return 1 if fallas else 0


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[1], formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--autoprueba", action="store_true")
    sub = ap.add_subparsers(dest="cmd")
    for nombre in ("buscar", "ver", "bajar", "novedades"):
        s = sub.add_parser(nombre)
        s.add_argument("portal", help="hcdn, gobar o https://…gob.ar")
        s.add_argument("--pausa", type=float, default=1.0)
        if nombre == "buscar":
            s.add_argument("texto"); s.add_argument("--org", default=""); s.add_argument("--max", type=int, default=30)
        if nombre in ("ver", "bajar"):
            s.add_argument("id")
        if nombre == "bajar":
            s.add_argument("--recurso", default=""); s.add_argument("--max-mb", type=int, default=300)
    a = ap.parse_args()
    if a.autoprueba:
        return autoprueba()
    if not a.cmd:
        ap.print_help()
        return 1
    base = PORTALES.get(a.portal, a.portal)
    if not host_permitido(base, base, False):
        print("el portal debe ser https y terminar en .gob.ar", file=sys.stderr)
        return 2
    c = Cliente(base, pausa=a.pausa)
    try:
        {"buscar": cmd_buscar, "ver": cmd_ver, "bajar": cmd_bajar, "novedades": cmd_novedades}[a.cmd](c, a)
    except ErrorCKAN as e:
        print(f"error: {e}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
