import * as store from "./store.js";
import {$,$$,saToday,esc} from "./util.js";
import {DAYS} from "./data.js";
import {sheet} from "./ui.js";
import hoy from "./views/hoy.js";
import mapa from "./views/mapa.js";
import etapas from "./views/etapas.js";
import media from "./views/media.js";
import mas from "./views/mas.js";
import "./views/carrera.js";
import "./views/fotos.js";

const views = {hoy,mapa,etapas,media,mas};
const TABS = Object.keys(views);

/* Estado de navegación compartido entre vistas */
export const nav = {
  tab:"hoy",
  day:(()=>{const t=saToday();return DAYS.some(d=>d.date===t)?t:DAYS[0].date;})(),
  stage:null,
  go(tab,opts={}){Object.assign(nav,opts);show(tab);}
};
window.__nav = nav;

function show(tab){
  if(!views[tab]) tab="hoy";
  nav.tab=tab;
  TABS.forEach(k=>{$("#v-"+k).hidden=k!==tab;$("#t-"+k).setAttribute("aria-selected",k===tab);});
  renderCurrent(true);
  try{localStorage.setItem("ane-ce2-tab",tab);}catch(e){}
  if(location.hash.slice(1)!==tab) history.replaceState(null,"","#"+tab);
  window.scrollTo(0,0);
}
function renderCurrent(full){
  const v=views[nav.tab], el=$("#v-"+nav.tab);
  if(!el.dataset.init){v.init?.(el,nav);el.dataset.init="1";}
  if(!full && v.update) v.update(el,nav); else v.render(el,nav);
}

function renderHeader(){
  const yo=store.me();
  $("#yo").textContent = yo ? "Tú: "+yo : "¿Quién eres?";
  const s=store.getStatus(), nb=$("#netbar");
  if(s.status==="sin-conexion"){nb.hidden=false;nb.textContent = s.pendientes ? `Sin conexión · ${s.pendientes} ${s.pendientes===1?"cambio pendiente":"cambios pendientes"} de enviar` : "Sin conexión · ves la última copia guardada";}
  else nb.hidden=true;
}

export function pickMe(){
  const nombres=[...new Set(store.all("equipo").map(p=>p.nombre).filter(Boolean))].sort();
  sheet({
    title:"¿Quién eres?",
    intro:"Sirve para saber quién apunta cada cosa. Se guarda solo en este móvil.",
    fields:[
      ...(nombres.length?[{id:"sel",label:"Elige tu nombre",type:"select",value:store.me(),options:[["",""],...nombres.map(n=>[n,n])]}]:[]),
      {id:"nuevo",label:nombres.length?"O escribe otro":"Tu nombre",value:nombres.includes(store.me())?"":store.me(),placeholder:"Eduardo"}
    ],
    onSubmit:v=>{const n=(v.nuevo||v.sel||"").trim(); if(!n) throw new Error("Escribe tu nombre."); store.setMe(n);}
  });
}

function tick(){
  const f=new Intl.DateTimeFormat("es-ES",{timeZone:"Africa/Johannesburg",hour:"2-digit",minute:"2-digit"});
  $("#clock").textContent=f.format(new Date())+" SA";
}

/* Arranque */
$$("nav.tabs button").forEach(b=>b.addEventListener("click",()=>show(b.dataset.v)));
$("#yo").addEventListener("click",pickMe);
store.onChange(()=>{renderHeader(); if($("#sheet").hidden) renderCurrent(false);});
window.addEventListener("sheetclose",()=>renderCurrent(false));
tick(); setInterval(tick,30000);
let first = location.hash.slice(1);
if(!TABS.includes(first)){try{first=localStorage.getItem("ane-ce2-tab")||"hoy";}catch(e){first="hoy";}}
renderHeader(); show(first);
store.startPolling();
if(!store.me()) setTimeout(()=>{ if($("#sheet").hidden) pickMe(); },1200);
if("serviceWorker" in navigator) navigator.serviceWorker.register("sw.js").catch(()=>{});
