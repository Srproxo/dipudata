#!/usr/bin/env python3
"""
Datos extra de cada ley de /leyes: texto completo oficial, normas vinculadas y la votación nominal del día de la sanción.

Fuentes:
- Base Infoleg de Normativa Nacional (datos.jus.gob.ar, Ministerio de Justicia, CC BY 4.0), conjunto "base-de-datos-legislativos-infoleg",
  bajado con:  python3 -I scripts/bajar_ckan.py bajar https://datos.jus.gob.ar base-de-datos-legislativos-infoleg --formato ZIP --max-mb 400
  Se usan dos archivos: la base principal (id_norma, tipo, número, fechas, título, enlace al texto original y actualizado) y la base
  complementaria de "normas modificadas" (qué norma posterior modifica, reglamenta o complementa a cuál, según Infoleg).
  Los ZIP (70 MB) no van al repositorio: este script guarda un recorte (solo leyes y las relaciones que tocan a una ley) en
  storage/oficial/infoleg/ con el sha256 de cada ZIP de origen, y desde ahí se reconstruye.
- Votaciones nominales ya publicadas (storage/verificado/votaciones/).

Cruce ley -> Infoleg: por tipo "Ley" y número (el número de ley es único). Si Infoleg registra otra fecha de sanción que la Cámara, se guardan las dos y la página lo dice.
Cruce ley -> votación: las actas no traen el número de ley. Se buscan votaciones del MISMO DÍA de la sanción cuyo título o el título de alguno de sus
expedientes cubra al menos el 60 % de las palabras distintivas del título de la ley (mínimo dos) o el 50 % con cuatro o más en común,
con el título de la Cámara o el de Infoleg. Es un cruce por texto: se publica como
"votación nominal del día de la sanción" y la página lo aclara. Muchas leyes se aprueban sin votación nominal o en bloque sin detalle: ahí no hay enlace.

Uso:  python3 -I scripts/construir_leyes_extra.py            (desde el recorte guardado)
      python3 -I scripts/construir_leyes_extra.py --desde-zip (rehace el recorte desde los ZIP bajados)
"""
import collections, csv, gzip, hashlib, io, json, re, sys, unicodedata, zipfile
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
ZIPS = RAIZ / "storage" / "oficial" / "ckan" / "https___datos.jus.gob.ar" / "base-de-datos-legislativos-infoleg"
RECORTE = RAIZ / "storage" / "oficial" / "infoleg"
VERIF = RAIZ / "storage" / "verificado"
csv.field_size_limit(10**9)

COLS_NORMA = ["id_norma", "tipo_norma", "numero_norma", "fecha_sancion", "fecha_boletin", "titulo_sumario", "titulo_resumido", "texto_original", "texto_actualizado"]
COLS_REL = ["id_norma_modificada", "id_norma_modificatoria", "tipo_norma", "nro_norma", "fecha_boletin", "titulo_sumario", "titulo_resumido"]


def _sha(p: Path) -> str:
    h = hashlib.sha256()
    with open(p, "rb") as f:
        for b in iter(lambda: f.read(1 << 20), b""):
            h.update(b)
    return h.hexdigest()


def _leer_zip(nombre: str):
    z = zipfile.ZipFile(ZIPS / nombre)
    return csv.DictReader(io.TextIOWrapper(z.open(z.namelist()[0]), encoding="utf-8", errors="strict"))


