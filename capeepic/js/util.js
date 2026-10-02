export const $ = (s,el=document)=>el.querySelector(s);
export const $$ = (s,el=document)=>[...el.querySelectorAll(s)];
export const esc = s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
export const mapsUrl = p=>"https://www.google.com/maps/search/?api=1&query="+encodeURIComponent(p);
export const dirUrl = p=>"https://www.google.com/maps/dir/?api=1&destination="+encodeURIComponent(p);
export const dirLL = (lat,lon)=>`https://www.google.com/maps/dir/?api=1&destination=${lat},${lon}`;
const WD=["dom","lun","mar","mié","jue","vie","sáb"];

/* Todo se expresa en hora de Sudáfrica (UTC+2, sin cambio de horario) */
export const SA_OFFSET = 2*3600e3;
export function saToday(){return new Date(Date.now()+SA_OFFSET).toISOString().slice(0,10);}
export function saHM(ms){const d=new Date(ms+SA_OFFSET);return d.toISOString().slice(11,16);}
/* Fecha "YYYY-MM-DD" + "HH:MM" en hora SA -> milisegundos UTC */
export function saMs(date,hm){return Date.parse(date+"T"+hm+":00Z")-SA_OFFSET;}
export function dayLabel(iso){const d=new Date(iso+"T12:00:00Z");return WD[d.getUTCDay()]+" "+d.getUTCDate();}
export function dayNum(iso){return new Date(iso+"T12:00:00Z").getUTCDate();}
export function dayName(iso){return WD[new Date(iso+"T12:00:00Z").getUTCDay()];}
export function daysBetween(a,b){return Math.round((Date.parse(b+"T00:00:00Z")-Date.parse(a+"T00:00:00Z"))/864e5);}
export function addDays(iso,n){const d=new Date(iso+"T12:00:00Z");d.setUTCDate(d.getUTCDate()+n);return d.toISOString().slice(0,10);}
export function hmToMin(hm){const m=/^(\d{1,2}):(\d{2})$/.exec(String(hm||"").trim());return m?(+m[1])*60+(+m[2]):null;}
export function minToHM(min){min=Math.round(min);const h=Math.floor(min/60),m=min%60;return h+":"+String(m).padStart(2,"0");}
export function durTxt(min){min=Math.round(min);if(min<60)return min+" min";const h=Math.floor(min/60),m=min%60;return h+" h"+(m?" "+String(m).padStart(2,"0"):"");}
export function uid(){return Date.now().toString(36)+Math.random().toString(36).slice(2,8);}
export function eur(n){return (Math.round(n*100)/100).toLocaleString("es-ES",{minimumFractionDigits:2,maximumFractionDigits:2})+" €";}
export function haversine(a,b){const R=6371,r=x=>x*Math.PI/180;const dLat=r(b.lat-a.lat),dLon=r(b.lon-a.lon);const h=Math.sin(dLat/2)**2+Math.cos(r(a.lat))*Math.cos(r(b.lat))*Math.sin(dLon/2)**2;return 2*R*Math.asin(Math.sqrt(h));}
export function starsHtml(n){const f=Math.floor(n),half=n-f>=.5;return `<span class="stars" aria-label="${n} de 5 estrellas">${"★".repeat(f)}${half?"½":""}<span class="off">${"★".repeat(5-f-(half?1:0))}</span></span>`;}
export function stageLabel(s){return s.id==="P"?"Prólogo":"Etapa "+s.id;}
export function splitTitle(name,cls){const w=name.split(" "),cut=Math.ceil(w.length/2);const a=w.slice(0,cut).join(" "),b=w.slice(cut).join(" ");return `<h1 class="${cls}">${esc(a)}${b?`<span class="l2">${esc(b)}</span>`:""}</h1>`;}
