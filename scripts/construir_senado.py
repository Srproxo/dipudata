#!/usr/bin/env python3
"""
Normaliza los listados oficiales del Senado (datos abiertos) a storage/verificado/senado_docs/.
Un archivo por tipo y año, para que PHP cargue solo lo que muestra.

Uso:  python3 -I scripts/construir_senado.py
"""
import json, re, shutil, sys, datetime, collections
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _carga import filas

RAIZ = Path(__file__).resolve().parent.parent
SALIDA = RAIZ / "storage" / "verificado" / "senado_docs"
FUENTE = "Senado de la Nación, datos abiertos"


def limpio(s) -> str:
    return re.sub(r"\s+", " ", str(s or "")).strip()


def iso(s) -> str:
    s = limpio(s)
    for fmt in ("%Y-%m-%d %H:%M:%S", "%Y-%m-%d", "%d-%m-%Y"):
        try:
            return datetime.datetime.strptime(s, fmt).date().isoformat()
        except ValueError:
            pass
    return ""


def url(s) -> str:
    s = limpio(s)
    return s if s.startswith("https://") else ""


def simple(num, fecha="FECHA", texto="EXTRACTO"):
    return lambda r: {"f": iso(r.get(fecha)), "n": limpio(r.get(num)), "t": limpio(r.get(texto))}


def licitacion(r):
    extra = {k: url(r.get(c)) for k, c in (("actas", "ACTAS APERTURA"), ("dictamen", "DICTAMEN"), ("disposicion", "DISPOSICION")) if url(r.get(c))}
    monto = limpio(r.get("MONTO TOTAL"))
    if monto:
        extra["monto"] = monto
    d = {"f": iso(r.get("APERTURA")), "n": "Actuación " + limpio(r.get("ACTUACION")), "t": limpio(r.get("OBJETO")),
         "e": limpio(r.get("EXPEDIENTE")), "l": limpio(r.get("LLAMADO"))}
    if extra:
        d["x"] = extra
    return d


def taquigrafica(r):
    return {"f": iso(r.get("FECHA DE SESION")), "n": f"Sesión {limpio(r.get('NRO DE SESION'))} · Reunión {limpio(r.get('NRO DE REUNION'))}",
            "t": "Sesión " + limpio(r.get("TIPO DE SESION")).lower(), "u": url(r.get("URL VESION TAQUIGRAFICA"))}


TIPOS = {
    "versiones-taquigraficas": ("versiones_taquigraficas", "Versiones taquigráficas", "sesiones",
        "Transcripción de lo dicho en cada sesión del Senado.", taquigrafica),
    "asuntos-entrados": ("asuntos_entrados", "Asuntos entrados", "sesiones",
        "Lista de los asuntos ingresados al Senado para cada reunión.",
        lambda r: {"f": iso(r.get("FECHA REUNION")), "n": "", "t": "Asuntos entrados", "u": url(r.get("URL"))}),
    "boletin-de-novedades": ("boletin_novedades", "Boletín de novedades", "sesiones",
        "Resumen de lo tratado en cada sesión.",
        lambda r: {"f": iso(r.get("FECHA SESION")), "n": "Boletín " + limpio(r.get("NUMERO BOLETIN")), "t": "Boletín de novedades", "u": url(r.get("URL"))}),
    "decretos-de-presidencia": ("decretos_presidenciales", "Decretos de la Presidencia del Senado", "administracion",
        "Decretos firmados por la presidencia del Senado: designaciones, licencias y otras decisiones administrativas.", simple("DECRETO")),
    "resoluciones-secretaria-administrativa": ("resoluciones_sec_administrativa", "Resoluciones de la Secretaría Administrativa", "administracion",
        "Resoluciones de la secretaría que gestiona el personal y los gastos del Senado.", simple("RESOLUCIONES SECRETARÍA ADMINISTRATIVA")),
    "resoluciones-conjuntas": ("resoluciones_conjuntas", "Resoluciones conjuntas", "administracion",
        "Resoluciones firmadas en conjunto por autoridades del Senado.", simple("RESOLUCIÓN CONJUNTA")),
    "disposiciones-contaduria": ("disposiciones_contaduria", "Disposiciones de la Dirección de Contaduría", "administracion",
        "Pagos, reintegros y rendiciones de gastos tramitados por Contaduría.", simple("NÚMERO/AÑO")),
    "disposiciones-administracion": ("disposiciones_dga", "Disposiciones de la Dirección General de Administración", "administracion",
        "Disposiciones sobre compras, contratos y administración.", simple("NÚMERO/AÑO")),
    "normativa-vigente": ("normativa_vigente", "Normativa administrativa vigente", "administracion",
        "Normas administrativas del Senado que siguen en vigor.", simple("NORMA")),
    "licitaciones": ("licitaciones", "Licitaciones", "compras",
        "Compras y contrataciones del Senado, con actas de apertura, dictámenes y disposiciones cuando existen.", licitacion),
}


def main() -> int:
    if SALIDA.exists():
        shutil.rmtree(SALIDA)
    indice = {}
    for slug, (archivo, titulo, familia, desc, f) in TIPOS.items():
        porAnio = collections.defaultdict(list)
        n = 0
        for r in filas(archivo):
            it = f(r)
            if not it["f"] and not it["t"] and not it.get("u"):
                continue
            if "PRUEBA DEL SISTEMA" in json.dumps(r, ensure_ascii=False).upper():
                continue
            it = {k: v for k, v in it.items() if v not in ("", None)}
            porAnio[(it.get("f") or "0000")[:4]].append(it)
            n += 1
        (SALIDA / slug).mkdir(parents=True, exist_ok=True)
        anios = {}
        for a, items in porAnio.items():
            items.sort(key=lambda x: (x.get("f", ""), x.get("n", "")), reverse=True)
            (SALIDA / slug / f"{a}.json").write_text(json.dumps(items, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
            anios[a] = len(items)
        indice[slug] = {"titulo": titulo, "familia": familia, "descripcion": desc, "total": n,
                        "anios": dict(sorted(anios.items(), reverse=True)), "fuente": FUENTE}
        print(f"{slug:42s} {n:6d} registros, {len(anios)} años")
    (SALIDA / "indice.json").write_text(json.dumps(indice, ensure_ascii=False, indent=1), encoding="utf-8")

    # Comisiones del Senado (solo nombre y tipo: el listado oficial no trae integrantes)
    com = [{"nombre": limpio(r["NOMBRE"]), "tipo": limpio(r["TIPO_COMISION"])} for r in filas("comisiones")]
    (RAIZ / "storage" / "verificado" / "comisiones_senado.json").write_text(json.dumps(com, ensure_ascii=False, indent=1), encoding="utf-8")
    print(len(com), "comisiones")
    return 0


if __name__ == "__main__":
    sys.exit(main())
