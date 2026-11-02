#!/usr/bin/env python3
"""
Valida las votaciones bajadas de votaciones.hcdn.gob.ar (scripts/bajar_votaciones_hcdn.py) antes de usarlas.

Controles:
  1. Cabecera contra detalle: los totales de cada votación deben coincidir con el recuento voto por voto
     (afirmativos, negativos, abstenciones y ausentes; "sin votar" se compara con los S, con o sin quien preside y las vacantes).
     Las bancas "pendientes de incorporación" (V) no son legisladores y no cuentan.
  2. Filas raras: sin código de diputado, sin nombre, repetidas o con un voto que no se reconoce.
  3. Índice contra página: misma fecha y mismo resultado en la portada y en el detalle.
  4. Intérprete contra datos.hcdn.gob.ar: la muestra del período 137 debe dar los mismos totales y los mismos votos
     que actas_cabecera_137 / actas_detalle_137 (archivo oficial ya publicado).
  5. Contraste con las votaciones actuales (ArgentinaDatos, desde el 10/12/2025): mismas votaciones, totales y votos
     (se cruza por número de acta y por el código del diputado, que ArgentinaDatos trae en la URL de la foto).
  6. Cobertura: votaciones por período y huecos en la numeración de actas de cada reunión.

Escribe storage/oficial/hcdn/votaciones_recinto/validacion.json y un resumen por pantalla. No publica nada.

Uso:  python3 -I scripts/validar_votaciones_hcdn.py
"""
import collections, csv, glob, gzip, json, re, sys, unicodedata
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
DIR = RAIZ / "storage" / "oficial" / "hcdn" / "votaciones_recinto"
OFICIAL = RAIZ / "storage" / "oficial" / "hcdn" / "votaciones"
AD = RAIZ / "storage" / "oficial" / "argentinadatos"
COD_AD = {"afirmativo": "A", "negativo": "N", "abstencion": "B", "ausente": "U", "presidente": "P"}
COD_OF = {"AFIRMATIVO": "A", "NEGATIVO": "N", "ABSTENCION": "B", "AUSENTE": "U", "PRESIDENTE": "P"}


def norm(s: str) -> str:
    s = "".join(c for c in unicodedata.normalize("NFD", s or "") if unicodedata.category(c) != "Mn").lower()
    return re.sub(r"\s+", " ", re.sub(r"[^a-z0-9 ]", " ", s)).strip()


def clave_nombre(s: str) -> str:
    return " ".join(sorted(norm(s).split()))


def distrito(s: str) -> str:
    n = norm(s).replace(" ", "")
    return "caba" if n in ("caba", "ciudaddebuenosaires", "ciudadautonomadebuenosaires") else norm(s)


def leer_csv(patron):
    for f in sorted(glob.glob(str(OFICIAL / patron))):
        with gzip.open(f, "rt", encoding="utf-8-sig", newline="") as fh:
            yield from csv.DictReader(fh)


