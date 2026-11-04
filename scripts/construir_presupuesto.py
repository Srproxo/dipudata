#!/usr/bin/env python3
"""
Ejecución presupuestaria de la Cámara de Diputados (programa 17, "Formación y Sanción Legislativa"), por inciso del gasto.

Fuente: conjunto "Ejecución presupuestaria de la Cámara de Diputados de la Nación" de datos.hcdn.gob.ar (CKAN), bajado con
scripts/bajar_ckan.py desde una máquina local (el portal corta la conexión desde la nube) y guardado en
storage/oficial/ckan/hcdn/ejecucion-presupuestaria-de-la-camara-de-diputados-de-la-nacion/ (27 CSV y manifest.json con sus sha256).
Los archivos cambian de formato entre años (2016-2019 en filas largas con importes decimales; 2020-2023 con importes tipo
"13.405.890.314.00" y a veces en Latin-1; 2024 en adelante con NA): cada uno se lee por el nombre de sus columnas.

Qué se usa: el cierre de cada ejercicio (2016-2025: cuarto trimestre acumulado o el archivo anual) y el último acumulado del 2026.
Por cada uno: crédito vigente (lo presupuestado, ya con modificaciones), compromiso consumido y devengado consumido, por inciso.

Controles: cada CSV tiene que coincidir con el sha256 del manifest; el único programa es el 17; la suma de los incisos tiene que ser
igual al total del programa (± $2 por redondeo) y, donde el archivo trae "Totales Generales", igual a ese total; el nombre de cada inciso tiene
que ser el esperado; en los años con acumulados trimestrales el devengado no puede bajar de un trimestre al siguiente; ningún importe negativo.
Pesos de hoy: IPC del INDEC (storage/verificado/ipc.json), promedio del IPC de los 12 meses del año → último mes; solo para años con los 12 meses
(la serie arranca en dic. 2016, así que 2016 queda sin conversión).
No se atribuye gasto a ningún legislador.

Genera storage/verificado/presupuesto_camara.json.   Uso:  python3 -I scripts/construir_presupuesto.py
"""
import csv, hashlib, io, json, re, sys, unicodedata
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
CARPETA = RAIZ / "storage" / "oficial" / "ckan" / "hcdn" / "ejecucion-presupuestaria-de-la-camara-de-diputados-de-la-nacion"
SALIDA = RAIZ / "storage" / "verificado" / "presupuesto_camara.json"
INCISOS = {1: "Gastos en personal", 2: "Bienes de consumo", 3: "Servicios no personales", 4: "Bienes de uso", 5: "Transferencias"}
TRIM = {"PRIMER": 1, "SEGUNDO": 2, "TERCER": 3, "CUARTO": 4}


def norm(t: str) -> str:
    t = unicodedata.normalize("NFD", (t or "").lower().strip())
    return re.sub(r"[^a-z0-9]+", " ", "".join(c for c in t if unicodedata.category(c) != "Mn")).strip()


def importe(s: str) -> float:
    s = (s or "").strip().strip('"')
    if s in ("", "NA"):
        return 0.0
    if re.fullmatch(r"-?\d+,\d{1,2}", s):        # 2017: coma decimal ("83475237,4")
        return float(s.replace(",", "."))
    if s.count(".") >= 2:                      # 13.405.890.314.00: puntos de miles y los dos últimos dígitos son decimales
        if not re.fullmatch(r"-?\d{1,3}(\.\d{3})*\.\d{2}", s):
            raise SystemExit(f"importe con formato desconocido: {s!r}")
        s = s.replace(".", "")
        return int(s) / 100
    if re.fullmatch(r"-?\d+\.\d{3}", s):      # "1.234": ambiguo, no se adivina
        raise SystemExit(f"importe ambiguo: {s!r}")
    return float(s)


def vacio(v) -> bool:
    return (v or "").strip().strip('"') in ("", "NA")


def leer_texto(ruta: Path) -> str:
    b = ruta.read_bytes()
    try:
        return b.decode("utf-8-sig")
    except UnicodeDecodeError:
        return b.decode("cp1252")


def periodo(nombre: str):
    """(año, trimestre) según el nombre del archivo; los anuales cuentan como trimestre 4."""
    m = re.search(r"(\d{4})(?=\.csv$)", nombre)
    anio = int(m.group(1)) if m else None
    if "TRIMESTRE_ACUMULADO" in nombre or re.search(r"(PRIMER|SEGUNDO|TERCER|CUARTO)_TRIMESTRE", nombre):
        q = TRIM[re.search(r"(PRIMER|SEGUNDO|TERCER|CUARTO)", nombre).group(1)]
        return anio, q
    return anio, 4


