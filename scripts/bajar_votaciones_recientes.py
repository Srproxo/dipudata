#!/usr/bin/env python3
"""
Descarga de ArgentinaDatos (api.argentinadatos.com, réplica de las votaciones publicadas por la HCDN y el Senado)
las votaciones nominales del mandato actual y las guarda en storage/oficial/argentinadatos/.

Solo se guardan las votaciones desde el 10/12/2025. Las actas anteriores de ArgentinaDatos no se usan: para los
períodos 129 a 137 vienen en cero y para el resto la cobertura es parcial (la fuente oficial de la HCDN llega hasta 2020).

Uso:  python3 -I scripts/bajar_votaciones_recientes.py
"""
import gzip, json, sys, urllib.request
from pathlib import Path

DESDE = "2025-12-10"
DEST = Path(__file__).resolve().parent.parent / "storage" / "oficial" / "argentinadatos"


def bajar(ruta: str):
    req = urllib.request.Request(f"https://api.argentinadatos.com/v1/{ruta}", headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(req, timeout=180) as r:
        return json.load(r)


def main() -> int:
    DEST.mkdir(parents=True, exist_ok=True)
    for ruta, nombre in (("diputados/actas", "diputados_actas"), ("senado/actas", "senado_actas")):
        datos = [a for a in bajar(ruta) if (a.get("fecha") or "") >= DESDE]
        with gzip.open(DEST / f"{nombre}_desde_{DESDE}.json.gz", "wt", encoding="utf-8") as f:
            json.dump(datos, f, ensure_ascii=False, separators=(",", ":"))
        print(f"{nombre}: {len(datos)} votaciones desde {DESDE}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
