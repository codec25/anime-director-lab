(()=>{
'use strict';
let queued=false,timer=0;
function emit(){queued=false;document.dispatchEvent(new CustomEvent('ad:ui-updated'))}
function schedule(){if(queued)return;queued=true;clearTimeout(timer);timer=setTimeout(()=>requestAnimationFrame(emit),32)}
function boot(){const root=document.querySelector('main')||document.body;new MutationObserver(schedule).observe(root,{childList:true,subtree:true});setTimeout(()=>document.dispatchEvent(new CustomEvent('ad:ui-updated')),0)}
document.addEventListener('DOMContentLoaded',boot);
})();