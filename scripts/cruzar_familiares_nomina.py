#!/usr/bin/env python3
"""
Cruce PRIVADO: familiares declarados por los legisladores (declaración jurada, archivo "Grupo familiar" de la Oficina Anticorrupción)
contra las nóminas de personal de las Cámaras. Corre en la máquina de quien lo ejecuta y deja el informe SOLO en informes_privados/
(ignorado por git). No publica nada ni se conecta a ningún servicio.

Qué es y qué no es el resultado:
  - Una coincidencia es una PISTA: el nombre de un familiar declarado por un legislador aparece también en la nómina de personal.
    No prueba que sea la misma persona (hay homónimos) ni dice que ese legislador lo haya contratado: las nóminas indican el área
    ("estructura"), no a qué legislador está asignada cada persona.
  - Cada fila lleva la calidad del cruce (exacto o parcial), cuántos homónimos hay en la nómina y qué tan frecuente es el nombre más
    raro, para priorizar. Todo se verifica a mano contra la fuente oficial antes de afirmar algo; para publicar un hecho hace falta la
    respuesta oficial de la Cámara (pedido de acceso a la información pública, Ley 27.275).
  - También se marcan los familiares que son legisladores en ejercicio y las estructuras de la nómina que mencionan a un legislador.

Entrada:
  --familia   CSV "Grupo familiar" de la OA (columnas dj_id, funcionario_apellido_nombre, familiar_apellido_nombre, familiar_parentesco, ...).
  --nomina    uno o más CSV de nómina de personal con columnas APELLIDO y NOMBRE (las demás se copian como contexto).
  --ddjj      archivo anual ya filtrado a legisladores (por defecto storage/oficial/ddjj/declaraciones_juradas_2024.csv.gz): define de quiénes
              son las declaraciones que se miran.
Salida: informes_privados/cruce_familiares_nomina.csv (no se escribe en ningún otro lugar) y un resumen SIN nombres por pantalla.

Uso:  python3 -I scripts/cruzar_familiares_nomina.py --familia informes_privados/aeb174ff_*.csv --nomina storage/oficial/ckan/hcdn/nomina-de-personal/*.csv
"""
import argparse, collections, csv, glob, gzip, json, sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from construir_datos import norm  # noqa: E402

RAIZ = Path(__file__).resolve().parent.parent
PRIVADO = RAIZ / "informes_privados"


def filas(ruta):
    abrir = gzip.open if str(ruta).endswith(".gz") else open
    with abrir(ruta, "rt", encoding="utf-8-sig", errors="replace", newline="") as f:
        for r in csv.DictReader(f):
            yield {(k or "").strip(): (v or "").strip() for k, v in r.items() if k is not None}


def tokens(s):
    return norm(s or "").split()


