/* Native product quantities stay canonical; the optional editor counts stock or configured catalogue portions. */
(() => {
 'use strict';
 const bindings=new WeakMap(),scale=value=>{const m=String(value).replace(',','.').match(/^(0|[1-9]\d{0,7})(?:\.(\d{1,4}))?$/);return m?Number(m[1])*10000+Number((m[2]||'').padEnd(4,'0')):null;};
 const decimal=value=>String(Math.floor(value/10000))+(value%10000?'.'+String(value%10000).padStart(4,'0').replace(/0+$/,''):'');
 const options=p=>p&&p.unit!=='kg'&&!Number(p.is_divisible)&&Number(p.is_piece_based)!==0?(p.stock_portions||p.portions||[]):[];
 function decorate(input,product,labels={}) {
  bindings.get(input)?.dispose();bindings.delete(input);input.hidden=false;
  const p=typeof product==='function'?product():product;
  if(input.readOnly||!options(p).length)return input;
  const wrap=document.createElement('fieldset'),badges=document.createElement('div'),count=document.createElement('input'),preview=document.createElement('small');
  wrap.className='kp-portion-editor';badges.className='kp-portion-options';badges.setAttribute('role','group');badges.setAttribute('aria-label',labels.portion||'Portion');
  count.type='number';count.min='0';count.max='99999999';count.step='1';count.inputMode='numeric';count.hidden=true;count.required=true;preview.hidden=true;preview.setAttribute('aria-live','polite');
  let selected=null;
  function update(){
   if(selected){const n=scale(count.value);input.value=n!==null&&n%10000===0?decimal(Math.floor((n*selected.numerator+selected.denominator/2)/selected.denominator)):'';}
   preview.textContent=(selected?count.value+' × '+selected.label+' = ':'')+(input.value||'—')+' '+(labels.pieces||'pcs');
   input.dispatchEvent(new Event('input',{bubbles:true}));
  }
  for(const part of [null,...options(p)]){
   const button=document.createElement('button');button.type='button';button.textContent=part?.label||labels.whole||'1';button.dataset.portionId=String(part?.id||part?.type||'');button.setAttribute('aria-pressed',String(part===null));
   button.addEventListener('click',()=>{
    const value=selected?count.value:input.value;selected=part;
    if(selected){const amount=scale(value);count.value=amount!==null&&amount%10000===0?value:'1';count.setAttribute('aria-label',(labels.quantity||'Quantity')+' × '+selected.label+' — '+(p.name||''));}
    else input.value=preview.dataset.native||input.value;
    input.hidden=!!selected;count.hidden=!selected;preview.hidden=!selected;
    badges.querySelectorAll('button').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));
    if(selected)update();else input.dispatchEvent(new Event('input',{bubbles:true}));
   });badges.append(button);
  }
  const syncDisabled=()=>{wrap.disabled=input.disabled;};syncDisabled();
  const observer=new MutationObserver(syncDisabled);observer.observe(input,{attributes:true,attributeFilter:['disabled']});
  count.addEventListener('input',update);
  input.addEventListener('input',()=>{preview.dataset.native=input.value;preview.textContent=(selected?count.value+' × '+selected.label+' = ':'')+(input.value||'—')+' '+(labels.pieces||'pcs');});
  bindings.set(input,{active:()=>selected!==null,dispose:()=>observer.disconnect(),restore:evidence=>{const button=[...badges.querySelectorAll('button')].find(b=>b.dataset.portionId===String(evidence.id_product_portion));if(button){button.click();count.value=evidence.source_quantity;update();}},evidence:()=>selected?.id&&scale(count.value)>0&&scale(count.value)%10000===0?{portion:{id_product_portion:selected.id,source_quantity:count.value}}:{},change:(delta)=>{
   if(!selected)return false;
   const value=scale(count.value);if(value===null||value%10000)return true;
   const candidate=Math.max(0,Math.min(99999999,value/10000+delta));
   const native=Math.floor((candidate*10000*selected.numerator+selected.denominator/2)/selected.denominator);
   if(native<Number(input.min||0)*10000||native>Number(input.max||99999999)*10000)return true;
   count.value=String(candidate);update();return true;
  }});
  const caption=document.createElement('span');caption.className='kp-portion-caption';caption.textContent=labels.portion||'Portion';wrap.append(caption,badges,input,count,preview);return wrap;
 }
 window.KitchenPortions={decorate,options,active:input=>bindings.get(input)?.active()||false,restore:(input,evidence)=>bindings.get(input)?.restore(evidence),evidence:input=>bindings.get(input)?.evidence()||{},change:(input,delta)=>bindings.get(input)?.change(delta)||false};
})();
