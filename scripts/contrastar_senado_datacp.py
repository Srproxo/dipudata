#!/usr/bin/env python3
"""
Contrasta los totales de las votaciones del Senado bajadas de senado.gob.ar con los que publica datacp.ar
(/api/congreso/actas-search). datacp.ar no es fuente primaria: sirve para detectar errores de lectura.
Compara afirmativos, negativos, abstenciones y ausentes por número de acta (el mismo ID que usa senado.gob.ar).

Uso:  python3 -I scripts/contrastar_senado_datacp.py [años separados por coma]   (por defecto, todos)
Escribe el resumen en storage/verificado/contraste_senado_datacp.json
"""
import glob, gzip, json, sys, time, urllib.request
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
API = "https://datacp.ar/api/congreso/actas-search?camara=senadores&anio={a}&limit=50&page={p}"


def pedir(url):
    for i in range(4):
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": "DipuData"}), timeout=60) as r:
                return json.load(r)
        except Exception:
            time.sleep(3 * (i + 1))
    raise RuntimeError(url)


def main() -> int:
    nuestras = {}
    for ruta in glob.glob(str(RAIZ / "storage" / "oficial" / "senado" / "votaciones_*.json.gz")):
        for v in json.load(gzip.open(ruta, "rt", encoding="utf-8")):
            cuenta = [sum(1 for x in v["votos"] if x[4] == c) for c in "ANBU"]
            nuestras[str(v["id"])] = (v["fecha"], cuenta if v["votos"] else [v["afirmativos"], v["negativos"], v["abstenciones"], v["ausentes"]])
    anios = sorted({f[:4] for f, _ in nuestras.values()})
    if len(sys.argv) > 1:
        anios = [a for a in anios if a in sys.argv[1].split(",")]
    iguales, distintas, faltan_alla = 0, [], 0
    for a in anios:
        p, vistas = 1, 0
        while True:
            d = pedir(API.format(a=a, p=p))
            for x in d["actas"]:
                vistas += 1
                k = str(x["actaId"])
                if k not in nuestras:
                    continue
                alla = [x["afirmativos"], x["negativos"], x["abstenciones"], x["ausentes"]]
                if alla == nuestras[k][1]:
                    iguales += 1
                else:
                    distintas.append({"acta": k, "fecha": nuestras[k][0], "senado_gob_ar": nuestras[k][1], "datacp": alla})
            if vistas >= d["total"] or not d["actas"]:
                break
            p += 1
            time.sleep(0.5)
    comparadas = iguales + len(distintas)
    res = {"comparadas": comparadas, "iguales": iguales, "distintas": distintas,
           "solo_en_senado_gob_ar": len([1 for f, _ in nuestras.values() if f[:4] in anios]) - comparadas}
    (RAIZ / "storage" / "verificado" / "contraste_senado_datacp.json").write_text(json.dumps(res, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"{comparadas} votaciones comparadas: {iguales} iguales, {len(distintas)} distintas; "
          f"{res['solo_en_senado_gob_ar']} no figuran en datacp.ar")
    return 0


if __name__ == "__main__":
    sys.exit(main())
