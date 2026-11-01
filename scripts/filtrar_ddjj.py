#!/usr/bin/env python3
"""
Reduce el archivo anual de declaraciones juradas de la Oficina Anticorrupción (66.000 funcionarios) a las
filas de legisladores nacionales, SIN la columna CUIT, y lo guarda en storage/oficial/ddjj/.
Así el repositorio no conserva datos de personas que el sitio no usa.

Uso:  python3 -I scripts/filtrar_ddjj.py  declaraciones-juradas-2024-consolidado-al-20251222.csv
"""
import collections, csv, gzip, re, sys
from pathlib import Path

DEST = Path(__file__).resolve().parent.parent / "storage" / "oficial" / "ddjj"
ORG = {"HONORABLE CAMARA DE DIPUTADOS DE LA NACION", "HONORABLE SENADO DE LA NACION"}


def main(ruta: str) -> int:
    csv.field_size_limit(10**8)
    with open(ruta, encoding="utf-8-sig", newline="") as fh:
        lector = csv.DictReader(fh)
        cols = [c for c in lector.fieldnames if c != "cuit"]
        filas = [r for r in lector if None not in r.values() and r.get("organismo") in ORG
                 and re.match(r"^(diputad|senador)", (r.get("cargo") or "").strip(), re.I)]
    anio = collections.Counter(r["anio"] for r in filas).most_common(1)[0][0]
    DEST.mkdir(parents=True, exist_ok=True)
    salida = DEST / f"declaraciones_juradas_{anio}.csv.gz"
    with gzip.open(salida, "wt", encoding="utf-8", newline="") as out:
        w = csv.DictWriter(out, fieldnames=cols, extrasaction="ignore", lineterminator="\n")
        w.writeheader()
        w.writerows(filas)
    print(f"{len(filas)} filas de legisladores, año {anio} -> {salida}")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1]))
