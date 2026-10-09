import 'bootstrap/js/dist/collapse';
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(!window.confirm(form.dataset.confirm))event.preventDefault();}));
const doctor=document.querySelector('#doctor_id');
if(doctor){
 const services=document.querySelector('#service_id'), date=document.querySelector('#booking_date'),slot=document.querySelector('#starts_at'),message=document.querySelector('#availability-message');
 const catalog=JSON.parse(document.querySelector('#doctor-data').textContent);
 let sequence=0;
 function updateServices(){services.replaceChildren();const entry=catalog.find(d=>d.id===Number(doctor.value));for(const service of entry?.services??[]){const option=new Option(service.name,service.id);option.selected=String(service.id)===services.dataset.selected;services.add(option);} }
 async function availability(){const current=++sequence;slot.replaceChildren(new Option('Choose an available time',''));message.textContent='';if(!doctor.value||!date.value)return;message.textContent='Loading available times…';try{const response=await fetch('/doctors/'+encodeURIComponent(doctor.value)+'/availability?date='+encodeURIComponent(date.value),{headers:{Accept:'application/json'}});const result=await response.json();if(current!==sequence)return;if(!response.ok)throw new Error(Object.values(result.errors??{}).flat()[0]??result.message??'Unable to load availability.');for(const value of result.data.slots){const label=new Intl.DateTimeFormat(undefined,{timeZone:result.data.timezone,hour:'2-digit',minute:'2-digit'}).format(new Date(value));slot.add(new Option(label,value));}message.textContent=result.data.slots.length?'Times shown in '+result.data.timezone+'. Availability is checked again when booking.':'No available appointments on this day. Choose another date.';}catch(error){if(current===sequence)message.textContent=error.message;}}
 doctor.addEventListener('change',()=>{updateServices();availability();});date.addEventListener('change',availability);updateServices();availability();
}