def cruzar(familia, nominas, ddjj, legisladores_json, salida):
    csv.field_size_limit(10**8)
    # 1) De quiénes son las declaraciones: las del archivo anual ya filtrado a legisladores.
    declarantes = {}
    for r in filas(ddjj):
        declarantes[r["dj_id"]] = (r.get("funcionario_apellido_nombre", ""), r.get("cargo", ""))
    # 2) Legisladores en ejercicio (para marcar familiares que lo son y estructuras que los mencionan).
    legis = []
    if legisladores_json.is_file():
        datos = json.loads(legisladores_json.read_text(encoding="utf-8"))
        for l in (datos if isinstance(datos, list) else list(datos.values())):
            t = frozenset(tokens(l.get("nombre", "")))
            if len(t) >= 2:
                legis.append((t, l.get("nombre", "")))
    # 3) Nóminas: una fila por persona, con índice por palabra.
    nomina, indice, frecuencia = [], collections.defaultdict(list), collections.Counter()
    for ruta in nominas:
        for r in filas(ruta):
            ru = {k.upper(): v for k, v in r.items()}
            if "APELLIDO" not in ru or "NOMBRE" not in ru:
                continue
            t = frozenset(tokens(ru["APELLIDO"] + " " + ru["NOMBRE"]))
            if len(t) < 2:
                continue
            i = len(nomina)
            nomina.append({"t": t, "ru": ru, "fuente": Path(ruta).name})
            for w in t:
                indice[w].append(i)
                frecuencia[w] += 1
    # 4) Familiares de los legisladores (sin repetir: el archivo trae una fila por declaración y rectificativa).
    vistos, familiares = set(), []
    for r in filas(familia):
        d = declarantes.get(r.get("dj_id", ""))
        if not d:
            continue
        nombre = r.get("familiar_apellido_nombre", "")
        t = frozenset(tokens(nombre))
        clave = (d[0], t, r.get("familiar_parentesco", ""))
        if len(t) < 2 or clave in vistos:
            continue
        vistos.add(clave)
        familiares.append({"decl": d, "nombre": nombre, "t": t, "parentesco": r.get("familiar_parentesco", "")})
    # 5) Cruce.
    hits = []
    for fam in familiares:
        t = fam["t"]
        raro = min(t, key=lambda w: frecuencia.get(w, 0) or 10**9)
        cand = indice.get(raro, [])
        ex, par = [], []
        for i in cand:
            tn = nomina[i]["t"]
            if tn == t:
                ex.append(i)
            elif min(len(tn), len(t)) >= 2 and (tn <= t or t <= tn):
                par.append(i)
        for calidad, lista in (("exacto", ex), ("parcial", par)):
            for i in lista:
                n = nomina[i]
                est = n["ru"].get("ESTRUCTURA", "")
                te = frozenset(tokens(est))
                menciona = [nm for tl, nm in legis if tl <= te]
                hits.append({
                    "calidad": calidad, "homonimos_en_nomina": len(lista), "frecuencia_palabra_mas_rara": frecuencia.get(raro, 0),
                    "legislador_declarante": fam["decl"][0], "cargo_declaracion": fam["decl"][1], "parentesco": fam["parentesco"],
                    "familiar": fam["nombre"], "nomina": n["fuente"], "legajo": n["ru"].get("LEGAJO", ""),
                    "escalafon": n["ru"].get("ESCALAFON", ""), "estructura": est, "convenio": n["ru"].get("CONVENIO", ""),
                    "estructura_menciona_legislador": "; ".join(menciona),
                    "familiar_es_legislador_en_ejercicio": "",
                })
    # 6) Familiares que son legisladores en ejercicio (aparte de la nómina).
    pares = []
    for fam in familiares:
        for tl, nm in legis:
            ft = fam["t"]
            if ft == tl or (min(len(ft), len(tl)) >= 2 and (ft <= tl or tl <= ft)):
                pares.append({"calidad": "familiar legislador (" + ("exacto" if ft == tl else "parcial") + ")", "homonimos_en_nomina": "", "frecuencia_palabra_mas_rara": "",
                              "legislador_declarante": fam["decl"][0], "cargo_declaracion": fam["decl"][1], "parentesco": fam["parentesco"],
                              "familiar": fam["nombre"], "nomina": "", "legajo": "", "escalafon": "", "estructura": "", "convenio": "",
                              "estructura_menciona_legislador": "", "familiar_es_legislador_en_ejercicio": nm})
    orden = {"exacto": 0, "parcial": 1}
    hits.sort(key=lambda h: (orden[h["calidad"]], h["homonimos_en_nomina"], h["frecuencia_palabra_mas_rara"], h["legislador_declarante"]))
    todo = hits + pares
    salida.parent.mkdir(parents=True, exist_ok=True)
    cols = list(todo[0].keys()) if todo else ["calidad"]
    with open(salida, "w", encoding="utf-8", newline="") as f:
        w = csv.DictWriter(f, fieldnames=cols)
        w.writeheader()
        w.writerows(todo)
    # Resumen sin nombres de personas.
    est_top = collections.Counter(n["ru"].get("ESTRUCTURA", "") for n in nomina).most_common(15)
    est_con_legis = sum(1 for n in nomina if any(tl <= frozenset(tokens(n["ru"].get("ESTRUCTURA", ""))) for tl, _ in legis))
    print(f"Declaraciones de legisladores consideradas: {len(declarantes)}")
    print(f"Familiares distintos revisados: {len(familiares)} | personas en la nómina: {len(nomina)}")
    print(f"Coincidencias exactas: {sum(1 for h in hits if h['calidad'] == 'exacto')} | parciales: {sum(1 for h in hits if h['calidad'] == 'parcial')}")
    print(f"Coincidencias exactas con un solo candidato en la nómina: {sum(1 for h in hits if h['calidad'] == 'exacto' and h['homonimos_en_nomina'] == 1)}")
    print(f"Familiares que son legisladores en ejercicio: {len(pares)}")
    print(f"Filas de nómina cuya estructura menciona a un legislador: {est_con_legis}")
    print("Estructuras más frecuentes de la nómina (no son datos personales):")
    for e, c in est_top:
        print(f"  {c:5d}  {e}")
    print(f"\nInforme: {salida}  (solo queda en esta carpeta, ignorada por git)")


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--familia", required=True)
    ap.add_argument("--nomina", nargs="+", required=True)
    ap.add_argument("--ddjj", default=str(RAIZ / "storage" / "oficial" / "ddjj" / "declaraciones_juradas_2024.csv.gz"))
    ap.add_argument("--salida", default=str(PRIVADO / "cruce_familiares_nomina.csv"))
    a = ap.parse_args()
    salida = Path(a.salida).resolve()
    try:
        salida.relative_to(PRIVADO.resolve())
    except ValueError:
        sys.exit(f"El informe solo se escribe dentro de {PRIVADO} (ignorada por git). Salida pedida: {salida}")
    fam = [p for g in [a.familia] for p in sorted(glob.glob(g))]
    nom = [p for g in a.nomina for p in sorted(glob.glob(g)) if p.lower().endswith(".csv")]
    if len(fam) != 1 or not nom:
        sys.exit(f"Hace falta exactamente un archivo de familia ({len(fam)} encontrados) y al menos una nómina CSV ({len(nom)} encontradas).")
    cruzar(fam[0], nom, a.ddjj, RAIZ / "storage" / "verificado" / "legisladores.json", salida)


if __name__ == "__main__":
    main()