def main() -> int:
    with gzip.open(DIR / "votaciones_hcdn.json.gz", "rt", encoding="utf-8") as f:
        datos = json.load(f)
    vs = datos["votaciones"]
    por_id = {v["id"]: v for v in vs}
    inf = {"pendientes": datos["pendientes"], "sin_interpretar": datos["sin_interpretar"]}

    # 1 y 2. Cabecera contra detalle y filas raras.
    no_cuadran, filas_raras = [], []
    for v in vs:
        c = collections.Counter(x[4] for x in v["votos"])
        rec = {"afirmativos": c["A"], "negativos": c["N"], "abstenciones": c["B"], "ausentes": c["U"]}
        dif = {k: (v[k], rec[k]) for k in rec if v[k] != rec[k]}
        # "Sin votar" a veces incluye a quien preside y a las bancas vacantes y a veces no: vale cualquiera de esas cuentas.
        if v["sin_votar"] is not None and v["sin_votar"] not in (c["S"], c["S"] + c["P"], c["S"] + c["V"], c["S"] + c["P"] + c["V"]):
            dif["sin_votar"] = (v["sin_votar"], c["S"], c["S"] + c["P"])
        if any(v[k] is None for k in ("afirmativos", "negativos", "abstenciones", "ausentes")):
            dif["faltan_totales"] = True
        if dif:
            no_cuadran.append({"id": v["id"], "periodo": v["periodo"], "fecha": v["fecha"], "diferencias": dif})
        legis = [x for x in v["votos"] if x[4] != "V"]
        codigos = [x[0] for x in legis]
        raras = {"sin_codigo": sum(1 for x in codigos if not x), "sin_nombre": sum(1 for x in legis if not x[1].strip(" ,")),
                 "codigo_repetido": len(codigos) - len(set(codigos)), "voto_desconocido": c["X"], "voto_vacio": c[""]}
        raras = {k: n for k, n in raras.items() if n}
        if raras:
            filas_raras.append({"id": v["id"], **raras})
    inf["cabecera_vs_detalle"] = {"revisadas": len(vs), "no_cuadran": no_cuadran}
    inf["filas_raras"] = filas_raras

    # 3. Índice contra página.
    dif_indice = []
    for v in vs:
        i = v["indice"]
        if i["fecha"] != v["fecha"] or (i["resultado"] and i["resultado"] != v["resultado"]):
            dif_indice.append({"id": v["id"], "indice": [i["fecha"], i["resultado"]], "pagina": [v["fecha"], v["resultado"]]})
    inf["indice_vs_pagina"] = dif_indice

    # 4. Muestra del período 137 contra datos.hcdn.gob.ar.
    cab = {int(r["acta_id"]): r for r in leer_csv("actas_cabecera_137.csv.gz")}
    det = collections.defaultdict(list)
    for r in leer_csv("actas_detalle_137.csv.gz"):
        det[int(r["acta_id"])].append(r)
    muestra = []
    for v in vs:
        if v["periodo"] != 137 or v["id"] not in cab:
            continue
        r = cab[v["id"]]
        tot_of = [int(r["votos_afirmativos"]), int(r["votos_negativos"]), int(r["abstenciones"]), int(r["ausentes"])]
        tot_pg = [v["afirmativos"], v["negativos"], v["abstenciones"], v["ausentes"]]
        # Los nombres difieren en la forma (el archivo trae el nombre completo, la página el de uso), así que se cruza por
        # primer apellido y distrito; el archivo oficial marca a quien preside como AUSENTE.
        of = collections.defaultdict(list)
        for x in det[v["id"]]:
            if not x["diputado_nombre"].strip().startswith("."):
                of[(norm(x["diputado_nombre"]).split()[0], distrito(x["distrito_nombre"]))].append(COD_OF.get(x["voto"].strip().upper(), "?"))
        pg = collections.defaultdict(list)
        for x in v["votos"]:
            if x[4] != "V" and not x[1].startswith("."):
                pg[(norm(x[1].partition(",")[0]).split()[0], distrito(x[3]))].append("U" if x[4] == "P" else x[4])
        iguales = sum(1 for k in of.keys() & pg.keys() if sorted(of[k]) == sorted(pg[k]))
        distintos = sum(1 for k in of.keys() & pg.keys() if sorted(of[k]) != sorted(pg[k]))
        muestra.append({"id": v["id"], "totales_iguales": tot_of == tot_pg, "oficial": tot_of, "pagina": tot_pg,
                        "diputados_iguales": iguales, "diputados_distintos": distintos,
                        "solo_oficial": len(of.keys() - pg.keys()), "solo_pagina": len(pg.keys() - of.keys()),
                        "reunion_acta": [int(r["reunion"]), int(r["numero"]), v["reunion"], v["acta"]]})
    inf["muestra_137_vs_datos_hcdn"] = muestra

    # 5. Contraste con las votaciones actuales (ArgentinaDatos).
    ruta_ad = next(iter(sorted(glob.glob(str(AD / "diputados_actas_desde_*.json.gz")))), None)
    contraste = {"archivo": Path(ruta_ad).name if ruta_ad else None}
    if ruta_ad:
        with gzip.open(ruta_ad, "rt", encoding="utf-8") as f:
            ad = {int(a["id"]): a for a in json.load(f)}
        desde = min(a["fecha"][:10] for a in ad.values())
        propias = {v["id"] for v in vs if v["fecha"] >= desde}
        faltan_aca = sorted(set(ad) - por_id.keys())
        sobran_aca = sorted(propias - set(ad))
        detalle = []
        for n in sorted(set(ad) & por_id.keys()):
            a, v = ad[n], por_id[n]
            tot_ad = [a["votosAfirmativos"], a["votosNegativos"], a["abstenciones"], a["ausentes"]]
            tot_pg = [v["afirmativos"], v["negativos"], v["abstenciones"], v["ausentes"]]
            va = {}
            for x in a["votos"]:
                cod = (x.get("imagen") or "").rstrip("/").rsplit("/", 1)[-1].split(".")[0]
                va[cod or clave_nombre(x["diputado"])] = COD_AD.get(norm(x["tipoVoto"]), x["tipoVoto"])
            vp = {x[0] or clave_nombre(x[1]): x[4] for x in v["votos"] if x[4] != "V"}
            vacantes = {x[0] for x in v["votos"] if x[4] == "V"}
            va = {k: x for k, x in va.items() if k not in vacantes}
            distintos = sum(1 for k in va.keys() & vp.keys() if va[k] != vp[k])
            detalle.append({"id": n, "totales_iguales": tot_ad == tot_pg, "argentinadatos": tot_ad, "hcdn": tot_pg,
                            "periodo_reunion_acta_iguales": [int(a["periodo"]), int(a["reunion"]), int(a["numeroActa"])] == [v["periodo"], v["reunion"], v["acta"]],
                            "votos_distintos": distintos, "solo_argentinadatos": len(va.keys() - vp.keys()), "solo_hcdn": len(vp.keys() - va.keys())})
        contraste.update({"votaciones_argentinadatos": len(ad), "desde": desde, "en_ambas": len(detalle),
                          "faltan_en_hcdn": faltan_aca, "solo_en_hcdn": sobran_aca,
                          "con_diferencias": [d for d in detalle if not d["totales_iguales"] or d["votos_distintos"] or d["solo_argentinadatos"] or d["solo_hcdn"] or not d["periodo_reunion_acta_iguales"]]})
    inf["contraste_actuales"] = contraste

    # 6. Cobertura por período y huecos en la numeración de actas.
    cobertura = {}
    for p in sorted({v["periodo"] for v in vs}):
        del_p = [v for v in vs if v["periodo"] == p]
        reuniones = collections.defaultdict(list)
        for v in del_p:
            reuniones[v["reunion"]].append(v["acta"])
        huecos = {str(r): sorted(set(range(1, max(a) + 1)) - set(a)) for r, a in sorted(reuniones.items()) if set(range(1, max(a) + 1)) - set(a)}
        cobertura[str(p)] = {"votaciones": len(del_p), "desde": min(v["fecha"] for v in del_p), "hasta": max(v["fecha"] for v in del_p),
                             "reuniones_con_votaciones": len(reuniones), "actas_faltantes_por_reunion": huecos}
    inf["cobertura"] = cobertura

    with open(DIR / "validacion.json", "w", encoding="utf-8") as f:
        json.dump(inf, f, ensure_ascii=False, indent=1)

    print(f"Votaciones interpretadas: {len(vs)}; pendientes {len(datos['pendientes'])}; sin interpretar {len(datos['sin_interpretar'])}")
    print(f"Cabecera vs detalle: {len(no_cuadran)} no cuadran; filas raras en {len(filas_raras)}; índice vs página: {len(dif_indice)} diferencias")
    ok137 = sum(1 for m in muestra if m["totales_iguales"] and not m["diputados_distintos"] and not m["solo_oficial"] and not m["solo_pagina"])
    print(f"Muestra 137 vs datos.hcdn: {ok137}/{len(muestra)} idénticas")
    if ruta_ad:
        print(f"Contraste ArgentinaDatos: {contraste['en_ambas']}/{contraste['votaciones_argentinadatos']} en ambas; "
              f"faltan en HCDN {len(contraste['faltan_en_hcdn'])}; solo en HCDN {len(contraste['solo_en_hcdn'])}; con diferencias {len(contraste['con_diferencias'])}")
    for p, c in cobertura.items():
        print(f"  Período {p}: {c['votaciones']} votaciones ({c['desde']} a {c['hasta']}), {c['reuniones_con_votaciones']} reuniones, "
              f"huecos en {len(c['actas_faltantes_por_reunion'])} reuniones")
    return 0


if __name__ == "__main__":
    sys.exit(main())
