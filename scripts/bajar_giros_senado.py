#!/usr/bin/env python3
"""
Descarga de senado.gob.ar (fuente primaria) los giros a comisiones de cada proyecto de los senadores en ejercicio y los
guarda en storage/oficial/senado/giros_proyectos.json.gz.

El Senado no publica los giros como datos abiertos. Cada expediente tiene su página
(parlamentario/comisiones/verExp/{ref}) con la tabla "Giros del Expediente a Comisiones": comisión, orden de giro,
fecha de ingreso y de egreso. La lista de expedientes sale de storage/oficial/senado/proyectos_senadores.json.gz
(bajar_proyectos_senado.py). Las comunicaciones y notas (códigos CO y CC) no son proyectos y no se piden.

Un expediente sin tabla de giros se guarda con la lista vacía (todavía no se giró, o se trató sobre tablas).
Si la página no responde, no se guarda y se vuelve a pedir en la próxima corrida (reanudable).

Uso:  python3 -I scripts/bajar_giros_senado.py
"""
import concurrent.futures, gzip, html, json, re, time, urllib.request
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
ORIGEN = RAIZ / "storage" / "oficial" / "senado" / "proyectos_senadores.json.gz"
ARCHIVO = RAIZ / "storage" / "oficial" / "senado" / "giros_proyectos.json.gz"
PAUSA = 0.5
HILOS = 3
NO_PROYECTO = ("/CO", "/CC")


def pedir(ref: str):
    req = urllib.request.Request(f"https://www.senado.gob.ar/parlamentario/comisiones/verExp/{ref}",
                                 headers={"User-Agent": "Mozilla/5.0 (DipuData; datos abiertos)"})
    for intento in range(4):
        try:
            with urllib.request.urlopen(req, timeout=45) as r:
                return r.read().decode("utf-8", errors="replace")
        except Exception:
            time.sleep(3 * (intento + 1))
    return None


def giros(t: str):
    i = t.find('summary="Giros del Expediente a Comisiones"')
    if i < 0:
        return []
    seg = t[i:t.find("</table>", i)]
    out = []
    for celda, ingreso, egreso in re.findall(r"<tr>\s*<td>(.*?)</td>\s*<td>(.*?)</td>\s*<td>(.*?)</td>", seg, flags=re.S):
        texto = re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", celda))).strip()
        m = re.match(r"(.*?)\s*ORDEN DE GIRO:\s*(\d+)", texto)
        nombre, orden = (m.group(1).strip(), int(m.group(2))) if m else (texto, None)
        fechas = []
        for f in (ingreso, egreso):
            f = re.sub(r"<[^>]+>", "", f).strip()
            d = re.match(r"(\d{2})-(\d{2})-(\d{4})", f)
            fechas.append(f"{d.group(3)}-{d.group(2)}-{d.group(1)}" if d else "")
        out.append([nombre, orden, fechas[0], fechas[1]])
    return out


def uno(ref: str):
    t = pedir(ref)
    time.sleep(PAUSA)
    if t is None or "verExp" not in t and "Expediente" not in t:
        return ref, None
    return ref, giros(t)


def main():
    origen = json.load(gzip.open(ORIGEN))
    refs = sorted({p[0] for lista in origen["senadores"].values() for p in lista if not p[0].endswith(NO_PROYECTO)})
    datos = json.load(gzip.open(ARCHIVO)) if ARCHIVO.exists() else {"fuente": "https://www.senado.gob.ar/parlamentario/comisiones/verExp/{expediente}", "giros": {}}
    faltan = [r for r in refs if r not in datos["giros"]]
    print(f"{len(refs)} expedientes, faltan {len(faltan)}", flush=True)
    hechos = 0
    with concurrent.futures.ThreadPoolExecutor(HILOS) as ex:
        for ref, g in ex.map(uno, faltan):
            if g is not None:
                datos["giros"][ref] = g
            hechos += 1
            if hechos % 200 == 0 or hechos == len(faltan):
                datos["bajado"] = time.strftime("%Y-%m-%d")
                tmp = ARCHIVO.with_suffix(".tmp")
                with gzip.open(tmp, "wt", encoding="utf-8") as f:
                    json.dump(datos, f, ensure_ascii=False, separators=(",", ":"))
                tmp.replace(ARCHIVO)
                print(f"{hechos}/{len(faltan)}", flush=True)


if __name__ == "__main__":
    main()
