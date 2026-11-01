#!/usr/bin/env python3
"""
Construye storage/verificado/aportes.json: cuánto recibió cada agrupación política en aportes privados declarados ante la
Cámara Nacional Electoral, por destino (campaña o desenvolvimiento institucional) y distrito.

Fuente: storage/oficial/cne/aportes.csv.gz (filtrar_aportes.py; registro de aportantes de la CNE, sin datos personales).
Criterios:
- Los aportes anulados por la CNE no se cuentan. Los rectificados se cuentan con los valores corregidos que publica.
- Los montos negativos (devoluciones registradas) se suman tal como figuran y se informan aparte.
- Filas idénticas (misma fecha, aportante, agrupación, destino, modalidad y monto) se cuentan: la CNE marca como "Anulado"
  los duplicados que detecta y el resto puede ser más de un pago en el día. Se informan en _meta.
- No se publica quién aportó: solo totales, cantidad de aportes y de aportantes distintos.

Uso:  python3 -I scripts/construir_aportes.py
"""
import collections, csv, gzip, json, re
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
ORIGEN = RAIZ / "storage" / "oficial" / "cne" / "aportes.csv.gz"
SALIDA = RAIZ / "storage" / "verificado" / "aportes.json"


def main() -> int:
    filas = list(csv.DictReader(gzip.open(ORIGEN, "rt", encoding="utf-8")))
    validas = [r for r in filas if r["anulado"] == "False"]
    claves = collections.Counter((r["fecha"], r["aportante"], r["agrupacion"], r["destino"], r["modalidad"], r["monto"]) for r in validas)
    dest = {}
    for r in validas:
        d = dest.setdefault(int(r["cod_destino"]), {"cod": int(r["cod_destino"]), "nombre": r["destino"], "aportes": 0, "total": 0.0,
                                                    "aportantes": set(), "juridicas": 0.0, "negativos": 0, "desde": r["fecha"], "hasta": r["fecha"],
                                                    "ag": {}})
        if d["nombre"] != r["destino"]:
            raise SystemExit(f"el código de destino {r['cod_destino']} tiene dos nombres")
        m = float(r["monto"])
        d["aportes"] += 1; d["total"] += m; d["aportantes"].add(r["aportante"])
        d["desde"] = min(d["desde"], r["fecha"]); d["hasta"] = max(d["hasta"], r["fecha"])
        if m < 0: d["negativos"] += 1
        a = d["ag"].setdefault((r["agrupacion"], r["distrito"]), {"aportes": 0, "total": 0.0, "aportantes": set(), "juridicas": 0.0, "especie": 0.0})
        a["aportes"] += 1; a["total"] += m; a["aportantes"].add(r["aportante"])
        if r["tipo"] == "Jurídica":
            a["juridicas"] += m; d["juridicas"] += m
        if r["modalidad"] == "ESPECIE":
            a["especie"] += m
    destinos = []
    for d in dest.values():
        anios = re.findall(r"\d{4}", d["nombre"])
        ags = [{"nombre": n, "distrito": dis, "total": round(a["total"], 2), "aportes": a["aportes"], "aportantes": len(a["aportantes"]),
                "juridicas": round(a["juridicas"], 2), "especie": round(a["especie"], 2)}
               for (n, dis), a in d["ag"].items()]
        ags.sort(key=lambda x: (-x["total"], x["nombre"], x["distrito"]))
        destinos.append({"cod": d["cod"], "nombre": d["nombre"], "anio": int(anios[-1]) if anios else 0,
                         "tipo": "institucional" if d["nombre"].startswith("DESENVOLVIMIENTO") else "campaña",
                         "desde": d["desde"], "hasta": d["hasta"], "aportes": d["aportes"], "total": round(d["total"], 2),
                         "aportantes": len(d["aportantes"]), "juridicas": round(d["juridicas"], 2), "negativos": d["negativos"],
                         "agrupaciones": ags})
    destinos.sort(key=lambda x: (-x["anio"], x["tipo"] != "campaña", -x["cod"]))
    salida = {"_meta": {"fuente": "Cámara Nacional Electoral, registro de aportantes (aportantes.electoral.gob.ar)",
                        "aportes": len(filas), "anulados": len(filas) - len(validas),
                        "rectificados": sum(1 for r in validas if r["rectificado"] == "True"),
                        "negativos": sum(1 for r in validas if float(r["monto"]) < 0),
                        "repetidos": sum(v - 1 for v in claves.values() if v > 1),
                        "desde": min(r["fecha"] for r in validas), "hasta": max(r["fecha"] for r in validas)},
              "destinos": destinos}
    SALIDA.write_text(json.dumps(salida, ensure_ascii=False, separators=(",", ":")) + "\n", encoding="utf-8")
    m = salida["_meta"]
    print(f"{m['aportes']} aportes ({m['anulados']} anulados, {m['repetidos']} filas repetidas); {len(destinos)} destinos")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
