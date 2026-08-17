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
    temas: [
      { t: 0,     titulo: "Nuestra opinión de la China Cycle 2026" },
      { t: 1440,  titulo: "Analizamos la bici Incolor" },
      { t: 1690,  titulo: "Precios en China" },
      { t: 2051,  titulo: "Revelamos el ganador del sorteo de Toteemi" },
      { t: 2189,  titulo: "Polémica en The Traka" },
      { t: 3000,  titulo: "Rotor busca inversión para asegurar puestos de trabajo" },
      { t: 3267,  titulo: "Empieza el Giro de Italia" },
      { t: 3720,  titulo: "¡Bici o cepo!" },
      { t: 4180,  titulo: "Recorremos Taiwán en bici" }
    ]
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
    titulo: "Van Der Poel IGNORA a Canyon en Roubaix! | Seixas va al tour? | Presentamos nuestras bicis de gravel",
    fecha: "2026-04-12",
    duracion: 6858,
    youtubeId: "KPiGXHKJ_gM",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 220,   titulo: "Cristóbal en el Tour de Flandes" },
      { t: 510,   titulo: "Por fin conocemos la nueva Canyon Endurance" },
      { t: 1535,  titulo: "¿Qué va a pasar en Paris Roubaix?" },
      { t: 2640,  titulo: "¿Paul Seixas debería ir al Tour de Francia y además fichar por el UAE?" },
      { t: 3420,  titulo: "Tenemos problemas de motivación" },
      { t: 4380,  titulo: "Descubrimos una app que le da sentido a tener Strava" },
      { t: 4935,  titulo: "La nueva bici de Cristóbal" },
      { t: 5550,  titulo: "¡Bici o Cepo!" }
    ]
  },
  {
    titulo: "¿Esta es la nueva Canyon Aeroad? | Strava no tiene sentido | La liamos para el tour…",
    fecha: "2026-04-05",
    duracion: 5667,
    youtubeId: "_pNQhuWqt3M",
    temas: [
      { t: 50,    titulo: "Los comentaristas de Eurosport nos atacan" },
      { t: 385,   titulo: "Look saca unos pedales nuevos" },
      { t: 970,   titulo: "¿Merece la pena Strava?" },
      { t: 1665,  titulo: "Analizamos los datos de los 90’ de Van Der Poel" },
      { t: 2790,  titulo: "Parece que Canyon tiene una nueva bici ¿AEROAD O ENDURANCE? (37:35)  - Tenemos un problema gordo" },
      { t: 4050,  titulo: "Bici o Cepo" }
    ]
  },
  {
    titulo: "El futuro del textil en ciclismo | Cómo puede ganar el tour Remco  | Carreras por parejas",
    fecha: "2026-03-29",
    duracion: 4889,
    youtubeId: "38AjpfCo7dc",
    temas: [
      { t: 0,     titulo: "Intro + Igraal" },
      { t: 505,   titulo: "La caída de Remco" },
      { t: 935,   titulo: "Nuestro proyecto para el Tour" },
      { t: 1085,  titulo: "Mejor comentario de la semana" },
      { t: 1460,  titulo: "Continental lanza las GP 5000 en 35mm" },
      { t: 1780,  titulo: "Hablamos de Lukas Baum y el Speed Company" },
      { t: 2260,  titulo: "Tendencias en textil para verano" },
      { t: 3175,  titulo: "El cuadro roto de Pogacar" },
      { t: 3385,  titulo: "¡Bici o Cepo!" }
    ]
  },
  {
    titulo: "Errores de la Cape Epic 2026 | ¿La nueva “mejor” marca española?",
    fecha: "2026-03-24",
    duracion: 4149,
    youtubeId: "lWj1oTcYX8o",
    temas: [
      { t: 0,     titulo: "Intro" },
      { t: 40,    titulo: "Igraal" },
      { t: 225,   titulo: "Conclusiones sobre Cape Epic" },
      { t: 1250,  titulo: "Tecnología Cape" },
      { t: 1495,  titulo: "Cambio en el recorrido de las chicas" },
      { t: 2270,  titulo: "Equipaciones a la venta" },
      { t: 2375,  titulo: "Felt presenta su nueva bici Aero" },
      { t: 3170,  titulo: "Comentarios sobre Milan San Remo" },
      { t: 3420,  titulo: "¡Bici o cepo!" }
    ]
  },
  {
    titulo: "Specialized perdona a Armstrong?? | Cuanto dinero se necesita para competir en el world tour?",
    fecha: "2026-03-15",
    duracion: 4153,
    youtubeId: "U1yfpFm5A4g",
    temas: [
      { t: 5,     titulo: "Maillots nuevos" },
      { t: 330,   titulo: "Igraal" },
      { t: 520,   titulo: "¿Cuánto dinero se necesita para competir en el World Tour?" },
      { t: 1430,  titulo: "¿Specialized perdona a Lance Armstrong?" },
      { t: 2000,  titulo: "Cannondale lanza una nueva bici de aluminio" },
      { t: 2520,  titulo: "Cyclingnews prueba la Enve Meele en el túnel del viento" },
      { t: 2800,  titulo: "¡Bici o Cepo!" }
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
    temas: [
      { t: 210,   titulo: "Analizamos la nueva Cannondale SuperSix Evo" },
      { t: 2220,  titulo: "Contamos cómo llegamos al Andalucía Bike Race" },
      { t: 2660,  titulo: "Marcas como Specialized y Trek se vuelven bohemios en sus diseños" },
      { t: 2970,  titulo: "Edu cuenta su experiencia con los pros en Santa Val" },
      { t: 5280,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "¿Redbull te da alas? | NOS PAGAN LA CAPE! | Lucha entre equipos “off-road”",
    fecha: "2026-02-15",
    duracion: 6991,
    youtubeId: "B9y3ELUXPJk",
    temas: [
      { t: 310,   titulo: "Nuestros últimos entrenamientos y cómo llegamos al Andalucía Bike Race" },
      { t: 1170,  titulo: "Anunciamos nuevos patrocinadores para Cape Epic" },
      { t: 2640,  titulo: "¿Qué hace falta para ganar la Mediterranean Epic?" },
      { t: 3300,  titulo: "Más marcas lanzan equipos todoterreno" },
      { t: 3860,  titulo: "¿El mejor momento de Remco?" },
      { t: 4930,  titulo: "Bici o cepo" }
    ]
  },
  {
    titulo: "¿Qué esta pasando en CANYON? | Edu presenta bici | Cuanto tiene que pesar una gravel para ser CEPO",
    fecha: "2026-02-08",
    duracion: 6436,
    youtubeId: "ev0tzxeAOLY",
    temas: [
      { t: 60,    titulo: "Nos picamos con las bicis de mountain bike" },
      { t: 600,   titulo: "El peso clave en bicicletas de gravel" },
      { t: 1695,  titulo: "Analizamos los canales de YouTube del World Tour" },
      { t: 4195,  titulo: "Charlamos sobre la bajada de precios de Canyon y otras marcas" },
      { t: 4810,  titulo: "Bici o cepo" }
    ]
  },
  {
    titulo: "Flecha nos acusa de \"CHARLATANES\" | los tests aerodinámicos de una Bici CHINA nos sorprenden!",
    fecha: "2026-02-01",
    duracion: 7650,
    youtubeId: "Z0HybwI-yxk",
    temas: [
      { t: 170,   titulo: "Os contamos cómo nos estamos preparando para el Andalucía Bike Race" },
      { t: 840,   titulo: "Meten la nueva Seka en el túnel del viento" },
      { t: 1880,  titulo: "Charlamos de lo bien que lo está haciendo Wilier" },
      { t: 3810,  titulo: "Ganador de etapa del Giro ficha por Rapha y Factor se pasa al gravel" },
      { t: 4290,  titulo: "La polémica con David Millar y Nero Show" },
      { t: 5300,  titulo: "Juan Antonio Flecha nos critica" },
      { t: 6240,  titulo: "¡Bici o cepo!" }
    ]
  },
  {
    titulo: "TODAS las bicis del world tour 2026 analizadas! | Van Rysel hace MUCHO ruido y anunciamos carrera!",
    fecha: "2026-01-25",
    duracion: 4860,
    youtubeId: "VEvA7w4EVOc",
    temas: [
      { t: 450,   titulo: "¿Cómo estamos cuidando la alimentación?" },
      { t: 1030,  titulo: "Anunciamos nuestra primera carrera por parejas en MTB" },
      { t: 1470,  titulo: "Sección de noticias (Rapha, Rose, dopaje)" },
      { t: 3200,  titulo: "Bici o cepo World Tour 2026" }
    ]
  },
  {
    titulo: "¿Por qué se retira un campeón tan pronto? | Rapha cierra tiendas! | ¿El Jayco regala el titulo?",
    fecha: "2026-01-18",
    duracion: 6831,
    youtubeId: "5yoINT_hUiw",
    temas: [
      { t: 160,   titulo: "El Jayco la lía en el campeonato de Australia" },
      { t: 1550,  titulo: "Simon Yates abandona el ciclismo" },
      { t: 3300,  titulo: "XDS busca ser la bici más rápida" },
      { t: 3720,  titulo: "SRAM empieza a vender bielas de 150 mm" },
      { t: 4220,  titulo: "El equipo Buff Megamo ahora se llamará Buff BH" },
      { t: 4750,  titulo: "Rapha empieza a cerrar clubhouses" },
      { t: 5940,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Los beneficios de la creatina para ciclistas y un nuevo maillot de specialized que ahorra 20 vatios?",
    fecha: "2026-01-11",
    duracion: 5502,
    youtubeId: "-TUEBDC4eqs",
    temas: [
      { t: 121,   titulo: "¿La creatina tiene beneficios en el ciclismo?" },
      { t: 1120,  titulo: "¿Qué bici se compraría ahora Cristóbal?" },
      { t: 2710,  titulo: "Salen las últimas equipaciones, ¿cuál nos compraríamos?" },
      { t: 3070,  titulo: "La nueva ropa de Specialized ahorra 20W" },
      { t: 3410,  titulo: "Detalles aerodinámicos" },
      { t: 3766,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Specialized lo vuelve a hacer!| Edu entrena con los pros en Andorra | Objetivos 2026!!",
    fecha: "2026-01-04",
    duracion: 5641,
    youtubeId: "IXwk61V6gWs",
    temas: [
      { t: 80,    titulo: "¿Cuál es el sistema perfecto para ruedas de bici de montaña?" },
      { t: 595,   titulo: "¿Cómo lleva Cristóbal el entrenamiento de la Cape Epic?" },
      { t: 1585,  titulo: "Edu sale a entrenar con los pros" },
      { t: 2480,  titulo: "Specialized hace un súper equipo off road. ¿Cuánto gastan?" },
      { t: 3090,  titulo: "Planteamos los objetivos del podcast para 2026" },
      { t: 4125,  titulo: "Assos presenta la nueva equipación del Education First" },
      { t: 4405,  titulo: "¡Bici o cepo!" }
    ]
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
    temas: [
      { t: 0,     titulo: "Introducción" },
      { t: 200,   titulo: "¿Cuál es la mejor equipación 2026?" },
      { t: 2460,  titulo: "Nuevo caso de dopaje en el ciclismo" },
      { t: 3480,  titulo: "Cycling News vuelve a analizar las bicis más rápidas" },
      { t: 3930,  titulo: "Noticias de la semana" },
      { t: 4570,  titulo: "Cape Epic, etapa 6" },
      { t: 4980,  titulo: "¡Bici o cepo!" }
    ]
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
    temas: [
      { t: 71,    titulo: "Sacamos nuestras gorras" },
      { t: 147,   titulo: "La empresa de Iniesta compra el equipo de Israel" },
      { t: 670,   titulo: "Campagnolo en dificultades" },
      { t: 1170,  titulo: "La UCI acepta las ruedas de 32\" en MTB" },
      { t: 1746,  titulo: "Cómo viajar con bici en coche" },
      { t: 2468,  titulo: "Sacamos club de Strava" },
      { t: 3135,  titulo: "Camino a la Cape Epic, etapa 4" },
      { t: 3378,  titulo: "¿Bici o cepo?" }
    ]
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
    temas: [
      { t: 231,   titulo: "Qué esperamos de la industria en los próximos 2 años" },
      { t: 2446,  titulo: "5 marcas dicen que tienen la bici más rápida del pelotón" },
      { t: 3023,  titulo: "Quiénes deberían probar las nuevas bicis del mercado" },
      { t: 3623,  titulo: "¿Bici o cepo?" },
      { t: 5072,  titulo: "Hacemos el primer sorteo del canal" }
    ]
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
    temas: [
      { t: 55,    titulo: "Sueldos de profesionales de pelotón" },
      { t: 1979,  titulo: "Premios en el Lifetime Grandprix" },
      { t: 2416,  titulo: "Mejor comentario de la semana" },
      { t: 3428,  titulo: "Recomendaciones para comprar bicis de segunda mano" },
      { t: 3747,  titulo: "Analizamos la primera etapa de Cape Epic" },
      { t: 4079,  titulo: "¿Bici o cepo?" }
    ]
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
    titulo: "¿Como de ancho es demasiado ancho!?,  Las bicis mas aero de 2025 y Van Der Poel fracasa? |  EP.3",
    fecha: "2025-09-21",
    duracion: 4059,
    youtubeId: "piWBTCsMFdY",
    temas: [
      { t: 0,     titulo: "Las bicis más rápidas del mercado" },
      { t: 960,   titulo: "Pedimos perdón a Orbea" },
      { t: 1220,  titulo: "Experiencia en el mundial de Ironman Niza" },
      { t: 1760,  titulo: "Analizamos el mejor ancho de cubierta para tu bici" },
      { t: 2280,  titulo: "El comentario elegido!" },
      { t: 2400,  titulo: "Van Der Poel hace un seto en el mundia de XC" },
      { t: 2520,  titulo: "Bici o CEPO!" }
    ]
  },
  {
    titulo: "¿La mas rápida? ¡Una Decathlon!, ¿somos buenos?, Canyon y más! | Ep.2",
    fecha: "2025-09-14",
    duracion: 3214,
    youtubeId: "3_6ojm-SF7c",
    temas: [
      { t: 66,    titulo: "Bici Van Rysel, características y marquismo del ciclismo" },
      { t: 1034,  titulo: "Cape Epic" },
      { t: 1093,  titulo: "Aumento de la velocidad media en el Tour de Francia" },
      { t: 1460,  titulo: "¿Cuál es el nivel de Cristóbal como ciclista?" },
      { t: 1860,  titulo: "Keegan Swenson y Kate Courtney campeones del mundo en XCM" },
      { t: 2130,  titulo: "Canyon y su cambio de CEO" },
      { t: 2331,  titulo: "Posible nueva sección" },
      { t: 2389,  titulo: "¿Bici o cepo?" }
    ]
  },
  {
    titulo: "Los vatios inhumanos de Pogacar, Cape Epic, Ayuso y más | Ep.1",
    fecha: "2025-09-07",
    duracion: 2356,
    youtubeId: "qG4PMXC7wLM",
    temas: [
      { t: 40,    titulo: "Pogacar" },
      { t: 770,   titulo: "Cape Epic: progreso económico" },
      { t: 1295,  titulo: "Badlands" },
      { t: 1790,  titulo: "Noticias: Ayuso sale del UAE, raja a saco y parece que se irá al Lidl-Trek" },
      { t: 1975,  titulo: "Preguntas entreno" },
      { t: 2270,  titulo: "¿Bici o cepo?" }
    ]
  }
];
