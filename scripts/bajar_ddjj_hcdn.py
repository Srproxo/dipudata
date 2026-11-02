#!/usr/bin/env python3
"""
Declaraciones juradas patrimoniales de los diputados en ejercicio, tal como las publica la Cámara de Diputados en la ficha
de cada diputado (formulario F. 1245 de ARCA, el mismo que recibe la Oficina Anticorrupción). El usuario sale del listado
oficial https://www.hcdn.gob.ar/diputados/ (/diputados/{usuario}/); la página /diputados/{usuario}/declaracion-jurada.html
enlaza los PDF en www3.hcdn.gob.ar con un rótulo ("DDJJ 2025", "DDJJ 2025 Acuse"). El nombre del archivo cambia de un
diputado a otro (DDJJ2025.pdf, 2.pdf, DECLARACION.pdf): se usa el rótulo, nunca el nombre. Los acuses no traen montos.

Sirve para los años que la Oficina Anticorrupción todavía no subió a datos.jus.gob.ar (al 2/11/2026 el último es el fiscal 2024).

Los PDF traen datos sensibles (CUIT, domicilio, CBU, patentes, tarjetas, grupo familiar): se guardan SOLO en storage/cache/
(ignorado por git) y de cada uno se extrae únicamente la cabecera (período, tipo, secuencia, fecha de presentación, cargo) y
el cuadro "Evolución patrimonial" del titular (bienes y deudas al inicio y al final del año, ingresos netos).

Controles (la declaración que no los pasa no se escribe y se informa):
  - el nombre del PDF tiene que contener el apellido del diputado del listado;
  - el período del PDF tiene que ser el año pedido;
  - los cuatro totales del cuadro tienen que estar.
Se leen dos formatos: el formulario F. 1245 de ARCA y el PDF público de la Oficina Anticorrupción (el de su buscador).
Los PDF escaneados (imágenes) no se pueden leer y quedan afuera.

Reanudable: las páginas y los PDF quedan en storage/cache/ddjj_hcdn/ y no se vuelven a pedir (borrar la carpeta para
actualizar). Pausa de cortesía entre pedidos.

Salida: storage/oficial/ddjj/hcdn_ddjj_AAAA.csv.gz (sin CUIT ni detalle de bienes).
Uso:  python3 -I scripts/bajar_ddjj_hcdn.py [AAAA]   (por defecto 2025)
"""
import csv, gzip, html, io, json, re, sys, time, unicodedata, urllib.error, urllib.request, zlib
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
CACHE = RAIZ / "storage" / "cache" / "ddjj_hcdn"
SAL = RAIZ / "storage" / "oficial" / "ddjj"
LISTADO = "https://www.hcdn.gob.ar/diputados/"
PAGINA = "https://www.hcdn.gob.ar/diputados/{u}/declaracion-jurada.html"
UA = {"User-Agent": "DipuData (dipudata.com; sitio civico, datos oficiales)"}
PAUSA = 1.5
COLUMNAS = ["usuario_hcdn", "listado_apellido_nombre", "listado_distrito", "listado_mandato", "funcionario_apellido_nombre",
            "periodo", "tipo_declaracion_jurada_descripcion", "secuencia", "rectificativa", "fecha_presentacion", "transaccion",
            "cargo", "organismo", "total_bienes_inicio", "deudas_inicio", "total_bienes_final", "total_deudas_final",
            "ingresos_neto_gastos", "url"]


def pedir(url):
    time.sleep(PAUSA)
    for intento in range(4):
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=90) as r:
                return r.read()
        except urllib.error.HTTPError as e:
            if e.code == 404:
                return None
            if intento == 3:
                raise
        except (urllib.error.URLError, TimeoutError):
            if intento == 3:
                raise
        time.sleep(2 ** (intento + 1))


def tokens(s):
    s = unicodedata.normalize("NFD", s or "")
    s = "".join(c for c in s if unicodedata.category(c) != "Mn").lower()
    return [t for t in re.split(r"[^a-z]+", s) if len(t) > 1]


