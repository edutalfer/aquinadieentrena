/* Avituallamientos y Toyota Tough Section de cada etapa, con el km de los perfiles oficiales y la hora de paso.
   (Sin ubicación en el mapa hasta tener los GPX oficiales.) */
import {esc,saHM} from "../util.js";
import {TTS,waterOf} from "../data.js";
import {extraSections} from "./etapas.js";
import {eta} from "./carrera.js";

function waterHtml(s){
  const e=eta(s), ws=waterOf(s.id), tts=TTS[s.id];
  const row=(km,name,sub)=>`<div class="spot"><div class="spot-km num">km ${String(km).replace(".",",")}</div><div class="spot-b"><div class="name">${name}</div><div class="small">${sub}</div></div></div>`;
  const rows=ws.map(w=>row(w.km,`Avituallamiento ${w.n} · ${esc(w.label)}`,`Pasan ≈ <b>${saHM(e.at(w.km).t)}</b>`)).join("");
  const ttsRow=tts?row(tts[0],`Toyota Tough Section · ${esc(tts[2])}`,`Hasta el km ${String(tts[1]).replace(".",",")} · pasan ≈ <b>${saHM(e.at(tts[0]).t)}</b>`):"";
  return `<div class="panel"><div class="panel-h"><h3>Avituallamientos</h3><span class="tag">${ws.length?ws.length+" en carrera":"Sin avituallamientos"}</span></div>
    ${ws.length?`<p class="small muted" style="margin:0">Km leídos de los perfiles oficiales (±0,5 km). Para ir a uno, guardadlo como punto de interés con su enlace de Google Maps cuando salga el Rider Manual.</p>${rows}`:`<p class="small muted" style="margin:0">El prólogo no tiene avituallamientos en el perfil oficial.</p>`}${ttsRow}</div>`;
}

extraSections.push({html:s=>waterHtml(s)});
