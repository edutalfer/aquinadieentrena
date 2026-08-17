#!/usr/bin/env python3
# ============================================================
# 02 — Convierte un VTT de YouTube en segmentos con timestamps
# Uso: python3 02_segmentar.py <youtubeId>
#   Lee   pipeline/tmp/<youtubeId>.es.vtt
#   Crea  data/transcripciones/<youtubeId>.json
# Los subtítulos automáticos repiten líneas (efecto "rodillo")
# y llevan etiquetas <c>/<00:00:00.000>: aquí se limpian,
# se deduplican y se agrupan en segmentos de ~35 s.
# ============================================================
import json
import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
DUR_OBJETIVO = 35
CHARS_MAX = 500


def a_segundos(ts):
    h, m, s = ts.split(":")
    return int(h) * 3600 + int(m) * 60 + float(s)


def limpiar(linea):
    linea = re.sub(r"<[^>]+>", "", linea)
    linea = linea.replace("&nbsp;", " ").replace("&amp;", "&")
    linea = linea.replace("&gt;", ">").replace("&lt;", "<")
    return re.sub(r"\s+", " ", linea).strip()


def parsear_vtt(ruta):
    cues = []
    inicio = None
    ultimo_texto = ""
    for linea in ruta.read_text(encoding="utf-8", errors="replace").splitlines():
        m = re.match(r"(\d{2}:\d{2}:\d{2}\.\d{3}) --> (\d{2}:\d{2}:\d{2}\.\d{3})", linea)
        if m:
            inicio = a_segundos(m.group(1))
            continue
        if inicio is None or not linea.strip() or linea.startswith(("WEBVTT", "Kind:", "Language:", "NOTE")):
            continue
        texto = limpiar(linea)
        if not texto or texto == ultimo_texto:
            continue
        if ultimo_texto and texto.startswith(ultimo_texto):
            texto = texto[len(ultimo_texto):].strip()
            if not texto:
                continue
        cues.append((inicio, texto))
        ultimo_texto = limpiar(linea)
    return cues


def agrupar(cues):
    segmentos = []
    actual = {"inicio": None, "fin": None, "textos": []}
    for t, texto in cues:
        if actual["inicio"] is None:
            actual["inicio"] = t
        actual["fin"] = t
        actual["textos"].append(texto)
        junto = " ".join(actual["textos"])
        if (t - actual["inicio"] >= DUR_OBJETIVO) or (len(junto) >= CHARS_MAX):
            segmentos.append({"inicio": int(actual["inicio"]), "fin": int(actual["fin"]), "texto": junto})
            actual = {"inicio": None, "fin": None, "textos": []}
    if actual["textos"]:
        segmentos.append({"inicio": int(actual["inicio"]), "fin": int(actual["fin"]), "texto": " ".join(actual["textos"])})
    return segmentos


def main():
    if len(sys.argv) != 2:
        sys.exit("Uso: python3 02_segmentar.py <youtubeId>")
    yid = sys.argv[1]
    vtt = RAIZ / "pipeline" / "tmp" / (yid + ".es.vtt")
    if not vtt.exists():
        sys.exit("No existe " + str(vtt) + " — ejecuta antes 01_descargar_subtitulos.sh " + yid)
    segmentos = agrupar(parsear_vtt(vtt))
    salida = RAIZ / "data" / "transcripciones" / (yid + ".json")
    salida.parent.mkdir(parents=True, exist_ok=True)
    salida.write_text(json.dumps({"youtubeId": yid, "segmentos": segmentos}, ensure_ascii=False, indent=1), encoding="utf-8")
    print("✔ " + yid + ": " + str(len(segmentos)) + " segmentos")


if __name__ == "__main__":
    main()
