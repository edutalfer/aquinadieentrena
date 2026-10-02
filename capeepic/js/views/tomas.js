/* Lista de tomas por etapa y marcas de momentos con hora exacta para la edición */
import * as store from "../store.js";
import {esc,stageLabel} from "../util.js";
import {SHOT_DEFAULTS} from "../data.js";
import {sheet,toast} from "../ui.js";
import {addMediaSection} from "./media.js";

/* ---- Tomas ---- */
const tomasOf=s=>store.all("tomas").filter(t=>t.etapa===s.id).sort((a,b)=>(a.orden??0)-(b.orden??0));

function tomasHtml(s){
  const ts=tomasOf(s), done=ts.filter(t=>t.hecho).length;
  return `<div class="panel"><div class="panel-h"><h3>Lista de tomas</h3><span class="tag num">${done}/${ts.length}</span></div>
    ${ts.length?ts.map(t=>`<div class="chk-row" style="--cols:1"><button class="edit lft" data-act="toma-edit" data-id="${t.id}">${esc(t.texto)}${t.quien?` <span class="small muted">· ${esc(t.quien)}</span>`:""}</button><button class="tick" aria-pressed="${!!t.hecho}" aria-label="Hecha: ${esc(t.texto)}" data-act="toma-tog" data-id="${t.id}">${t.hecho?"✓":""}</button></div>`).join("")
      :`<p class="empty">Sin tomas para ${stageLabel(s).toLowerCase()}.</p><div><button class="btn small ghost" data-act="toma-seed">Crear con las básicas</button></div>`}
    <div class="btns"><button class="btn small" data-act="toma-add">Añadir toma</button></div></div>`;
}
function tomaSheet(s,t={}){
  sheet({title:t.id?"Editar toma":"Nueva toma",fields:[
      {id:"texto",label:"Qué hay que grabar",value:t.texto||"",required:true,placeholder:"Plano cenital en The Cliffhanger"},
      {id:"quien",label:"Quién se encarga",value:t.quien||"",placeholder:"Julia"}],
    onSubmit:v=>store.save("tomas",{...t,etapa:s.id,texto:v.texto,quien:v.quien,orden:t.orden??Date.now(),hecho:!!t.hecho}),
    danger:t.id?{label:"Borrar toma",onClick:()=>store.remove("tomas",t.id)}:null});
}

/* ---- Marcas ---- */
const marcasOf=s=>store.all("marcas").filter(m=>m.etapa===s.id).sort((a,b)=>a.t-b.t);
const hms=t=>new Date(t+2*3600e3).toISOString().slice(11,19);

function marcasHtml(s){
  const ms=marcasOf(s);
  return `<div class="panel live"><div class="panel-h"><h3>Marcas de momentos</h3><span class="tag">${stageLabel(s)}</span></div>
    <p class="small muted" style="margin:0">Toca en el momento: se guarda la hora exacta (con segundos) para encontrar el clip al editar. La nota puedes ponerla después.</p>
    <button class="btn wide big" data-act="marca-add">Marcar momento</button>
    ${ms.length?`<ol class="timeline">${ms.slice().reverse().map(m=>`<li><span class="h">${hms(m.t)}</span><span class="t">${m.nota?esc(m.nota):`<span class="muted">Sin nota</span>`}<small>${esc(m.autor||"")}</small></span><button class="edit" data-act="marca-edit" data-id="${m.id}">Editar</button></li>`).join("")}</ol>
    <div class="btns"><button class="btn small ghost" data-act="marca-copy">Copiar lista para edición</button></div>`:""}
  </div>`;
}
function marcaSheet(m){
  sheet({title:"Marca de las "+hms(m.t),fields:[{id:"nota",label:"Qué ha pasado",type:"textarea",rows:2,value:m.nota||"",placeholder:"Caída en la bajada de Pipeline, cámara 2"}],
    onSubmit:v=>store.save("marcas",{...m,nota:v.nota}),
    danger:{label:"Borrar marca",onClick:()=>store.remove("marcas",m.id)}});
}

async function onClick(a,b,s){
  if(a==="toma-add") tomaSheet(s);
  if(a==="toma-edit"){ const t=store.one("tomas",b.dataset.id); if(t) tomaSheet(s,t); }
  if(a==="toma-tog"){ const t=store.one("tomas",b.dataset.id); if(t) await store.save("tomas",{...t,hecho:!t.hecho}); }
  if(a==="toma-seed"){ let o=0; for(const x of SHOT_DEFAULTS) await store.save("tomas",{etapa:s.id,texto:x,orden:o++,hecho:false}); toast("Tomas básicas añadidas."); }
  if(a==="marca-add"){ const m=await store.save("marcas",{etapa:s.id,t:Date.now(),nota:""}); toast("Marca guardada a las "+hms(m.t)+"."); marcaSheet(m); }
  if(a==="marca-edit"){ const m=store.one("marcas",b.dataset.id); if(m) marcaSheet(m); }
  if(a==="marca-copy"){
    const txt=`${stageLabel(s)} · ${s.name}\n`+marcasOf(s).map(m=>`${hms(m.t)}  ${m.nota||"(sin nota)"}${m.autor?"  · "+m.autor:""}`).join("\n");
    try{ await navigator.clipboard.writeText(txt); toast("Lista copiada. Pégala donde quieras."); }
    catch(e){ sheet({title:"Copia este texto",fields:[{id:"txt",label:"Marcas",type:"textarea",rows:10,value:txt}]}); }
  }
}

addMediaSection({id:"marcas",label:"Marcas",html:marcasHtml,onClick});
addMediaSection({id:"tomas",label:"Tomas",html:tomasHtml,onClick});
