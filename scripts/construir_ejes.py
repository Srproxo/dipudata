#!/usr/bin/env python3
"""
Construye storage/verificado/ejes.json: para cada legislador actual, cuántos de sus proyectos y de sus votos caen en
cada uno de los 11 ejes temáticos.

Criterio (decidido por Marcelo el 1/11/2026): el eje de un proyecto es el de la comisión a la que se giró en PRIMER
lugar (orden de giro 1), según la tabla editable scripts/ejes_por_comision.csv. Una comisión puede tener más de un eje
(se cuenta en cada uno) o ninguno ("Sin eje": el proyecto no cuenta para ningún eje).

Fuentes:
- Giros de Diputados: storage/oficial/hcdn/giros_comisiones.csv.gz (datos.hcdn.gob.ar, "Giro a Comisiones").
- Giros del Senado: storage/oficial/senado/giros_proyectos.json.gz (bajar_giros_senado.py, senado.gob.ar verExp).
- Proyectos de cada legislador: storage/verificado/proyectos/ (construir_proyectos.py).
- Votos: storage/verificado/votaciones/actas/ con número de expediente; el expediente se cruza con
  proyectos_parlamentarios.csv.gz para llegar al giro. Solo cuentan los votos emitidos (afirmativo, negativo, abstención).

No combina proyectos y votos en un solo número: eso se hace al mostrar, con las cantidades a la vista.

Uso:  python3 -I scripts/construir_ejes.py
"""
import collections, csv, glob, gzip, json, re, unicodedata
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
VER = RAIZ / "storage" / "verificado"
OFI = RAIZ / "storage" / "oficial"
EJES = ["Salud", "Educación", "Industria", "Comercio", "Previsión Social", "Medio Ambiente", "Seguridad", "Género",
        "Federalismo", "Transparencia", "Tecnología"]


def norm(s: str) -> str:
    s = unicodedata.normalize("NFKD", s.upper()).encode("ascii", "ignore").decode()
    s = re.sub(r"\s+", " ", s).strip()
    return re.sub(r"^(DE LA |DE LOS |DE LAS |DE )", "", s)


def norm_exp(e: str) -> str:
    m = re.match(r"0*(\d+)-([A-Z]+)-(\d{4})", e.strip().upper())
    return f"{int(m[1])}-{m[2]}-{m[3]}" if m else e.strip().upper()


def main() -> int:
    tabla = {}
    for r in csv.DictReader(open(RAIZ / "scripts" / "ejes_por_comision.csv", encoding="utf-8")):
        ejes = [e for e in r["ejes"].split("|") if e]
        desconocidos = [e for e in ejes if e not in EJES]
        if desconocidos:
            raise SystemExit(f"eje desconocido en ejes_por_comision.csv: {desconocidos} ({r['comision']})")
        tabla[norm(r["comision"])] = ejes

    sin_tabla = collections.Counter()

    def ejes_de(comision: str):
        k = norm(comision)
        if k not in tabla:
            sin_tabla[comision] += 1
            return []
        return tabla[k]

    giro_hcdn = {}
    with gzip.open(OFI / "hcdn" / "giros_comisiones.csv.gz", "rt", encoding="utf-8-sig") as f:
        for r in csv.DictReader(f):
            if r["ORDEN"] == "1":
                giro_hcdn[r["PROYECTO_ID"]] = r["COMISION"]

    giro_senado = {}
    ruta_s = OFI / "senado" / "giros_proyectos.json.gz"
    if ruta_s.exists():
        for ref, g in json.load(gzip.open(ruta_s))["giros"].items():
            primero = next((x for x in g if x[1] == 1), g[0] if g else None)
            if primero:
                giro_senado[ref] = primero[0]

    exp_a_id = {}
    with gzip.open(OFI / "hcdn" / "proyectos_parlamentarios.csv.gz", "rt", encoding="utf-8-sig") as f:
        for r in csv.DictReader(f):
            for k in ("EXP_DIPUTADOS", "EXP_SENADO"):
                if r[k].strip():
                    exp_a_id[norm_exp(r[k])] = r["PROYECTO_ID"]

    salida = {}
    tot = collections.Counter()
    for ruta in sorted(glob.glob(str(VER / "proyectos" / "*.json"))):
        nombre = Path(ruta).stem
        if nombre.startswith("_"):
            continue
        senado = nombre.startswith("s-")
        clave = "s:" + nombre[2:] if senado else nombre
        d = json.load(open(ruta, encoding="utf-8"))
        cuenta = collections.Counter()
        con_giro = con_eje = 0
        for p in d["proyectos"]:
            comision = giro_senado.get(p[0]) if senado else giro_hcdn.get(p[0])
            if not comision:
                continue
            con_giro += 1
            ejes = ejes_de(comision)
            if ejes:
                con_eje += 1
            for e in ejes:
                cuenta[e] += 1
        salida[clave] = {"proyectos": len(d["proyectos"]), "con_giro": con_giro, "con_eje": con_eje,
                         "ejes": {e: {"proyectos": cuenta[e]} for e in EJES if cuenta[e]}}
        tot["proyectos"] += len(d["proyectos"]); tot["con_giro"] += con_giro; tot["con_eje"] += con_eje

    actas_con_eje = 0
    for ruta in glob.glob(str(VER / "votaciones" / "actas" / "*.json")):
        a = json.load(open(ruta, encoding="utf-8"))
        ejes = set()
        for e in a.get("expedientes") or []:
            pid = exp_a_id.get(norm_exp(e[0]))
            if pid and pid in giro_hcdn:
                ejes.update(ejes_de(giro_hcdn[pid]))
        if not ejes:
            continue
        actas_con_eje += 1
        for v in a["votos"]:
            if len(v) < 5 or v[3] not in ("A", "N", "B"):
                continue
            leg = salida.setdefault(v[4], {"proyectos": 0, "con_giro": 0, "con_eje": 0, "ejes": {}})
            for e in ejes:
                x = leg["ejes"].setdefault(e, {"proyectos": 0})
                x["votos"] = x.get("votos", 0) + 1
                x["afirmativos"] = x.get("afirmativos", 0) + (v[3] == "A")

    for leg in salida.values():
        leg["ejes"] = {e: leg["ejes"][e] for e in EJES if e in leg["ejes"]}

    salida = dict(sorted(salida.items()))
    salida["_meta"] = {
        "criterio": "Eje de la comisión de primer giro, según scripts/ejes_por_comision.csv",
        "fuentes": ["datos.hcdn.gob.ar: Giro a Comisiones", "senado.gob.ar: verExp (giros del expediente)",
                    "datos.hcdn.gob.ar: Proyectos parlamentarios", "actas de votación verificadas"],
        "ejes": EJES,
        "proyectos": tot["proyectos"], "proyectos_con_giro": tot["con_giro"], "proyectos_con_eje": tot["con_eje"],
        "actas_con_eje": actas_con_eje,
        "giros_senado_disponibles": len(giro_senado),
        "comisiones_fuera_de_tabla": dict(sin_tabla.most_common()),
    }
    (VER / "ejes.json").write_text(json.dumps(salida, ensure_ascii=False, separators=(",", ":")) + "\n", encoding="utf-8")
    m = salida["_meta"]
    print(f"proyectos {m['proyectos']}, con giro {m['proyectos_con_giro']}, con eje {m['proyectos_con_eje']}; actas con eje {actas_con_eje}")
    if sin_tabla:
        print("comisiones que no están en la tabla:", dict(sin_tabla.most_common(15)))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
