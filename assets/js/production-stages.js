(()=>{
'use strict';
const $=(s,r=document)=>r.querySelector(s);
let stage=localStorage.getItem('ad_director_stage')||'direct';
if(!['direct','perform','review','finish'].includes(stage))stage='direct';
const order=['direct','perform','review','finish'];
const meta={
 direct:{label:'Direct',sub:'Shots',title:'Build the scene',help:'Describe the next shot, generate a take, and keep the story moving.',next:'Perform →'},
 perform:{label:'Perform',sub:'Voices + acting',title:'Direct the performance',help:'Add dialogue or precision acting only where the scene needs it.',next:'Review →'},
 review:{label:'Review',sub:'Picture + sound',title:'Review the scene',help:'Check continuity and sound. Fix only what breaks the film.',next:'Finish →'},
 finish:{label:'Finish',sub:'Export',title:'Finish the film',help:'Confirm the chosen takes, make optional timing changes, then export.',next:''}
};
const map={direct:['#directorComposeStage','#quickRow','#conversation','#productionStageShots'],perform:['#castMemoryPanel','#productionStageShots'],review:['#continuityPanel','#soundDesignPanel'],finish:['#timelinePanel']};
const memory=['#worldMemoryPanel','#sceneMemoryPanel'];
function nav(){if($('#productionStageNav'))return;const anchor=$('#quickRow')||$('#conversation');if(!anchor)return;const n=document.createElement('nav');n.id='productionStageNav';n.className='production-stage-nav';n.setAttribute('aria-label','Production stage');n.innerHTML=order.map((k,i)=>`<button type="button" class="production-stage-btn" data-stage="${k}" aria-label="Step ${i+1} of 4: ${meta[k].label}"><b>${i+1}</b>${meta[k].label}<span>${meta[k].sub}</span></button>`).join('');anchor.parentElement?.insertBefore(n,anchor);n.addEventListener('click',e=>{const b=e.target.closest('[data-stage]');if(!b)return;setStage(b.dataset.stage,true)})}
function guide(){let g=$('#stageGuide');if(g)return g;const n=$('#productionStageNav');if(!n)return null;g=document.createElement('section');g.id='stageGuide';g.className='stage-guide';g.setAttribute('aria-live','polite');n.insertAdjacentElement('afterend',g);g.addEventListener('click',e=>{const b=e.target.closest('[data-next-stage]');if(b)setStage(b.dataset.nextStage,true)});return g}
function allSelectors(){return [...new Set(Object.values(map).flat())]}
function setStage(next,scroll=false){if(!order.includes(next))return;stage=next;localStorage.setItem('ad_director_stage',stage);apply(scroll)}
function apply(scroll=false){nav();const m=meta[stage],g=guide();document.querySelectorAll('#productionStageNav [data-stage]').forEach(b=>{const active=b.dataset.stage===stage;b.classList.toggle('active',active);b.setAttribute('aria-current',active?'step':'false')});if(g){const i=order.indexOf(stage),next=order[i+1];g.innerHTML=`<div><span>STEP ${i+1} OF 4</span><strong>${m.title}</strong><p>${m.help}</p></div>${next?`<button class="shot-action stage-next" type="button" data-next-stage="${next}">${m.next}</button>`:''}`}
 for(const sel of allSelectors()){const el=$(sel);if(el)el.classList.toggle('stage-hidden',!(map[stage]||[]).includes(sel))}for(const sel of memory){const el=$(sel);if(el)el.classList.toggle('stage-hidden',stage!=='direct')}document.body.dataset.directorStage=stage;if(scroll){const target=stage==='finish'?$('#timelinePanel'):stage==='review'?($('#continuityPanel')||$('#soundDesignPanel')):stage==='perform'?($('#castMemoryPanel')||$('#productionStageShots')):$('#directorComposeStage');target?.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'})}}
function boot(){nav();apply();const root=$('main')||document.body;let timer=0;new MutationObserver(()=>{clearTimeout(timer);timer=setTimeout(()=>apply(false),50)}).observe(root,{childList:true,subtree:true})}
document.addEventListener('DOMContentLoaded',boot);
})();