#!/usr/bin/env python3
"""
Descarga las votaciones nominales del Senado directamente de senado.gob.ar (fuente primaria) y las guarda,
ya interpretadas, en storage/oficial/senado/votaciones_senado.json.gz.

Los números de acta (detalleActa/{id}) no siguen el orden de las fechas y las inexistentes devuelven una página vacía,
así que se recorre todo el rango. Se conservan las votaciones desde DESDE (1/1/2005).
Cada fila trae, cuando la página lo tiene, el ID oficial del senador; en las actas viejas casi ninguna lo trae y el cruce
se hace después con el padrón (ver construir_votaciones.py).
Cada votación se valida más adelante: los totales de la cabecera deben coincidir con el recuento voto por voto.

Es reanudable: el archivo guarda qué números ya se consultaron y se reescribe cada tanto. Hay una pausa de cortesía entre pedidos.
Los números mayores al último con datos se vuelven a consultar siempre (así entran las votaciones nuevas).

Uso:  python3 -I scripts/bajar_votaciones_senado.py [desde_id] [hasta_id]
"""
import concurrent.futures, gzip, html, json, re, sys, time, urllib.request
from pathlib import Path

DESDE = "2005-01-01"
PAUSA = 0.5
HILOS = 3
ARCHIVO = Path(__file__).resolve().parent.parent / "storage" / "oficial" / "senado" / "votaciones_senado.json.gz"
VOTOS = {"AFIRMATIVO": "A", "NEGATIVO": "N", "ABSTENCION": "B", "ABSTENCIÓN": "B", "AUSENTE": "U"}


def pedir(n: int):
    """Devuelve el HTML, o None si no se pudo (error de red): en ese caso el número no se da por consultado."""
    req = urllib.request.Request(f"https://www.senado.gob.ar/votaciones/detalleActa/{n}", headers={"User-Agent": "Mozilla/5.0 (DipuData; datos abiertos)"})
    for intento in range(4):
        try:
            with urllib.request.urlopen(req, timeout=45) as r:
                return r.read().decode("utf-8", errors="replace")
        except Exception:
            time.sleep(3 * (intento + 1))
    return None


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
        # Columnas: foto, senador, bloque, provincia, voto. El bloque puede venir vacío: se lee por posición.
        celdas = [limpiar(c) for c in re.findall(r"<td[^>]*>(.*?)</td>", fila, flags=re.S)]
        if len(celdas) != 5 or not celdas[1]:
            continue
        voto = VOTOS.get(celdas[4].upper())
        votos.append([int(sid.group(1)) if sid else 0, celdas[1], celdas[2], celdas[3], voto or "X"])
    # Alguna página repite la tabla de votos: se conserva una sola fila por senador.
    vistos, unicos = set(), []
    for v in votos:
        k = (v[1], v[3])
        if k not in vistos:
            vistos.add(k)
            unicos.append(v)
    votos = unicos
    return {"id": n, "acta": int(m[1]), "titulo": m[2].strip(), "fecha": f"{m[5]}-{m[4]}-{m[3]}", "hora": m[6] or "", "mayoria": mayoria,
            "resultado": resultado, "tipo": tipo, "afirmativos": int(m[8]), "negativos": int(m[9]), "abstenciones": int(m[10]),
            "ausentes": int(m[11]), "votos": votos}


def guardar(est):
    ARCHIVO.parent.mkdir(parents=True, exist_ok=True)
    est["consultados"] = sorted(set(est["consultados"]))
    tmp = ARCHIVO.with_suffix(".tmp")
    with gzip.open(tmp, "wt", encoding="utf-8") as f:
        json.dump(est, f, ensure_ascii=False, separators=(",", ":"))
    tmp.replace(ARCHIVO)


def traer(n: int):
    t = pedir(n)
    time.sleep(PAUSA)
    return n, t


def main() -> int:
    desde = int(sys.argv[1]) if len(sys.argv) > 1 else 1
    hasta = int(sys.argv[2]) if len(sys.argv) > 2 else 3400
    est = {"fuente": "https://www.senado.gob.ar/votaciones/detalleActa/{id}", "desde_fecha": DESDE, "consultados": [], "votaciones": []}
    if ARCHIVO.exists():
        with gzip.open(ARCHIVO, "rt", encoding="utf-8") as f:
            est = json.load(f)
    por_id = {v["id"]: v for v in est["votaciones"]}
    hecho = set(est["consultados"])
    ultimo = max(por_id) if por_id else 0
    sin_datos_seguidos = 0
    pendientes = [n for n in range(desde, hasta + 1) if not (n in hecho and n <= ultimo)]
    # Pocos hilos (cada uno con su pausa): unos 3 pedidos por segundo como máximo.
    with concurrent.futures.ThreadPoolExecutor(HILOS) as pool:
        for i in range(0, len(pendientes), 30):
            fin = False
            for n, t in pool.map(traer, pendientes[i:i + 30]):
                if t is None:
                    print(f"acta {n}: sin respuesta, se reintenta en la próxima corrida", file=sys.stderr)
                    continue
                v = interpretar(n, t)
                hecho.add(n)
                if v is None:
                    sin_datos_seguidos += 1
                    # Pasado el último número con datos, 150 vacíos seguidos marcan el final.
                    if n > ultimo and sin_datos_seguidos >= 150:
                        fin = True
                else:
                    sin_datos_seguidos = 0
                    ultimo = max(ultimo, n)
                    if v["fecha"] >= DESDE:
                        por_id[n] = v
            est["consultados"], est["votaciones"] = list(hecho), sorted(por_id.values(), key=lambda x: x["id"])
            if (i // 30) % 5 == 4:
                guardar(est)
                print(f"  hasta el acta {pendientes[min(i + 29, len(pendientes) - 1)]}: {len(por_id)} votaciones", flush=True)
            if fin:
                break
    est["consultados"], est["votaciones"] = list(hecho), sorted(por_id.values(), key=lambda x: x["id"])
    guardar(est)
    v = est["votaciones"]
    print(f"{len(v)} votaciones del Senado desde {DESDE} (fechas {min(x['fecha'] for x in v)} a {max(x['fecha'] for x in v)})" if v else "sin datos")
    return 0


if __name__ == "__main__":
    sys.exit(main())
