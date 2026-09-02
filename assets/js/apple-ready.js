(()=>{
'use strict';
function setKeyboardState(){if(!window.visualViewport)return;const delta=window.innerHeight-window.visualViewport.height;document.documentElement.classList.toggle('keyboard-open',delta>140)}
function stageAccessibility(){document.querySelectorAll('[data-stage]').forEach(btn=>{const active=btn.classList.contains('active');btn.setAttribute('aria-pressed',active?'true':'false');if(active)btn.setAttribute('aria-current','step');else btn.removeAttribute('aria-current')})}
function enhanceInputs(){const prompt=document.querySelector('#directorPrompt');if(prompt){prompt.setAttribute('enterkeyhint','send');prompt.setAttribute('autocapitalize','sentences');prompt.setAttribute('autocomplete','off');prompt.setAttribute('spellcheck','true')}}
function registerSW(){if(!('serviceWorker'in navigator)||location.protocol!=='https:')return;navigator.serviceWorker.register('sw.js',{scope:'./'}).catch(()=>{})}
function boot(){enhanceInputs();stageAccessibility();if(window.visualViewport){window.visualViewport.addEventListener('resize',setKeyboardState,{passive:true});window.visualViewport.addEventListener('scroll',setKeyboardState,{passive:true});setKeyboardState()}const nav=document.querySelector('#productionStageNav');if(nav)new MutationObserver(stageAccessibility).observe(nav,{attributes:true,subtree:true,attributeFilter:['class']});document.addEventListener('click',e=>{if(e.target.closest('[data-stage]'))requestAnimationFrame(stageAccessibility)},true);registerSW()}
document.addEventListener('DOMContentLoaded',boot);
})();
