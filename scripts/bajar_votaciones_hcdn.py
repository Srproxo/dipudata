#!/usr/bin/env python3
"""
Descarga las votaciones nominales de Diputados directamente de votaciones.hcdn.gob.ar (fuente primaria) para los
períodos que datos.hcdn.gob.ar todavía no publica (138 en adelante) y las guarda, ya interpretadas, en
storage/oficial/hcdn/votaciones_recinto/votaciones_hcdn.json.gz.

El índice (la portada del sitio) lista todas las votaciones con su número, fecha y título; cada una tiene su página
votacion/{id}.html con período, reunión, acta, presidente, mayoría, resultado, totales y el voto de cada diputado con su
código oficial (el de la foto, p. ej. A51126).
Códigos de voto: A afirmativo, N negativo, B abstención, U ausente, P preside, S sin votar (presente pero no votó),
V banca pendiente de incorporación (no es un legislador; la fila viene sin nombre o como "DIP, PENDIENTE DE INCORPORACIÓN"). Los números son los mismos acta_id de datos.hcdn.gob.ar.

Se bajan las votaciones fechadas desde DESDE y, además, una muestra del período 137 (MUESTRA_137) que sirve para
comprobar el intérprete contra el archivo oficial de datos.hcdn.gob.ar (ver validar_votaciones_hcdn.py).
Se conservan solo las del período 137 en adelante.

Es reanudable: cada página se guarda comprimida en storage/cache/hcdn_votaciones/ (ignorado por git) y no se vuelve a
pedir; el resultado interpretado se rehace siempre desde ese caché. Hay una pausa de cortesía entre pedidos y un solo hilo.

Uso:  python3 -I scripts/bajar_votaciones_hcdn.py            (descarga lo que falta e interpreta)
      python3 -I scripts/bajar_votaciones_hcdn.py --solo-interpretar
"""
import gzip, html, json, re, sys, time, urllib.error, urllib.request
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
BASE = "https://votaciones.hcdn.gob.ar"
DESDE = "2019-12-10"
MUESTRA_137 = range(3864, 3884)
PERIODO_MIN = 137
PAUSA = 1.5
CACHE = RAIZ / "storage" / "cache" / "hcdn_votaciones"
SALIDA = RAIZ / "storage" / "oficial" / "hcdn" / "votaciones_recinto" / "votaciones_hcdn.json.gz"
VOTOS = {"AFIRMATIVO": "A", "NEGATIVO": "N", "ABSTENCION": "B", "ABSTENCIÓN": "B", "AUSENTE": "U", "PRESIDENTE": "P",
         "SIN VOTAR": "S", "PENDIENTE DE INCORPORACIÓN": "V"}
NO_EXISTE = "404"
UA = "Mozilla/5.0 (DipuData; datos abiertos; pausa de cortesía)"


def pedir(ruta: str):
    """Devuelve el HTML, o None si no se pudo (error de red): la página queda pendiente para la próxima corrida."""
    req = urllib.request.Request(f"{BASE}/{ruta}", headers={"User-Agent": UA})
    for intento in range(4):
        try:
            with urllib.request.urlopen(req, timeout=90) as r:
                return r.read().decode("utf-8", errors="replace")
        except urllib.error.HTTPError as e:
            if e.code == 404:
                print(f"  {ruta}: no existe (404)", file=sys.stderr)
                return NO_EXISTE
            print(f"  {ruta}: {e} (reintento {intento + 1})", file=sys.stderr)
            time.sleep(5 * (intento + 1))
        except Exception as e:
            print(f"  {ruta}: {e} (reintento {intento + 1})", file=sys.stderr)
            time.sleep(5 * (intento + 1))
    return None


def limpiar(t: str) -> str:
    t = re.sub(r"<script.*?</script>|<style.*?</style>|<!--.*?-->", " ", t, flags=re.S)
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", t))).strip()


def leer_indice(t: str):
    """[(id, 'AAAA-MM-DD', título, tipo, resultado)] de la portada."""
    out = []
    for m in re.finditer(r'<tr class="row-acta"[^>]*\bid="(\d+)"[^>]*>(.*?)</tr>', t, flags=re.S):
        fila = m.group(2)
        f = re.search(r"<span[^>]*>\s*(\d{4})(\d{2})(\d{2})\s*</span>", fila)
        celdas = [limpiar(c) for c in re.findall(r"<td[^>]*>(.*?)</td>", fila, flags=re.S)]
        res = re.search(r'force-square">\s*([^<]+?)\s*<', fila)
        if f and len(celdas) >= 3:
            out.append((int(m.group(1)), f"{f[1]}-{f[2]}-{f[3]}", celdas[1], celdas[2], res.group(1) if res else ""))
    return out


