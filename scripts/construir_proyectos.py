#!/usr/bin/env python3
"""
Proyectos parlamentarios firmados por cada legislador en ejercicio.

Diputados: conjunto "Proyectos parlamentarios" de la HCDN (datos.hcdn.gob.ar). Trae un solo autor por proyecto (el
primer firmante), escrito "APELLIDO, NOMBRES", sin ID. El cruce se hace contra el histórico oficial de diputados
(que sí tiene ID HCDN): el nombre debe corresponder a una sola persona del histórico, y el proyecto tiene que haberse
publicado mientras esa persona era diputada. Recién entonces se asigna al ID, y del ID al diputado actual.
Qué pasó con cada proyecto sale de "Resultado proyectos" (2026) y "Resultado de proyectos" (2019), ambos de la HCDN:
se toma el resultado más avanzado que registre cualquiera de los dos.

Senado: lista de proyectos de cada senador bajada del buscador oficial por ID (scripts/bajar_proyectos_senado.py).
El buscador no informa el resultado: se enlaza al expediente oficial.

Salidas (storage/verificado/proyectos/):
  diputados/<slug>.json  [[expediente, fecha, tipo, título, resultado, fecha del resultado], ...]
  senado/<slug>.json     [[expediente, tipo, extracto, ruta del expediente], ...]
  indice.json            totales por legislador y metadatos del cruce

Uso:  python3 -I scripts/construir_proyectos.py   (después de construir_datos.py)
"""
import collections, csv, gzip, html, json, re, shutil, sys, unicodedata
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
HCDN = RAIZ / "storage" / "oficial" / "hcdn"
SAL = RAIZ / "storage" / "verificado" / "proyectos"
ORDEN = {"SANCIONADO": 5, "MEDIA SANCION": 4, "APROBADO": 3, "RECHAZADO": 2, "RETIRADO": 1}
TIPO_DIPUTADOS = {"LEY": "Ley", "RESOLUCION": "Resolución", "DECLARACION": "Declaración", "MENSAJE": "Mensaje",
                  "MENSAJE Y PROYECTO DE LEY": "Mensaje y proyecto de ley"}
TIPO_SENADO = {"PL": "Ley", "PD": "Declaración", "PR": "Resolución", "PC": "Comunicación", "CO": "Otro trámite (CO)"}


def norm(s: str) -> str:
    s = "".join(c for c in unicodedata.normalize("NFD", s or "") if unicodedata.category(c) != "Mn").lower()
    return re.sub(r"\s+", " ", re.sub(r"[^a-z0-9 ]", " ", s)).strip()


def compatibles(a, b):
    """El primer nombre debe ser igual; los siguientes, iguales o uno abreviatura del otro."""
    if not a or not b or a[0] != b[0]:
        return False
    return all(x.startswith(y) or y.startswith(x) for x, y in zip(a[1:], b[1:]))


def texto(s: str) -> str:
    """Algunos títulos traen entidades HTML ('112&deg;'): se decodifican."""
    return re.sub(r"\s+", " ", html.unescape(html.unescape(s or ""))).strip()


def leer(nombre):
    with gzip.open(HCDN / nombre, "rt", encoding="utf-8-sig", newline="") as f:
        yield from csv.DictReader(f)


def diputados():
    legs = json.loads((RAIZ / "storage" / "verificado" / "legisladores.json").read_text(encoding="utf-8"))
    actuales = {l["hcdn_id"]: l for l in legs if l["camara"] == "Diputados" and l.get("hcdn_id")}
    hist = json.loads(gzip.open(HCDN / "diputados_historico.json.gz", "rt", encoding="utf-8").read())
    por_apellido, periodos = collections.defaultdict(dict), collections.defaultdict(list)
    for h in hist:
        por_apellido[norm(h["APELLIDO"])].setdefault(h["ID"], set()).add(norm(h["NOMBRE"]))
        periodos[h["ID"]].append((h["INICIO"][:10], (h.get("CESE") or h["FIN"])[:10]))

    def candidatos(ap, no):
        """IDs del histórico con el mismo apellido y nombres compatibles ('CRISTIAN A.' con 'CRISTIAN ADRIAN')."""
        no = no.split()
        out = set()
        for hid, nombres in por_apellido.get(ap, {}).items():
            if any(compatibles(no, n.split()) for n in nombres):
                out.add(hid)
        return out

    resultado = {}
    for r in leer("resultados_proyectos.csv.gz"):
        res = r["RESULTADO"].strip().upper()
        if res in ORDEN:
            f = r["FECHA"][:10] if r["FECHA"][:4] not in ("", "1899") else ""
            prev = resultado.get(r["EXPEDIENTE_ID"])
            if not prev or ORDEN[res] > ORDEN[prev[0]] or (res == prev[0] and f > prev[1]):
                resultado[r["EXPEDIENTE_ID"]] = (res, f)
    viejo = 0
    for r in leer("resultado_proyectos.csv.gz"):
        res = r["resultado"].strip().upper()
        if res in ORDEN:
            f = r["movimiento_fecha"][:10]
            prev = resultado.get(r["proyecto_id"])
            if not prev or ORDEN[res] > ORDEN[prev[0]]:
                viejo += 1
                resultado[r["proyecto_id"]] = (res, f)

    meta = collections.Counter()
    por_dip = collections.defaultdict(list)
    for p in leer("proyectos_parlamentarios.csv.gz"):
        if p["CAMARA_ORIGEN"] != "Diputados":
            continue
        meta["proyectos"] += 1
        ap, _, no = p["AUTOR"].partition(",")
        fecha = p["PUBLICACION_FECHA"][:10]
        ids = candidatos(norm(ap), norm(no))
        if not ids:
            meta["sin_cruce"] += 1
            continue
        # Solo vale una persona que era diputada el día en que se publicó el proyecto.
        en_mandato = {i for i in ids if any(a <= fecha <= b for a, b in periodos[i])}
        if len(en_mandato) != 1:
            meta["fuera_de_mandato" if not en_mandato else "nombre_ambiguo"] += 1
            continue
        hid = next(iter(en_mandato))
        meta["cruzados"] += 1
        if hid in actuales:
            res, fres = resultado.get(p["PROYECTO_ID"], ("", ""))
            por_dip[actuales[hid]["slug"]].append([p["EXP_DIPUTADOS"], fecha, TIPO_DIPUTADOS.get(p["TIPO"], p["TIPO"].capitalize()), texto(p["TITULO"]), res, fres])
    return por_dip, dict(meta), viejo


