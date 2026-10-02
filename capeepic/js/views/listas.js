/* Checklists diarias: material de corredores (una casilla por corredor) y material audiovisual */
import * as store from "../store.js";
import {esc,saToday,dayLabel} from "../util.js";
import {DAYS,CHECK_DEFAULTS} from "../data.js";
import {sheet,dayStrip,toast} from "../ui.js";
import {addSection} from "./mas.js";
import {extraCards} from "./hoy.js";

let day=(()=>{const t=saToday();return DAYS.some(d=>d.date===t)?t:DAYS[0].date;})();

export function riders(){
  const r=store.all("equipo").filter(p=>p.rol==="corredor").map(p=>p.nombre);
  return r.length?r:["Eduardo","Cristóbal"];
}
function items(tipo){ return store.all("listas").filter(i=>i.tipo===tipo).sort((a,b)=>(a.orden??0)-(b.orden??0)); }
const hid=(date,item,who)=>`${date}_${item}_${(who||"todos").replace(/[^A-Za-z0-9]/g,"")}`;
function isDone(date,item,who){ return !!store.one("hechos",hid(date,item,who)); }

async function toggle(date,item,who){
  const id=hid(date,item,who);
  if(store.one("hechos",id)) await store.remove("hechos",id);
  else await store.save("hechos",{id,fecha:date,item,quien:who||"",hora:Date.now()});
}

function listHtml(tipo,title){
  const its=items(tipo), cols=tipo==="corredor"?riders():[null];
  const total=its.length*cols.length, done=its.reduce((n,i)=>n+cols.filter(w=>isDone(day,i.id,w)).length,0);
  return dayStrip(DAYS,day,"data-lday")+`<div class="panel">
    <div class="panel-h"><h3>${title}</h3><span class="tag num">${dayLabel(day)} · ${done}/${total}</span></div>
    ${its.length?`<div class="chk-head" style="--cols:${cols.length}"><span></span>${cols.map(w=>`<span>${esc(w||"Hecho")}</span>`).join("")}</div>
    ${its.map(i=>`<div class="chk-row" style="--cols:${cols.length}"><button class="edit lft" data-act="li-edit" data-id="${i.id}">${esc(i.texto)}</button>${cols.map(w=>{const on=isDone(day,i.id,w);return `<button class="tick" aria-pressed="${on}" aria-label="${esc(i.texto)}${w?" · "+esc(w):""}" data-act="li-tog" data-item="${i.id}" data-who="${esc(w||"")}">${on?"✓":""}</button>`;}).join("")}</div>`).join("")}`
    :`<p class="empty">La lista está vacía.</p><div><button class="btn small ghost" data-act="li-seed" data-tipo="${tipo}">Crear con lo básico</button></div>`}
    <div class="btns"><button class="btn small" data-act="li-add" data-tipo="${tipo}">Añadir cosa</button>${done?`<button class="btn small ghost" data-act="li-reset" data-tipo="${tipo}">Desmarcar todo hoy</button>`:""}</div>
    <p class="small muted" style="margin:0">Se marca por día: cada mañana empieza vacía.</p>
  </div>`;
}

function itemSheet(tipo,it={}){
  sheet({title:it.id?"Editar":"Añadir a la lista",
    fields:[{id:"texto",label:"Qué",value:it.texto||"",required:true,placeholder:tipo==="media"?"Filtro ND":"Bomba de CO₂"}],
    onSubmit:v=>store.save("listas",{...it,tipo,texto:v.texto,orden:it.orden??Date.now()}),
    danger:it.id?{label:"Quitar de la lista",onClick:()=>store.remove("listas",it.id)}:null});
}

async function onClick(a,b){
  if(!a) return;
  if(b.dataset.lday){ day=b.dataset.lday; return "rerender"; }
  if(a==="li-tog") await toggle(day,b.dataset.item,b.dataset.who||null);
  if(a==="li-add") itemSheet(b.dataset.tipo);
  if(a==="li-edit"){ const it=store.one("listas",b.dataset.id); if(it) itemSheet(it.tipo,it); }
  if(a==="li-seed"){ let o=0; for(const t of CHECK_DEFAULTS[b.dataset.tipo]) await store.save("listas",{tipo:b.dataset.tipo,texto:t,orden:o++}); toast("Lista creada. Ajústala a vuestro gusto."); }
  if(a==="li-reset"){ for(const h of store.all("hechos").filter(h=>h.fecha===day)){ const it=store.one("listas",h.item); if(it?.tipo===b.dataset.tipo) await store.remove("hechos",h.id); } }
}

/* Los botones de día no llevan data-act: se resuelven aquí */
document.addEventListener("click",e=>{ const b=e.target.closest("[data-lday]"); if(!b) return; day=b.dataset.lday; window.__nav?.go(window.__nav.tab); },true);

addSection({id:"material",label:"Material",html:()=>listHtml("corredor","Material de carrera"),onClick});

/* Resumen en Hoy */
extraCards.push({html:(d,st)=>{
  const its=items("corredor"); if(!its.length||!st) return "";
  const cols=riders(), total=its.length*cols.length, done=its.reduce((n,i)=>n+cols.filter(w=>isDone(d.date,i.id,w)).length,0);
  return `<button class="panel strip-link" data-act="go-material" data-date="${d.date}"><span><b>Material de carrera</b><br><span class="small muted">${done===total?"Todo listo":"Faltan "+(total-done)+" casillas"}</span></span><span class="tag num">${done}/${total}</span></button>`;
},onClick:(a,b,nav)=>{ if(a==="go-material"){ day=b.dataset.date; nav.go("mas",{masSec:"material"}); } }});
