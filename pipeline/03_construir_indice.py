#!/usr/bin/env python3
# ============================================================
# 03 — Compila el índice de búsqueda que consume el frontend
#   Lee   data/episodios.js  +  data/transcripciones/*.json
#   Crea  data/indice_busqueda.json
# Solo entran los episodios dados de alta en episodios.js.
# ============================================================
import json
import re
import subprocess
from datetime import date
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent


def cargar_correcciones():
    """Diccionario de erratas de los subtitulos automaticos.

    Se aplica AQUI, al construir el indice, y no sobre
    data/transcripciones/: el dato en bruto queda intacto y reversible.
    Solo palabras completas, para no romper texto que ya esta bien.
    """
    ruta = RAIZ / "pipeline" / "correcciones.json"
    if not ruta.exists():
        return None, 0
    tabla = json.loads(ruta.read_text(encoding="utf-8"))["correcciones"]
    patron = re.compile(r"\b(" + "|".join(re.escape(k) for k in tabla) + r")\b",
                        re.IGNORECASE)

    def calca_mayusculas(original, corregido):
        # "CANION" -> "CANYON", "Canion" -> "Canyon", "canion" -> "Canyon"
        if original.isupper():
            return corregido.upper()
        if original[0].isupper():
            return corregido[0].upper() + corregido[1:]
        return corregido

    return (patron, tabla, calca_mayusculas), len(tabla)


def corregir(texto, correcciones, contador):
    if not correcciones:
        return texto
    patron, tabla, calca = correcciones

    def sustituye(m):
        original = m.group(0)
        corregido = tabla[original.lower()]
        contador[0] += 1
        return calca(original, corregido)

    return patron.sub(sustituye, texto)


def episodios_dados_de_alta():
    js = ("const window={};" + (RAIZ / "data" / "episodios.js").read_text(encoding="utf-8")
          + ";console.log(JSON.stringify(window.ANE_EPISODIOS.map(e=>e.youtubeId)))")
    salida = subprocess.run(["node", "-e", js], capture_output=True, text=True, check=True)
    return json.loads(salida.stdout)


def main():
    ids = episodios_dados_de_alta()
    correcciones, n_reglas = cargar_correcciones()
    aplicadas = [0]
    indice = {"generado": date.today().isoformat(), "episodios": []}
    faltan = []
    for yid in ids:
        ruta = RAIZ / "data" / "transcripciones" / (yid + ".json")
        if not ruta.exists():
            faltan.append(yid)
            continue
        datos = json.loads(ruta.read_text(encoding="utf-8"))
        segmentos = [[s["inicio"],
                      corregir(re.sub(r"\s+", " ", s["texto"]).strip(), correcciones, aplicadas)]
                     for s in datos["segmentos"] if s.get("texto", "").strip()]
        indice["episodios"].append({"youtubeId": yid, "segmentos": segmentos})
    salida = RAIZ / "data" / "indice_busqueda.json"
    salida.write_text(json.dumps(indice, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
    total = sum(len(e["segmentos"]) for e in indice["episodios"])
    print("✔ Índice: %d episodios, %d segmentos, %.0f KB" % (len(indice["episodios"]), total, salida.stat().st_size / 1024))
    if correcciones:
        print("✔ Correcciones de transcripcion: %d aplicadas (%d reglas)" % (aplicadas[0], n_reglas))
    if faltan:
        print("⚠ Sin transcripción: " + ", ".join(faltan))


if __name__ == "__main__":
    main()
