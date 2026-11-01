#!/usr/bin/env python3
"""
Descarga las votaciones nominales del Senado directamente de senado.gob.ar (fuente primaria) y las guarda,
ya interpretadas, en storage/oficial/senado/.

Cada votación se valida: los totales de la cabecera deben coincidir con el recuento de votos individuales, y
debe haber 72 senadores. Las páginas recientes traen el ID oficial de cada senador; las antiguas casi nunca
(en ese caso el ID queda en 0 y el cruce se hace después por nombre y provincia).

Los números de acta de la página no siguen el orden de las fechas: hay que recorrer todo el rango.

Modos:
  python3 -I scripts/bajar_votaciones_senado.py                 mandato actual (desde el 10/12/2025)
  python3 -I scripts/bajar_votaciones_senado.py --historico     todas las anteriores, desde 2005 (ids 1 a 3000)
  python3 -I scripts/bajar_votaciones_senado.py --historico 1500 3000   solo un tramo (para correr dos a la vez)

El modo histórico es reanudable: cada página leída se anota en storage/oficial/senado/votaciones_historico.parcial.jsonl
(ignorado por git), y si se corta, al volver a correrlo sigue desde donde quedó. Hay una pausa de cortesía entre pedidos.
"""
import gzip, html, json, re, sys, time, urllib.request
from pathlib import Path

DESDE = "2025-12-10"
DESDE_HISTORICO = "2005-01-01"
DEST = Path(__file__).resolve().parent.parent / "storage" / "oficial" / "senado"
PARCIAL = DEST / "votaciones_historico.parcial.jsonl"
VOTOS = {"AFIRMATIVO": "A", "NEGATIVO": "N", "ABSTENCION": "B", "ABSTENCIÓN": "B", "AUSENTE": "U"}
PAUSA = 0.6  # segundos entre pedidos
TOTAL = 3000  # los números de acta llegan a ~2900; las votaciones de 2005 tienen números por encima de 2700


def pedir(n: int):
    req = urllib.request.Request(f"https://www.senado.gob.ar/votaciones/detalleActa/{n}", headers={"User-Agent": "Mozilla/5.0 (DipuData; datos abiertos)"})
    for intento in range(4):
        try:
            with urllib.request.urlopen(req, timeout=45) as r:
                return r.read().decode("utf-8", errors="replace")
        except Exception:
            time.sleep(3 * (intento + 1))
    return None  # None = no se pudo leer (distinto de una página sin acta)


def limpiar(t: str) -> str:
    t = re.sub(r"<script.*?</script>|<style.*?</style>|<!--.*?-->", " ", t, flags=re.S)
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", t))).strip()


def interpretar(n: int, t: str):
    txt = limpiar(t)
    # El título puede faltar ("Acta Nro: 57 30/11/2016 - ..."). Una página sin acta muestra "Acta Nro:" sin número.
    m = re.search(r"Acta Nro:\s*(\d+)\s+(.*?)\s*(\d{2})/(\d{2})/(\d{4})\s*-\s*(\d{2}:\d{2})?\s*:?\s*(.*?)\s*(\d+)\s+AFIRMATIVOS\s+(\d+)\s+NEGATIVOS\s+(\d+)\s+ABSTENCIONES\s+(\d+)\s+AUSENTES", txt)
    if not m:
        return None
    # Entre la hora y los totales viene "<mayoría> <RESULTADO> <tipo de votación>"; la mayoría puede estar vacía.
    # El resultado suele ser AFIRMATIVO o NEGATIVO; también aparecen EMPATE, CANCELADA / LEV.VOT. (votación levantada)
    # y, en algunas páginas, AUSENTE o ABSTENCION en lugar de un resultado.
    resto = m[7].strip()
    r = re.search(r"\b(AFIRMATIVO|NEGATIVO|EMPATE|AUSENTE|ABSTENCI[OÓ]N|CANCELADA(?:\s+LEV\.\s?VOT\.)?|LEV\.\s?VOT\.)", resto)
    if not r:
        return None
    mayoria, resultado, tipo = resto[:r.start()].strip(), r.group(1), resto[r.end():].strip()
    cancelada = resultado.startswith(("CANCELADA", "LEV"))
    if resultado not in ("AFIRMATIVO", "NEGATIVO", "EMPATE"):
        resultado = ""  # la página no informa un resultado
    votos = []
    for fila in re.findall(r"<tr[^>]*>(.*?)</tr>", t, flags=re.S):
        sid = re.search(r"/senadores/senador/(\d+)", fila)
        # Columnas: foto, senador, bloque, provincia, voto. El bloque puede venir vacío: se leen por posición.
        celdas = [limpiar(c) for c in re.findall(r"<td[^>]*>(.*?)</td>", fila, flags=re.S)]
        if len(celdas) < 4:
            continue
        voto = VOTOS.get(celdas[-1].upper())
        if voto is None and not sid:
            continue
        votos.append([int(sid.group(1)) if sid else 0, celdas[-4], celdas[-3], celdas[-2], voto or "X"])
    # Alguna página repite la tabla de votos: se conserva una sola fila por senador.
    vistos, unicos = set(), []
    for v in votos:
        k = v[0] or v[1]
        if k not in vistos:
            vistos.add(k)
            unicos.append(v)
    titulo = m[2].strip()
    anulada = cancelada or bool(re.search(r"VOTACI[OÓ]N (ANULADA|CANCELADA)", titulo, re.I))
    return {"id": n, "acta": int(m[1]), "titulo": titulo, "anulada": anulada, "fecha": f"{m[5]}-{m[4]}-{m[3]}", "hora": m[6] or "", "mayoria": mayoria,
            "resultado": resultado, "tipo": tipo, "afirmativos": int(m[8]), "negativos": int(m[9]), "abstenciones": int(m[10]),
            "ausentes": int(m[11]), "votos": unicos}


