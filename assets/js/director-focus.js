(()=>{
'use strict';
const $=(s,r=document)=>r.querySelector(s);
function focusPrompt(seed=''){
 const input=$('#directorPrompt'); if(!input)return;
 if(seed) input.value=seed;
 input.placeholder='What happens next? Direct the action, camera, emotion, dialogue or mood…';
 input.focus({preventScroll:false});
 input.dispatchEvent(new Event('input',{bubbles:true}));
 input.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'center'});
}
function workspace(stage){document.dispatchEvent(new CustomEvent('ad:open-workspace',{detail:{stage}}))}
function patch(){
 const cards=[...document.querySelectorAll('#directorScenes .shot-card')];
 cards.forEach(c=>c.classList.remove('director-current-shot'));
 const card=cards[0]; if(!card)return;
 card.classList.add('director-current-shot');
 const main=card.querySelector('.shot-main'); if(!main||card.querySelector('.director-next-actions'))return;
 const media=card.querySelector('.shot-thumb video');
 const takeReady=!!media;
 const bar=document.createElement('div'); bar.className='director-next-actions';
 bar.innerHTML=`<div class="director-next-copy"><span>${takeReady?'CURRENT SHOT · APPROVED TAKE':'CURRENT SHOT'}</span><strong>${takeReady?'What happens next?':'Make this shot.'}</strong></div><div class="director-next-buttons">${takeReady?'<button type="button" data-focus-continue>Continue</button><button type="button" data-focus-camera>Camera</button><button type="button" data-focus-performance>Performance</button><button type="button" data-focus-dialogue>Dialogue</button>':''}<button type="button" data-focus-more aria-label="More shot controls">•••</button></div>`;
 main.append(bar);
 bar.querySelector('[data-focus-continue]')?.addEventListener('click',()=>focusPrompt('Continue naturally from this moment. '));
 bar.querySelector('[data-focus-camera]')?.addEventListener('click',()=>focusPrompt('Keep everything consistent. Change the camera to '));
 bar.querySelector('[data-focus-performance]')?.addEventListener('click',()=>{workspace('perform');setTimeout(()=>card.querySelector('[data-acttwo-shot]')?.click(),180)});
 bar.querySelector('[data-focus-dialogue]')?.addEventListener('click',()=>{workspace('perform');setTimeout(()=>card.querySelector('[data-dialogue-shot]')?.click(),180)});
 bar.querySelector('[data-focus-more]')?.addEventListener('click',()=>card.querySelector('.shot-more')?.click());
 const title=$('#shotSectionTitle'); if(title)title.textContent=takeReady?'Current shot':'Your film';
 if(takeReady){const input=$('#directorPrompt');if(input)input.placeholder='What happens next? Direct the action, camera, emotion, dialogue or mood…'}
}
function boot(){patch();document.addEventListener('ad:shots-rendered',()=>requestAnimationFrame(patch));document.addEventListener('ad:ui-updated',()=>requestAnimationFrame(patch))}
document.addEventListener('DOMContentLoaded',boot);
})();