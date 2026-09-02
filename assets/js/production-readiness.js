(()=>{
'use strict';
const $=(s,r=document)=>r.querySelector(s);
let result={issues:[],stages:{}},timer=0,lastRefresh=0;
async function get(url){const r=await fetch(url),d=await r.json().catch(()=>null);if(!r.ok||!d)throw new Error('Readiness check unavailable.');return d}
function takeUrl(t){return t?.local?.url||t?.result_media?.local?.url||t?.remote_url||t?.result_media?.remote_url||''}
function usableTakes(state,shotId){return(state?.takes||[]).filter(t=>t.shot_id===shotId&&takeUrl(t))}
function selectedTake(state,shot){const takes=usableTakes(state,shot.id),id=shot.selected_take_id;return takes.find(t=>t.id===id)||takes.find(t=>t.selected)||null}
function dialogueSeconds(lines){let words=0;for(const l of lines||[])words+=String(l.text||'').trim().split(/\s+/).filter(Boolean).length;return words?words/2.6+(lines.length*.22):0}
function issue(id,stage,title,detail,selector='',action=''){return{id,stage,title,detail,selector,action}}
function build(base,continuity,sound,timeline){
 const state=base?.state||{},issues=[],shots=state.shots||[],jobs=state.jobs||[];
 for(const shot of shots){
  const takes=usableTakes(state,shot.id),failed=[...jobs].reverse().find(j=>j.shot_id===shot.id&&['failed','error','cancelled'].includes(String(j.status||'').toLowerCase()));
  const selector=`[data-open-shot="${CSS.escape(String(shot.id))}"]`;
  if(!takes.length)issues.push(issue(`take-${shot.id}`,'direct',failed?'Retry this shot':'Generate this shot',failed?(failed.safe_error||'The last generation failed and can be retried.'):'This shot needs a usable take before it can enter the film.',selector));
  else if(!selectedTake(state,shot))issues.push(issue(`select-${shot.id}`,'direct','Approve a take','Choose which generated take should continue through the film.',selector,'compare'));
  if(shot.continuity_from_shot_id){
   const source=(shots||[]).find(s=>s.id===shot.continuity_from_shot_id);
   if(!source||!selectedTake(state,source))issues.push(issue(`continuity-${shot.id}`,'direct','Continuation needs its source','Generate and approve the previous shot before continuing this one.',selector));
  }
  const lines=Array.isArray(shot.dialogue)?shot.dialogue:[],needed=dialogueSeconds(lines),target=Number(shot.duration_target||5);
  const failedVoice=lines.find(l=>l.speech_status==='failed');
  if(failedVoice)issues.push(issue(`voice-${shot.id}`,'perform','Retry dialogue voice',failedVoice.speech_error||'A dialogue voice failed and can be retried.',selector,'dialogue'));
  if(needed>target+1)issues.push(issue(`dialogue-${shot.id}`,'perform','Check dialogue timing',`The dialogue may need about ${needed.toFixed(1)}s, while the shot targets ${target.toFixed(1)}s.`,selector,'dialogue'));
  const passes=shot.act_two_orchestration?.passes||[];
  if(passes.some(p=>p.status==='failed'))issues.push(issue(`acting-${shot.id}`,'perform','Retry precision acting','A precision-performance pass failed and can be retried.',selector,'acting'));
 }
 for(const scene of continuity?.scenes||[])for(const shot of scene.shots||[]){
  const failed=(shot.warnings||[]).find(w=>['PRECISION_FAILED'].includes(w.code));
  const checks=Object.values(shot.review?.checks||{});
  if(checks.includes('fail'))issues.push(issue(`review-${shot.id}`,'review','Continuity needs correction',`Shot ${String(shot.number||'').padStart(2,'0')} has a review item marked Needs fix.`,'#continuityPanel'));
  else if(failed&&!issues.some(x=>x.id===`acting-${shot.id}`))issues.push(issue(`review-${shot.id}`,'review','Continuity needs attention',failed.label,'#continuityPanel'));
 }
 for(const scene of sound?.scenes||[]){
  for(const job of scene.sound_design?.jobs||[])if(job.status==='failed')issues.push(issue(`sound-${job.id}`,'review','Retry scene sound',job.safe_error||'A scene sound generation failed.','#soundDesignPanel'));
  for(const shot of scene.shots||[])for(const cue of shot.sound_design?.cues||[])if(cue.status==='failed')issues.push(issue(`sound-${cue.id}`,'review','Retry a sound cue',cue.safe_error||'A shot sound cue failed.','#soundDesignPanel'));
 }
 if(shots.some(s=>usableTakes(state,s.id).length)&&timeline?.ffmpeg_available===false)issues.push(issue('ffmpeg','finish','Final export unavailable','This server cannot create the final MP4 until FFmpeg is available.','#timelineStatus'));
 const by=stage=>issues.filter(x=>x.stage===stage);
 const stages={
  direct:by('direct').length?'attention':(shots.length?'complete':'active'),
  perform:by('perform').length?'attention':(shots.length?'complete':'pending'),
  review:by('review').length?'attention':(shots.length?'complete':'pending'),
  finish:by('finish').length?'attention':(shots.length&&shots.every(s=>usableTakes(state,s.id).length)?'complete':'pending')
 };
 return{issues,stages,hasShots:shots.length>0}
}
function ensure(){
 const nav=$('#productionStageNav');if(!nav)return false;
 if(!$('#productionReadiness')){const b=document.createElement('button');b.id='productionReadiness';b.className='production-readiness';b.type='button';b.innerHTML='<span class="readiness-dot"></span><span id="readinessLabel">Checking production…</span><span aria-hidden="true">›</span>';nav.insertAdjacentElement('afterend',b);b.onclick=openSheet}
 if(!$('#readinessDialog')){const d=document.createElement('dialog');d.id='readinessDialog';d.className='readiness-dialog';d.innerHTML='<div class="readiness-sheet"><div class="readiness-head"><div><span>PRODUCTION STATUS</span><h2>Film readiness</h2></div><button class="shot-action" type="button" data-close-readiness>Done</button></div><div id="readinessList"></div></div>';document.body.append(d);d.querySelector('[data-close-readiness]').onclick=()=>d.close();d.addEventListener('click',e=>{if(e.target===d)d.close()});d.addEventListener('click',route)}
 return true
}
function paint(){
 if(!ensure())return;const n=result.issues.length,label=$('#readinessLabel'),button=$('#productionReadiness');
 label.textContent=!result.hasShots?'Start with your first shot':n?`${n} item${n===1?'':'s'} need attention`:'Ready to finish';
 button.dataset.state=!result.hasShots?'active':n?'attention':'ready';
 document.querySelectorAll('#productionStageNav [data-stage]').forEach(b=>b.dataset.readiness=result.stages[b.dataset.stage]||'pending')
}
function renderList(){
 const box=$('#readinessList'),items=result.issues;if(!box)return;
 box.innerHTML=items.length?items.map((x,i)=>`<button class="readiness-item" type="button" data-readiness-index="${i}"><span class="readiness-item-mark"></span><span><strong>${escapeHtml(x.title)}</strong><small>${escapeHtml(x.detail)}</small></span><b>Open</b></button>`).join(''):'<div class="readiness-ready"><span>✓</span><strong>Your film is ready for a final review.</strong><p>Confirm the approved takes in Finish, then export.</p></div>'
}
function escapeHtml(s){return String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]))}
async function refresh(force=false){const now=Date.now();if(!force&&now-lastRefresh<800)return;lastRefresh=now;try{const [base,continuity,sound,timeline]=await Promise.all([get('api.php?action=state'),get('continuity-review-api.php?action=state').catch(()=>({scenes:[]})),get('sound-design-api.php?action=state').catch(()=>({scenes:[]})),get('timeline-api.php?action=state').catch(()=>({ffmpeg_available:null}))]);result=build(base,continuity,sound,timeline);paint()}catch(e){if(ensure())$('#readinessLabel').textContent='Readiness unavailable'}}
async function openSheet(){await refresh(true);renderList();$('#readinessDialog')?.showModal()}
function route(e){const b=e.target.closest('[data-readiness-index]');if(!b)return;const x=result.issues[Number(b.dataset.readinessIndex)];if(!x)return;$('#readinessDialog')?.close();document.querySelector(`#productionStageNav [data-stage="${x.stage}"]`)?.click();setTimeout(()=>{let target=x.selector?$(x.selector):null;if(target?.matches?.('[data-open-shot]'))target=target.closest('.shot-card');target?.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'center'});if(x.action==='dialogue')target?.querySelector('[data-dialogue-shot]')?.click();if(x.action==='acting')target?.querySelector('[data-acttwo-shot]')?.click();if(x.action==='compare')target?.querySelector('[data-compare-takes]')?.click()},180)}
function schedule(){clearTimeout(timer);timer=setTimeout(refresh,160)}
function boot(){ensure();refresh();document.addEventListener('ad:state-changed',schedule);document.addEventListener('ad:take-selected',schedule);document.addEventListener('click',e=>{if(e.target.closest('[data-stage]'))schedule()},true);document.addEventListener('ad:ui-updated',()=>{if(!$('#productionReadiness'))ensure()})}
document.addEventListener('DOMContentLoaded',boot);
})();