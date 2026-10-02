/* En carrera: botón "Estamos aquí", posiciones y llegada estimada */
import * as store from "../store.js";
import {esc,saHM,saToday,durTxt,stageLabel,dirLL} from "../util.js";
import {STAGES} from "../data.js";
import {sheet,toast} from "../ui.js";
import {extraCards} from "./hoy.js";
import {stageCfg,extraSections} from "./etapas.js";
import {registerLayer,mapIcon} from "./mapa.js";

/* Conversión posición -> km de etapa (la rellenan los tracks cuando existan) */
export const kmFinder = {fn:null};

export function checkinsOf(stageId){ return store.all("checkins").filter(c=>c.etapa===stageId).sort((a,b)=>a.t-b.t); }

/* Ritmo y llegada estimada de una etapa.
   Sin posiciones: salida + tiempo objetivo. Con posiciones con km: ritmo real hasta el último punto. */
export function eta(s){
  const cfg=stageCfg(s), cs=checkinsOf(s.id);
  const salida=cs.find(c=>c.km===0);
  const start=salida?salida.t:cfg.startMs;
  const conKm=cs.filter(c=>typeof c.km==="number"&&c.km>0);
  const last=conKm.at(-1)||null;
  const objPace=cfg.minPerKm*60e3; // ms por km
  if(last){
    const pace=Math.max((last.t-start)/last.km, objPace*0.4);
    const done=last.km>=s.km;
    return {start,last,pace,objPace,real:true,done,finish:done?last.t:last.t+(s.km-last.km)*pace,
      at:km=>km<=last.km?{t:start+km*pace,pasado:true}:{t:last.t+(km-last.km)*pace,pasado:false}};
  }
  return {start,last:null,pace:objPace,objPace,real:false,done:false,finish:start+s.km*objPace,
    at:km=>({t:start+km*objPace,pasado:false})};
}
const kmh=msPerKm=>(3600e3/msPerKm).toFixed(1).replace(".",",");
function ago(t){const m=Math.round((Date.now()-t)/60e3);return m<1?"ahora mismo":m<60?`hace ${m} min`:`hace ${durTxt(m)}`;}

function getPos(){
  return new Promise(res=>{
    if(!navigator.geolocation) return res(null);
    navigator.geolocation.getCurrentPosition(p=>res({lat:+p.coords.latitude.toFixed(5),lon:+p.coords.longitude.toFixed(5),acc:Math.round(p.coords.accuracy)}),()=>res(null),{enableHighAccuracy:true,timeout:9000,maximumAge:30000});
  });
}

export async function checkinSheet(s,preset={}){
  const posP=getPos();
  sheet({
    title:"Estamos aquí",
    intro:`${stageLabel(s)} · ${s.km} km. Se guarda la hora y, si el móvil lo permite, la ubicación. Sin cobertura se envía en cuanto vuelva.`,
    fields:[
      {id:"km",label:"Kilómetro",type:"number",value:preset.km??"",placeholder:"58",inputmode:"decimal",step:"0.1",half:true,hint:"Lo marca el ciclocomputador"},
      {id:"lugar",label:"Dónde",value:preset.lugar||"",placeholder:"Avituallamiento 2",half:true},
      {id:"nota",label:"Nota",value:"",placeholder:"Vamos bien, Cristóbal con calambres…"}
    ],
    submit:"Enviar posición",
    onSubmit:async v=>{
      const pos=await Promise.race([posP,new Promise(r=>setTimeout(()=>r(null),3000))]);
      let km=v.km===""?null:Math.max(0,Math.min(s.km,parseFloat(String(v.km).replace(",","."))));
      if(km==null && pos && kmFinder.fn){ const k=kmFinder.fn(s.id,pos); if(k!=null) km=+k.toFixed(1); }
      await store.save("checkins",{etapa:s.id,fecha:s.date,t:Date.now(),km,lugar:v.lugar,nota:v.nota,...(pos||{})});
      toast(pos?"Posición enviada con ubicación.":"Posición enviada (sin ubicación).");
    }
  });
}

function checkinRow(c){
  return `<li><span class="h">${saHM(c.t)}</span><span class="t">${c.km!=null?`<b>km ${String(c.km).replace(".",",")}</b>`:""}${c.lugar?` · ${esc(c.lugar)}`:""}${c.nota?`<small>${esc(c.nota)}</small>`:""}<small>${esc(c.autor||"")}${c.lat?` · <a href="${dirLL(c.lat,c.lon)}" target="_blank" rel="noopener">Cómo llegar</a>`:""}</small></span><button class="edit" data-act="ck-del" data-id="${c.id}">Borrar</button></li>`;
}

