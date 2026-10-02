/* Puntos de interés por etapa: guía de los acompañantes para ese día
   (avituallamientos, sitios para animar, parking, comida…). Cada punto lleva su enlace para ir. */
import * as store from "../store.js";
import {esc,saHM,dirLL,stageLabel,dayLabel} from "../util.js";
import {STAGES} from "../data.js";
import {sheet,toast} from "../ui.js";
import {extraSections} from "./etapas.js";
import {registerLayer,mapIcon,pickPoint,onPopupClick} from "./mapa.js";
import {eta} from "./carrera.js";

const TIPOS=[["animar","Para animar"],["avit","Avituallamiento"],["parking","Parking"],["comida","Comida / café"],["otro","Otro"]];
const tipoTxt=t=>(TIPOS.find(x=>x[0]===t)||TIPOS[4])[1];

export function puntosOf(stageId){ return store.all("spots").filter(p=>p.etapa===stageId).sort((a,b)=>(a.km??1e9)-(b.km??1e9)||String(a.nombre).localeCompare(b.nombre)); }

/* Saca coordenadas de un enlace de Google Maps cuando las lleva (los enlaces cortos maps.app.goo.gl no las llevan) */
export function coordsFromLink(url){
  const u=String(url||"");
  const pats=[/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/,/@(-?\d+\.\d+),(-?\d+\.\d+)/,/[?&](?:q|query|destination|ll)=(-?\d+\.\d+),\s*(-?\d+\.\d+)/,/^\s*(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)\s*$/];
  for(const re of pats){ const m=re.exec(u); if(m){ const lat=+m[1], lon=+m[2]; if(Math.abs(lat)<=90&&Math.abs(lon)<=180) return {lat:+lat.toFixed(5),lon:+lon.toFixed(5)}; } }
  return null;
}
function goUrl(p){ if(p.link && /^https?:\/\//.test(p.link)) return p.link; if(p.lat!=null) return dirLL(p.lat,p.lon); return null; }

function puntoRow(p,e){
  const at=p.km!=null?e.at(p.km):null, go=goUrl(p);
  return `<div class="spot">
    <div class="spot-km num">${p.km!=null?"km "+String(p.km).replace(".",","):"—"}</div>
    <div class="spot-b"><div class="name">${esc(p.nombre)} <span class="tag">${tipoTxt(p.tipo)}</span></div>
      ${at?`<div class="small">Pasan ≈ <b>${saHM(at.t)}</b></div>`:""}
      ${p.notas?`<div class="small muted">${esc(p.notas)}</div>`:""}
      <div class="acts">${go?`<a class="btn quiet" href="${esc(go)}" target="_blank" rel="noopener">Ir</a>`:`<span class="small muted">Sin enlace</span>`}${p.lat!=null?`<button class="btn quiet" data-act="pi-map" data-id="${p.id}">Ver en mapa</button>`:""}<button class="btn quiet" data-act="pi-edit" data-id="${p.id}">Editar</button></div>
    </div></div>`;
}

export function puntoSheet(s,p={}){
  sheet({
    title:p.id?"Editar punto":"Nuevo punto de interés",
    intro:`${stageLabel(s)} · ${dayLabel(s.date)}.${p.lat!=null&&!p.link?" Ubicación marcada en el mapa.":""}`,
    fields:[
      {id:"nombre",label:"Nombre",value:p.nombre||"",required:true,placeholder:"Avituallamiento 2 · R46"},
      {id:"tipo",label:"Tipo",type:"select",value:p.tipo||"animar",options:TIPOS,half:true},
      {id:"km",label:"Km de carrera",type:"number",value:p.km??"",step:"0.1",inputmode:"decimal",half:true,hint:"Opcional: da la hora de paso"},
      {id:"link",label:"Enlace de Google Maps",value:p.link||"",placeholder:"Pega aquí el enlace de Compartir",hint:"En Google Maps: Compartir → Copiar enlace"},
      {id:"notas",label:"Notas",type:"textarea",rows:2,value:p.notas||"",placeholder:"Aparcar en la pista de tierra, 5 min andando…"}
    ],
    onSubmit:v=>{
      if(v.link && !/^https?:\/\//.test(v.link) && !coordsFromLink(v.link)) throw new Error("El enlace tiene que empezar por https:// (o pega unas coordenadas).");
      const c=coordsFromLink(v.link);
      const km=v.km===""?null:parseFloat(String(v.km).replace(",","."));
      const link=v.link && /^https?:\/\//.test(v.link) ? v.link : (c?dirLL(c.lat,c.lon):"");
      return store.save("spots",{...p,etapa:s.id,nombre:v.nombre,tipo:v.tipo,km,link,notas:v.notas,lat:c?c.lat:(p.lat??null),lon:c?c.lon:(p.lon??null)});
    },
    danger:p.id?{label:"Borrar punto",onClick:()=>store.remove("spots",p.id)}:null
  });
}

function addPunto(s,nav){
  sheet({
    title:"Nuevo punto de interés",
    intro:"La forma más fácil: busca el sitio en Google Maps, toca Compartir → Copiar enlace y pégalo.",
    html:`<div class="btns col">
      <button type="button" class="btn" data-pick="link">Pegar enlace de Google Maps</button>
      <button type="button" class="btn ghost" data-pick="map">Tocar en el mapa</button>
      <button type="button" class="btn ghost" data-pick="gps">Usar mi ubicación</button></div>`,
  });
  document.querySelectorAll("[data-pick]").forEach(b=>b.addEventListener("click",()=>{
    const how=b.dataset.pick;
    document.querySelector("#sheet [data-close]").click();
    if(how==="link") puntoSheet(s,{});
    if(how==="map"){ nav.go("mapa",{mapStage:s.id,_fit:true}); setTimeout(()=>pickPoint("Toca el mapa donde está el punto",pos=>puntoSheet(s,pos)),300); }
    if(how==="gps"){ if(!navigator.geolocation){toast("Este móvil no da la ubicación.");return;} toast("Buscando ubicación…"); navigator.geolocation.getCurrentPosition(p=>puntoSheet(s,{lat:+p.coords.latitude.toFixed(5),lon:+p.coords.longitude.toFixed(5)}),()=>toast("No se pudo obtener la ubicación."),{enableHighAccuracy:true,timeout:10000}); }
  }));
}

function html(s){
  const e=eta(s), ps=puntosOf(s.id);
  return `<div class="panel"><div class="panel-h"><h3>Puntos de interés</h3><button class="btn small" data-act="pi-add">Añadir</button></div>
    <p class="small muted" style="margin:0">La guía de los acompañantes para este día: dónde ir, cómo llegar y a qué hora pasan.</p>
    ${ps.length?ps.map(p=>puntoRow(p,e)).join(""):`<p class="empty">Aún no hay puntos para esta etapa.</p>`}
  </div>`;
}

function onClick(a,b,s,nav){
  if(a==="pi-add") addPunto(s,nav);
  if(a==="pi-edit"){ const p=store.one("spots",b.dataset.id); if(p) puntoSheet(STAGES.find(x=>x.id===p.etapa),p); }
  if(a==="pi-map"){ const p=store.one("spots",b.dataset.id); if(p) nav.go("mapa",{mapStage:p.etapa,_fit:true}); }
}

extraSections.push({html,onClick});
onPopupClick((a,b)=>{ if(a==="pi-edit"){ const p=store.one("spots",b.dataset.id); if(p) puntoSheet(STAGES.find(x=>x.id===p.etapa),p); } });

registerLayer({id:"interes",label:"Puntos",draw(g,ctx){
  const L=window.L;
  const stages= ctx.stage==="todas"?STAGES:STAGES.filter(s=>s.id===ctx.stage);
  for(const s of stages){ const e=eta(s);
    for(const p of puntosOf(s.id)){ if(p.lat==null) continue; const at=p.km!=null?e.at(p.km):null, go=goUrl(p);
      L.marker([p.lat,p.lon],{icon:mapIcon("spot",(ctx.stage==="todas"?s.id+" · ":"")+p.nombre),stage:s.id})
        .bindPopup(`<b>${esc(p.nombre)}</b><br>${stageLabel(s)} · ${tipoTxt(p.tipo)}${p.km!=null?" · km "+p.km:""}${at?`<br>Pasan ≈ <b>${saHM(at.t)}</b>`:""}${p.notas?`<br><small>${esc(p.notas)}</small>`:""}<div class="pop-acts">${go?`<a href="${esc(go)}" target="_blank" rel="noopener">Ir</a>`:""}<button data-act="pi-edit" data-id="${p.id}">Editar</button></div>`).addTo(g);
    }
  }
}});
