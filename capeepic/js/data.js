/* Datos oficiales ruta 2027 (epic-series.com, anuncio de septiembre 2026) */
export const PLACES = {
  lourensford:{name:"Lourensford Wine Estate",town:"Somerset West",lat:-34.0679,lon:18.8866,q:"Lourensford Wine Estate, Somerset West"},
  ceres:{name:"Ceres",town:"Ceres",lat:-33.3689,lon:19.3104,q:"Ceres, Western Cape"},
  saronsberg:{name:"Saronsberg",town:"Tulbagh",lat:-33.2551,lon:19.1383,q:"Saronsberg Wine Cellar, Tulbagh"},
  riebeek:{name:"Riebeek Kasteel",town:"Riebeek Kasteel",lat:-33.3833,lon:18.8963,q:"Riebeek Kasteel"},
  imbuko:{name:"Imbuko Wines",town:"Wellington",lat:-33.6120,lon:19.0040,q:"Imbuko Wines, Wellington"},
  cpt:{name:"Aeropuerto de Ciudad del Cabo",town:"Aeropuerto",lat:-33.9690,lon:18.6020,q:"Cape Town International Airport"}
};
export const VENUES = ["lourensford","ceres","saronsberg","imbuko"];

export const STAGES = [
  {id:"P",date:"2027-03-21",name:"The Decision",km:27,dplus:1050,stars:2,from:"lourensford",to:"lourensford",salida:"08:00",objetivo:"1:45",
   sectors:["Pinot Stairs","Subida N4","Rock Wall","Schoeman's","Big Switch"],tts:"Schoeman's",
   note:"Bucle en sentido horario por el anfiteatro de Lourensford. Salidas escalonadas por parejas."},
  {id:"1",date:"2027-03-22",name:"The First Question",km:127,dplus:2450,stars:5,from:"ceres",to:"ceres",salida:"07:00",objetivo:"6:30",
   sectors:["Old Theron's Pass","Waboomsberg","Koue Bokkeveld","Kaleo","Old Gydo Pass"],tts:null,
   note:"Waboomsberg es el punto más alto de la historia de la carrera. Bajada técnica por la arenisca de Kaleo."},
  {id:"2",date:"2027-03-23",name:"The Response",km:103,dplus:2050,stars:4.5,from:"ceres",to:"ceres",salida:"07:00",objetivo:"5:30",
   sectors:["Death Drop","Dead Man Walking","Baboon's Highway","Pipeline","Presa de Eselfontein"],tts:"Pipeline",
   note:"Tres bucles en Eselfontein, al este de Ceres."},
  {id:"3",date:"2027-03-24",name:"The Rhythm",km:87,dplus:2250,stars:4,from:"ceres",to:"saronsberg",salida:"07:00",objetivo:"5:00",
   sectors:["Old Gydo Pass","Bokkeveld","Skurfberg","Die Eike","Witzenberg Ridge","Wagon Trail"],tts:"Wagon Trail",
   note:"Sale de Ceres y termina en el nuevo race village de Saronsberg (Tulbagh): algo más de 1 h de vuelta a la casa."},
  {id:"4",date:"2027-03-25",name:"The Turning Point",km:96,dplus:2550,stars:4.5,from:"saronsberg",to:"saronsberg",salida:"07:00",objetivo:"5:45",
   sectors:["Oakhurst","God's Window","Kilprivier","Schoonderzicht","Bone Trail"],tts:null,
   note:"Cinco subidas y bajadas por el valle de Tulbagh."},
  {id:"5",date:"2027-03-26",name:"The Crossing",km:114,dplus:2350,stars:3.5,from:"saronsberg",to:"imbuko",via:"riebeek",salida:"07:00",objetivo:"5:30",
   sectors:["Swartland","Riebeek Kasteel","Kasteelberg","Eight Feet","Riebeekberg","Patatskloof"],tts:"Riebeekberg",
   note:"Pista abierta y viento en el Swartland. Día de ir a rueda. Termina en Imbuko, a 10 min de la casa."},
  {id:"6",date:"2027-03-27",name:"The Cliffhanger",km:83,dplus:3100,stars:5,from:"imbuko",to:"imbuko",salida:"07:00",objetivo:"6:00",
   sectors:["Hawequa","Seven Peaks","Aap d'Huez","Angel's Tears","DNF","Brandslangnek","The Cliffhanger","Full Monty","Route 66","Unhappy Hog","Golden Mile"],tts:null,
   note:"Etapa reina. The Cliffhanger está en Canetsfontein Wine Estate."},
  {id:"7",date:"2027-03-28",name:"The Final Line",km:64,dplus:1350,stars:3,from:"imbuko",to:"imbuko",salida:"07:30",objetivo:"3:30",
   sectors:["Groenberg","Luislang","Welvanpas","Bull Run","True Grit","Doolhof Wine Estate","Blazing Saddles"],tts:null,
   note:"La más suave sobre el papel. Doolhof es buen sitio para animar."}
];

