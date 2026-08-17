/* ─────────────────────────────────────────────────────────────
   BASE DE DATOS DE EPISODIOS — Aquí Nadie Entrena
   ─────────────────────────────────────────────────────────────
   Cada episodio necesita:
     titulo      → el título del episodio
     fecha       → AAAA-MM-DD (la fecha de publicación en YouTube)
     duracion    → duración total en segundos
     youtubeId   → lo que va después de "v=" en la URL del vídeo
     temas       → lista de { t: segundos, titulo: "..." }

   Para añadir un episodio: copia un bloque, cambia los datos y
   pégalo ARRIBA del todo (el más reciente siempre primero).
   Los temas y los minutos salen tal cual de la descripción de
   YouTube, del bloque "Temas del episodio".
   ───────────────────────────────────────────────────────────── */

window.ANE_EPISODIOS = [
  {
    titulo: "Polémica en QH | Louis Vuitton y el ciclismo | La UCI a por los vatios",
    fecha: "2026-06-28",
    duracion: 4800,
    youtubeId: "uhRAjdhf_rQ",
    temas: [
      { t: 0,    titulo: "Intro" },
      { t: 180,  titulo: "Edu cuenta todo sobre la polémica de QH" },
      { t: 1475, titulo: "¿Por qué Iván Romeo no irá al Tour?" },
      { t: 1950, titulo: "Canyon presenta varios modelos de bici que no sabemos si son reales" },
      { t: 2900, titulo: "Pinarello aparece en una pasarela de París con Louis Vuitton" },
      { t: 3480, titulo: "La cruda confesión de un ciclista pro sobre Pogacar" },
      { t: 3760, titulo: "La UCI propone crear un pasaporte de vatios para cada ciclista" },
      { t: 4140, titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Edu estrena bici | ¿Por qué es la QH la mejor cicloturista? | El Movistar busca nuevo patrocinador",
    fecha: "2026-06-21",
    duracion: 6780,
    youtubeId: "EdlZUQjCDjc",
    temas: [
      { t: 0,    titulo: "Intro" },
      { t: 80,   titulo: "La preparación de Edu para la QH" },
      { t: 2250, titulo: "Edu sale en bici con Espargaró, Verona y Jorge Martín" },
      { t: 2915, titulo: "¿Eclipsará el Mundial de Fútbol al Tour de Francia?" },
      { t: 3660, titulo: "Analizamos las etapas del Tour" },
      { t: 4660, titulo: "¿Nuevo patrocinador al Movistar?" },
      { t: 5400, titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Analizamos la nueva marca de GOBIK | Cristóbal sale en bici con VALVERDE | ¿Orbea presenta CEPO?",
    fecha: "2026-06-14",
    duracion: 6900,
    youtubeId: "8kEa4K59chg",
    temas: [
      { t: 0,    titulo: "Intro" },
      { t: 170,  titulo: "Cristóbal cuenta su experiencia montando en bici con Valverde" },
      { t: 1240, titulo: "A un mes del Tour de Francia, en qué forma llegan los favoritos" },
      { t: 2630, titulo: "Empiezan las filtraciones pre-Tour" },
      { t: 3540, titulo: "La nueva marca de Gobik" },
      { t: 5600, titulo: "La UCI prohíbe los ciclocomputadores" },
      { t: 5970, titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "¿Cuánto gana Cristóbal con Madeinn? | ¿El peor lanzamiento de la historia? | Trucos para el calor",
    fecha: "2026-06-07",
    duracion: 7000,
    youtubeId: "fc-mkO2m9bk",
    temas: [
      { t: 0,    titulo: "Intro" },
      { t: 110,  titulo: "Analizamos la nueva Specialized Crux y su marketing" },
      { t: 530,  titulo: "Vemos los resultados de la Unbound Gravel" },
      { t: 2930, titulo: "Comentamos la expulsión de Lorena Wiebes del Giro de Italia femenino" },
      { t: 3370, titulo: "Contestamos todas vuestras preguntas" },
      { t: 5970, titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Los juegos del dopaje | Presión por sacar las 32\" | Fábricas de carbono en China",
    fecha: "2026-05-31",
    duracion: 6720,
    youtubeId: "j_pzuhI0vZ4",
    temas: [
      { t: 0,    titulo: "Intro" },
      { t: 160,  titulo: "La nueva Orbea de MTB y los vídeos de Zugasti" },
      { t: 1620, titulo: "Update del proyecto Tour de Francia" },
      { t: 1740, titulo: "Análisis del Giro de Italia y predicciones" },
      { t: 3420, titulo: "Las marcas aceleran con las bicis de 32\"" },
      { t: 4180, titulo: "Enhanced Games: los juegos del dopaje" },
      { t: 5420, titulo: "¿Bici o cepo?" }
    ]
  }
];
