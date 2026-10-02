/* Equipo: quién viene, qué hace, teléfono y coche */
import * as store from "../store.js";
import {esc} from "../util.js";
import {sheet,toast} from "../ui.js";
import {addSection} from "./mas.js";

const ROLES=[["corredor","Corredor"],["acompañante","Acompañante"],["otro","Otro"]];
const rolTxt=r=>(ROLES.find(x=>x[0]===r)||["","—"])[1];
const tidy=t=>String(t||"").replace(/[^\d+]/g,"");

function personSheet(p={}){
  sheet({title:p.id?"Editar persona":"Añadir persona",
    fields:[
      {id:"nombre",label:"Nombre",value:p.nombre||"",required:true,placeholder:"Cristóbal"},
      {id:"rol",label:"Qué hace",type:"select",value:p.rol||"acompañante",options:ROLES,half:true},
      {id:"tel",label:"Teléfono",type:"tel",value:p.tel||"",placeholder:"+34 600 000 000",half:true},
      {id:"coche",label:"Coche que conduce",value:p.coche||"",placeholder:"Toyota Hilux blanca · CA 123-456"},
      {id:"notas",label:"Notas",type:"textarea",rows:2,value:p.notas||"",placeholder:"Talla, alergias no, vuelo de llegada…"}
    ],
    onSubmit:v=>store.save("equipo",{...p,...v}),
    danger:p.id?{label:"Quitar del equipo",onClick:()=>store.remove("equipo",p.id)}:null});
}

function html(){
  const ps=store.all("equipo").sort((a,b)=>ROLES.findIndex(r=>r[0]===a.rol)-ROLES.findIndex(r=>r[0]===b.rol)||a.nombre.localeCompare(b.nombre));
  const coches=ps.filter(p=>p.coche);
  return `<div class="btns"><button class="btn" data-act="eq-add">Añadir persona</button></div>
  <div class="panel"><h3>Equipo</h3>${ps.length?`<div class="list">${ps.map(p=>`<div class="it person">
      <span><b>${esc(p.nombre)}</b> <span class="tag">${rolTxt(p.rol)}</span>${p.coche?`<br><span class="small">Conduce: ${esc(p.coche)}</span>`:""}${p.notas?`<br><span class="small muted">${esc(p.notas)}</span>`:""}</span>
      <span class="acts">${p.tel?`<span class="num">${esc(p.tel)}</span><button class="copy" data-act="eq-copy" data-v="${esc(p.tel)}">Copiar</button><a href="https://wa.me/${tidy(p.tel).replace(/^\+/,"")}" target="_blank" rel="noopener">WhatsApp</a>`:""}<button class="copy" data-act="eq-edit" data-id="${p.id}">Editar</button></span>
    </div>`).join("")}</div>`:`<p class="empty">Añadid a todos los que vais: corredores y acompañantes. Los nombres sirven para "¿Quién eres?", las listas y los gastos.</p>`}</div>
  ${coches.length?`<div class="panel"><h3>Coches</h3><div class="list">${coches.map(p=>`<div class="it"><span>${esc(p.coche)}</span><b>${esc(p.nombre)}</b></div>`).join("")}</div></div>`:""}`;
}

addSection({id:"equipo",label:"Equipo",html,onClick:async(a,b)=>{
  if(a==="eq-add") personSheet();
  if(a==="eq-edit"){ const p=store.one("equipo",b.dataset.id); if(p) personSheet(p); }
  if(a==="eq-copy"){ try{await navigator.clipboard.writeText(b.dataset.v);toast("Teléfono copiado.");}catch(e){toast(b.dataset.v);} }
}});
