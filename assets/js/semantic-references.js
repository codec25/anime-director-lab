(() => {
'use strict';
const ROLES=['character','environment','prop','style','motion','voice','sound','reference'];
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let state=null;
async function req(url,opts={}){const r=await fetch(url,opts);const d=await r.json().catch(()=>({ok:false,error:'Invalid server response.'}));if(!r.ok||d.ok===false)throw new Error(d.error||'Request failed.');return d}
function toast(msg){const n=document.createElement('div');n.className='toast';n.textContent=msg;document.body.append(n);setTimeout(()=>n.remove(),2600)}
function defaultRole(ref,scope){if(ROLES.includes(ref?.role))return ref.role;const kind=ref?.kind||'';if(scope==='world')return kind==='audio'?'sound':'environment';if(kind==='video')return'motion';if(kind==='audio')return'sound';return'reference'}
async function load(){state=(await req('api.php?action=state')).state;patch()}
async function saveRole(scope,refId,role,shotId=''){await req('semantic-reference-api.php?action=set-role',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({scope,reference_id:refId,role,shot_id:shotId})});toast(`Reference role: ${role}`);await load()}
function closeModal(){document.querySelector('.ref-role-modal')?.remove()}
function openManager(scope,refs,shotId='',title='Reference roles'){
  closeModal();
  const modal=document.createElement('div');modal.className='ref-role-modal';
  modal.innerHTML=`<div class="ref-role-dialog" role="dialog" aria-modal="true"><div class="ref-role-head"><div><h3>${esc(title)}</h3><p>Tell the Director what each asset controls.</p></div><button class="ref-role-close" type="button" aria-label="Close">×</button></div><div class="ref-role-list">${refs.length?refs.map(r=>`<div class="ref-role-item"><div class="ref-role-meta"><strong>${esc(r.original_name||r.id||'Reference')}</strong><span>${esc(r.kind||'media')}</span></div><select class="ref-role-select" data-ref-id="${esc(r.id)}">${ROLES.map(role=>`<option value="${role}" ${defaultRole(r,scope)===role?'selected':''}>${role}</option>`).join('')}</select></div>`).join(''):'<div class="ref-role-empty">No references attached yet.</div>'}</div></div>`;
  document.body.append(modal);
  modal.querySelector('.ref-role-close').onclick=closeModal;
  modal.onclick=e=>{if(e.target===modal)closeModal()};
  modal.querySelectorAll('.ref-role-select').forEach(sel=>sel.onchange=async()=>{sel.disabled=true;try{await saveRole(scope,sel.dataset.refId,sel.value,shotId)}catch(e){toast(e.message)}finally{sel.disabled=false}});
}
function patchWorld(){const row=document.querySelector('#worldRefRow');if(!row||!state)return;const refs=Array.isArray(state.world?.references)?state.world.references:[];let btn=document.querySelector('#worldRoleManager');if(!btn){btn=document.createElement('button');btn.id='worldRoleManager';btn.className='ref-role-btn';btn.type='button';btn.textContent='Reference roles';const actions=document.querySelector('#worldMemoryPanel .world-actions');actions?.prepend(btn)}btn.hidden=!refs.length;btn.onclick=()=>openManager('world',refs,'','World reference roles');}
function patchShots(){if(!state)return;for(const card of document.querySelectorAll('.shot-card')){const open=card.querySelector('[data-open-shot]');if(!open)continue;const shot=(state.shots||[]).find(s=>s.id===open.dataset.openShot);const refs=Array.isArray(shot?.references)?shot.references:[];let btn=card.querySelector('[data-role-manager]');if(refs.length&&!btn){btn=document.createElement('button');btn.className='shot-action';btn.type='button';btn.dataset.roleManager=shot.id;btn.textContent='Reference roles';open.parentElement?.insertBefore(btn,open)}if(btn){btn.hidden=!refs.length;btn.onclick=()=>openManager('shot',refs,shot.id,`Shot ${String(shot.number||'').padStart(2,'0')} reference roles`)}}}
function patch(){patchWorld();patchShots()}
function boot(){load().catch(e=>toast(e.message));const scenes=document.querySelector('#directorScenes');if(scenes)new MutationObserver(()=>setTimeout(patchShots,50)).observe(scenes,{childList:true,subtree:true});const world=document.querySelector('#worldMemoryPanel');if(world)new MutationObserver(()=>setTimeout(patchWorld,50)).observe(world,{childList:true,subtree:true});document.addEventListener('click',e=>{if(e.target.closest('[data-reference-shot]')||e.target.closest('#worldReference'))setTimeout(()=>load().catch(()=>{}),1200)})}
document.addEventListener('DOMContentLoaded',boot);
})();