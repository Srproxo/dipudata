#!/usr/bin/env python3
"""
Construye storage/verificado/cruces.json: siete cruces entre datos que el sitio ya publica, cada uno con su fuente y su método.
No agrega ninguna fuente nueva: lee lo que generan los demás scripts y el CSV oficial de dictámenes de Diputados.

Período: mandato actual (desde el 10/12/2025), salvo "provincia y leyes" (todas las actas con detalle) y "proyectos y
resultados" (todos los proyectos de los diputados actuales). Solo cuentan las votaciones válidas: las que cuadran con su
cabecera y no fueron anuladas (las mismas que usan las estadísticas de cada perfil).

Orden del día → comisiones: los títulos de las votaciones de Diputados traen el número de orden del día ("O.D. 209").
El CSV oficial de dictámenes (datos.hcdn.gob.ar) trae, para cada número y fecha de orden del día, las comisiones que lo
dictaminaron (GIRO) y el orden de giro (ORDEN 1 = primer giro). Un número se busca en el orden del día más reciente con ese
número publicado antes de la votación y hasta 400 días antes (la numeración vuelve a empezar cada período). Los que no
aparecen en el CSV (más nuevos que el archivo, o mal escritos) quedan sin comisión y se cuentan.

Cruces:
1. patrimonio_votos: votos en general de cada diputado en votaciones de temas económicos (orden del día cuya comisión
   cabecera, la de menor orden de giro, es una de COMISIONES_ECONOMICAS). El patrimonio lo une la página con las mismas cifras de la pestaña de declaración jurada.
2. presentismo_comisiones: presencia de cada diputado en las votaciones de órdenes del día de comisiones que integra
   (composición actual publicada por la Cámara) contra su presencia en todas las votaciones.
3. disciplina: votos emitidos distintos a la postura de su bloque en el acta (misma regla que /votaciones/{id}: mayoría
   estricta de presentes de un bloque con 2 o más presentes; empate = dividido, no cuenta). En Diputados, por eje temático
   (comisión cabecera del orden del día, tabla scripts/ejes_por_comision.csv).
4. afinidad: coincidencia de votos entre pares de legisladores de bloques distintos, solo en votaciones disputadas (la
   opción ganadora sacó menos del 80 % de los votos emitidos), con un mínimo de votaciones en común.
5. pasajes_presentismo: pasajes emitidos (viajes.json, por mes, desde diciembre de 2025) y presencia en las votaciones del
   mismo período (10/12/2025 al último día del último mes con pasajes).
6. provincias: votaciones cuyo título nombra una provincia con "provincia de", "prov.", "pcia." o "provincias de" delante
   (o la Ciudad Autónoma de Buenos Aires): cómo votó la delegación de esa provincia y cómo votó el resto de la cámara.
7. proyectos_resultados: proyectos de cada diputado actual, cuántos tienen dictamen, cuántos integraron un orden del día o un
   expediente que se votó en el recinto de Diputados y con qué resultado, y la resolución que registra el archivo de la Cámara.

Uso:  python3 -I scripts/construir_cruces.py
"""
import collections, csv, datetime, glob, gzip, hashlib, json, re, sys, unicodedata
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
VER = RAIZ / "storage" / "verificado"
DICT = RAIZ / "storage" / "oficial" / "ckan" / "hcdn" / "dictamenes"
DESDE = "2025-12-10"
EJES = ["Salud", "Educación", "Industria", "Comercio", "Previsión Social", "Medio Ambiente", "Seguridad", "Género",
        "Federalismo", "Transparencia", "Tecnología"]
# Comisiones que, como cabecera del dictamen (menor orden de giro), hacen "económica" a una votación.
COMISIONES_ECONOMICAS = ["PRESUPUESTO Y HACIENDA", "FINANZAS", "ECONOMIA", "LEGISLACION DEL TRABAJO",
                         "ANALISIS Y SEGUIMIENTO DEL CUMPLIMIENTO DE NORMAS TRIBUTARIAS Y PREVISIONALES"]
# Nombres con tilde para mostrar (el archivo de la Cámara los trae sin tildes).
NOMBRE_COMISION = {"PRESUPUESTO Y HACIENDA": "Presupuesto y Hacienda", "FINANZAS": "Finanzas", "ECONOMIA": "Economía",
                   "LEGISLACION DEL TRABAJO": "Legislación del Trabajo",
                   "ANALISIS Y SEGUIMIENTO DEL CUMPLIMIENTO DE NORMAS TRIBUTARIAS Y PREVISIONALES": "Análisis y Seguimiento del Cumplimiento de Normas Tributarias y Previsionales"}
