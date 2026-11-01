#!/usr/bin/env python3
"""
Descarga las votaciones nominales del Senado directamente de senado.gob.ar (fuente primaria) y las guarda,
ya interpretadas, en storage/oficial/senado/votaciones_desde_AAAA-MM-DD.json.gz.

Cada votación trae el ID oficial de cada senador, así que el cruce con el padrón no depende de nombres.
Cada una se valida: los totales de la cabecera deben coincidir con el recuento de votos individuales.

Se recorre un rango de números de acta y se descartan los anteriores a DESDE. El rango se corta cuando hay
varias actas seguidas inexistentes.

Uso:  python3 -I scripts/bajar_votaciones_senado.py [desde_id] [hasta_id]
"""
import gzip, html, json, re, sys, time, urllib.request
from pathlib import Path

DESDE = "2025-12-10"
DEST = Path(__file__).resolve().parent.parent / "storage" / "oficial" / "senado"
VOTOS = {"AFIRMATIVO": "A", "NEGATIVO": "N", "ABSTENCION": "B", "ABSTENCIÓN": "B", "AUSENTE": "U"}


def pedir(n: int):
    req = urllib.request.Request(f"https://www.senado.gob.ar/votaciones/detalleActa/{n}", headers={"User-Agent": "Mozilla/5.0"})
    for intento in range(3):
        try:
            with urllib.request.urlopen(req, timeout=45) as r:
                return r.read().decode("utf-8", errors="replace")
        except Exception:
            time.sleep(2 * (intento + 1))
    return ""


def limpiar(t: str) -> str:
    t = re.sub(r"<script.*?</script>|<style.*?</style>|<!--.*?-->", " ", t, flags=re.S)
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", t))).strip()


def interpretar(n: int, t: str):
    txt = limpiar(t)
    m = re.search(r"Acta Nro:\s*(\d+)\s+(.*?)\s+(\d{2})/(\d{2})/(\d{4})\s*-\s*(\d{2}:\d{2})?\s*:?\s*(.*?)\s*(\d+)\s+AFIRMATIVOS\s+(\d+)\s+NEGATIVOS\s+(\d+)\s+ABSTENCIONES\s+(\d+)\s+AUSENTES", txt)
    if not m:
        return None
    # Entre la hora y los totales viene "<mayoría> <RESULTADO> <tipo de votación>"; la mayoría puede estar vacía.
    resto = m[7].strip()
    r = re.search(r"\b(AFIRMATIVO|NEGATIVO)\b", resto)
    if not r:
        return None
    mayoria, resultado, tipo = resto[:r.start()].strip(), r.group(1), resto[r.end():].strip()
    votos = []
    for fila in re.findall(r"<tr[^>]*>(.*?)</tr>", t, flags=re.S):
        sid = re.search(r"/senadores/senador/(\d+)", fila)
        celdas = [limpiar(c) for c in re.findall(r"<td[^>]*>(.*?)</td>", fila, flags=re.S)]
        celdas = [c for c in celdas if c]
        if len(celdas) < 4 or VOTOS.get(celdas[-1].upper()) is None and not sid:
            continue
        voto = VOTOS.get(celdas[-1].upper())
        # Algún senador no tiene enlace con ID en la página: en ese caso el ID queda en 0 y se cruza por nombre.
        votos.append([int(sid.group(1)) if sid else 0, celdas[0], celdas[1], celdas[2], voto or "X"])
    # Alguna página repite la tabla de votos: se conserva una sola fila por senador.
    vistos, unicos = set(), []
    for v in votos:
        k = v[0] or v[1]
        if k not in vistos:
            vistos.add(k)
            unicos.append(v)
    votos = unicos
    return {"id": n, "acta": int(m[1]), "titulo": m[2].strip(), "fecha": f"{m[5]}-{m[4]}-{m[3]}", "hora": m[6] or "", "mayoria": mayoria,
            "resultado": resultado, "tipo": tipo, "afirmativos": int(m[8]), "negativos": int(m[9]), "abstenciones": int(m[10]),
            "ausentes": int(m[11]), "votos": votos}


def main() -> int:
    desde = int(sys.argv[1]) if len(sys.argv) > 1 else 2595
    hasta = int(sys.argv[2]) if len(sys.argv) > 2 else 3200
    out, vacias = [], 0
    for n in range(desde, hasta + 1):
        v = interpretar(n, pedir(n))
        if v is None:
            vacias += 1
            if vacias >= 15 and n > desde + 50:
                break
            continue
        vacias = 0
        if v["fecha"] >= DESDE:
            out.append(v)
        time.sleep(0.2)
    DEST.mkdir(parents=True, exist_ok=True)
    with gzip.open(DEST / f"votaciones_desde_{DESDE}.json.gz", "wt", encoding="utf-8") as f:
        json.dump(out, f, ensure_ascii=False, separators=(",", ":"))
    print(f"{len(out)} votaciones del Senado desde {DESDE} (actas {out[0]['id']} a {out[-1]['id']})" if out else "sin datos")
    return 0


if __name__ == "__main__":
    sys.exit(main())
