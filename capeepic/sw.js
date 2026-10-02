/* Cape Epic 2027 — funciona sin cobertura.
   App: de la red si hay conexión, de la copia si no.
   Mapas (teselas): de la copia si ya se vieron; se guardan al verlas.
   Los datos (api.php) no pasan por aquí: la app guarda su propia copia. */
const V = "ane-ce2-20261002a";
const APP = [
  "./","./index.html","./manifest.webmanifest","./icon.svg","./icon-180.png","./icon-512.png",
  "./css/app.css?v=20261002a","./js/app.js?v=20261002a",
  "./js/data.js","./js/util.js","./js/store.js","./js/ui.js",
  "./js/views/hoy.js","./js/views/casas.js","./js/views/etapas.js","./js/views/mas.js","./js/views/mapa.js","./js/views/media.js"
];
const TILES = "ane-ce2-tiles";
const TILE_HOSTS = ["tile.openstreetmap.org","tile.opentopomap.org"];

self.addEventListener("install",e=>{
  e.waitUntil(caches.open(V).then(c=>Promise.all(APP.map(u=>c.add(u).catch(()=>{})))).then(()=>self.skipWaiting()));
});
self.addEventListener("activate",e=>{
  e.waitUntil(caches.keys().then(ks=>Promise.all(ks.filter(k=>k!==V&&k!==TILES).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));
});
self.addEventListener("fetch",e=>{
  const req=e.request;
  if(req.method!=="GET") return;
  const url=new URL(req.url);
  if(url.pathname.endsWith("api.php")||url.pathname.endsWith("upload.php")) return;
  if(TILE_HOSTS.some(h=>url.hostname.endsWith(h))){
    e.respondWith(caches.open(TILES).then(async c=>{
      const hit=await c.match(req); if(hit) return hit;
      try{const res=await fetch(req); if(res.ok||res.type==="opaque") c.put(req,res.clone()); return res;}
      catch(err){return new Response("",{status:504});}
    }));
    return;
  }
  const isFont=url.hostname.includes("fonts.googleapis.com")||url.hostname.includes("fonts.gstatic.com");
  if(url.origin!==location.origin&&!isFont) return;
  e.respondWith(
    fetch(req).then(res=>{ if(res.ok||res.type==="opaque"){const cp=res.clone();caches.open(V).then(c=>c.put(req,cp));} return res; })
      .catch(()=>caches.match(req,{ignoreSearch:false}).then(r=>r||caches.match(req,{ignoreSearch:true})).then(r=>r||(req.mode==="navigate"?caches.match("./index.html"):undefined)))
  );
});
