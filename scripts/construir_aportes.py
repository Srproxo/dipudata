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
- Los 10 mayores aportantes de cada agrupación: las personas jurídicas con su razón social; las personas humanas sin
  nombre (decisión de Marcelo, 1/11/2026), con el número correlativo que reemplaza su CUIL.

Uso:  python3 -I scripts/construir_aportes.py
"""
import collections, csv, gzip, json, re, unicodedata
from pathlib import Path

def slug(s: str) -> str:
    s = unicodedata.normalize("NFKD", s.lower()).encode("ascii", "ignore").decode()
    return re.sub(r"[^a-z0-9]+", "-", s).strip("-")


RAIZ = Path(__file__).resolve().parent.parent
ORIGEN = RAIZ / "storage" / "oficial" / "cne" / "aportes.csv.gz"
SALIDA = RAIZ / "storage" / "verificado" / "aportes.json"


def main() -> int:
    filas = list(csv.DictReader(gzip.open(ORIGEN, "rt", encoding="utf-8")))
    validas = [r for r in filas if r["anulado"] == "False"]
    for r in validas:  # la fuente escribe a veces "X-Y" y a veces "X - Y" para la misma agrupación
        r["agrupacion"] = re.sub(r"\s*-\s*", " - ", r["agrupacion"])
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
        a = d["ag"].setdefault((r["agrupacion"], r["distrito"]), {"aportes": 0, "total": 0.0, "aportantes": set(), "juridicas": 0.0, "especie": 0.0,
                                                                   "por": collections.defaultdict(lambda: [0.0, 0, "", "", ""])})
        p = a["por"][r["aportante"]]
        p[0] += m; p[1] += 1; p[2] = r["razon_social"]; p[3] = r["tipo"]; p[4] = r["actividad"]
        a["aportes"] += 1; a["total"] += m; a["aportantes"].add(r["aportante"])
        if r["tipo"] == "Jurídica":
            a["juridicas"] += m; d["juridicas"] += m
        if r["modalidad"] == "ESPECIE":
            a["especie"] += m
    destinos = []
    for d in dest.values():
        anios = re.findall(r"\d{4}", d["nombre"])
        ags = []
        for (n, dis), a in d["ag"].items():
            top = sorted(a["por"].items(), key=lambda kv: (-kv[1][0], int(kv[0])))[:10]
            ags.append({"nombre": n, "distrito": dis, "slug": slug(n + " " + dis),
                        "total": round(a["total"], 2), "aportes": a["aportes"], "aportantes": len(a["aportantes"]),
                        "juridicas": round(a["juridicas"], 2), "especie": round(a["especie"], 2),
                        "mayores": [[v[2] if v[3] == "Jurídica" else None, v[4] or None, round(v[0], 2), v[1], k if v[3] == "Jurídica" else None] for k, v in top]})
        if len({x["slug"] for x in ags}) != len(ags):
            raise SystemExit(f"slugs repetidos en {d['nombre']}")
        ags.sort(key=lambda x: (-x["total"], x["nombre"], x["distrito"]))
        destinos.append({"cod": d["cod"], "nombre": d["nombre"], "anio": int(anios[-1]) if anios else 0,
                         "tipo": "institucional" if d["nombre"].startswith("DESENVOLVIMIENTO") else "campaña",
                         "desde": d["desde"], "hasta": d["hasta"], "aportes": d["aportes"], "total": round(d["total"], 2),
                         "aportantes": len(d["aportantes"]), "juridicas": round(d["juridicas"], 2), "negativos": d["negativos"],
                         "agrupaciones": ags})
    destinos.sort(key=lambda x: (-x["anio"], x["tipo"] != "campaña", -x["cod"]))

    # Personas jurídicas: a qué agrupaciones aportaron (todas, no solo el top 10), por destino.
    emp = {}
    for r in validas:
        if r["tipo"] != "Jurídica":
            continue
        e = emp.setdefault(r["aportante"], {"nombre": r["razon_social"], "actividad": r["actividad"], "total": 0.0, "aportes": 0, "ag": {}})
        m = float(r["monto"])
        e["total"] += m; e["aportes"] += 1
        g = e["ag"].setdefault((r["agrupacion"], r["distrito"], r["destino"]), [0.0, 0, r["fecha"]])
        g[0] += m; g[1] += 1; g[2] = max(g[2], r["fecha"])
    empresas, usados, id_slug = [], set(), {}
    for k, e in sorted(emp.items(), key=lambda kv: (-len({a[0] for a in kv[1]["ag"]}), -kv[1]["total"], kv[1]["nombre"])):
        base = slug(e["nombre"]) or "empresa"
        sl = base if base not in usados else f"{base}-{k}"
        usados.add(sl)
        filas_ag = sorted(([n, dis, dest, round(v[0], 2), v[1], v[2]] for (n, dis, dest), v in e["ag"].items()), key=lambda x: (x[5], x[0]), reverse=True)
        id_slug[k] = sl
        empresas.append({"slug": sl, "nombre": e["nombre"], "actividad": e["actividad"] or None, "total": round(e["total"], 2),
                         "aportes": e["aportes"], "agrupaciones": len({a[0] for a in filas_ag}), "detalle": filas_ag})
    for d in destinos:  # el número correlativo de una persona jurídica se reemplaza por el enlace a su página
        for a in d["agrupaciones"]:
            for x in a["mayores"]:
                x[4] = id_slug.get(x[4]) if x[4] else None
    salida = {"_meta": {"fuente": "Cámara Nacional Electoral, registro de aportantes (aportantes.electoral.gob.ar)",
                        "aportes": len(filas), "anulados": len(filas) - len(validas),
                        "rectificados": sum(1 for r in validas if r["rectificado"] == "True"),
                        "negativos": sum(1 for r in validas if float(r["monto"]) < 0),
                        "repetidos": sum(v - 1 for v in claves.values() if v > 1),
                        "desde": min(r["fecha"] for r in validas), "hasta": max(r["fecha"] for r in validas)},
              "destinos": destinos, "empresas": empresas}
    SALIDA.write_text(json.dumps(salida, ensure_ascii=False, separators=(",", ":")) + "\n", encoding="utf-8")
    m = salida["_meta"]
    print(f"{m['aportes']} aportes ({m['anulados']} anulados, {m['repetidos']} filas repetidas); {len(destinos)} destinos; {len(empresas)} personas jurídicas")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
