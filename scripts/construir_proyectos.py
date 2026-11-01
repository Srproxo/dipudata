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

Uso:  python3 -I scripts/construir_proyectos.py   (después de construir_datos.py)
"""
import collections, csv, gzip, html, json, re, shutil, sys, unicodedata
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


TIPOS_SEN = {"PL": "Ley", "PD": "Declaracion", "PC": "Comunicacion", "PR": "Resolucion", "DE": "Decreto"}


def senado(legs_todos, cuenta):
    """Proyectos de los senadores actuales según su página en senado.gob.ar, atribuidos por ID oficial."""
    ruta = OFI.parent / "senado" / "proyectos_senadores.json.gz"
    if not ruta.exists():
        return {}
    por_id = {l["id_senado"]: l["slug"] for l in legs_todos if l["camara"] == "Senadores" and l.get("id_senado")}
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
            out.setdefault(slug, []).append([ref, fecha, TIPOS_SEN[m[3]], exp, recortar(extracto), "", ""])
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
        por_dip[actual[hid]].append([p["PROYECTO_ID"], fecha, p["TIPO"].capitalize(), p["EXP_DIPUTADOS"].strip(),
                                     recortar(html.unescape(p["TITULO"])), r[1] if r else "", r[0] if r else ""])

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
