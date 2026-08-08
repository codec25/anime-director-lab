<?php declare(strict_types=1); require_once __DIR__ . '/app/bootstrap.php'; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Anime Director Lab 0.01</title>
<link rel="stylesheet" href="assets/css/app.css?v=002">
</head>
<body>
<div class="shell">
<header class="topbar">
  <div>
    <span class="eyebrow">RESEARCH LAB 0.01</span>
    <h1>Anime Director</h1>
    <p class="tagline">Perform it. Direct it. Animate it.</p>
  </div>
  <div class="top-actions"><span id="modeBadge" class="badge">Loading…</span><button id="refreshBtn" class="btn ghost">Refresh</button></div>
</header>

<nav class="steps" aria-label="Lab workflow">
  <button data-jump="character">1 <span>Character</span></button>
  <button data-jump="performance">2 <span>Perform</span></button>
  <button data-jump="shot">3 <span>Shot</span></button>
  <button data-jump="takes">4 <span>Takes</span></button>
  <button data-jump="scene">5 <span>Scene</span></button>
  <button data-jump="benchmark">6 <span>Benchmark</span></button>
</nav>

<main>
<section class="hero panel">
  <div>
    <span class="kicker">THE AKIO TEST</span>
    <h2>One character. Your movement. Evidence before hype.</h2>
    <p>Character Lock owns identity. ACT IT preserves performance. Anime Boost is a separate direction layer — not a fake claim of solved VFX.</p>
  </div>
  <div class="hero-stats">
    <div><strong id="statPerformances">0</strong><span>performances</span></div>
    <div><strong id="statTakes">0</strong><span>generated takes</span></div>
    <div><strong id="statUsable">0</strong><span>usable</span></div>
    <div><strong id="statCost">$0.00</strong><span id="statCostLabel">est. live cost</span></div>
  </div>
</section>

<section id="character" class="workspace-grid">
  <div class="panel stage">
    <div class="section-title"><span>01</span><div><h3>Character Bible / Lock</h3><p>Canonical identity belongs to Anime Director — not a provider.</p></div></div>
    <div id="characterCard" class="character-card empty"><div class="avatar-placeholder">AK</div><div><strong>No character locked</strong><p>Create AKIO-v1 from upload or import.</p></div></div>
    <div id="referenceGrid" class="reference-grid"></div>
    <div id="bindingList" class="binding-list"></div>
  </div>
  <div class="panel">
    <div class="section-title"><span>+</span><div><h3>Create Character</h3><p>Upload art now. Drawing and generation stay future plugs.</p></div></div>
    <div class="create-choices" id="createChoices">
      <button type="button" class="choice active" data-source="upload"><strong>Upload Character</strong><span>Master reference art</span></button>
      <button type="button" class="choice" data-source="import_sheet"><strong>Import Character Sheet</strong><span>Use sheet as master front</span></button>
      <button type="button" class="choice future" data-source="generated" disabled><strong>Generated Reference</strong><span>Future / provider</span></button>
      <button type="button" class="choice future" data-source="draw" disabled><strong>Draw Character</strong><span>Future canvas</span></button>
    </div>
    <form id="characterForm" class="form-card">
      <input type="hidden" name="source" id="characterSource" value="upload">
      <input type="hidden" name="lock" value="1">
      <div class="row"><label>Name<input name="name" value="Akio" required></label><label>Version<input name="version" value="v1" required></label></div>
      <label>Canonical style<input name="canonical_style" value="Clean modern anime, grounded proportions"></label>
      <label>Description<textarea name="description" rows="2">AKIO-v1 — persistent original anime character for ACT IT benchmarks.</textarea></label>
      <div class="row"><label>Body / build<textarea name="body_notes" rows="2">Lean athletic young adult build.</textarea></label><label>Facial identity<textarea name="facial_notes" rows="2">Sharp jaw, amber eyes, composed expression.</textarea></label></div>
      <div class="row"><label>Hairstyle<textarea name="hairstyle_notes" rows="2">Dark hair with one silver streak.</textarea></label><label>Eyes / color<textarea name="eye_notes" rows="2">Amber irises.</textarea></label></div>
      <div class="row"><label>Outfit<textarea name="outfit_notes" rows="2">Navy jacket, red sleeve accent, black pants, simple shoes.</textarea></label><label>Movement personality<textarea name="movement_notes" rows="2">Controlled, precise, slight coiled energy.</textarea></label></div>
      <label>Voice notes<textarea name="voice_notes" rows="2">Calm mid tone; reserved until action beats.</textarea></label>
      <label id="primaryImageLabel">Master front (required)<input name="image" id="characterImage" type="file" accept="image/jpeg,image/png,image/webp" required></label>
      <label class="hidden" id="sheetLabel">Character sheet<input name="sheet" id="characterSheet" type="file" accept="image/jpeg,image/png,image/webp"></label>
      <button class="btn primary" type="submit">Lock character</button>
    </form>
    <form id="referenceForm" class="form-card inset">
      <div class="section-title compact"><div><h3>Add / replace reference</h3><p>AKIO-v1 needs master_front, three_quarter, portrait. Others optional.</p></div></div>
      <div class="row">
        <label>Role<select name="role" id="referenceRole"></select></label>
        <label>Image<input name="image" type="file" accept="image/jpeg,image/png,image/webp" required></label>
      </div>
      <button class="btn" type="submit">Upload reference</button>
    </form>
  </div>
