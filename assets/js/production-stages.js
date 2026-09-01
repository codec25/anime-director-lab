(()=>{
'use strict';
const $=(s,r=document)=>r.querySelector(s);
let stage=localStorage.getItem('ad_director_stage')||'direct';
const map={direct:['#directorComposeStage','#quickRow','#conversation','#productionStageShots'],perform:['#castMemoryPanel','#actTwoPanel'],review:['#continuityPanel','#soundDesignPanel'],finish:['#timelinePanel']};
const memory=['#worldMemoryPanel','#sceneMemoryPanel'];
function nav(){if($('#productionStageNav'))return;const anchor=$('#quickRow')||$('#conversation');if(!anchor)return;const n=document.createElement('nav');n.id='productionStageNav';n.className='production-stage-nav';n.setAttribute('aria-label','Production stage');n.innerHTML=[['direct','Direct','Shots'],['perform','Perform','Voices + acting'],['review','Review','Continuity + sound'],['finish','Finish','Timeline + export']].map(([k,a,b])=>`<button type="button" class="production-stage-btn" data-stage="${k}">${a}<span>${b}</span></button>`).join('');anchor.parentElement?.insertBefore(n,anchor);n.addEventListener('click',e=>{const b=e.target.closest('[data-stage]');if(!b)return;stage=b.dataset.stage;localStorage.setItem('ad_director_stage',stage);apply()})}
function allSelectors(){return [...new Set(Object.values(map).flat())]}
function apply(){nav();document.querySelectorAll('[data-stage]').forEach(b=>b.classList.toggle('active',b.dataset.stage===stage));for(const sel of allSelectors()){const el=$(sel);if(el)el.classList.toggle('stage-hidden',!(map[stage]||[]).includes(sel))}for(const sel of memory){const el=$(sel);if(el)el.classList.toggle('stage-hidden',stage!=='direct')}
 const finish=$('#timelinePanel');if(stage==='finish'&&finish)finish.scrollIntoView({behavior:'smooth',block:'start'});
}
function boot(){nav();apply();const root=$('main')||document.body;new MutationObserver(()=>apply()).observe(root,{childList:true,subtree:true})}
document.addEventListener('DOMContentLoaded',boot);
})();