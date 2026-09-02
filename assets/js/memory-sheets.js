(()=>{
'use strict';
const configs=[
 {panel:'worldMemoryPanel',label:'Edit world',title:'World memory',description:'Identity, visual rules and references that should remain consistent.'},
 {panel:'sceneMemoryPanel',label:'Scene options',title:'Scene memory',description:'Environment, lighting, plates and scene inheritance.'},
 {panel:'castMemoryPanel',label:'Cast setup',title:'Cast and voices',description:'Recurring character identity, references and voice direction.'}
];
function sheet(cfg,panel,body){
 const d=document.createElement('dialog');d.id=`${cfg.panel}Sheet`;d.className='memory-sheet';d.innerHTML=`<div class="memory-sheet-shell"><div class="memory-sheet-head"><div><span>PRODUCTION MEMORY</span><h2>${cfg.title}</h2><p>${cfg.description}</p></div><button class="shot-action" type="button" data-close-memory>Done</button></div><div class="memory-sheet-content"></div></div>`;d.querySelector('.memory-sheet-content').append(body);document.body.append(d);d.querySelector('[data-close-memory]').onclick=()=>d.close();d.addEventListener('click',e=>{if(e.target===d)d.close()});
 const b=document.createElement('button');b.type='button';b.className='shot-action memory-sheet-trigger';b.textContent=cfg.label;b.setAttribute('aria-haspopup','dialog');b.onclick=()=>d.showModal();panel.append(b)
}
function apply(){for(const cfg of configs){const panel=document.getElementById(cfg.panel);if(!panel||panel.dataset.memorySheet==='1')continue;const details=panel.querySelector('.memory-disclosure'),body=details?.querySelector('.memory-disclosure-body');if(!details||!body)continue;sheet(cfg,panel,body);details.remove();panel.dataset.memorySheet='1'}}
function boot(){apply();let timer=0;const root=document.querySelector('main')||document.body;new MutationObserver(()=>{clearTimeout(timer);timer=setTimeout(apply,70)}).observe(root,{childList:true,subtree:true})}
document.addEventListener('DOMContentLoaded',boot);
})();