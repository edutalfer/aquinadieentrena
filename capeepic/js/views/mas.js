import * as store from "../store.js";
import {esc,mapsUrl} from "../util.js";
import {PLACES,VENUES,TRANSFERS} from "../data.js";
import {casasPanel,casaSheet} from "./casas.js";

/* Subsecciones de "Más". Otros módulos registran las suyas aquí. */
export const sections = [
  {id:"casas",label:"Casas",html:()=>casasPanel(),onClick(a,b){ if(a==="casa-add") casaSheet({desde:b.dataset.date}); if(a==="casa-edit") casaSheet(store.one("casas",b.dataset.id)||{}); }},
];
const infoSection = {id:"info",label:"Info",html:()=>`
  <div class="panel"><h3>Traslados en coche</h3><div class="list">${TRANSFERS.map(t=>`<div class="it"><span>${t.from==="imbuko"?"Casa (Wellington)":PLACES[t.from].town} → ${t.to==="imbuko"?"Casa (Wellington)":PLACES[t.to].town}${t.nota?`<br><span class="small muted">${t.nota}</span>`:""}</span><b class="num">${t.t}</b></div>`).join("")}</div><div class="small muted">Tiempos aproximados sin tráfico. Se conduce por la izquierda. Bainskloof es un puerto estrecho: con niebla o de noche, mejor ir con margen.</div></div>
  <div class="panel"><h3>Sedes</h3><div class="list">${VENUES.map(id=>`<div class="it"><span>${PLACES[id].name}<br><span class="small muted">${PLACES[id].town}</span></span><a target="_blank" rel="noopener" href="${mapsUrl(PLACES[id].q)}">Abrir en Maps</a></div>`).join("")}</div></div>
  <div class="panel"><h3>Emergencias en Sudáfrica</h3><div class="list">
    <div class="it"><span>Desde el móvil</span><span><b>112</b> <button class="copy" data-act="copy" data-v="112">Copiar</button></span></div>
    <div class="it"><span>Ambulancia</span><span><b>10177</b> <button class="copy" data-act="copy" data-v="10177">Copiar</button></span></div>
    <div class="it"><span>Policía</span><span><b>10111</b> <button class="copy" data-act="copy" data-v="10111">Copiar</button></span></div></div>
    <div class="small muted">Los teléfonos de la organización y del equipo médico de carrera vienen en el Rider Manual.</div></div>
  <div class="panel"><h3>Hora</h3><p class="small" style="margin:0">Toda la app usa la hora de Sudáfrica (UTC+2 todo el año). Durante la carrera va 1 hora por delante de España, hasta el domingo 28, cuando España cambia al horario de verano y pasáis a tener la misma hora.</p></div>
  <div class="panel"><h3>Enlaces oficiales</h3><div class="list">
    <div class="it"><span>Ruta 2027</span><a target="_blank" rel="noopener" href="https://www.epic-series.com/races/capeepic/route">epic-series.com</a></div>
    <div class="it"><span>Rider Manual</span><a target="_blank" rel="noopener" href="https://www.epic-series.com/races/capeepic/manual">epic-series.com</a></div>
    <div class="it"><span>Guía del espectador</span><a target="_blank" rel="noopener" href="https://www.epic-series.com/races/capeepic/supporters">epic-series.com</a></div>
    <div class="it"><span>Horarios del evento</span><a target="_blank" rel="noopener" href="https://www.epic-series.com/races/capeepic/schedule">epic-series.com</a></div></div></div>`};

let current="casas";
export default {
  init(el,nav){
    el.addEventListener("click",async e=>{
      const b=e.target.closest("[data-sec],[data-act]"); if(!b) return;
      if(b.dataset.sec){current=b.dataset.sec;this.render(el,nav);return;}
      if(b.dataset.act==="copy"){const v=b.dataset.v;try{await navigator.clipboard.writeText(v);b.textContent="Copiado";}catch(err){b.textContent=v;}setTimeout(()=>b.textContent="Copiar",1500);return;}
      for(const s of [...sections,infoSection]) s.onClick?.(b.dataset.act,b,nav);
    });
  },
  render(el,nav){
    const all=[...sections,infoSection];
    if(nav.masSec){current=nav.masSec;nav.masSec=null;}
    const sec=all.find(s=>s.id===current)||all[0];
    el.innerHTML=`<div class="seg" role="tablist">${all.map(s=>`<button role="tab" data-sec="${s.id}" aria-selected="${s.id===sec.id}">${esc(s.label)}</button>`).join("")}</div>${sec.html(nav)}`;
  }
};
export function addSection(s,before="info"){ sections.push(s); }
