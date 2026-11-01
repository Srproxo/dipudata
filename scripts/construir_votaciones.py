#!/usr/bin/env python3
"""
Construye las votaciones nominales de Diputados a partir de los archivos oficiales de la HCDN
(datos.hcdn.gob.ar, "Votaciones nominales"). Lee todos los pares  actas_cabecera_*.csv.gz  y
actas_detalle_*.csv.gz  de storage/oficial/hcdn/votaciones/, de modo que agregar un período nuevo
es copiar sus dos archivos y volver a correr el script.

Salidas (storage/verificado/):
  votaciones/indice.json         cabecera de cada votación
  votaciones/actas/<id>.json     cómo votó cada diputado en esa votación
  votaciones_diputados.json      resumen por diputado actual (slug -> estadísticas)

El detalle no trae ID de persona: el cruce con los diputados actuales es por apellido, primer nombre
y provincia, y solo se acepta si es único.

Uso:  python3 -I scripts/construir_votaciones.py   (después de construir_datos.py)
"""
import collections, csv, glob, gzip, json, re, shutil, sys, unicodedata
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
        with gzip.open(f, "rt", encoding="utf-8", newline="") as fh:
            yield from csv.DictReader(fh)


def main() -> int:
    cab = {r["acta_id"]: r for r in leer("actas_cabecera_*.csv.gz")}
    if not cab:
        sys.exit("No hay cabeceras en storage/oficial/hcdn/votaciones/")
    votos = collections.defaultdict(list)
    for r in leer("actas_detalle_*.csv.gz"):
        if r["acta_id"] in cab and not r["diputado_nombre"].strip().startswith("."):
            votos[r["acta_id"]].append(r)

    # Diputados actuales: (apellido, primer nombre, distrito) -> slug, solo si es único
    legs = [l for l in json.loads((SAL / "legisladores.json").read_text(encoding="utf-8")) if l["camara"] == "Diputados"]
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
            json.dumps({"bloques": {b: dict(cn) for b, cn in sorted(por_bloque.items())}, "votos": compactas},
                       ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
        indice.append({
            "id": aid, "p": int(c["nroperiodo"]), "f": c["fecha"], "h": c["hora"], "s": c["tipo_sesion"],
            "r": int(c["reunion"] or 0), "t": c["titulo"].strip(), "res": c["resultado"],
            "m": f'{c["base_mayoria"]} · {c["tipo_mayoria"]}',
            "a": int(c["votos_afirmativos"]), "n": int(c["votos_negativos"]), "b": int(c["abstenciones"]), "u": int(c["ausentes"]),
            "pres": c["presidente_nombre"].strip(), "tiene_detalle": bool(filas),
        })
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
    print(f"{len(indice)} votaciones ({len(sin)} sin detalle), {sum(len(v) for v in votos.values())} votos, {len(resumen)} diputados actuales con historial")
    return 0


if __name__ == "__main__":
    sys.exit(main())