</section>

<section id="performance" class="panel">
  <div class="section-title"><span>02</span><div><h3>ACT IT — Performance Library</h3><p>Human source performances stay separate from generated takes.</p></div></div>
  <div class="split">
    <form id="performanceForm" class="form-card inset">
      <div class="row">
        <label>Test code<select name="code" id="perfCode"><option>A1</option><option>A2</option><option>A3</option><option>A4</option><option>B1</option><option>B2</option><option>B3</option><option>B4</option></select></label>
        <label>Track<select name="track"><option value="acting">Acting / upper body</option><option value="full_body">Full body</option></select></label>
      </div>
      <label>Label<input name="label" value="Neutral acting" required></label>
      <label>Performance video<input name="video" type="file" accept="video/mp4,video/quicktime" required></label>
      <label>Duration (seconds)<input name="duration_seconds" type="number" min="3" max="30" step="0.1" value="5"></label>
      <label>Notes<textarea name="notes" rows="3" placeholder="Exact movement sequence…"></textarea></label>
      <button class="btn primary" type="submit">Add performance</button>
    </form>
    <div id="performanceList" class="cards"></div>
  </div>
</section>

<section id="shot" class="workspace-grid">
  <div class="panel">
    <div class="section-title"><span>03</span><div><h3>Create controlled shot</h3><p>ACT IT measures fidelity. DESCRIBE IT is architecture-only. Anime Boost is direction metadata.</p></div></div>
    <form id="shotForm" class="form-card">
      <label>Title<input name="title" placeholder="A1 Neutral hold"></label>
      <label id="shotPerformanceLabel">Performance<select id="shotPerformance" name="performance_id"></select></label>
      <label>Shot intent<textarea name="intent" rows="3" placeholder="Akio steps forward and throws a controlled jab-cross."></textarea></label>
      <label>Direction<textarea name="direction" rows="2" placeholder="Keep eyeline camera-left; no camera move."></textarea></label>
      <div class="row">
        <label>Generation mode<select name="generation_mode" id="shotGenerationMode"><option value="ACT_IT">ACT IT</option><option value="DESCRIBE_IT">DESCRIBE IT (future)</option></select></label>
        <label>Camera<input name="camera_direction" placeholder="Medium close-up, static"></label>
      </div>
      <div class="row">
        <label>Frame<select name="ratio"><option value="1280:720">16:9 Landscape</option><option value="720:1280">9:16 Portrait</option><option value="960:960">1:1 Square</option><option value="1104:832">4:3</option><option value="832:1104">3:4</option><option value="1584:672">21:9</option></select></label>
        <label>Anime Boost<select name="boost"><option value="natural">Natural — fidelity first</option><option value="anime">Anime — stylized amplification</option><option value="extreme">Extreme — stress test</option></select></label>
      </div>
      <button class="btn primary" type="submit">Create shot</button>
    </form>
  </div>
  <div class="panel">
    <div class="section-title"><span>AI</span><div><h3>Capability gateway</h3><p>App code requests capabilities. Providers are adapters. Max 3 attempts / provider / shot.</p></div></div>
    <div id="providerBench" class="provider-bench"></div>
    <div class="boost-note">
      <strong>Anime Boost layers</strong>
      <ol>
        <li>AI motion/style enhancement (provider-dependent; stored as direction)</li>
        <li>Editable post/compositing effects (future — speed lines, impact flash, SFX…)</li>
      </ol>
      <p id="futureEffects" class="muted"></p>
    </div>
  </div>
</section>

<section id="takes" class="panel">
  <div class="section-title"><span>04</span><div><h3>Takes</h3><p>Performance takes stay source-of-truth. Generated takes are AI executions to compare and choose.</p></div></div>
  <div id="shotsList" class="shot-list"></div>
</section>

<section id="scene" class="panel">
  <div class="section-title"><span>05</span><div><h3>Scene / Animatic</h3><p>Ordered filmstrip of selected takes. Missing takes show placeholder planning frames.</p></div></div>
  <div id="sceneBoard" class="scene-board"></div>
</section>

<section id="benchmark" class="panel">
  <div class="section-title"><span>06</span><div><h3>Benchmark</h3><p>Character Preservation + Performance Preservation + Director Usability.</p></div></div>
  <div id="benchmarkSummary" class="benchmark-summary"></div>
  <div id="benchmarkTable" class="benchmark-table"></div>
  <div id="scoreEditor" class="score-editor empty-state">Select a take to score it.</div>
</section>
</main>
<footer><span>Anime Director Lab 0.01 Foundation</span><span>SHORTS remains a separate product.</span></footer>
</div>
<script>window.AD_CONFIG={mock:<?= ad_mock_mode() ? 'true' : 'false' ?>};</script>
<script src="assets/js/app.js?v=002"></script>
</body>
</html>
