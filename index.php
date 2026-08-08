<?php declare(strict_types=1); require_once __DIR__ . '/app/bootstrap.php'; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Anime Director Lab 0.01</title>
<link rel="stylesheet" href="assets/css/app.css?v=001">
</head>
<body>
<div class="shell">
<header class="topbar">
  <div><span class="eyebrow">RESEARCH LAB 0.01</span><h1>Anime Director</h1><p>Perform it. Direct it. Animate it.</p></div>
  <div class="top-actions"><span id="modeBadge" class="badge">Loading…</span><button id="refreshBtn" class="btn ghost">Refresh</button></div>
</header>
<nav class="steps" aria-label="Lab workflow">
  <button data-jump="character">1 <span>Character Lock</span></button><button data-jump="performance">2 <span>ACT IT</span></button><button data-jump="shot">3 <span>Shot</span></button><button data-jump="takes">4 <span>Takes</span></button><button data-jump="benchmark">5 <span>Benchmark</span></button>
</nav>
<main>
<section class="hero panel">
  <div><span class="kicker">THE AKIO TEST</span><h2>One character. Your movement. Evidence before hype.</h2><p>First preserve the performance. Then test how far we can amplify it into anime action.</p></div>
  <div class="hero-stats"><div><strong id="statPerformances">0</strong><span>performances</span></div><div><strong id="statTakes">0</strong><span>takes</span></div><div><strong id="statUsable">0</strong><span>usable</span></div><div><strong id="statCost">$0</strong><span>est. spend</span></div></div>
</section>

<section id="character" class="workspace-grid">
  <div class="panel stage"><div class="section-title"><span>01</span><div><h3>Character Lock</h3><p>The canonical character belongs to Anime Director—not a provider.</p></div></div><div id="characterCard" class="character-card empty"><div class="avatar-placeholder">AK</div><div><strong>No character locked</strong><p>Upload the master reference for AKIO-v1.</p></div></div></div>
  <form id="characterForm" class="panel form-card"><label>Name<input name="name" value="Akio" required></label><label>Version<input name="version" value="v1" required></label><label>Master reference<input name="image" type="file" accept="image/jpeg,image/png,image/webp" required></label><label>Identity notes<textarea name="notes" rows="4">Dark hair with one silver streak, amber eyes, navy jacket, red sleeve accent, black pants, simple shoes.</textarea></label><button class="btn primary" type="submit">Lock character</button></form>
</section>

<section id="performance" class="panel"><div class="section-title"><span>02</span><div><h3>ACT IT — Performance Library</h3><p>Upload controlled 3–30 second source performances. Keep the original file as the source of truth.</p></div></div>
<div class="split"><form id="performanceForm" class="form-card inset"><div class="row"><label>Test code<select name="code" id="perfCode"><option>A1</option><option>A2</option><option>A3</option><option>A4</option><option>B1</option><option>B2</option><option>B3</option><option>B4</option></select></label><label>Track<select name="track"><option value="acting">Acting / upper body</option><option value="full_body">Full body</option></select></label></div><label>Label<input name="label" value="Neutral acting" required></label><label>Performance video<input name="video" type="file" accept="video/mp4,video/quicktime" required></label><label>Duration (seconds)<input name="duration_seconds" type="number" min="3" max="30" step="0.1" value="5"></label><label>Notes<textarea name="notes" rows="3" placeholder="Exact movement sequence…"></textarea></label><button class="btn primary" type="submit">Add performance</button></form><div id="performanceList" class="cards"></div></div></section>

<section id="shot" class="workspace-grid"><div class="panel"><div class="section-title"><span>03</span><div><h3>Create controlled shot</h3><p>ACT IT measures fidelity. Anime Boost is stored as a separate direction layer.</p></div></div><form id="shotForm" class="form-card"><label>Performance<select id="shotPerformance" name="performance_id"></select></label><label>Shot intent<textarea name="intent" rows="3" placeholder="Akio steps forward and throws a controlled jab-cross."></textarea></label><div class="row"><label>Frame<select name="ratio"><option value="1280:720">16:9 Landscape</option><option value="720:1280">9:16 Portrait</option><option value="960:960">1:1 Square</option><option value="1104:832">4:3</option></select></label><label>Anime Boost<select name="boost"><option value="natural">Natural — fidelity first</option><option value="anime">Anime — stylized amplification</option><option value="extreme">Extreme — stress test</option></select></label></div><button class="btn primary" type="submit">Create shot</button></form></div><div class="panel"><div class="section-title"><span>AI</span><div><h3>Provider bench</h3><p>Maximum 3 attempts per provider per shot.</p></div></div><div id="providerBench" class="provider-bench"></div></div></section>

<section id="takes" class="panel"><div class="section-title"><span>04</span><div><h3>Takes</h3><p>Compare failures too. A miracle after 20 retries is not a production-ready workflow.</p></div></div><div id="shotsList" class="shot-list"></div></section>

<section id="benchmark" class="panel"><div class="section-title"><span>05</span><div><h3>Benchmark</h3><p>Character Preservation + Performance Preservation + Director Usability.</p></div></div><div id="benchmarkSummary" class="benchmark-summary"></div><div id="scoreEditor" class="score-editor empty-state">Select a take to score it.</div></section>
</main>
<footer><span>Anime Director Lab 0.01</span><span>SHORTS remains a separate product.</span></footer>
</div>
<script>window.AD_CONFIG={mock:<?= ad_mock_mode() ? 'true' : 'false' ?>};</script><script src="assets/js/app.js?v=001"></script>
</body></html>
