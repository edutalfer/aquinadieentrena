import * as store from "../store.js";
import {esc,saToday,saHM,dayLabel,daysBetween,stageLabel,splitTitle,starsHtml,dirUrl,hmToMin} from "../util.js";
import {STAGES,DAYS,PLACES,planTemplate} from "../data.js";
import {sheet,dayStrip,toast} from "../ui.js";
import {casaHtml,casaSheet,casasForNight} from "./casas.js";

const TOTAL_KM=STAGES.reduce((a,s)=>a+s.km,0), TOTAL_D=STAGES.reduce((a,s)=>a+s.dplus,0);

function headerHtml(nav){
  const today=saToday(), first=STAGES[0].date, last=STAGES.at(-1).date;
  const st=STAGES.find(s=>s.date===today);
  if(today<DAYS[0].date){
    const d=daysBetween(today,first);
    return `<div><div class="kicker">Faltan</div><h1 class="big-title num">${d} días<span class="l2">para el prólogo</span></h1></div>
    <div class="stats"><div class="stat"><b class="num">${TOTAL_KM}</b><span>km</span></div><div class="stat"><b class="num">${TOTAL_D.toLocaleString("es-ES")}</b><span>m de desnivel</span></div><div class="stat"><b>8</b><span>días</span></div><div class="stat"><b>3</b><span>sedes</span></div></div>`;
  }
  if(st) return `<div><div class="kicker">Hoy · ${dayLabel(st.date)} · ${stageLabel(st)}</div>${splitTitle(st.name,"big-title")}</div>
    <div class="stats"><div class="stat"><b class="num">${st.km}</b><span>km</span></div><div class="stat"><b class="num">${st.dplus.toLocaleString("es-ES")}</b><span>m D+</span></div><div class="stat">${starsHtml(st.stars)}<span><br>dureza oficial</span></div></div>
    <div class="btns"><a class="btn" target="_blank" rel="noopener" href="${dirUrl(PLACES[st.to].q)}">Llévame a meta</a><button class="btn ghost" data-act="ficha" data-stage="${st.id}">Ver etapa</button></div>`;
  if(today>last) return `<div><div class="kicker">Hecho</div><h1 class="big-title">Cape Epic<span class="l2">terminada</span></h1></div>`;
  return `<div><div class="kicker">Hoy · ${dayLabel(today)}</div><h1 class="big-title">Día<span class="l2">de viaje</span></h1></div>`;
}

export function planFor(date){return store.all("plan").filter(p=>p.fecha===date).sort((a,b)=>(hmToMin(a.hora)??9999)-(hmToMin(b.hora)??9999));}

function planHtml(date){
  const items=planFor(date), isToday=date===saToday();
  const now=hmToMin(saHM(Date.now()));
  let nextIdx=-1; if(isToday) nextIdx=items.findIndex(p=>(hmToMin(p.hora)??0)>=now);
  const st=STAGES.find(s=>s.date===date);
  const body = items.length
    ? `<ol class="timeline">${items.map((p,i)=>{
        const cls = isToday ? (i===nextIdx?"next":(nextIdx===-1||i<nextIdx?"past":"")) : "";
        return `<li class="${cls}"><span class="h">${esc(p.hora||"—")}</span><span class="t">${esc(p.texto)}${p.quien||p.notas?`<small>${esc([p.quien,p.notas].filter(Boolean).join(" · "))}</small>`:""}</span><button class="edit" data-act="plan-edit" data-id="${p.id}" aria-label="Editar ${esc(p.texto)}">Editar</button></li>`;}).join("")}</ol>`
    : `<p class="empty">Nada apuntado para este día.</p><div class="btns"><button class="btn small ghost" data-act="plan-tpl">Rellenar con el plan tipo${st?" de "+(st.id==="P"?"prólogo":"etapa"):""}</button></div>`;
  return `<div class="panel"><div class="panel-h"><h3>Plan del día</h3><button class="btn small" data-act="plan-add">Añadir</button></div>${body}</div>`;
}

function planSheet(date,p={}){
  sheet({
    title:p.id?"Editar plan":"Añadir al plan",
    intro:`${dayLabel(date)} de marzo. Hora de Sudáfrica.`,
    fields:[
      {id:"hora",label:"Hora",type:"time",value:p.hora||"",half:true},
      {id:"quien",label:"Quién",value:p.quien||"",placeholder:"Todos",half:true},
      {id:"texto",label:"Qué",value:p.texto||"",required:true,placeholder:"Salida hacia meta"},
      {id:"notas",label:"Notas",type:"textarea",rows:2,value:p.notas||"",placeholder:"Dónde, qué llevar…"}
    ],
    onSubmit:v=>store.save("plan",{...p,...v,fecha:date}),
    danger:p.id?{label:"Borrar",onClick:()=>store.remove("plan",p.id)}:null
  });
}

/* Otras vistas añaden tarjetas a "Hoy" (mono, estamos aquí, tiempo…) */
export const extraCards = [];

export default {
  init(el,nav){
    el.addEventListener("click",async e=>{
      const b=e.target.closest("[data-act],[data-day]"); if(!b) return;
      if(b.dataset.day){nav.day=b.dataset.day;this.render(el,nav);return;}
      const a=b.dataset.act;
      if(a==="ficha") nav.go("etapas",{stage:b.dataset.stage});
      if(a==="plan-add") planSheet(nav.day);
      if(a==="plan-edit") planSheet(nav.day,store.one("plan",b.dataset.id)||{});
      if(a==="plan-tpl"){
        const st=STAGES.find(s=>s.date===nav.day);
        const aj=st?{...st,...(store.one("ajustes",st.id)||{})}:null;
        for(const it of planTemplate(aj)) await store.save("plan",{...it,fecha:nav.day});
        toast("Plan tipo añadido. Edítalo a vuestro gusto.");
      }
      if(a==="casa-add") casaSheet({desde:b.dataset.date});
      if(a==="casa-edit") casaSheet(store.one("casas",b.dataset.id)||{});
      for(const c of extraCards) c.onClick?.(a,b,nav);
    });
  },
  render(el,nav){
    const d=DAYS.find(x=>x.date===nav.day)||DAYS[0];
    const st=STAGES.find(s=>s.date===d.date);
    const hs=casasForNight(d.date);
    let html=headerHtml(nav);
    html+=dayStrip(DAYS,d.date);
    html+=`<div class="row"><h2 style="font-size:28px">${dayLabel(d.date)} marzo</h2><span class="tag">${esc(st?stageLabel(st)+" · "+st.name:d.label)}</span></div>`;
    for(const c of extraCards){ try{ html+=c.html?.(d,st,nav)||""; }catch(err){ console.error(err); } }
    html+=planHtml(d.date);
    html+=`<div class="panel"><div class="panel-h"><h3>Esta noche</h3><span class="tag">${esc(d.base)}</span></div>${hs.length?hs.map(casaHtml).join(""):`<p class="empty">Sin casa apuntada.</p>`}${d.base!=="Libre"&&!hs.length?`<div><button class="btn small ghost" data-act="casa-add" data-date="${d.date}">Apuntar casa</button></div>`:""}</div>`;
    el.innerHTML=html;
  }
};