/* Días del viaje y base donde se duerme esa noche.
   Del 21 al 29 dormimos en la Casa de campo Twyfeling (Wellington) */
export const DAYS = [
  {date:"2027-03-20",base:"Somerset West",place:"lourensford",label:"Víspera"},
  {date:"2027-03-21",base:"Wellington",place:"imbuko",label:"Prólogo y llegada a la casa"},
  {date:"2027-03-22",base:"Wellington",place:"imbuko",label:"Etapa 1"},
  {date:"2027-03-23",base:"Wellington",place:"imbuko",label:"Etapa 2"},
  {date:"2027-03-24",base:"Wellington",place:"imbuko",label:"Etapa 3"},
  {date:"2027-03-25",base:"Wellington",place:"imbuko",label:"Etapa 4"},
  {date:"2027-03-26",base:"Wellington",place:"imbuko",label:"Etapa 5"},
  {date:"2027-03-27",base:"Wellington",place:"imbuko",label:"Etapa 6"},
  {date:"2027-03-28",base:"Wellington",place:"imbuko",label:"Etapa 7 y meta"}
];

/* La casa: Casa de campo Twyfeling, Wellington (coordenadas aproximadas del pueblo hasta tener la dirección exacta) */
export const HOME = {name:"Casa Twyfeling",town:"Wellington",lat:-33.6390,lon:19.0110};
/* Coche desde la casa hasta la salida de cada etapa (OSRM, sin tráfico) */
export const DRIVE = {P:{min:60,km:65},"1":{min:54,km:53},"2":{min:54,km:53},"3":{min:54,km:53},"4":{min:66,km:57},"5":{min:66,km:57},"6":{min:11,km:4},"7":{min:11,km:4}};

export const TRANSFERS = [
  {from:"cpt",to:"imbuko",t:"≈ 1 h · 69 km"},
  {from:"imbuko",to:"lourensford",t:"≈ 1 h · 65 km",nota:"Prólogo"},
  {from:"imbuko",to:"ceres",t:"≈ 55 min · 53 km",nota:"Etapas 1, 2 y 3 (por Bainskloof)"},
  {from:"imbuko",to:"saronsberg",t:"≈ 1 h 05 · 57 km",nota:"Etapas 4 y 5"},
  {from:"imbuko",to:"cpt",t:"≈ 1 h · 69 km",nota:"Vuelta"}
];

