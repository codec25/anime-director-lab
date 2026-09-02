(()=>{
'use strict';
const $=(s,r=document)=>r.querySelector(s);
const stages=['perform','review','finish'];
const groups={
 perform:['#castMemoryPanel'],
 review:['#reviewWorkspaceSwitch','#continuityPanel','#soundDesignPanel'],
 finish:['#timelinePanel']
};
const labels={perform:'Performance',review:'Review',finish:'Finish film'};
let active='';
function allToolSelectors(){return [...new Set(Object.values(groups).flat())]}
function ensureSheet(){let sheet=$('#directorToolSheet');if(sheet)return sheet;sheet=document.createElement('div');sheet.id='directorToolSheet';sheet.className='director-tool-sheet';sheet.hidden=true;sheet.setAttribute('role','dialog');sheet.setAttribute('aria-modal','true');sheet.setAttribute('aria-labelledby','directorToolSheetTitle');sheet.innerHTML=`<div class="director-tool-backdrop" data-tool-close></div><section class="director-tool-panel"><header class="director-tool-head"><div><span>ANIME DIRECTOR</span><strong id="directorToolSheetTitle">Tools</strong></div><button type="button" class="director-tool-close" data-tool-close aria-label="Close tools">×</button></header><div class="director-tool-body" id="directorToolBody"></div></section>`;document.body.append(sheet);sheet.addEventListener('click',e=>{if(e.target.closest('[data-tool-close]'))close()});return sheet}
function adopt(){const sheet=ensureSheet(),body=$('#directorToolBody',sheet);if(!body)return;for(const sel of allToolSelectors()){const el=$(sel);if(el&&el.parentElement!==body)body.append(el)}for(const sel of allToolSelectors()){const el=$(sel);if(el)el.classList.toggle('stage-hidden',!active||!(groups[active]||[]).includes(sel))}}
function open(stage){if(!stages.includes(stage))return;active=stage;const sheet=ensureSheet();$('#directorToolSheetTitle',sheet).textContent=labels[stage]||'Tools';adopt();sheet.hidden=false;document.body.classList.add('director-tools-open');document.body.dataset.directorTool=stage;requestAnimationFrame(()=>sheet.classList.add('open'));setTimeout(()=>{adopt();const target=stage==='finish'?$('#timelinePanel'):stage==='review'?($('#reviewWorkspaceSwitch')||$('#continuityPanel')||$('#soundDesignPanel')):$('#castMemoryPanel');target?.scrollIntoView({behavior:'auto',block:'start'})},80)}
function close(){const sheet=$('#directorToolSheet');active='';document.body.classList.remove('director-tools-open');delete document.body.dataset.directorTool;if(!sheet)return;sheet.classList.remove('open');setTimeout(()=>{sheet.hidden=true;adopt()},160)}
function hideBackgroundSystems(){for(const sel of ['#worldMemoryPanel','#sceneMemoryPanel'])$(sel)?.classList.add('stage-hidden')}
function boot(){ensureSheet();hideBackgroundSystems();adopt();document.addEventListener('ad:ui-updated',()=>{hideBackgroundSystems();adopt()});document.addEventListener('ad:open-workspace',e=>{const stage=e.detail?.stage||'direct';if(stage==='direct')close();else open(stage)});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&active){e.preventDefault();close()}});let queued=false;new MutationObserver(()=>{if(queued)return;queued=true;requestAnimationFrame(()=>{queued=false;hideBackgroundSystems();adopt()})}).observe(document.body,{childList:true,subtree:true})}
document.addEventListener('DOMContentLoaded',boot);
})();