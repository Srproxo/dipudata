#!/usr/bin/env python3
"""
Proyectos parlamentarios de cada diputado actual, con lo que pasó con ellos.

Fuentes (datos.hcdn.gob.ar, guardadas en storage/oficial/hcdn/):
  proyectos_parlamentarios.csv.gz  "Proyectos parlamentarios": una fila por proyecto con UN autor ("APELLIDO, NOMBRES"), sin ID de
                                   persona y sin cofirmantes.
  resultado_proyectos.csv.gz       "Resultado de proyectos": movimientos que resolvió la Cámara (proyecto_id, fecha, movimiento, resultado).
  diputados_historico.json.gz      todos los mandatos de diputados, con ID oficial (HCDN...) y fechas.

Como el conjunto de proyectos no trae ID de autor, el cruce se hace por nombre completo (apellido y todos los nombres) contra el histórico
oficial y se acepta solo si:
  1. el nombre corresponde a un único ID oficial,
  2. esa persona tenía mandato (desde el juramento hasta el cese) en la fecha de publicación del proyecto,
  3. ese ID es el de un diputado actual (hcdn_id de legisladores.json).
Lo que no cumple se descarta y se cuenta (ver storage/verificado/proyectos/_meta.json).

Salidas (storage/verificado/proyectos/):
  <slug>.json  {"resumen": {...}, "proyectos": [[id, fecha, tipo, expediente, titulo, resultado, fecha_resultado], ...]} de más nuevo a más viejo
  _meta.json   cobertura y descartes

Senado: no hay conjunto abierto de proyectos; se usa la lista de cada senador en senado.gob.ar (scripts/bajar_proyectos_senado.py), donde el
senador se identifica por el ID oficial de la URL. Se publican los proyectos de ley, declaración, comunicación, resolución y decreto (códigos PL, PD,
PC, PR y DE del expediente); las comunicaciones y notas (CO, CC) no son proyectos y se descartan. El año del expediente tiene que ser el de la fecha de
mesa de entradas (o el anterior en enero a marzo: la numeración sigue el período parlamentario). La página del Senado no informa qué pasó con cada proyecto.
Las salidas del Senado se guardan como s-<slug>.json.

Cada proyecto lleva al final dos datos más, para el gráfico de proyectos por año: ROL y RECONOCIMIENTO.
  - Rol, Senado: el extracto empieza con los firmantes ("SORIA Y OTROS:", "MARKS Y SORIA:"). Si el primero es el propio senador, el proyecto es
    "p" (propio: primer firmante); si es otro, "f" (firmó junto con otros); si el extracto no trae firmantes, "n" (sin dato).
  - Rol, Diputados: el archivo oficial registra un solo autor por proyecto y no trae cofirmantes: todo es "p" y no se puede distinguir a los firmantes.
  - Reconocimiento (1/0): proyectos de declaración, resolución o comunicación cuyo título pide o expresa reconocimiento, beneplácito, homenaje,
    felicitación, conmemoración, distinción o adhesión, o declara de interés algo. Es una regla de texto (ver RECONOCIMIENTO): se muestra en pantalla.
Además se calcula el promedio de proyectos por legislador de cada cámara y año (_promedios.json): proyectos de los legisladores actuales de esa cámara en
ese año dividido por los años completos de funciones que sumaron ese año (un legislador que asumió el 10/12 cuenta 21/365; según los mandatos oficiales).

Uso:  python3 -I scripts/construir_proyectos.py   (después de construir_datos.py)
"""
import collections, csv, gzip, html, json, re, shutil, statistics, sys, unicodedata
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
OFI = RAIZ / "storage" / "oficial" / "hcdn"
SAL = RAIZ / "storage" / "verificado"


def norm(s: str) -> str:
    s = "".join(c for c in unicodedata.normalize("NFD", s) if unicodedata.category(c) != "Mn").lower()
    return re.sub(r"\s+", " ", re.sub(r"[^a-z0-9 ]", " ", s)).strip()


