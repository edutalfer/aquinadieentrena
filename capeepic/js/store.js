/* Datos compartidos: todo vive en el servidor (api.php) y se copia en el móvil.
   Si no hay cobertura, los cambios se guardan en una cola y se envían solos
   en cuanto vuelve la conexión. */
import {uid} from "./util.js";

const API = "api.php";
const LS_DATA = "ane-ce2-data", LS_OUT = "ane-ce2-outbox", LS_ME = "ane-ce2-yo";
let data = {};          // {coleccion: {id: doc}}
let outbox = [];        // [{accion,c,doc|id}]
let status = "cargando"; // cargando | ok | sin-conexion
const subs = new Set();

function readLS(k,def){try{const v=localStorage.getItem(k);return v?JSON.parse(v):def;}catch(e){return def;}}
function writeLS(k,v){try{localStorage.setItem(k,JSON.stringify(v));}catch(e){}}

data = readLS(LS_DATA,{});
outbox = readLS(LS_OUT,[]);
if(Object.keys(data).length) status = "copia";

function notify(){subs.forEach(f=>{try{f();}catch(e){console.error(e);}});}
export function onChange(f){subs.add(f);return ()=>subs.delete(f);}
export function getStatus(){return {status,pendientes:outbox.length};}

export function all(c){return Object.values(data[c]||{});}
export function one(c,id){return (data[c]||{})[id]||null;}

function applyLocal(op){
  data[op.c] = data[op.c]||{};
  if(op.accion==="guardar") data[op.c][op.doc.id] = op.doc;
  else delete data[op.c][op.id];
}

async function post(op){
  const r = await fetch(API,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify(op),cache:"no-store"});
  const j = await r.json().catch(()=>({}));
  if(!r.ok){const e=new Error(j.error||("Error "+r.status));e.server=true;throw e;}
  return j;
}

async function flush(){
  while(outbox.length){
    try{await post(outbox[0]);}
    catch(e){ if(e.server){outbox.shift();writeLS(LS_OUT,outbox);continue;} return false; }
    outbox.shift(); writeLS(LS_OUT,outbox);
  }
  return true;
}

export async function sync(){
  await flush();
  try{
    const r = await fetch(API,{credentials:"same-origin",cache:"no-store"});
    if(!r.ok) throw new Error("HTTP "+r.status);
    const j = await r.json();
    const fresh = {};
    for(const [c,docs] of Object.entries(j.cols||{})){fresh[c]={};docs.forEach(d=>fresh[c][d.id]=d);}
    data = fresh; outbox.forEach(applyLocal); // los pendientes siguen viéndose
    status = outbox.length ? "sin-conexion" : "ok"; writeLS(LS_DATA,data);
  }catch(e){ status = "sin-conexion"; }
  notify();
}

/* Guarda un documento. Devuelve el doc guardado. Nunca falla por falta de cobertura. */
export async function save(c,doc){
  doc = {...doc}; if(!doc.id) doc.id = uid();
  doc.autor = doc.autor || me() || "";
  const op = {accion:"guardar",c,doc};
  applyLocal(op); writeLS(LS_DATA,data); notify();
  try{ await flush(); await post(op); }
  catch(e){ if(e.server){ throw e; } outbox.push(op); writeLS(LS_OUT,outbox); status="sin-conexion"; notify(); }
  return doc;
}
export async function remove(c,id){
  const op = {accion:"borrar",c,id};
  applyLocal(op); writeLS(LS_DATA,data); notify();
  try{ await flush(); await post(op); }
  catch(e){ if(e.server) throw e; outbox.push(op); writeLS(LS_OUT,outbox); status="sin-conexion"; notify(); }
}

/* Quién usa este móvil (solo se guarda en el propio móvil) */
export function me(){try{return localStorage.getItem(LS_ME)||"";}catch(e){return "";}}
export function setMe(n){try{localStorage.setItem(LS_ME,n);}catch(e){} notify();}

/* Subida de fotos (mono del día). Requiere conexión. */
export async function upload(file){
  const fd = new FormData(); fd.append("foto",file);
  const r = await fetch("upload.php",{method:"POST",body:fd,credentials:"same-origin"});
  const j = await r.json().catch(()=>({}));
  if(!r.ok) throw new Error(j.error||("Error "+r.status));
  return j.url;
}

export function startPolling(){
  sync();
  setInterval(()=>{if(!document.hidden) sync();},20000);
  document.addEventListener("visibilitychange",()=>{if(!document.hidden) sync();});
  window.addEventListener("online",sync);
}
