#!/usr/bin/env python3
"""
Copia las fotos de los legisladores a assets/fotos/ para que el sitio las sirva desde su propio dominio (sin pedirlas a terceros).

Fuentes: la URL de cada foto en storage/verificado/legisladores.json (ArgentinaDatos para diputados, senado.gob.ar para senadores).
Solo se aceptan respuestas que sean realmente una imagen (JPEG, PNG o GIF, por su encabezado) y de tamaño razonable; cualquier otra cosa
(una página de error, una imagen genérica de Wikimedia) se descarta y el perfil muestra las iniciales.
Es reanudable: no vuelve a bajar una foto que ya está. Pausa de cortesía entre pedidos.
Escribe storage/verificado/fotos.json  {"<slug>" | "s:<slug>": "/assets/fotos/<archivo>"}  (lo que lee app/Datos.php).

Uso:  python3 -I scripts/bajar_fotos.py [--rehacer]
"""
import concurrent.futures, json, sys, time, urllib.request
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
DEST = RAIZ / "assets" / "fotos"
SAL = RAIZ / "storage" / "verificado" / "fotos.json"
PERMITIDOS = ("https://api.argentinadatos.com/", "https://www.senado.gob.ar/")
MAX_BYTES = 400_000


def tipo(b: bytes):
    if b[:3] == b"\xff\xd8\xff":
        return "jpg"
    if b[:8] == b"\x89PNG\r\n\x1a\n":
        return "png"
    if b[:4] == b"GIF8":
        return "gif"
    return None


def bajar(item):
    clave, url, base = item
    ya = next((p for p in DEST.glob(base + ".*")), None)
    if ya and "--rehacer" not in sys.argv:
        return clave, "/assets/fotos/" + ya.name, "ya estaba"
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (DipuData; datos abiertos)"})
    for intento in range(3):
        try:
            with urllib.request.urlopen(req, timeout=30) as r:
                datos = r.read(MAX_BYTES + 1)
            break
        except Exception:
            time.sleep(2 * (intento + 1))
    else:
        return clave, None, "sin respuesta"
    time.sleep(0.3)
    ext = tipo(datos)
    if not ext or len(datos) > MAX_BYTES or len(datos) < 500:
        return clave, None, "no es una imagen válida"
    (DEST / f"{base}.{ext}").write_bytes(datos)
    return clave, f"/assets/fotos/{base}.{ext}", "bajada"


def main() -> int:
    DEST.mkdir(parents=True, exist_ok=True)
    legs = json.loads((RAIZ / "storage" / "verificado" / "legisladores.json").read_text(encoding="utf-8"))
    items = []
    for l in legs:
        f = l.get("foto") or ""
        if f.startswith(PERMITIDOS):
            sen = l["camara"] == "Senadores"
            items.append((("s:" if sen else "") + l["slug"], f, ("s-" if sen else "d-") + l["slug"]))
    fotos, estados = {}, {}
    with concurrent.futures.ThreadPoolExecutor(3) as pool:
        for clave, ruta, estado in pool.map(bajar, items):
            estados[estado] = estados.get(estado, 0) + 1
            if ruta:
                fotos[clave] = ruta
    SAL.write_text(json.dumps(dict(sorted(fotos.items())), ensure_ascii=False, indent=0), encoding="utf-8")
    print(f"{len(fotos)} fotos de {len(legs)} legisladores:", estados)
    return 0


if __name__ == "__main__":
    sys.exit(main())
