#!/usr/bin/env python3
"""
Declaraciones juradas patrimoniales de legisladores nacionales, desde los datos abiertos de la Oficina
Anticorrupción (datos.jus.gob.ar, "Declaraciones Juradas Patrimoniales Integrales", Ley 26.857).

Entrada: storage/oficial/ddjj/declaraciones_juradas_AAAA.csv.gz  (el archivo principal de cada año fiscal;
         agregar un año es copiar su archivo y volver a correr este script).
Salida:  storage/verificado/ddjj.json   slug -> lista de años, solo con totales y el cargo por el que se presentó.

Cruce: las declaraciones como legislador se buscan por nombre en la cámara actual y cada año se controla contra los
mandatos oficiales. Si no hay ninguna (por ejemplo, quien asumió en 2025 y fue diputado antes), se buscan en la otra
cámara, exigiendo una sola persona con ese nombre y esa provincia en el listado histórico de esa cámara. Una vez
identificada la persona, se suman sus declaraciones por otros cargos (ministro, funcionario, candidato), que
filtrar_ddjj.py unió por CUIT con un número de "persona" (el CUIT no se guarda).

NO se publica: CUIT, grupo familiar, ni la descripción de cada bien. Los montos están en pesos de cada año.
Los montos vienen escritos de dos formas: "155817519-23" (155.817.519,23; negativos "-3000000-00") y con punto decimal normal.

Diputados, años que la OA todavía no subió (desde el fiscal 2025): storage/oficial/ddjj/hcdn_ddjj_AAAA.csv.gz, que
bajar_ddjj_hcdn.py extrae de los PDF que la Cámara de Diputados publica en la ficha de cada diputado (mismo formulario
F. 1245). Se une al diputado por el usuario de su ficha, controlando apellido, nombres y provincia contra el listado
oficial, y el año se controla contra los mandatos igual que los de la OA. Si la OA ya tiene ese año, se usa el de la OA.

Uso:  python3 -I scripts/construir_ddjj.py   (después de construir_datos.py)
"""
import collections, csv, glob, gzip, io, json, re, sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from construir_datos import norm, provincia, titulo  # noqa: E402

RAIZ = Path(__file__).resolve().parent.parent
ENT = RAIZ / "storage" / "oficial" / "ddjj"
SAL = RAIZ / "storage" / "verificado"
ORGANISMOS = {"HONORABLE CAMARA DE DIPUTADOS DE LA NACION": "Diputados", "HONORABLE SENADO DE LA NACION": "Senadores"}
TIPOS = {"Anual": 0, "Inicial": 1, "Baja": 2}
MONTO = re.compile(r"^(-?)(\d*)-(\d{2})$")
CARGO_LEG = re.compile(r"^(diputad[oa]|dipurad[oa]|senador[a]?)\s+(nacional|de\s+la\s+naci[oó]n)(\s+titular)?(\s+por\s+.+)?$", re.I)
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
    """Listados históricos de cada cámara: id -> (apellido, nombres, provincia, [(desde, hasta)])."""
    of = RAIZ / "storage" / "oficial"
    hist = {"Diputados": {}, "Senadores": {}}

    def prov(x):
        try:
            return provincia(x)
        except ValueError:
            return ""
    for x in json.loads(gzip.open(of / "hcdn" / "diputados_historico.json.gz", "rt", encoding="utf-8", errors="replace").read()):
        h = hist["Diputados"].setdefault(str(x["ID"]), (tokens(x["APELLIDO"]), tokens(x["NOMBRE"]), prov(x.get("DISTRITO") or ""), []))
        h[3].append((min(x["INICIO"][:10], (x.get("JURAMENTO") or x["INICIO"])[:10]), max(x["FIN"][:10], (x.get("CESE") or x["FIN"])[:10])))
    for x in json.loads(gzip.open(of / "senado" / "senadores_no_activos.json.gz", "rt", encoding="utf-8", errors="replace").read())["table"]["rows"]:
        ap, _, no = (x.get("SENADOR") or "").partition(",")
        c = x["CESE PERIODO REAL"]
        h = hist["Senadores"].setdefault(str(x["ID"]), (tokens(ap), tokens(no), prov(x.get("PROVINCIA") or ""), []))
        h[3].append((x["INICIO PERIODO REAL"][:10], c[:10] if c[:2] in ("19", "20") else "2099-12-31"))
    for x in json.loads(gzip.open(of / "senado" / "senadores_activos.json.gz", "rt", encoding="utf-8", errors="replace").read())["table"]["rows"]:
        c = x["C_LEGAL"]
        h = hist["Senadores"].setdefault(str(x["ID"]), (tokens(x["APELLIDO"]), tokens(x["NOMBRE"]), prov(x.get("PROVINCIA") or ""), []))
        h[3].append((x["D_REAL"][:10], c[:10] if c[:2] in ("19", "20") else "2099-12-31"))
    return hist


