/* Puntos de foto y vídeo por etapa, con hora estimada de paso y aviso de si da tiempo a llegar al siguiente */
import * as store from "../store.js";
import {esc,saHM,durTxt,haversine,dirLL,stageLabel,dayLabel} from "../util.js";
import {STAGES,PLACES} from "../data.js";
import {sheet,toast} from "../ui.js";
import {addMediaSection} from "./media.js";
import {extraSections} from "./etapas.js";
import {registerLayer,mapIcon,pickPoint,onPopupClick} from "./mapa.js";
import {eta} from "./carrera.js";

export function spotsOf(stageId){ return store.all("spots").filter(p=>p.etapa===stageId).sort((a,b)=>(a.km??1e9)-(b.km??1e9)); }

/* Tiempo en coche estimado entre dos puntos: línea recta × 1,4 a 45 km/h, más 10 min para aparcar y colocarse */
function driveMin(a,b){ return haversine(a,b)*1.4/45*60+10; }

function chain(s){
  const e=eta(s), ps=spotsOf(s.id);
  return ps.map((p,i)=>{
    const at=p.km!=null?e.at(p.km):null;
    let next=null;
    const q=ps[i+1];
    if(q && p.km!=null && q.km!=null && p.lat!=null && q.lat!=null){
      const gap=(q.km-p.km)*e.pace/60e3, drive=driveMin(p,q), margin=gap-drive;
      next={gap,drive,margin,estado:margin>=15?"ok":margin>=0?"justo":"no"};
    }
    return {p,at,next};
  });
}

function spotRow({p,at,next},withStage=false){
  const est = next ? `<div class="chainline ${next.estado}">${next.estado==="ok"?"Da tiempo a llegar al siguiente":next.estado==="justo"?"Justo para llegar al siguiente":"No da tiempo a llegar al siguiente"} · ${durTxt(Math.abs(next.margin))} ${next.margin>=0?"de margen":"tarde"} <span class="muted">(pasan en ${durTxt(next.gap)}, coche ≈ ${durTxt(next.drive)})</span></div>`:"";
  return `<div class="spot">
    <div class="spot-km num">${p.km!=null?"km "+String(p.km).replace(".",","):"—"}</div>
    <div class="spot-b"><div class="name">${esc(p.nombre)}</div>
      <div class="small">${at?(at.pasado?`Pasaron ≈ ${saHM(at.t)}`:`Pasan ≈ <b>${saHM(at.t)}</b>`):"Sin km: no se puede calcular la hora"}${p.publico===false?" · Sin acceso de público":""}</div>
      ${p.acceso?`<div class="small muted">${esc(p.acceso)}</div>`:""}${p.notas?`<div class="small muted">${esc(p.notas)}</div>`:""}
      <div class="acts">${p.lat!=null?`<a class="btn quiet" href="${dirLL(p.lat,p.lon)}" target="_blank" rel="noopener">Cómo llegar</a><button class="btn quiet" data-act="spot-map" data-id="${p.id}">Ver en mapa</button>`:""}<button class="btn quiet" data-act="spot-edit" data-id="${p.id}">Editar</button></div>
    </div></div>${est}`;
}

export function spotSheet(s,p={},nav){
  sheet({
    title:p.id?"Editar punto":"Nuevo punto de foto",
    intro:`${stageLabel(s)} · ${dayLabel(s.date)}. Con el kilómetro se calcula la hora de paso.${p.lat!=null?` Ubicación: ${p.lat}, ${p.lon}.`:" Sin ubicación todavía."}`,
    fields:[
      {id:"nombre",label:"Nombre",value:p.nombre||"",required:true,placeholder:"Curva de Waboomsberg"},
      {id:"km",label:"Kilómetro de carrera",type:"number",value:p.km??"",step:"0.1",inputmode:"decimal",half:true},
      {id:"publico",label:"Se puede ir con público",type:"select",value:p.publico===false?"no":"si",options:[["si","Sí"],["no","No / no sé"]],half:true},
      {id:"acceso",label:"Cómo se llega",value:p.acceso||"",placeholder:"Pista desde la R46, 10 min andando"},
      {id:"notas",label:"Notas",type:"textarea",rows:2,value:p.notas||"",placeholder:"Luz de cara por la mañana, buen plano con dron…"}
    ],
    onSubmit:v=>{
      const km=v.km===""?null:parseFloat(String(v.km).replace(",","."));
      return store.save("spots",{...p,etapa:s.id,nombre:v.nombre,km,publico:v.publico==="si",acceso:v.acceso,notas:v.notas});
    },
    danger:p.id?{label:"Borrar punto",onClick:()=>store.remove("spots",p.id)}:null
  });
}

