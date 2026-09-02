(()=>{
'use strict';
const configs=[
 {panel:'worldMemoryPanel',label:'Edit world',title:'World memory'},
 {panel:'sceneMemoryPanel',label:'Scene options',title:'Scene memory'},
 {panel:'castMemoryPanel',label:'Cast setup',title:'Cast and voices'}
];
function disclosure(cfg,panel,body){
 const inline=document.createElement('div');inline.className='memory-sheet-inline';inline.hidden=true;inline.append(body);panel.append(inline);
 const b=document.createElement('button');b.type='button';b.className='shot-action memory-sheet-trigger';b.textContent=cfg.label;b.setAttribute('aria-expanded','false');
 b.onclick=()=>{const opening=inline.hidden;inline.hidden=!opening;b.setAttribute('aria-expanded',String(opening));b.textContent=opening?'Done':cfg.label;if(opening){inline.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'nearest'});inline.querySelector('input,textarea,select,button')?.focus({preventScroll:true})}};
 panel.append(b)
}
function apply(){for(const cfg of configs){const panel=document.getElementById(cfg.panel);if(!panel||panel.dataset.memorySheet==='1')continue;const details=panel.querySelector('.memory-disclosure'),body=details?.querySelector('.memory-disclosure-body');if(!details||!body)continue;disclosure(cfg,panel,body);details.remove();panel.dataset.memorySheet='1'}}
function boot(){apply();document.addEventListener('ad:ui-updated',apply)}
document.addEventListener('DOMContentLoaded',boot);
})();