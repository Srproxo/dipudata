#!/usr/bin/env python3
"""
Descarga, para cada senador en ejercicio, la lista de proyectos que firmó, desde el buscador oficial del Senado
(www.senado.gob.ar/parlamentario/parlamentaria/, búsqueda avanzada por autor). El buscador filtra por el ID oficial
del senador, así que el cruce no depende del nombre. Incluye los proyectos en los que figura como autor o coautor,
de todos sus mandatos.

El Senado no publica estos proyectos como datos abiertos: se lee la página del buscador, de a 10 resultados.
Cada lista se valida: ningún expediente repetido y la cantidad de páginas coincide con la que anuncia el buscador.

Salida: storage/oficial/senado/proyectos_por_senador.json.gz  {id_senado: [[expediente, tipo, origen, extracto], ...]}
Reanudable (storage/oficial/senado/proyectos_senado.parcial.jsonl, ignorado por git), con pausa de cortesía.

Uso:  python3 -I scripts/bajar_proyectos_senado.py   (después de construir_datos.py)
"""
import gzip, html, http.cookiejar, json, re, sys, time, urllib.parse, urllib.request
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
DEST = RAIZ / "storage" / "oficial" / "senado"
PARCIAL = DEST / "proyectos_senado.parcial.jsonl"
BASE = "https://www.senado.gob.ar/parlamentario/parlamentaria/"
PAUSA = 0.8


def abrir(op, url, datos=None):
    req = urllib.request.Request(url, data=datos, headers={"User-Agent": "Mozilla/5.0 (DipuData; datos abiertos)"})
    for intento in range(4):
        try:
            with op.open(req, timeout=90) as r:
                return r.read().decode("utf-8", errors="replace")
        except Exception:
            time.sleep(4 * (intento + 1))
    raise RuntimeError(f"no se pudo leer {url}")


def limpiar(t):
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", t))).strip()


def filas(t):
    out = []
    for f in re.findall(r"<tr[^>]*>(.*?)</tr>", t, flags=re.S):
        m = re.search(r'href="/parlamentario/comisiones/verExp/([^"]+)"', f)
        if not m:
            continue
        c = [limpiar(x) for x in re.findall(r"<td[^>]*>(.*?)</td>", f, flags=re.S)]
        if len(c) >= 4:
            out.append([c[0], c[1], c[2], c[3], m.group(1)])
    return out


def proyectos(sid: int):
    op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    t = abrir(op, BASE)
    tok = re.search(r'name="busqueda_proyectos\[_token\]" value="([^"]+)"', t)
    if not tok:
        raise RuntimeError("el formulario no trae el token")
    datos = urllib.parse.urlencode({"busqueda_proyectos[autor]": str(sid), "busqueda_proyectos[_token]": tok.group(1)}).encode()
    t = abrir(op, BASE + "avanzada", datos)
    paginas = max([1] + [int(p) for p in re.findall(r"avanzada\?page=(\d+)", t)])
    if not re.search(rf'<option value="{sid}"\s+selected', t) and paginas > 1:
        # Si el ID todavía no figura entre los autores del buscador (recién asumió), el buscador ignora el filtro y
        # devuelve todos los proyectos del Senado: eso no se toma como lista del senador.
        raise RuntimeError(f"senador {sid}: el buscador no reconoce el ID como autor")
    todas = filas(t)
    for p in range(2, paginas + 1):
        time.sleep(PAUSA)
        t = abrir(op, BASE + f"avanzada?page={p}")
        todas += filas(t)
        paginas = max([paginas] + [int(x) for x in re.findall(r"avanzada\?page=(\d+)", t)])
    exps = [f[4] for f in todas]
    if len(exps) != len(set(exps)):
        raise RuntimeError(f"senador {sid}: expedientes repetidos entre páginas")
    if todas and not (10 * (paginas - 1) < len(todas) <= 10 * paginas):
        raise RuntimeError(f"senador {sid}: {len(todas)} proyectos no cuadran con {paginas} páginas")
    return [f[:4] for f in todas]


def main() -> int:
    legs = json.loads((RAIZ / "storage" / "verificado" / "legisladores.json").read_text(encoding="utf-8"))
    ids = sorted(l["id_senado"] for l in legs if l["camara"] == "Senadores" and l.get("id_senado"))
    hechos = {}
    if PARCIAL.exists():
        for linea in PARCIAL.read_text(encoding="utf-8").splitlines():
            d = json.loads(linea)
            hechos[d["id"]] = d["p"]
    with PARCIAL.open("a", encoding="utf-8") as f:
        for sid in ids:
            if sid in hechos:
                continue
            try:
                p = proyectos(sid)
            except RuntimeError as e:
                print("ERROR", e, flush=True)
                continue
            hechos[sid] = p
            f.write(json.dumps({"id": sid, "p": p}, ensure_ascii=False) + "\n")
            f.flush()
            print(f"  senador {sid}: {len(p)} proyectos", flush=True)
            time.sleep(PAUSA)
    faltan = [i for i in ids if i not in hechos]
    with gzip.open(DEST / "proyectos_por_senador.json.gz", "wt", encoding="utf-8") as f:
        json.dump({str(k): v for k, v in sorted(hechos.items()) if k in ids}, f, ensure_ascii=False, separators=(",", ":"))
    print(f"{len(ids) - len(faltan)} de {len(ids)} senadores; {sum(len(hechos[i]) for i in ids if i in hechos)} proyectos"
          + (f"; faltan {faltan}" if faltan else ""))
    return 1 if faltan else 0


if __name__ == "__main__":
    sys.exit(main())