def rehacer_recorte() -> None:
    man = json.loads((ZIPS / "manifest.json").read_text(encoding="utf-8"))["recursos"]
    arch = {r["nombre"]: r for r in man.values()}
    base, modif = arch["Base Infoleg Normativa Nacional"], arch["Base Complementaria Infoleg de Normas Modificadas"]
    for r in (base, modif):
        if _sha(ZIPS / r["archivo"]) != r["sha256"]:
            sys.exit(f"sha256 distinto en {r['archivo']}")
    normas = {}   # id -> fila, solo para resolver las normas que una ley modifica
    leyes = []
    for row in _leer_zip(base["archivo"]):
        normas[row["id_norma"]] = (row["tipo_norma"], row["numero_norma"], row["fecha_boletin"], row["titulo_sumario"], row["titulo_resumido"])
        if row["tipo_norma"] == "Ley":
            leyes.append([row[c] for c in COLS_NORMA])
    ids_ley = {f[0] for f in leyes}
    rel = []
    for row in _leer_zip(modif["archivo"]):
        a, b = row["id_norma_modificada"], row["id_norma_modificatoria"]
        if a in ids_ley or b in ids_ley:
            fila = [row[c] for c in COLS_REL]
            # Si la ley es la modificatoria, se guarda también qué norma modifica (tipo, número, fecha, títulos), desde la base principal.
            t = normas.get(a, ("", "", "", "", ""))
            rel.append(fila + list(t))
    RECORTE.mkdir(parents=True, exist_ok=True)
    with gzip.open(RECORTE / "leyes_infoleg.csv.gz", "wt", encoding="utf-8", newline="") as f:
        w = csv.writer(f); w.writerow(COLS_NORMA); w.writerows(sorted(leyes, key=lambda x: int(x[0])))
    with gzip.open(RECORTE / "relaciones_leyes.csv.gz", "wt", encoding="utf-8", newline="") as f:
        w = csv.writer(f); w.writerow(COLS_REL + ["modificada_tipo", "modificada_nro", "modificada_fecha", "modificada_sumario", "modificada_resumido"])
        w.writerows(sorted(rel, key=lambda x: (int(x[0]), int(x[1]))))
    (RECORTE / "manifest.json").write_text(json.dumps({
        "fuente": "Base Infoleg de Normativa Nacional, datos.jus.gob.ar (Ministerio de Justicia), CC BY 4.0",
        "conjunto": "https://datos.jus.gob.ar/dataset/base-de-datos-legislativos-infoleg",
        "origen": {r["archivo"]: {"url": r["url"], "sha256": r["sha256"], "modificado": r["last_modificado"], "bajado": r["bajado"]} for r in (base, modif)},
        "leyes": len(leyes), "relaciones": len(rel),
        "sha256": {n: _sha(RECORTE / n) for n in ("leyes_infoleg.csv.gz", "relaciones_leyes.csv.gz")},
    }, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"recorte: {len(leyes)} leyes, {len(rel)} relaciones")


def _normalizar(s: str) -> list:
    s = unicodedata.normalize("NFD", (s or "").lower())
    s = "".join(c for c in s if unicodedata.category(c) != "Mn")
    return re.findall(r"[a-z0-9]{3,}", s)


VACIAS = set("""para sobre entre como esta este desde hasta del los las una unos con por que ley leyes nacional nacion republica argentina proyecto
dictamen mayoria minoria orden dia votacion general particular expediente articulo articulos modificacion sancion aprobacion suscripto suscripta
gobierno revision similar and the""".split())
UMBRAL = 0.6


def coincide(titulo_ley: str, titulo_voto: str) -> bool:
    """El título de la votación (o de un expediente) cubre el 60 % de las palabras distintivas del título de la ley (mínimo 2),
    o el 50 % con al menos 4 palabras en común."""
    pal = set(_normalizar(titulo_ley)) - VACIAS
    if len(pal) < 2:
        return False
    comunes = len(pal & (set(_normalizar(titulo_voto)) - VACIAS))
    return (comunes >= 2 and comunes / len(pal) >= UMBRAL) or (comunes >= 4 and comunes / len(pal) >= 0.5)


def _https(u: str, id_norma: str, actualizado: bool) -> str:
    # Infoleg publica el mismo texto en argentina.gob.ar (https). El enlace http de servicios.infoleg.gob.ar queda como respaldo en el recorte.
    if not u:
        return ""
    return f"https://www.argentina.gob.ar/normativa/nacional/norma-{id_norma}/" + ("actualizacion" if actualizado else "texto")


def construir() -> None:
    man = json.loads((RECORTE / "manifest.json").read_text(encoding="utf-8"))
    for n, h in man["sha256"].items():
        if _sha(RECORTE / n) != h:
            sys.exit(f"sha256 distinto en {n}")
    leyes_sitio = {int(l[0]): l for l in json.loads((VERIF / "leyes" / "indice.json").read_text(encoding="utf-8"))}
    info = collections.defaultdict(list)
    with gzip.open(RECORTE / "leyes_infoleg.csv.gz", "rt", encoding="utf-8") as f:
        for row in csv.DictReader(f):
            if row["numero_norma"].isdigit():
                info[int(row["numero_norma"])].append(row)
    rel_mod = collections.defaultdict(list)   # id ley -> normas posteriores que la modifican o complementan
    rel_hace = collections.defaultdict(list)  # id ley -> normas que esta ley modifica
    with gzip.open(RECORTE / "relaciones_leyes.csv.gz", "rt", encoding="utf-8") as f:
        for r in csv.DictReader(f):
            rel_mod[r["id_norma_modificada"]].append(r)
            rel_hace[r["id_norma_modificatoria"]].append(r)

    votos_por_fecha = collections.defaultdict(list)
    for v in json.loads((VERIF / "votaciones" / "indice.json").read_text(encoding="utf-8")):
        votos_por_fecha[v["f"]].append(v)
    det_cache = {}

    def items_voto(v):
        if v["id"] not in det_cache:
            d = json.loads((VERIF / "votaciones" / "actas" / f"{v['id']}.json").read_text(encoding="utf-8"))
            det_cache[v["id"]] = [v["t"]] + [e[1] for e in d.get("expedientes", [])], len(d.get("expedientes", []))
        return det_cache[v["id"]]

    salida, sin_infoleg, fecha_distinta, con_voto = {}, 0, 0, 0
    for n, (num, titulo, fsan, _r) in leyes_sitio.items():
        e = {}
        cand = info.get(n, [])
        titulos = [titulo]
        if len(cand) == 1:
            r = cand[0]
            titulos.append(r["titulo_sumario"])
            e["infoleg"] = {"id": int(r["id_norma"]), "original": _https(r["texto_original"], r["id_norma"], False),
                            "actualizado": _https(r["texto_actualizado"], r["id_norma"], True), "boletin": r["fecha_boletin"]}
            if r["fecha_sancion"] != fsan:
                # El número de ley es único; si las fechas no coinciden se muestran las dos, sin elegir.
                e["infoleg"]["sancion"] = r["fecha_sancion"]
                fecha_distinta += 1
            posteriores = rel_mod.get(r["id_norma"], [])
            prom = [x for x in posteriores if x["tipo_norma"] == "Decreto" and "PROMULGACION" in x["titulo_resumido"].upper()]
            if prom:
                e["promulgacion"] = f"Decreto {prom[0]['nro_norma']}/{prom[0]['fecha_boletin'][:4]}"
            e["modificada_por_leyes"] = sorted({int(x["nro_norma"]) for x in posteriores if x["tipo_norma"] == "Ley" and x["nro_norma"].isdigit() and int(x["nro_norma"]) != n}, reverse=True)
            otras = collections.Counter(x["tipo_norma"] for x in posteriores if x["tipo_norma"] != "Ley" and x not in prom)
            e["otras_normas"] = dict(sorted(otras.items(), key=lambda kv: -kv[1]))
            e["modifica_leyes"] = sorted({int(x["modificada_nro"]) for x in rel_hace.get(r["id_norma"], []) if x["modificada_tipo"] == "Ley" and x["modificada_nro"].isdigit() and int(x["modificada_nro"]) != n})
            e["modifica_otras"] = sum(1 for x in rel_hace.get(r["id_norma"], []) if x["modificada_tipo"] != "Ley")
        else:
            sin_infoleg += 1
        votos = []
        for v in votos_por_fecha.get(fsan, []):
            its, nexp = items_voto(v)
            if any(coincide(tl, t) for tl in titulos for t in its):
                    votos.append({"id": v["id"], "c": v["c"], "t": v["t"], "res": v["res"], "a": v["a"], "n": v["n"], "conjunto": nexp > 1})
        if votos:
            con_voto += 1
            e["votaciones"] = sorted(votos, key=lambda x: (x["c"], x["id"]))
        salida[str(n)] = e
    (VERIF / "leyes" / "vinculos.json").write_text(json.dumps({
        "_meta": {"fuente_infoleg": man["fuente"], "conjunto": man["conjunto"], "origen": man["origen"], "umbral_titulo": UMBRAL,
                  "leyes": len(salida), "con_infoleg": sum(1 for x in salida.values() if "infoleg" in x), "sin_infoleg": sin_infoleg,
                  "fecha_distinta_infoleg": fecha_distinta, "con_votacion": con_voto},
        "leyes": salida}, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
    print(f"{len(salida)} leyes · Infoleg {len(salida) - sin_infoleg} (con otra fecha de sanción: {fecha_distinta}) · sin Infoleg {sin_infoleg} · con votación {con_voto}")


if __name__ == "__main__":
    if "--desde-zip" in sys.argv:
        rehacer_recorte()
    construir()
