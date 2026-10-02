import * as store from "../store.js";
import {esc,dayLabel,dayNum,dayName,addDays,dirUrl} from "../util.js";
import {DAYS} from "../data.js";
import {sheet} from "../ui.js";

export function casasForNight(date){return store.all("casas").filter(c=>c.desde<=date&&date<c.hasta);}

export function casaHtml(c){
  return `<div class="house"><div class="name">${esc(c.nombre)}</div>
    ${c.direccion?`<div class="addr">${esc(c.direccion)}</div>`:""}
    <div class="small muted num">${dayLabel(c.desde)} → ${dayLabel(c.hasta)}${c.checkin?` · check-in ${esc(c.checkin)}`:""}</div>
    ${c.contacto?`<div class="small">Contacto: ${esc(c.contacto)}</div>`:""}
    ${c.notas?`<div class="notes">${esc(c.notas)}</div>`:""}
    <div class="acts">${c.direccion?`<a class="btn quiet" target="_blank" rel="noopener" href="${dirUrl(c.direccion)}">Cómo llegar</a>`:""}<button class="btn quiet" data-act="casa-edit" data-id="${c.id}">Editar</button></div></div>`;
}

export function casaSheet(c={}){
  const desde=c.desde||DAYS[0].date;
  sheet({
    title:c.id?"Editar casa":"Nueva casa",
    fields:[
      {id:"nombre",label:"Nombre",value:c.nombre||"",required:true,placeholder:"Casa Ceres – Airbnb"},
      {id:"direccion",label:"Dirección",value:c.direccion||"",placeholder:"Calle, pueblo",hint:"Se usa para el botón Cómo llegar y para el mapa"},
      {id:"desde",label:"Entrada",type:"date",value:desde,required:true,half:true},
      {id:"hasta",label:"Salida",type:"date",value:c.hasta||addDays(desde,1),required:true,half:true},
      {id:"checkin",label:"Check-in",value:c.checkin||"",placeholder:"16:00",half:true},
      {id:"contacto",label:"Contacto",value:c.contacto||"",placeholder:"Anfitrión, teléfono",half:true},
      {id:"notas",label:"Notas",type:"textarea",value:c.notas||"",placeholder:"Código de puerta, parking, wifi, quién duerme"}
    ],
    onSubmit:async v=>{
      if(v.hasta<=v.desde) throw new Error("La salida tiene que ser después de la entrada.");
      const geo = (v.direccion && v.direccion!==c.direccion) ? await geocode(v.direccion) : (c.lat?{lat:c.lat,lon:c.lon}:null);
      return store.save("casas",{...c,...v,lat:geo?.lat??null,lon:geo?.lon??null});
    },
    danger:c.id?{label:"Borrar casa",onClick:()=>store.remove("casas",c.id)}:null
  });
}

/* Coordenadas de una dirección (OpenStreetMap). Si falla, la casa se guarda igual sin punto en el mapa. */
export async function geocode(q){
  try{
    const r=await fetch("https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=za&q="+encodeURIComponent(q),{headers:{"Accept-Language":"es"}});
    const j=await r.json(); if(j[0]) return {lat:+j[0].lat,lon:+j[0].lon};
  }catch(e){}
  return null;
}

export function casasPanel(){
  return `<div class="btns"><button class="btn" data-act="casa-add" data-date="${DAYS[0].date}">Añadir casa</button></div>
  <div class="panel">${DAYS.map(n=>{const hs=casasForNight(n.date);
    return `<div class="night"><div class="d"><b>${dayNum(n.date)}</b><span>${dayName(n.date)}</span></div>
      <div class="house"><span class="tag">${esc(n.label)} · ${esc(n.base)}</span>
      ${hs.length?hs.map(casaHtml).join(""):`<span class="empty">Sin casa</span>${n.base!=="Libre"?`<div><button class="btn quiet" data-act="casa-add" data-date="${n.date}">Apuntar casa</button></div>`:""}`}</div></div>`;}).join("")}</div>`;
}