def en_mandato(anio, tipo, mand):
    """El año fiscal tiene que tocar un mandato. Se acepta el año anterior a asumir (declaración inicial) y una baja hasta un año después."""
    y = int(anio)
    if any(i <= f"{y + 1}-12-31" and f >= f"{y}-01-01" for i, f in mand):
        return True
    return tipo == "Baja" and any(f >= f"{y - 1}-01-01" and i <= f"{y}-12-31" for i, f in mand)


ACENTOS = {"nacion": "nación", "educacion": "educación", "produccion": "producción", "economia": "economía",
           "administracion": "administración", "direccion": "dirección", "comision": "comisión", "republica": "república",
           "camara": "cámara", "accion": "acción", "gestion": "gestión", "mineria": "minería", "coordinacion": "coordinación",
           "participacion": "participación", "programacion": "programación", "ganaderia": "ganadería", "informacion": "información",
           "planificacion": "planificación", "regulacion": "regulación", "organizacion": "organización", "comunicacion": "comunicación",
           "innovacion": "innovación", "promocion": "promoción", "energia": "energía", "tecnologia": "tecnología", "politica": "política",
           "politicas": "políticas", "publica": "pública", "publico": "público", "publicas": "públicas", "publicos": "públicos",
           "juridicos": "jurídicos", "tecnico": "técnico", "agricola": "agrícola", "turistico": "turístico", "cordoba": "córdoba",
           "rios": "ríos", "tucuman": "tucumán", "neuquen": "neuquén", "rio": "río"}


def acentos(c):
    def cambio(m):
        w = m.group(0)
        b = ACENTOS.get(w.lower())
        if not b:
            return w
        return b.upper() if w.isupper() and len(w) > 1 else (b[:1].upper() + b[1:] if w[:1].isupper() else b)
    return re.sub(r"[A-Za-z]+", cambio, c)


def cargo_legible(r, legislador):
    """El cargo tal como lo declaró la persona (y el organismo, si no es de legislador), sin mayúsculas sostenidas ni errores de tipeo frecuentes."""
    c = re.sub(r"\s+", " ", (r.get("cargo") or "").strip(" :.-\t"))
    if c.isupper() or legislador:
        c = c.lower()
    c = acentos(re.sub(r"\bdipurad", "diputad", c, flags=re.I))
    c = c[:1].upper() + c[1:] if c else "Cargo no informado"
    if legislador:
        return c
    org = re.sub(r"\s+", " ", (r.get("organismo") or "").strip())
    if org and not set(tokens(org)) <= set(tokens(r.get("funcionario_apellido_nombre"))):  # a veces el organismo trae el nombre de la persona
        o = re.sub(r"\(([^)]*)\)", lambda m: "(" + m.group(1).upper() + ")", titulo(org)) if org.isupper() else org
        return f"{c} · {acentos(o)}"
    if not (r.get("sector") or "").strip():
        return c + " (sin organismo: puede ser una candidatura)"
    return c


