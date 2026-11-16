(()=>{'use strict';const root=document.getElementById('knowledgeProducts');if(!root)return;
const state={life:'SSL',sector:root.dataset.selectedSector||'',group:'',category:''},search=document.getElementById('productSearch'),count=document.getElementById('productCount'),empty=document.getElementById('noResults');
const rows=[...root.querySelectorAll('.product-item')].map(node=>({node,name:node.dataset.productName,life:node.dataset.life,sector:node.dataset.sectorId,sectorName:node.dataset.sectorName||root.dataset.unassigned,category:node.dataset.categoryId,categoryName:node.dataset.categoryName||root.dataset.noCategory,groups:JSON.parse(node.dataset.groups||'[]')}));
const labels={};root.querySelectorAll('[data-card-options]').forEach(node=>labels[node.dataset.cardOptions]=node.getAttribute('aria-label'));
function choices(key,includeSearch=true){
 const map=new Map(),term=includeSearch?search.value.trim().toLocaleLowerCase():'';
 for(const row of rows){
  if(term&&!row.name.toLocaleLowerCase().includes(term))continue;
  if(state.life&&row.life!==state.life)continue;
  if(key!=='sector'&&state.sector&&row.sector!==state.sector)continue;
  if(key==='sector')map.set(row.sector,row.sectorName);
  if(key==='group')for(const group of row.groups)map.set(String(group.id),group.name||group.base_name||String(group.id));
  if(key==='category'&&(!state.group||row.groups.some(g=>String(g.id)===state.group)))map.set(row.category,row.categoryName);
 }
 return [...map].sort((a,b)=>a[1].localeCompare(b[1]));
}
function normalize(){
 const sectionRows=rows.filter(row=>!state.sector||row.sector===state.sector);
 const lives=new Set(sectionRows.map(row=>row.life)),term=search.value.trim().toLocaleLowerCase();
 const matchingLives=new Set(sectionRows.filter(row=>!term||row.name.toLocaleLowerCase().includes(term)).map(row=>row.life));
 if(state.life&&!lives.has(state.life))state.life='';
 for(const key of ['group','category'])if(state[key]&&!choices(key,false).some(([id])=>id===state[key]))state[key]='';
 root.querySelectorAll('[data-card-filter="life"]').forEach(button=>button.hidden=!!button.dataset.filterValue&&button.dataset.filterValue!==state.life&&!matchingLives.has(button.dataset.filterValue));

}
function selectionLabel(key){for(const row of rows){if(key==='sector'&&row.sector===state[key])return row.sectorName;if(key==='category'&&row.category===state[key])return row.categoryName;if(key==='group'){const group=row.groups.find(g=>String(g.id)===state[key]);if(group)return group.name||group.base_name||String(group.id);}}return root.dataset.all;}

function controls(){normalize();for(const key of ['sector','group','category']){const options=root.querySelector('[data-card-options="'+key+'"]'),disclosure=options.closest('details'),summary=disclosure.querySelector('summary'),items=choices(key);options.replaceChildren();summary.querySelector('.kp-picker-value').textContent=items.find(([value])=>value===state[key])?.[1]||(state[key]?selectionLabel(key):root.dataset.all);summary.setAttribute('aria-label',labels[key]+': '+summary.querySelector('.kp-picker-value').textContent);summary.title=summary.getAttribute('aria-label');summary.classList.toggle('is-selected',!!state[key]);for(const [value,label]of [['',root.dataset.all],...items]){const button=document.createElement('button');button.type='button';button.textContent=label;button.dataset.cardFilter=key;button.dataset.filterValue=value;button.setAttribute('aria-pressed',String(state[key]===value));options.append(button);}}}
const advanced=document.getElementById('kpAdvancedFilters');
function filter(){let visible=0;const term=search.value.trim().toLocaleLowerCase();for(const row of rows){const show=(!state.life||row.life===state.life)&&(!state.sector||row.sector===state.sector)&&(!state.group||row.groups.some(g=>String(g.id)===state.group))&&(!state.category||row.category===state.category)&&(!term||row.name.toLocaleLowerCase().includes(term));row.node.hidden=!show;if(show)visible++;}document.getElementById('kpFilterClear').hidden=!Object.values(state).some(Boolean)&&!term;count.textContent=visible+' '+root.dataset.total;empty.hidden=visible!==0;document.getElementById('kpShowAll').hidden=visible!==0||rows.length===0;root.querySelectorAll('[data-card-filter="life"]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.filterValue===state.life)));}
root.addEventListener('click',event=>{const button=event.target.closest('[data-card-filter]');if(!button)return;const key=button.dataset.cardFilter;state[key]=key==='life'&&state.life===button.dataset.filterValue?'':button.dataset.filterValue;if(key==='sector'){state.group='';state.category='';}if(key==='group')state.category='';if(key==='sector'){if(!choices('group').some(([value])=>value===state.group))state.group='';if(!choices('category').some(([value])=>value===state.category))state.category='';}if(key!=='life'){const disclosure=button.closest('details');controls();disclosure.open=false;disclosure.querySelector('summary').focus({preventScroll:true});}else controls();filter();});search.addEventListener('input',()=>{controls();filter();});root.querySelectorAll('.kp-photo img').forEach(img=>{img.addEventListener('error',()=>img.hidden=true);if(img.complete&&img.naturalWidth===0)img.hidden=true;});const disclosures=[...advanced.querySelectorAll('details')];
advanced.addEventListener('click',event=>{const summary=event.target.closest('summary');if(summary)for(const item of disclosures)if(item!==summary.parentElement)item.open=false;});
document.addEventListener('click',event=>{if(!event.target.closest('.kp-filter-disclosure'))disclosures.forEach(item=>item.open=false);});
root.addEventListener('keydown',event=>{if(event.key==='Escape'){const opened=disclosures.find(item=>item.open);if(opened){event.preventDefault();opened.open=false;opened.querySelector('summary').focus();}}});
function clearFilters(){Object.assign(state,{life:'',sector:'',group:'',category:''});search.value='';advanced.querySelectorAll('details').forEach(n=>n.open=false);controls();filter();}document.getElementById('kpFilterClear').addEventListener('click',clearFilters);document.getElementById('kpShowAll').addEventListener('click',clearFilters);const topbar=document.querySelector('.app-topbar');function sizeHeader(){root.style.setProperty('--kp-header-height',(topbar?.getBoundingClientRect().height||0)+'px');}if(topbar&&typeof ResizeObserver==='function')new ResizeObserver(sizeHeader).observe(topbar);window.addEventListener('resize',sizeHeader);
sizeHeader();controls();filter();})();
