#!/bin/bash
# ============================================================
# 01 — Descarga los subtítulos automáticos de YouTube (es)
# Uso: ./01_descargar_subtitulos.sh <youtubeId> [<youtubeId>...]
# Salida: pipeline/tmp/<youtubeId>.es.vtt
# ============================================================
set -uo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
source "$DIR/entorno.sh"
mkdir -p "$DIR/tmp"

for YID in "$@"; do
  if [ -f "$DIR/tmp/$YID.es.vtt" ]; then
    echo "→ $YID ya descargado, salto"
    continue
  fi
  echo "→ Descargando subtítulos de $YID..."
  yt-dlp --skip-download --write-auto-subs --write-subs \
    --sub-langs "es.*" --sub-format vtt \
    -o "$DIR/tmp/$YID" \
    "https://www.youtube.com/watch?v=$YID" 2>&1 | tail -2 || echo "  ⚠ Falló $YID"
  CANDIDATO=$(ls "$DIR/tmp/$YID".es*.vtt 2>/dev/null | head -1)
  if [ -n "$CANDIDATO" ] && [ "$CANDIDATO" != "$DIR/tmp/$YID.es.vtt" ]; then
    mv "$CANDIDATO" "$DIR/tmp/$YID.es.vtt"
  fi
done
echo "✔ Descarga terminada"