def leer_csv(nombre):
    with gzip.open(OFI / nombre, "rt", encoding="utf-8-sig", newline="") as f:
        yield from csv.DictReader(f)


def recortar(t: str, n: int = 400) -> str:
    """Recorta en el límite de una palabra y marca el corte."""
    t = re.sub(r"\s+", " ", t).strip()
    return t if len(t) <= n else t[:n].rsplit(" ", 1)[0] + "…"


# Palabras del título (sin tildes, en minúsculas) que marcan un pedido o una expresión de reconocimiento.
RECONOCIMIENTO = re.compile(r"reconocimiento|beneplacito|homenaje|felicit|conmemor|distincion|adhesion|declar\w* de interes|de interes (de la|del|parlamentario|cultural|educativo|social|sanitario|legislativo|turistico|nacional|provincial|municipal|deportivo)")
TIPOS_RECONOCIMIENTO = {"Declaracion", "Resolucion", "Comunicacion"}


def es_reconocimiento(tipo: str, titulo: str) -> int:
    return int(tipo in TIPOS_RECONOCIMIENTO and bool(RECONOCIMIENTO.search(norm(titulo))))


def rol_senado(extracto: str, apellido: str, nombre: str) -> str:
    """"p" si el primer firmante del extracto es el senador, "f" si firmó con otros, "n" si el extracto no trae firmantes.
    El primer firmante puede venir como "SORIA", "SORIA Y OTROS", "LOPEZ C." (inicial) o "LOPEZ, FLORENCIA" (nombre de pila)."""
    m = re.match(r"^(.*?)\s*(?::|\.\s+PROYECTO)", extracto)
    if not m:
        return "n"
    seg = re.split(r"\s+y\s+", m.group(1), flags=re.I)[0]
    sur, _, giv = seg.partition(",")
    tok_sur, tok_giv = norm(sur).split(), norm(giv).split()
    ap, nombres = norm(apellido).split(), norm(nombre).split()
    if not tok_sur or not ap:
        return "n"
    if tok_sur[:len(ap)] != ap:
        return "f"
    iniciales = {n[:1] for n in nombres}
    # "LOPEZ C." distingue a dos senadores con el mismo apellido por la inicial; "LOPEZ, FLORENCIA", por el nombre de pila.
    if any(not (len(t) == 1 and t in iniciales) for t in tok_sur[len(ap):]):
        return "f"
    if any(not any(n == t or (len(t) >= 3 and n.startswith(t)) for n in nombres) for t in tok_giv):
        return "f"
    return "p"


TIPOS_SEN = {"PL": "Ley", "PD": "Declaracion", "PC": "Comunicacion", "PR": "Resolucion", "DE": "Decreto"}


def senado(legs_todos, cuenta):
    """Proyectos de los senadores actuales según su página en senado.gob.ar, atribuidos por ID oficial."""
    ruta = OFI.parent / "senado" / "proyectos_senadores.json.gz"
    if not ruta.exists():
        return {}
    por_id = {l["id_senado"]: l["slug"] for l in legs_todos if l["camara"] == "Senadores" and l.get("id_senado")}
    nombres = {l["slug"]: (l["apellido"] or l["nombre"].split()[-1], l["nombre"].replace(l["apellido"] or "", "", 1).strip() or l["nombre"]) for l in legs_todos if l["camara"] == "Senadores"}
    datos = json.loads(gzip.open(ruta, "rt", encoding="utf-8").read())["senadores"]
    out = {}
    for sid, lista in datos.items():
        slug = por_id.get(int(sid))
        if not slug:
            continue
        for ref, exp, fecha, extracto in lista:
            m = re.match(r"^(\d+)\.(\d{2})/S/(\w+)$", ref)
            e = re.match(r"^(\d+)/(\d{2})$", exp)
            cuenta["senado_total"] += 1
            if not m or not e or e[1] != m[1] or e[2] != m[2]:
                cuenta["senado_expediente_invalido"] += 1
                continue
            if m[3] not in TIPOS_SEN:
                cuenta["senado_no_es_proyecto"] += 1
                continue
            anio_f, mes_f, anio_e = int(fecha[2:4]), int(fecha[5:7]), int(m[2])
            if not (anio_f == anio_e or (anio_f == (anio_e + 1) % 100 and mes_f <= 3)):
                cuenta["senado_fecha_inconsistente"] += 1
                continue
            cuenta["senado_proyectos"] += 1
            tipo = TIPOS_SEN[m[3]]
            out.setdefault(slug, []).append([ref, fecha, tipo, exp, recortar(extracto), "", "", rol_senado(extracto, *nombres[slug]), es_reconocimiento(tipo, extracto)])
    return out