def interpretar(n: int, t: str):
    m = re.search(r"Per[ií]odo\s*(\d+)\s*-\s*Reuni[oó]n\s*(\d+)\s*-\s*Acta\s*(\d+)", t)
    if not m:
        return None
    cab = t[m.end():t.find('id="myTable"')]
    tit = re.search(r'<h4 class="black-opacity"\s*>(.*?)<h5[^>]*>\s*(\d{2})/(\d{2})/(\d{4})\s*-\s*(\d{2}:\d{2})?', cab, flags=re.S)
    totales = {}
    for num, etiqueta in re.findall(r'<h3 class="text-center"[^>]*>\s*(\d+)\s*</h3>.*?<h4[^>]*>\s*([A-ZÁÉÍÓÚ ]+?)\s*</h4>', cab, flags=re.S):
        totales[etiqueta.strip()] = int(num)
    # Tras el bloque de compartir viene "Presidida por | NOMBRE | RESULTADO | mayoría".
    txt = limpiar(cab[cab.find('id="custom-share"'):])
    pres = re.search(r"Presidida por\s+(.*?)\s+(AFIRMATIVO|NEGATIVO|EMPATE|ANULADA|SIN RESULTADO)\s+(.*?)\s+\d+\s+AFIRMATIVOS", txt)
    votos = []
    cuerpo = t[t.find('id="myTable"'):]
    cuerpo = cuerpo[cuerpo.find("<tbody"):cuerpo.find("</tbody>")]
    for fila in re.findall(r"<tr[^>]*>(.*?)</tr>", cuerpo, flags=re.S):
        celdas = [limpiar(c) for c in re.findall(r"<td[^>]*>(.*?)</td>", fila, flags=re.S)]
        if len(celdas) < 5:
            continue
        cod = re.search(r'id="container-([^"]+)"', fila)
        etiqueta = celdas[4].upper()
        votos.append([cod.group(1) if cod else "", celdas[1], celdas[2], celdas[3], VOTOS.get(etiqueta, "X" if etiqueta else "")])
    return {"id": n, "periodo": int(m[1]), "reunion": int(m[2]), "acta": int(m[3]),
            "titulo": limpiar(tit.group(1)) if tit else "",
            "fecha": f"{tit[4]}-{tit[3]}-{tit[2]}" if tit else "", "hora": (tit[5] or "") if tit else "",
            "presidente": pres.group(1) if pres else "", "resultado": pres.group(2) if pres else "",
            "mayoria": pres.group(3) if pres else "",
            "afirmativos": totales.get("AFIRMATIVOS"), "negativos": totales.get("NEGATIVOS"),
            "abstenciones": totales.get("ABSTENCIONES"), "sin_votar": totales.get("SIN VOTAR"),
            "ausentes": totales.get("AUSENTES"), "votos": votos}


def cache_de(n: int) -> Path:
    return CACHE / f"{n}.html.gz"


def sin_pagina(n: int) -> Path:
    """Marca de las votaciones que figuran en el índice pero cuya página de detalle devuelve 404."""
    return CACHE / f"{n}.404"


def main() -> int:
    solo = "--solo-interpretar" in sys.argv
    CACHE.mkdir(parents=True, exist_ok=True)
    ind_ruta = CACHE / "indice.html.gz"
    if not solo or not ind_ruta.exists():
        t = pedir("")
        if t is None:
            print("No se pudo bajar el índice.", file=sys.stderr)
            return 1
        with gzip.open(ind_ruta, "wt", encoding="utf-8") as f:
            f.write(t)
    with gzip.open(ind_ruta, "rt", encoding="utf-8") as f:
        indice = leer_indice(f.read())
    elegidos = sorted({i for i, fecha, *_ in indice if fecha >= DESDE} | {i for i, *_ in indice if i in MUESTRA_137})
    faltan = [n for n in elegidos if not cache_de(n).exists() and not sin_pagina(n).exists()]
    print(f"Índice: {len(indice)} votaciones; elegidas {len(elegidos)}; en caché {len(elegidos) - len(faltan)}; faltan {len(faltan)}")
    errores = []
    if not solo:
        for k, n in enumerate(faltan, 1):
            t = pedir(f"votacion/{n}.html")
            if t == NO_EXISTE:
                sin_pagina(n).touch()
            elif t is None or "Acta" not in t:
                errores.append(n)
            else:
                tmp = cache_de(n).with_suffix(".tmp")
                with gzip.open(tmp, "wt", encoding="utf-8") as f:
                    f.write(t)
                tmp.rename(cache_de(n))
            if k % 25 == 0:
                print(f"  {k}/{len(faltan)}", flush=True)
            time.sleep(PAUSA)
    votaciones, sin_interpretar, pendientes, sin_detalle = [], [], [], []
    por_id = {i: (fecha, tit, tipo, res) for i, fecha, tit, tipo, res in indice}
    for n in elegidos:
        if sin_pagina(n).exists():
            sin_detalle.append(dict(zip(("id", "fecha", "titulo", "tipo", "resultado"), (n, *por_id[n]))))
            continue
        if not cache_de(n).exists():
            pendientes.append(n)
            continue
        with gzip.open(cache_de(n), "rt", encoding="utf-8") as f:
            v = interpretar(n, f.read())
        if v is None:
            sin_interpretar.append(n)
            continue
        if v["periodo"] < PERIODO_MIN:
            continue
        v["indice"] = dict(zip(("fecha", "titulo", "tipo", "resultado"), por_id[n]))
        votaciones.append(v)
    SALIDA.parent.mkdir(parents=True, exist_ok=True)
    with gzip.open(SALIDA, "wt", encoding="utf-8") as f:
        json.dump({"fuente": f"{BASE}/votacion/{{id}}.html", "bajado": time.strftime("%Y-%m-%d"), "desde": DESDE,
                   "pendientes": pendientes, "sin_pagina_de_detalle": sin_detalle, "sin_interpretar": sin_interpretar, "votaciones": votaciones},
                  f, ensure_ascii=False, separators=(",", ":"))
    print(f"Interpretadas {len(votaciones)}; sin página de detalle (404) {len(sin_detalle)}; sin interpretar {len(sin_interpretar)}; pendientes {len(pendientes)}")
    return 0 if not pendientes and not errores else 2


if __name__ == "__main__":
    sys.exit(main())
