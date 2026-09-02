(()=>{
'use strict';
const $=(s,r=document)=>r.querySelector(s);
let menu=null,activeCard=null,longTimer=null,startX=0,startY=0;
const labels=[
 ['compare-takes','Compare takes'],
 ['reference-shot','Add reference'],
 ['revise-shot','Revise shot'],
 ['continue-shot','Continue story'],
 ['dialogue-shot','Dialogue'],
 ['acttwo-shot','Precision acting'],
 ['open-shot','Advanced shot tools']
];
function menuEl(){if(menu)return menu;menu=document.createElement('div');menu.id='shotContextMenu';menu.className='shot-context-menu';menu.hidden=true;menu.setAttribute('role','menu');document.body.append(menu);return menu}
function buttonFor(card,key){return card.querySelector(`[data-${key}]`)}
function shotTitle(card){return card.querySelector('.shot-copy strong')?.textContent?.trim()||'Shot actions'}
function actions(card){const out=[];for(const [key,label] of labels){const b=buttonFor(card,key);if(b&&!b.disabled)out.push({label,button:b,key})}return out}
function close(){if(menu)menu.hidden=true;if(activeCard)activeCard.classList.remove('context-active');activeCard=null}
function open(card,x,y){const items=actions(card);if(!items.length)return;close();activeCard=card;card.classList.add('context-active');const m=menuEl();m.innerHTML=`<div class="shot-context-title">${shotTitle(card).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]))}</div>`+items.map((a,i)=>`${i===items.length-1&&a.key==='open-shot'&&items.length>1?'<div class="shot-context-separator"></div>':''}<button class="shot-context-item" type="button" role="menuitem" data-context-index="${i}">${a.label}</button>`).join('');m.hidden=false;const pad=12,w=m.offsetWidth,h=m.offsetHeight;let left=Math.max(pad,Math.min(x,window.innerWidth-w-pad));let top=Math.max(pad,Math.min(y,window.innerHeight-h-pad));m.style.left=`${left}px`;m.style.top=`${top}px`;m.querySelectorAll('[data-context-index]').forEach(b=>b.onclick=()=>{const a=items[Number(b.dataset.contextIndex)];close();setTimeout(()=>a?.button?.click(),0)});m.querySelector('[data-context-index]')?.focus({preventScroll:true})}
function patch(){document.querySelectorAll('.shot-card').forEach(card=>{const box=card.querySelector('.shot-actions');if(!box||box.classList.contains('context-ready'))return;box.classList.add('context-ready');const more=document.createElement('button');more.type='button';more.className='shot-action shot-more';more.setAttribute('aria-label','More shot actions');more.setAttribute('title','More actions');more.textContent='•••';more.onclick=e=>{e.preventDefault();e.stopPropagation();const r=more.getBoundingClientRect();open(card,r.right-260,Math.min(r.bottom+6,window.innerHeight-20))};box.append(more)})}
function keyNav(e){if(!menu||menu.hidden)return;const items=[...menu.querySelectorAll('.shot-context-item')],i=items.indexOf(document.activeElement);if(e.key==='Escape'){e.preventDefault();close();return}if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();const d=e.key==='ArrowDown'?1:-1;items[(i+d+items.length)%items.length]?.focus()}}
function boot(){patch();const root=$('#directorScenes');if(root)new MutationObserver(()=>setTimeout(patch,40)).observe(root,{childList:true,subtree:true});document.addEventListener('contextmenu',e=>{const card=e.target.closest('.shot-card');if(!card)return;e.preventDefault();open(card,e.clientX,e.clientY)});document.addEventListener('pointerdown',e=>{if(e.pointerType==='mouse')return;const card=e.target.closest('.shot-card');if(!card||e.target.closest('button,input,textarea,select,a,video'))return;startX=e.clientX;startY=e.clientY;clearTimeout(longTimer);longTimer=setTimeout(()=>open(card,startX,startY),520)});document.addEventListener('pointermove',e=>{if(Math.abs(e.clientX-startX)>10||Math.abs(e.clientY-startY)>10)clearTimeout(longTimer)});document.addEventListener('pointerup',()=>clearTimeout(longTimer));document.addEventListener('pointercancel',()=>clearTimeout(longTimer));document.addEventListener('click',e=>{if(menu&&!menu.hidden&&!e.target.closest('#shotContextMenu')&&!e.target.closest('.shot-more'))close()});document.addEventListener('keydown',keyNav);window.addEventListener('blur',close);window.addEventListener('resize',close);window.addEventListener('scroll',()=>{if(menu&&!menu.hidden)close()},{passive:true})}
document.addEventListener('DOMContentLoaded',boot);
})();