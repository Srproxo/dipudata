#!/usr/bin/env python3
"""
Contrasta los totales de las votaciones del sitio con la segunda fuente: "leyes clave" de DataCP (datacp.ar, CC BY 4.0, un
trabajo de la UBA; no es una fuente oficial). Solo cubre votaciones de leyes clave y solo trae totales, no el voto de cada legislador.

Cruce: cámara + número de acta (Senado: número de detalleActa; Diputados: acta de la HCDN). Si la fecha o algún total difiere se
lista; el sitio publica lo que dice la cabecera oficial y lo confirma con el recuento voto por voto.

Entrada:  storage/oficial/datacp/leyes_clave_resultados.json.gz   Salida: storage/verificado/contraste_datacp.json
Uso:  python3 -I scripts/contrastar_datacp.py   (después de construir_votaciones.py)
"""
import collections, gzip, json
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
SAL = RAIZ / "storage" / "verificado"


def main() -> int:
    idx = {v["id"]: v for v in json.loads((SAL / "votaciones" / "indice.json").read_text(encoding="utf-8"))}
    with gzip.open(RAIZ / "storage" / "oficial" / "datacp" / "leyes_clave_resultados.json.gz", "rt", encoding="utf-8") as f:
        d = json.load(f)
    rows = [dict(zip(d["headers"], r)) for r in d["rows"]]
    cuenta = collections.Counter()
    difieren, faltan = [], collections.Counter()
    for r in rows:
        cam = "S" if r["camara"] == "senadores" else "D"
        k = ("S" if cam == "S" else "") + r["numero_acta"]
        v = idx.get(k)
        if cam == "S" and r["fecha"] < "2005-01-01":
            continue
        if not v or v["c"] != cam:
            if cam == "D" and "2020-02-01" <= r["fecha"] <= "2025-12-09":
                faltan[r["fecha"][:4]] += 1  # el hueco de Diputados 2020-2025
            cuenta[cam + "_sin_equivalente"] += 1
            continue
        cuenta[cam + "_comparadas"] += 1
        mios = [v["a"], v["n"], v["b"], v["u"]]
        suyos = [r["afirmativos"], r["negativos"], r["abstenciones"], r["ausentes"]]
        if v["f"] != r["fecha"] or mios != suyos:
            cuenta[cam + "_difieren"] += 1
            difieren.append({"camara": "Senado" if cam == "S" else "Diputados", "acta": r["numero_acta"], "ley": r["ley_label"], "fecha_sitio": v["f"], "fecha_datacp": r["fecha"],
                             "sitio": mios, "datacp": suyos, "suma_sitio": sum(mios), "suma_datacp": sum(suyos)})
    salida = {"fuente": d.get("fuente"), "resumen": dict(cuenta), "diputados_2020_2025_sin_votaciones_en_el_sitio_por_anio": dict(sorted(faltan.items())),
              "difieren": difieren}
    (SAL / "contraste_datacp.json").write_text(json.dumps(salida, ensure_ascii=False, indent=1), encoding="utf-8")
    print(dict(cuenta), "| Diputados 2020-2025 de DataCP sin equivalente:", dict(faltan))
    for x in difieren:
        print("  ", x["camara"], x["acta"], x["fecha_sitio"], x["sitio"], "vs", x["datacp"], f'(suman {x["suma_sitio"]} y {x["suma_datacp"]})')
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