def senado():
    ruta = RAIZ / "storage" / "oficial" / "senado" / "proyectos_por_senador.json.gz"
    if not ruta.exists():
        return {}
    datos = json.loads(gzip.open(ruta, "rt", encoding="utf-8").read())
    legs = json.loads((RAIZ / "storage" / "verificado" / "legisladores.json").read_text(encoding="utf-8"))
    por_id = {str(l["id_senado"]): l for l in legs if l["camara"] == "Senadores" and l.get("id_senado")}
    out = {}
    for sid, ps in datos.items():
        if sid not in por_id:
            continue
        filas = []
        for exp, tipo, origen, extracto in ps:
            m = re.fullmatch(r"(\d+)/(\d{2})", exp.strip())
            if not m:
                continue
            filas.append([f"{exp.strip()}-{origen}", TIPO_SENADO.get(tipo, tipo), extracto,
                          f"/parlamentario/comisiones/verExp/{m[1]}.{m[2]}/{origen}/{tipo}"])
        anio = lambda e: int(e[0].split("/")[1][:2])
        filas.sort(key=lambda e: (anio(e) + (1900 if anio(e) > 80 else 2000), int(e[0].split("/")[0])), reverse=True)
        out[por_id[sid]["slug"]] = filas
    return out


def main() -> int:
    if SAL.exists():
        shutil.rmtree(SAL)
    (SAL / "diputados").mkdir(parents=True)
    (SAL / "senado").mkdir(parents=True)
    por_dip, meta, viejo = diputados()
    resumen = {"diputados": {}, "senado": {}}
    for slug, ps in por_dip.items():
        ps.sort(key=lambda x: (x[1], x[0]), reverse=True)
        (SAL / "diputados" / f"{slug}.json").write_text(json.dumps(ps, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
        c = collections.Counter(x[4] for x in ps)
        resumen["diputados"][slug] = {"total": len(ps), "leyes": sum(1 for x in ps if x[2] == "Ley"),
                                      "resultados": {k: v for k, v in c.items() if k}, "desde": ps[-1][1], "hasta": ps[0][1]}
    sen = senado()
    for slug, ps in sen.items():
        (SAL / "senado" / f"{slug}.json").write_text(json.dumps(ps, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
        resumen["senado"][slug] = {"total": len(ps), "leyes": sum(1 for x in ps if x[1] == "Ley")}
    hasta = max((x[1] for ps in por_dip.values() for x in ps), default="")
    resumen["_meta"] = {"cruce_diputados": meta, "resultados_del_archivo_2019": viejo, "hasta": hasta}
    (SAL / "indice.json").write_text(json.dumps(resumen, ensure_ascii=False, indent=1), encoding="utf-8")
    pct = round(meta.get("cruzados", 0) / meta["proyectos"] * 100, 1)
    print(f"Diputados: {meta} ({pct}% cruzados con un diputado por ID); {len(por_dip)} diputados actuales con proyectos, "
          f"{sum(map(len, por_dip.values()))} proyectos. Senado: {len(sen)} senadores, {sum(map(len, sen.values()))} proyectos.")
    if pct < 80:
        sys.exit("ERROR: menos del 80% de los proyectos se pudo cruzar con un diputado; revisar el cruce")
    return 0


if __name__ == "__main__":
    sys.exit(main())
