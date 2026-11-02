#!/usr/bin/env python3
"""
Reduce el archivo anual de declaraciones juradas de la Oficina Anticorrupción (66.000 funcionarios) a las
filas de legisladores nacionales, SIN la columna CUIT, y lo guarda en storage/oficial/ddjj/.
Así el repositorio no conserva datos de personas que el sitio no usa.

Se toman dos clases de filas:
  - organismo = Cámara de Diputados o Senado de la Nación y cargo que empieza con "diputad" o "senador";
  - cualquier otra fila cuyo cargo sea de legislador nacional ("Diputado nacional", "Senadora de la Nación", "Diputada
    nacional titular", "dipurado nacional", "Diputado nacional por la provincia de..."). La Oficina Anticorrupción
    publica así buena parte de las declaraciones de legisladores: con el organismo vacío o con el de su actividad
    anterior (una empresa, una fundación, una legislatura). Antes se descartaban y faltaban, por ejemplo, 77
    legisladores con mandato desde 2023 o antes. No se toman cargos de candidato ni de suplente, ni "diputado" a secas
    fuera del Congreso (puede ser provincial).

Uso:  python3 -I scripts/filtrar_ddjj.py  declaraciones-juradas-2024-consolidado-al-20251222.csv
"""
import collections, csv, gzip, re, sys
from pathlib import Path

DEST = Path(__file__).resolve().parent.parent / "storage" / "oficial" / "ddjj"
ORG = {"HONORABLE CAMARA DE DIPUTADOS DE LA NACION", "HONORABLE SENADO DE LA NACION"}
CARGO_NACIONAL = re.compile(r"^(diputad[oa]|dipurad[oa]|senador[a]?)\s+(nacional|de\s+la\s+naci[oó]n)(\s+titular)?(\s+por\s+.+)?$", re.I)


def es_legislador(r) -> bool:
    cargo = re.sub(r"\s+", " ", (r.get("cargo") or "").strip(" :.-\t"))
    org = (r.get("organismo") or "").strip()
    if org in ORG:
        return bool(re.match(r"^(diputad|senador)", cargo, re.I))
    return bool(CARGO_NACIONAL.match(cargo))


def main(ruta: str) -> int:
    csv.field_size_limit(10**8)
    with open(ruta, encoding="utf-8-sig", newline="") as fh:
        lector = csv.DictReader(fh)
        cols = [c for c in lector.fieldnames if c != "cuit"]
        filas = [r for r in lector if None not in r.values() and es_legislador(r)]
    anio = collections.Counter(r["anio"] for r in filas).most_common(1)[0][0]
    DEST.mkdir(parents=True, exist_ok=True)
    salida = DEST / f"declaraciones_juradas_{anio}.csv.gz"
    with gzip.open(salida, "wt", encoding="utf-8", newline="") as out:
        w = csv.DictWriter(out, fieldnames=cols, extrasaction="ignore", lineterminator="\n")
        w.writeheader()
        w.writerows(filas)
    fuera = sum(1 for r in filas if (r.get("organismo") or "").strip() not in ORG)
    print(f"{len(filas)} filas de legisladores ({fuera} sin el organismo del Congreso), año {anio} -> {salida}")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1]))