/* Plantilla de plan para un día de carrera (se usa para rellenar un día vacío) */
export function planTemplate(stage){
  if(!stage) return [
    {hora:"09:00",texto:"Desayuno"},
    {hora:"11:00",texto:"Recoger dorsales y material"},
    {hora:"18:00",texto:"Briefing / revisar bicis"},
    {hora:"20:00",texto:"Cena"}
  ];
  const [h,m]=stage.salida.split(":").map(Number);
  const t=(min)=>{const x=Math.floor((h*60+m+min)/5)*5;return String(Math.floor(((x%1440)+1440)%1440/60)).padStart(2,"0")+":"+String(((x%60)+60)%60).padStart(2,"0");};
  const [oh,om]=stage.objetivo.split(":").map(Number), dur=oh*60+om;
  const drive=(DRIVE[stage.id]?.min||30)+30; // coche + margen para aparcar y calentar
  return [
    {hora:t(-drive-90),texto:"Despertador"},
    {hora:t(-drive-60),texto:"Desayuno"},
    {hora:t(-drive),texto:"Salir de casa hacia la salida",notas:`${DRIVE[stage.id]?.min||"?"} min de coche desde Wellington`},
    {hora:t(0),texto:"Salida "+(stage.id==="P"?"del prólogo":"etapa "+stage.id)},
    {hora:t(dur),texto:"Llegada prevista a meta"},
    {hora:t(dur+60),texto:"Comida y recuperación"},
    {hora:t(dur+150),texto:"Masaje / mecánico"},
    {hora:t(dur+210),texto:"Vuelta a casa"},
    {hora:"19:00",texto:"Cena"},
    {hora:"21:30",texto:"A dormir"}
  ];
}

export const CHECK_DEFAULTS = {
  corredor:["Dorsal y chip","Casco y gafas","Bidones llenos","Geles y barritas","Herramienta y cámara","Ropa de lluvia/viento","Crema solar","Móvil cargado","Luces (prólogo no)"],
  media:["Tarjetas descargadas","Copia de seguridad hecha","Baterías cargadas","Dron cargado y permiso","Micros cargados","Objetivos limpios","Disco duro con espacio"]
};

export const SHOT_DEFAULTS = ["Salida desde dentro del pelotón","Plano del mono del día","Paso por la Tough Section","Avituallamiento","Llegada a meta y reacción","Entrevista corta post-etapa","Recuperación / masaje","Ambiente del race village"];

/* Avituallamientos y Toyota Tough Sections leídos de los perfiles oficiales 2027 (km aproximado ±0,5).
   [km, nombre oficial o null, referencia cercana] */
export const WATER = {
  "1":[[26.2,"Elim",null],[48.6,"R46",null],[65.0,null,"Waboomsberg"],[92.8,"Kaleo",null],[117.8,"Prince Alfred Hamlet",null]],
  "2":[[21.7,"Eselfontein",null],[42.6,"Eselfontein",null],[57.0,null,"Pipeline"],[67.5,"Eselfontein",null],[88.9,"Loxtonia",null]],
  "3":[[25.6,null,"Old Gydo"],[38.6,null,"Welgemeen"],[62.9,"Die Eike",null],[73.0,null,"Wagon Trail"],[82.9,"Kruisvallei Rd",null]],
  "4":[[17.2,null,"Daan se Baan"],[33.8,"Theuniskraal",null],[58.0,null,"The Meadows"],[80.2,null,"Schalkenbosch"],[88.9,"Church Street",null]],
  "5":[[27.3,null,"Historic Bridge"],[50.9,"Riebeek Kasteel",null],[75.3,"Porselein Berg Road",null],[94.3,"Kufefi Coffee R44",null],[104.9,null,"Wellington"]],
  "6":[[30.1,null,"Cool Runnings"],[39.3,null,"DNF"],[52.2,null,"Full Monty"],[65.5,null,"Golden Mile"]],
  "7":[[22.0,null,"Roller Coaster"],[36.3,null,"Happy Hog"],[46.2,null,"True Grit"],[56.3,null,"Doolhof"]]
};
export const TTS = {"1":[68,75.7,"Mast Drop"],"2":[58,62.7,"Pipeline"],"3":[74,80.2,"Wagon Trail"],"4":[64.4,68.2,"Bone Trail"],"5":[56.9,59.3,"Riebeekberg"],"6":[42.9,45.3,"The Cliffhanger"],"7":[49.4,51.3,"Blazing Saddles"]};
export function waterOf(id){ return (WATER[id]||[]).map(([km,nombre,cerca],i)=>({n:i+1,km,nombre,cerca,label:nombre||("cerca de "+cerca)})); }