UMBRAL_DISPUTADA = 0.8
MIN_COMUNES = {"D": 25, "S": 25}
PROVINCIAS = {  # nombre normalizado en títulos -> nombre en provincias.json
    "BUENOS AIRES": "Buenos Aires", "BS. AS.": "Buenos Aires", "BS.AS.": "Buenos Aires", "BS AS": "Buenos Aires",
    "CATAMARCA": "Catamarca", "CHACO": "Chaco", "CHUBUT": "Chubut", "CORDOBA": "Córdoba", "CORRIENTES": "Corrientes",
    "ENTRE RIOS": "Entre Ríos", "FORMOSA": "Formosa", "JUJUY": "Jujuy", "LA PAMPA": "La Pampa", "LA RIOJA": "La Rioja",
    "MENDOZA": "Mendoza", "MISIONES": "Misiones", "NEUQUEN": "Neuquén", "RIO NEGRO": "Río Negro", "SALTA": "Salta",
    "SAN JUAN": "San Juan", "SAN LUIS": "San Luis", "SANTA CRUZ": "Santa Cruz", "SANTA FE": "Santa Fe",
    "SANTIAGO DEL ESTERO": "Santiago del Estero", "TIERRA DEL FUEGO": "Tierra del Fuego", "TUCUMAN": "Tucumán",
}
CABA = "Ciudad Autónoma de Buenos Aires"


def norm(s: str) -> str:
    s = unicodedata.normalize("NFKD", (s or "").upper()).encode("ascii", "ignore").decode()
    return re.sub(r"\s+", " ", s).strip()


def norm_exp(e: str) -> str:
    m = re.match(r"0*(\d+)-([A-Z]+)-(\d{2,4})", e.strip().upper())
    if not m:
        return e.strip().upper()
    anio = int(m[3]) + (2000 if len(m[3]) == 2 else 0)
    return f"{int(m[1])}-{m[2]}-{anio}"


def leer(nombre: str):
    return json.loads((VER / nombre).read_text(encoding="utf-8"))


def distrito_a_provincia(d: str, nombres: dict) -> str | None:
    n = norm(d)
    if n in ("C.A.B.A.", "CABA") or n.startswith("CIUDAD AUTONOMA"):
        return CABA
    if n.startswith("TIERRA DEL FUEGO"):
        return "Tierra del Fuego"
    return nombres.get(n)


# --- Provincias nombradas en el título
_NOMBRES = sorted(PROVINCIAS, key=len, reverse=True)
_ALT = "|".join(re.escape(p) for p in _NOMBRES)
_RE_PROV = re.compile(r"\b(?:PROVINCIAS?|PROV\.?|PCIAS?\.?)\s+(?:DE\s+(?:LA\s+)?|DEL\s+)?((?:" + _ALT + r")(?:\s*(?:,|\bY\b|\bE\b)\s*(?:(?:LA|DE|DEL)\s+)?(?:" + _ALT + r"))*)")
_RE_CABA = re.compile(r"CIUDAD AUTONOMA DE (?:BUENOS AIRES|BS\.? ?AS)|C\.A\.B\.A|\bCABA\b")


def provincias_en(titulo: str) -> set:
    t = norm(titulo)
    out = set()
    if _RE_CABA.search(t):
        out.add(CABA)
    for m in _RE_PROV.finditer(t):
        for p in re.findall(_ALT, m[1]):
            out.add(PROVINCIAS[p])
    return out


