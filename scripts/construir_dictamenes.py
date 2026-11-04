#!/usr/bin/env python3
"""
Dictámenes de comisión de los proyectos de la Cámara de Diputados.

Fuente: conjunto "Dictámenes" de datos.hcdn.gob.ar (CKAN), bajado con scripts/bajar_ckan.py desde una máquina local (el portal corta la conexión
desde el entorno en la nube) y guardado en storage/oficial/ckan/hcdn/dictamenes/ (CSV comprimido y manifest.json con su sha256).
Cada fila es un dictamen de una comisión sobre un proyecto: EXPEDIENTE (el mismo ID "HCDN…" que el archivo de proyectos: el cruce es por ID, no por
nombre), GIRO (la comisión), TIPO, ORDEN, NUMERO y FECHA del Orden del Día, y OBSERVACIONES.

Controles: el CSV descomprimido tiene que coincidir con el sha256 del manifest; todo expediente tiene que existir en el archivo de proyectos;
se descartan las filas idénticas repetidas; las fechas tienen que estar entre 2008 y hoy.

Código de cada dictamen:  od = Orden del Día (dictamen de comisión numerado) · sn = dictamen aún sin número · st = pedido de tratamiento sobre tablas ·
a114 / a204 = tratado según el artículo 114 / 204 del Reglamento (se muestra el texto oficial, sin interpretarlo).

Lo usa construir_proyectos.py (cargar()); correr este script solo verifica y resume.
Uso:  python3 -I scripts/construir_dictamenes.py
"""
import collections, csv, gzip, hashlib, io, json, re, sys
from datetime import date
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
CARPETA = RAIZ / "storage" / "oficial" / "ckan" / "hcdn" / "dictamenes"
sys.path.insert(0, str(Path(__file__).resolve().parent))


def _tipo(t: str, numero: str) -> str:
    n = re.sub(r"[^a-z0-9 ]", "", re.sub(r"\s+", " ", (t or "").lower().replace("í", "i").replace("á", "a").strip()))
    if n.startswith("orden del dia"):
        return "od"
    if n.startswith("dictamen sin"):
        return "sn"
    if n.startswith("sobre tablas"):
        return "st"
    if "114" in n:
        return "a114"
    if "204" in n:
        return "a204"
    return "od" if numero else ""   # una fila sin tipo pero con número y fecha de Orden del Día


def cargar(ids_proyectos=None):
    """Devuelve (dict expediente -> lista de [comisión, tipo, número, fecha, observaciones], meta). Falla si algún control no cuadra."""
    from construir_datos import titulo
    ruta = next(iter(sorted(CARPETA.glob("*Dictamenes.csv.gz"))), None)
    man = json.loads((CARPETA / "manifest.json").read_text(encoding="utf-8")) if (CARPETA / "manifest.json").exists() else {}
    if not ruta or not man:
        raise SystemExit("faltan los dictámenes: bajarlos con scripts/bajar_ckan.py bajar hcdn dictamenes (desde una máquina donde el portal responda)")
    crudo = gzip.open(ruta, "rb").read()
    sha = hashlib.sha256(crudo).hexdigest()
    esperados = {r["sha256"] for r in man["recursos"].values() if (r.get("formato") or "").upper() == "CSV"}
    if sha not in esperados:
        raise SystemExit(f"el CSV de dictámenes no coincide con el sha256 del manifest ({sha[:12]}…)")
    hoy = date.today().isoformat()
    por_exp, vistos, filas, malas = collections.defaultdict(list), set(), 0, []
    for r in csv.DictReader(io.StringIO(crudo.decode("utf-8-sig"))):
        filas += 1
        exp = r["EXPEDIENTE"].strip()
        if not re.fullmatch(r"HCDN\d+", exp):
            malas.append(exp)
            continue
        fecha = (r["FECHA"] or "")[:10]
        if fecha and not ("2008-01-01" <= fecha <= hoy):
            malas.append(f"{exp} fecha {fecha}")
            continue
        num = r["NUMERO"].strip()
        d = [titulo(re.sub(r"\s+", " ", r["GIRO"]).strip()), _tipo(r["TIPO"], num), num, fecha, re.sub(r"\s+", " ", r["OBSERVACIONES"]).strip()[:300]]
        k = (exp, *d)
        if k in vistos:
            continue
        vistos.add(k)
        por_exp[exp].append(d)
    if malas:
        raise SystemExit(f"{len(malas)} filas inválidas en los dictámenes, por ejemplo: {malas[:3]}")
    if ids_proyectos is not None:
        sin = [e for e in por_exp if e not in ids_proyectos]
        if sin:
            raise SystemExit(f"{len(sin)} expedientes de dictámenes no figuran en el archivo de proyectos, por ejemplo {sin[:3]}")
    for lista in por_exp.values():
        lista.sort(key=lambda d: (d[3], d[0]), reverse=True)
    meta = {"filas": filas, "expedientes": len(por_exp), "dictamenes_unicos": sum(len(v) for v in por_exp.values()), "sha256_csv": sha,
            "url": next(r["url"] for r in man["recursos"].values() if r["sha256"] == sha), "bajado": next(r["bajado"] for r in man["recursos"].values() if r["sha256"] == sha),
            "modificado_en_el_portal": next(r["last_modificado"] for r in man["recursos"].values() if r["sha256"] == sha)[:10],
            "por_tipo": dict(collections.Counter(d[1] for v in por_exp.values() for d in v))}
    return por_exp, meta


def main() -> int:
    ids = {p["PROYECTO_ID"] for p in csv.DictReader(gzip.open(RAIZ / "storage" / "oficial" / "hcdn" / "proyectos_parlamentarios.csv.gz", "rt", encoding="utf-8-sig"))}
    por_exp, meta = cargar(ids)
    print(f"{meta['filas']} filas, {meta['expedientes']} proyectos con dictamen (todos cruzados por ID con el archivo de proyectos), {meta['dictamenes_unicos']} dictámenes únicos; por tipo: {meta['por_tipo']}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
