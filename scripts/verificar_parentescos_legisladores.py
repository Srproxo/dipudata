#!/usr/bin/env python3
"""
Parentescos DECLARADOS entre legisladores en ejercicio, verificados por número de CUIT (no por nombre).

Qué hace: en el archivo "Grupo familiar" de la Oficina Anticorrupción cada fila es un familiar que un funcionario declaró (con su CUIT).
Si el CUIT de ese familiar es el de otro legislador en ejercicio, queda confirmado que un legislador declaró como familiar a otro: es
una coincidencia de identificador, no de nombre, así que no tiene el problema de los homónimos.

Cómo une a cada legislador con su CUIT sin adivinar por nombre: storage/verificado/ddjj.json guarda el `dj_id` (número interno de la
declaración) de cada año publicado; el archivo crudo de declaraciones trae el CUIT de cada `dj_id`. El CUIT solo se usa en memoria para
unir: NO se escribe en ningún archivo.

Entrada (se bajan con bajar_ckan.py, ver CLAUDE.md; están en tu máquina y no se suben al repositorio):
  --declaraciones  CSV consolidado de declaraciones del año fiscal 2024 (columnas dj_id y cuit).
  --familia        CSV "Grupo familiar" (columnas cuit, familiar_cuit, familiar_parentesco, anio).
Salida:
  informes_privados/parentescos_revision.csv   para revisar (ignorado por git).
  storage/verificado/parentescos.json          solo con --publicar: declarante, familiar (slugs), vínculo declarado, año. Sin CUIT.

Uso:  python3 -I scripts/verificar_parentescos_legisladores.py --declaraciones "storage/oficial/ckan/gobar/declaraciones-juradas-patrimoniales-integrales/3506158d*.csv" --familia "informes_privados/aeb174ff*.csv"
"""
import argparse, collections, csv, glob, json, re, sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
PRIVADO = RAIZ / "informes_privados"
SAL = RAIZ / "storage" / "verificado"
VINCULOS = {"CONYUGE / CONVIVIENTE": "cónyuge o conviviente", "HIJO/A": "hijo/a"}


def filas(ruta):
    with open(ruta, encoding="utf-8-sig", errors="replace", newline="") as f:
        for r in csv.DictReader(f):
            yield {(k or "").strip(): (v or "").strip() for k, v in r.items() if k is not None}


def cuit_valido(s):
    d = re.sub(r"\D", "", s or "")
    return d if len(d) == 11 and set(d) != {"0"} else ""


def verificar(declaraciones, familia, ddjj_json, publicar):
    csv.field_size_limit(10**8)
    dj = json.loads(Path(ddjj_json).read_text(encoding="utf-8"))
    # dj_id -> legislador (clave de ddjj.json: "slug" de diputado o "s:slug" de senador)
    por_dj = {}
    for clave, anios in dj.items():
        for a in anios:
            if a.get("dj_id"):
                por_dj[str(a["dj_id"])] = clave
    # CUIT -> legislador, solo en memoria
    cuit_leg, ambiguos = {}, set()
    for r in filas(declaraciones):
        clave = por_dj.get(r.get("dj_id", ""))
        c = cuit_valido(r.get("cuit", ""))
        if not clave or not c:
            continue
        if cuit_leg.get(c, clave) != clave:
            ambiguos.add(c)
        cuit_leg[c] = clave
    for c in ambiguos:
        cuit_leg.pop(c, None)
    # Relaciones: declarante (CUIT) -> familiar (CUIT), ambos legisladores en ejercicio.
    rel, otros_vinculos = {}, collections.Counter()
    for r in filas(familia):
        a, b = cuit_leg.get(cuit_valido(r.get("cuit", ""))), cuit_leg.get(cuit_valido(r.get("familiar_cuit", "")))
        if not a or not b or a == b:
            continue
        par = r.get("familiar_parentesco", "").upper()
        if par not in VINCULOS:
            otros_vinculos[par] += 1
            continue
        rel[(a, b, par)] = r.get("anio", "")
    salida = []
    for (a, b, par), anio in sorted(rel.items()):
        salida.append({"declarante": a, "familiar": b, "vinculo": par, "reciproco": any(k[0] == b and k[1] == a and k[2] == par for k in rel), "anio": anio})
    PRIVADO.mkdir(exist_ok=True)
    with open(PRIVADO / "parentescos_revision.csv", "w", encoding="utf-8", newline="") as f:
        w = csv.DictWriter(f, fieldnames=["declarante", "familiar", "vinculo", "reciproco", "anio"])
        w.writeheader()
        w.writerows(salida)
    if publicar:
        pub = [{"declarante": x["declarante"], "familiar": x["familiar"], "vinculo": x["vinculo"], "reciproco": x["reciproco"], "anio": int(x["anio"] or 0)} for x in salida]
        (SAL / "parentescos.json").write_text(json.dumps(pub, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"Legisladores en ejercicio con CUIT identificado: {len(set(cuit_leg.values()))} (CUIT ambiguos descartados: {len(ambiguos)})")
    print(f"Parentescos declarados entre legisladores confirmados por CUIT: {len(salida)} (recíprocos: {sum(1 for x in salida if x['reciproco'])})")
    print("Por vínculo:", dict(collections.Counter(x["vinculo"] for x in salida)))
    if otros_vinculos:
        print("Otros vínculos entre legisladores (no se publican):", dict(otros_vinculos))
    print(f"Revisión: {PRIVADO / 'parentescos_revision.csv'}" + (f" | Publicable: {SAL / 'parentescos.json'}" if publicar else " (sin --publicar no se escribe parentescos.json)"))


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--declaraciones", required=True)
    ap.add_argument("--familia", required=True)
    ap.add_argument("--ddjj", default=str(SAL / "ddjj.json"))
    ap.add_argument("--publicar", action="store_true")
    a = ap.parse_args()
    d, f = sorted(glob.glob(a.declaraciones)), sorted(glob.glob(a.familia))
    if len(d) != 1 or len(f) != 1:
        sys.exit(f"Hace falta exactamente un archivo de declaraciones ({len(d)}) y uno de familia ({len(f)}).")
    verificar(d[0], f[0], a.ddjj, a.publicar)


if __name__ == "__main__":
    main()
