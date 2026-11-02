#!/usr/bin/env python3
"""
Rutas de los pasajes que la Cámara de Diputados emitió a pedido de cada diputado en ejercicio, para dibujarlas en un
mapa del perfil. El archivo de la Cámara (Viajes nacionales, datos.hcdn.gob.ar) trae por tramo: período, diputado,
tipo (aéreo o terrestre), origen y destino, como nombre de aeropuerto o terminal. Cada nombre se lleva a una localidad
con scripts/lugares_pasajes.csv (editable) y la localidad se ubica con Georef (IGN/INDEC), que bajar_georef.py dejó
en storage/oficial/georef/. En el CSV, provincia "*" quiere decir que el nombre existe en varias provincias y se toma
la del diputado; si ahí no hay una sola localidad con ese nombre, el tramo queda "sin ubicar" y no se dibuja.

Salida: storage/verificado/rutas_pasajes.json
Uso:  python3 -I scripts/construir_rutas_pasajes.py   (después de construir_diputados_extra.py y bajar_georef.py)
"""
import collections, csv, glob, gzip, json, math, re, sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from construir_datos import norm  # noqa: E402
from construir_diputados_extra import leer, col, MESES  # noqa: E402

RAIZ = Path(__file__).resolve().parent.parent
G = RAIZ / "storage" / "oficial" / "georef"
SAL = RAIZ / "storage" / "verificado"
DESDE = "2025-12"  # el mismo corte que la fila "Desde el 10/12/2025" del perfil (viajes.json)
CABA = "Ciudad Autónoma de Buenos Aires"

# Proyección: equirrectangular con la longitud achicada por el coseno de 38° S (centro aproximado del país).
LON0, LAT0, ESC, COS = -74.0, -21.6, 18.0, math.cos(math.radians(38))
ANCHO, ALTO = round((-53.4 - LON0) * COS * ESC), round((LAT0 + 55.3) * ESC)


def xy(lon, lat):
    return round((lon - LON0) * COS * ESC, 1), round((LAT0 - lat) * ESC, 1)


def arreglar(s):
    """Algunos archivos traen texto UTF-8 leído como Windows-1252 (BAHÃA): se intenta revertir."""
    for _ in range(2):
        try:
            s = s.encode("cp1252").decode("utf-8")
        except (UnicodeEncodeError, UnicodeDecodeError):
            break
    return s


def clave(s):
    return norm(re.sub(r"[^\w\s.-]", "", arreglar((s or "").strip())))


def rdp(pts, eps):
    """Simplificación de Douglas-Peucker (en grados)."""
    if len(pts) < 3:
        return pts
    (x1, y1), (x2, y2) = pts[0], pts[-1]
    dx, dy = x2 - x1, y2 - y1
    lar = math.hypot(dx, dy) or 1e-12
    i, dmax = 0, 0.0
    for k in range(1, len(pts) - 1):
        d = abs(dy * pts[k][0] - dx * pts[k][1] + x2 * y1 - y2 * x1) / lar
        if d > dmax:
            i, dmax = k, d
    if dmax <= eps:
        return [pts[0], pts[-1]]
    return rdp(pts[:i + 1], eps)[:-1] + rdp(pts[i:], eps)


def mapa():
    geo = json.loads(gzip.open(G / "provincias.geojson.gz").read())
    trazos, centroides = [], {}
    for f in sorted(geo["features"], key=lambda f: f["properties"]["id"]):
        p = f["properties"]
        centroides[norm(p["nombre"])] = p["centroide"]
        polis = f["geometry"]["coordinates"] if f["geometry"]["type"] == "MultiPolygon" else [f["geometry"]["coordinates"]]
        d = []
        for poli in polis:
            anillo = poli[0]  # sin huecos
            lon_m = sum(q[0] for q in anillo) / len(anillo)
            lat_m = sum(q[1] for q in anillo) / len(anillo)
            if lat_m < -56 or lon_m > -53:  # el mapa abarca el continente, Tierra del Fuego y Malvinas
                continue
            mitad = len(anillo) // 2  # el anillo es cerrado: se simplifica en dos mitades
            s = rdp(anillo[:mitad + 1], 0.03)[:-1] + rdp(anillo[mitad:], 0.03)
            if len(s) < 4:
                continue
            area = abs(sum(s[k][0] * s[k + 1][1] - s[k + 1][0] * s[k][1] for k in range(len(s) - 1))) / 2
            if area < 0.004:
                continue
            d.append("M" + "L".join("%g %g" % xy(*q) for q in s[:-1]) + "Z")
        if d:
            trazos.append({"nombre": p["nombre"], "d": "".join(d)})
    caba = centroides[norm(CABA)]
    return trazos, (caba["lon"], caba["lat"])


