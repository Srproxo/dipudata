#!/usr/bin/env python3
"""
Resultados de las elecciones generales 2023 para Diputados y Senadores nacionales, por provincia, desde el archivo oficial
de la Dirección Nacional Electoral (Ministerio del Interior, argentina.gob.ar/dine/resultados-electorales/elecciones-2023).
El archivo (28 MB comprimido, ~1 GB descomprimido, escrutinio provisorio a nivel de mesa) se lee en streaming; no se guarda.
Se guarda solo el agregado por provincia, con la huella (sha256) del archivo de origen:
  storage/oficial/dine/generales_2023_provincia.json.gz
Controles al leer: ninguna fila repetida (mismo mesa/cargo/agrupación/lista/tipo de voto); cada mesa trae un único
total de electores; el escrutinio es el PROVISORIO de las generales (no el definitivo).
Uso:  python3 -I scripts/bajar_resultados_electorales.py [ruta_al_zip]   (sin argumento, lo baja a storage/cache/)
"""
import collections, csv, gzip, hashlib, io, json, sys, urllib.request, zipfile
from datetime import date
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
URL = "https://www.argentina.gob.ar/sites/default/files/2023_generales_1.zip"
PAGINA = "https://www.argentina.gob.ar/dine/resultados-electorales/elecciones-2023"
INTERNO = "2023_Generales/ResultadoElectorales_2023_Generales.csv"
CARGOS = {"2": "Senadores nacionales", "3": "Diputados nacionales"}
DEST = RAIZ / "storage" / "oficial" / "dine" / "generales_2023_provincia.json.gz"


def conseguir_zip(arg):
    if arg:
        return Path(arg)
    ruta = RAIZ / "storage" / "cache" / "2023_generales_1.zip"
    if not ruta.exists():
        ruta.parent.mkdir(parents=True, exist_ok=True)
        req = urllib.request.Request(URL, headers={"User-Agent": "DipuData"})
        with urllib.request.urlopen(req, timeout=300) as r, open(ruta, "wb") as f:
            while (b := r.read(1 << 20)):
                f.write(b)
    return ruta


def main() -> int:
    ruta = conseguir_zip(sys.argv[1] if len(sys.argv) > 1 else None)
    h = hashlib.sha256()
    with open(ruta, "rb") as f:
        while (b := f.read(1 << 20)):
            h.update(b)
    votos = collections.defaultdict(int)            # (distrito, cargo, agrupacion_id, nombre) -> votos positivos
    otros = collections.defaultdict(int)            # (distrito, cargo, tipo) -> votos
    mesas = {}                                      # (distrito, seccion, circuito, mesa) -> electores
    nombres, vistas, repetidas, filas, ajenas = {}, set(), 0, 0, 0
    z = zipfile.ZipFile(ruta)
    with io.TextIOWrapper(z.open(INTERNO), encoding="utf-8-sig", newline="") as f:
        for r in csv.DictReader(f):
            filas += 1
            if r["cargo_id"] not in CARGOS or r["eleccion_tipo"] != "GENERAL" or r["padron_tipo"] != "NORMAL":
                ajenas += 1
                continue
            d, c = r["distrito_id"], r["cargo_id"]
            nombres[d] = r["distrito_nombre"]
            k = (d, r["seccion_id"], r["circuito_id"], r["mesa_id"], c, r["agrupacion_id"], r["lista_numero"], r["votos_tipo"])
            if k in vistas:
                repetidas += 1
                continue
            vistas.add(k)
            n = int(r["votos_cantidad"] or 0)
            mk = (d, r["seccion_id"], r["circuito_id"], r["mesa_id"])
            e = int(r["mesa_electores"] or 0)
            if mesas.setdefault(mk, e) != e:
                raise SystemExit(f"la mesa {mk} trae dos totales de electores distintos")
            if r["votos_tipo"] == "POSITIVO":
                votos[(d, c, r["agrupacion_id"], r["agrupacion_nombre"])] += n
            else:
                otros[(d, c, r["votos_tipo"])] += n
    if repetidas:
        raise SystemExit(f"{repetidas} filas repetidas en el archivo de origen: no se publica")
    prov = {}
    for d, nom in nombres.items():
        prov[d] = {"distrito_id": int(d), "nombre": nom, "mesas": 0, "electores": 0, "cargos": {}}
    for (d, *_), e in mesas.items():
        prov[d]["mesas"] += 1
        prov[d]["electores"] += e
    for (d, c, aid, an), n in votos.items():
        cg = prov[d]["cargos"].setdefault(c, {"cargo": CARGOS[c], "agrupaciones": [], "otros": {}})
        cg["agrupaciones"].append({"id": aid, "nombre": an, "votos": n})
    for (d, c, t), n in otros.items():
        prov[d]["cargos"].setdefault(c, {"cargo": CARGOS[c], "agrupaciones": [], "otros": {}})["otros"][t] = n
    for p in prov.values():
        for cg in p["cargos"].values():
            cg["agrupaciones"].sort(key=lambda a: (-a["votos"], a["nombre"]))
    salida = {"_meta": {
        "fuente": "Dirección Nacional Electoral, Ministerio del Interior (argentina.gob.ar/dine/resultados-electorales)",
        "pagina": PAGINA, "url_archivo": URL, "archivo": INTERNO,
        "sha256_zip": h.hexdigest(), "bytes_zip": ruta.stat().st_size,
        "eleccion": "Elecciones generales del 22 de octubre de 2023", "escrutinio": "provisorio",
        "bajado": date.today().isoformat(), "filas_leidas": filas, "filas_ajenas": ajenas, "filas_repetidas": repetidas,
    }, "provincias": [prov[d] for d in sorted(prov, key=int)]}
    DEST.parent.mkdir(parents=True, exist_ok=True)
    with gzip.open(DEST, "wt", encoding="utf-8") as f:
        json.dump(salida, f, ensure_ascii=False, separators=(",", ":"))
    print(f"filas {filas}; provincias {len(prov)}; mesas {len(mesas)}; sha256 {h.hexdigest()}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
