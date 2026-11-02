#!/usr/bin/env python3
"""
Ubicación de los lugares de origen y destino de los pasajes de Diputados, desde el Servicio de Normalización de Datos
Geográficos de Argentina (Georef, apis.datos.gob.ar, con datos del IGN y del INDEC). Genera:
  storage/oficial/georef/provincias.geojson.gz         límites provinciales del IGN, tal como se bajaron
  storage/oficial/georef/localidades_pasajes.json.gz   {localidad: [coincidencias exactas de Georef]} para cada
                                                       localidad nombrada en scripts/lugares_pasajes.csv

El archivo de pasajes de la Cámara nombra aeropuertos y terminales, no coordenadas: scripts/lugares_pasajes.csv
(editable) dice a qué localidad corresponde cada uno. Después: construir_rutas_pasajes.py.
Uso:  python3 -I scripts/bajar_georef.py
"""
import csv, gzip, json, time, urllib.parse, urllib.request
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
DEST = RAIZ / "storage" / "oficial" / "georef"
PROVINCIAS = "https://infra.datos.gob.ar/georef/provincias.geojson"
LOCALIDADES = "https://apis.datos.gob.ar/georef/api/localidades"  # y, si no hay coincidencia, localidades-censales
CAMPOS = "id,nombre,categoria,provincia.nombre,departamento.nombre,centroide"


def bajar(url: str) -> bytes:
    for intento in range(4):
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": "DipuData"}), timeout=90) as r:
                return r.read()
        except OSError:
            if intento == 3:
                raise
            time.sleep(2 ** (intento + 1))
    raise RuntimeError(url)


def main() -> int:
    DEST.mkdir(parents=True, exist_ok=True)
    crudo = bajar(PROVINCIAS)
    if len(json.loads(crudo)["features"]) != 24:
        raise SystemExit("el archivo de provincias no trae 24 jurisdicciones")
    with gzip.open(DEST / "provincias.geojson.gz", "wb") as f:
        f.write(crudo)
    with open(RAIZ / "scripts" / "lugares_pasajes.csv", encoding="utf-8") as f:
        nombres = sorted({r["localidad"] for r in csv.DictReader(f) if r["localidad"]})
    salida = {}
    for n in nombres:
        q = urllib.parse.urlencode({"nombre": n, "exacto": "true", "max": 100, "campos": CAMPOS})
        salida[n] = json.loads(bajar(f"{LOCALIDADES}?{q}"))["localidades"]
        if not salida[n]:  # algunas (Campana, Lezama) figuran solo como localidad censal
            salida[n] = json.loads(bajar(f"{LOCALIDADES}-censales?{q}"))["localidades_censales"]
        time.sleep(0.2)
    with gzip.open(DEST / "localidades_pasajes.json.gz", "wt", encoding="utf-8") as f:
        json.dump({"_meta": {"fuente": "Georef (apis.datos.gob.ar/georef), localidades del INDEC/IGN", "url": LOCALIDADES},
                   "localidades": salida}, f, ensure_ascii=False)
    sin = [n for n, v in salida.items() if not v]
    print(f"provincias: 24; localidades consultadas: {len(salida)}; sin coincidencia exacta: {sin}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
