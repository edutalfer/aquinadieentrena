/* Mapa real (Leaflet). Las capas las aportan los módulos: sedes, casas, puntos de foto, posiciones, tracks. */
import * as store from "../store.js";
import {esc,dirLL,dirUrl,stageLabel,dayLabel} from "../util.js";
import {PLACES,VENUES,STAGES} from "../data.js";
import {toast} from "../ui.js";

const L = window.L;
let map=null, baseLayers={}, me=null, meWatch=null, addMode=null;
const providers=[];      // {id,label,on,draw(group,ctx)}
const groups={};
const clickHandlers=[];  // (act,btn,nav) desde popups

export function registerLayer(p){ providers.push(p); }
export function onPopupClick(f){ clickHandlers.push(f); }
/* Pide al usuario que toque un punto del mapa; llama a cb({lat,lon}) */
export function pickPoint(texto,cb){ addMode={texto,cb}; const b=document.getElementById("map-hint"); if(b){b.textContent=texto;b.hidden=false;} }

function icon(cls,txt){ return L.divIcon({className:"mk "+cls,html:`<span>${esc(txt)}</span>`,iconSize:null,iconAnchor:[14,14],popupAnchor:[0,-12]}); }
export const mapIcon = icon;

function setSizes(){
  const top=document.querySelector(".top").getBoundingClientRect().bottom;
  const nav=document.querySelector("nav.tabs").getBoundingClientRect().height;
  document.documentElement.style.setProperty("--top-h",Math.max(0,top)+"px");
  document.documentElement.style.setProperty("--nav-h",nav+"px");
}

function stageFilter(nav){ return nav.mapStage||"todas"; }

function draw(nav){
  const ctx={stage:stageFilter(nav),nav,icon};
  for(const p of providers){
    if(!groups[p.id]) groups[p.id]=L.layerGroup();
    const g=groups[p.id]; g.clearLayers();
    if(p.on===false){ map.removeLayer(g); continue; }
    try{ p.draw(g,ctx); }catch(e){ console.error(p.id,e); }
    g.addTo(map);
  }
}

function chipsHtml(nav){
  const st=stageFilter(nav);
  return `<div class="map-chips">
    <select id="map-stage" aria-label="Etapa">${[["todas","Todas las etapas"],...STAGES.map(s=>[s.id,stageLabel(s)+" · "+dayLabel(s.date)])].map(([v,t])=>`<option value="${v}"${v===st?" selected":""}>${esc(t)}</option>`).join("")}</select>
    ${providers.map(p=>`<button class="mchip" data-layer="${p.id}" aria-pressed="${p.on!==false}">${esc(p.label)}</button>`).join("")}
  </div>`;
}

function fitStage(nav){
  const st=stageFilter(nav);
  const pts=[];
  if(st==="todas") VENUES.forEach(id=>pts.push([PLACES[id].lat,PLACES[id].lon]));
  else{ const s=STAGES.find(x=>x.id===st); [s.from,s.via,s.to].filter(Boolean).forEach(id=>pts.push([PLACES[id].lat,PLACES[id].lon])); }
  Object.values(groups).forEach(g=>g.eachLayer?.(l=>{ if(l.getLatLng && l.options.stage===st) pts.push(l.getLatLng()); if(l.getBounds && l.options.stage===st){const b=l.getBounds(); pts.push(b.getNorthEast(),b.getSouthWest());} }));
  if(pts.length===1) map.setView(pts[0],12); else map.fitBounds(L.latLngBounds(pts).pad(0.25),{maxZoom:13});
}

/* Guardar las teselas de la vista actual para usar sin cobertura (zoom actual y dos más, máx. ~400) */
async function saveArea(){
  const z0=map.getZoom(), b=map.getBounds(), urls=[];
  const tpl=baseLayers.current._url, subs=["a","b","c"];
  const lon2x=(lon,z)=>Math.floor((lon+180)/360*2**z), lat2y=(lat,z)=>Math.floor((1-Math.log(Math.tan(lat*Math.PI/180)+1/Math.cos(lat*Math.PI/180))/Math.PI)/2*2**z);
  for(let z=z0; z<=Math.min(z0+2,16); z++){
    const x1=lon2x(b.getWest(),z),x2=lon2x(b.getEast(),z),y1=lat2y(b.getNorth(),z),y2=lat2y(b.getSouth(),z);
    for(let x=x1;x<=x2;x++) for(let y=y1;y<=y2;y++){ urls.push(tpl.replace("{s}",subs[(x+y)%3]).replace("{z}",z).replace("{x}",x).replace("{y}",y).replace("{r}","")); }
  }
  if(urls.length>400){ toast(`Zona demasiado grande (${urls.length} trozos). Acerca más el mapa.`); return; }
  let ok=0; toast(`Guardando ${urls.length} trozos de mapa…`);
  for(let i=0;i<urls.length;i+=6){ await Promise.all(urls.slice(i,i+6).map(u=>fetch(u,{mode:"no-cors"}).then(()=>ok++).catch(()=>{}))); }
  toast(`Zona guardada (${ok} trozos). Se verá sin cobertura.`);
}