def mandatos_senadores(legs_todos):
    """slug -> [(inicio, fin)] de cada senador actual, con los mandatos reales del padrón oficial (activos y no activos, por ID)."""
    sys.path.insert(0, str(Path(__file__).resolve().parent))
    from _carga import filas
    por_id = {str(l["id_senado"]): l["slug"] for l in legs_todos if l["camara"] == "Senadores" and l.get("id_senado")}
    m = collections.defaultdict(list)
    for r in filas("senadores_no_activos"):
        if r.get("SENADOR") and str(r["ID"]) in por_id and re.match(r"\d{4}-", r.get("INICIO PERIODO REAL") or ""):
            fin = r["CESE PERIODO REAL"][:10] if re.match(r"\d{4}-", r.get("CESE PERIODO REAL") or "") else "9999-12-31"
            m[por_id[str(r["ID"])]].append((r["INICIO PERIODO REAL"][:10], fin))
    for r in filas("senadores_activos"):
        if str(r["ID"]) in por_id and re.match(r"\d{4}-", r.get("D_REAL") or ""):
            m[por_id[str(r["ID"])]].append((r["D_REAL"][:10], "9999-12-31"))
    return m


MIN_EQUIVALENTES = 3  # legisladores-año mínimos para mostrar un promedio o una mediana


def fraccion_en_funciones(mandatos, anio):
    """Fracción del año (0 a 1) en que alguno de los mandatos estuvo vigente: quien asumió el 10/12 cuenta 21/365 de ese año, no uno entero."""
    import datetime
    ini, fin = datetime.date(int(anio), 1, 1), datetime.date(int(anio), 12, 31)
    tramos = []
    for i, f in mandatos:
        try:
            a = max(ini, datetime.date.fromisoformat(i)); b = min(fin, datetime.date.fromisoformat(f if f < "9999" else "9999-12-31"))
        except ValueError:
            continue
        if a <= b:
            tramos.append((a, b))
    tramos.sort()
    dias, corte = 0, None
    for a, b in tramos:
        if corte is None or a > corte:
            dias += (b - a).days + 1
            corte = b
        elif b > corte:
            dias += (b - corte).days
            corte = b
    return dias / ((fin - ini).days + 1)