def listado():
    """[(usuario, 'Apellido, Nombre', distrito, mandato)] del listado oficial de diputados en ejercicio."""
    t = pedir(LISTADO).decode("utf-8", "replace")
    out = []
    for fila in re.findall(r"<tr>(.*?)</tr>", t, re.S):
        m = re.search(r'href="/diputados/([a-z0-9]+)/"\s*>\s*([^<]+?)\s*</a>', fila)
        if not m:
            continue
        celdas = [re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", c))).strip() for c in re.findall(r"<td[^>]*>(.*?)</td>", fila, re.S)]
        out.append((m.group(1), html.unescape(m.group(2)).strip(), celdas[2] if len(celdas) > 2 else "", celdas[4] if len(celdas) > 4 else ""))
    return out


def textos_pdf(b):
    """Las cadenas de texto del PDF, en orden (los flujos son Flate y las cadenas Latin-1 con escapes octales)."""
    out = []
    for m in re.finditer(rb"stream\r?\n(.*?)\r?\nendstream", b, re.S):
        try:
            s = zlib.decompress(m.group(1))
        except zlib.error:
            continue
        for linea in re.findall(rb"\[(.*?)\]\s*TJ|\((.*?)(?<!\\)\)\s*Tj", s, re.S):
            crudo = linea[0] or linea[1]
            partes = re.findall(rb"\(((?:\\.|[^\\)])*)\)", crudo) if linea[0] else [crudo]
            txt = b"".join(partes)
            txt = re.sub(rb"\\([0-7]{1,3})", lambda x: bytes([int(x.group(1), 8) & 255]), txt)
            txt = re.sub(rb"\\(.)", rb"\1", txt)
            out.append(txt.decode("latin-1").strip())
    return [x for x in out if x]


def monto(s):
    m = re.fullmatch(r"\$\s*(-?\d+(?:\.\d+)?)", s.strip())
    return m.group(1) if m else None


def numero(s):
    """'12.538.985,92' -> '12538985.92' (formato del PDF de la Oficina Anticorrupción)."""
    s = (s or "").strip()
    return s.replace(".", "").replace(",", ".") if re.fullmatch(r"-?\d{1,3}(\.\d{3})*,\d{2}", s) else None


def leer_oa(t):
    """PDF público que genera la Oficina Anticorrupción ("Declaración jurada patrimonial integral de carácter público"),
    el mismo que entrega su buscador. Algunos diputados suben este en lugar del formulario de ARCA."""
    m = re.fullmatch(r"(ANUAL|INICIAL|BAJA)\s+(\d{4})\s+(.*)", t[0]) if t else None
    if not m:
        return {}
    d = {"periodo": m.group(2), "tipo_declaracion_jurada_descripcion": m.group(1).title(), "secuencia": m.group(3).upper()}
    n = re.search(r"(\d+)", m.group(3))
    d["rectificativa"] = n.group(1) if "RECTIF" in m.group(3).upper() and n else "0"
    i = next((k for k, x in enumerate(t) if x == "Ingreso a la APN"), None)
    if i is not None:
        k = i + 1 + (1 if re.fullmatch(r"\d{2}-\d{8}-\d", t[i + 1]) else 0)
        d["funcionario_apellido_nombre"] = t[k]
    j = next((k for k, x in enumerate(t) if x == "PUBLICO"), None)
    if j is not None:
        org = []
        for x in t[j + 1:j + 6]:
            if re.fullmatch(r"\d{2}-\d{8}-\d", x):
                break
            org.append(x)
        d["organismo"] = " ".join(org)
        f = next((k for k in range(j, min(j + 15, len(t))) if re.fullmatch(r"\d{2}/\d{4}", t[k])), None)
        d["cargo"] = t[f - 1] if f else ""
    e = next((k for k, x in enumerate(t) if x.endswith("Evolucion Patrimonial")), None)
    if e is not None:
        vals = [numero(x) for x in t[e + 1:e + 5]]
        if all(v is not None for v in vals):
            d["total_bienes_inicio"], d["deudas_inicio"], d["total_bienes_final"], d["total_deudas_final"] = vals
    g = next((k for k, x in enumerate(t) if x.lower().startswith("ingresos del trabajo, de alquileres")), None)
    d["ingresos_neto_gastos"] = numero(t[g + 1]) if g is not None and g + 1 < len(t) else None
    return d


def leer(b):
    t = textos_pdf(b)
    oa = leer_oa(t)
    if oa:
        return oa
    d = {}

    def tras(etiqueta, desde=0):
        for i in range(desde, len(t) - 1):
            if t[i].startswith(etiqueta):
                resto = t[i][len(etiqueta):].strip(" :")
                return resto if resto else t[i + 1]
        return None

    d["funcionario_apellido_nombre"] = tras("Apellido y Nombre o Razón Social")
    p = tras("Período")
    d["periodo"] = (re.search(r"\d{4}", p or "") or [None])[0]
    d["tipo_declaracion_jurada_descripcion"] = (tras("Tipo Declaración") or "").title()
    sec = tras("Secuencia") or ""
    d["secuencia"] = sec
    n = re.search(r"(\d+)", sec)
    d["rectificativa"] = n.group(1) if "RECTIF" in sec.upper() and n else ("0" if sec else "")
    d["fecha_presentacion"] = tras("Fecha de Presentación")
    d["transaccion"] = tras("Nro. de Transacción")
    # Cargo y organismo del primer antecedente que motiva la presentación.
    i = next((k for k, x in enumerate(t) if x.startswith("¿Este cargo / candidatura motiva")), 0)
    d["cargo"] = (tras("Cargo o Función en la que se desempeña", i) or "").split(":")[-1].strip()
    d["organismo"] = tras("Organismo - Razón Social", i)
    j = next((k for k, x in enumerate(t) if x == "EVOLUCION PATRIMONIAL"), None)
    if j is not None:
        for campo, etiqueta in (("total_bienes_inicio", "Bienes, depósitos y dinero al inicio del año"),
                                ("deudas_inicio", "Deudas al inicio del año"),
                                ("total_bienes_final", "Bienes, depósitos y dinero al final del año"),
                                ("total_deudas_final", "Deudas al final del año"),
                                ("ingresos_neto_gastos", "Ingresos del trabajo, de alquileres y otras rentas neto de Gastos")):
            v = tras(etiqueta, j)
            d[campo] = monto(v) if v else None
    return d


def enlaces(pagina, anio):
    """URLs de los PDF rotulados como declaración del año (sin los acuses), en el orden de la página."""
    out = []
    for url, rot in re.findall(r"abrirPDF\('(https://www3\.hcdn\.gob\.ar/[^']+\.pdf)'\)\"?[^>]*>\s*([^<]*)</a>", pagina):
        rot = re.sub(r"\s+", " ", html.unescape(rot)).strip()
        if re.search(rf"\b{anio}\b", rot) and not re.search(r"acuse", rot, re.I) and url not in out:
            out.append(url)
    return out


def cache(nombre, url):
    ruta = CACHE / nombre
    if not ruta.exists():
        b = pedir(url)
        ruta.write_bytes(b if b is not None else b"")
    return ruta.read_bytes()


def main() -> int:
    anio = sys.argv[1] if len(sys.argv) > 1 else "2025"
    CACHE.mkdir(parents=True, exist_ok=True)
    dips = listado()
    print(f"Listado oficial: {len(dips)} diputados", flush=True)
    if len(dips) < 250:
        print("El listado vino incompleto: no se sigue.")
        return 1
    filas, problemas, sin_pdf = [], [], []
    for k, (u, nom, distrito, mandato) in enumerate(dips, 1):
        if k % 25 == 0:
            print(f"  {k}/{len(dips)}", flush=True)
        pagina = cache(f"{u}.html", PAGINA.format(u=u)).decode("utf-8", "replace")
        urls = enlaces(pagina, anio)
        if not urls:
            sin_pdf.append(nom)
            continue
        leidas = []
        for n, url in enumerate(urls):
            b = cache(f"{u}_{anio}_{n}.pdf", url)
            if not b.startswith(b"%PDF"):
                problemas.append(f"{nom}: {url} no es un PDF")
                continue
            d = leer(b)
            ap = tokens(nom.split(",")[0])
            if not d.get("funcionario_apellido_nombre"):
                problemas.append(f"{nom}: {url} no es un formulario legible (acuse, otro formulario o imagen escaneada)")
                continue
            if not set(ap) <= set(tokens(d["funcionario_apellido_nombre"])):
                problemas.append(f"{nom}: el PDF es de «{d['funcionario_apellido_nombre']}»")
                continue
            if d.get("periodo") != anio:
                problemas.append(f"{nom}: el PDF rotulado {anio} es del período {d.get('periodo')}")
                continue
            if any(d.get(c) is None for c in ("total_bienes_inicio", "deudas_inicio", "total_bienes_final", "total_deudas_final")):
                problemas.append(f"{nom}: no se pudo leer el cuadro de evolución patrimonial")
                continue
            d.update(usuario_hcdn=u, listado_apellido_nombre=nom, listado_distrito=distrito, listado_mandato=mandato, url=url)
            leidas.append(d)
        # Si la ficha trae más de un formulario legible del año, se queda el más rectificado.
        if leidas:
            filas.append(max(leidas, key=lambda x: int(x.get("rectificativa") or 0)))
    buf = io.StringIO()
    w = csv.DictWriter(buf, fieldnames=COLUMNAS, extrasaction="ignore")
    w.writeheader()
    w.writerows(filas)
    SAL.mkdir(parents=True, exist_ok=True)
    with gzip.open(SAL / f"hcdn_ddjj_{anio}.csv.gz", "wt", encoding="utf-8", newline="") as fh:
        fh.write(buf.getvalue())
    print(f"Declaraciones {anio} leídas: {len(filas)} de {len({f['usuario_hcdn'] for f in filas})} diputados; "
          f"sin declaración {anio} en la ficha: {len(sin_pdf)}; descartadas: {len(problemas)}")
    for p in problemas:
        print("  descartada:", p)
    print("Sin declaración en la ficha:", "; ".join(sin_pdf))
    return 0


if __name__ == "__main__":
    sys.exit(main())
