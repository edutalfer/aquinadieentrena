/* Tiempo (Open-Meteo) y luz (amanecer, atardecer y hora dorada, calculados en el móvil) */
import {esc,saHM,saToday,daysBetween,stageLabel} from "../util.js";
import {PLACES,STAGES,DAYS} from "../data.js";
import {extraCards} from "./hoy.js";

/* ---- Sol (método de SunCalc, precisión de un par de minutos) ---- */
export function sunTimes(date,lat,lon){
  const PI=Math.PI, rad=PI/180, e=rad*23.4397, J0=0.0009;
  const d=(Date.parse(date+"T10:00:00Z")/864e5-0.5+2440588)-2451545; // mediodía aproximado en Sudáfrica
  const lw=rad*-lon, phi=rad*lat;
  const n=Math.round(d-J0-lw/(2*PI));
  const ds=J0+lw/(2*PI)+n;
  const M=rad*(357.5291+0.98560028*ds);
  const L=M+rad*(1.9148*Math.sin(M)+0.02*Math.sin(2*M)+0.0003*Math.sin(3*M))+rad*102.9372+PI;
  const dec=Math.asin(Math.sin(e)*Math.sin(L));
  const transit=a=>2451545+a+0.0053*Math.sin(M)-0.0069*Math.sin(2*L);
  const Jnoon=transit(ds);
  const toMs=j=>(j+0.5-2440588)*864e5;
  const at=h=>{const w=Math.acos((Math.sin(h*rad)-Math.sin(phi)*Math.sin(dec))/(Math.cos(phi)*Math.cos(dec)));const Jset=transit(J0+(w+lw)/(2*PI)+n);return [toMs(Jnoon-(Jset-Jnoon)),toMs(Jset)];};
  const [rise,set]=at(-0.833), [g1,g2]=at(6), [c1,c2]=at(-6);
  return {rise,set,dawn:c1,dusk:c2,goldAmEnd:g1,goldPmStart:g2,noon:toMs(Jnoon)};
}

function lightHtml(date,place,label){
  const p=PLACES[place], t=sunTimes(date,p.lat,p.lon);
  return `<div class="light"><div class="light-h">${esc(label||p.town)}</div>
    <div class="light-row"><span>Primera luz</span><b class="num">${saHM(t.dawn)}</b></div>
    <div class="light-row"><span>Amanecer</span><b class="num">${saHM(t.rise)}</b></div>
    <div class="light-row gold"><span>Hora dorada mañana</span><b class="num">${saHM(t.rise)}–${saHM(t.goldAmEnd)}</b></div>
    <div class="light-row gold"><span>Hora dorada tarde</span><b class="num">${saHM(t.goldPmStart)}–${saHM(t.set)}</b></div>
    <div class="light-row"><span>Atardecer</span><b class="num">${saHM(t.set)}</b></div>
    <div class="light-row"><span>Última luz</span><b class="num">${saHM(t.dusk)}</b></div></div>`;
}

/* ---- Tiempo ---- */
const cache={};
function lsGet(k){try{const v=JSON.parse(localStorage.getItem(k)||"null");return v;}catch(e){return null;}}
function lsSet(k,v){try{localStorage.setItem(k,JSON.stringify(v));}catch(e){}}
const DAILY="temperature_2m_max,temperature_2m_min,precipitation_sum,precipitation_probability_max,wind_speed_10m_max,wind_gusts_10m_max,wind_direction_10m_dominant";
const DIRS=["N","NE","E","SE","S","SO","O","NO"];
const dir=deg=>DIRS[Math.round(((deg%360)+360)%360/45)%8];

async function fetchWeather(date,place){
  const p=PLACES[place], ahead=daysBetween(saToday(),date);
  const forecast=ahead>=0&&ahead<=15;
  const qdate=forecast?date:"2026"+date.slice(4);
  const key=`ane-wx-${place}-${date}-${forecast?"f":"h"}`;
  const hit=lsGet(key); if(hit && (!forecast || Date.now()-hit.ts<3*3600e3)) return hit;
  const base=forecast?"https://api.open-meteo.com/v1/forecast":"https://archive-api.open-meteo.com/v1/archive";
  const daily=forecast?DAILY:DAILY.replace("precipitation_probability_max,","");
  const url=`${base}?latitude=${p.lat}&longitude=${p.lon}&daily=${daily}&timezone=Africa%2FJohannesburg&start_date=${qdate}&end_date=${qdate}`;
  const r=await fetch(url); if(!r.ok) throw new Error("HTTP "+r.status);
  const j=await r.json(), d=j.daily;
  const out={ts:Date.now(),forecast,qdate,tmax:d.temperature_2m_max?.[0],tmin:d.temperature_2m_min?.[0],rain:d.precipitation_sum?.[0],prob:d.precipitation_probability_max?.[0],wind:d.wind_speed_10m_max?.[0],gust:d.wind_gusts_10m_max?.[0],wdir:d.wind_direction_10m_dominant?.[0]};
  lsSet(key,out); return out;
}

function wxHtml(w){
  if(!w) return `<p class="small muted" style="margin:0">Cargando el tiempo…</p>`;
  if(w.error) return `<p class="small muted" style="margin:0">No se ha podido cargar el tiempo. Se reintentará en un minuto.</p>`;
  const r=n=>n==null?"—":Math.round(n);
  return `<div class="wx">
    <div class="wx-t num"><b>${r(w.tmax)}°</b><span>${r(w.tmin)}°</span></div>
    <div class="wx-d small"><div>Viento ${r(w.wind)} km/h ${w.wdir!=null?"del "+dir(w.wdir):""} · rachas ${r(w.gust)}</div><div>Lluvia ${w.rain!=null?(Math.round(w.rain*10)/10).toString().replace(".",","):"—"} mm${w.prob!=null?` · ${r(w.prob)} % de probabilidad`:""}</div></div>
  </div><p class="small muted" style="margin:0">${w.forecast?"Previsión de Open-Meteo.":`Aún no hay previsión (sale 16 días antes). Así fue el mismo día de 2026.`}</p>`;
}

function weatherBlock(date,place){
  const k=date+place;
  if(!cache[k] || (cache[k].error && Date.now()-cache[k].at>60e3)){ cache[k]={loading:true}; fetchWeather(date,place).then(w=>{cache[k]=w;}).catch(()=>{cache[k]={error:true,at:Date.now()};}).finally(()=>window.dispatchEvent(new Event("sheetclose"))); }
  return wxHtml(cache[k].loading?null:cache[k]);
}

function placeFor(d){ const st=STAGES.find(s=>s.date===d.date); return st?st.from:d.place; }

extraCards.push({html:(d)=>{
  const pl=placeFor(d), t=sunTimes(d.date,PLACES[pl].lat,PLACES[pl].lon);
  return `<div class="panel"><div class="panel-h"><h3>Tiempo y luz</h3><span class="tag">${esc(PLACES[pl].town)}</span></div>
    ${weatherBlock(d.date,pl)}
    <div class="sunline small num">Amanecer <b>${saHM(t.rise)}</b> · Atardecer <b>${saHM(t.set)}</b> · Hora dorada <b>${saHM(t.goldPmStart)}</b></div></div>`;
}});
