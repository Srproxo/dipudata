#!/usr/bin/env python3
"""
Declaraciones juradas patrimoniales de legisladores nacionales, desde los datos abiertos de la Oficina
Anticorrupción (datos.jus.gob.ar, "Declaraciones Juradas Patrimoniales Integrales", Ley 26.857).

Entrada: storage/oficial/ddjj/declaraciones_juradas_AAAA.csv.gz  (el archivo principal de cada año fiscal;
         agregar un año es copiar su archivo y volver a correr este script).
Salida:  storage/verificado/ddjj.json   slug -> lista de años, solo con totales.

NO se publica: CUIT, grupo familiar, ni la descripción de cada bien. Los montos están en pesos de cada año.
Los montos vienen escritos "155817519-23" (155.817.519,23) y los negativos "-3000000-00".

Uso:  python3 -I scripts/construir_ddjj.py   (después de construir_datos.py)
"""
import collections, csv, glob, gzip, io, json, re, sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from construir_datos import norm  # noqa: E402

RAIZ = Path(__file__).resolve().parent.parent
ENT = RAIZ / "storage" / "oficial" / "ddjj"
SAL = RAIZ / "storage" / "verificado"
ORGANISMOS = {"HONORABLE CAMARA DE DIPUTADOS DE LA NACION": "Diputados", "HONORABLE SENADO DE LA NACION": "Senadores"}
TIPOS = {"Anual": 0, "Inicial": 1, "Baja": 2}
MONTO = re.compile(r"^(-?)(\d*)-(\d{2})$")


def monto(s):
    s = (s or "").strip()
    if not s:
        return None
    m = MONTO.match(s)
    if not m:
        return None
    v = float(f"{m[2] or 0}.{m[3]}")
    return -v if m[1] else v


def tokens(s):
    return norm(s or "").split()


def main() -> int:
    csv.field_size_limit(10**8)
    legs = json.loads((SAL / "legisladores.json").read_text(encoding="utf-8"))
    filas = []
    for ruta in sorted(glob.glob(str(ENT / "declaraciones_juradas_*.csv.gz"))):
        anio = re.search(r"_(\d{4})\.csv", ruta).group(1)
        with gzip.open(ruta, "rt", encoding="utf-8-sig", newline="") as fh:
            for r in csv.DictReader(fh):
                if None in r.values() or r.get("organismo") not in ORGANISMOS:
                    continue
                if not re.match(r"^(diputad|senador)", (r.get("cargo") or "").strip(), re.I):
                    continue
                camara = "Senadores" if re.match(r"^senador", r["cargo"].strip(), re.I) else "Diputados"
                if camara != ORGANISMOS[r["organismo"]]:
                    continue
                r["_anio"], r["_camara"] = anio, camara
                filas.append(r)

    salida, usadas, ambiguos = {}, set(), []
    for l in legs:
        ap = set(tokens(l["apellido"] or l["nombre"].split()[-1]))
        resto = tokens(l["nombre"].replace(l["apellido"] or "", "", 1))
        cand = [i for i, r in enumerate(filas)
                if r["_camara"] == l["camara"] and ap <= set(tokens(r["funcionario_apellido_nombre"]))
                and (not resto or resto[0] in tokens(r["funcionario_apellido_nombre"]))]
        personas = {tuple(sorted(tokens(filas[i]["funcionario_apellido_nombre"]))) for i in cand}
        if len(personas) > 1:
            ambiguos.append(l["nombre"])
            continue
        if not cand:
            continue
        usadas.update(cand)
        por_anio = collections.defaultdict(list)
        for i in cand:
            por_anio[filas[i]["_anio"]].append(filas[i])
        anios = []
        for anio, rs in sorted(por_anio.items(), reverse=True):
            # Se elige una declaración por año: Anual antes que Inicial y Baja; la más rectificada.
            r = sorted(rs, key=lambda x: (TIPOS.get(x["tipo_declaracion_jurada_descripcion"], 9), -int(x["rectificativa"] or 0), -int(x["dj_id"] or 0)))[0]
            bf, df = monto(r["total_bienes_final"]), monto(r["total_deudas_final"])
            bi, di = monto(r["total_bienes_inicio"]), monto(r["deudas_inicio"])
            if bf is None:
                continue
            anios.append({
                "anio": int(anio), "tipo": r["tipo_declaracion_jurada_descripcion"], "rectificativa": int(r["rectificativa"] or 0),
                "bienes_inicio": bi, "deudas_inicio": di, "bienes_final": bf, "deudas_final": df or 0.0,
                "patrimonio_neto_final": round(bf - (df or 0.0), 2),
                "ingresos_netos": monto(r["ingresos_neto_gastos"]),
            })
        if anios:
            salida[l["slug"] if l["camara"] == "Diputados" else "s:" + l["slug"]] = anios

    (SAL / "ddjj.json").write_text(json.dumps(salida, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"{len(filas)} declaraciones de legisladores nacionales; {len(salida)} legisladores actuales cruzados; "
          f"{len({tuple(sorted(tokens(filas[i]['funcionario_apellido_nombre']))) for i in range(len(filas)) if i not in usadas})} personas del archivo ya no están en el cargo; {len(ambiguos)} ambiguos")
    return 0


if __name__ == "__main__":
    sys.exit(main())
