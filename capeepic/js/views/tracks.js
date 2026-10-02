/* Tracks orientativos (reconstruidos de los mapas oficiales) y avituallamientos con su km oficial */
import * as store from "../store.js";
import {esc,saHM,haversine,dirLL,stageLabel} from "../util.js";
import {STAGES,TTS,waterOf} from "../data.js";
import {extraSections} from "./etapas.js";
import {registerLayer,mapIcon} from "./mapa.js";
import {eta,kmFinder,checkinSheet} from "./carrera.js";

const tracks={};   // id -> {pts:[[lat,lon,ele]], cum:[km], scale}
let loading=null;

export function loadTracks(){
  if(loading) return loading;
  loading=Promise.all(STAGES.map(s=>fetch(`tracks/${s.id}.json`,{cache:"no-cache"}).then(r=>r.ok?r.json():null).catch(()=>null).then(j=>{
    if(!j||!j.puntos?.length) return;
    const pts=j.puntos, cum=[0];
    for(let i=1;i<pts.length;i++) cum.push(cum[i-1]+haversine({lat:pts[i-1][0],lon:pts[i-1][1]},{lat:pts[i][0],lon:pts[i][1]}));
    tracks[s.id]={pts,cum,scale:j.oficial_km/cum.at(-1),aviso:j.aviso};
  }))).then(()=>window.dispatchEvent(new Event("sheetclose")));
  return loading;
}
export const hasTrack=id=>!!tracks[id];

/* Punto del track en un km oficial */
export function posAtKm(id,km){
  const t=tracks[id]; if(!t) return null;
  const d=km/t.scale; let i=t.cum.findIndex(c=>c>=d); if(i<=0) i=Math.max(1,i===-1?t.cum.length-1:1);
  const f=Math.min(1,Math.max(0,(d-t.cum[i-1])/((t.cum[i]-t.cum[i-1])||1)));
  const a=t.pts[i-1], b=t.pts[i];
  return {lat:a[0]+(b[0]-a[0])*f, lon:a[1]+(b[1]-a[1])*f};
}
/* Km oficial aproximado de una posición (si está a menos de 2 km del track) */
kmFinder.fn=(id,pos)=>{
  const t=tracks[id]; if(!t) return null;
  let best=1e9,bi=-1;
  t.pts.forEach((p,i)=>{ const d=(p[0]-pos.lat)**2+((p[1]-pos.lon)*0.84)**2; if(d<best){best=d;bi=i;} });
  if(bi<0||haversine({lat:t.pts[bi][0],lon:t.pts[bi][1]},pos)>2) return null;
  return t.cum[bi]*t.scale;
};

function waterHtml(s){
  const e=eta(s), ws=waterOf(s.id), tts=TTS[s.id];
  const rows=ws.map(w=>{const at=e.at(w.km), p=posAtKm(s.id,w.km);
    return `<div class="spot"><div class="spot-km num">km ${String(w.km).replace(".",",")}</div><div class="spot-b">
      <div class="name">Avituallamiento ${w.n} · ${esc(w.label)}</div>
      <div class="small">${at.pasado?"Pasaron ≈ ":"Pasan ≈ <b>"}${saHM(at.t)}${at.pasado?"":"</b>"}</div>
      <div class="acts">${p?`<a class="btn quiet" href="${dirLL(p.lat.toFixed(5),p.lon.toFixed(5))}" target="_blank" rel="noopener">Cómo llegar (aprox.)</a>`:""}<button class="btn quiet" data-act="wp-check" data-km="${w.km}" data-label="Avituallamiento ${w.n}">Pasamos por aquí</button></div></div></div>`;}).join("");
  const ttsRow=tts?`<div class="spot"><div class="spot-km num">km ${String(tts[0]).replace(".",",")}</div><div class="spot-b"><div class="name">Toyota Tough Section · ${esc(tts[2])}</div><div class="small">Hasta el km ${String(tts[1]).replace(".",",")} · pasan ≈ <b>${saHM(e.at(tts[0]).t)}</b></div></div></div>`:"";
  return `<div class="panel"><div class="panel-h"><h3>Avituallamientos</h3><span class="tag">${ws.length?ws.length+" en carrera":"Sin avituallamientos"}</span></div>
    ${ws.length?`<p class="small muted" style="margin:0">Kilómetros leídos de los perfiles oficiales (±0,5 km). La ubicación sale del trazado orientativo: confirmad el acceso con el Rider Manual.</p>${rows}`:`<p class="small muted" style="margin:0">El prólogo no tiene avituallamientos en el perfil oficial.</p>`}${ttsRow}</div>`;
}

extraSections.push({html:s=>waterHtml(s),onClick:(a,b,s)=>{
  if(a==="wp-check") checkinSheet(s,{km:+b.dataset.km,lugar:b.dataset.label});
}});

registerLayer({id:"tracks",label:"Tracks",draw(g,ctx){
  const L=window.L;
  const list= ctx.stage==="todas"?STAGES:STAGES.filter(s=>s.id===ctx.stage);
  for(const s of list){ const t=tracks[s.id]; if(!t) continue;
    const solo=ctx.stage!=="todas";
    L.polyline(t.pts.map(p=>[p[0],p[1]]),{color:"#191919",weight:solo?7:5,opacity:.55,stage:s.id}).addTo(g);
    L.polyline(t.pts.map(p=>[p[0],p[1]]),{color:"#C9862E",weight:solo?4:3,dashArray:"8 6",stage:s.id})
      .bindPopup(`<b>${stageLabel(s)} · ${esc(s.name)}</b><br>${s.km} km · ${s.dplus.toLocaleString("es-ES")} m<br><small>${esc(t.aviso||"Trazado orientativo, no oficial.")}</small>`).addTo(g);
  }
}});
registerLayer({id:"avit",label:"Avituallamientos",draw(g,ctx){
  const L=window.L;
  const list= ctx.stage==="todas"?STAGES.filter(s=>s.date===new Date(Date.now()+72e5).toISOString().slice(0,10)):STAGES.filter(s=>s.id===ctx.stage);
  for(const s of list){ const e=eta(s);
    for(const w of waterOf(s.id)){ const p=posAtKm(s.id,w.km); if(!p) continue; const at=e.at(w.km);
      L.marker([p.lat,p.lon],{icon:mapIcon("water","A"+w.n+" · "+saHM(at.t)),stage:s.id})
        .bindPopup(`<b>Avituallamiento ${w.n}</b> · ${esc(w.label)}<br>km ${w.km} · ${at.pasado?"pasaron":"pasan"} ≈ <b>${saHM(at.t)}</b><br><small>Ubicación aproximada</small><div class="pop-acts"><a href="${dirLL(p.lat.toFixed(5),p.lon.toFixed(5))}" target="_blank" rel="noopener">Cómo llegar</a></div>`).addTo(g);
    }
  }
}});

loadTracks();
