#!/usr/bin/env python3
"""
Construye storage/verificado/*.json, la única fuente de datos que lee el sitio.

Entradas (todas dentro del repositorio):
  storage/hcdn/diputados_actuales.csv   Padrón oficial de diputados en ejercicio (datos.hcdn.gob.ar)
  storage/oficial/senado/senadores_activos.json.gz   Padrón oficial de senadores en ejercicio (Senado de la Nación)
  storage/oficial/senado/senadores_no_activos.json.gz  Ex senadores (se usa para controlar el padrón)
  storage/oficial/hcdn/composicion_por_bloques.csv     Bloque de cada diputado (fuente de la pertenencia)
  storage/oficial/hcdn/composicion_por_interbloque.csv Interbloque de cada diputado
  storage/oficial/hcdn/diputados_historico.json.gz     Todos los mandatos de diputados (ID oficial, períodos, bloques)
  storage/oficial/hcdn/listado_bloques.csv / listado_interbloques.csv  Presidentes de bloque e interbloque
  storage/legisladores_master.json      Solo se toman la foto y el slug de los diputados, para no cambiar URLs.
                                        Se IGNORAN cuit, asistencia, lealtad, votaciones, patrimonio, biografía:
                                        eran valores generados sin fuente.

Uso:  python3 -I scripts/construir_datos.py
"""
import csv, gzip, json, re, sys, unicodedata, collections, datetime
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _carga import filas

RAIZ = Path(__file__).resolve().parent.parent
HCDN = RAIZ / "storage" / "hcdn" / "diputados_actuales.csv"
OFI_HCDN = RAIZ / "storage" / "oficial" / "hcdn"
MASTER = RAIZ / "storage" / "legisladores_master.json"
SALIDA = RAIZ / "storage" / "verificado"

PROVINCIAS = {
    "BUENOS AIRES": "Buenos Aires", "CATAMARCA": "Catamarca", "CHACO": "Chaco", "CHUBUT": "Chubut",
    "CIUDAD DE BUENOS AIRES": "Ciudad Autónoma de Buenos Aires",
    "CIUDAD AUTONOMA DE BUENOS AIRES": "Ciudad Autónoma de Buenos Aires",
    "CABA": "Ciudad Autónoma de Buenos Aires",
    "CORDOBA": "Córdoba", "CORRIENTES": "Corrientes", "ENTRE RIOS": "Entre Ríos", "FORMOSA": "Formosa",
    "JUJUY": "Jujuy", "LA PAMPA": "La Pampa", "LA RIOJA": "La Rioja", "MENDOZA": "Mendoza",
    "MISIONES": "Misiones", "NEUQUEN": "Neuquén", "RIO NEGRO": "Río Negro", "SALTA": "Salta",
    "SAN JUAN": "San Juan", "SAN LUIS": "San Luis", "SANTA CRUZ": "Santa Cruz", "SANTA FE": "Santa Fe",
    "SANTIAGO DEL ESTERO": "Santiago del Estero", "TIERRA DEL FUEGO": "Tierra del Fuego", "TUCUMAN": "Tucumán",
    "TIERRA DEL FUEGO, ANTARTIDA E ISLAS DEL ATLANTICO SUR": "Tierra del Fuego",
}
SIGLAS = {"PRO", "UCR", "MID", "PTS", "FIT", "PO", "CC", "ARI", "LLA", "UXP"}
MINUSCULAS = {"de", "del", "la", "las", "los", "y", "e", "en", "el", "por", "a"}
# Los senadores vienen con siglas o nombres abreviados: se llevan al nombre oficial del bloque.
ALIAS_BLOQUE = {
    "LLA": "LA LIBERTAD AVANZA", "UXP": "UNIÓN POR LA PATRIA", "UCR": "UCR - UNIÓN CÍVICA RADICAL",
    "PJ": "JUSTICIALISTA",
}


def leer_csv(ruta):
    with Path(ruta).open(encoding="utf-8", newline="") as f:
        return [r for r in csv.DictReader(f) if any(v for v in r.values())]


