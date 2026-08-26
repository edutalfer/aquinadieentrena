#!/usr/bin/env python3
# ============================================================
# NUEVO EPISODIO — de la URL de YouTube a la web, de una vez
#
#   python3 pipeline/nuevo_episodio.py <url o youtubeId>
#
# Hace todo esto solo:
#   1. Saca de YouTube el título, la fecha, la duración y los capítulos
#   2. Escribe el bloque en data/episodios.js (arriba del todo)
#   3. Descarga los subtítulos y los segmenta
#   4. Reconstruye el índice de búsqueda
#
# ⚠️ Solo funciona desde el Mac: YouTube veta las IPs del hosting.
# ============================================================
import json
import re
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
PIPE = RAIZ / "pipeline"


def id_de(entrada):
    m = re.search(r"(?:v=|youtu\.be/|shorts/)([A-Za-z0-9_-]{11})", entrada)
    return m.group(1) if m else entrada.strip()


def metadatos(yid):
    print("→ Consultando YouTube...")
    r = subprocess.run(
        ["yt-dlp", "--skip-download", "--dump-json",
         "https://www.youtube.com/watch?v=" + yid],
        capture_output=True, text=True)
    if r.returncode != 0:
        sys.exit("✖ yt-dlp no pudo leer el vídeo:\n" + r.stderr.strip()[-400:])
    return json.loads(r.stdout)


def escapa(s):
    return s.replace("\\", "\\\\").replace('"', '\\"')


def bloque_js(d, yid):
    fecha = d.get("upload_date", "")
    fecha = "%s-%s-%s" % (fecha[:4], fecha[4:6], fecha[6:]) if len(fecha) == 8 else ""
    caps = d.get("chapters") or []
    temas = [{"t": int(c["start_time"]), "titulo": c["title"].strip()} for c in caps]

    if temas:
        ancho = max(len(str(t["t"])) for t in temas) + 1
        filas = ",\n".join(
            '      { t: %-*s titulo: "%s" }' % (ancho, str(t["t"]) + ",", escapa(t["titulo"]))
            for t in temas)
    else:
        filas = ""

    return ('  {\n'
            '    titulo: "%s",\n'
            '    fecha: "%s",\n'
            '    duracion: %d,\n'
            '    youtubeId: "%s",\n'
            '    temas: [\n%s\n    ]\n'
            '  },\n' % (escapa(d.get("title", "").strip()), fecha,
                        int(d.get("duration") or 0), yid, filas)), len(temas)


def main():
    if len(sys.argv) != 2:
        sys.exit("Uso: python3 pipeline/nuevo_episodio.py <url o youtubeId>")
    yid = id_de(sys.argv[1])

    fichero = RAIZ / "data" / "episodios.js"
    contenido = fichero.read_text(encoding="utf-8")
    if yid in contenido:
        sys.exit("✖ El episodio %s ya está en data/episodios.js" % yid)

    d = metadatos(yid)
    bloque, n_temas = bloque_js(d, yid)

    ancla = "window.ANE_EPISODIOS = [\n"
    if ancla not in contenido:
        sys.exit("✖ No encuentro 'window.ANE_EPISODIOS = [' en episodios.js")
    fichero.write_text(contenido.replace(ancla, ancla + bloque, 1), encoding="utf-8")

    print("✔ Añadido a episodios.js: %s" % d.get("title", "")[:60])
    print("  fecha %s · %d min · %d capítulos"
          % (d.get("upload_date", "?"), (d.get("duration") or 0) // 60, n_temas))
    if not n_temas:
        print("  ⚠ El vídeo no tiene capítulos en YouTube: el episodio se")
        print("    encontrará solo por transcripción. Si añades los capítulos")
        print("    en YouTube, vuelve a lanzar esto tras borrar el bloque.")

    print("→ Subtítulos...")
    subprocess.run([str(PIPE / "01_descargar_subtitulos.sh"), yid], check=False)
    subprocess.run(["python3", str(PIPE / "02_segmentar.py"), yid], check=False)
    subprocess.run(["python3", str(PIPE / "03_construir_indice.py")], check=False)

    print("\nSi todo se ve bien:")
    print('  git add data/ && git commit -m "Episodio: %s" && git push'
          % d.get("title", "")[:40])


if __name__ == "__main__":
    main()
