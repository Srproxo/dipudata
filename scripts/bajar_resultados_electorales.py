#!/usr/bin/env python3
"""
Resultados de elecciones nacionales (Diputados y Senadores nacionales) por provincia, desde los archivos oficiales de la
Dirección Nacional Electoral (Ministerio del Interior, argentina.gob.ar/dine/resultados-electorales/elecciones-AAAA).
Cada archivo (12 a 35 MB comprimido, hasta ~1,3 GB descomprimido, escrutinio PROVISORIO a nivel de mesa) se lee en streaming y no
se guarda. Se guarda solo el agregado por provincia, con la huella (sha256) del archivo de origen:
  storage/oficial/dine/<clave>_provincia.json.gz     (clave: generales_2023, paso_2021, generales_2021, ...)

Los archivos traen también cargos provinciales y municipales: se toman únicamente "DIPUTADOS NACIONALES" y "SENADORES NACIONALES"
(por nombre del cargo, que en 2017 va en singular). Las columnas también cambian de orden y de mayúsculas: se leen por nombre.
En las PASO cada agrupación suma los votos de todas sus listas internas; los resultados no dicen qué lista ganó la interna.

Controles al leer: ninguna fila repetida (mismo mesa/cargo/agrupación/lista/tipo de voto); cada mesa trae un único total de electores;
solo mesas de padrón común (NORMAL). Si alguno falla no se escribe nada. Se registran los valores de tipo de elección y de padrón que
aparecieron, para poder auditar qué se dejó afuera.

Uso:  python3 -I scripts/bajar_resultados_electorales.py <clave> [ruta_al_zip]
      python3 -I scripts/bajar_resultados_electorales.py --lista
"""
import collections, csv, gzip, hashlib, io, json, sys, unicodedata, urllib.request, zipfile
from datetime import date
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
BASE = "https://www.argentina.gob.ar/sites/default/files/"
CARGOS = {"DIPUTADOS NACIONALES": "3", "SENADORES NACIONALES": "2", "DIPUTADO NACIONAL": "3", "SENADOR NACIONAL": "2"}  # 2017 usa el singular
NOMBRE_CARGO = {"3": "Diputados nacionales", "2": "Senadores nacionales"}
# clave -> (zip, archivo interno, año, tipo, fecha, etiqueta, página oficial)
ELECCIONES = {
    "generales_2023": ("2023_generales_1.zip", "2023_Generales/ResultadoElectorales_2023_Generales.csv", 2023, "GENERAL", "2023-10-22", "Elecciones generales del 22 de octubre de 2023", 2023),
    "generales_2021": ("2021-provisorios_generales.zip", "2021_Generales/ResultadosElectorales.csv", 2021, "GENERAL", "2021-11-14", "Elecciones generales del 14 de noviembre de 2021", 2021),
    "paso_2021": ("2021-provisorios_paso.zip", "2021_PASO/ResultadosElectorales.csv", 2021, "PASO", "2021-09-12", "PASO del 12 de septiembre de 2021", 2021),
    "generales_2019": ("2019-provisorios_generales.zip", "2019_Generales/ResultadosElectorales.csv", 2019, "GENERAL", "2019-10-27", "Elecciones generales del 27 de octubre de 2019", 2019),
    "paso_2019": ("2019-provisorios_paso.zip", "2019_PASO/ResultadosElectorales.csv", 2019, "PASO", "2019-08-11", "PASO del 11 de agosto de 2019", 2019),
    "generales_2017": ("2017-provisorios_generales.zip", "2017_Generales/ResultadosElectorales.csv", 2017, "GENERAL", "2017-10-22", "Elecciones generales del 22 de octubre de 2017", 2017),
    "paso_2017": ("2017-provisorios_paso.zip", "2017_PASO/ResultadosElectorales.csv", 2017, "PASO", "2017-08-13", "PASO del 13 de agosto de 2017", 2017),
}


def sin_acentos(s: str) -> str:
    return "".join(c for c in unicodedata.normalize("NFD", s) if unicodedata.category(c) != "Mn")


def conseguir_zip(nombre, arg):
    if arg:
        return Path(arg)
    ruta = RAIZ / "storage" / "cache" / nombre
    if not ruta.exists():
        ruta.parent.mkdir(parents=True, exist_ok=True)
        req = urllib.request.Request(BASE + nombre, headers={"User-Agent": "DipuData"})
        with urllib.request.urlopen(req, timeout=300) as r, open(ruta, "wb") as f:
            while (b := r.read(1 << 20)):
                f.write(b)
    return ruta


