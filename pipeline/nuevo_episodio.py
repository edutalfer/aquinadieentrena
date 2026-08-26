#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ============================================================
# NUEVO EPISODIO — de la URL de YouTube a la web, de una vez
#
#   python3 pipeline/nuevo_episodio.py <url o youtubeId>
#   python3 pipeline/nuevo_episodio.py <url o youtubeId> --solo-ver
#
# Hace esto solo:
#   1. Saca de YouTube el título, la fecha, la duración y la línea de tiempo
#      («Temas del episodio» de la descripción)
#   2. Valida la línea de tiempo y escribe el bloque en data/episodios.js
#   3. Segmenta el VTT y reconstruye el índice de búsqueda
#
# ⚠️ ANTES hay que dejar el subtítulo en su sitio, a mano desde YouTube Studio:
#      data/subtitulos_originales/<youtubeId>.es.vtt
#      pipeline/tmp/<youtubeId>.es.vtt
#    yt-dlp no descarga: veta la IP del hosting y el del Mac ya no extrae.
#
# ⚠️ Solo funciona desde el Mac: YouTube veta las IPs del hosting.
#
# Los metadatos se leen del HTML público con curl, NO con yt-dlp: el yt-dlp
# del Mac responde «The page needs to be reloaded» desde hace meses. Y la
# línea de tiempo sale de la descripción, no de los capítulos nativos, porque
# muchos episodios de ANE no tienen capítulos pero sí «Temas del episodio».
# ============================================================
import json
import re
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
UA = ("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/126.0 Safari/537.36")

TS = r"\d{1,2}:\d{2}(?::\d{2})?"
PRE = re.compile(r"^[\(\[]?\s*(%s)\s*[\)\]]?\s*[\-–—:.·•]*\s*(.+?)$" % TS)   # "03:20 Tema"
POST = re.compile(r"^(.+?)\s*[\(\[]?\s*(%s)\s*[\)\]]?\s*$" % TS)             # "Tema (03:20)"
INICIO = re.compile(r"temas\s+del\s+episodio|gui[óo]n", re.I)
FIN = re.compile(r"lo que no te puedes perder|s[íi]guenos|nuestras redes", re.I)


def id_de(entrada):
    """Acepta una URL de YouTube en cualquiera de sus formas, o el id pelado."""
    m = re.search(r"(?:v=|youtu\.be/|shorts/|embed/)([A-Za-z0-9_-]{11})", entrada)
    if m:
        return m.group(1)
    entrada = entrada.strip()
    if re.fullmatch(r"[A-Za-z0-9_-]{11}", entrada):
        return entrada
    sys.exit("No reconozco «%s» como URL ni como youtubeId" % entrada)


def bajar(yid):
    return subprocess.run(
        ["curl", "-s", "--compressed", "-H", "Accept-Language: es-ES,es;q=0.9",
         "-A", UA, "https://www.youtube.com/watch?v=" + yid],
        capture_output=True, text=True, timeout=60).stdout


def fin_cadena(html, i):
    """Índice del cierre de la cadena JSON que abre en i, respetando escapes."""
    j = i + 1
    while True:
        if html[j] == "\\":
            j += 2
            continue
        if html[j] == '"':
            return j + 1
        j += 1


def campo(html, clave):
    i = html.find('"%s":"' % clave)
    if i < 0:
        return None
    i += len(clave) + 3
    return json.loads(html[i:fin_cadena(html, i)])


def a_segundos(marca):
    p = [int(x) for x in marca.split(":")]
    return p[0] * 3600 + p[1] * 60 + p[2] if len(p) == 3 else p[0] * 60 + p[1]


def timeline(desc):
    lineas = [l.strip() for l in desc.split("\n")]
    ini = next((i for i, l in enumerate(lineas) if INICIO.search(l)), None)
    if ini is not None:
        lineas = lineas[ini + 1:]
    fin = next((i for i, l in enumerate(lineas) if l and FIN.search(l)), None)
    if fin is not None:
        lineas = lineas[:fin]

    temas = []
    for linea in lineas:
        if not linea or "http" in linea:
            continue
        m = PRE.match(linea)
        if m:
            t, titulo = a_segundos(m.group(1)), m.group(2)
        else:
            m = POST.match(linea)
            if not m:
                continue
            t, titulo = a_segundos(m.group(2)), m.group(1)
        titulo = titulo.strip(" -–—:.·•\t")
        if titulo:
            temas.append({"t": t, "titulo": titulo})
    return temas


def metadatos(yid):
    html = bajar(yid)
    if '"lengthSeconds"' not in html:
        sys.exit("No se pudo leer la página de %s. ¿El vídeo es público?" % yid)
    d = {"youtubeId": yid,
         "titulo": campo(html, "title") or "",
         "duracion": int(re.search(r'"lengthSeconds":"(\d+)"', html).group(1))}
    m = re.search(r'"uploadDate":"(\d{4}-\d{2}-\d{2})', html)
    d["fecha"] = m.group(1) if m else None
    d["temas"] = timeline(campo(html, "shortDescription") or "")
    return d