def main() -> int:
    trazos, caba = mapa()
    with open(RAIZ / "scripts" / "lugares_pasajes.csv", encoding="utf-8") as f:
        lugares_csv = {r["lugar"]: r for r in csv.DictReader(f)}
    georef = json.loads(gzip.open(G / "localidades_pasajes.json.gz").read())["localidades"]

    def prov_igual(a, b):
        a, b = norm(a), norm(b)
        return a == b or a.startswith(b) or b.startswith(a)

    def ubicar(lugar, prov_diputado):
        """Devuelve (id, nombre, provincia, lon, lat) o None."""
        r = lugares_csv.get(lugar)
        if not r:
            return None
        prov = prov_diputado if r["provincia"] == "*" else r["provincia"]
        if not prov:
            return None
        if not r["localidad"]:
            if prov_igual(prov, CABA):
                return ("02", "Ciudad de Buenos Aires", CABA, *caba)
            return None
        c = [m for m in georef.get(r["localidad"], []) if prov_igual(m["provincia"]["nombre"], prov)]
        if len(c) > 1:  # mismo nombre dos veces en la provincia: se prefiere la localidad simple o componente
            c = [m for m in c if m["categoria"] in ("Localidad simple", "Componente de localidad compuesta")] or c
        if len(c) != 1:
            return None
        m = c[0]
        return (m["id"], m["nombre"], m["provincia"]["nombre"], m["centroide"]["lon"], m["centroide"]["lat"])

    legs = [l for l in json.loads((SAL / "legisladores.json").read_text(encoding="utf-8")) if l["camara"] == "Diputados"]
    tokens = [(set(norm(l["nombre"]).split()), l) for l in legs]

    def buscar(nombre):  # el mismo cruce por nombre que construir_diputados_extra.py
        tk = set(norm(nombre.replace(",", " ")).split())
        c = [l for t, l in tokens if t == tk] or [l for t, l in tokens if t <= tk or tk <= t]
        return c[0] if len(c) == 1 else None

    rutas = collections.defaultdict(collections.Counter)
    resumen = collections.defaultdict(collections.Counter)
    lugares, no_ubicados, ultimo = {}, collections.Counter(), ""
    for ruta in sorted(glob.glob(str(RAIZ / "storage" / "oficial" / "hcdn" / "viajes" / "viajes_*.csv.gz"))):
        for r in leer(ruta):
            l = buscar(col(r, "diputad"))
            if not l:
                continue
            tipo = norm(col(r, "tiposolicitud", "tipo")).split()[:1]
            tipo = "aereo" if tipo and tipo[0].startswith("aer") else ("terrestre" if tipo and tipo[0].startswith("terr") else None)
            per = col(r, "periodo", "per").strip().lower().replace("é", "e")
            iso = re.match(r"^(\d{4})-(\d{2})-\d{2}", per)
            m = re.match(r"^([a-zñ]{3})\w*[-/ ]+(\d{2,4})$", per)
            mes = f"{iso[1]}-{iso[2]}" if iso else (f"{int(m[2]) + (2000 if int(m[2]) < 100 else 0)}-{MESES[m[1]]:02d}" if m and m[1] in MESES else None)
            if not tipo or not mes or mes < DESDE:
                continue
            ultimo = max(ultimo, mes)
            s = l["slug"]
            resumen[s]["total"] += 1
            o, d = (ubicar(clave(col(r, k)), l["provincia"]) for k in ("origen", "destino"))
            if not o or not d:
                resumen[s]["sin_ubicar"] += 1
                for k, u in (("origen", o), ("destino", d)):
                    if not u:
                        no_ubicados[clave(col(r, k))] += 1
                continue
            if o[0] == d[0]:
                resumen[s]["misma_localidad"] += 1
                continue
            for u in (o, d):
                lugares[u[0]] = {"nombre": u[1], "provincia": u[2], "xy": list(xy(u[3], u[4]))}
            rutas[s][(o[0], d[0], tipo)] += 1

    viajes = json.loads((SAL / "viajes.json").read_text(encoding="utf-8"))["actual"]
    distintos = {s: (resumen[s]["total"], v["total"]) for s, v in viajes.items() if resumen[s]["total"] != v["total"]}
    if distintos:
        raise SystemExit(f"los tramos no coinciden con viajes.json: {distintos}")

    por_dip = {}
    for s, c in rutas.items():
        pares = collections.defaultdict(lambda: [0, 0])
        for (a, b, t), n in c.items():
            ida = (a, b) <= (b, a)
            k = (a, b, t) if ida else (b, a, t)
            pares[k][0 if ida else 1] += n
        lista = [{"a": a, "b": b, "tipo": t, "ida": n1, "vuelta": n2} for (a, b, t), (n1, n2) in pares.items()]
        lista.sort(key=lambda x: (-(x["ida"] + x["vuelta"]), lugares[x["a"]]["nombre"], lugares[x["b"]]["nombre"], x["tipo"]))
        por_dip[s] = {"rutas": lista, **{k: resumen[s][k] for k in ("total", "sin_ubicar", "misma_localidad")}}
    for s in resumen:
        por_dip.setdefault(s, {"rutas": [], **{k: resumen[s][k] for k in ("total", "sin_ubicar", "misma_localidad")}})

    usados = {x[k] for v in por_dip.values() for x in v["rutas"] for k in ("a", "b")}
    salida = {
        "_meta": {"fuente_pasajes": "Cámara de Diputados de la Nación, Viajes nacionales (datos.hcdn.gob.ar)",
                  "fuente_mapa": "Georef (apis.datos.gob.ar), límites provinciales del IGN y localidades del INDEC",
                  "desde": DESDE + "-10", "hasta": ultimo, "ancho": ANCHO, "alto": ALTO},
        "provincias": trazos,
        "lugares": {k: v for k, v in sorted(lugares.items()) if k in usados},
        "por_diputado": dict(sorted(por_dip.items())),
    }
    (SAL / "rutas_pasajes.json").write_text(json.dumps(salida, ensure_ascii=False, separators=(",", ":")) + "\n", encoding="utf-8")
    tot = sum(v["total"] for v in por_dip.values())
    sin = sum(v["sin_ubicar"] for v in por_dip.values())
    print(f"rutas: {len(por_dip)} diputados, {tot} tramos desde {DESDE}; sin ubicar: {sin}; misma localidad: "
          f"{sum(v['misma_localidad'] for v in por_dip.values())}; lugares: {len(usados)}; mapa {ANCHO}x{ALTO}")
    print(f"nombres sin ubicar: {dict(no_ubicados.most_common())}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
