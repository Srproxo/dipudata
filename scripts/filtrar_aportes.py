#!/usr/bin/env python3
"""
Recorta el archivo de aportes de la Cámara Nacional Electoral (registro de aportantes, aportantes.electoral.gob.ar) y lo
guarda en storage/oficial/cne/aportes.csv.gz SIN datos personales.

El sitio de la CNE no acepta descargas automáticas: el archivo lo baja una persona desde el navegador (exportación
"aportes.csv") y se pasa como argumento. De cada aporte se quitan el CUIL/CUIT y el banco, y el nombre de las personas humanas. De las personas jurídicas
(CUIT que empieza con 30, 33 o 34: empresas, cooperativas, asociaciones) se conserva la razón social.
Para poder contar cuántas personas o empresas distintas aportaron sin guardar quiénes son, cada CUIL/CUIT se reemplaza
por un número correlativo (1, 2, 3...) que no permite volver al original.

Rubro: si se pasa el Registro Nacional de Sociedades (Ministerio de Justicia, datos abiertos, datos.jus.gob.ar, el ZIP
anual), a cada persona jurídica se le agrega su actividad principal declarada ante ARCA: la de orden 1 y estado "AC"
(activa). El cruce se hace por CUIT acá y el CUIT no se guarda.

Uso:  python3 -I scripts/filtrar_aportes.py ruta/al/aportes.csv [ruta/al/registro-nacional-sociedades-AAAA.zip]
"""
import csv, gzip, io, re, sys, time, zipfile
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
SALIDA = RAIZ / "storage" / "oficial" / "cne" / "aportes.csv.gz"
MESES = {m: i for i, m in enumerate(["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto",
                                      "septiembre", "octubre", "noviembre", "diciembre"], 1)}
COLUMNAS = ["fecha", "cod_destino", "destino", "tipo", "aportante", "razon_social", "actividad", "distrito", "agrupacion", "modalidad", "recurrente",
            "monto", "rectificado", "anulado", "observacion"]


def fecha_iso(s: str) -> str:
    m = re.match(r"(\d{1,2}) de (\w+) de (\d{4})$", s.strip(), flags=re.I)
    if not m or m[2].lower() not in MESES:
        raise SystemExit(f"fecha con formato inesperado: {s!r}")
    return f"{m[3]}-{MESES[m[2].lower()]:02d}-{int(m[1]):02d}"


def main() -> int:
    if len(sys.argv) not in (2, 3):
        raise SystemExit(__doc__)
    filas = list(csv.DictReader(open(sys.argv[1], encoding="utf-8-sig")))
    actividad = {}
    if len(sys.argv) == 3:
        cuits = {re.sub(r"\D", "", r["Cuil/Cuit"]) for r in filas if r["Persona Humana/Jurídica"].startswith("Jur")}
        z = zipfile.ZipFile(sys.argv[2])
        with z.open(z.infolist()[0]) as f:
            for r in csv.DictReader(io.TextIOWrapper(f, encoding="utf-8-sig", errors="replace")):
                if r["cuit"] in cuits and r["actividad_orden"] == "1" and r["actividad_estado"] == "AC" and r["actividad_descripcion"].strip():
                    actividad[r["cuit"]] = re.sub(r"\s+", " ", r["actividad_descripcion"]).strip()
        print(f"actividad principal encontrada para {len(actividad)} de {len(cuits)} personas jurídicas")
    esperadas = {"Fecha", "Cod_destino", "Destino", "Persona Humana/Jurídica", "Aportante", "Cuil/Cuit", "Distrito",
                 "Agrupacion", "Modalidad", "Recurrente", "Monto", "Banco Origen", "Rectificado", "Anulado", "Observación"}
    if set(filas[0]) != esperadas:
        raise SystemExit(f"columnas inesperadas: {sorted(set(filas[0]) ^ esperadas)}")
    ids = {}
    buf = io.StringIO()
    w = csv.writer(buf, lineterminator="\n")
    w.writerow(COLUMNAS)
    for r in filas:
        cuit = re.sub(r"\D", "", r["Cuil/Cuit"])
        if not cuit:
            raise SystemExit("aporte sin CUIL/CUIT")
        n = ids.setdefault(cuit, len(ids) + 1)
        juridica = r["Persona Humana/Jurídica"].startswith("Jur")
        if juridica and cuit[:2] not in ("30", "33", "34"):
            raise SystemExit(f"persona jurídica con CUIT de persona humana (fila {n}): revisar antes de publicar")
        razon = re.sub(r"\s+", " ", r["Aportante"]).strip() if juridica else ""
        w.writerow([fecha_iso(r["Fecha"]), int(r["Cod_destino"]), r["Destino"].strip(),
                    "Jurídica" if juridica else "Humana", n, razon, actividad.get(cuit, "") if juridica else "", r["Distrito"].strip(),
                    re.sub(r"\s+", " ", r["Agrupacion"]).strip(), r["Modalidad"].strip(), r["Recurrente"] == "Si",
                    f"{float(r['Monto']):.2f}", r["Rectificado"] == "True", r["Anulado"] == "True", r["Observación"].strip()])
    SALIDA.parent.mkdir(parents=True, exist_ok=True)
    with gzip.GzipFile(SALIDA, "wb", mtime=0) as f:
        f.write(buf.getvalue().encode("utf-8"))
    (SALIDA.parent / "aportes_fuente.txt").write_text(
        "Cámara Nacional Electoral, registro de aportantes: https://aportantes.electoral.gob.ar/\n"
        f"Archivo exportado desde el navegador y recortado el {time.strftime('%Y-%m-%d')}: {len(filas)} aportes, "
        f"{len(ids)} aportantes distintos. Sin CUIL/CUIT ni banco; sin nombre de personas humanas (solo razón social de personas jurídicas).\n"
        + (f"Actividad principal de personas jurídicas: Registro Nacional de Sociedades (datos.jus.gob.ar), {Path(sys.argv[2]).name}.\n"
           if len(sys.argv) == 3 else ""), encoding="utf-8")
    print(f"{len(filas)} aportes, {len(ids)} aportantes -> {SALIDA.relative_to(RAIZ)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
