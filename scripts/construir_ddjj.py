#!/usr/bin/env python3
"""
Declaraciones juradas patrimoniales de legisladores nacionales, desde los datos abiertos de la Oficina
Anticorrupción (datos.jus.gob.ar, "Declaraciones Juradas Patrimoniales Integrales", Ley 26.857).

Entrada: storage/oficial/ddjj/declaraciones_juradas_AAAA.csv.gz  (el archivo principal de cada año fiscal;
         agregar un año es copiar su archivo y volver a correr este script).
Salida:  storage/verificado/ddjj.json   slug -> lista de años, solo con totales.

NO se publica: CUIT, grupo familiar, ni la descripción de cada bien. Los montos están en pesos de cada año.
Los montos vienen escritos de dos formas: "155817519-23" (155.817.519,23; negativos "-3000000-00") y con punto decimal normal.

Uso:  python3 -I scripts/construir_ddjj.py   (después de construir_datos.py)
"""
import collections, csv, glob, gzip, io, json, re, sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from construir_datos import norm  # noqa: E402

RAIZ = Path(__file__).resolve().parent.parent
ENT = RAIZ / "storage" / "oficial" / "ddjj"
SAL = RAIZ / "storage" / "verificado"
ORGANISMOS = {"HONORABLE CAMARA DE DIPUTADOS DE LA NACION": "Diputados", "HONORABLE SENADO DE LA NACION": "Senadores"}
TIPOS = {"Anual": 0, "Inicial": 1, "Baja": 2}
MONTO = re.compile(r"^(-?)(\d*)-(\d{2})$")
PLANO = re.compile(r"^-?\d+(\.\d+)?$")


def monto(s):
    s = (s or "").strip()
    if not s:
        return None
    m = MONTO.match(s)
    if m:  # formato "155817519-23" y "-3000000-00"
        v = float(f"{m[2] or 0}.{m[3]}")
        return -v if m[1] else v
    if PLANO.match(s):  # formato con punto decimal, usado en los años 2012 a 2019 y en algunas filas posteriores
        return float(s)
    return None


def tokens(s):
    return norm(s or "").split()


def tiene(tks, t):
    """El nombre t figura en tks, también abreviado o cortado ("ELIZABET" por "ELIZABETH", "MARGARI" por "MARGARITA")."""
    return any(x == t or (len(x) >= 4 and t.startswith(x)) or (len(t) >= 4 and x.startswith(t)) for x in tks)


def compatibles(a, b):
    """Dos formas de escribir el mismo nombre: todas las palabras de la más corta están en la más larga (aceptando cortes)."""
    corto, largo = (a, b) if len(a) <= len(b) else (b, a)
    return all(tiene(largo, t) for t in corto)


def agrupar(nombres):
    """Agrupa las formas de un nombre que son compatibles entre sí; cada grupo es una persona (su forma más larga)."""
    grupos = []
    for n in sorted({tuple(x) for x in nombres}, key=len, reverse=True):
        for g in grupos:
            if compatibles(n, g):
                break
        else:
            grupos.append(n)
    return grupos


def mandatos():
    """Mandatos de cada persona (desde-hasta), de los listados históricos de cada cámara."""
    of = RAIZ / "storage" / "oficial"
    md, ms = collections.defaultdict(list), collections.defaultdict(list)
    for x in json.loads(gzip.open(of / "hcdn" / "diputados_historico.json.gz", "rt", encoding="utf-8", errors="replace").read()):
        md[str(x["ID"])].append((min(x["INICIO"][:10], (x.get("JURAMENTO") or x["INICIO"])[:10]),
                                 max(x["FIN"][:10], (x.get("CESE") or x["FIN"])[:10])))
    for f, (d, c) in [("senadores_no_activos", ("INICIO PERIODO REAL", "CESE PERIODO REAL")), ("senadores_activos", ("D_REAL", "C_LEGAL"))]:
        for x in json.loads(gzip.open(of / "senado" / f"{f}.json.gz", "rt", encoding="utf-8", errors="replace").read())["table"]["rows"]:
            ms[str(x["ID"])].append((x[d][:10], x[c][:10] if x[c][:2] in ("19", "20") else "2099-12-31"))
    return md, ms


def en_mandato(anio, tipo, mand):
    """El año fiscal tiene que tocar un mandato. Se acepta el año anterior a asumir (declaración inicial) y una baja hasta un año después."""
    y = int(anio)
    if any(i <= f"{y + 1}-12-31" and f >= f"{y}-01-01" for i, f in mand):
        return True
    return tipo == "Baja" and any(f >= f"{y - 1}-01-01" and i <= f"{y}-12-31" for i, f in mand)


