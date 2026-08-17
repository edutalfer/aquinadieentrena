#!/bin/bash
# ============================================================
# PIPELINE COMPLETO — Aquí Nadie Entrena
#   ./pipeline.sh <youtubeId>   → un episodio nuevo
#   ./pipeline.sh --todos       → todos los de episodios.js
# Descarga subtítulos → segmenta → reconstruye el índice.
# ============================================================
set -uo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
RAIZ="$(dirname "$DIR")"
source "$DIR/entorno.sh"

if [ "${1:-}" = "--todos" ]; then
  IDS=$(node -e "const window={};$(cat "$RAIZ/data/episodios.js");console.log(window.ANE_EPISODIOS.map(e=>e.youtubeId).join(' '))")
elif [ -n "${1:-}" ]; then
  IDS="$*"
else
  echo "Uso: ./pipeline.sh <youtubeId> | --todos"; exit 1
fi

for YID in $IDS; do
  "$DIR/01_descargar_subtitulos.sh" "$YID"
  python3 "$DIR/02_segmentar.py" "$YID"
done
python3 "$DIR/03_construir_indice.py"
echo ""
echo "Hecho. Revisa y haz commit de data/"
