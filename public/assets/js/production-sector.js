(()=>{
'use strict';
const modal=document.getElementById('productionSectorModal');if(!modal)return;
const list=document.getElementById('productionSectorList'),error=document.getElementById('productionSectorError'),root=modal.dataset.root;
let saving=false,loading=0;
function lock(value){list.setAttribute('aria-busy',String(value));list.querySelectorAll('button').forEach(button=>button.disabled=value);}
async function choose(sector){
 if(saving)return;saving=true;lock(true);error.textContent='';
 try{
  const r=await fetch(root+'/ajax/production-sector',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({sector_id:sector.id})}),j=await r.json();
  if(!r.ok||!j.success)throw Error();
  document.getElementById('productionSectorButton').textContent=sector.name;
  location.assign(root+'/production-day');
 }catch(_){error.textContent=modal.dataset.error;saving=false;lock(false);}
}
modal.addEventListener('show.bs.modal',async()=>{
 const request=++loading;error.textContent='';list.replaceChildren();list.setAttribute('aria-busy','true');
 try{
  const r=await fetch(root+'/ajax/production-sector',{credentials:'same-origin',cache:'no-store'}),j=await r.json();
  if(request!==loading)return;if(!r.ok||!j.success)throw Error();
  for(const sector of [{id:null,name:modal.dataset.all},...j.items]){
   const button=document.createElement('button');button.type='button';button.className='tablet-worker-badge production-sector-choice';button.dataset.sectorId=sector.id??'';
   const selected=sector.id===j.selected;button.classList.toggle('active',selected);button.setAttribute('aria-pressed',String(selected));
   const initials=document.createElement('span');initials.className='tablet-worker-initials';initials.setAttribute('aria-hidden','true');initials.textContent=sector.name.trim().split(/\s+/).map(word=>word.charAt(0)).join('').slice(0,2);
   const name=document.createElement('span');name.className='tablet-worker-name';name.textContent=sector.name;button.append(initials,name);button.onclick=()=>choose(sector);list.append(button);
  }
  lock(saving);
 }catch(_){if(request===loading){error.textContent=modal.dataset.error;list.setAttribute('aria-busy','false');}}
});
})();
