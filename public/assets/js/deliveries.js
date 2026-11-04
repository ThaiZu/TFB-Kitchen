(() => {
    'use strict';
    const dataNode=document.getElementById('dlData');
    if (!dataNode) return;
    const {root,order,messages:m}=JSON.parse(dataNode.textContent);
    const form=document.getElementById('dlForm'), fieldset=form.querySelector('fieldset'), save=document.getElementById('dlSave');
    if (!save) return;
    const notice=document.getElementById('dlNotice'), check=document.getElementById('dlCheck'), dialog=document.getElementById('dlConfirm'), issues=document.getElementById('dlIssues'), confirmText=document.getElementById('dlConfirmText'), confirmSave=document.getElementById('dlConfirmSave');
    const rows=[...document.querySelectorAll('.dl-line')], format=n=>Number(n).toLocaleString(undefined,{maximumFractionDigits:4});
    let pending=null, busy=false, confirmedExclusion=false, uncertain=false, dirty=false;
    const show=(text,success=false)=>{notice.hidden=false;notice.textContent=text;notice.classList.toggle('is-success',success);notice.scrollIntoView({block:'nearest'});};
    function summary(){let packs=0,different=0;rows.forEach(row=>{const qty=Number(row.querySelector('input').value),diff=qty-Number(row.dataset.ordered);packs+=Number.isFinite(qty)?qty:0;if(diff!==0)different++;const output=row.querySelector('output');output.textContent=`${m.difference}: ${diff>0?'+':''}${format(diff)}`;output.classList.toggle('is-different',diff!==0);});document.getElementById('dlSummary').textContent=`${format(packs)} ${m.packs} · ${different} ${m.differing_lines}`;return {packs,different};}
    rows.forEach(row=>{const input=row.querySelector('input');input.addEventListener('input',()=>{dirty=true;summary();});row.querySelectorAll('[data-step]').forEach(button=>button.addEventListener('click',()=>{input.value=String(Math.round(Math.max(0,Math.min(99999999,Number(input.value||0)+Number(button.dataset.step)))*10000)/10000);input.dispatchEvent(new Event('input',{bubbles:true}));}));});
    summary();
    document.getElementById('dlDate').addEventListener('input',()=>{dirty=true;});
    form.addEventListener('submit',event=>{event.preventDefault();if(busy||uncertain||!form.reportValidity())return;const quantities=Object.fromEntries(rows.map(row=>[row.dataset.key,row.querySelector('input').value]));pending={shipment_revision:order.shipment_revision,delivered_on:document.getElementById('dlDate').value,quantities};confirmedExclusion=false;issues.replaceChildren();const total=summary();confirmText.textContent=`${m.confirm_hint} ${format(total.packs)} ${m.packs}; ${total.different} ${m.differing_lines}.`;confirmSave.textContent=m.confirm_receipt;dialog.showModal();});
    document.getElementById('dlCancel').addEventListener('click',()=>dialog.close());
    function lockForCheck(text){uncertain=true;fieldset.disabled=true;check.hidden=false;show(text);}
    async function receive(){
        if(busy||!pending)return;busy=true;confirmSave.disabled=true;dialog.close();fieldset.disabled=true;show(m.saving);
        const abort=new AbortController(), timer=setTimeout(()=>abort.abort(),45000);
        try{
            const response=await fetch(`${root}/ajax/deliveries/${order.id}/receive`,{method:'POST',credentials:'same-origin',signal:abort.signal,headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({...pending,confirm_exclude_unmapped_products:confirmedExclusion})});
            const result=await response.json();
            if(response.ok&&result.success===true&&result.data?.success_insert_delivery===true){
                const costOk=result.data.success_recalculate_costs===true, excluded=result.data.excluded_unmapped_products||[];
                show((costOk?m.saved:m.saved_cost_warning)+(excluded.length?` ${m.excluded_notice} ${excluded.length}.`:''),costOk);save.hidden=true;check.textContent=m.back_to_deliveries;check.href=root+'/deliveries';check.hidden=false;pending=null;dirty=false;return;
            }
            const reason=result.reason||'', unmapped=result.errors?.unmapped_products;
            if(response.ok||response.status>=500||!response.status){lockForCheck(m.unknown_result);return;}
            if(reason==='DELIVERY_CATALOG_MAPPING_MISMATCH'&&Array.isArray(unmapped)&&unmapped.length&&!confirmedExclusion){
                fieldset.disabled=false;issues.replaceChildren();unmapped.forEach(line=>{const li=document.createElement('li');li.textContent=String(line.material_name||line.name||line.supplier_sku||m.unmapped_line);issues.append(li);});confirmText.textContent=m.exclude_hint;confirmSave.textContent=m.confirm_exclusion;confirmedExclusion=true;dialog.showModal();return;
            }
            if(['DELIVERY_SHIPMENT_UPDATED','ORDER_ALREADY_DELIVERED','DELIVERY_ORDER_CLOSED'].includes(reason)){lockForCheck(reason==='ORDER_ALREADY_DELIVERED'?m.already_received:reason==='DELIVERY_ORDER_CLOSED'?m.closed_delivery:m.shipment_updated);return;}
            if(reason.startsWith('KITCHEN_SHIFT_')){fieldset.disabled=true;show(m.shift_required);check.hidden=false;window.dispatchEvent(new CustomEvent('kitchen:worker-session-expired'));return;}
            fieldset.disabled=false;show(reason==='INVALID_DELIVERY_DATE'?m.invalid_date:reason==='DELIVERY_NO_MAPPED_ITEMS'?m.no_mapped_items:m.save_failed);
        }catch(error){lockForCheck(m.unknown_result);}
        finally{clearTimeout(timer);busy=false;confirmSave.disabled=false;}
    }
    confirmSave.addEventListener('click',receive);
    window.addEventListener('beforeunload',event=>{if(busy||(dirty&&!uncertain)){event.preventDefault();event.returnValue='';}});
})();
