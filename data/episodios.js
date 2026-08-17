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
    titulo: "¿El ciclismo en su peor momento? | Lapierre quiebra | Decathlon bate récords",
    fecha: "2026-08-16",
    duracion: 5030,
    youtubeId: "jf9efUtkvoo",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 870,   titulo: "The Grefg hace un test de 20 min ¿es positivo para el ciclismo?" },
      { t: 2260,  titulo: "¿Cuánta gente ha visto el Tour de Francia femenino?" },
      { t: 3270,  titulo: "¡Quiebra la marca histórica Lapierre!" },
      { t: 3590,  titulo: "Decathlon tiene récords de beneficios, ¿qué significa esto para el ciclismo?" },
      { t: 4090,  titulo: "Bici o cepo" }
    ]
  },
  {
    titulo: "Damos respuesta a vuestro HATE y Pogacar viene a la Vuelta, que significa esto?",
    fecha: "2026-08-09",
    duracion: 4915,
    youtubeId: "gy5RLwGDFs8",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 360,   titulo: "Contestamos vuestros comentarios haters" },
      { t: 620,   titulo: "Analizamos la nueva Orbea Orca" },
      { t: 1830,  titulo: "Charlamos sobre la venta de Klassmark a una multinacional" },
      { t: 2320,  titulo: "¡Pogacar irá a La Vuelta!" },
      { t: 3295,  titulo: "Los comisarios de UCI revisan los sujetadores de las corredoras" },
      { t: 6000,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Analizamos la nueva Megamo! | ¿Cuento hemos ganado con el tour? | Garmin compra Training Peaks!",
    fecha: "2026-08-02",
    duracion: 7227,
    youtubeId: "oo5mXrkefns",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 432,   titulo: "Analizamos la nueva Megamo" },
      { t: 2735,  titulo: "¿Qué puede suponer la compra de Training Peaks por Garmin?" },
      { t: 3910,  titulo: "Los datos de Pogacar en el Tour analizados por A Umbral" },
      { t: 4235,  titulo: "Comparamos nuestros datos de visualización con los de HBO" },
      { t: 5715,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Polémica en QH | Louis Vuitton y el ciclismo | La UCI a por los vatios",
    fecha: "2026-06-28",
    duracion: 4831,
    youtubeId: "uhRAjdhf_rQ",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 180,   titulo: "Edu cuenta todo sobre la polémica de QH" },
      { t: 1475,  titulo: "¿Por qué Iván Romeo no irá al Tour?" },
      { t: 1950,  titulo: "Canyon presenta varios modelos de bici que no sabemos si son reales" },
      { t: 2900,  titulo: "Pinarello aparece en una pasarela de París con Louis Vuitton" },
      { t: 3480,  titulo: "La cruda confesión de un ciclista pro sobre Pogacar" },
      { t: 3760,  titulo: "La UCI propone crear un pasaporte de vatios para cada ciclista" },
      { t: 4140,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Edu estrena bici! | ¿Porque es la QH la mejor cicloturista? | El Movistar busca nuevo patrocinador!",
    fecha: "2026-06-21",
    duracion: 6783,
    youtubeId: "EdlZUQjCDjc",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 80,    titulo: "La preparación de Edu para la QH" },
      { t: 2250,  titulo: "Edu sale en bici con Espargaró, Verona y Jorge Martín" },
      { t: 2915,  titulo: "¿Eclipsará el Mundial de Fútbol al Tour de Francia?" },
      { t: 3660,  titulo: "Analizamos las etapas del Tour" },
      { t: 4660,  titulo: "¿Nuevo patrocinador al Movistar?" },
      { t: 5400,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Analizamos la nueva marca de GOBIK... | Cristobal sale en bici con VALVERDE! | Orbea presenta CEPO?",
    fecha: "2026-06-14",
    duracion: 6803,
    youtubeId: "8kEa4K59chg",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 170,   titulo: "Cristóbal cuenta su experiencia montando en bici con Valverde" },
      { t: 1240,  titulo: "A un mes del Tour de Francia os contamos en qué forma llegan los favoritos" },
      { t: 2630,  titulo: "Empiezan las filtraciones pre-Tour" },
      { t: 3540,  titulo: "La nueva marca de Gobik" },
      { t: 5600,  titulo: "La UCI prohíbe los ciclocomputadores" },
      { t: 5970,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "¿Cuanto gana Cristóbal con Madeinn? | ¿El peor lanzamiento de la historia? | Trucos para el calor.",
    fecha: "2026-06-07",
    duracion: 7010,
    youtubeId: "fc-mkO2m9bk",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 110,   titulo: "Analizamos la nueva Specialized Crux y su marketing" },
      { t: 530,   titulo: "Vemos los resultados de la Unbound Gravel" },
      { t: 2930,  titulo: "Comentamos la expulsión de Lorena Wiebes del Giro de Italia femenino" },
      { t: 3370,  titulo: "Contestamos todas vuestras preguntas" },
      { t: 5970,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Los juegos del dopaje | Presión por sacar las 32” | Fábricas de carbono en China",
    fecha: "2026-05-31",
    duracion: 6747,
    youtubeId: "j_pzuhI0vZ4",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 160,   titulo: "La nueva Orbea de MTB y los vídeos de Zugasti" },
      { t: 1620,  titulo: "Update del proyecto Tour de Francia" },
      { t: 1740,  titulo: "Análisis del Giro de Italia y predicciones" },
      { t: 3420,  titulo: "Las marcas aceleran con las bicis de 32\"" },
      { t: 4180,  titulo: "Enhanced Games: los juegos del dopaje" },
      { t: 5420,  titulo: "Bici o cepo!" }
    ]
  },
  {
    titulo: "Adiós definitivo a ROTOR | Canyon no se aclara | SRAM gana a la UCI",
    fecha: "2026-05-24",
    duracion: 6611,
    youtubeId: "9jvbWZQ6E0I",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 455,   titulo: "Rotor se desmorona por completo" },
      { t: 2600,  titulo: "No tiene ningún sentido lo que está haciendo Canyon" },
      { t: 4320,  titulo: "Sale el resultado de UCI vs SRAM" },
      { t: 4560,  titulo: "Giro de Italia y cómo la prensa española no valora a Enric Mas" },
      { t: 5575,  titulo: "Bici o cepo" }
    ]
  },
  {
    titulo: "Empleado de Specialized filtra las nuevas SL9 y CRUX! | Drama cruzando Taiwan en 4 días | Giro Itali",
    fecha: "2026-05-17",
    duracion: 5473,
    youtubeId: "UNRxbg3sJcI",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 120,   titulo: "Analizamos la nueva SL9 y la Crux de Specialized" },
      { t: 2000,  titulo: "¿Van Rysel compra ropa de Castelli y le pone su logo?" },
      { t: 2400,  titulo: "Os contamos todo sobre nuestro viaje" },
      { t: 3315,  titulo: "Analizamos el Giro de Italia" },
      { t: 4260,  titulo: "Bici o cepo" }
    ]
  },
  {
    titulo: "¿Quién ganará en el mercado? \"Nuestra opinión\" | Polémica en Traka",
    fecha: "2026-05-10",
    duracion: 4295,
    youtubeId: "gFu-15TP8uw",
    temas: []
  },
  {
    titulo: "THE TRAKA desde dentro! | Nuevos Lanzamientos de Gravel | Maratón de Londres",
    fecha: "2026-05-03",
    duracion: 4534,
    youtubeId: "5enRFyHj0qU",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 60,    titulo: "Analizamos a fondo The Traka" },
      { t: 690,   titulo: "Vemos en persona la primera Gravel de 32\"" },
      { t: 840,   titulo: "¿Qué bicis se están utilizando?" },
      { t: 1150,  titulo: "Edu prueba un nuevo Set Up y le sale mal" },
      { t: 2690,  titulo: "Se acerca el final del sorteo de Toteemi" },
      { t: 2970,  titulo: "Comentamos la Maratón de Londres" },
      { t: 3900,  titulo: "¡Bici o cepo!" }
    ]
  },
  {
    titulo: "La UCI amenaza a un YOUTUBER! | Drama en el campeonato Nacional de XCM | Nos vamos a CHINA!",
    fecha: "2026-04-26",
    duracion: 6449,
    youtubeId: "G6KO82NeeX0",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 95,    titulo: "Edu nos presenta su próximo reto" },
      { t: 265,   titulo: "La logística de nuestro viaje a China y Taiwan" },
      { t: 2860,  titulo: "Test de rigidez: El posible nuevo estándar de la industria" },
      { t: 3610,  titulo: "Drama en el Cto nacional de XC maratón" },
      { t: 4260,  titulo: "La UCI amenaza a un youtuber" },
      { t: 4900,  titulo: "¡Bici o cepo!" }
    ]
  },
  {
    titulo: "¿Volvemos atrás? | Assos vs Maap | La experiencia de Edu con 2.2 en Gravel!",
    fecha: "2026-04-19",
    duracion: 5911,
    youtubeId: "u1sojhfqFfA",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 220,   titulo: "La tecnología en Paris Roubaix" },
      { t: 595,   titulo: "¿El ciclismo profesional tiene un problema con las motos?" },
      { t: 2705,  titulo: "Toteemi nos plantea un reto" },
      { t: 3060,  titulo: "Qué se tomó Van Aert a 4km de meta en Paris Roubaix" },
      { t: 3600,  titulo: "¿Se copian Maap y Assos entre sí?" },
      { t: 3900,  titulo: "Edu cuenta su experiencia con las cubiertas de 2.1 gravel" },
      { t: 4420,  titulo: "¡Bici o cepo!" }
    ]
  },
  {
    titulo: "¿Qué pasa con estas ruedas? | LANZAMIENTO: Merida vs Giant! | Nos vamos a Sudáfrica!",
    fecha: "2026-03-08",
    duracion: 6200,
    youtubeId: "1Pbt5q_L75c",
    temas: [
      { t: 0,     titulo: "Intro + Igraal" },
      { t: 345,   titulo: "Maillots" },
      { t: 465,   titulo: "Julbo" },
      { t: 600,   titulo: "Cubiertas más anchas testeadas" },
      { t: 1500,  titulo: "Novedades en Cape" },
      { t: 1720,  titulo: "Nuevos lanzamientos: Merida vs Giant" },
      { t: 3060,  titulo: "Drama en Cadex" },
      { t: 3445,  titulo: "Ciclismo Pro" },
      { t: 4140,  titulo: "Bici o Cepo" },
      { t: 5940,  titulo: "Cepo" }
    ]
  },
  {
    titulo: "Episodio Especial: Sobremesa con los pros | David Valero, Enrique Morcillo, Marti Aran, Paco Camacho",
    fecha: "2026-03-02",
    duracion: 6247,
    youtubeId: "tcVNXIfRcMw",
    temas: []
  },
  {
    titulo: "Nueva Cannondale SUPERSIX! ¿Bici o Cepo? | Edu vuelve asustado de Catalunya...|¿Nuevas ROVAL Gravel?",
    fecha: "2026-02-22",
    duracion: 6789,
    youtubeId: "gWswoVKJ2Lc",
    temas: []
  },
  {
    titulo: "¿Redbull te da alas? | NOS PAGAN LA CAPE! | Lucha entre equipos “off-road”",
    fecha: "2026-02-15",
    duracion: 6991,
    youtubeId: "B9y3ELUXPJk",
    temas: []
  },
  {
    titulo: "¿Qué esta pasando en CANYON? | Edu presenta bici | Cuanto tiene que pesar una gravel para ser CEPO",
    fecha: "2026-02-08",
    duracion: 6436,
    youtubeId: "ev0tzxeAOLY",
    temas: []
  },
  {
    titulo: "Flecha nos acusa de \"CHARLATANES\" | los tests aerodinámicos de una Bici CHINA nos sorprenden!",
    fecha: "2026-02-01",
    duracion: 7650,
    youtubeId: "Z0HybwI-yxk",
    temas: []
  },
  {
    titulo: "TODAS las bicis del world tour 2026 analizadas! | Van Rysel hace MUCHO ruido y anunciamos carrera!",
    fecha: "2026-01-25",
    duracion: 4860,
    youtubeId: "VEvA7w4EVOc",
    temas: []
  },
  {
    titulo: "¿Por qué se retira un campeón tan pronto? | Rapha cierra tiendas! | ¿El Jayco regala el titulo?",
    fecha: "2026-01-18",
    duracion: 6831,
    youtubeId: "5yoINT_hUiw",
    temas: []
  },
  {
    titulo: "Los beneficios de la creatina para ciclistas y un nuevo maillot de specialized que ahorra 20 vatios?",
    fecha: "2026-01-11",
    duracion: 5502,
    youtubeId: "-TUEBDC4eqs",
    temas: []
  },
  {
    titulo: "Specialized lo vuelve a hacer!| Edu entrena con los pros en Andorra | Objetivos 2026!!",
    fecha: "2026-01-04",
    duracion: 5641,
    youtubeId: "IXwk61V6gWs",
    temas: []
  },
  {
    titulo: "Nuevo Recorrido Vuelta 2026! | Top tendencias 2025 | El problema de los \"rankings\" de las revistas.",
    fecha: "2025-12-28",
    duracion: 5766,
    youtubeId: "l-OwmhoBQuY",
    temas: []
  },
  {
    titulo: "Nuevas Equipaciones 2026!! ¿cuál es la mejor? | Motivación en invierno... | S5 al túnel del viento!",
    fecha: "2025-12-21",
    duracion: 7090,
    youtubeId: "DyquvTvjJYE",
    temas: []
  },
  {
    titulo: "Incautan 1,600,000.00€ en S-WORKS falsas! | Nueva \"bici mas rapida\" FACTOR ONE! | Orbea vs Quickpro",
    fecha: "2025-12-14",
    duracion: 6406,
    youtubeId: "fR4yu8iMPTE",
    temas: [
      { t: 0,     titulo: "Introducción" },
      { t: 98,    titulo: "¿Hay que llevar casco en la ciudad? Polémica" },
      { t: 690,   titulo: "Presentación de la Factor One" },
      { t: 2184,  titulo: "Ineos se pasa a las ruedas Scope" },
      { t: 2555,  titulo: "Specialized y AliExpress juntos contra las falsificaciones" },
      { t: 2962,  titulo: "Euskaltel Euskadi va a ir en bicicletas chinas" },
      { t: 4325,  titulo: "¿Quién ganaría un Ironman entre Phelps, Kipchoge y Pogacar?" },
      { t: 4905,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "¿Cagada o Genialidad? | Tubeless ¿si o no? | UAE presenta el \"Coll de POGACAR\"",
    fecha: "2025-12-07",
    duracion: 5620,
    youtubeId: "gbrCsfy5vRQ",
    temas: [
      { t: 225,   titulo: "Specialized crea el superequipo de gravel" },
      { t: 630,   titulo: "¿Se equivoca Specialized en marketing?" },
      { t: 955,   titulo: "Las claves para hacer rodillo" },
      { t: 1800,  titulo: "Emiratos Árabes construye una montaña para Pogacar" },
      { t: 2355,  titulo: "¿Es el tubeless mejor que la cámara en carretera?" },
      { t: 3200,  titulo: "Lo petamos con el club de Strava" },
      { t: 3905,  titulo: "Camino a la Cape Epic, etapa 5" },
      { t: 4085,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Campagnolo está en peligro | Montará Iniesta el próximo SUPEREQUIPO?? | Cape Epic Etapa 4!",
    fecha: "2025-11-30",
    duracion: 4618,
    youtubeId: "sF7fe35IJi4",
    temas: []
  },
  {
    titulo: "Quién manda en el sector de la Bicicleta | 10 cosas sobrevaloradas | DT Swiss se pasa al carbono!",
    fecha: "2025-11-23",
    duracion: 5352,
    youtubeId: "2UP0eeC7aak",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 115,   titulo: "¿Quien es dueño de que?" },
      { t: 860,   titulo: "El Imperio de Rigoberto Uran" },
      { t: 1390,  titulo: "Nuevas Ruedas de DT Swiss" },
      { t: 1910,  titulo: "10 cosas sobrevaloradas del ciclismo" },
      { t: 3580,  titulo: "Cape Epic Etapa 3" },
      { t: 3920,  titulo: "Mejor Comentario" },
      { t: 4135,  titulo: "BICI O CEPO!" }
    ]
  },
  {
    titulo: "5 marcas dicen que tienen la bici más rápida | ¿Quien debería probar las bicis nuevas? | Sorteo!",
    fecha: "2025-11-16",
    duracion: 5103,
    youtubeId: "tiRqcZ6vol8",
    temas: []
  },
  {
    titulo: "Caso de Dopaje en el Pelotón Profesional | El Movistar tiene un problema | ¿Oakley el mejor casco?",
    fecha: "2025-11-09",
    duracion: 5121,
    youtubeId: "ZV-T4QmXcD4",
    temas: [
      { t: 36,    titulo: "El caso de dopaje de Oier Lazkano" },
      { t: 1645,  titulo: "Los cascos más rápidos del mercado" },
      { t: 2815,  titulo: "Presentamos nuestra newsletter" },
      { t: 2876,  titulo: "Analizamos la etapa 2 de Cape Epic" },
      { t: 2994,  titulo: "Cómo convertir tu cepo en avión" },
      { t: 3404,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Top 10 Sueldos del Peloton, (no te lo esperas)! | Como comprar de segunda mano | Cape Epic Etapa 1",
    fecha: "2025-11-02",
    duracion: 6174,
    youtubeId: "umyb6Wroe84",
    temas: []
  },
  {
    titulo: "Un Amateur Gana a Pogacar!! | Tour 2026 | Accidentes con Tubeless en Carretera | ¿Red Bull Rampage?",
    fecha: "2025-10-26",
    duracion: 5343,
    youtubeId: "TWGAR--PDA4",
    temas: [
      { t: 41,    titulo: "Pogi Challenge y un amateur le destrona" },
      { t: 707,   titulo: "Se ven pinceladas del Tour 2026" },
      { t: 889,   titulo: "Avances de la Cape Epic" },
      { t: 1328,  titulo: "Red Bull Rampage" },
      { t: 1790,  titulo: "Respondemos al comentario con más likes" },
      { t: 2194,  titulo: "Analizamos la nueva bici de Ibon Zugasti" },
      { t: 2400,  titulo: "¿Por qué explotan algunas llantas de carbono?" },
      { t: 2844,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Mejores Bicis de Gravel 2025 | ¿Cuanto cuesta empezar a montar en bici? | Porqué exploto Edu en...",
    fecha: "2025-10-19",
    duracion: 5724,
    youtubeId: "txZPMn5L-KA",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 33,    titulo: "Edu cuenta su experiencia en el Mundial" },
      { t: 804,   titulo: "El mega review de bicis de gravel!" },
      { t: 3639,  titulo: "Respondemos a la pregunta con más likes del capítulo" },
      { t: 3888,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Strava demanda a Garmin! | Hemos sido engañados | Edu corre el mundial y las bicis están mal hechas?",
    fecha: "2025-10-12",
    duracion: 4978,
    youtubeId: "gY39_3JzEn0",
    temas: [
      { t: 0,     titulo: "Introducción" },
      { t: 87,    titulo: "Nos habéis mentido en Bici o Cepo" },
      { t: 281,   titulo: "Desvelamos los costes de Strava" },
      { t: 336,   titulo: "Edu nos cuenta su estrategia para el Mundial de Gravel" },
      { t: 1163,  titulo: "Strava demanda a Garmin con objetivos contradictorios" },
      { t: 1855,  titulo: "EE. UU. bloquea la entrada de productos Giant" },
      { t: 2268,  titulo: "Analizamos la altura o stack de las bicis modernas" },
      { t: 3397,  titulo: "Bici o Cepo?" }
    ]
  },
  {
    titulo: "Una Bici CHINA Gana el Mundial! ¿Que significa esto? | No todo vale en STRAVA | ¿Series de torque?",
    fecha: "2025-10-05",
    duracion: 3880,
    youtubeId: "3NbxbH_aJ2s",
    temas: [
      { t: 30,    titulo: "La subida de peso de Remco da sus frutos" },
      { t: 388,   titulo: "Bici china gana el mundial" },
      { t: 685,   titulo: "Analizamos los retos de Strava" },
      { t: 1362,  titulo: "¿Las series de cadencia baja tienen sentido?" },
      { t: 1916,  titulo: "Trek y su Frankestein" },
      { t: 2544,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Subida de peso de Remco!  ¿Fraude de BICILAB? y ya nos pagan la Cape!!",
    fecha: "2025-09-28",
    duracion: 3460,
    youtubeId: "E6tegT-Den0",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 120,   titulo: "Sea Otter" },
      { t: 660,   titulo: "nuevo grupo de \"rotor\"" },
      { t: 960,   titulo: "Carrera Sea Otter" },
      { t: 1410,  titulo: "CRITICA A BICILAB" },
      { t: 1950,  titulo: "Progreso Cape!" },
      { t: 1980,  titulo: "Cambio Fisico de REMCO" },
      { t: 2173,  titulo: "Zapato Specialized FAIL" },
      { t: 2420,  titulo: "BICI O CEPO" }
    ]
  },
  {
    titulo: "¿La mas rápida? ¡Una Decathlon!, ¿somos buenos?, Canyon y más! | Ep.2",
    fecha: "2025-09-14",
    duracion: 3214,
    youtubeId: "3_6ojm-SF7c",
    temas: []
  },
  {
    titulo: "Los vatios inhumanos de Pogacar, Cape Epic, Ayuso y más | Ep.1",
    fecha: "2025-09-07",
    duracion: 2356,
    youtubeId: "qG4PMXC7wLM",
    temas: []
  }
];
