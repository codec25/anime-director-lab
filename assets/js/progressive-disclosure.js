(()=>{
'use strict';
const $=(s,r=document)=>r.querySelector(s);
function details(label,cls){const d=document.createElement('details');d.className=`director-details ${cls||''}`.trim();const s=document.createElement('summary');s.innerHTML=`<span>${label}</span><span class="director-details-chevron">›</span>`;d.append(s);return d}
function wrapSound(){const panel=$('#soundDesignPanel');if(!panel)return;
 const mix=[...panel.querySelectorAll('.sound-card')].find(x=>x.querySelector('h3')?.textContent.trim()==='Mix continuity notes');
 if(mix&&!mix.closest('.director-details')){const d=details('Mix continuity notes','sound-secondary');mix.before(d);d.append(mix)}
 const shots=$('#soundShots');if(shots&&!shots.closest('.director-details')){const d=details('Shot SFX & Foley','sound-secondary');shots.before(d);d.append(shots)}
 const note=[...panel.querySelectorAll(':scope > .sound-note')].find(Boolean);if(note)note.classList.add('director-secondary-note');
}
function wrapTimeline(){const panel=$('#timelinePanel');if(!panel)return;
 const audio=$('#timelineAudioMap'),mix=$('#timelineMix');
 if(audio&&!audio.closest('.director-details')){const d=details('Audio tracks','timeline-secondary');audio.before(d);d.append(audio)}
 if(mix&&!mix.closest('.director-details')){const d=details('Mix & master','timeline-secondary');mix.before(d);d.append(mix)}
 const reset=$('#timelineReset'),manifest=$('#timelineManifest');
 if(reset)reset.classList.add('director-secondary-action');if(manifest)manifest.classList.add('director-secondary-action');
 for(const card of panel.querySelectorAll('[data-timeline-clip]')){
   const row=card.querySelector('.timeline-row'),actions=card.querySelector('.timeline-clip-actions');
   if(row&&!row.closest('.director-details')){const d=details('Edit clip','clip-secondary');row.before(d);d.append(row);if(actions)d.append(actions)}
 }
}
function polishContinuity(){const panel=$('#continuityPanel');if(!panel)return;for(const b of panel.querySelectorAll('[data-redirect-shot]'))b.classList.add('director-secondary-action')}
function polishPerform(){for(const b of document.querySelectorAll('[data-acttwo-shot]'))b.classList.add('director-secondary-action')}
function apply(){wrapSound();wrapTimeline();polishContinuity();polishPerform()}
function boot(){apply();let queued=false;const root=$('main')||document.body;new MutationObserver(()=>{if(queued)return;queued=true;requestAnimationFrame(()=>{queued=false;apply()})}).observe(root,{childList:true,subtree:true})}
document.addEventListener('DOMContentLoaded',boot);
})();