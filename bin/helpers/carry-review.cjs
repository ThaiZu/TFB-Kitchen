// Follow the employee-facing carry review without bypassing its confirmations.
module.exports=async function reviewCarry(page,edits={}){
 let visited=0;
 while(await page.locator('[data-carry-card]').count()){
  if(++visited>10000)throw Error('Carry review did not reach its summary');
  const input=page.locator('[data-carry-card] [data-opening-product]'),id=await input.getAttribute('data-opening-product');
  if(Object.hasOwn(edits,id))await input.fill(edits[id]);
  await page.locator('#pd-wizard-next').click();
 }
 await page.locator('#pd-wizard-next').click();
};