function addSpot(s,nav){
  sheet({
    title:"Nuevo punto de foto",
    intro:"¿Dónde está el punto?",
    html:`<div class="btns col">
      <button type="button" class="btn" data-pick="map">Tocar en el mapa</button>
      <button type="button" class="btn ghost" data-pick="gps">Usar mi ubicación</button>
      <button type="button" class="btn ghost" data-pick="none">Sin ubicación (solo kilómetro)</button></div>`,
  });
  document.querySelectorAll("[data-pick]").forEach(b=>b.addEventListener("click",()=>{
    const how=b.dataset.pick;
    document.querySelector("#sheet [data-close]").click();
    if(how==="map"){ nav.go("mapa",{mapStage:s.id,_fit:true}); setTimeout(()=>pickPoint("Toca el mapa donde está el punto",pos=>spotSheet(s,pos,nav)),300); }
    if(how==="gps"){ if(!navigator.geolocation){toast("Este móvil no da la ubicación.");return;} toast("Buscando ubicación…"); navigator.geolocation.getCurrentPosition(p=>spotSheet(s,{lat:+p.coords.latitude.toFixed(5),lon:+p.coords.longitude.toFixed(5)},nav),()=>toast("No se pudo obtener la ubicación."),{enableHighAccuracy:true,timeout:10000}); }
    if(how==="none") spotSheet(s,{},nav);
  }));
}

function spotsHtml(s){
  const rows=chain(s), e=eta(s);
  return `<div class="panel"><div class="panel-h"><h3>Puntos de foto</h3><button class="btn small" data-act="spot-add">Añadir punto</button></div>
    <p class="small muted" style="margin:0">Horas con ${e.real?"el ritmo real de hoy":"el tiempo objetivo de la etapa"}. El tiempo en coche es una estimación en línea recta; compruébalo en Google Maps.</p>
    ${rows.length?rows.map(r=>spotRow(r)).join(""):`<p class="empty">Aún no hay puntos para esta etapa. Añadid los sitios donde queréis grabar.</p>`}
    <div class="spot"><div class="spot-km num">km ${s.km}</div><div class="spot-b"><div class="name">Meta · ${esc(PLACES[s.to].name)}</div><div class="small">${e.done?"Llegaron":"Llegan ≈"} <b>${saHM(e.finish)}</b></div></div></div>
  </div>`;
}

function onClick(a,b,s,nav){
  if(a==="spot-add") addSpot(s,nav);
  if(a==="spot-edit"){ const p=store.one("spots",b.dataset.id); if(p) spotSheet(STAGES.find(x=>x.id===p.etapa),p,nav); }
  if(a==="spot-map"){ const p=store.one("spots",b.dataset.id); if(p) nav.go("mapa",{mapStage:p.etapa,_fit:true}); }
}

addMediaSection({id:"puntos",label:"Puntos",html:s=>spotsHtml(s),onClick});
extraSections.push({html:s=>spotsHtml(s),onClick});
onPopupClick((a,b,nav)=>{ if(a==="spot-edit"){ const p=store.one("spots",b.dataset.id); if(p) spotSheet(STAGES.find(x=>x.id===p.etapa),p,nav); } });

registerLayer({id:"fotos",label:"Fotos",draw(g,ctx){
  const L=window.L;
  const stages= ctx.stage==="todas"?STAGES:STAGES.filter(s=>s.id===ctx.stage);
  for(const s of stages){
    for(const {p,at} of chain(s)){ if(p.lat==null) continue;
      L.marker([p.lat,p.lon],{icon:mapIcon("spot",(ctx.stage==="todas"?s.id+" · ":"")+(at?saHM(at.t):p.nombre)),stage:s.id})
        .bindPopup(`<b>${esc(p.nombre)}</b><br>${stageLabel(s)}${p.km!=null?" · km "+p.km:""}${at?`<br>${at.pasado?"Pasaron":"Pasan"} ≈ <b>${saHM(at.t)}</b>`:""}${p.acceso?`<br><small>${esc(p.acceso)}</small>`:""}<div class="pop-acts"><a href="${dirLL(p.lat,p.lon)}" target="_blank" rel="noopener">Cómo llegar</a><button data-act="spot-edit" data-id="${p.id}">Editar</button></div>`).addTo(g);
    }
  }
}});