def calcular_promedios(por_dip, por_sen, legs_todos, hist):
    """Por cámara y año: proyectos de los legisladores actuales de esa cámara, cuántos de ellos estaban en funciones y el promedio por legislador."""
    por_hcdn = collections.defaultdict(list)
    for h in hist:
        por_hcdn[h["ID"]].append((min(h["INICIO"][:10], (h.get("JURAMENTO") or h["INICIO"])[:10]), max(h["FIN"][:10], (h.get("CESE") or h["FIN"])[:10])))
    mand = {"Diputados": {l["slug"]: por_hcdn.get(l.get("hcdn_id"), []) for l in legs_todos if l["camara"] == "Diputados"},
            "Senadores": mandatos_senadores(legs_todos)}
    datos = {"Diputados": por_dip, "Senadores": por_sen}
    out = {}
    for cam in ("Diputados", "Senadores"):
        anios = collections.defaultdict(lambda: {"propios": 0, "firmante": 0, "reconocimientos": 0, "sin_dato": 0})
        por_leg = collections.defaultdict(collections.Counter)   # año -> slug -> proyectos
        for slug, lista in datos[cam].items():
            for x in lista:
                a = anios[x[1][:4]]
                a["reconocimientos" if x[8] else {"p": "propios", "f": "firmante", "n": "sin_dato"}[x[7]]] += 1
                por_leg[x[1][:4]][slug] += 1
        res = {}
        for anio in sorted(anios):
            pesos = {slug: fraccion_en_funciones(ms, anio) for slug, ms in mand[cam].items() if slug in datos[cam] or cam == "Diputados"}
            pesos = {k: v for k, v in pesos.items() if v > 0}
            a = anios[anio]
            total = a["propios"] + a["firmante"] + a["reconocimientos"] + a["sin_dato"]
            equivalentes = sum(pesos.values())
            # Promedio por legislador-año: proyectos / años completos de funciones. La mediana es de los que estuvieron al menos medio año
            # (incluye a los que no presentaron ningún proyecto, que cuentan 0).
            medio = [por_leg[anio][s_] for s_, w in pesos.items() if w >= 0.5]
            # Con menos de 3 legisladores-año el promedio no significa nada (por ejemplo, dos senadores actuales apenas en funciones en 2001): no se calcula.
            res[anio] = {"proyectos": total, "legisladores": len(pesos), "equivalentes": round(equivalentes, 3),
                         "promedio": round(total / equivalentes, 2) if equivalentes >= MIN_EQUIVALENTES else None,
                         "mediana": statistics.median(medio) if len(medio) >= MIN_EQUIVALENTES else None, "mediana_n": len(medio), **a}
        out[cam] = {"firmantes_disponibles": cam == "Senadores", "anios": res}
    return out


