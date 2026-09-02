(()=>{
'use strict';
const $=(s,r=document)=>r.querySelector(s);
let view=localStorage.getItem('ad_review_workspace')==='sound'?'sound':'continuity',timer=0;
function inject(){
 const continuity=$('#continuityPanel'),sound=$('#soundDesignPanel');
 if(!continuity||!sound||$('#reviewWorkspaceSwitch'))return;
 const bar=document.createElement('div');
 bar.id='reviewWorkspaceSwitch';bar.className='review-workspace-switch';bar.setAttribute('role','tablist');bar.setAttribute('aria-label','Review workspace');
 bar.innerHTML='<button type="button" role="tab" data-review-view="continuity">Continuity</button><button type="button" role="tab" data-review-view="sound">Sound</button>';
 continuity.before(bar);
 bar.addEventListener('click',e=>{const b=e.target.closest('[data-review-view]');if(!b)return;view=b.dataset.reviewView;localStorage.setItem('ad_review_workspace',view);apply();const panel=view==='sound'?$('#soundDesignPanel'):$('#continuityPanel');panel?.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'})});
}
function apply(){
 inject();const continuity=$('#continuityPanel'),sound=$('#soundDesignPanel'),bar=$('#reviewWorkspaceSwitch');if(!continuity||!sound||!bar)return;
 continuity.classList.toggle('review-workspace-hidden',view!=='continuity');sound.classList.toggle('review-workspace-hidden',view!=='sound');
 bar.querySelectorAll('[data-review-view]').forEach(b=>{const active=b.dataset.reviewView===view;b.classList.toggle('active',active);b.setAttribute('aria-selected',active?'true':'false');b.tabIndex=active?0:-1});
}
function boot(){apply();document.addEventListener('ad:ui-updated',apply)}
document.addEventListener('DOMContentLoaded',boot);
})();