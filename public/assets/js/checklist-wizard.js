(() => {
 'use strict';
 const root=document.getElementById('checklist-wizard');if(!root)return;
 const cards=[...root.querySelectorAll('[data-cw-task-id]')];if(!cards.length)return;
 const navigation=document.getElementById('cw-navigation'),previous=document.getElementById('cw-previous'),next=document.getElementById('cw-next'),exit=document.getElementById('cw-exit'),progress=document.getElementById('cw-progress'),bar=document.getElementById('cw-progress-bar'),text=document.getElementById('cw-progress-text'),summary=document.getElementById('cw-summary');
 const answered=card=>['DONE','FAILED','SKIPPED'].includes(card.dataset.cwStatus),done=cards.filter(answered).length;
 let busy=false,index=cards.findIndex(card=>!answered(card));
 if(index<0&&new URL(location.href).searchParams.get('wizard')!=='1')index=cards.findIndex(card=>card.dataset.cwStatus==='FAILED');
 if(index<0)index=cards.length;
 const url=new URL(location.href);url.searchParams.set('wizard','1');url.searchParams.set('checklist_id',root.dataset.checklistId);url.searchParams.set('date',root.dataset.date);history.replaceState(null,'',url);
 function focusCurrent(){const target=index===cards.length?summary:cards[index];target.tabIndex=-1;target.focus({preventScroll:true});target.scrollIntoView({block:'center',behavior:'instant'});}
 function render(){
  navigation.hidden=false;progress.hidden=false;summary.hidden=index!==cards.length;
  cards.forEach((card,i)=>card.hidden=i!==index);document.getElementById('cw-task-list').hidden=false;
  root.dispatchEvent(new CustomEvent('checklist:task',{detail:{card:cards[index]||null}}));
  previous.hidden=index===0;previous.disabled=busy;next.hidden=index===cards.length;next.disabled=busy||!answered(cards[index]||document.createElement('div'));exit.disabled=busy;
  text.textContent=root.dataset.progress.replace('{done}',String(done)).replace('{total}',String(cards.length));bar.max=cards.length;bar.value=done;
  if(index===cards.length){summary.replaceChildren();const heading=document.createElement('h3');heading.textContent=root.dataset.summary;summary.append(heading);for(const [i,card]of cards.entries()){const button=document.createElement('button');button.type='button';button.className='cw-summary-task is-'+card.dataset.cwStatus.toLowerCase();button.dataset.cwReview=card.dataset.cwTaskId;const name=card.querySelector('.tk-title')?.textContent.trim()||'',status=card.querySelector('.task-badge')?.textContent.trim()||root.dataset.pending;button.textContent=name+' — '+status;button.disabled=busy;button.onclick=()=>{if(busy)return;index=i;render();focusCurrent();};summary.append(button);}}
 }
 previous.onclick=()=>{if(!busy&&index>0){index--;render();focusCurrent();}};
 next.onclick=()=>{if(!busy&&index<cards.length&&answered(cards[index])){index++;render();focusCurrent();}};
 exit.onclick=()=>{if(!busy)location.assign(root.dataset.backUrl);};
 const originals=new Map();window.ChecklistWizard={setBusy(value){busy=value;root.setAttribute('aria-busy',String(value));for(const button of root.querySelectorAll('.btn-complete-task,.btn-fail-task')){if(value){originals.set(button,button.disabled);button.disabled=true;}else button.disabled=originals.get(button)||false;}if(!value)originals.clear();render();}};
 render();focusCurrent();
})();