def main() -> int:
    legs_todos = json.loads((SAL / "legisladores.json").read_text(encoding="utf-8"))
    legs = [l for l in legs_todos if l["camara"] == "Diputados"]
    actual = {l["hcdn_id"]: l["slug"] for l in legs if l.get("hcdn_id")}

    # Mandatos por nombre completo
    hist = json.loads(gzip.open(OFI / "diputados_historico.json.gz", "rt", encoding="utf-8", errors="replace").read())
    por_nombre, por_corto = collections.defaultdict(list), collections.defaultdict(list)
    for h in hist:
        ini = min(h["INICIO"][:10], (h.get("JURAMENTO") or h["INICIO"])[:10])
        fin = max(h["FIN"][:10], (h.get("CESE") or h["FIN"])[:10])
        por_nombre[norm(f'{h["APELLIDO"]}, {h["NOMBRE"]}')].append((h["ID"], ini, fin))
        por_corto[(norm(h["APELLIDO"]), (norm(h["NOMBRE"]).split() or [""])[0])].append((h["ID"], ini, fin))

    # Último movimiento resuelto de cada proyecto
    res = {}
    for r in leer_csv("resultado_proyectos.csv.gz"):
        f = r["movimiento_fecha"][:10]
        if r["proyecto_id"] not in res or f >= res[r["proyecto_id"]][0]:
            res[r["proyecto_id"]] = (f, r["resultado"].strip(), r["movimiento"].strip())

    cuenta = collections.Counter()
    por_dip = collections.defaultdict(list)
    for p in leer_csv("proyectos_parlamentarios.csv.gz"):
        cuenta["total"] += 1
        if p["CAMARA_ORIGEN"] != "Diputados":
            cuenta["origen_senado"] += 1
            continue
        fecha = p["PUBLICACION_FECHA"][:10]
        ap, _, no = p["AUTOR"].partition(",")
        mand = por_nombre.get(norm(p["AUTOR"]))
        corto = False
        if not mand:
            # Segunda regla, más estricta: mismo apellido y primer nombre, y una única persona con mandato en esa fecha.
            c = [m for m in por_corto.get((norm(ap), (norm(no).split() or [""])[0]), []) if m[1] <= fecha <= m[2]]
            if c and len({m[0] for m in c}) == 1:
                mand, corto = c, True
        if not mand:
            cuenta["autor_sin_padron"] += 1  # comisiones, el Poder Ejecutivo, diputados que el histórico no lista, etc.
            continue
        if len({m[0] for m in mand}) > 1:
            cuenta["nombre_ambiguo"] += 1
            continue
        if not any(m[1] <= fecha <= m[2] for m in mand):
            cuenta["fuera_de_mandato"] += 1
            continue
        hid = mand[0][0]
        cuenta["cruzados_por_primer_nombre"] += int(corto)
        cuenta["cruzados"] += 1
        if hid not in actual:
            continue
        cuenta["de_diputados_actuales"] += 1
        r = res.get(p["PROYECTO_ID"])
        titulo, tipo = recortar(html.unescape(p["TITULO"])), p["TIPO"].capitalize()
        por_dip[actual[hid]].append([p["PROYECTO_ID"], fecha, tipo, p["EXP_DIPUTADOS"].strip(), titulo,
                                     r[1] if r else "", r[0] if r else "", "p", es_reconocimiento(tipo, titulo)])

    por_sen = senado(legs_todos, cuenta)
    salida = SAL / "proyectos"
    if salida.exists():
        shutil.rmtree(salida)
    salida.mkdir()
    for slug, lista in por_dip.items():
        lista.sort(key=lambda x: (x[1], x[0]), reverse=True)
        tipos = collections.Counter(x[2] for x in lista)
        resultados = collections.Counter(x[5] or "Sin resolución registrada" for x in lista)
        resumen = {"total": len(lista), "por_tipo": dict(tipos.most_common()), "por_resultado": dict(resultados.most_common()),
                   "desde": lista[-1][1], "hasta": lista[0][1]}
        (salida / f"{slug}.json").write_text(json.dumps({"resumen": resumen, "proyectos": lista}, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
    for slug, lista in por_sen.items():
        lista.sort(key=lambda x: (x[1], x[0]), reverse=True)
        tipos = collections.Counter(x[2] for x in lista)
        resumen = {"camara": "Senadores", "total": len(lista), "por_tipo": dict(tipos.most_common()), "por_resultado": {"Sin resolución registrada": len(lista)},
                   "desde": lista[-1][1], "hasta": lista[0][1]}
        (salida / f"s-{slug}.json").write_text(json.dumps({"resumen": resumen, "proyectos": lista}, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
    promedios = calcular_promedios(por_dip, por_sen, legs_todos, hist)
    (salida / "_promedios.json").write_text(json.dumps(promedios, ensure_ascii=False, indent=1), encoding="utf-8")
    meta = {k: cuenta[k] for k in ("total", "origen_senado", "cruzados", "cruzados_por_primer_nombre", "autor_sin_padron", "fuera_de_mandato", "nombre_ambiguo", "de_diputados_actuales", "senado_total", "senado_proyectos", "senado_no_es_proyecto", "senado_expediente_invalido", "senado_fecha_inconsistente")}
    meta["senadores_con_proyectos"] = len(por_sen)
    meta["diputados_con_proyectos"] = len(por_dip)
    meta["con_resultado"] = sum(1 for l in por_dip.values() for x in l if x[5])
    meta["desde"] = min((l[-1][1] for l in por_dip.values()), default="")
    meta["hasta"] = max((l[0][1] for l in por_dip.values()), default="")
    (salida / "_meta.json").write_text(json.dumps(meta, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"{cuenta['total']} proyectos; {cuenta['cruzados']} con autor cruzado por nombre completo y mandato ({cuenta['cruzados_por_primer_nombre']} por apellido y primer nombre; {cuenta['autor_sin_padron']} autor fuera del padrón, "
          f"{cuenta['fuera_de_mandato']} fuera de mandato, {cuenta['nombre_ambiguo']} ambiguos); {cuenta['de_diputados_actuales']} son de diputados actuales "
          f"({len(por_dip)} diputados), {meta['con_resultado']} con resultado registrado")
    return 0


if __name__ == "__main__":
    sys.exit(main())
