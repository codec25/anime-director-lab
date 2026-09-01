(() => {
'use strict';
const $=(s,r=document)=>r.querySelector(s);
const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
let state=null,config=null,providers=[],mode='guide';
const quickIdeas=[
  'Create a tense anime opening in the rain',
  'Make the next shot a close-up on the eyes',
  'Keep the character, change the mood to ominous',
  'Low camera. Slow push-in. Something is behind him.',
  'Continue the scene with a faster action beat'
];
function toast(msg){const n=document.createElement('div');n.className='toast';n.textContent=msg;document.body.append(n);setTimeout(()=>n.remove(),2800)}
async function request(url,opts={}){const r=await fetch(url,opts);const d=await r.json().catch(()=>({ok:false,error:'Invalid server response.'}));if(!r.ok||d.ok===false)throw new Error(d.error||'Request failed.');return d}
async function api(action,opts={}){return request(`api.php?action=${encodeURIComponent(action)}`,opts)}
function capabilitySet(){const c=config?.capabilities||{};return new Set(Array.isArray(c)?c:Object.keys(c))}
function describeProvider(){return providers.find(p=>p.id==='runway_gen45'&&p.implemented&&p.available)||providers.find(p=>p.implemented&&p.available&&(p.capabilities||[]).includes('DESCRIBE_SHOT'))||null}
function characterMaster(){const c=state?.character;return c?.references?.master_front||c?.asset||null}
function latestDescribeJob(shotId){return [...(state?.jobs||[])].reverse().find(j=>j.shot_id===shotId&&j.capability==='DESCRIBE_SHOT')||null}
async function refresh(){
  const [base,status]=await Promise.all([
    api('state'),
    request('director-api.php?action=status').catch(()=>({providers:[]}))
  ]);
  state=base.state;config=base.config;providers=status.providers||[];
  renderContext();renderScenes();renderCapabilities();
}
function renderContext(){
  const c=state?.character,dot=$('#directorStatusDot'),name=$('#directorCharacterName'),avatar=$('#directorCharacterAvatar');
  if(c){name.textContent=`${c.name} ${c.version} · ${c.status==='locked'?'Character locked':'Draft character'}`;dot.classList.toggle('ready',c.status==='locked');const m=characterMaster();avatar.style.backgroundImage=m?.url?`url('${String(m.url).replace(/'/g,"%27")}')`:'none'}
  else{name.textContent='No character locked · set one up in Advanced';dot.classList.remove('ready');avatar.style.backgroundImage='none'}
  $('#directorSceneCount').textContent=`${state?.shots?.length||0} shots`;
}
function renderQuick(){const row=$('#quickRow');row.innerHTML=quickIdeas.map(x=>`<button class="chip" type="button">${esc(x)}</button>`).join('');[...row.children].forEach((b,i)=>b.onclick=()=>{$('#directorPrompt').value=quickIdeas[i];$('#directorPrompt').focus();syncSend()})}
function selectedMediaForShot(s){const take=(state?.takes||[]).find(t=>t.id===s.selected_take_id);return take?.local?.url||take?.result_media?.local?.url||take?.remote_url||take?.result_media?.remote_url||''}
function generationButton(s){
  if(s.generation_mode!=='DESCRIBE_IT')return '';
  const p=describeProvider();
  if(!p)return '<button class="shot-action" disabled>Generation not configured</button>';
  const job=latestDescribeJob(s.id);
  if(job&&['submitted','queued','processing'].includes(job.status))return `<button class="shot-action" data-poll-job="${esc(job.id)}">Check generation</button>`;
  return `<button class="shot-action primary-action" data-generate-shot="${esc(s.id)}">${window.AD_CONFIG?.mock?'Generate mock take':'Generate anime take'}</button>`;
}
function renderScenes(){
  const el=$('#directorScenes'),shots=[...(state?.shots||[])].reverse().slice(0,12);
  if(!shots.length){el.innerHTML='<div class="empty">Your directed shots will appear here. Start by describing a scene above.</div>';return}
  const ref=characterMaster();
  el.innerHTML=shots.map(s=>{const media=selectedMediaForShot(s);let thumb=ref?.url?`<img src="${esc(ref.url)}" alt="character reference">`:'✦';if(media)thumb=`<video muted playsinline preload="metadata" src="${esc(media)}"></video>`;return `<article class="shot-card"><div class="shot-main"><div class="shot-thumb">${thumb}</div><div class="shot-copy"><div class="shot-number">SHOT ${String(s.number||s.shot_number||'').padStart(2,'0')}</div><strong>${esc(s.title||'Directed shot')}</strong><p>${esc(s.intent||s.direction||'')}</p><div class="shot-tags"><span class="shot-tag">${esc(s.generation_mode||'DESCRIBE_IT')}</span><span class="shot-tag">${esc(s.anime_boost_mode||'natural')}</span><span class="shot-tag">${esc(s.ratio||'')}</span><span class="shot-tag">${esc(s.status||'draft')}</span></div></div><div class="shot-actions">${generationButton(s)}<button class="shot-action" data-open-shot="${esc(s.id)}">Advanced</button></div></div></article>`}).join('');
  el.querySelectorAll('[data-open-shot]').forEach(b=>b.onclick=()=>location.href=`lab.php#shot-${encodeURIComponent(b.dataset.openShot)}`);
  el.querySelectorAll('[data-generate-shot]').forEach(b=>b.onclick=()=>generateShot(b.dataset.generateShot,b));
  el.querySelectorAll('[data-poll-job]').forEach(b=>b.onclick=()=>pollJob(b.dataset.pollJob,b));
}
function renderCapabilities(){
  const caps=capabilitySet(),p=describeProvider();
  const list=[
    ['Character consistency',!!state?.character,'Character Bible'],
    ['Natural-language direction',true,'Director'],
    ['Describe-to-video',!!p,p?`${p.label}${window.AD_CONFIG?.mock?' · mock':' · configured'}`:'provider not configured'],
    ['ACT IT performance',caps.has('ACT_IT'),'Advanced'],
    ['Anime Boost',caps.has('ANIME_BOOST'),'shot direction layer'],
    ['Continue shot',false,'next production phase'],
    ['Dialogue / lip sync',false,'next production phase'],
    ['Sound design',false,'next production phase']
  ];
  $('#capabilityGrid').innerHTML=list.map(([label,ready,note])=>`<div class="feature ${ready?'ready':'planned'}"><strong>${esc(label)}</strong><span>${esc(note)}</span></div>`).join('');
}
function parseDirection(text){
  const lower=text.toLowerCase();let camera='',boost='natural',ratio='1280:720';
  if(/vertical|portrait|reel|tiktok|shorts/.test(lower))ratio='720:1280';
  else if(/square/.test(lower))ratio='960:960';
  else if(/cinematic|widescreen|movie|film/.test(lower))ratio='1584:672';
  if(/close[- ]?up|eyes|face/.test(lower))camera='Close-up';
  else if(/low angle|ground level|feet/.test(lower))camera='Low-angle';
  else if(/over the shoulder|behind him|behind her|from behind/.test(lower))camera='Over-the-shoulder / rear reveal';
  else if(/wide|establishing/.test(lower))camera='Wide establishing shot';
  else if(/push in|push-in|dolly in/.test(lower))camera='Slow push-in';
  else if(/handheld/.test(lower))camera='Handheld camera';
  else camera=mode==='guide'?'Director chooses cinematic framing':'Follow written framing exactly';
  if(/anime|impact|speed lines|fight|action|dynamic/.test(lower))boost='anime';
  if(/extreme|explosive|sakuga|huge impact/.test(lower))boost='extreme';
  const title=text.split(/[.!?]/)[0].trim().slice(0,72)||'Directed shot';
  return{title,intent:text,direction:text,camera_direction:camera,ratio,boost,generation_mode:'DESCRIBE_IT',duration_target:/slow|suspense|tense|ominous/.test(lower)?6:5};
}
function addMessage(role,text){const wrap=$('#conversation');wrap.classList.add('has');const m=document.createElement('div');m.className=`message ${role}`;m.innerHTML=role==='assistant'?`<div class="assistant-avatar">AD</div><div class="bubble">${esc(text)}</div>`:`<div class="bubble">${esc(text)}</div>`;wrap.append(m);m.scrollIntoView({behavior:'smooth',block:'nearest'})}
function assistantReply(plan){const p=describeProvider(),bits=[];bits.push(`Shot added with ${plan.camera_direction.toLowerCase()}.`);if(plan.boost!=='natural')bits.push(`Anime Boost: ${plan.boost}.`);bits.push('The direction stays attached to this production and character.');bits.push(p?'You can generate a take from the shot card below.':'Generation is not configured yet, but the shot is ready and can be generated when a provider is connected.');return bits.join(' ')}
async function submitDirection(){
  const input=$('#directorPrompt'),text=input.value.trim();if(!text)return;
  if(!state?.character){addMessage('assistant','Set up and lock a character first so I have an identity to preserve.');toast('Character lock required');setTimeout(()=>location.href='lab.php#character',650);return}
  addMessage('user',text);input.value='';syncSend();const plan=parseDirection(text),btn=$('#directorSend');btn.disabled=true;document.body.classList.add('loading');
  try{const d=await api('create-shot',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(plan)});addMessage('assistant',assistantReply(plan));await refresh();document.querySelector(`[data-open-shot="${CSS.escape(d.shot.id)}"]`)?.closest('.shot-card')?.scrollIntoView({behavior:'smooth',block:'center'})}
  catch(e){addMessage('assistant',`I couldn't add that shot: ${e.message}`);toast(e.message)}finally{document.body.classList.remove('loading');syncSend()}
}
async function generateShot(shotId,btn){
  const p=describeProvider();if(!p){toast('No describe-to-video provider is configured.');return}
  const estimate=p.cost_per_second_usd??p.estimated_cost_per_second_usd;
  if(!window.AD_CONFIG?.mock){const cost=estimate!=null?` Estimated provider rate: $${Number(estimate).toFixed(2)}/second.`:'';if(!confirm(`Generate this shot with ${p.label}?${cost}`))return}
  btn.disabled=true;btn.textContent='Submitting…';
  try{const d=await request('director-api.php?action=generate',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({shot_id:shotId,provider:p.id})});toast(window.AD_CONFIG?.mock?'Mock generation submitted.':`Generation submitted · est. $${Number(d.estimated_cost_usd||0).toFixed(2)}`);await refresh();setTimeout(()=>pollJob(d.job.id,null),1000)}catch(e){toast(e.message);btn.disabled=false;btn.textContent='Generate anime take'}
}
async function pollJob(jobId,btn){
  if(btn){btn.disabled=true;btn.textContent='Checking…'}
  try{const d=await request('director-api.php?action=poll',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({job_id:jobId})});if(d.done){toast(d.job?.status==='completed'?'Anime take is ready.':(d.job?.safe_error||'Generation ended without a usable take.'));await refresh()}else{toast(`${d.job?.status||'processing'} — check again shortly`);await refresh()}}catch(e){toast(e.message);if(btn){btn.disabled=false;btn.textContent='Check generation'}}
}
function syncSend(){$('#directorSend').disabled=!$('#directorPrompt').value.trim()}
function setupMode(){const btn=$('#directorMode');btn.onclick=()=>{mode=mode==='guide'?'precise':'guide';btn.textContent=mode==='guide'?'Guide me ▾':'Precise ▾';$('#directorPrompt').placeholder=mode==='guide'?'Describe the anime scene, feeling, or change you want…':'Give exact shot, camera, action, pacing, mood, and continuity notes…'}}
function boot(){renderQuick();setupMode();$('#directorPrompt').addEventListener('input',syncSend);$('#directorPrompt').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();submitDirection()}});$('#directorSend').onclick=submitDirection;$('#newDirection').onclick=()=>{$('#directorPrompt').focus();window.scrollTo({top:0,behavior:'smooth'})};$('#createNav').onclick=e=>{e.preventDefault();$('#directorPrompt').focus();window.scrollTo({top:0,behavior:'smooth'})};refresh().catch(e=>{toast(e.message);$('#directorCharacterName').textContent='Could not load production state'});syncSend()}
document.addEventListener('DOMContentLoaded',boot);
})();