def main() -> int:
    legs = leer("legisladores.json")
    por_clave = {}
    for l in legs:
        k = ("s:" if l["camara"] == "Senadores" else "") + l["slug"]
        por_clave[k] = l
    provs = leer("provincias.json")
    nombres_prov = {norm(p["nombre"]): p["nombre"] for p in provs}
    nombres_prov["RIO NEGRO"] = "Río Negro"
    slug_prov = {p["nombre"]: p["slug"] for p in provs}
    slug_prov.setdefault(CABA, "ciudad-autonoma-de-buenos-aires")

    # --- Tabla de ejes (la misma de construir_ejes.py)
    tabla_ejes = {}
    for r in csv.DictReader(open(RAIZ / "scripts" / "ejes_por_comision.csv", encoding="utf-8")):
        ejes = [e for e in r["ejes"].split("|") if e]
        if any(e not in EJES for e in ejes):
            raise SystemExit(f"eje desconocido en ejes_por_comision.csv: {r}")
        tabla_ejes[re.sub(r"^(DE LA |DE LOS |DE LAS |DE )", "", norm(r["comision"]))] = ejes

    def ejes_de(com: str) -> list:
        return tabla_ejes.get(re.sub(r"^(DE LA |DE LOS |DE LAS |DE )", "", norm(com)), [])

    # --- Dictámenes: orden del día -> [(fecha, {giro: orden}, {proyecto_id})]
    man = json.loads((DICT / "manifest.json").read_text(encoding="utf-8"))
    rec = man["recursos"]["59595a93-5a5e-4ba6-a3db-c1044e2f949e"]
    crudo = gzip.open(DICT / "59595a93_Dictamenes.csv.gz").read()
    if hashlib.sha256(crudo).hexdigest() != rec["sha256"]:
        raise SystemExit("el CSV de dictámenes no coincide con el sha256 del manifest")
    ods = collections.defaultdict(lambda: collections.defaultdict(lambda: {"giros": {}, "proyectos": set()}))
    for r in csv.DictReader(crudo.decode("utf-8-sig").splitlines()):
        if r["TIPO"] != "Orden del Día" or not r["NUMERO"].strip().isdigit() or not r["FECHA"][:10]:
            continue
        o = ods[int(r["NUMERO"])][r["FECHA"][:10]]
        if r["GIRO"].strip():
            g = norm(r["GIRO"])
            o["giros"][g] = min(o["giros"].get(g, 99), int(r["ORDEN"] or 99))
        o["proyectos"].add(r["EXPEDIENTE"])

    def od_de(num: int, fecha: str):
        cands = [f for f in ods.get(num, {}) if f <= fecha and
                 (datetime.date.fromisoformat(fecha) - datetime.date.fromisoformat(f)).days <= 400]
        return (max(cands), ods[num][max(cands)]) if cands else (None, None)

    re_od = re.compile(r"\b(?:O\.?\s?D\.?|ORDEN DEL DIA)\s*(?:N[º°O]\.?\s*)?(\d{1,4})\b")

    # --- Votaciones válidas
    indice = leer("votaciones/indice.json")
    actas = {}
    for v in indice:
        if not v.get("tiene_detalle"):
            continue
        d = json.loads((VER / "votaciones" / "actas" / f"{v['id']}.json").read_text(encoding="utf-8"))
        nota = str(d.get("nota") or "")
        if "no coincide" in nota or "anulada" in nota:
            continue
        actas[v["id"]] = (v, d)
    actuales = [(v, d) for v, d in actas.values() if v["f"] >= DESDE]
    meta_mand = leer("votaciones_mandato_actual.json")["_meta"]
    cuenta = collections.Counter(v["c"] for v, _ in actuales)
    if cuenta["D"] != meta_mand["validas"]["Diputados"] or cuenta["S"] != meta_mand["validas"]["Senadores"]:
        raise SystemExit(f"las votaciones válidas del mandato no cuadran con votaciones_mandato_actual.json: {cuenta}")
    hasta = max(v["f"] for v, _ in actuales)

    def en_general(t: str) -> bool:
        n = norm(t)
        return bool(re.search(r"EN GRAL|EN GENERAL|VOT\.? EN G\.", n)) or not re.search(r"PARTICULAR|TITULO|CAPITULO|\bART", n)

    # Comisiones de cada votación de Diputados del mandato
    od_vot, sin_od, od_no_hallado = {}, 0, []
    for v, d in actuales:
        if v["c"] != "D":
            continue
        nums = [int(x) for x in re_od.findall(norm(v["t"]))]
        if not nums:
            sin_od += 1
            continue
        giros, proys, fechas = {}, set(), []
        for n in nums:
            f, o = od_de(n, v["f"])
            if not o:
                od_no_hallado.append([v["id"], n])
                continue
            fechas.append(f)
            for g, ordn in o["giros"].items():
                giros[g] = min(giros.get(g, 99), ordn)
            proys |= o["proyectos"]
        if giros:
            od_vot[v["id"]] = {"od": nums, "giros": giros, "proyectos": proys}

    def ejes_votacion(vid: str) -> list:
        o = od_vot.get(vid)
        if not o:
            return []
        return sorted({e for g in cabecera(o) for e in ejes_de(g)})

    def cabecera(o) -> list:
        """Comisión o comisiones con el menor orden de giro del orden del día (la cabecera del dictamen)."""
        minimo = min(o["giros"].values())
        return [g for g, ordn in o["giros"].items() if ordn == minimo]

    def voto_por_clave(d) -> dict:
        return {f[4]: f[3] for f in d["votos"] if len(f) > 4 and f[4]}

    nombre_com = {}
    membresia = collections.defaultdict(set)
    for c in leer("comisiones_diputados.json"):
        nombre_com[norm(c["nombre"])] = c["nombre"]
        for m in c["integrantes"]:
            membresia[m["slug"]].add(norm(c["nombre"]))
    giros_vistos = {g for o in od_vot.values() for g in o["giros"]}
    giros_sin_comision = sorted(g for g in giros_vistos if g not in nombre_com)

    # ---------- 1. Patrimonio y votos económicos (Diputados)
    econ = []
    for v, d in sorted(actuales, key=lambda x: (x[0]["f"], x[0]["h"])):
        o = od_vot.get(v["id"])
        if v["c"] != "D" or not o or not en_general(v["t"]):
            continue
        coms = sorted(g for g in cabecera(o) if g in COMISIONES_ECONOMICAS)
        if coms:
            econ.append({"id": v["id"], "f": v["f"], "t": v["t"], "res": v["res"], "a": v["a"], "n": v["n"], "b": v["b"], "u": v["u"],
                         "comisiones": [NOMBRE_COMISION.get(g, g.title()) for g in coms], "votos": voto_por_clave(d)})
    votos_econ = collections.defaultdict(dict)
    for x in econ:
        for k, c in x.pop("votos").items():
            if k in por_clave and c in "ANBUP":
                votos_econ[k][x["id"]] = c

    # ---------- 2. Presentismo y comisiones (Diputados)
    pres_com = {}
    for l in legs:
        if l["camara"] != "Diputados":
            continue
        k = l["slug"]
        st = {"total": 0, "presente": 0, "com_total": 0, "com_presente": 0}
        mis = membresia.get(k, set())
        for v, d in actuales:
            if v["c"] != "D":
                continue
            c = voto_por_clave(d).get(k)
            if c not in ("A", "N", "B", "U"):
                continue
            st["total"] += 1
            st["presente"] += c != "U"
            o = od_vot.get(v["id"])
            if o and mis & set(o["giros"]):
                st["com_total"] += 1
                st["com_presente"] += c != "U"
        st["comisiones"] = sorted(nombre_com[g] for g in mis)
        if st["total"]:
            pres_com[k] = st

    # ---------- 3. Disciplina de bloque (ambas cámaras)
    disc = {}
    for v, d in actuales:
        bl = collections.defaultdict(collections.Counter)
        for f in d["votos"]:
            if f[3] in ("A", "N", "B"):
                bl[f[1]][f[3]] += 1
        postura = {}
        for b, cn in bl.items():
            if norm(b) in ("", "SIN DATO", "SIN BLOQUE", "SIN DATOS") or sum(cn.values()) < 2:
                continue
            top = cn.most_common()
            postura[b] = None if len(top) > 1 and top[0][1] == top[1][1] else top[0][0]
        ejes_v = ejes_votacion(v["id"]) if v["c"] == "D" else []
        for f in d["votos"]:
            if len(f) < 5 or f[4] not in por_clave or f[3] not in ("A", "N", "B") or not postura.get(f[1]):
                continue
            s = disc.setdefault(f[4], {"con_postura": 0, "distintos": 0, "ejes": collections.Counter(), "ejes_total": collections.Counter(), "lista": []})
            s["con_postura"] += 1
            for e in ejes_v:
                s["ejes_total"][e] += 1
            if f[3] != postura[f[1]]:
                s["distintos"] += 1
                for e in ejes_v:
                    s["ejes"][e] += 1
                s["lista"].append([v["id"], f[3], postura[f[1]], f[1]])
    for k, s in disc.items():
        s["ejes"] = {e: [s["ejes"][e], s["ejes_total"][e]] for e in EJES if s["ejes_total"][e]}
        del s["ejes_total"]
        s["lista"].sort(key=lambda x: actas[x[0]][0]["f"], reverse=True)

    # ---------- 4. Afinidad entre legisladores de bloques distintos
    afin = {}
    for cam in ("D", "S"):
        disput = []
        for v, d in actuales:
            if v["c"] != cam:
                continue
            emit = v["a"] + v["n"] + v["b"]
            if emit and max(v["a"], v["n"], v["b"]) / emit < UMBRAL_DISPUTADA:
                disput.append(voto_por_clave(d))
        claves = sorted(k for k in por_clave if (k.startswith("s:")) == (cam == "S"))
        pares = []
        for i, a in enumerate(claves):
            for b in claves[i + 1:]:
                if por_clave[a]["bloque"] == por_clave[b]["bloque"]:
                    continue
                com = coin = 0
                for vv in disput:
                    x, y = vv.get(a), vv.get(b)
                    if x in ("A", "N", "B") and y in ("A", "N", "B"):
                        com += 1
                        coin += x == y
                if com >= MIN_COMUNES[cam]:
                    pares.append([a, b, coin, com])
        pares.sort(key=lambda p: (-p[2] / p[3], -p[3], p[0], p[1]))
        # Promedio entre bloques (pares de bloques distintos con 2 o más legisladores cada uno)
        tam = collections.Counter(por_clave[k]["bloque"] for k in claves)
        acum = collections.defaultdict(lambda: [0, 0])
        for a, b, coin, com in pares:
            ba, bb = sorted([por_clave[a]["bloque"], por_clave[b]["bloque"]])
            if tam[ba] >= 2 and tam[bb] >= 2:
                acum[(ba, bb)][0] += coin
                acum[(ba, bb)][1] += com
        bloques = [[ba, bb, round(c / n * 100, 1), n] for (ba, bb), (c, n) in acum.items() if n]
        bloques.sort(key=lambda x: -x[2])
        afin[cam] = {"disputadas": len(disput), "pares_evaluados": len(pares), "top": pares[:400], "bloques": bloques}

    # ---------- 5. Pasajes y presentismo (Diputados)
    viajes = leer("viajes.json")
    rutas_meta = leer("rutas_pasajes.json")["_meta"]
    ultimo_mes = rutas_meta["hasta"]
    a, m = map(int, ultimo_mes.split("-"))
    fin_periodo = (datetime.date(a + (m == 12), m % 12 + 1, 1) - datetime.timedelta(days=1)).isoformat()
    pas = {}
    for l in legs:
        if l["camara"] != "Diputados":
            continue
        k = l["slug"]
        vt = pr = 0
        for v, d in actuales:
            if v["c"] != "D" or v["f"] > fin_periodo:
                continue
            c = voto_por_clave(d).get(k)
            if c in ("A", "N", "B", "U"):
                vt += 1
                pr += c != "U"
        p = viajes["actual"].get(k, {"aereo": 0, "terrestre": 0, "total": 0})
        if vt:
            pas[k] = {"pasajes": p["total"], "aereo": p["aereo"], "terrestre": p["terrestre"], "votaciones": vt, "presentes": pr}

    # ---------- 6. Provincias y votaciones que las nombran
    provincias = collections.defaultdict(list)
    for vid, (v, d) in actas.items():
        ps = provincias_en(v["t"])
        if not ps:
            continue
        for p in ps:
            dele, resto = collections.Counter(), collections.Counter()
            for f in d["votos"]:
                if f[3] not in ("A", "N", "B", "U"):
                    continue
                (dele if distrito_a_provincia(f[2], nombres_prov) == p else resto)[f[3]] += 1
            if sum(dele.values()) == 0:
                continue
            provincias[p].append({"id": vid, "c": v["c"], "f": v["f"], "t": v["t"], "res": v["res"], "otras": sorted(ps - {p}),
                                  "del": {x: dele[x] for x in "ANBU"}, "resto": {x: resto[x] for x in "ANBU"}})
    prov_out = {}
    for p, lista in provincias.items():
        lista.sort(key=lambda x: (x["f"], x["id"]), reverse=True)
        prov_out[slug_prov[p]] = {"nombre": p, "votaciones": lista}

    # ---------- 7. Proyectos y resultados (Diputados actuales)
    exp_votado = {}  # expediente (o proyecto HCDN) -> lista de (vid, res, general)
    for vid, (v, d) in actas.items():
        if v["c"] != "D":
            continue
        gen = en_general(v["t"])
        for e in v.get("e") or []:
            exp_votado.setdefault(norm_exp(e), []).append((vid, v["res"], gen, v["f"]))
        for n in re_od.findall(norm(v["t"])):
            f, o = od_de(int(n), v["f"])
            if o:
                for pid in o["proyectos"]:
                    exp_votado.setdefault(pid, []).append((vid, v["res"], gen, v["f"]))
    proy = {}
    for l in legs:
        if l["camara"] != "Diputados":
            continue
        ruta = VER / "proyectos" / f"{l['slug']}.json"
        if not ruta.exists():
            continue
        ps = json.loads(ruta.read_text(encoding="utf-8"))["proyectos"]
        st = collections.Counter()
        votados = []
        for x in ps:
            es_ley = x[2] == "Ley"
            st["presentados"] += 1
            st["ley"] += es_ley
            tiene_dict = bool(x[9])
            st["con_dictamen"] += tiene_dict
            st["ley_con_dictamen"] += tiene_dict and es_ley
            vs = exp_votado.get(x[0], []) + exp_votado.get(norm_exp(x[3]), [])
            vs = sorted({(a, b, c, f) for a, b, c, f in vs if f >= x[1]}, key=lambda z: z[3])
            if vs:
                st["votados"] += 1
                st["ley_votados"] += es_ley
                aprob = any(r == "AFIRMATIVO" and g for _, r, g, _ in vs)
                st["aprobados"] += aprob
                st["ley_aprobados"] += aprob and es_ley
                votados.append([x[3], x[2], x[4][:220], vs[-1][0], "AFIRMATIVO" if aprob else vs[-1][1]])
            if x[5] in ("APROBADO", "MEDIA SANCION", "SANCIONADO"):
                st["resolucion"] += 1
        proy[l["slug"]] = dict(st, votados_lista=votados[-30:])

    meta = {
        "desde": DESDE, "hasta": hasta, "generado": datetime.date.today().isoformat(),
        "votaciones_validas": dict(cuenta),
        "diputados_con_od": len(od_vot), "diputados_sin_od_en_titulo": sin_od, "od_no_hallados": od_no_hallado,
        "giros_sin_comision_actual": giros_sin_comision,
        "dictamenes": {"url": rec["url"], "sha256": rec["sha256"], "bajado": rec["bajado"], "modificado_en_el_portal": rec["last_modificado"][:10]},
        "comisiones_economicas": [NOMBRE_COMISION[c] for c in COMISIONES_ECONOMICAS],
        "umbral_disputada": UMBRAL_DISPUTADA, "min_comunes": MIN_COMUNES,
        "pasajes": {"desde": viajes["desde_mandato_actual"], "hasta_mes": ultimo_mes, "fin_periodo": fin_periodo},
    }
    salida = {"_meta": meta,
              "patrimonio_votos": {"votaciones": econ, "votos": votos_econ},
              "presentismo_comisiones": pres_com,
              "disciplina": disc,
              "afinidad": afin,
              "pasajes_presentismo": pas,
              "provincias": prov_out,
              "proyectos_resultados": proy}
    (VER / "cruces.json").write_text(json.dumps(salida, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
    print(f"votaciones válidas del mandato: {dict(cuenta)}; Diputados con orden del día y comisión: {len(od_vot)}; "
          f"sin O.D. en el título: {sin_od}; O.D. no hallados en el CSV: {len(od_no_hallado)} {od_no_hallado[:10]}")
    print(f"giros sin comisión actual: {giros_sin_comision}")
    print(f"1) votaciones económicas en general: {len(econ)}: " + "; ".join(f"{x['id']} {x['t'][:50]}" for x in econ))
    print(f"2) diputados con presentismo: {len(pres_com)}; con votaciones de sus comisiones: {sum(1 for s in pres_com.values() if s['com_total'])}")
    print(f"3) legisladores con disciplina: {len(disc)}")
    print(f"4) afinidad: " + ", ".join(f"{c}: {a['disputadas']} disputadas, {a['pares_evaluados']} pares" for c, a in afin.items()))
    print(f"5) pasajes: {len(pas)} diputados, período {DESDE} a {fin_periodo}")
    print(f"6) provincias: " + ", ".join(f"{v['nombre']} {len(v['votaciones'])}" for v in prov_out.values()))
    print(f"7) proyectos: {len(proy)} diputados; votados en el recinto: {sum(p.get('votados', 0) for p in proy.values())}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
