#!/usr/bin/env python3
"""
Construye las votaciones nominales de Diputados a partir de los archivos oficiales de la HCDN
(datos.hcdn.gob.ar, "Votaciones nominales"). Lee todos los pares  actas_cabecera_*.csv.gz  y
actas_detalle_*.csv.gz  (y los expedientes_*.csv.gz) de storage/oficial/hcdn/votaciones/, de modo que agregar un período nuevo
es copiar sus dos archivos y volver a correr el script.

Además incorpora las votaciones del mandato actual (desde el 10/12/2025) de Diputados y Senado, bajadas con
scripts/bajar_votaciones_recientes.py. Cada una se valida contra su cabecera; las que no cuadran se muestran pero no entran
en las estadísticas por persona.

Salidas (storage/verificado/):
  votaciones/indice.json         cabecera de cada votación
  votaciones/actas/<id>.json     cómo votó cada diputado en esa votación
  votaciones_diputados.json      resumen 2011-2020 por diputado actual (slug -> estadísticas)
  votaciones_mandato_actual.json resumen desde el 10/12/2025 por legislador actual (slug o "s:slug")

El detalle no trae ID de persona: el cruce con los diputados actuales es por apellido, primer nombre
y provincia, y solo se acepta si es único.

Uso:  python3 -I scripts/construir_votaciones.py   (después de construir_datos.py)
"""
import collections, csv, datetime, glob, gzip, json, re, shutil, sys, unicodedata
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
ENT = RAIZ / "storage" / "oficial" / "hcdn" / "votaciones"
SAL = RAIZ / "storage" / "verificado"
CODIGO = {"AFIRMATIVO": "A", "NEGATIVO": "N", "ABSTENCION": "B", "AUSENTE": "U"}


def norm(s: str) -> str:
    s = "".join(c for c in unicodedata.normalize("NFD", s) if unicodedata.category(c) != "Mn").lower()
    return re.sub(r"\s+", " ", re.sub(r"[^a-z0-9 ]", " ", s)).strip()


def distrito(s: str) -> str:
    n = norm(s).replace(" ", "")
    return "caba" if n in ("caba", "ciudaddebuenosaires", "ciudadautonomadebuenosaires") else norm(s)


def partir(nombre: str):
    """'ABDALA DE MATARAZZO Norma Amanda' -> ('abdala de matarazzo', 'norma')"""
    t = nombre.split()
    ap = [w for w in t if w.upper() == w and any(c.isalpha() for c in w)]
    resto = [w for w in t if w not in ap]
    return norm(" ".join(ap)), (norm(resto[0]) if resto else "")


def leer(patron):
    for f in sorted(glob.glob(str(ENT / patron))):
        with gzip.open(f, "rt", encoding="utf-8-sig", newline="") as fh:
            yield from csv.DictReader(fh)


AD = RAIZ / "storage" / "oficial" / "argentinadatos"
CODIGO_AD = {"afirmativo": "A", "negativo": "N", "abstencion": "B", "ausente": "U", "presidente": "P",
             "si": "A", "no": "N", "no emite": "E", "lev.vot.": "X"}


def nombre_directo(n: str) -> str:
    """'Aguero, Guillermo Cesar' -> 'Guillermo Cesar Aguero'"""
    ap, _, no = n.partition(",")
    return re.sub(r"\s+", " ", f"{no.strip()} {ap.strip()}").strip() if no else n.strip()


def a_hora_local(utc: str):
    """Las fechas vienen en UTC ('2025-12-17T17:55:00.000Z'): se pasan a hora argentina (UTC-3)."""
    d = datetime.datetime.strptime(utc[:19], "%Y-%m-%dT%H:%M:%S") - datetime.timedelta(hours=3)
    return d.date().isoformat(), d.strftime("%H:%M")


