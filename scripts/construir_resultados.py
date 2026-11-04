#!/usr/bin/env python3
"""
Resultados de las elecciones generales 2023 (Diputados y Senadores nacionales) por provincia.
Lee  storage/oficial/dine/generales_2023_provincia.json.gz  (lo genera bajar_resultados_electorales.py desde el archivo
oficial de la Dirección Nacional Electoral) y escribe  storage/verificado/resultados_2023.json  con la clave de cada
provincia igual al slug de storage/verificado/provincias.json.
Controles (si alguno falla no se escribe nada):
  - 24 jurisdicciones, todas asignadas a una provincia del sitio, sin repetir;
  - cada provincia trae Diputados nacionales; los Senadores solo donde hubo elección;
  - votos positivos + blancos + nulos + impugnados + recurridos + comando no superan los electores de las mesas;
  - ninguna agrupación repetida dentro de un cargo ni con votos negativos.
No se publica qué banca ocupó cada persona: el archivo oficial trae votos por agrupación, no candidatos ni bancas.
Uso:  python3 -I scripts/construir_resultados.py
"""
import gzip, json, sys, unicodedata
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
ENTRADA = RAIZ / "storage" / "oficial" / "dine" / "generales_2023_provincia.json.gz"
SALIDA = RAIZ / "storage" / "verificado" / "resultados_2023.json"
ALIAS = {"tierra del fuego, antartida e islas del atlantico sur": "tierra del fuego"}
ORDEN_OTROS = ["EN BLANCO", "NULO", "IMPUGNADO", "RECURRIDO", "COMANDO"]


def norm(s: str) -> str:
    s = unicodedata.normalize("NFD", s.lower())
    return "".join(c for c in s if unicodedata.category(c) != "Mn").strip()


def main() -> int:
    d = json.load(gzip.open(ENTRADA, "rt", encoding="utf-8"))
    sitio = {norm(p["nombre"]): p["slug"] for p in json.load(open(RAIZ / "storage" / "verificado" / "provincias.json", encoding="utf-8"))}
    errores, prov = [], {}
    if len(d["provincias"]) != 24:
        errores.append(f"hay {len(d['provincias'])} jurisdicciones, no 24")
    for p in d["provincias"]:
        n = norm(p["nombre"])
        slug = sitio.get(ALIAS.get(n, n))
        if not slug or slug in prov:
            errores.append(f"jurisdicción sin asignar o repetida: {p['nombre']}")
            continue
        cargos, suma_max = {}, 0
        if "3" not in p["cargos"]:
            errores.append(f"{p['nombre']}: sin Diputados nacionales")
        for c, cg in p["cargos"].items():
            ids = [a["id"] for a in cg["agrupaciones"]]
            if len(ids) != len(set(ids)) or any(a["votos"] < 0 for a in cg["agrupaciones"]) or not ids:
                errores.append(f"{p['nombre']}: agrupaciones repetidas, vacías o con votos negativos en cargo {c}")
            positivos = sum(a["votos"] for a in cg["agrupaciones"])
            total = positivos + sum(cg["otros"].values())
            if total > p["electores"] or total <= 0:
                errores.append(f"{p['nombre']}: los votos del cargo {c} ({total}) no caben en los electores ({p['electores']})")
            cargos["diputados" if c == "3" else "senadores"] = {
                "positivos": positivos, "votantes": total,
                "otros": {t: cg["otros"].get(t, 0) for t in ORDEN_OTROS},
                "agrupaciones": [{"nombre": a["nombre"], "votos": a["votos"]} for a in cg["agrupaciones"]],
            }
        prov[slug] = {"nombre": p["nombre"], "mesas": p["mesas"], "electores": p["electores"], **cargos}
    if errores:
        print("\n".join(errores), file=sys.stderr)
        return 1
    m = d["_meta"]
    salida = {"_meta": {
        "fuente": m["fuente"], "pagina": m["pagina"], "url_archivo": m["url_archivo"], "sha256_zip": m["sha256_zip"],
        "eleccion": m["eleccion"], "escrutinio": m["escrutinio"], "bajado": m["bajado"],
        "provincias_con_senadores": sorted(s for s, v in prov.items() if "senadores" in v),
    }, "provincias": prov}
    SALIDA.write_text(json.dumps(salida, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"{len(prov)} provincias; con elección de senadores: {len(salida['_meta']['provincias_con_senadores'])}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
