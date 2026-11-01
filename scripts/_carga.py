"""Lectura tolerante de los listados del Senado (JSON con comillas sin escapar y comas finales)."""
import gzip, json, re
from pathlib import Path

SENADO = Path(__file__).resolve().parent.parent / "storage" / "oficial" / "senado"


def filas(nombre: str) -> list:
    t = gzip.open(SENADO / f"{nombre}.json.gz", "rt", encoding="utf-8", errors="replace").read()
    t = re.sub(r'(?m)^(\s*"[^"\n]+":\s*")(.*)("\s*,?\s*)$',
               lambda m: m.group(1) + re.sub(r'(?<!\\)"', '\\"', m.group(2)) + m.group(3), t)
    t = re.sub(r",(\s*[}\]])", r"\1", t)
    d = json.loads(t, strict=False)
    return d["table"]["rows"] if isinstance(d, dict) and "table" in d else d