def sin_tildes(s: str) -> str:
    return "".join(c for c in unicodedata.normalize("NFD", s) if unicodedata.category(c) != "Mn")


def norm(s: str) -> str:
    return re.sub(r"\s+", " ", re.sub(r"[^a-z0-9 ]", " ", sin_tildes(s).lower())).strip()


def slugify(s: str) -> str:
    return re.sub(r"-+", "-", re.sub(r"[^a-z0-9]+", "-", sin_tildes(s).lower())).strip("-")


def provincia(s: str) -> str:
    k = sin_tildes(s).upper().strip()
    if k not in PROVINCIAS:
        raise ValueError(f"Provincia desconocida: {s!r}")
    return PROVINCIAS[k]


def titulo(s: str) -> str:
    out = []
    for i, p in enumerate(re.split(r"(\s+|-)", s.strip())):
        if not p.strip() or p == "-":
            out.append(p)
        elif sin_tildes(p.upper()) in SIGLAS:
            out.append(p.upper())
        elif i > 0 and p.lower() in MINUSCULAS:
            out.append(p.lower())
        else:
            w = p[:1].upper() + p[1:].lower()
            out.append(re.sub(r"(\'|-)([a-záéíóúñ])", lambda m: m.group(1) + m.group(2).upper(), w))
    return "".join(out)


def bloque_oficial(s: str) -> str:
    s = re.sub(r"\s+", " ", (s or "").strip()).upper()
    s = ALIAS_BLOQUE.get(s, s)
    return s or "SIN BLOQUE"


def fecha_iso(s: str):
    try:
        return datetime.datetime.strptime(s.strip(), "%d/%m/%Y").date().isoformat()
    except Exception:
        return None


def fecha_iso_iso(s: str):
    s = (s or "").strip()
    return s if re.match(r"^\d{4}-\d{2}-\d{2}$", s) else None