def leer_archivo(ruta: Path):
    """-> {anio, incisos {n: [credito, comprometido, devengado]}, total [..], total_declarado [..]|None}"""
    filas = list(csv.reader(io.StringIO(leer_texto(ruta))))
    ih = next((i for i, f in enumerate(filas) if any("credito" in norm(c) and "vigente" in norm(c) for c in f)), None)
    if ih is None:
        raise SystemExit(f"{ruta.name}: no encuentro la fila de títulos")
    cab = [norm(c) for c in filas[ih]]

    def col(*claves, obligatoria=True):
        for i, c in enumerate(cab):
            if all(k in c for k in claves):
                return i
        if obligatoria:
            raise SystemExit(f"{ruta.name}: falta la columna {claves}")
        return None

    c_cred, c_comp, c_dev = col("credito", "vigente"), col("compromiso", "consumido"), col("devengado", "consumido")
    largo = col("programa", "id", obligatoria=False) is not None
    incisos, total, declarado, anios = {n: [0.0, 0.0, 0.0] for n in INCISOS}, [0.0] * 3, None, set()
    nombres, reservado = {}, 0.0
    if largo:   # 2016-2019: una fila por parcial; se suma por inciso
        c_pg, c_in, c_ind, c_ej = col("programa", "id"), col("inciso", "id"), col("inciso", "desc"), col("ejercicio")
        c_compr, c_devr = col("compromiso", "reservado"), col("devengado", "reservado")
        for f in filas[ih + 1:]:
            if not f or not any(f):
                continue
            if f[c_pg].strip() != "17":
                raise SystemExit(f"{ruta.name}: programa distinto de 17: {f[c_pg]!r}")
            n = int(f[c_in])
            reservado += importe(f[c_compr]) + importe(f[c_devr])
            # 2018 trae todo en las columnas "reservado" y nada en "consumido": se suman las dos etapas (en los otros años "reservado" es 0)
            v = [importe(f[c_cred]), importe(f[c_comp]) + importe(f[c_compr]), importe(f[c_dev]) + importe(f[c_devr])]
            for k in range(3):
                incisos[n][k] += v[k]
                total[k] += v[k]
            nombres[n] = f[c_ind]
            anios.add(int(f[c_ej]))
    else:
        c_pg, c_in, c_pp = col("pg"), col("in"), col("pp")
        c_pc = col("pc", obligatoria=False)
        c_ley = col("leyenda", obligatoria=False)
        for f in filas[ih + 1:]:
            if len(f) <= max(c_cred, c_comp, c_dev) or not any(x.strip() for x in f):
                continue
            if "totales" in norm(f[0]) or "total" in norm(f[0]):
                declarado = [importe(f[c_cred]), importe(f[c_comp]), importe(f[c_dev])]
                continue
            pg, i_, pp = f[c_pg], f[c_in], f[c_pp]
            pc = f[c_pc] if c_pc is not None else ""
            v = [importe(f[c_cred]), importe(f[c_comp]), importe(f[c_dev])]
            if not vacio(pg) and vacio(i_) and vacio(pp) and vacio(pc):      # fila del programa
                if pg.strip().strip('"') != "17":
                    raise SystemExit(f"{ruta.name}: programa distinto de 17: {pg!r}")
                total = v
            elif not vacio(i_) and vacio(pp) and vacio(pc):                  # fila de inciso
                n = int(i_.strip().strip('"'))
                if n not in INCISOS:
                    raise SystemExit(f"{ruta.name}: inciso desconocido {n}")
                incisos[n] = v
                if c_ley is not None:
                    nombres[n] = f[c_ley]
    for n, nom in nombres.items():
        if norm(nom) != norm(INCISOS[n]):
            raise SystemExit(f"{ruta.name}: el inciso {n} se llama {nom!r} y esperaba {INCISOS[n]!r}")
    anio, q = periodo(ruta.name)
    if anios and anios != {anio}:
        raise SystemExit(f"{ruta.name}: el ejercicio del contenido {anios} no coincide con el del nombre {anio}")
    return {"anio": anio, "trimestre": q, "incisos": incisos, "total": total, "declarado": declarado, "archivo": ruta.name, "reservado": reservado > 0}


