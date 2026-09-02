(()=>{
'use strict';
const $=(s,r=document)=>r.querySelector(s);
let stage=localStorage.getItem('ad_director_stage')||'direct';
if(!['direct','perform','review','finish'].includes(stage))stage='direct';
const order=['direct','perform','review','finish'];
const map={direct:['#directorComposeStage','#quickRow','#conversation','#productionStageShots'],perform:['#castMemoryPanel','#productionStageShots'],review:['#reviewWorkspaceSwitch','#continuityPanel','#soundDesignPanel'],finish:['#timelinePanel']};
const hiddenMemories=['#worldMemoryPanel','#sceneMemoryPanel'];
function allSelectors(){return [...new Set(Object.values(map).flat())]}
function setStage(next,scroll=false){if(!order.includes(next))return;stage=next;localStorage.setItem('ad_director_stage',stage);apply(scroll)}
function apply(scroll=false){for(const sel of allSelectors()){const el=$(sel);if(el)el.classList.toggle('stage-hidden',!(map[stage]||[]).includes(sel))}for(const sel of hiddenMemories){const el=$(sel);if(el)el.classList.add('stage-hidden')}document.body.dataset.directorStage=stage;if(scroll){const target=stage==='finish'?$('#timelinePanel'):stage==='review'?($('#reviewWorkspaceSwitch')||$('#continuityPanel')||$('#soundDesignPanel')):stage==='perform'?($('#castMemoryPanel')||$('#productionStageShots')):$('#directorComposeStage');target?.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'})}}
function boot(){apply();document.addEventListener('ad:ui-updated',()=>apply(false));document.addEventListener('ad:open-workspace',e=>setStage(e.detail?.stage||'direct',true))}
document.addEventListener('DOMContentLoaded',boot);
})();