def main() -> int:
    master = json.loads(MASTER.read_text(encoding="utf-8"))
    avisos = []

    # Índice del master por nombre normalizado (tokens ordenados) + provincia
    def clave(nombre: str, prov: str) -> str:
        return " ".join(sorted(norm(nombre).split())) + "|" + prov

    idx = {}
    for m in master:
        if m["camara"] != "Diputados":
            continue
        idx.setdefault(clave(m["nombre_completo"], provincia(m["provincia"])), []).append(m)

    legisladores = []
    usados = set()

    # ---- Diputados: padrón oficial HCDN
    with HCDN.open(encoding="utf-8", newline="") as f:
        for r in csv.DictReader(f):
            nombre = f'{r["NOMBRE"].strip()} {r["APELLIDO"].strip()}'
            prov = provincia(r["DISTRITO"])
            cands = [m for m in idx.get(clave(nombre, prov), []) if m["id"] not in usados]
            if not cands:
                # Nombres abreviados o con segundo nombre: mismo distrito y un nombre contenido en el otro.
                tk = set(norm(nombre).split())
                cands = [m for k, ms in idx.items() if k.endswith("|" + prov) for m in ms
                         if m["id"] not in usados and (tk <= set(norm(m["nombre_completo"]).split())
                                                      or set(norm(m["nombre_completo"]).split()) <= tk)]
                if len(cands) != 1:
                    cands = []
            m = cands[0] if cands else None
            if m:
                usados.add(m["id"])
            else:
                avisos.append(f"Diputado sin ficha en master (sin foto): {nombre} ({prov})")
            nom = re.sub(r"\s+", " ", nombre.replace('"', "")).strip()
            legisladores.append({
                "nombre": nom,
                "apellido": r["APELLIDO"].strip(),
                "camara": "Diputados",
                "provincia": prov,
                "bloque": titulo(bloque_oficial(r["BLOQUE"])),
                "bloque_oficial": bloque_oficial(r["BLOQUE"]),
                "mandato": r["MANDATO"].strip(),
                "jura": fecha_iso(r["FECHA_DE_JURA"]),
                "inicio": fecha_iso(r["FECHA_DE_INICIO"]),
                "sexo": r["SEXO"].strip() or None,
                "foto": (m or {}).get("foto_url") or None,
                "slug_previo": (m or {}).get("slug"),
                "verificado": True,
                "fuentes": {
                    "nombre, provincia, bloque, mandato, jura": "Padrón oficial de diputados en ejercicio, HCDN (datos.hcdn.gob.ar)",
                    "foto": "ArgentinaDatos" if m and m.get("foto_url") else None,
                },
            })

    # ---- Senadores: padrón oficial del Senado de la Nación (datos abiertos)
    idx_sen = {}
    for m in master:
        if m["camara"] == "Senadores":
            idx_sen.setdefault(clave(m["nombre_completo"], provincia(m["provincia"])), []).append(m)
    for r in filas("senadores_activos"):
        nombre = titulo(f'{r["NOMBRE"].strip()} {r["APELLIDO"].strip()}')
        prov = provincia(r["PROVINCIA"])
        m = (idx_sen.get(clave(nombre, prov)) or [None])[0]
        mandato = f'{r["D_LEGAL"][:4]}-{r["C_LEGAL"][:4]}' if r["D_LEGAL"][:4].isdigit() and r["C_LEGAL"][:4].isdigit() else None
        foto = r["FOTO"].strip() if r["FOTO"].strip().startswith("https://www.senado.gob.ar/") else None
        legisladores.append({
            "nombre": nombre, "apellido": titulo(r["APELLIDO"].strip()), "camara": "Senadores", "provincia": prov,
            "bloque": titulo(bloque_oficial(r["BLOQUE"])), "bloque_oficial": bloque_oficial(r["BLOQUE"]),
            "alianza": titulo(r["PARTIDO O ALIANZA"].strip()) if r["PARTIDO O ALIANZA"].strip() else None,
            "mandato": mandato, "jura": None, "inicio": fecha_iso_iso(r["D_REAL"]), "sexo": None,
            "email": r["EMAIL"].strip() or None, "telefono": r["TELEFONO"].strip() or None,
            "foto": foto, "slug_previo": (m or {}).get("slug"), "verificado": True,
            "fuentes": {
                "nombre, provincia, bloque, mandato, contacto": "Padrón de senadores en ejercicio, Senado de la Nación (datos abiertos)",
                "foto": "Senado de la Nación" if foto else None,
            },
        })

    # ---- Slugs únicos y estables
    vistos = collections.Counter()
    for l in legisladores:
        base = l["slug_previo"] or slugify(l["nombre"])
        vistos[(l["camara"], base)] += 1
        l["slug"] = base if vistos[(l["camara"], base)] == 1 else f"{base}-{slugify(l['provincia'])}"
        del l["slug_previo"]
        l["fuentes"] = {k: v for k, v in l["fuentes"].items() if v}
    legisladores.sort(key=lambda l: (norm(l["apellido"] or l["nombre"].split()[-1]), norm(l["nombre"])))


    # ---- Bloques e interbloques de Diputados: composición oficial (más reciente que el padrón)
    def buscar_dip(ap: str, no: str):
        tk = set(norm(f"{no} {ap}").split())
        c = [l for l in legisladores if l["camara"] == "Diputados"
             and (tk <= set(norm(l["nombre"]).split()) or set(norm(l["nombre"]).split()) <= tk)]
        if len(c) > 1:
            exacto = [l for l in c if set(norm(l["nombre"]).split()) == tk]
            c = exacto or c
        if len(c) == 1:
            return c[0]
        # apellido con otra grafía (p. ej. Veresay / Verasay): mismo nombre de pila y apellido parecido
        import difflib
        nombres = set(norm(no).split())
        c = [l for l in legisladores if l["camara"] == "Diputados" and nombres <= set(norm(l["nombre"]).split())
             and difflib.SequenceMatcher(None, norm(ap), norm(l["apellido"] or l["nombre"].split()[-1])).ratio() >= 0.8]
        return c[0] if len(c) == 1 else None

    for r in leer_csv(OFI_HCDN / "composicion_por_bloques.csv"):
        l = buscar_dip(r["APELLIDO"], r["NOMBRE"])
        if not l:
            avisos.append(f"Composición de bloques: sin diputado para {r['APELLIDO']}, {r['NOMBRE']}")
            continue
        oficial = bloque_oficial(r["BLOQUE"])
        if norm(oficial) != norm(l["bloque_oficial"]):
            avisos.append(f"Bloque de {l['nombre']}: padrón dice {l['bloque_oficial']}, composición dice {oficial} (se usa la composición)")
        l["bloque_oficial"], l["bloque"] = oficial, titulo(oficial)
        l["fuentes"]["bloque"] = "Composición actual por bloques, HCDN (datos.hcdn.gob.ar)"
    for l in legisladores:
        l["interbloque"] = None
    for r in leer_csv(OFI_HCDN / "composicion_por_interbloque.csv"):
        l = buscar_dip(r["APELLIDO"], r["NOMBRE"])
        if l:
            l["interbloque"] = titulo(re.sub(r"^INTERBLOQUE\s+", "", r["INTERBLOQUE"].strip().upper()))
            l["fuentes"]["interbloque"] = "Composición actual por interbloque, HCDN (datos.hcdn.gob.ar)"
        else:
            avisos.append(f"Composición de interbloques: sin diputado para {r['APELLIDO']}, {r['NOMBRE']}")
    presidentes = {}
    for r in leer_csv(OFI_HCDN / "listado_bloques.csv"):
        l = buscar_dip(r["DIPUTADO_APELLIDO"], r["DIPUTADO_NOMBRE"])
        if l:
            presidentes[l["bloque_oficial"]] = l
            l["preside_bloque"] = True
        else:
            avisos.append(f"Presidente de bloque sin diputado: {r['DIPUTADO_APELLIDO']}, {r['DIPUTADO_NOMBRE']} ({r['BLOQUE']})")
    for l in legisladores:
        l.setdefault("preside_bloque", False)


    # ---- Trayectoria: mandatos y bloques anteriores, del histórico oficial de la HCDN
    txt = gzip.open(OFI_HCDN / "diputados_historico.json.gz", "rt", encoding="utf-8", errors="replace").read()
    hist = json.loads(re.sub(r",(\s*[}\]])", r"\1", txt), strict=False)
    hoy = datetime.date.today().isoformat()
    vigentes = [h for h in hist if h["FIN"][:10] >= hoy]
    for l in legisladores:
        if l["camara"] != "Diputados":
            continue
        tk = set(norm(l["nombre"]).split())
        ids = {h["ID"] for h in vigentes
               if provincia(h["DISTRITO"]) == l["provincia"]
               and (set(norm(f'{h["NOMBRE"]} {h["APELLIDO"]}').split()) >= tk or set(norm(f'{h["NOMBRE"]} {h["APELLIDO"]}').split()) <= tk)}
        if len(ids) != 1:
            avisos.append(f"Histórico HCDN: {len(ids)} coincidencias para {l['nombre']}")
            l["hcdn_id"], l["trayectoria"] = None, []
            continue
        hid = ids.pop()
        mandatos = collections.OrderedDict()
        for h in sorted((x for x in hist if x["ID"] == hid), key=lambda x: (x["INICIO"], x["BLOQUE_INICIO"]), reverse=True):
            k = (h["INICIO"][:10], h["FIN"][:10], h["DISTRITO"])
            m = mandatos.setdefault(k, {"inicio": k[0], "fin": k[1], "cese": h["CESE"][:10] if h.get("CESE") else None,
                                        "distrito": provincia(h["DISTRITO"]), "bloques": []})
            m["bloques"].append({"nombre": titulo(bloque_oficial(h["BLOQUE"])), "desde": h["BLOQUE_INICIO"][:10], "hasta": h["BLOQUE_FIN"][:10]})
        l["hcdn_id"] = hid
        l["trayectoria"] = list(mandatos.values())
        l["fuentes"]["trayectoria"] = "Histórico de diputados 2007-2027, HCDN (datos.hcdn.gob.ar). No incluye mandatos anteriores a 2007"

    # ---- Bloques y provincias (derivados del padrón, nunca cargados a mano)
    bloques = []
    for camara in ("Diputados", "Senadores"):
        c = collections.defaultdict(list)
        for l in legisladores:
            if l["camara"] == camara:
                c[(l["bloque"], l["bloque_oficial"])].append(l)
        for (nombre, oficial), miembros in sorted(c.items(), key=lambda kv: (-len(kv[1]), kv[0][0])):
            pres = presidentes.get(oficial) if camara == "Diputados" else None
            inter = sorted({m["interbloque"] for m in miembros if m.get("interbloque")})
            bloques.append({
                "camara": camara, "nombre": nombre, "nombre_oficial": oficial, "slug": slugify(nombre),
                "cantidad": len(miembros),
                "provincias": sorted({m["provincia"] for m in miembros}),
                "presidente": pres["nombre"] if pres else None,
                "presidente_slug": pres["slug"] if pres else None,
                "interbloque": inter[0] if len(inter) == 1 else None,
            })
    provincias = []
    for nombre in sorted(set(PROVINCIAS.values()), key=norm):
        d = [l for l in legisladores if l["provincia"] == nombre and l["camara"] == "Diputados"]
        s = [l for l in legisladores if l["provincia"] == nombre and l["camara"] == "Senadores"]
        provincias.append({"nombre": nombre, "slug": slugify(nombre), "diputados": len(d), "senadores": len(s)})

    interbloques = []
    pres_int = {}
    for r in leer_csv(OFI_HCDN / "listado_interbloques.csv"):
        ap, _, no = r["PRESIDENTE"].partition(",")
        pres_int[titulo(re.sub(r"^INTERBLOQUE\s+", "", r["INTERBLOQUE"].upper()))] = (buscar_dip(ap.strip(), no.strip()), int(r["CANTIDAD"]))
    for nombre in sorted({l["interbloque"] for l in legisladores if l.get("interbloque")}):
        miembros = [l for l in legisladores if l.get("interbloque") == nombre]
        pres, esperado = pres_int.get(nombre, (None, None))
        if esperado is not None and esperado != len(miembros):
            avisos.append(f"Interbloque {nombre}: el listado dice {esperado} y la composición {len(miembros)}")
        interbloques.append({
            "camara": "Diputados", "nombre": nombre, "slug": slugify(nombre), "cantidad": len(miembros),
            "presidente": pres["nombre"] if pres else None, "presidente_slug": pres["slug"] if pres else None,
            "bloques": sorted({m["bloque"] for m in miembros}),
        })

    # ---- Controles de integridad: el script falla si algo no cuadra
    dip = [l for l in legisladores if l["camara"] == "Diputados"]
    sen = [l for l in legisladores if l["camara"] == "Senadores"]
    if len(dip) != 257:
        sys.exit(f"ERROR: se esperaban 257 diputados y hay {len(dip)}")
    for p in provincias:
        if p["senadores"] > 3:
            sys.exit(f"ERROR: {p['nombre']} tiene {p['senadores']} senadores (máximo 3)")
        if p["senadores"] != 3:
            sys.exit(f"ERROR: {p['nombre']} tiene {p['senadores']} senadores (deben ser 3)")
    if len(sen) != 72:
        sys.exit(f"ERROR: se esperaban 72 senadores y hay {len(sen)}")
    if len({l["slug"] for l in legisladores if l["camara"] == "Diputados"}) != len(dip):
        sys.exit("ERROR: slugs duplicados")

    informe = {
        "generado": datetime.date.today().isoformat(),
        "diputados": len(dip), "senadores": len(sen), "senadores_esperados": 72,
        "avisos": avisos,
    }
    SALIDA.mkdir(parents=True, exist_ok=True)
    for nombre, datos in (("legisladores", legisladores), ("bloques", bloques), ("interbloques", interbloques), ("provincias", provincias), ("informe", informe)):
        (SALIDA / f"{nombre}.json").write_text(json.dumps(datos, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print(f"{len(dip)} diputados, {len(sen)} senadores, {len(bloques)} bloques, {len(provincias)} provincias")
    for a in avisos:
        print("AVISO:", a)
    return 0


if __name__ == "__main__":
    sys.exit(main())