function locate(){
  if(!navigator.geolocation){ toast("Este móvil no da la ubicación."); return; }
  if(meWatch!=null){ navigator.geolocation.clearWatch(meWatch); meWatch=null; me?.remove(); me=null; document.querySelector('[data-act="locate"]')?.setAttribute("aria-pressed","false"); return; }
  document.querySelector('[data-act="locate"]')?.setAttribute("aria-pressed","true");
  let first=true;
  meWatch=navigator.geolocation.watchPosition(p=>{
    const ll=[p.coords.latitude,p.coords.longitude];
    if(!me) me=L.circleMarker(ll,{radius:8,color:"#fff",weight:3,fillColor:"#2A5CB8",fillOpacity:1}).addTo(map).bindTooltip("Tú");
    else me.setLatLng(ll);
    if(first){ map.setView(ll,Math.max(map.getZoom(),13)); first=false; }
  },e=>{ toast(e.code===1?"Sin permiso de ubicación. Actívalo en los ajustes del navegador.":"No se pudo obtener la ubicación."); locate(); },{enableHighAccuracy:true,maximumAge:15000});
}
export function myPosition(){ return me?me.getLatLng():null; }

export default {
  init(el,nav){
    el.classList.add("full");
    el.innerHTML=`<div class="mapwrap"><div id="map"></div>
      <div class="map-top" id="map-top"></div>
      <div class="map-hint" id="map-hint" hidden></div>
      <div class="map-fab">
        <button class="fab" data-act="locate" aria-pressed="false" title="Mi ubicación" aria-label="Mi ubicación"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><circle cx="12" cy="12" r="4" fill="currentColor"/><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 1v3M12 20v3M1 12h3M20 12h3" stroke="currentColor" stroke-width="2"/></svg></button>
        <button class="fab" data-act="base" title="Cambiar mapa" aria-label="Cambiar entre mapa topográfico y de calles"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M12 3 2 8l10 5 10-5-10-5Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="m2 13 10 5 10-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></button>
        <button class="fab" data-act="save-area" title="Guardar esta zona para usar sin cobertura" aria-label="Guardar esta zona para usar sin cobertura"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M12 3v12m0 0-5-5m5 5 5-5M4 20h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
      </div></div>`;
    setSizes(); window.addEventListener("resize",()=>{setSizes();map&&map.invalidateSize();});
    if(!L){ el.querySelector("#map").innerHTML=`<p class="pending" style="margin:16px">No se ha podido cargar el mapa. Revisa la conexión y vuelve a abrir la pestaña.</p>`; return; }
    map=L.map(el.querySelector("#map"),{zoomControl:false,attributionControl:true}).setView([-33.5,19.05],9);
    L.control.zoom({position:"bottomleft"}).addTo(map);
    baseLayers.topo=L.tileLayer("https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png",{maxZoom:17,attribution:'© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, <a href="https://opentopomap.org">OpenTopoMap</a>'});
    baseLayers.osm=L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:19,attribution:'© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'});
    baseLayers.current=baseLayers.topo.addTo(map);
    map.on("click",e=>{ if(!addMode) return; const m=addMode; addMode=null; document.getElementById("map-hint").hidden=true; m.cb({lat:+e.latlng.lat.toFixed(5),lon:+e.latlng.lng.toFixed(5)}); });
    el.addEventListener("change",e=>{ if(e.target.id==="map-stage"){ nav.mapStage=e.target.value; draw(nav); fitStage(nav); } });
    el.addEventListener("click",e=>{
      const b=e.target.closest("[data-layer],[data-act]"); if(!b) return;
      if(b.dataset.layer){ const p=providers.find(x=>x.id===b.dataset.layer); p.on=p.on===false; b.setAttribute("aria-pressed",p.on!==false); draw(nav); return; }
      const a=b.dataset.act;
      if(a==="locate") return locate();
      if(a==="save-area") return saveArea();
      if(a==="base"){ map.removeLayer(baseLayers.current); baseLayers.current = baseLayers.current===baseLayers.topo?baseLayers.osm:baseLayers.topo; baseLayers.current.addTo(map); toast(baseLayers.current===baseLayers.topo?"Mapa topográfico":"Mapa de calles"); return; }
      for(const f of clickHandlers) f(a,b,nav);
    });
  },
  render(el,nav){
    if(!map) return;
    el.querySelector("#map-top").innerHTML=chipsHtml(nav);
    setSizes();
    setTimeout(()=>{ map.invalidateSize(); draw(nav); if(nav._fit!==false){ fitStage(nav); nav._fit=false; } },60);
  },
  update(el,nav){ if(map) draw(nav); }
};

/* Capa: sedes */
registerLayer({id:"sedes",label:"Sedes",draw(g,ctx){
  let ids=VENUES;
  if(ctx.stage!=="todas"){const s=STAGES.find(x=>x.id===ctx.stage); ids=[s.from,s.via,s.to].filter(Boolean);}
  for(const id of [...new Set(ids)]){ const p=PLACES[id];
    L.marker([p.lat,p.lon],{icon:icon("sede",p.town),stage:ctx.stage}).bindPopup(`<b>${esc(p.name)}</b><br>${esc(p.town)}<div class="pop-acts"><a href="${dirUrl(p.q)}" target="_blank" rel="noopener">Cómo llegar</a></div>`).addTo(g);
  }
}});
/* Capa: casas */
registerLayer({id:"casas",label:"Casas",draw(g,ctx){
  for(const c of store.all("casas")){ if(c.lat==null) continue;
    L.marker([c.lat,c.lon],{icon:icon("casa","Casa")}).bindPopup(`<b>${esc(c.nombre)}</b><br>${dayLabel(c.desde)} → ${dayLabel(c.hasta)}${c.direccion?`<br>${esc(c.direccion)}`:""}<div class="pop-acts"><a href="${dirLL(c.lat,c.lon)}" target="_blank" rel="noopener">Cómo llegar</a></div>`).addTo(g);
  }
}});