def cargar_ad(nombre):
    ruta = next(iter(sorted(glob.glob(str(AD / f"{nombre}_desde_*.json.gz")))), None)
    if not ruta:
        return []
    with gzip.open(ruta, "rt", encoding="utf-8") as f:
        datos = json.load(f)
    # La fuente repite algunas votaciones (misma acta, mismos votos, a veces con el título vacío): se queda la más completa.
    unicas = {}
    for a in datos:
        k = str(a.get("actaId", a.get("id")))
        if k not in unicas or len(a.get("titulo") or "") > len(unicas[k].get("titulo") or ""):
            unicas[k] = a
    return list(unicas.values())


def senado_primario():
    """Votaciones del Senado bajadas de senado.gob.ar, con la forma que usa procesar_recientes."""
    ruta = next(iter(sorted(glob.glob(str(RAIZ / "storage" / "oficial" / "senado" / "votaciones_desde_*.json.gz")))), None)
    if not ruta:
        return []
    cod = {"A": "si", "N": "no", "B": "abstencion", "U": "ausente", "X": "lev.vot."}
    out = []
    with gzip.open(ruta, "rt", encoding="utf-8") as f:
        for v in json.load(f):
            cuenta = collections.Counter(x[4] for x in v["votos"])
            a, n, b, u = v["afirmativos"], v["negativos"], v["abstenciones"], v["ausentes"]
            rec = (cuenta["A"], cuenta["N"], cuenta["B"], cuenta["U"])
            nota = None
            if (a, n, b, u) != rec and (a, n, b, u) == tuple(2 * x for x in rec):
                # La página del Senado repite los totales (y la tabla): vale el recuento voto por voto.
                a, n, b, u = rec
                nota = "La página del Senado muestra esta votación con los totales duplicados; se usa el recuento voto por voto."
            out.append({"actaId": v["id"], "acta": v["acta"], "fecha_local": v["fecha"], "hora": v["hora"], "titulo": v["titulo"], "descripcion": v["tipo"],
                        "resultado": v["resultado"], "mayoria": v["mayoria"], "afirmativos": a, "negativos": n, "abstenciones": b, "ausentes": u,
                        "presidente": "", "nota_origen": nota,
                        "votos": [{"nombre": x[1], "voto": cod[x[4]], "sid": x[0]} for x in v["votos"]]})
    return out


def nombres_compatibles(a, b):
    """'MARIA EUGENIA' con 'MARÍA E.': primer nombre igual; los demás, iguales o abreviados."""
    a, b = a.split(), b.split()
    if not a or not b or a[0] != b[0]:
        return False
    return all(x.startswith(y) or y.startswith(x) for x, y in zip(a[1:], b[1:]))


