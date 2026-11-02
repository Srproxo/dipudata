#!/usr/bin/env python3
"""
Convierte las votaciones bajadas de votaciones.hcdn.gob.ar (storage/oficial/hcdn/votaciones_recinto/votaciones_hcdn.json.gz)
al mismo formato de datos.hcdn.gob.ar: un par actas_cabecera_<período>.csv.gz / actas_detalle_<período>.csv.gz por período
en storage/oficial/hcdn/votaciones/, para que construir_votaciones.py las lea con el glob de siempre.

Solo entran las votaciones que pasan los controles de validar_votaciones_hcdn.py (cabecera = detalle, sin filas raras)
y que son anteriores al mandato actual (CORTE_ACTUAL): las posteriores ya vienen de ArgentinaDatos y se contrastaron.
Las que no entran quedan listadas en validacion.json con el motivo.

Diferencias con el archivo de datos.hcdn.gob.ar: la página no trae tipo de período, número de sesión ni tipo de sesión
(quedan vacíos); el nombre viene como 'APELLIDO, NOMBRE' y se pasa a 'APELLIDO Nombre'; y el detalle sí trae el código oficial del diputado (persona_id). Quien preside figura con voto PRESIDENTE y quien estaba presente sin votar, con SIN VOTAR. Las bancas pendientes de
incorporación no son legisladores: van con el nombre ". BANCA PENDIENTE DE INCORPORACION" (como las filas ". NO INCORPORADO"
del archivo oficial, que construir_votaciones.py descarta).

Uso:  python3 -I scripts/convertir_votaciones_hcdn.py   (después de validar_votaciones_hcdn.py)
"""
import collections, csv, gzip, json, sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
DIR = RAIZ / "storage" / "oficial" / "hcdn" / "votaciones_recinto"
SAL = RAIZ / "storage" / "oficial" / "hcdn" / "votaciones"
CORTE_ACTUAL = "2025-12-10"
PERIODOS = range(138, 145)
VOTO = {"A": "AFIRMATIVO", "N": "NEGATIVO", "B": "ABSTENCION", "U": "AUSENTE", "P": "PRESIDENTE", "S": "SIN VOTAR",
        "V": "PENDIENTE DE INCORPORACION"}
CAB = ["sesion_id", "acta_id", "nroperiodo", "tipo_periodo", "reunion", "sesion", "tipo_sesion", "numero", "fecha", "hora",
       "base_mayoria", "tipo_mayoria", "titulo", "resultado", "presidente_nombre", "presidente_id",
       "votos_afirmativos", "votos_negativos", "abstenciones", "ausentes"]
DET = ["acta_id", "acta_detalle_id", "diputado_nombre", "persona_id", "bloque", "distrito_nombre", "voto"]


def como_oficial(nombre: str) -> str:
    """'YEDLIN, PABLO RAUL' -> 'YEDLIN Pablo Raul', la forma de datos.hcdn.gob.ar (apellido en mayúsculas), que es la que
    construir_votaciones.py sabe partir en apellido y nombre."""
    ap, coma, no = nombre.partition(",")
    return f"{ap.strip().upper()} {no.strip().title()}".strip() if coma else nombre.strip()


def main() -> int:
    with gzip.open(DIR / "votaciones_hcdn.json.gz", "rt", encoding="utf-8") as f:
        vs = json.load(f)["votaciones"]
    val = json.loads((DIR / "validacion.json").read_text(encoding="utf-8"))
    malas = {d["id"] for d in val["cabecera_vs_detalle"]["no_cuadran"]} | {d["id"] for d in val["filas_raras"]}
    por_periodo, excluidas = collections.defaultdict(list), collections.Counter()
    for v in vs:
        if v["periodo"] not in PERIODOS:
            continue
        if v["fecha"] >= CORTE_ACTUAL:
            excluidas["mandato actual (viene de ArgentinaDatos)"] += 1
        elif v["id"] in malas:
            excluidas["no pasa la validación"] += 1
        else:
            por_periodo[v["periodo"]].append(v)
    for p, lista in sorted(por_periodo.items()):
        lista.sort(key=lambda v: (v["fecha"], v["hora"], v["id"]))
        with gzip.open(SAL / f"actas_cabecera_{p}.csv.gz", "wt", encoding="utf-8", newline="") as fc, \
             gzip.open(SAL / f"actas_detalle_{p}.csv.gz", "wt", encoding="utf-8", newline="") as fd:
            wc, wd = csv.writer(fc), csv.writer(fd)
            wc.writerow(CAB)
            wd.writerow(DET)
            for v in lista:
                tipo_mayoria, _, base_mayoria = v["mayoria"].rpartition(" - ")
                pres = next((x for x in v["votos"] if x[4] == "P"), None)
                wc.writerow([f"HCDN{p}R{v['reunion']:02d}", v["id"], p, "", v["reunion"], "", "", v["acta"], v["fecha"], v["hora"],
                             base_mayoria, tipo_mayoria, v["titulo"], v["resultado"], v["presidente"], pres[0] if pres else "",
                             v["afirmativos"], v["negativos"], v["abstenciones"], v["ausentes"]])
                for k, (cod, nombre, bloque, prov, voto) in enumerate(v["votos"], 1):
                    wd.writerow([v["id"], f"{v['id']}-{k}", ". BANCA PENDIENTE DE INCORPORACION" if voto == "V" else como_oficial(nombre), cod, bloque, prov, VOTO[voto]])
        print(f"Período {p}: {len(lista)} votaciones")
    for motivo, n in excluidas.items():
        print(f"No convertidas, {motivo}: {n}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
