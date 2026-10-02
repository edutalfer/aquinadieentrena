/* Foto y vídeo: contenedor de secciones (puntos, mono, tomas, marcas, material, luz) */
import {esc,saToday} from "../util.js";
import {STAGES} from "../data.js";

export const sections=[];
export function addMediaSection(s){ sections.push(s); }
let current=null;

export default {
  init(el,nav){
    el.addEventListener("click",e=>{
      const b=e.target.closest("[data-msec],[data-mstage],[data-act]"); if(!b) return;
      if(b.dataset.msec){current=b.dataset.msec;this.render(el,nav);return;}
      if(b.dataset.mstage){nav.mediaStage=b.dataset.mstage;this.render(el,nav);return;}
      const s=STAGES.find(x=>x.id===nav.mediaStage);
      for(const x of sections) x.onClick?.(b.dataset.act,b,s,nav,()=>this.render(el,nav));
    });
    el.addEventListener("change",e=>{ const s=STAGES.find(x=>x.id===nav.mediaStage); for(const x of sections) x.onChange?.(e,s,nav); });
  },
  render(el,nav){
    if(!nav.mediaStage){const t=STAGES.find(s=>s.date>=saToday());nav.mediaStage=(t||STAGES[0]).id;}
    if(nav.mediaSec){current=nav.mediaSec;nav.mediaSec=null;}
    const sec=sections.find(x=>x.id===current)||sections[0];
    if(!sec){el.innerHTML="";return;}
    const s=STAGES.find(x=>x.id===nav.mediaStage);
    el.innerHTML=`<div class="seg" role="tablist">${sections.map(x=>`<button role="tab" data-msec="${x.id}" aria-selected="${x.id===sec.id}">${esc(x.label)}</button>`).join("")}</div>
      ${sec.perStage!==false?`<div class="daystrip" role="tablist" aria-label="Etapa">${STAGES.map(x=>`<button role="tab" aria-selected="${x.id===s.id}" data-mstage="${x.id}"><b>${x.id}</b><span>${["dom","lun","mar","mié","jue","vie","sáb"][new Date(x.date+"T12:00:00Z").getUTCDay()]} ${new Date(x.date+"T12:00:00Z").getUTCDate()}</span></button>`).join("")}</div>`:""}
      ${sec.html(s,nav)}`;
  }
};
