#!/usr/bin/env python3
"""
Construye storage/verificado/coautorias_senado.json: con qué otros senadores en ejercicio comparte proyectos cada senador.

Fuente: la lista de proyectos de cada senador en senado.gob.ar (storage/verificado/proyectos/s-*.json, construir_proyectos.py),
que incluye los proyectos que firmó con otros. Dos senadores son coautores de un proyecto cuando el mismo expediente figura
en las listas de ambos. Solo se ven coautorías entre senadores en ejercicio (de quienes ya no lo son no hay lista).

Se cuentan los proyectos presentados desde el 10/12/2025, para que el bloque de cada uno sea el actual. En Diputados no se
puede hacer: el archivo oficial trae un solo autor por proyecto, sin cofirmantes.

Uso:  python3 -I scripts/construir_coautorias.py
"""
import collections, glob, json
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
VER = RAIZ / "storage" / "verificado"
DESDE = "2025-12-10"


def main() -> int:
    bloque = {l["slug"]: l["bloque_oficial"] for l in json.load(open(VER / "legisladores.json", encoding="utf-8")) if l["camara"] == "Senadores"}
    por_exp = collections.defaultdict(set)
    propios = collections.Counter()
    for ruta in glob.glob(str(VER / "proyectos" / "s-*.json")):
        slug = Path(ruta).stem[2:]
        if slug not in bloque:
            continue
        for p in json.load(open(ruta, encoding="utf-8"))["proyectos"]:
            if p[1] >= DESDE:
                por_exp[p[0]].add(slug)
                propios[slug] += 1
    pares = collections.Counter()
    con_otros = collections.Counter()
    con_otros_bloques = collections.Counter()
    for firmantes in por_exp.values():
        if len(firmantes) < 2:
            continue
        for s in firmantes:
            con_otros[s] += 1
            if any(bloque[o] != bloque[s] for o in firmantes if o != s):
                con_otros_bloques[s] += 1
            for o in firmantes:
                if o != s:
                    pares[(s, o)] += 1
    salida = {}
    for s in sorted(propios):
        socios = sorted(((o, n) for (a, o), n in pares.items() if a == s), key=lambda x: (-x[1], x[0]))
        salida["s:" + s] = {
            "proyectos": propios[s], "con_otros": con_otros[s], "con_otros_bloques": con_otros_bloques[s],
            "socios": [[o, n, bloque[o] == bloque[s]] for o, n in socios],
        }
    salida["_meta"] = {"desde": DESDE, "fuente": "senado.gob.ar: proyectos de cada senador en ejercicio",
                       "criterio": "mismo expediente en las listas de dos senadores en ejercicio; bloque actual",
                       "expedientes": len(por_exp), "expedientes_compartidos": sum(1 for f in por_exp.values() if len(f) > 1)}
    (VER / "coautorias_senado.json").write_text(json.dumps(salida, ensure_ascii=False, separators=(",", ":")) + "\n", encoding="utf-8")
    m = salida["_meta"]
    print(f"{m['expedientes']} expedientes desde {DESDE}, {m['expedientes_compartidos']} compartidos; {len(salida) - 1} senadores")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