def main() -> int:
    man = json.loads((CARPETA / "manifest.json").read_text(encoding="utf-8"))
    por_nombre = {}
    for r in man["recursos"].values():
        if (r.get("formato") or "").upper() == "CSV":
            por_nombre[Path(r["archivo"]).name if r.get("archivo") else None] = r
    lecturas, descartados = [], []
    for ruta in sorted(CARPETA.glob("*.csv")):
        if periodo(ruta.name)[0] == 2021 and periodo(ruta.name)[1] < 4:   # trimestres 1 a 3 de 2021: sin columna de programa y con importes que no cuadran con el cierre
            descartados.append(ruta.name)
            continue
        crudo = ruta.read_bytes()
        sha = hashlib.sha256(crudo).hexdigest()
        if sha not in {r["sha256"] for r in man["recursos"].values()}:
            raise SystemExit(f"{ruta.name} no coincide con el sha256 del manifest")
        a = leer_archivo(ruta)
        a["sha256"] = sha
        r = next(r for r in man["recursos"].values() if r["sha256"] == sha)
        a["url"], a["bajado"], a["modificado"] = r.get("url", ""), r.get("bajado", ""), (r.get("last_modificado") or "")[:10]
        for k in range(3):
            suma = sum(v[k] for v in a["incisos"].values())
            if abs(suma - a["total"][k]) > 2:
                raise SystemExit(f"{ruta.name}: los incisos suman {suma:,.2f} y el programa dice {a['total'][k]:,.2f}")
            if a["declarado"] and abs(a["declarado"][k] - a["total"][k]) > 2:
                raise SystemExit(f"{ruta.name}: 'Totales Generales' no coincide con el programa 17")
            if min(v[k] for v in a["incisos"].values()) < 0:
                raise SystemExit(f"{ruta.name}: importe negativo")
        if a["total"][2] > a["total"][1] + 2:
            raise SystemExit(f"{ruta.name}: devengado mayor que el compromiso")
        lecturas.append(a)
    # acumulados trimestrales: el devengado no baja dentro de un año (donde hay más de un trimestre leído con el mismo criterio)
    por_anio = {}
    for a in lecturas:
        por_anio.setdefault(a["anio"], {})[a["trimestre"]] = a
    for anio, d in por_anio.items():
        previo = None
        for q in sorted(d):
            if previo is not None and d[q]["total"][2] + 2 < previo["total"][2] and anio >= 2022:
                raise SystemExit(f"{anio}: el devengado acumulado baja del trimestre {previo['trimestre']} al {q}")
            previo = d[q]
    # ejercicios: cierre = trimestre 4; el último año incompleto = su último trimestre
    ipc = json.loads((RAIZ / "storage" / "verificado" / "ipc.json").read_text(encoding="utf-8"))["mensual"]
    ult = max(ipc)
    anios_out, ultimo_anio = [], max(por_anio)
    for anio in sorted(por_anio):
        a = por_anio[anio][max(por_anio[anio])]
        meses = [ipc[f"{anio}-{m:02d}"] for m in range(1, 13) if f"{anio}-{m:02d}" in ipc]
        factor = round(ipc[ult] / (sum(meses) / 12), 6) if len(meses) == 12 else None
        anios_out.append({
            "anio": anio, "trimestre": a["trimestre"], "cerrado": a["trimestre"] == 4, "factor_ipc": factor,
            "credito": round(a["total"][0]), "comprometido": round(a["total"][1]), "devengado": round(a["total"][2]),
            "incisos": {str(n): [round(x) for x in v] for n, v in a["incisos"].items()},
            "importes_reservados": a["reservado"], "archivo": a["archivo"], "sha256": a["sha256"], "url": a["url"], "bajado": a["bajado"],
        })
    salida = {
        "_meta": {
            "fuente": "Cámara de Diputados de la Nación, \"Ejecución presupuestaria\" (datos.hcdn.gob.ar), programa 17 Formación y Sanción Legislativa",
            "incisos": {str(n): t for n, t in INCISOS.items()}, "ipc_ultimo_mes": ult,
            "metodo_pesos_de_hoy": "importe del año × IPC del último mes disponible ÷ promedio del IPC de los 12 meses de ese año (INDEC, base dic. 2016); sin conversión donde faltan meses",
            "archivos": len(lecturas), "descartados": descartados,
        },
        "anios": anios_out,
    }
    SALIDA.write_text(json.dumps(salida, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
    for a in anios_out:
        print(f"{a['anio']} T{a['trimestre']}: crédito {a['credito']/1e6:>10,.0f} M  devengado {a['devengado']/1e6:>10,.0f} M  factor {a['factor_ipc']}")
    print(f"{len(lecturas)} archivos leídos, {len(anios_out)} ejercicios")
    return 0


if __name__ == "__main__":
    sys.exit(main())
