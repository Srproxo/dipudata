#!/usr/bin/env python3
"""
Comisiones, pasajes emitidos, misiones oficiales y remuneraciones de la Cámara de Diputados
(datos abiertos de la HCDN, datos.hcdn.gob.ar), cruzados con los diputados en ejercicio.

Salidas (storage/verificado/): comisiones_diputados.json, viajes.json, misiones.json, remuneraciones.json
Uso:  python3 -I scripts/construir_diputados_extra.py   (después de construir_datos.py)
"""
import collections, csv, glob, gzip, io, json, re, sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from construir_datos import norm, titulo, slugify  # noqa: E402

RAIZ = Path(__file__).resolve().parent.parent
H = RAIZ / "storage" / "oficial" / "hcdn"
SAL = RAIZ / "storage" / "verificado"
MESES = {"ene": 1, "feb": 2, "mar": 3, "abr": 4, "may": 5, "jun": 6, "jul": 7, "ago": 8, "sep": 9, "set": 9, "oct": 10, "nov": 11, "dic": 12}


def leer(ruta):
    b = gzip.open(ruta, "rb").read()
    for enc in ("utf-8-sig", "cp1252"):
        try:
            return list(csv.DictReader(io.StringIO(b.decode(enc), newline="")))
        except UnicodeDecodeError:
            continue
    raise ValueError(ruta)


def monto(s):
    s = (s or "").strip().replace("$", "").replace(" ", "")
    if not s:
        return None
    neg = s.startswith("-")
    s = s.lstrip("-")
    if "," in s:
        s = s.replace(".", "").replace(",", ".")
    try:
        v = float(s)
    except ValueError:
        return None
    return -v if neg else v


def col(fila, *claves):
    for k in fila:
        kk = norm(k).replace(" ", "")
        if any(c in kk for c in claves):
            return fila[k] or ""
    return ""


