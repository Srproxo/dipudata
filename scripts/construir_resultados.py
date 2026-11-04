#!/usr/bin/env python3
"""
Resultados de elecciones nacionales (Diputados y Senadores nacionales) por provincia.
Lee todos los  storage/oficial/dine/<clave>_provincia.json.gz  (los genera bajar_resultados_electorales.py desde los archivos
oficiales de la Dirección Nacional Electoral) y escribe  storage/verificado/resultados_elecciones.json  con la clave de cada
provincia igual al slug de storage/verificado/provincias.json.
Controles por elección (si alguno falla no se escribe nada):
  - 24 jurisdicciones, todas asignadas a una provincia del sitio, sin repetir;
  - cada provincia trae Diputados nacionales; los Senadores solo donde hubo elección (en cada año, 8 provincias);
  - votos positivos + blancos + nulos + impugnados + recurridos + comando no superan los electores de las mesas;
  - ninguna agrupación repetida dentro de un cargo ni con votos negativos.
No se publica qué banca ocupó cada persona: el archivo oficial trae votos por agrupación, no candidatos ni bancas.
Uso:  python3 -I scripts/construir_resultados.py
"""
import glob, gzip, json, sys, unicodedata
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
SALIDA = RAIZ / "storage" / "verificado" / "resultados_elecciones.json"
ALIAS = {"tierra del fuego, antartida e islas del atlantico sur": "tierra del fuego"}
ORDEN_OTROS = ["EN BLANCO", "NULO", "IMPUGNADO", "RECURRIDO", "COMANDO"]


def norm(s: str) -> str:
    s = unicodedata.normalize("NFD", s.lower())
    return "".join(c for c in s if unicodedata.category(c) != "Mn").strip()


def una(d: dict, sitio: dict, errores: list):
    m = d["_meta"]
    pref = f"{m['clave']}: "
    prov = {}
    if len(d["provincias"]) != 24:
        errores.append(f"{pref}hay {len(d['provincias'])} jurisdicciones, no 24")
    for p in d["provincias"]:
        n = norm(p["nombre"])
        slug = sitio.get(ALIAS.get(n, n))
        if not slug or slug in prov:
            errores.append(f"{pref}jurisdicción sin asignar o repetida: {p['nombre']}")
            continue
        cargos = {}
        if "3" not in p["cargos"]:
            errores.append(f"{pref}{p['nombre']}: sin Diputados nacionales")
        for c, cg in p["cargos"].items():
            ids = [a["id"] for a in cg["agrupaciones"]]
            if len(ids) != len(set(ids)) or any(a["votos"] < 0 for a in cg["agrupaciones"]) or not ids:
                errores.append(f"{pref}{p['nombre']}: agrupaciones repetidas, vacías o con votos negativos en cargo {c}")
            positivos = sum(a["votos"] for a in cg["agrupaciones"])
            total = positivos + sum(cg["otros"].values())
            if total > p["electores"] or total <= 0:
                errores.append(f"{pref}{p['nombre']}: los votos del cargo {c} ({total}) no caben en los electores ({p['electores']})")
            cargos["diputados" if c == "3" else "senadores"] = {
                "positivos": positivos, "votantes": total,
                "otros": {t: cg["otros"].get(t, 0) for t in ORDEN_OTROS},
                "agrupaciones": [{"nombre": a["nombre"], "votos": a["votos"]} for a in cg["agrupaciones"]],
            }
        prov[slug] = {"nombre": p["nombre"], "mesas": p["mesas"], "electores": p["electores"], **cargos}
    con_sen = sorted(s for s, v in prov.items() if "senadores" in v)
    if len(con_sen) != 8:
        errores.append(f"{pref}el Senado se renovó en {len(con_sen)} provincias, no en 8")
    meta = {k: m[k] for k in ("clave", "anio", "tipo", "fecha", "fuente", "pagina", "url_archivo", "sha256_zip", "eleccion", "escrutinio", "bajado")}
    meta["provincias_con_senadores"] = con_sen
    return {"_meta": meta, "provincias": prov}


def main() -> int:
    sitio = {norm(p["nombre"]): p["slug"] for p in json.load(open(RAIZ / "storage" / "verificado" / "provincias.json", encoding="utf-8"))}
    errores, elecciones = [], []
    for ruta in sorted(glob.glob(str(RAIZ / "storage" / "oficial" / "dine" / "*_provincia.json.gz"))):
        d = json.load(gzip.open(ruta, "rt", encoding="utf-8"))
        if "clave" not in d["_meta"]:
            errores.append(f"{Path(ruta).name}: archivo viejo, volver a generarlo con bajar_resultados_electorales.py")
            continue
        elecciones.append(una(d, sitio, errores))
    if errores:
        print("\n".join(errores), file=sys.stderr)
        return 1
    elecciones.sort(key=lambda e: e["_meta"]["fecha"], reverse=True)
    SALIDA.write_text(json.dumps({"elecciones": elecciones}, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"{len(elecciones)} elecciones:", ", ".join(e["_meta"]["clave"] for e in elecciones))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