def guardar(nombre, datos):
    DEST.mkdir(parents=True, exist_ok=True)
    datos.sort(key=lambda v: (v["fecha"], v["hora"], v["id"]))
    with gzip.open(DEST / nombre, "wt", encoding="utf-8") as f:
        json.dump(datos, f, ensure_ascii=False, separators=(",", ":"))


def historico(desde: int, hasta: int, total: int = TOTAL, reintentar_vacias: bool = False) -> int:
    """Lee las actas desde..hasta. Se pueden correr dos tramos a la vez: cada uno anota en su propio archivo parcial."""
    hechos = {}
    for ruta in sorted(DEST.glob("votaciones_historico.parcial*.jsonl")):
        for linea in ruta.read_text(encoding="utf-8").splitlines():
            try:
                d = json.loads(linea)
            except ValueError:
                continue  # una línea cortada por una interrupción: se vuelve a pedir
            previo = hechos.get(d["n"])
            # Una lectura con acta vale más que una vacía o con error (las vacías se pueden haber vuelto a pedir).
            if previo is None or previo.get("error") or (not previo.get("v") and not d.get("error")):
                hechos[d["n"]] = d
    pendientes = [n for n in range(desde, hasta + 1) if n not in hechos or hechos[n].get("error")
                  or (reintentar_vacias and not hechos[n].get("v"))]
    print(f"{len(hechos)} páginas ya leídas, {len(pendientes)} pendientes en {desde}-{hasta}", flush=True)
    parcial = PARCIAL if (desde, hasta) == (1, total) and not reintentar_vacias else DEST / f"votaciones_historico.parcial.{desde}-{hasta}.jsonl"
    with parcial.open("a", encoding="utf-8") as f:
        for i, n in enumerate(pendientes, 1):
            t = pedir(n)
            d = {"n": n, "error": True} if t is None else {"n": n, "v": interpretar(n, t)}
            hechos[n] = d
            f.write(json.dumps(d, ensure_ascii=False, separators=(",", ":")) + "\n")
            f.flush()
            if i % 100 == 0:
                print(f"  {i}/{len(pendientes)} (acta {n})", flush=True)
            time.sleep(PAUSA)
    faltan = [n for n in range(1, total + 1) if n not in hechos]
    if faltan:
        print(f"Tramo terminado. Faltan {len(faltan)} páginas de otros tramos; el archivo final se arma cuando estén todas.")
        return 0
    errores = sorted(n for n, d in hechos.items() if d.get("error"))
    out = [d["v"] for d in hechos.values() if d.get("v") and DESDE_HISTORICO <= d["v"]["fecha"] < DESDE]
    guardar(f"votaciones_{DESDE_HISTORICO[:4]}_a_{DESDE}.json.gz", out)
    sin_acta = sum(1 for d in hechos.values() if not d.get("error") and not d.get("v"))
    print(f"{len(out)} votaciones del Senado entre {DESDE_HISTORICO} y {DESDE}; {sin_acta} números sin acta; "
          f"{len(errores)} páginas que no se pudieron leer{': ' + str(errores[:20]) if errores else ''}")
    return 1 if errores else 0


def main() -> int:
    if "--historico" in sys.argv:
        nums = [int(a) for a in sys.argv[1:] if a.isdigit()]
        return historico(*(nums[:2] if len(nums) >= 2 else (1, TOTAL)), reintentar_vacias="--reintentar-vacias" in sys.argv)
    desde = int(sys.argv[1]) if len(sys.argv) > 1 else 2595
    hasta = int(sys.argv[2]) if len(sys.argv) > 2 else 3200
    out, vacias = [], 0
    for n in range(desde, hasta + 1):
        t = pedir(n)
        v = interpretar(n, t or "")
        if v is None:
            vacias += 1
            if vacias >= 15 and n > desde + 50:
                break
            continue
        vacias = 0
        if v["fecha"] >= DESDE:
            out.append(v)
        time.sleep(PAUSA)
    guardar(f"votaciones_desde_{DESDE}.json.gz", out)
    print(f"{len(out)} votaciones del Senado desde {DESDE} (actas {out[0]['id']} a {out[-1]['id']})" if out else "sin datos")
    return 0


if __name__ == "__main__":
    sys.exit(main())
