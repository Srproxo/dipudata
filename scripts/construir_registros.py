#!/usr/bin/env python3
"""
Leyes (sumario + fechas) y subsidios de la Cámara de Diputados, desde los datos abiertos de la HCDN.

Entradas (storage/oficial/hcdn/):
  sumario_leyes.csv.gz        número, título, sumario y referencias de cada ley
  leyes_promulgadas.csv.gz    SOLO se usan la fecha de sanción y la de publicación en el Boletín Oficial.
                              Las columnas de promulgación, veto y decreto traen valores repetidos en todas
                              las filas (p. ej. 27/12/1983 o decreto 2850/1984): no son datos reales y no se publican.
  subsidios/subsidios_*.csv.gz  subsidios por año; los CUIT no se publican.

Salidas (storage/verificado/): leyes/, subsidios/, subsidios_diputados.json
Uso:  python3 -I scripts/construir_registros.py   (después de construir_datos.py)
"""
import collections, csv, glob, gzip, io, json, re, shutil, sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from construir_datos import norm, titulo, provincia  # noqa: E402

RAIZ = Path(__file__).resolve().parent.parent
HCDN = RAIZ / "storage" / "oficial" / "hcdn"
SAL = RAIZ / "storage" / "verificado"


def leer(ruta):
    b = gzip.open(ruta, "rb").read()
    for enc in ("utf-8-sig", "cp1252"):
        try:
            return list(csv.DictReader(io.StringIO(b.decode(enc), newline="")))
        except UnicodeDecodeError:
            continue
    raise ValueError(f"No se pudo leer {ruta}")


def limpio(s) -> str:
    return re.sub(r"\s+", " ", (s or "").replace('""', '"')).strip().strip('"').strip()


def fecha(s):
    s = (s or "").strip()[:10]
    return s if re.fullmatch(r"\d{4}-\d{2}-\d{2}", s) else None


def construir_leyes():
    prom = {}
    for r in leer(HCDN / "leyes_promulgadas.csv.gz"):
        if r["ley"].strip().isdigit():
            prom.setdefault(int(r["ley"]), r)
    destino = SAL / "leyes"
    if destino.exists():
        shutil.rmtree(destino)
    (destino / "ley").mkdir(parents=True)
    indice, vistos = [], set()
    for r in leer(HCDN / "sumario_leyes.csv.gz"):
        if not r["ley"].strip().isdigit() or int(r["ley"]) in vistos:
            continue
        n = int(r["ley"])
        vistos.add(n)
        p = prom.get(n, {})
        fs, fp = fecha(p.get("fecha_sancion")), fecha(p.get("fecha_publicacion"))
        if fs and fp and fs > fp:
            fp = None
        t = limpio(r["titulo"]) or limpio(p.get("asunto"))
        item = {"n": n, "t": t, "s": limpio(r["sumario"]), "r": limpio(r["referencias"]), "fs": fs, "fp": fp}
        item = {k: v for k, v in item.items() if v not in (None, "")}
        (destino / "ley" / f"{n}.json").write_text(json.dumps(item, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
        indice.append([n, t, fs or "", limpio(r["referencias"])])
    indice.sort(reverse=True)
    (destino / "indice.json").write_text(json.dumps(indice, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
    print(f"{len(indice)} leyes ({min(i[0] for i in indice)} a {max(i[0] for i in indice)})")


def monto(s) -> float:
    return float(re.sub(r"[^\d.]", "", (s or "").replace(",", "") if "." in (s or "") else (s or "")) or 0)


def construir_subsidios():
    legs = [l for l in json.loads((SAL / "legisladores.json").read_text(encoding="utf-8")) if l["camara"] == "Diputados"]
    hist = json.loads(re.sub(r",(\s*[}\]])", r"\1", gzip.open(HCDN / "diputados_historico.json.gz", "rt", encoding="utf-8", errors="replace").read()), strict=False)
    por_tokens = collections.defaultdict(set)
    for h in hist:
        por_tokens[frozenset(norm(f'{h["NOMBRE"]} {h["APELLIDO"]}').split())].add(titulo(f'{h["NOMBRE"]} {h["APELLIDO"]}'))

    def legislador_actual(tokens, prov):
        c = [l for l in legs if provincia(l["provincia"]) == prov and (tokens <= set(norm(l["nombre"]).split()) or set(norm(l["nombre"]).split()) <= tokens)]
        return c[0]["slug"] if len(c) == 1 else ""

    destino = SAL / "subsidios"
    if destino.exists():
        shutil.rmtree(destino)
    destino.mkdir(parents=True)
    indice, por_dip = {}, collections.defaultdict(dict)
    for ruta in sorted(glob.glob(str(HCDN / "subsidios" / "subsidios_20??.csv.gz"))):
        anio = re.search(r"subsidios_(\d{4})", ruta).group(1)
        items = []
        for r in leer(ruta):
            nom = limpio(r["Diputado"]).upper()
            if "," in nom:
                ap, _, no = nom.partition(",")
                mostrar, tk = titulo(f"{no.strip()} {ap.strip()}"), frozenset(norm(f"{no} {ap}").split())
            else:
                tk = frozenset(norm(nom).split())
                mostrar = sorted(por_tokens.get(tk, {titulo(nom)}))[0]
            try:
                prov = provincia(r["Provincia"])
            except ValueError:
                prov = limpio(r["Provincia"]).title()
            slug = legislador_actual(tk, prov)
            m = monto(r["Monto"])
            items.append({"b": limpio(r["Beneficiario"]).upper(), "m": m, "d": mostrar, "p": prov, "s": slug})
            if slug:
                x = por_dip[slug].setdefault(anio, {"n": 0, "total": 0.0})
                x["n"] += 1
                x["total"] += m
        items.sort(key=lambda x: (x["d"], -x["m"]))
        (destino / f"{anio}.json").write_text(json.dumps(items, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
        indice[anio] = {"n": len(items), "total": round(sum(i["m"] for i in items), 2), "diputados": len({i["d"] for i in items})}
        print(f"subsidios {anio}: {len(items)} registros, ${indice[anio]['total']:,.0f}, {indice[anio]['diputados']} diputados")
    covid = []
    for r in leer(HCDN / "subsidios" / "subsidios_covid_19.csv.gz"):
        f = r["transferencia_fecha"].strip()
        mm = re.fullmatch(r"(\d{1,2})/(\d{1,2})/(\d{4})", f)
        covid.append({"b": limpio(r["beneficiario"]).upper(), "a": titulo(limpio(r["interbloque_area"]).upper()), "m": monto(r["monto"]),
                      "e": r["transferencia_estado"].strip().lower(), "f": f"{mm[3]}-{int(mm[2]):02d}-{int(mm[1]):02d}" if mm else ""})
    covid.sort(key=lambda x: (x["f"] or "9999", x["b"]))
    (destino / "covid.json").write_text(json.dumps(covid, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
    indice["covid"] = {"n": len(covid), "total": round(sum(c["m"] for c in covid), 2), "efectuadas": sum(1 for c in covid if c["e"] == "efectuada")}
    (destino / "indice.json").write_text(json.dumps(indice, ensure_ascii=False, indent=1), encoding="utf-8")
    (SAL / "subsidios_diputados.json").write_text(json.dumps(por_dip, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"covid: {len(covid)} registros; {len(por_dip)} diputados actuales con subsidios registrados")


if __name__ == "__main__":
    construir_leyes()
    construir_subsidios()
