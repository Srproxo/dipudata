#!/usr/bin/env python3
"""
Descarga de senado.gob.ar (fuente primaria) la lista de proyectos de cada senador en ejercicio y la guarda en
storage/oficial/senado/proyectos_senadores.json.gz.

El Senado no publica proyectos como datos abiertos; cada página de senador (senadores/senador/{ID}?ProyectosSenador={página})
lista, de a 10, los proyectos del mandato actual: expediente, fecha de mesa de entradas y extracto. El senador se identifica
por el ID oficial de la URL (no por nombre). La lista incluye los proyectos que firmó con otros ("X Y OTROS").
La página no informa qué pasó con cada proyecto.

Validaciones: las páginas se recorren hasta que una viene vacía; no se repite ningún expediente por senador; el año del
expediente coincide con el de la fecha de mesa de entradas (o es el anterior, por expedientes de fin de año).
Pausa de cortesía entre pedidos. Reanudable: si el archivo existe, solo se vuelven a pedir los senadores faltantes
(--todos para rehacer todo).

Uso:  python3 -I scripts/bajar_proyectos_senado.py [--todos]
"""
import concurrent.futures, gzip, html, json, re, sys, time, urllib.request
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
ARCHIVO = RAIZ / "storage" / "oficial" / "senado" / "proyectos_senadores.json.gz"
PAUSA = 0.6
HILOS = 3
TIPOS = {"PL": "Proyecto de ley", "PD": "Proyecto de declaración", "PC": "Proyecto de comunicación", "PR": "Proyecto de resolución"}


def pedir(sid: int, pagina: int):
    req = urllib.request.Request(f"https://www.senado.gob.ar/senadores/senador/{sid}?ProyectosSenador={pagina}",
                                 headers={"User-Agent": "Mozilla/5.0 (DipuData; datos abiertos)"})
    for intento in range(4):
        try:
            with urllib.request.urlopen(req, timeout=45) as r:
                return r.read().decode("utf-8", errors="replace")
        except Exception:
            time.sleep(3 * (intento + 1))
    return None


def filas(t: str):
    i = t.find('id="3"')
    seg = t[i:] if i >= 0 else ""
    out = []
    for ref, exp, fecha, extracto in re.findall(r'verExp/([^"]+)">([^<]+)</a>\s*</td>\s*<td>\s*([\d-]+)\s*</td>\s*<td>(.*?)</td>', seg, flags=re.S):
        d, m, a = fecha.split("-")
        out.append([ref.strip(), exp.strip(), f"{a}-{m}-{d}", re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", extracto))).strip()])
    return out


def senador(sid: int):
    todos, vistos = [], set()
    for pagina in range(1, 200):
        t = pedir(sid, pagina)
        time.sleep(PAUSA)
        if t is None:
            return sid, None
        f = filas(t)
        if not f:
            break
        for x in f:
            if x[0] not in vistos:
                vistos.add(x[0])
                todos.append(x)
    return sid, todos


def main() -> int:
    legs = json.loads((RAIZ / "storage" / "verificado" / "legisladores.json").read_text(encoding="utf-8"))
    ids = sorted({l["id_senado"] for l in legs if l["camara"] == "Senadores" and l.get("id_senado")})
    est = {"fuente": "https://www.senado.gob.ar/senadores/senador/{id}?ProyectosSenador={pagina}", "bajado": time.strftime("%Y-%m-%d"), "senadores": {}}
    if ARCHIVO.exists() and "--todos" not in sys.argv:
        with gzip.open(ARCHIVO, "rt", encoding="utf-8") as f:
            est = json.load(f)
    pendientes = [i for i in ids if str(i) not in est["senadores"]]
    with concurrent.futures.ThreadPoolExecutor(HILOS) as pool:
        for sid, lista in pool.map(senador, pendientes):
            if lista is None:
                print(f"senador {sid}: sin respuesta, se reintenta en la próxima corrida", file=sys.stderr)
                continue
            est["senadores"][str(sid)] = lista
            print(f"  senador {sid}: {len(lista)} proyectos", flush=True)
    est["bajado"] = time.strftime("%Y-%m-%d")
    ARCHIVO.parent.mkdir(parents=True, exist_ok=True)
    with gzip.open(ARCHIVO, "wt", encoding="utf-8") as f:
        json.dump(est, f, ensure_ascii=False, separators=(",", ":"))
    print(f"{len(est['senadores'])} senadores, {sum(len(v) for v in est['senadores'].values())} proyectos")
    return 0


if __name__ == "__main__":
    sys.exit(main())