def main() -> int:
    csv.field_size_limit(10**8)
    legs = json.loads((SAL / "legisladores.json").read_text(encoding="utf-8"))
    md, ms = mandatos()
    filas = []
    for ruta in sorted(glob.glob(str(ENT / "declaraciones_juradas_*.csv.gz"))):
        anio = re.search(r"_(\d{4})\.csv", ruta).group(1)
        with gzip.open(ruta, "rt", encoding="utf-8-sig", newline="") as fh:
            for r in csv.DictReader(fh):
                if None in r.values():
                    continue
                # filtrar_ddjj.py ya dejó solo cargos de legislador nacional. Muchas filas traen el organismo vacío o el
                # de la actividad anterior de la persona: la cámara sale del cargo y, si el organismo es del Congreso, tiene que coincidir.
                org, cargo = (r.get("organismo") or "").strip(), (r.get("cargo") or "").strip(" :.-\t")
                if not re.match(r"^(diputad|dipurad|senador)", cargo, re.I):
                    continue
                camara = "Senadores" if re.match(r"^senador", cargo, re.I) else "Diputados"
                if org in ORGANISMOS and camara != ORGANISMOS[org]:
                    continue
                r["_anio"], r["_camara"] = anio, camara
                filas.append(r)

    salida, usadas, ambiguos, fuera, saltadas, vacias = {}, set(), [], [], collections.Counter(), collections.Counter()
    for r in filas:
        r["_tk"] = tokens(r["funcionario_apellido_nombre"])
    datos_leg = []
    for l in legs:
        ap = tokens(l["apellido"] or l["nombre"].split()[-1])
        datos_leg.append((l, ap, tokens(l["nombre"].replace(l["apellido"] or "", "", 1))))
    for l, ap, resto in datos_leg:
        cand = [i for i, r in enumerate(filas)
                if r["_camara"] == l["camara"] and set(ap) <= set(r["_tk"]) and (not resto or tiene(r["_tk"], resto[0]))]
        if not cand and len(ap) >= 2:
            # Apellido compuesto y un apodo como nombre ("Bertie Benegas Lynch"): se acepta si en esa cámara hay una sola persona con ese apellido.
            cand = [i for i, r in enumerate(filas) if r["_camara"] == l["camara"] and set(ap) <= set(r["_tk"])]
        grupos = agrupar([filas[i]["_tk"] for i in cand])
        if len(grupos) > 1:
            # 1) quedan las personas que tienen todos los nombres del legislador;
            # 2) se descartan las que corresponden mejor a otro legislador actual de la misma cámara (Carlos Gutiérrez / Carlos Mario Gutiérrez).
            todos = [g for g in grupos if all(tiene(g, t) for t in resto)] or grupos
            otros = [(ap2, resto2) for l2, ap2, resto2 in datos_leg if l2 is not l and l2["camara"] == l["camara"]]
            propios = [g for g in todos if not any(set(ap2) <= set(g) and all(tiene(g, t) for t in resto2)
                                                     and len(ap2) + len(resto2) > len(ap) + len(resto) for ap2, resto2 in otros)]
            grupos = propios
            if len(grupos) == 1:
                cand = [i for i in cand if compatibles(filas[i]["_tk"], grupos[0])]
        if len(grupos) > 1:
            ambiguos.append(l["nombre"])
            continue
        if not cand:
            continue
        usadas.update(cand)
        por_anio = collections.defaultdict(list)
        for i in cand:
            por_anio[filas[i]["_anio"]].append(filas[i])
        mand = md.get(str(l.get("hcdn_id"))) if l["camara"] == "Diputados" else ms.get(str(l.get("id_senado")))
        if not mand:
            mand = [(l.get("inicio") or "1900-01-01", "2099-12-31")]
        anios = []
        for anio, rs in sorted(por_anio.items(), reverse=True):
            # Se elige una declaración por año: Anual antes que Inicial y Baja; la más rectificada.
            r = sorted(rs, key=lambda x: (TIPOS.get(x["tipo_declaracion_jurada_descripcion"], 9), -int(x["rectificativa"] or 0), -int(x["dj_id"] or 0)))[0]
            if not en_mandato(anio, r["tipo_declaracion_jurada_descripcion"], mand):
                # Mismo nombre pero un año en que esa persona no era legisladora en esta cámara: puede ser un homónimo. No se publica.
                fuera.append(f"{l['nombre']} {anio}")
                continue
            bf, df = monto(r["total_bienes_final"]), monto(r["total_deudas_final"])
            bi, di = monto(r["total_bienes_inicio"]), monto(r["deudas_inicio"])
            if bf is None:
                saltadas[anio] += 1
                continue
            if bf == 0:
                # Bienes en cero al cierre: más probable una declaración vacía o incompleta que un patrimonio nulo.
                vacias[anio] += 1
                continue
            anios.append({
                "anio": int(anio), "tipo": r["tipo_declaracion_jurada_descripcion"], "rectificativa": int(r["rectificativa"] or 0),
                "bienes_inicio": bi, "deudas_inicio": di, "bienes_final": bf, "deudas_final": df or 0.0,
                "patrimonio_neto_final": round(bf - (df or 0.0), 2),
                "ingresos_netos": monto(r["ingresos_neto_gastos"]),
            })
        if anios:
            salida[l["slug"] if l["camara"] == "Diputados" else "s:" + l["slug"]] = anios

    if vacias:
        print("Declaraciones con bienes en cero (no se publican), por año:", dict(sorted(vacias.items())))
    if fuera:
        print(f"Años fuera de todo mandato del legislador (no se publican): {len(fuera)}:", "; ".join(fuera))
    if saltadas:
        print("Declaraciones sin monto interpretable, por año:", dict(sorted(saltadas.items())))
    por_anio_n = collections.Counter(a["anio"] for v in salida.values() for a in v)
    print("Legisladores actuales con declaración, por año fiscal:", dict(sorted(por_anio_n.items())))
    (SAL / "ddjj.json").write_text(json.dumps(salida, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"{len(filas)} declaraciones de legisladores nacionales; {len(salida)} legisladores actuales cruzados; "
          f"{len({tuple(sorted(tokens(filas[i]['funcionario_apellido_nombre']))) for i in range(len(filas)) if i not in usadas})} personas del archivo ya no están en el cargo; {len(ambiguos)} ambiguos")
    return 0


if __name__ == "__main__":
    sys.exit(main())