def main() -> int:
    legs = [l for l in json.loads((SAL / "legisladores.json").read_text(encoding="utf-8")) if l["camara"] == "Diputados"]
    tokens = [(set(norm(l["nombre"]).split()), l) for l in legs]

    def buscar(nombre):
        """Cruce por nombre: exacto por conjunto de palabras; si no, uno contenido en el otro. Solo se acepta si es único."""
        tk = set(norm(nombre.replace(",", " ")).split())
        c = [l for t, l in tokens if t == tk] or [l for t, l in tokens if t <= tk or tk <= t]
        return c[0] if len(c) == 1 else None

    # ---- Comisiones
    nombres = {r["ID"]: r for r in leer(H / "comisiones" / "comisiones.csv.gz")}
    cargos = collections.defaultdict(dict)
    for r in leer(H / "comisiones" / "autoridades.csv.gz"):
        l = buscar(r["DIPUTADO_NOMBRE"])
        cargos[r["COMISION_ID"]][l["slug"] if l else "n:" + r["DIPUTADO_NOMBRE"]] = r["CARGO"].strip()
    integrantes, sin = collections.defaultdict(list), 0
    for r in leer(H / "comisiones" / "integrantes.csv.gz"):
        l = buscar(r["DIPUTADO_NOMBRE"])
        if not l:
            sin += 1
            continue
        if l["slug"] not in [x["slug"] for x in integrantes[r["COMISION_ID"]]]:
            integrantes[r["COMISION_ID"]].append({"slug": l["slug"], "nombre": l["nombre"], "bloque": l["bloque"], "provincia": l["provincia"],
                                                  "cargo": cargos[r["COMISION_ID"]].get(l["slug"], "Vocal")})
    comisiones = []
    for cid, miembros in integrantes.items():
        n = nombres.get(cid)
        if not n:
            continue
        orden = {"Presidente": 0, "Vicepresidente 1": 1, "Vice Presidente 1": 1, "Vicepresidente 2": 2, "Vice Presidente 2": 2, "Secretario": 3}
        miembros.sort(key=lambda m: (orden.get(m["cargo"], 9), m["nombre"]))
        comisiones.append({"id": cid, "nombre": titulo(n["NOMBRE"].strip()), "slug": slugify(n["NOMBRE"]), "tipo": {"P": "Permanente", "E": "Especial", "B": "Bicameral"}.get(n["TIPO_DE_COMISION"].strip(), n["TIPO_DE_COMISION"].strip()),
                           "integrantes": miembros})
    comisiones.sort(key=lambda c: c["nombre"])
    (SAL / "comisiones_diputados.json").write_text(json.dumps(comisiones, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"comisiones: {len(comisiones)} con integrantes; {sum(len(c['integrantes']) for c in comisiones)} membresías; {sin} integrantes que no son diputados actuales")

    # ---- Pasajes emitidos por semestre
    viajes, meta, sin_cruce = collections.defaultdict(lambda: collections.defaultdict(collections.Counter)), {}, collections.Counter()
    por_mes = collections.defaultdict(lambda: collections.defaultdict(collections.Counter))
    for ruta in sorted(glob.glob(str(H / "viajes" / "viajes_*.csv.gz"))):
        sem = re.search(r"viajes_(\d{4}_s\d)", ruta).group(1)
        filas = leer(ruta)
        meta[sem] = len(filas)
        for r in filas:
            nombre = col(r, "diputad")
            tipo = norm(col(r, "tiposolicitud", "tipo")).split()[:1]
            tipo = "aereo" if tipo and tipo[0].startswith("aer") else ("terrestre" if tipo and tipo[0].startswith("terr") else "otro")
            l = buscar(nombre)
            if not l:
                sin_cruce[sem] += 1
                continue
            viajes[l["slug"]][sem][tipo] += 1
            per = col(r, "periodo", "per").strip().lower().replace("é", "e")
            iso = re.match(r"^(\d{4})-(\d{2})-\d{2}", per)
            m = re.match(r"^([a-zñ]{3})\w*[-/ ]+(\d{2,4})$", per)
            if iso:
                clave = f"{iso[1]}-{iso[2]}"
            elif m and m[1] in MESES:
                clave = f"{int(m[2]) + (2000 if int(m[2]) < 100 else 0)}-{MESES[m[1]]:02d}"
            else:
                clave = None
            if clave:
                por_mes[l["slug"]][clave][tipo] += 1
    salida = {s: {sem: dict(c, total=sum(c.values())) for sem, c in d.items()} for s, d in viajes.items()}
    desde = "2025-12"
    actual = {s: {t: sum(c[t] for m, c in meses.items() if m >= desde) for t in ("aereo", "terrestre")} for s, meses in por_mes.items()}
    actual = {s: dict(v, total=v["aereo"] + v["terrestre"]) for s, v in actual.items() if v["aereo"] + v["terrestre"] > 0}
    meses_sin_parsear = sum(meta.values()) - sum(sum(sum(c.values()) for c in m.values()) for m in por_mes.values()) - sum(sin_cruce.values())
    (SAL / "viajes.json").write_text(json.dumps({"semestres": meta, "desde_mandato_actual": desde + "-10", "actual": actual, "por_diputado": salida}, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"pasajes desde {desde}: {len(actual)} diputados; tramos sin mes interpretable: {meses_sin_parsear}")
    print(f"pasajes: {sum(meta.values())} tramos en {len(meta)} semestres; {len(salida)} diputados actuales con pasajes; tramos de otras personas: {dict(sin_cruce)}")

    # ---- Misiones oficiales (listado completo, tal como lo publica la Cámara)
    misiones = []
    for ruta in sorted(glob.glob(str(H / "viajes" / "misiones_*.csv.gz")), reverse=True):
        for r in leer(ruta):
            ini = col(r, "fechainicio")[:10]
            if not ini:
                continue
            partic = re.sub(r"\s+", " ", col(r, "participa")).strip()
            l = buscar(re.sub(r"^(dip\.?|diputad[oa])\s*", "", partic, flags=re.I))
            misiones.append({"inicio": ini, "fin": col(r, "fechafin")[:10], "motivo": re.sub(r"\s+", " ", col(r, "motivo")).strip(),
                             "invita": re.sub(r"\s+", " ", col(r, "institucion")).strip(), "lugar": re.sub(r"\s+", " ", col(r, "lugar")).strip(),
                             "participa": partic, "slug": l["slug"] if l else "", "bloque": titulo(col(r, "bloque").strip()) if col(r, "bloque") else ""})
    misiones.sort(key=lambda m: m["inicio"], reverse=True)
    (SAL / "misiones.json").write_text(json.dumps(misiones, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"misiones oficiales: {len(misiones)}")

    # ---- Remuneraciones (valores únicos, iguales para todos los legisladores) y escala salarial del personal
    def items(nombre):
        return [{"concepto": re.sub(r"\s+", " ", col(r, "concepto")).strip(), "importe": monto(col(r, "importe"))} for r in leer(H / f"remuneraciones_{nombre}.csv.gz")]
    rem = {k: items(k) for k in ("remuneraciones", "desarraigo", "movilidad", "descuentos")}
    (SAL / "remuneraciones.json").write_text(json.dumps(rem, ensure_ascii=False, indent=1), encoding="utf-8")
    print("remuneraciones:", {k: [(i["concepto"], i["importe"]) for i in v] for k, v in rem.items()})
    return 0


if __name__ == "__main__":
    sys.exit(main())
