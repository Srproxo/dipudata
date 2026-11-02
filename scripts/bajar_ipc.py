#!/usr/bin/env python3
"""
Descarga el Índice de Precios al Consumidor (IPC) Nivel General Nacional del INDEC, base diciembre 2016 = 100, desde la
API de Series de Tiempo de datos.gob.ar (serie 148.3_INIVELNAL_DICI_M_26), y genera:
  storage/oficial/indec/ipc_nacional.json.gz   la respuesta tal como se bajó
  storage/verificado/ipc.json                  {"_meta": {...}, "mensual": {"AAAA-MM": índice}}

Se usa para expresar montos de las declaraciones juradas en pesos de un mismo mes (decisión de Marcelo, 1/11/2026).
La serie oficial empieza en diciembre de 2016: los años fiscales anteriores no se ajustan.

Uso:  python3 -I scripts/bajar_ipc.py
"""
import gzip, json, urllib.request
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
SERIE = "148.3_INIVELNAL_DICI_M_26"
URL = f"https://apis.datos.gob.ar/series/api/series/?ids={SERIE}&format=json&limit=1000"


def main() -> int:
    with urllib.request.urlopen(urllib.request.Request(URL, headers={"User-Agent": "DipuData"}), timeout=60) as r:
        crudo = r.read()
    d = json.loads(crudo)
    mensual = {f[:7]: v for f, v in d["data"] if v is not None}
    meses = sorted(mensual)
    if mensual.get("2016-12") != 100.0 or any(mensual[a] <= 0 for a in meses):
        raise SystemExit("la serie no tiene base diciembre 2016 = 100 o trae valores no positivos")
    (RAIZ / "storage" / "oficial" / "indec").mkdir(parents=True, exist_ok=True)
    with gzip.open(RAIZ / "storage" / "oficial" / "indec" / "ipc_nacional.json.gz", "wb") as f:
        f.write(crudo)
    salida = {"_meta": {"fuente": "INDEC, IPC Nivel General Nacional, base diciembre 2016 = 100 (datos.gob.ar, serie " + SERIE + ")",
                        "url": URL, "desde": meses[0], "hasta": meses[-1]}, "mensual": mensual}
    (RAIZ / "storage" / "verificado" / "ipc.json").write_text(json.dumps(salida, ensure_ascii=False, separators=(",", ":")) + "\n", encoding="utf-8")
    print(f"IPC {meses[0]} a {meses[-1]} ({len(meses)} meses)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
