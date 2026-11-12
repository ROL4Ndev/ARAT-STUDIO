document.querySelectorAll('[data-estimate-endpoint]').forEach(form => {
 const service=form.elements.service_id,area=form.elements.area_m2,lod=form.elements.lod_selected,preview=form.querySelector('#estimate-preview'),token=form.elements.estimate_token;
 let timer,controller,sequence=0,pending=false;
 const calculate=async()=>{
  controller?.abort();controller=new AbortController();const run=++sequence;
  const manual=service.selectedOptions[0]?.dataset.mode==='manual';area.disabled=lod.disabled=manual;area.required=lod.required=!manual;
  if(token)token.value='';if(form.elements.estimate_confirmed)form.elements.estimate_confirmed.checked=false;
  if(!manual&&(!area.checkValidity()||!lod.value)){preview.textContent='Completa un área válida y un nivel LOD.';return;}
  pending=true;preview.textContent='Consultando estimación…';
  try{
   const response=await fetch(form.dataset.estimateEndpoint,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({service_id:service.value,area_m2:manual?null:area.value,lod_selected:manual?null:lod.value}),signal:controller.signal});
   const data=await response.json();if(run!==sequence)return;
   if(!response.ok)throw new Error(Object.values(data.errors??{}).flat()[0]??'No se pudo consultar. Intenta nuevamente.');
   const demoNote=data.is_demo?' Las tarifas disponibles son demostrativas; no constituyen una cotización oficial.':' Esta estimación no constituye una cotización oficial.';
   preview.textContent=data.estimated_cost===null?`${data.message}${demoNote}`:`Estimación referencial: ${new Intl.NumberFormat('es-PE',{style:'currency',currency:'PEN'}).format(data.estimated_cost)}.${demoNote}`;
   if(token)token.value=data.estimate_token;
  }catch(e){if(e.name!=='AbortError'&&run===sequence)preview.textContent=e.message;}
  finally{if(run===sequence)pending=false;}
 };
 [service,area,lod].forEach(el=>el.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(calculate,250);}));
 form.addEventListener('submit',event=>{
  if(form.id==='quotation-form'){event.preventDefault();calculate();return;}
  if(pending||(token&&!token.value)){event.preventDefault();calculate();return;}
  if(form.dataset.submitting==='true'){event.preventDefault();return;}
  form.dataset.submitting='true';form.setAttribute('aria-busy','true');form.classList.add('form-loading');
  const button=event.submitter??form.querySelector('button[type="submit"],button:not([type])');
  if(button){button.dataset.originalLabel=button.textContent.trim();button.disabled=true;button.textContent=button.dataset.loadingLabel??'Enviando solicitud…';}
 });
 const link=form.querySelector('#request-project-link');
 if(link)link.addEventListener('click',()=>{const url=new URL(link.href);['service_id','area_m2','lod_selected','project_type'].forEach(name=>{if(!form.elements[name].disabled)url.searchParams.set(name,form.elements[name].value);});link.href=url.toString();});
 calculate();
});
