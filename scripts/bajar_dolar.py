#!/usr/bin/env python3
"""
Descarga el tipo de cambio oficial del BCRA (peso por dólar) al cierre de cada año, desde la API de Series de Tiempo de
datos.gob.ar, y lo guarda para expresar el patrimonio neto de las declaraciones juradas en dólares:
  storage/oficial/bcra/dolar_cierre.json.gz   las respuestas tal como se bajaron
  storage/verificado/dolar.json               {"_meta": {...}, "cierre_anual": {"AAAA": {"fecha": "AAAA-MM-DD", "valor": pesos por dólar}}}

Serie principal: "Tipo de cambio de valuación" del BCRA (92.2_TIPO_CAMBIION_0_0_21_24, diaria, desde 2003), que es el
de referencia de la Comunicación "A" 3500. Control: donde la serie "Dólar Referencia Comunicación A 3500 (Mayorista)"
(175.1_DR_REFE500_0_0_25) tiene el mismo día, no puede diferir en más de 0,1%; si difiere más, no se publica nada.
Se pide la segunda quincena de diciembre de cada año y se toma el último día publicado.

Se usa el dólar oficial porque el MEP no tiene serie oficial (ni el BCRA ni el INDEC lo publican). Además, las
declaraciones juradas valúan los dólares al tipo de cambio oficial del cierre del año.

Uso:  python3 -I scripts/bajar_dolar.py
"""
import datetime, gzip, json, time, urllib.request
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
SERIE = "92.2_TIPO_CAMBIION_0_0_21_24"
CONTROL = "175.1_DR_REFE500_0_0_25"
URL = "https://apis.datos.gob.ar/series/api/series/?ids={s}&format=json&limit=100&start_date={a}-12-15&end_date={a}-12-31"
DESDE = 2003


def pedir(serie, anio):
    time.sleep(0.4)  # pausa de cortesía
    with urllib.request.urlopen(urllib.request.Request(URL.format(s=serie, a=anio), headers={"User-Agent": "DipuData"}), timeout=90) as r:
        return json.loads(r.read())


def main() -> int:
    crudos, cierre, controlados, difieren = {}, {}, 0, {}
    for anio in range(DESDE, datetime.date.today().year):
        d, c = pedir(SERIE, anio), pedir(CONTROL, anio)
        crudos[str(anio)] = {SERIE: d, CONTROL: c}
        filas = sorted((f, v) for f, v in d["data"] if v is not None)
        if not filas:
            continue
        f, v = filas[-1]
        if v <= 0 or f[:7] != f"{anio}-12":
            raise SystemExit(f"{anio}: valor no positivo o fuera de diciembre ({f}, {v})")
        otro = dict((ff, vv) for ff, vv in c["data"] if vv is not None).get(f)
        if otro is not None:
            if abs(otro - v) / otro > 0.001:
                raise SystemExit(f"{anio}: las dos series del BCRA difieren más de 0,1% el {f} ({v} y {otro})")
            if otro != v:
                difieren[str(anio)] = [v, otro]
            controlados += 1
        cierre[str(anio)] = {"fecha": f, "valor": v}
    (RAIZ / "storage" / "oficial" / "bcra").mkdir(parents=True, exist_ok=True)
    with gzip.open(RAIZ / "storage" / "oficial" / "bcra" / "dolar_cierre.json.gz", "wt", encoding="utf-8") as fh:
        json.dump(crudos, fh, ensure_ascii=False, separators=(",", ":"))
    salida = {"_meta": {"fuente": "BCRA, tipo de cambio de valuación (referencia Comunicación A 3500), datos.gob.ar, serie " + SERIE,
                        "control": f"comparada con la serie {CONTROL} en los {controlados} años en que ambas publican el mismo día",
                        "difieren_menos_de_0_1_pct": difieren,
                        "desde": min(cierre), "hasta": max(cierre)}, "cierre_anual": cierre}
    (RAIZ / "storage" / "verificado" / "dolar.json").write_text(json.dumps(salida, ensure_ascii=False, separators=(",", ":")) + "\n", encoding="utf-8")
    print(f"dólar oficial al cierre: {min(cierre)} a {max(cierre)} ({len(cierre)} años); control con A 3500 en {controlados} años")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
