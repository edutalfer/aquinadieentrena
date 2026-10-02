/* Horas de paso: se calculan con la hora de salida y el tiempo objetivo de cada etapa.
   (El seguimiento en carrera va por WhatsApp con ubicación en directo y el GPS oficial.) */
import {stageCfg} from "./etapas.js";

/* Conversión posición -> km de etapa (la rellenan los tracks) */
export const kmFinder = {fn:null};

/* Ritmo y llegada prevista de una etapa con el tiempo objetivo */
export function eta(s){
  const cfg=stageCfg(s);
  const start=cfg.startMs, pace=cfg.minPerKm*60e3; // ms por km
  return {start,pace,objPace:pace,real:false,done:false,finish:start+s.km*pace,
    at:km=>({t:start+km*pace,pasado:false})};
}