export function carreraPanel(s,full=false){
  const e=eta(s), cs=checkinsOf(s.id), cfg=stageCfg(s);
  const isToday=s.date===saToday();
  const last=cs.at(-1);
  let status;
  if(e.done) status=`<div class="eta"><span>En meta</span><b class="num">${saHM(e.finish)}</b><small>${durTxt((e.finish-e.start)/60e3)} de carrera</small></div>`;
  else status=`<div class="eta"><span>Llegada estimada</span><b class="num">${saHM(e.finish)}</b><small>${e.real?`Ritmo real ${kmh(e.pace)} km/h (objetivo ${kmh(e.objPace)})`:`Con el tiempo objetivo ${cfg.objetivo} h · salida ${saHM(e.start)}`}</small></div>`;
  return `<div class="panel live">
    <div class="panel-h"><h3>En carrera</h3><span class="tag">${stageLabel(s)}</span></div>
    ${status}
    ${last?`<p class="small" style="margin:0">Última posición: <b>${saHM(last.t)}</b>${last.km!=null?` · km ${String(last.km).replace(".",",")}`:""}${last.lugar?` · ${esc(last.lugar)}`:""} · ${ago(last.t)}${last.autor?` · ${esc(last.autor)}`:""}</p>`:`<p class="small muted" style="margin:0">Aún no hay posiciones de esta etapa.</p>`}
    <button class="btn wide big" data-act="ck-add" data-stage="${s.id}">Estamos aquí</button>
    <div class="btns"><button class="btn small ghost" data-act="ck-salida" data-stage="${s.id}">Salida dada</button><button class="btn small ghost" data-act="ck-meta" data-stage="${s.id}">En meta</button></div>
    ${cs.length&&(full||isToday)?`<ol class="timeline">${cs.slice().reverse().slice(0,full?50:4).map(checkinRow).join("")}</ol>`:""}
  </div>`;
}

async function handle(a,b,s){
  if(!s) s=STAGES.find(x=>x.id===b.dataset.stage);
  if(a==="ck-add") checkinSheet(s);
  if(a==="ck-salida"){ await store.save("checkins",{etapa:s.id,fecha:s.date,t:Date.now(),km:0,lugar:"Salida"}); toast("Hora de salida guardada."); }
  if(a==="ck-meta"){ await store.save("checkins",{etapa:s.id,fecha:s.date,t:Date.now(),km:s.km,lugar:"Meta"}); toast("¡En meta! Hora guardada."); }
  if(a==="ck-del"){ const c=store.one("checkins",b.dataset.id); if(c && b.dataset.armed){ await store.remove("checkins",c.id); } else { b.dataset.armed="1"; b.textContent="¿Seguro?"; } }
}

/* En "Hoy": solo los días con etapa */
extraCards.push({
  html:(d,st)=>st?carreraPanel(st):"",
  onClick:(a,b)=>{ if(a&&a.startsWith("ck-")) handle(a,b); }
});
/* En la ficha de etapa */
extraSections.push({
  html:(s)=>carreraPanel(s,true),
  onClick:(a,b,s)=>{ if(a&&a.startsWith("ck-")) handle(a,b,s); }
});
/* En el mapa */
registerLayer({id:"posiciones",label:"Posiciones",draw(g,ctx){
  const L=window.L;
  const stages = ctx.stage==="todas" ? STAGES.filter(s=>s.date===saToday()) : STAGES.filter(s=>s.id===ctx.stage);
  for(const s of stages){
    const cs=checkinsOf(s.id).filter(c=>c.lat!=null);
    if(cs.length>1) L.polyline(cs.map(c=>[c.lat,c.lon]),{color:"#C9862E",weight:3,dashArray:"4 6",stage:s.id}).addTo(g);
    cs.forEach((c,i)=>{
      const lastOne=i===cs.length-1;
      L.marker([c.lat,c.lon],{icon:mapIcon("check"+(lastOne?" last":""),saHM(c.t)+(c.km!=null?" · km "+c.km:"")),stage:s.id,zIndexOffset:lastOne?1000:0})
        .bindPopup(`<b>${saHM(c.t)}${c.km!=null?" · km "+c.km:""}</b>${c.lugar?"<br>"+esc(c.lugar):""}${c.nota?"<br>"+esc(c.nota):""}<br><small>${esc(c.autor||"")}</small><div class="pop-acts"><a href="${dirLL(c.lat,c.lon)}" target="_blank" rel="noopener">Cómo llegar</a></div>`).addTo(g);
    });
  }
}});
