#!/usr/bin/env python3
"""
Descarga el dólar MEP ("bolsa") de ArgentinaDatos (api.argentinadatos.com/v1/cotizaciones/dolares/bolsa) y guarda el valor
de venta del último día de diciembre de cada año, para expresar el patrimonio neto de las declaraciones juradas en dólares MEP.

ArgentinaDatos NO es una fuente oficial: el MEP no tiene serie oficial (ni el BCRA ni el INDEC lo publican). Se usa por
decisión de Marcelo (2/11/2026) y en pantalla se aclara. La serie empieza en octubre de 2018.

Controles (si alguno falla, no se escribe nada):
  - sin fechas repetidas y con valores positivos;
  - el MEP de cada cierre entre 0,9 y 3,5 veces el dólar oficial del BCRA del mismo año (storage/verificado/dolar.json);
  - un cierre se descarta si el valor no cambió en los 7 días anteriores: la API repite el último dato cuando no tiene
    uno nuevo (pasa en diciembre de 2025, con el mismo valor del 16 al 31);
  - el "mayorista" de ArgentinaDatos al cierre de cada año se compara con el dólar oficial del BCRA (1%) para ver qué
    tan bien reproduce una serie verificable; las diferencias quedan anotadas (en diciembre de 2023 repite el valor
    previo a la devaluación) y, si fallan más de dos cierres, no se publica nada.

Salida: storage/oficial/argentinadatos/dolar_bolsa.json.gz (lo bajado) y storage/verificado/dolar_mep.json
Uso:  python3 -I scripts/bajar_dolar_mep.py   (después de bajar_dolar.py)
"""
import gzip, json, time, urllib.request
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
BASE = "https://api.argentinadatos.com/v1/cotizaciones/dolares/"


def pedir(casa):
    time.sleep(0.5)
    with urllib.request.urlopen(urllib.request.Request(BASE + casa, headers={"User-Agent": "DipuData"}), timeout=90) as r:
        crudo = r.read()
    return crudo, json.loads(crudo)


def cierres(filas):
    out = {}
    for x in sorted(filas, key=lambda x: x["fecha"]):
        if x["fecha"][5:7] == "12" and x.get("venta"):
            out[x["fecha"][:4]] = {"fecha": x["fecha"], "valor": float(x["venta"])}
    return out


def main() -> int:
    oficial = json.loads((RAIZ / "storage" / "verificado" / "dolar.json").read_text(encoding="utf-8"))["cierre_anual"]
    crudo, mep = pedir("bolsa")
    _, may = pedir("mayorista")
    fechas = [x["fecha"] for x in mep]
    if len(fechas) != len(set(fechas)) or any((x.get("venta") or 0) <= 0 for x in mep):
        raise SystemExit("la serie MEP trae fechas repetidas o valores no positivos")
    import datetime
    por_fecha = {x["fecha"]: float(x["venta"]) for x in mep}
    cm, repetidos = {}, []
    for a, c in cierres(mep).items():
        if a not in oficial:
            continue
        f = datetime.date.fromisoformat(c["fecha"])
        ventana = {por_fecha[d] for d in ((f - datetime.timedelta(days=k)).isoformat() for k in range(8)) if d in por_fecha}
        if len(ventana) < 2:
            repetidos.append(a)
            continue
        cm[a] = c
    for a, c in cm.items():
        r = c["valor"] / oficial[a]["valor"]
        if not 0.9 <= r <= 3.5:
            raise SystemExit(f"{a}: el MEP ({c['valor']}) da {r:.2f} veces el oficial; no se publica")
    cmay, comparados, fallan = cierres(may), 0, {}
    for a, c in cmay.items():
        if a in oficial and c["fecha"] == oficial[a]["fecha"]:
            comparados += 1
            if abs(c["valor"] / oficial[a]["valor"] - 1) > 0.01:
                fallan[a] = {"argentinadatos": c["valor"], "bcra": oficial[a]["valor"]}
    if comparados < 5 or len(fallan) > 2:
        raise SystemExit(f"el mayorista de ArgentinaDatos no reproduce al BCRA ({len(fallan)} de {comparados} cierres difieren)")
    (RAIZ / "storage" / "oficial" / "argentinadatos").mkdir(parents=True, exist_ok=True)
    with gzip.open(RAIZ / "storage" / "oficial" / "argentinadatos" / "dolar_bolsa.json.gz", "wb") as f:
        f.write(crudo)
    salida = {"_meta": {"fuente": "ArgentinaDatos, dólar MEP (bolsa), valor de venta; no es una fuente oficial", "url": BASE + "bolsa",
                        "control": f"el mayorista de ArgentinaDatos coincide con el dólar oficial del BCRA (1%) en {comparados - len(fallan)} de {comparados} cierres de año",
                        "mayorista_distinto_del_bcra": fallan, "cierres_descartados_por_valor_repetido": repetidos,
                        "desde": min(cm), "hasta": max(cm)}, "cierre_anual": cm}
    (RAIZ / "storage" / "verificado" / "dolar_mep.json").write_text(json.dumps(salida, ensure_ascii=False, separators=(",", ":")) + "\n", encoding="utf-8")
    print(f"MEP al cierre: {min(cm)} a {max(cm)} ({len(cm)} años); descartados por valor repetido: {repetidos}; "
          f"mayorista igual al BCRA en {comparados - len(fallan)} de {comparados} cierres (difieren: {sorted(fallan)})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