def validar(d):
    """Los tres controles de siempre. Devuelve la lista de avisos."""
    avisos = []
    for a, b in zip(d["temas"], d["temas"][1:]):
        if b["t"] <= a["t"]:
            avisos.append("«%s» (%ds) no va después de «%s» (%ds)"
                          % (b["titulo"], b["t"], a["titulo"], a["t"]))
    for t in d["temas"]:
        if t["t"] > d["duracion"]:
            avisos.append("«%s» está en %ds y el vídeo dura %ds"
                          % (t["titulo"], t["t"], d["duracion"]))
    if not d["temas"]:
        avisos.append("La descripción no trae «Temas del episodio»: el episodio "
                      "entrará sin línea de tiempo (se buscará por transcripción)")
    return avisos


def cad(s):
    return '"' + s.replace("\\", "\\\\").replace('"', '\\"') + '"'


def bloque(d):
    if d["temas"]:
        ancho = max(len(str(t["t"])) for t in d["temas"])
        temas = ",\n".join(
            "      { t: %s titulo: %s }" % ((str(t["t"]) + ",").ljust(ancho + 2),
                                            cad(t["titulo"]))
            for t in d["temas"])
        temas = "    temas: [\n" + temas + "\n    ]"
    else:
        temas = "    temas: []"
    return ("  {\n"
            "    titulo: %s,\n"
            "    fecha: %s,\n"
            "    duracion: %d,\n"
            "    youtubeId: %s,\n"
            "%s\n"
            "  }" % (cad(d["titulo"]), cad(d["fecha"]), d["duracion"],
                     cad(d["youtubeId"]), temas))


def insertar(d):
    """Mete el bloque arriba del array, que es donde va el más reciente."""
    ruta = RAIZ / "data" / "episodios.js"
    js = ruta.read_text(encoding="utf-8")
    if '"%s"' % d["youtubeId"] in js:
        sys.exit("%s ya está en data/episodios.js. No toco nada." % d["youtubeId"])
    ancla = "window.ANE_EPISODIOS = [\n"
    if ancla not in js:
        sys.exit("No encuentro el arranque del array en data/episodios.js")
    js = js.replace(ancla, ancla + bloque(d) + ",\n", 1)
    ruta.write_text(js, encoding="utf-8")
    print("✔ Insertado en data/episodios.js")


def hay_subtitulo(yid):
    """El VTT tiene que estar puesto a mano antes de llamar a esto."""
    return (RAIZ / "pipeline" / "tmp" / (yid + ".es.vtt")).exists() or \
           (RAIZ / "data" / "subtitulos_originales" / (yid + ".es.vtt")).exists()


def main():
    args = sys.argv[1:]
    if not args:
        sys.exit("Uso: python3 pipeline/nuevo_episodio.py <url o youtubeId> [--solo-ver]")
    yid = id_de(args[0])
    solo_ver = "--solo-ver" in args

    print("→ Consultando YouTube...")
    d = metadatos(yid)
    print("  %s · %s" % (d["fecha"], d["titulo"][:66]))
    print("  %d:%02d · %d bloques de línea de tiempo"
          % (d["duracion"] // 60, d["duracion"] % 60, len(d["temas"])))

    avisos = validar(d)
    for a in avisos:
        print("  ⚠ " + a)
    grave = [a for a in avisos if "no va después" in a or "y el vídeo dura" in a]

    if solo_ver:
        print("\n" + bloque(d))
        return
    if grave:
        sys.exit("\n✖ No escribo nada con la línea de tiempo mal. Arréglala en la "
                 "descripción de YouTube y vuelve a lanzarlo.")

    insertar(d)

    if not hay_subtitulo(yid):
        print("\n⚠ Falta el subtítulo. Bájalo de YouTube Studio (VTT, no SRT) y déjalo en:")
        print("    data/subtitulos_originales/%s.es.vtt" % yid)
        print("    pipeline/tmp/%s.es.vtt" % yid)
        print("  Luego: ./pipeline/pipeline.sh %s" % yid)
        return

    print("→ Segmentando y reconstruyendo el índice...")
    subprocess.run([sys.executable, str(RAIZ / "pipeline" / "02_segmentar.py"), yid],
                   check=True)
    subprocess.run([sys.executable, str(RAIZ / "pipeline" / "03_construir_indice.py")],
                   check=True)

    print("\nSi se ve bien:")
    print('  git add data/ && git commit -m "Episodio del %s" && git push' % d["fecha"])
    print("  y en el servidor, git pull")


if __name__ == "__main__":
    main()