def main() -> int:
    if len(sys.argv) < 2 or sys.argv[1] == "--lista" or sys.argv[1] not in ELECCIONES:
        print("claves:", ", ".join(ELECCIONES))
        return 0 if len(sys.argv) > 1 and sys.argv[1] == "--lista" else 1
    clave = sys.argv[1]
    nombre_zip, interno, anio, tipo, fecha, etiqueta, pagina_anio = ELECCIONES[clave]
    ruta = conseguir_zip(nombre_zip, sys.argv[2] if len(sys.argv) > 2 else None)
    h = hashlib.sha256()
    with open(ruta, "rb") as f:
        while (b := f.read(1 << 20)):
            h.update(b)
    votos = collections.defaultdict(int)            # (distrito, cargo, agrupacion_id, nombre) -> votos positivos
    otros = collections.defaultdict(int)            # (distrito, cargo, tipo) -> votos
    mesas = {}                                      # (distrito, seccion, circuito, mesa) -> electores
    nombres, vistas, repetidas, filas, ajenas = {}, set(), 0, 0, 0
    vistos = collections.Counter()                  # (tipo de elección, padrón, cargo) -> filas, para auditar
    z = zipfile.ZipFile(ruta)
    with io.TextIOWrapper(z.open(interno), encoding="utf-8-sig", newline="") as f:
        lector = csv.reader(f)
        cab = [c.strip().lower() for c in next(lector)]
        ix = {c: i for i, c in enumerate(cab)}
        falta = {"eleccion_tipo", "padron_tipo", "distrito_id", "distrito_nombre", "seccion_id", "circuito_id", "mesa_id", "mesa_electores",
                 "cargo_nombre", "agrupacion_id", "agrupacion_nombre", "lista_numero", "votos_tipo", "votos_cantidad"} - set(ix)
        if falta:
            raise SystemExit(f"faltan columnas en el archivo: {sorted(falta)}")
        g = lambda r, c: r[ix[c]]
        for r in lector:
            filas += 1
            cargo_n = sin_acentos(g(r, "cargo_nombre")).strip().upper()
            et = g(r, "eleccion_tipo").strip().upper()
            if cargo_n in CARGOS:
                vistos[(et, g(r, "padron_tipo").strip().upper(), cargo_n)] += 1
            es_tipo = et.startswith("PASO") if tipo == "PASO" else et.startswith("GENERAL")
            if cargo_n not in CARGOS or not es_tipo or g(r, "padron_tipo").strip().upper() != "NORMAL":
                ajenas += 1
                continue
            c, d = CARGOS[cargo_n], g(r, "distrito_id").strip()
            nombres[d] = g(r, "distrito_nombre").strip()
            k = (d, g(r, "seccion_id"), g(r, "circuito_id").strip(), g(r, "mesa_id"), c, g(r, "agrupacion_id"), g(r, "lista_numero"), g(r, "votos_tipo"))
            if k in vistas:
                repetidas += 1
                continue
            vistas.add(k)
            n = int(g(r, "votos_cantidad") or 0)
            mk = (d, g(r, "seccion_id"), g(r, "circuito_id").strip(), g(r, "mesa_id"))
            e = int(g(r, "mesa_electores") or 0)
            if mesas.setdefault(mk, e) != e:
                raise SystemExit(f"la mesa {mk} trae dos totales de electores distintos")
            if g(r, "votos_tipo").strip().upper() == "POSITIVO":
                votos[(d, c, g(r, "agrupacion_id").strip(), g(r, "agrupacion_nombre").strip())] += n
            else:
                otros[(d, c, g(r, "votos_tipo").strip().upper())] += n
    if repetidas:
        raise SystemExit(f"{repetidas} filas repetidas en el archivo de origen: no se publica")
    prov = {}
    for d, nom in nombres.items():
        prov[d] = {"distrito_id": int(d), "nombre": nom, "mesas": 0, "electores": 0, "cargos": {}}
    for (d, *_), e in mesas.items():
        prov[d]["mesas"] += 1
        prov[d]["electores"] += e
    for (d, c, aid, an), n in votos.items():
        cg = prov[d]["cargos"].setdefault(c, {"cargo": NOMBRE_CARGO[c], "agrupaciones": [], "otros": {}})
        cg["agrupaciones"].append({"id": aid, "nombre": an, "votos": n})
    for (d, c, t), n in otros.items():
        prov[d]["cargos"].setdefault(c, {"cargo": NOMBRE_CARGO[c], "agrupaciones": [], "otros": {}})["otros"][t] = n
    for p in prov.values():
        for cg in p["cargos"].values():
            cg["agrupaciones"].sort(key=lambda a: (-a["votos"], a["nombre"]))
    salida = {"_meta": {
        "clave": clave, "anio": anio, "tipo": tipo, "fecha": fecha,
        "fuente": "Dirección Nacional Electoral, Ministerio del Interior (argentina.gob.ar/dine/resultados-electorales)",
        "pagina": f"https://www.argentina.gob.ar/dine/resultados-electorales/elecciones-{pagina_anio}",
        "url_archivo": BASE + nombre_zip, "archivo": interno,
        "sha256_zip": h.hexdigest(), "bytes_zip": ruta.stat().st_size,
        "eleccion": etiqueta, "escrutinio": "provisorio",
        "bajado": date.today().isoformat(), "filas_leidas": filas, "filas_ajenas": ajenas, "filas_repetidas": repetidas,
        "visto_en_cargos_nacionales": {" | ".join(k): v for k, v in sorted(vistos.items())},
    }, "provincias": [prov[d] for d in sorted(prov, key=int)]}
    destino = RAIZ / "storage" / "oficial" / "dine" / f"{clave}_provincia.json.gz"
    destino.parent.mkdir(parents=True, exist_ok=True)
    with gzip.open(destino, "wt", encoding="utf-8") as f:
        json.dump(salida, f, ensure_ascii=False, separators=(",", ":"))
    print(f"{clave}: filas {filas}; provincias {len(prov)}; mesas {len(mesas)}; sha256 {h.hexdigest()[:16]}…")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
