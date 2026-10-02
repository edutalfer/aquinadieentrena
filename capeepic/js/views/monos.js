/* Mono del día: cada etapa, un animal sudafricano. Foto + colores para reconoceros entre la gente */
import * as store from "../store.js";
import {esc,dayLabel,stageLabel} from "../util.js";
import {STAGES} from "../data.js";
import {sheet,toast} from "../ui.js";
import {extraCards} from "./hoy.js";
import {addMediaSection} from "./media.js";

const monoOf = s => store.one("monos",s.date);

function monoCard(s,compact){
  const m=monoOf(s);
  if(!m) return `<div class="panel mono-card"><div class="panel-h"><h3>Mono del día</h3><span class="tag">${stageLabel(s)}</span></div><p class="empty">Aún no está elegido el mono de este día.</p><div><button class="btn small" data-act="mono-edit" data-date="${s.date}">Añadir mono</button></div></div>`;
  return `<div class="panel mono-card">
    <div class="panel-h"><h3>Mono del día</h3><span class="tag">${stageLabel(s)}</span></div>
    ${m.foto?`<img class="mono-img" src="${esc(m.foto)}" alt="Mono ${esc(m.animal)}" loading="lazy">`:""}
    <div class="mono-name">${esc(m.animal||"Sin nombre")}</div>
    ${m.colores?`<div class="small"><b>Para reconocernos:</b> ${esc(m.colores)}</div>`:""}
    ${m.notas&&!compact?`<div class="small muted">${esc(m.notas)}</div>`:""}
    <div><button class="btn quiet" data-act="mono-edit" data-date="${s.date}">Editar</button></div>
  </div>`;
}

export function monoSheet(date){
  const s=STAGES.find(x=>x.date===date), m=store.one("monos",date)||{};
  sheet({
    title:"Mono · "+stageLabel(s),
    intro:dayLabel(date)+" de marzo. La foto se reduce y se guarda en el servidor; hace falta cobertura para subirla.",
    fields:[
      {id:"animal",label:"Animal",value:m.animal||"",required:true,placeholder:"Kudu"},
      {id:"colores",label:"Cómo reconoceros",value:m.colores||"",placeholder:"Marrón con rayas blancas, casco negro"},
      {id:"foto",label:m.foto?"Cambiar foto":"Foto del mono",type:"file"},
      {id:"notas",label:"Notas para el contenido",type:"textarea",rows:2,value:m.notas||"",placeholder:"Historia del animal, frase para el vlog…"}
    ],
    onSubmit:async v=>{
      let foto=m.foto||"";
      if(v.foto){ try{ foto=await store.upload(v.foto); }catch(e){ throw new Error("No se ha podido subir la foto: "+e.message); } }
      await store.save("monos",{...m,id:date,animal:v.animal,colores:v.colores,notas:v.notas,foto});
      toast("Mono guardado.");
    },
    danger:m.id?{label:"Borrar mono",onClick:()=>store.remove("monos",date)}:null
  });
}

function gallery(){
  return `<div class="panel"><h3>Los 8 monos</h3><div class="mono-grid">${STAGES.map(s=>{const m=monoOf(s);
    return `<button class="mono-thumb" data-act="mono-edit" data-date="${s.date}">${m?.foto?`<img src="${esc(m.foto)}" alt="" loading="lazy">`:`<span class="ph">${s.id}</span>`}<span class="cap"><b>${s.id}</b> ${esc(m?.animal||"Por elegir")}</span></button>`;}).join("")}</div></div>`;
}

const click=(a,b)=>{ if(a==="mono-edit") monoSheet(b.dataset.date); };
extraCards.push({html:(d,st)=>st?monoCard(st,true):"",onClick:click});
addMediaSection({id:"mono",label:"Mono del día",html:s=>monoCard(s,false)+gallery(),onClick:click});
