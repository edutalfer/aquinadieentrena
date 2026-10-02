import {$,esc} from "./util.js";

/* Hoja inferior con formulario.
   fields: [{id,label,type:'text'|'time'|'date'|'number'|'textarea'|'select'|'checkbox'|'checks'|'file',value,placeholder,options:[[v,t]],required,step,half}] */
export function sheet({title,intro="",fields=[],submit="Guardar",onSubmit,danger,html=""}){
  const root = $("#sheet");
  const f = fields.map(fl=>{
    const id="sf-"+fl.id, v=fl.value??"";
    const req=fl.required?" required":"";
    let input;
    if(fl.type==="textarea") input=`<textarea id="${id}" rows="${fl.rows||3}" placeholder="${esc(fl.placeholder||"")}"${req}>${esc(v)}</textarea>`;
    else if(fl.type==="select") input=`<select id="${id}"${req}>${fl.options.map(([ov,ot])=>`<option value="${esc(ov)}"${String(ov)===String(v)?" selected":""}>${esc(ot)}</option>`).join("")}</select>`;
    else if(fl.type==="checkbox") return `<label class="chk" for="${id}"><input type="checkbox" id="${id}"${v?" checked":""}> ${esc(fl.label)}</label>`;
    else if(fl.type==="checks") return `<fieldset class="checks"><legend>${esc(fl.label)}</legend>${fl.options.map(([ov,ot],i)=>`<label class="chk"><input type="checkbox" name="${id}" value="${esc(ov)}"${(v||[]).includes(ov)?" checked":""}> ${esc(ot)}</label>`).join("")}</fieldset>`;
    else if(fl.type==="file") input=`<input id="${id}" type="file" accept="${fl.accept||"image/*"}">`;
    else input=`<input id="${id}" type="${fl.type||"text"}" value="${esc(v)}" placeholder="${esc(fl.placeholder||"")}"${fl.step?` step="${fl.step}"`:""}${fl.inputmode?` inputmode="${fl.inputmode}"`:""}${req}>`;
    return `<label class="${fl.half?"half":""}" for="${id}">${esc(fl.label)}${fl.hint?`<span class="hint">${esc(fl.hint)}</span>`:""}${input}</label>`;
  }).join("");
  root.innerHTML = `<div class="sheet-back" data-close></div>
    <form class="sheet-card" id="sheet-form" novalidate>
      <div class="sheet-head"><h2>${esc(title)}</h2><button type="button" class="x" data-close aria-label="Cerrar">✕</button></div>
      ${intro?`<p class="small muted">${intro}</p>`:""}
      ${html}
      <div class="form-grid">${f}</div>
      <div class="msg" id="sheet-msg" role="status"></div>
      <div class="btns">${onSubmit?`<button class="btn" type="submit">${esc(submit)}</button>`:""}<button class="btn ghost" type="button" data-close>${onSubmit?"Cancelar":"Cerrar"}</button>
      ${danger?`<button class="btn quiet danger" type="button" id="sheet-danger">${esc(danger.label)}</button>`:""}</div>
    </form>`;
  root.hidden = false; document.body.classList.add("noscroll");
  const close=()=>{root.hidden=true;root.innerHTML="";document.body.classList.remove("noscroll");window.dispatchEvent(new Event("sheetclose"));};
  root.querySelectorAll("[data-close]").forEach(b=>b.addEventListener("click",close));
  const msg=t=>{$("#sheet-msg").textContent=t;};
  const first = root.querySelector("input:not([type=checkbox]):not([type=file]),textarea,select"); if(first && !first.value) setTimeout(()=>first.focus(),50);
  if(danger){let armed=false;$("#sheet-danger").addEventListener("click",async e=>{
    if(!armed){armed=true;e.target.textContent="Toca otra vez para confirmar";return;}
    try{await danger.onClick();close();}catch(err){msg(err.message);}
  });}
  $("#sheet-form").addEventListener("submit",async e=>{
    e.preventDefault(); if(!onSubmit) return close();
    const vals={};
    for(const fl of fields){
      const el=root.querySelector("#sf-"+fl.id);
      if(fl.type==="checkbox") vals[fl.id]=el.checked;
      else if(fl.type==="checks") vals[fl.id]=[...root.querySelectorAll(`[name="sf-${fl.id}"]:checked`)].map(x=>x.value);
      else if(fl.type==="file") vals[fl.id]=el.files[0]||null;
      else vals[fl.id]=el.value.trim();
      if(fl.required && (vals[fl.id]===""||vals[fl.id]==null)){msg("Falta: "+fl.label+".");el?.focus();return;}
    }
    const btn=root.querySelector("button[type=submit]"); btn.disabled=true; msg("Guardando…");
    try{ const r=await onSubmit(vals); if(r===false){btn.disabled=false;return;} close(); }
    catch(err){ msg(err.message||String(err)); btn.disabled=false; }
  });
  return {close,msg};
}

let toastT;
export function toast(t){const el=$("#toast");el.textContent=t;el.hidden=false;clearTimeout(toastT);toastT=setTimeout(()=>el.hidden=true,2600);}

/* Selector de días 20–28 marzo */
export function dayStrip(days,sel,attr="data-day"){
  return `<div class="daystrip" role="tablist">${days.map(d=>`<button role="tab" aria-selected="${d.date===sel}" ${attr}="${d.date}"><b>${new Date(d.date+"T12:00:00Z").getUTCDate()}</b><span>${["dom","lun","mar","mié","jue","vie","sáb"][new Date(d.date+"T12:00:00Z").getUTCDay()]}</span></button>`).join("")}</div>`;
}
