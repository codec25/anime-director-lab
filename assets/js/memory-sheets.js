(()=>{
'use strict';
const configs=[
 {panel:'worldMemoryPanel',label:'Edit world',collect(panel){return [document.getElementById('worldRefRow'),document.getElementById('worldForm')].filter(Boolean)}},
 {panel:'sceneMemoryPanel',label:'Scene options',collect(panel){
  const toolbar=panel.querySelector('.scene-memory-toolbar'),select=toolbar?.querySelector('#sceneSelect');
  if(select&&!panel.querySelector('.memory-quick-row')){const quick=document.createElement('div');quick.className='memory-quick-row';const label=document.createElement('span');label.textContent='Active scene';quick.append(label,select);panel.querySelector('.scene-memory-head')?.insertAdjacentElement('afterend',quick)}
  return [toolbar,document.getElementById('sceneMemoryRefs'),panel.querySelector('.scene-inherit-note'),document.getElementById('sceneMemoryForm')].filter(Boolean)
 }},
 {panel:'castMemoryPanel',label:'Cast setup',collect(panel){return [document.getElementById('speakerForm'),panel.querySelector('.voice-note')].filter(Boolean)}}
];
function disclosure(cfg,panel,nodes){
 const inline=document.createElement('div');inline.className='memory-sheet-inline';inline.hidden=true;nodes.forEach(node=>inline.append(node));panel.append(inline);
 const button=document.createElement('button');button.type='button';button.className='shot-action memory-sheet-trigger';button.textContent=cfg.label;button.setAttribute('aria-expanded','false');
 button.onclick=()=>{const opening=inline.hidden;inline.hidden=!opening;button.setAttribute('aria-expanded',String(opening));button.textContent=opening?'Done':cfg.label;if(opening){inline.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'nearest'});inline.querySelector('input,textarea,select,button')?.focus({preventScroll:true})}};
 panel.append(button)
}
function bindCast(panel){const list=document.getElementById('castList');if(!list||list.dataset.castMenus)return;list.addEventListener('click',event=>{if(!event.target.closest('[data-cast-edit],[data-cast-identity],[data-cast-voice]'))return;const inline=panel.querySelector('.memory-sheet-inline'),button=panel.querySelector('.memory-sheet-trigger');if(inline?.hidden)button?.click()},true);list.dataset.castMenus='1'}
function apply(){for(const cfg of configs){const panel=document.getElementById(cfg.panel);if(!panel||panel.dataset.memorySheet==='1')continue;const nodes=cfg.collect(panel);if(!nodes.length)continue;disclosure(cfg,panel,nodes);panel.dataset.memorySheet='1';if(cfg.panel==='castMemoryPanel')bindCast(panel)}}
function boot(){apply();document.addEventListener('ad:ui-updated',apply)}
document.addEventListener('DOMContentLoaded',boot);
})();