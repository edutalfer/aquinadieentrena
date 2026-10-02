import * as store from "../store.js";
import {esc,saToday,dayLabel,stageLabel,splitTitle,starsHtml,dirUrl,hmToMin,minToHM,saMs,saHM} from "../util.js";
import {STAGES,PLACES} from "../data.js";
import {sheet} from "../ui.js";

/* Salida y tiempo objetivo de cada etapa: datos por defecto + lo que hayáis ajustado */
export function stageCfg(s){
  const a=store.one("ajustes",s.id)||{};
  const salida=a.salida||s.salida, objetivo=a.objetivo||s.objetivo;
  const durMin=hmToMin(objetivo)||300;
  return {salida,objetivo,durMin,startMs:saMs(s.date,salida),minPerKm:durMin/s.km,ajustado:!!(a.salida||a.objetivo)};
}

export const extraSections = [];

function ajustesSheet(s){
  const c=stageCfg(s);
  sheet({
    title:"Horario de "+(s.id==="P"?"prólogo":"etapa "+s.id),
    intro:"Con esto se calculan la llegada prevista y la hora de paso por cada punto. La salida oficial se confirma en el Rider Manual.",
    fields:[
      {id:"salida",label:"Hora de salida",type:"time",value:c.salida,required:true,half:true},
      {id:"objetivo",label:"Tiempo objetivo",value:c.objetivo,required:true,placeholder:"5:30",hint:"horas:minutos",half:true,inputmode:"numeric"}
    ],
    onSubmit:v=>{ if(hmToMin(v.objetivo)==null) throw new Error("Escribe el tiempo como 5:30."); return store.save("ajustes",{id:s.id,...v}); }
  });
}

export default {
  init(el,nav){
    el.addEventListener("click",e=>{
      const b=e.target.closest("[data-stage],[data-act]"); if(!b) return;
      if(b.dataset.act){
        const s=STAGES.find(x=>x.id===nav.stage);
        if(b.dataset.act==="ajustes") ajustesSheet(s);
        for(const x of extraSections) x.onClick?.(b.dataset.act,b,s,nav);
        return;
      }
      nav.stage=b.dataset.stage; this.render(el,nav);
    });
  },
  render(el,nav){
    if(!nav.stage){const t=STAGES.find(s=>s.date>=saToday());nav.stage=(t||STAGES[0]).id;}
    const s=STAGES.find(x=>x.id===nav.stage), c=stageCfg(s);
    const rk=f=>1+STAGES.filter(x=>x[f]>s[f]).length;
    let html=`<div class="daystrip" role="tablist" aria-label="Elegir etapa">${STAGES.map(x=>`<button role="tab" aria-selected="${x.id===s.id}" data-stage="${x.id}"><b>${x.id}</b><span>${dayLabel(x.date)}</span></button>`).join("")}</div>`;
    html+=`<div><div class="kicker">${stageLabel(s)} · ${dayLabel(s.date)} marzo</div>${splitTitle(s.name,"stage-h")}</div>`;
    html+=`<div class="stats"><div class="stat"><b class="num">${s.km}</b><span>km</span></div><div class="stat"><b class="num">${s.dplus.toLocaleString("es-ES")}</b><span>m D+</span></div><div class="stat"><b class="num">${Math.round(s.dplus/s.km*10)}</b><span>m D+ cada 10 km</span></div><div class="stat">${starsHtml(s.stars)}<span><br>dureza oficial</span></div></div>`;
    html+=`<div class="panel"><div class="panel-h"><h3>Horario</h3><button class="btn small ghost" data-act="ajustes">Cambiar</button></div>
      <div class="rank-row"><span>Salida</span><b class="num">${c.salida}</b></div>
      <div class="rank-row"><span>Tiempo objetivo</span><b class="num">${c.objetivo} h</b></div>
      <div class="rank-row"><span>Llegada prevista</span><b class="num">${saHM(c.startMs+c.durMin*60e3)}</b></div>
      <p class="small muted" style="margin:0">${c.ajustado?"Ajustado por vosotros.":"Valores de ejemplo: cambiadlos con vuestro objetivo."} Ritmo medio ${(60/c.minPerKm).toFixed(1).replace(".",",")} km/h.</p></div>`;
    html+=`<div class="panel"><div class="small muted">Puesto entre las 8 etapas (1.ª = la más larga o la de más desnivel)</div>
      <div class="rank-row"><span>Distancia</span><span><b class="rank num">${rk("km")}.ª</b> <span class="muted">de 8 · ${s.km} km</span></span></div>
      <div class="rank-row"><span>Desnivel</span><span><b class="rank num">${rk("dplus")}.ª</b> <span class="muted">de 8 · ${s.dplus.toLocaleString("es-ES")} m</span></span></div></div>`;
    html+=`<div class="panel"><h3>Recorrido</h3><div style="font-weight:600">${PLACES[s.from].name}${s.via?" → "+PLACES[s.via].name:""} → ${PLACES[s.to].name}</div><p class="small" style="margin:0">${esc(s.note)}</p>
      <ul class="sectors">${s.sectors.map(x=>`<li class="${x===s.tts?"tts":""}">${esc(x)}${x===s.tts?" · Tough Section":""}</li>`).join("")}</ul>
      <div class="btns"><a class="btn" target="_blank" rel="noopener" href="${dirUrl(PLACES[s.from].q)}">Cómo llegar a la salida</a>${s.from!==s.to?`<a class="btn ghost" target="_blank" rel="noopener" href="${dirUrl(PLACES[s.to].q)}">Cómo llegar a meta</a>`:""}<button class="btn ghost" data-act="ver-mapa">Ver en el mapa</button></div></div>`;
    for(const x of extraSections){ try{ html+=x.html?.(s,c,nav)||""; }catch(err){console.error(err);} }
    html+=`<div class="panel"><h3>Avituallamientos</h3><div class="pending">Los water points con su kilómetro salen con el Rider Manual, normalmente en febrero. Se añadirán aquí y en el mapa con su hora de paso.</div></div>`;
    el.innerHTML=html;
  }
};
extraSections.push({onClick(a,b,s,nav){ if(a==="ver-mapa") nav.go("mapa",{mapStage:s.id,_fit:true}); }});