def senado_historico(legs, salida, indice):
    """
    Votaciones del Senado entre 2005 y el 9/12/2025 (senado.gob.ar). Cada fila se asigna al ID oficial del senador
    usando el listado histórico del Senado (mismo ID que la página): apellido igual, nombres compatibles, misma provincia
    y un mandato vigente el día de la votación; solo si queda una única persona. Devuelve las estadísticas de los
    senadores actuales agrupadas por mandato.
    """
    ruta = next(iter(sorted(glob.glob(str(RAIZ / "storage" / "oficial" / "senado" / "votaciones_2005_a_*.json.gz")))), None)
    if not ruta:
        return {}, {}
    sys.path.insert(0, str(Path(__file__).resolve().parent))
    from _carga import filas as filas_senado
    mandatos = collections.defaultdict(list)   # id -> [(desde, hasta)]
    por_apellido = collections.defaultdict(list)  # (apellido, provincia) -> [(id, nombres)]
    for r in filas_senado("senadores_no_activos"):
        if not str(r.get("ID", "")).isdigit():
            continue
        sid = int(r["ID"])
        ap, _, no = r["SENADOR"].partition(",")
        d = (r.get("INICIO PERIODO REAL") or "")[:10]
        h = (r.get("CESE PERIODO REAL") or "")[:10]
        h = h if re.match(r"\d{4}-\d{2}-\d{2}", h) else "9999-12-31"
        if not re.match(r"\d{4}-\d{2}-\d{2}", d):
            continue
        mandatos[sid].append((d, h))
        k = (norm(ap), distrito(r["PROVINCIA"]))
        if (sid, norm(no)) not in por_apellido[k]:
            por_apellido[k].append((sid, norm(no)))
    actuales = {l["id_senado"]: l for l in legs if l["camara"] == "Senadores" and l.get("id_senado")}
    tam_bloque = collections.Counter()
    with gzip.open(ruta, "rt", encoding="utf-8") as f:
        datos = json.load(f)
    meta = collections.Counter()
    stats = collections.defaultdict(collections.Counter)  # (sid, desde, hasta) -> contadores
    for v in datos:
        aid = f"S{v['id']}"
        filas, cod, por_bloque, sin, cruzados = [], collections.Counter(), collections.defaultdict(collections.Counter), 0, []
        for sid_pag, nombre, bloque, prov, voto in v["votos"]:
            if not re.sub(r"[\s,]", "", nombre):
                continue
            ap, _, no = nombre.partition(",")
            cands = {i for i, n in por_apellido.get((norm(ap), distrito(prov)), []) if nombres_compatibles(norm(no), n)}
            cands = {i for i in cands if any(a <= v["fecha"] <= b for a, b in mandatos[i])}
            if sid_pag and any(a <= v["fecha"] <= b for a, b in mandatos.get(sid_pag, [])):
                # La página trae el ID oficial: manda sobre el nombre, y se controla que no se contradigan.
                meta["id_contradictorio"] += int(len(cands) == 1 and sid_pag not in cands)
                sid = sid_pag
            else:
                sid = next(iter(cands)) if len(cands) == 1 else None
            if sid is None:
                sin += 1
            cod[voto] += 1
            fila = [nombre_directo(nombre), bloque, prov.title() if prov.isupper() else prov, voto]
            if sid in actuales:
                fila.append("s:" + actuales[sid]["slug"])
                cruzados.append((sid, voto))
            filas.append(fila)
            por_bloque[bloque or "Sin dato"][voto] += 1
        a, n, b, u = v["afirmativos"], v["negativos"], v["abstenciones"], v["ausentes"]
        rec = (cod["A"], cod["N"], cod["B"], cod["U"])
        nota = None
        if (a, n, b, u) != rec and (a, n, b, u) == tuple(2 * x for x in rec):
            a, n, b, u = rec
            nota = "La página del Senado muestra esta votación con los totales duplicados; se usa el recuento voto por voto."
        cuadra = (a, n, b, u) == rec and len(filas) > 0 and cod["X"] == 0 and not v.get("anulada")
        if v.get("anulada"):
            nota = "El Senado registra esta votación como anulada o cancelada; se muestra tal como figura y no se cuenta en las estadísticas de nadie."
            meta["anuladas"] += 1
        elif not cuadra:
            nota = ("La página del Senado no publica el detalle voto por voto de esta votación." if not filas
                    else "El detalle de esta votación no coincide con sus totales; no se cuenta en las estadísticas de cada uno.")
            meta["no_cuadran" if filas else "sin_detalle"] += 1
        else:
            meta["validas"] += 1
            meta["con_72"] += int(len(filas) == 72)
            for sid, voto in cruzados:
                per = next(p for p in mandatos[sid] if p[0] <= v["fecha"] <= p[1])
                st = stats[(sid,) + per]
                if voto in "ANB":
                    st["emitio"] += 1
                    st[voto] += 1
                elif voto == "U":
                    st["ausente"] += 1
        meta["filas"] += len(filas)
        meta["sin_cruzar"] += sin
        titulo_ = v["titulo"].strip()
        if v.get("tipo") and v["tipo"].strip().lower() not in titulo_.lower():
            titulo_ = f'{titulo_} ({v["tipo"].strip().capitalize()})'
        indice.append({"id": aid, "c": "S", "p": 0, "f": v["fecha"], "h": v["hora"], "s": "nominal", "r": 0, "t": titulo_,
                       "res": v["resultado"] or "", "m": v.get("mayoria", ""), "a": a, "n": n, "b": b, "u": u, "pres": "",
                       "tiene_detalle": bool(filas), "e": []})
        (salida / "actas" / f"{aid}.json").write_text(json.dumps(
            {"bloques": {k: dict(c) for k, c in sorted(por_bloque.items())}, "votos": filas, "expedientes": [], "nota": nota,
             "camara": "Senadores", "historico": True}, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
    resumen = collections.defaultdict(list)
    for (sid, d, h), st in stats.items():
        total = st["emitio"] + st["ausente"]
        if total <= 0:
            continue
        resumen["s:" + actuales[sid]["slug"]].append({
            "mandato_desde": d, "mandato_hasta": h if h != "9999-12-31" else "", "votaciones": total, "emitio": st["emitio"],
            "ausente": st["ausente"], "afirmativos": st["A"], "negativos": st["N"], "abstenciones": st["B"],
            "voto_pct": round(st["emitio"] / total * 100, 1)})
    for k in resumen:
        resumen[k].sort(key=lambda x: x["mandato_desde"], reverse=True)
    fechas = [v["fecha"] for v in datos]
    meta_out = {"votaciones": len(datos), "validas": meta["validas"], "no_cuadran": meta["no_cuadran"], "sin_detalle": meta["sin_detalle"], "anuladas": meta["anuladas"], "con_72": meta["con_72"],
                "filas": meta["filas"], "sin_cruzar": meta["sin_cruzar"], "id_contradictorio": meta["id_contradictorio"],
                "desde": min(fechas) if fechas else "", "hasta": max(fechas) if fechas else ""}
    print(f"Senado 2005-2025: {meta_out}; {len(resumen)} senadores actuales con votaciones anteriores")
    return dict(resumen), meta_out


def procesar_recientes(legs, salida, indice):
    """Votaciones del mandato actual. Devuelve las estadísticas por legislador."""
    por_camara = {}
    for camara in ("Diputados", "Senadores"):
        c = collections.defaultdict(list)
        for l in legs:
            if l["camara"] == camara:
                c[(frozenset(norm(l["apellido"] or l["nombre"].split()[-1]).split()),
                   (norm(l["nombre"].replace(l["apellido"] or "", "", 1)).split() or [""])[0])].append(l)
        por_camara[camara] = {k: v[0] for k, v in c.items() if len(v) == 1}
    tam_bloque = collections.Counter((l["camara"], l["bloque_oficial"]) for l in legs)
    stats = collections.defaultdict(collections.Counter)
    meta = {"desde": "2025-12-10", "hasta": "", "validas": collections.Counter(), "excluidas": collections.Counter(), "sin_cruzar": collections.Counter()}

    por_sid = {l["id_senado"]: l for l in legs if l["camara"] == "Senadores" and l.get("id_senado")}
    for camara, datos in (("Diputados", cargar_ad("diputados_actas")), ("Senadores", senado_primario())):
        for a in datos:
            fecha, hora = (a["fecha_local"], a["hora"]) if "fecha_local" in a else a_hora_local(a["fecha"])
            es_dip = camara == "Diputados"
            aid = str(a["id"]) if es_dip else f"S{a['actaId']}"
            filas, por_bloque, codigos = [], collections.defaultdict(collections.Counter), collections.Counter()
            en_blanco = 0
            for v in a["votos"]:
                nombre = v["diputado"] if es_dip else v["nombre"]
                if not re.sub(r"[\s,]", "", nombre or ""):
                    # Fila sin nombre en el registro oficial (una banca vacía): no es un legislador y no se cuenta.
                    en_blanco += 1
                    continue
                ap, _, no = nombre.partition(",")
                leg = por_sid.get(v.get("sid")) if not es_dip else None
                leg = leg or por_camara[camara].get((frozenset(norm(ap).split()), (norm(no).split() or [""])[0]))
                cod = CODIGO_AD.get((v["tipoVoto"] if es_dip else v["voto"]).strip().lower(), "X")
                codigos[cod] += 1
                if not leg:
                    meta["sin_cruzar"][camara] += 1
                fila = [nombre_directo(nombre), leg["bloque"] if leg else "", leg["provincia"] if leg else "", cod]
                if leg:
                    fila.append(leg["slug"] if es_dip else "s:" + leg["slug"])
                filas.append(fila)
                por_bloque[fila[1] or "Sin dato"][cod] += 1
            h = {"a": a["votosAfirmativos"] if es_dip else a["afirmativos"], "n": a["votosNegativos"] if es_dip else a["negativos"],
                 "b": a["abstenciones"], "u": a["ausentes"]}
            cuadra = (codigos["A"], codigos["N"], codigos["B"], codigos["U"]) == (h["a"], h["n"], h["b"], h["u"])
            res = "AFIRMATIVO" if str(a["resultado"]).lower().startswith("afirm") else "NEGATIVO"
            sesion = a.get("sesion") or ""
            tipo = (re.search(r"Sesi[oó]n\s+([A-Za-zÁÉÍÓÚáéíóúñ ]+?)(?:\s+-|$)", sesion) or [None, ""])[1].strip() if es_dip else "nominal"
            titulo_ = (a["titulo"] or "").strip() or (a.get("descripcion") or "").strip()
            if not es_dip and a.get("descripcion") and a["descripcion"].strip().lower() not in titulo_.lower():
                titulo_ = f'{titulo_} ({a["descripcion"].strip().capitalize()})'
            nota = (a.get("nota_origen") or ("El registro oficial de esta votación incluye una fila sin nombre (marcada como ausente), que no corresponde a ningún legislador y no se cuenta." if en_blanco else None)) if cuadra else ("En esta votación hay un diputado presente que no emitió voto y el detalle lo registra como ausente; "
                                       "no se sabe cuál, por eso no se cuenta en las estadísticas de cada uno." if False
                                       else "El detalle de esta votación no coincide con sus totales; no se cuenta en las estadísticas de cada uno.")
            indice.append({
                "id": aid, "c": "D" if es_dip else "S", "p": int(a["periodo"]) if es_dip else 0, "f": fecha, "h": hora, "s": tipo,
                "r": int(a["reunion"]) if es_dip and str(a.get("reunion", "")).isdigit() else 0, "t": titulo_, "res": res,
                "m": (a.get("mayoria") or "").replace(" — ", " · "), "a": h["a"], "n": h["n"], "b": h["b"], "u": h["u"],
                "pres": nombre_directo(a.get("presidente") or ""), "tiene_detalle": True, "e": [],
            })
            (salida / "actas" / f"{aid}.json").write_text(json.dumps(
                {"bloques": {b: dict(cn) for b, cn in sorted(por_bloque.items())}, "votos": filas, "expedientes": [], "nota": nota,
                 "camara": camara}, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
            meta["hasta"] = max(meta["hasta"], fecha)
            if not cuadra:
                meta["excluidas"][camara] += 1
                continue
            meta["validas"][camara] += 1
            mayoria = {}
            for b, cn in por_bloque.items():
                top = sorted(((x, cn[x]) for x in "ANB" if cn[x]), key=lambda kv: -kv[1])
                mayoria[b] = top[0][0] if top and (len(top) == 1 or top[0][1] > top[1][1]) else None
            for f in filas:
                if len(f) < 5:
                    continue
                st, cod = stats[f[4]], f[3]
                if cod in "ANB":
                    st["emitio"] += 1
                    st[cod] += 1
                elif cod == "U":
                    st["ausente"] += 1
                else:
                    continue
                if cod in "ANB" and mayoria.get(f[1]) and tam_bloque[(camara, next((l["bloque_oficial"] for l in legs if l["slug"] == f[4].replace("s:", "") and l["camara"] == camara), ""))] >= 3:
                    st["cb_n"] += 1
                    st["cb"] += int(cod == mayoria[f[1]])
    resumen = {}
    for k, st in stats.items():
        total = st["emitio"] + st["ausente"]
        resumen[k] = {"votaciones": total, "emitio": st["emitio"], "ausente": st["ausente"],
                      "afirmativos": st["A"], "negativos": st["N"], "abstenciones": st["B"],
                      "voto_pct": round(st["emitio"] / total * 100, 1) if total else None,
                      "coincidencia_bloque_pct": round(st["cb"] / st["cb_n"] * 100, 1) if st["cb_n"] >= 20 else None,
                      "coincidencia_bloque_n": st["cb_n"]}
    resumen["_meta"] = {"desde": meta["desde"], "hasta": meta["hasta"], "validas": dict(meta["validas"]), "excluidas": dict(meta["excluidas"])}
    print(f"mandato actual: {dict(meta['validas'])} votaciones válidas, {dict(meta['excluidas'])} excluidas, "
          f"{dict(meta['sin_cruzar'])} votos sin cruzar con un legislador actual; {len(resumen) - 1} legisladores con estadísticas")
    return resumen


def main() -> int:
    cab = {r["acta_id"]: r for r in leer("actas_cabecera_*.csv.gz")}
    if not cab:
        sys.exit("No hay cabeceras en storage/oficial/hcdn/votaciones/")
    votos = collections.defaultdict(list)
    for r in leer("actas_detalle_*.csv.gz"):
        if r["acta_id"] in cab and not r["diputado_nombre"].strip().startswith("."):
            votos[r["acta_id"]].append(r)

    # Expedientes tratados en cada votación (el archivo del período 137 está contenido en el general)
    exps = collections.defaultdict(list)
    vistos = set()
    for r in leer("expedientes_*.csv.gz"):
        k = (r["acta_id"], r["expediente"])
        if r["acta_id"] in cab and k not in vistos:
            vistos.add(k)
            exps[r["acta_id"]].append([r["expediente"].strip(), r["titulo"].strip(), r["od"].strip()])

    # Diputados actuales: (apellido, primer nombre, distrito) -> slug, solo si es único
    legs_todos = json.loads((SAL / "legisladores.json").read_text(encoding="utf-8"))
    legs = [l for l in legs_todos if l["camara"] == "Diputados"]
    clave_actual = collections.defaultdict(list)
    for l in legs:
        ap = norm(l["apellido"] or "")
        no = norm(l["nombre"].replace(l["apellido"] or "", "", 1)).split()
        clave_actual[(ap, no[0] if no else "", distrito(l["provincia"]))].append(l["slug"])
    a_slug = {k: v[0] for k, v in clave_actual.items() if len(v) == 1}

    salida = SAL / "votaciones"
    if salida.exists():
        shutil.rmtree(salida)
    (salida / "actas").mkdir(parents=True)

    indice, stats = [], collections.defaultdict(lambda: collections.Counter())
    for aid, c in cab.items():
        filas = votos.get(aid, [])
        por_bloque = collections.defaultdict(collections.Counter)
        compactas = []
        for r in filas:
            v = CODIGO.get(r["voto"].strip().upper(), "U")
            k = partir(r["diputado_nombre"]) + (distrito(r["distrito_nombre"]),)
            slug = a_slug.get(k)
            compactas.append([r["diputado_nombre"].strip(), r["bloque"].strip(), r["distrito_nombre"].strip(), v] + ([slug] if slug else []))
            por_bloque[r["bloque"].strip()][v] += 1
        # El presidente de la sesión figura como AUSENTE en el detalle, pero la cabecera no lo cuenta como
        # ausente cuando está presidiendo. Se marca "P" (preside) solo si el recuento de la cabecera lo exige.
        ausentes = sum(1 for x in compactas if x[3] == "U")
        if ausentes - int(c["ausentes"]) == 1:
            for x in compactas:
                if x[3] == "U" and norm(x[0]) == norm(c["presidente_nombre"]):
                    x[3] = "P"
                    break
            por_bloque = collections.defaultdict(collections.Counter)
            for x in compactas:
                por_bloque[x[1]][x[3]] += 1
        cuenta = collections.Counter(x[3] for x in compactas)
        if (cuenta["A"], cuenta["N"], cuenta["B"], cuenta["U"]) != (int(c["votos_afirmativos"]), int(c["votos_negativos"]), int(c["abstenciones"]), int(c["ausentes"])):
            sys.exit(f"ERROR: el detalle de la acta {aid} no coincide con su cabecera")
        mayoria = {}
        for b, cnt in por_bloque.items():
            pres = {x: cnt[x] for x in "ANB" if cnt[x]}
            if pres:
                top = sorted(pres.items(), key=lambda kv: -kv[1])
                mayoria[b] = top[0][0] if len(top) == 1 or top[0][1] > top[1][1] else None
        for nombre_, bloque_, dist_, v, *rest in compactas:
            if not rest:
                continue
            s = stats[rest[0]]
            if v == "P":
                s["presidio"] += 1
                s["_desde"] = min(s["_desde"] or 99999999, int(c["fecha"].replace("-", "")))
                s["_hasta"] = max(s["_hasta"], int(c["fecha"].replace("-", "")))
                continue
            s["actas"] += 1
            s["presente" if v != "U" else "ausente"] += 1
            if v != "U":
                s[v] += 1
                if mayoria.get(bloque_):
                    s["con_bloque_n"] += 1
                    s["con_bloque"] += int(v == mayoria[bloque_])
            s["_desde"] = min(s["_desde"] or 99999999, int(c["fecha"].replace("-", "")))
            s["_hasta"] = max(s["_hasta"], int(c["fecha"].replace("-", "")))
        (salida / "actas" / f"{aid}.json").write_text(
            json.dumps({"bloques": {b: dict(cn) for b, cn in sorted(por_bloque.items())}, "votos": compactas, "expedientes": exps.get(aid, [])},
                       ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
        indice.append({
            "id": aid, "p": int(c["nroperiodo"]), "f": c["fecha"], "h": c["hora"], "s": c["tipo_sesion"],
            "r": int(c["reunion"] or 0), "t": c["titulo"].strip(), "res": c["resultado"],
            "m": f'{c["base_mayoria"]} · {c["tipo_mayoria"]}',
            "a": int(c["votos_afirmativos"]), "n": int(c["votos_negativos"]), "b": int(c["abstenciones"]), "u": int(c["ausentes"]),
            "pres": c["presidente_nombre"].strip(), "tiene_detalle": bool(filas),
            "e": [x[0] for x in exps.get(aid, [])], "c": "D",
        })
    actual = procesar_recientes(legs_todos, salida, indice)
    anteriores, meta_hist = senado_historico(legs_todos, salida, indice)
    (SAL / "votaciones_senado_anteriores.json").write_text(json.dumps({**anteriores, "_meta": meta_hist}, ensure_ascii=False, indent=1), encoding="utf-8")
    (SAL / "votaciones_mandato_actual.json").write_text(json.dumps(actual, ensure_ascii=False, indent=1), encoding="utf-8")
    indice.sort(key=lambda x: (x["f"], x["h"]), reverse=True)
    (salida / "indice.json").write_text(json.dumps(indice, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")

    resumen = {}
    for slug, s in stats.items():
        pres = s["presente"]
        resumen[slug] = {
            "actas": s["actas"], "presidio": s["presidio"], "presente": pres, "ausente": s["ausente"],
            "afirmativos": s["A"], "negativos": s["N"], "abstenciones": s["B"],
            "asistencia_pct": round(pres / s["actas"] * 100, 1) if s["actas"] else None,
            "coincidencia_bloque_pct": round(s["con_bloque"] / s["con_bloque_n"] * 100, 1) if s["con_bloque_n"] >= 20 else None,
            "coincidencia_bloque_n": s["con_bloque_n"],
            "desde": f'{s["_desde"]:08d}'[:4] + "-" + f'{s["_desde"]:08d}'[4:6] + "-" + f'{s["_desde"]:08d}'[6:],
            "hasta": f'{s["_hasta"]:08d}'[:4] + "-" + f'{s["_hasta"]:08d}'[4:6] + "-" + f'{s["_hasta"]:08d}'[6:],
        }
    (SAL / "votaciones_diputados.json").write_text(json.dumps(resumen, ensure_ascii=False, indent=1), encoding="utf-8")
    sin = [a for a in cab if a not in votos]
    print(f"{sum(1 for v in indice if v['e'])} con expedientes vinculados;", f"{len(indice)} votaciones ({len(sin)} sin detalle), {sum(len(v) for v in votos.values())} votos, {len(resumen)} diputados actuales con historial")
    return 0


if __name__ == "__main__":
    sys.exit(main())