def main() -> int:
    csv.field_size_limit(10**8)
    legs = json.loads((SAL / "legisladores.json").read_text(encoding="utf-8"))
    hist = mandatos()
    filas, otras = [], collections.defaultdict(list)
    for ruta in sorted(glob.glob(str(ENT / "declaraciones_juradas_*.csv.gz"))):
        anio = re.search(r"_(\d{4})\.csv", ruta).group(1)
        with gzip.open(ruta, "rt", encoding="utf-8-sig", newline="") as fh:
            for r in csv.DictReader(fh):
                if None in r.values():
                    continue
                r["_anio"] = anio
                # filtrar_ddjj.py dejó los cargos de legislador nacional y los otros cargos de esas mismas personas. Muchas filas de
                # legisladores traen el organismo vacío o el de la actividad anterior: la cámara sale del cargo y, si el organismo es del Congreso, tiene que coincidir.
                org, cargo = (r.get("organismo") or "").strip(), re.sub(r"\s+", " ", (r.get("cargo") or "").strip(" :.-\t"))
                if not CARGO_LEG.match(cargo) and not (org in ORGANISMOS and re.match(r"^(diputad|senador)", cargo, re.I)):
                    # Las filas del Congreso con un cargo que no se reconoce ("Dipùtado", "Si", una fecha) no se pueden controlar
                    # contra los mandatos: no se usan.
                    if r.get("persona") and org not in ORGANISMOS:
                        otras[r["persona"]].append(r)
                    continue
                camara = "Senadores" if re.match(r"^senador", cargo, re.I) else "Diputados"
                if org in ORGANISMOS and camara != ORGANISMOS[org]:
                    continue
                r["_camara"] = camara
                filas.append(r)
    for r in filas:
        r["_tk"] = tokens(r["funcionario_apellido_nombre"])
    datos_leg = []
    for l in legs:
        ap = tokens(l["apellido"] or l["nombre"].split()[-1])
        datos_leg.append((l, ap, tokens(l["nombre"].replace(l["apellido"] or "", "", 1))))

    def cruzar(l, ap, resto, camara):
        """Filas de legislador de esa cámara que corresponden a l, o None si hay más de una persona posible."""
        cand = [i for i, r in enumerate(filas)
                if r["_camara"] == camara and set(ap) <= set(r["_tk"]) and (not resto or tiene(r["_tk"], resto[0]))]
        if not cand and len(ap) >= 2:
            # Apellido compuesto y un apodo como nombre ("Bertie Benegas Lynch"): se acepta si en esa cámara hay una sola persona con ese apellido.
            cand = [i for i, r in enumerate(filas) if r["_camara"] == camara and set(ap) <= set(r["_tk"])]
        grupos = agrupar([filas[i]["_tk"] for i in cand])
        if len(grupos) > 1:
            # 1) quedan las personas que tienen todos los nombres del legislador;
            # 2) se descartan las que corresponden mejor a otro legislador actual de esa cámara (Carlos Gutiérrez / Carlos Mario Gutiérrez).
            todos = [g for g in grupos if all(tiene(g, t) for t in resto)] or grupos
            otros = [(ap2, resto2) for l2, ap2, resto2 in datos_leg if l2 is not l and l2["camara"] == camara]
            grupos = [g for g in todos if not any(set(ap2) <= set(g) and all(tiene(g, t) for t in resto2)
                                                  and len(ap2) + len(resto2) > len(ap) + len(resto) for ap2, resto2 in otros)]
            if len(grupos) == 1:
                cand = [i for i in cand if compatibles(filas[i]["_tk"], grupos[0])]
        return None if len(grupos) > 1 else cand

    salida, ambiguos, fuera, saltadas, vacias, varias = {}, [], [], collections.Counter(), collections.Counter(), []
    otra_camara, con_otros = [], 0
    for l, ap, resto in datos_leg:
        cam = l["camara"]
        h = hist[cam].get(str(l.get("hcdn_id") if cam == "Diputados" else l.get("id_senado")))
        mand = h[3] if h else [(l.get("inicio") or "1900-01-01", "2099-12-31")]
        cand = cruzar(l, ap, resto, cam)
        if cand is None:
            ambiguos.append(l["nombre"])
            continue
        elegidas = []
        for i in cand:
            r = filas[i]
            if en_mandato(r["_anio"], r["tipo_declaracion_jurada_descripcion"], mand):
                elegidas.append(r)
            else:
                # Mismo nombre pero un año en que esa persona no era legisladora en esta cámara: puede ser un homónimo. No se publica.
                fuera.append(f"{l['nombre']} {r['_anio']}")
        if not elegidas:
            # Sin declaraciones como legislador de esta cámara: se buscan en la otra, si en su listado histórico hay una sola
            # persona con ese nombre y de esa provincia, y cada año cae en uno de sus mandatos.
            otra = "Senadores" if cam == "Diputados" else "Diputados"
            prov = provincia(l["provincia"])
            ids = [k for k, (ap2, no2, pv, _) in hist[otra].items()
                   if set(ap) <= set(ap2) and (not resto or tiene(no2, resto[0])) and pv == prov]
            c2 = cruzar(l, ap, resto, otra) if len(ids) == 1 else None
            if c2:
                elegidas = [filas[i] for i in c2 if en_mandato(filas[i]["_anio"], filas[i]["tipo_declaracion_jurada_descripcion"], hist[otra][ids[0]][3])]
                if elegidas:
                    otra_camara.append(l["nombre"])
        if not elegidas:
            continue
        personas = {r["persona"] for r in elegidas if r.get("persona")}
        if len(personas) == 1:
            extra = otras.get(next(iter(personas)), [])
            con_otros += bool(extra)
        else:
            extra = []
            if len(personas) > 1:
                varias.append(l["nombre"])  # más de una persona con ese nombre: no se suman otros cargos
        for r in elegidas:
            r["_leg"] = True
        for r in extra:
            r["_leg"] = False
        por_anio = collections.defaultdict(list)
        for r in elegidas + extra:
            por_anio[r["_anio"]].append(r)
        anios = []
        for anio, rs in sorted(por_anio.items(), reverse=True):
            # Una declaración por año: Anual antes que Inicial y Baja, la de legislador antes que otro cargo, la más rectificada.
            r = sorted(rs, key=lambda x: (TIPOS.get(x["tipo_declaracion_jurada_descripcion"], 9), not x["_leg"],
                                          -int(x["rectificativa"] or 0), -int(x["dj_id"] or 0)))[0]
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
                "cargo": cargo_legible(r, r["_leg"]), "camara": r["_camara"] if r["_leg"] else None,
                "bienes_inicio": bi, "deudas_inicio": di, "bienes_final": bf, "deudas_final": df or 0.0,
                "patrimonio_neto_final": round(bf - (df or 0.0), 2),
                "ingresos_netos": monto(r["ingresos_neto_gastos"]),
            })
        if anios:
            salida[l["slug"] if cam == "Diputados" else "s:" + l["slug"]] = anios

    # Diputados: declaraciones de los años que la OA todavía no publicó, tomadas de los PDF de la ficha de cada diputado.
    hcdn_n, hcdn_sin, hcdn_fuera, hcdn_contraste = collections.Counter(), [], [], []
    for ruta in sorted(glob.glob(str(ENT / "hcdn_ddjj_*.csv.gz"))):
        with gzip.open(ruta, "rt", encoding="utf-8", newline="") as fh:
            hrows = list(csv.DictReader(fh))
        for r in hrows:
            ap_l, _, no_l = r["listado_apellido_nombre"].partition(",")
            try:
                pv = provincia(r["listado_distrito"])
            except ValueError:
                pv = ""
            cand = [(l, ap, resto) for l, ap, resto in datos_leg if l["camara"] == "Diputados" and set(ap) == set(tokens(ap_l))
                    and provincia(l["provincia"]) == pv and compatibles(resto, tokens(no_l))]
            if len(cand) != 1:
                hcdn_sin.append(r["listado_apellido_nombre"])
                continue
            l, ap, resto = cand[0]
            anio, tipo = int(r["periodo"]), r["tipo_declaracion_jurada_descripcion"]
            h = hist["Diputados"].get(str(l.get("hcdn_id")))
            mand = h[3] if h else [(l.get("inicio") or "1900-01-01", "2099-12-31")]
            cargo_pdf = re.sub(r"\s+", " ", r["cargo"] or "").strip(" :.-")
            leg = bool(CARGO_LEG.match(cargo_pdf)) and r["organismo"] in ("", "HONORABLE CAMARA DE DIPUTADOS DE LA NACION")
            if leg and not en_mandato(anio, tipo, mand):
                hcdn_fuera.append(f"{l['nombre']} {anio}")
                continue
            lista = salida.setdefault(l["slug"], [])
            if any(a["anio"] == anio for a in lista):
                continue  # la OA ya lo tiene
            bi, di, bf, df = (monto(r[c]) for c in ("total_bienes_inicio", "deudas_inicio", "total_bienes_final", "total_deudas_final"))
            if not bf:
                vacias[str(anio)] += 1
                continue
            previo = next((a for a in lista if a["anio"] == anio - 1), None)
            if previo and previo["bienes_final"] and bi is not None and abs(bi - previo["bienes_final"]) > 0.01 * previo["bienes_final"]:
                hcdn_contraste.append(f"{l['nombre']} ({previo['bienes_final']:,.0f} → {bi:,.0f})")
            lista.append({
                "anio": anio, "tipo": tipo, "rectificativa": int(r["rectificativa"] or 0),
                "cargo": cargo_legible({"cargo": cargo_pdf, "organismo": r["organismo"], "funcionario_apellido_nombre": r["funcionario_apellido_nombre"],
                                        "sector": "x"}, leg),
                "camara": "Diputados" if leg else None,
                "bienes_inicio": bi, "deudas_inicio": di, "bienes_final": bf, "deudas_final": df or 0.0,
                "patrimonio_neto_final": round(bf - (df or 0.0), 2), "ingresos_netos": monto(r["ingresos_neto_gastos"]),
                "fuente": "HCDN", "presentada": r["fecha_presentacion"], "url": r["url"],
            })
            lista.sort(key=lambda a: -a["anio"])
            hcdn_n[anio] += 1
    if hcdn_n:
        print("Declaraciones tomadas de las fichas de Diputados, por año:", dict(sorted(hcdn_n.items())))
    if hcdn_sin:
        print(f"Fichas de Diputados sin un diputado único del listado (no se usan): {len(hcdn_sin)}:", "; ".join(hcdn_sin))
    if hcdn_fuera:
        print("Fichas de Diputados fuera de mandato (no se publican):", "; ".join(hcdn_fuera))
    if hcdn_contraste:
        print(f"Bienes al inicio que no coinciden (más de 1%) con los del cierre anterior de la OA: {len(hcdn_contraste)}:", "; ".join(hcdn_contraste))

    if vacias:
        print("Declaraciones con bienes en cero (no se publican), por año:", dict(sorted(vacias.items())))
    if saltadas:
        print("Declaraciones sin monto interpretable, por año:", dict(sorted(saltadas.items())))
    if fuera:
        print(f"Años fuera de todo mandato del legislador (no se publican): {len(fuera)}:", "; ".join(fuera))
    if varias:
        print("Con más de una persona en sus filas (no se suman otros cargos):", "; ".join(varias))
    print(f"Cruzados por la otra cámara: {len(otra_camara)}:", "; ".join(otra_camara))
    por_anio_n = collections.Counter(a["anio"] for v in salida.values() for a in v)
    print("Legisladores actuales con declaración, por año fiscal:", dict(sorted(por_anio_n.items())))
    (SAL / "ddjj.json").write_text(json.dumps(salida, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"{len(filas)} declaraciones de legisladores nacionales; {len(salida)} legisladores actuales cruzados "
          f"({con_otros} con declaraciones de otros cargos); {len(ambiguos)} ambiguos")
    return 0


if __name__ == "__main__":
    sys.exit(main())
