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


def episodios_dados_de_alta():
    js = ("const window={};" + (RAIZ / "data" / "episodios.js").read_text(encoding="utf-8")
          + ";console.log(JSON.stringify(window.ANE_EPISODIOS.map(e=>e.youtubeId)))")
    salida = subprocess.run(["node", "-e", js], capture_output=True, text=True, check=True)
    return json.loads(salida.stdout)


def main():
    ids = episodios_dados_de_alta()
    indice = {"generado": date.today().isoformat(), "episodios": []}
    faltan = []
    for yid in ids:
        ruta = RAIZ / "data" / "transcripciones" / (yid + ".json")
        if not ruta.exists():
            faltan.append(yid)
            continue
        datos = json.loads(ruta.read_text(encoding="utf-8"))
        segmentos = [[s["inicio"], re.sub(r"\s+", " ", s["texto"]).strip()]
                     for s in datos["segmentos"] if s.get("texto", "").strip()]
        indice["episodios"].append({"youtubeId": yid, "segmentos": segmentos})
    salida = RAIZ / "data" / "indice_busqueda.json"
    salida.write_text(json.dumps(indice, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
    total = sum(len(e["segmentos"]) for e in indice["episodios"])
    print("✔ Índice: %d episodios, %d segmentos, %.0f KB" % (len(indice["episodios"]), total, salida.stat().st_size / 1024))
    if faltan:
        print("⚠ Sin transcripción: " + ", ".join(faltan))


if __name__ == "__main__":
    main()
