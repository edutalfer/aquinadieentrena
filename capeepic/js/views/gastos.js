/* Gastos compartidos: quién pagó qué, entre quién se reparte y quién debe a quién */
import * as store from "../store.js";
import {esc,eur,dayLabel,saToday} from "../util.js";
import {sheet,toast} from "../ui.js";
import {addSection} from "./mas.js";

const people=()=>{ const n=store.all("equipo").map(p=>p.nombre).filter(Boolean); return n.length?n.sort():["Eduardo","Cristóbal"]; };
const rate=()=>{ const r=parseFloat(String(store.one("ajustes","fx")?.zar_eur||"").replace(",",".")); return r>0?r:0.05; };
const toEur=g=>g.moneda==="ZAR"?g.importe*rate():g.importe;

function balances(){
  const ps=people(), bal=Object.fromEntries(ps.map(p=>[p,0]));
  for(const g of store.all("gastos")){
    const amt=toEur(g), entre=(g.entre&&g.entre.length?g.entre:ps);
    if(!(g.pagador in bal)) bal[g.pagador]=0;
    bal[g.pagador]+=amt;
    for(const p of entre){ if(!(p in bal)) bal[p]=0; bal[p]-=amt/entre.length; }
  }
  /* Pagos mínimos para saldar */
  const deb=Object.entries(bal).filter(([,v])=>v<-0.005).map(([p,v])=>[p,-v]).sort((a,b)=>b[1]-a[1]);
  const cre=Object.entries(bal).filter(([,v])=>v>0.005).sort((a,b)=>b[1]-a[1]);
  const pagos=[]; let i=0,j=0;
  while(i<deb.length&&j<cre.length){ const x=Math.min(deb[i][1],cre[j][1]); pagos.push([deb[i][0],cre[j][0],x]); deb[i][1]-=x; cre[j][1]-=x; if(deb[i][1]<0.005)i++; if(cre[j][1]<0.005)j++; }
  return {bal,pagos};
}

function gastoSheet(g={}){
  const ps=people();
  sheet({title:g.id?"Editar gasto":"Nuevo gasto",fields:[
      {id:"concepto",label:"Concepto",value:g.concepto||"",required:true,placeholder:"Gasolina Ceres"},
      {id:"importe",label:"Importe",type:"number",step:"0.01",inputmode:"decimal",value:g.importe??"",required:true,half:true},
      {id:"moneda",label:"Moneda",type:"select",value:g.moneda||"ZAR",options:[["ZAR","Rand (R)"],["EUR","Euro (€)"]],half:true},
      {id:"pagador",label:"Pagó",type:"select",value:g.pagador||store.me()||ps[0],options:[...new Set([...ps,g.pagador].filter(Boolean))].map(p=>[p,p])},
      {id:"entre",label:"Se reparte entre",type:"checks",value:g.entre&&g.entre.length?g.entre:ps,options:ps.map(p=>[p,p])},
      {id:"fecha",label:"Fecha",type:"date",value:g.fecha||saToday(),half:true}],
    onSubmit:v=>{
      const importe=parseFloat(String(v.importe).replace(",","."));
      if(!(importe>0)) throw new Error("El importe tiene que ser mayor que cero.");
      if(!v.entre.length) throw new Error("Marca al menos una persona para repartir.");
      return store.save("gastos",{...g,concepto:v.concepto,importe,moneda:v.moneda,pagador:v.pagador,entre:v.entre,fecha:v.fecha});
    },
    danger:g.id?{label:"Borrar gasto",onClick:()=>store.remove("gastos",g.id)}:null});
}
function fxSheet(){
  sheet({title:"Cambio rand → euro",intro:"Se usa para pasar a euros los gastos en rand. Míralo en tu banco el día que paguéis.",
    fields:[{id:"zar_eur",label:"1 rand equivale a… euros",type:"number",step:"0.0001",inputmode:"decimal",value:rate(),required:true}],
    onSubmit:v=>store.save("ajustes",{id:"fx",zar_eur:String(v.zar_eur)})});
}

function html(){
  const gs=store.all("gastos").sort((a,b)=>(b.fecha||"").localeCompare(a.fecha||"")||(b.actualizado||"").localeCompare(a.actualizado||""));
  const total=gs.reduce((n,g)=>n+toEur(g),0), {bal,pagos}=balances();
  return `<div class="btns"><button class="btn" data-act="g-add">Añadir gasto</button><button class="btn ghost small" data-act="g-fx">1 R = ${String(rate()).replace(".",",")} €</button></div>
  <div class="panel"><div class="panel-h"><h3>Cuentas</h3><span class="tag num">Total ${eur(total)}</span></div>
    ${gs.length?`<div class="list">${Object.entries(bal).map(([p,v])=>`<div class="it"><span>${esc(p)}</span><b class="num" style="color:${v>=0?"var(--ok)":"var(--danger)"}">${v>=0?"+":"−"}${eur(Math.abs(v))}</b></div>`).join("")}</div>
    ${pagos.length?`<h3 style="font-size:16px;margin-top:6px">Para quedar en paz</h3><div class="list">${pagos.map(([a,b,x])=>`<div class="it"><span>${esc(a)} paga a ${esc(b)}</span><b class="num">${eur(x)}</b></div>`).join("")}</div>`:`<p class="small" style="margin:0">Estáis en paz.</p>`}`
    :`<p class="empty">Sin gastos todavía. Lo que apuntéis aquí se reparte solo.</p>`}
  </div>
  ${gs.length?`<div class="panel"><h3>Gastos</h3><div class="list">${gs.map(g=>`<div class="it"><span><b>${esc(g.concepto)}</b><br><span class="small muted">${g.fecha?dayLabel(g.fecha)+" · ":""}pagó ${esc(g.pagador)} · entre ${(g.entre||[]).length||people().length}</span></span><span class="acts"><span class="num">${g.moneda==="ZAR"?"R "+g.importe.toLocaleString("es-ES"):eur(g.importe)}</span><button class="copy" data-act="g-edit" data-id="${g.id}">Editar</button></span></div>`).join("")}</div></div>`:""}`;
}

addSection({id:"gastos",label:"Gastos",html,onClick:(a,b)=>{
  if(a==="g-add") gastoSheet();
  if(a==="g-edit"){ const g=store.one("gastos",b.dataset.id); if(g) gastoSheet(g); }
  if(a==="g-fx") fxSheet();
}});
