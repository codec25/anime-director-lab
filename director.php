<?php declare(strict_types=1); require_once __DIR__ . '/app/bootstrap.php'; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#08080b">
<title>Anime Director</title>
<link rel="stylesheet" href="assets/css/director.css?v=002">
<link rel="stylesheet" href="assets/css/world-memory.css?v=001">
<link rel="stylesheet" href="assets/css/semantic-references.css?v=001">
<link rel="stylesheet" href="assets/css/scene-memory.css?v=001">
</head>
<body>
<div class="director-shell">
  <header class="director-topbar">
    <div class="brand"><span class="brand-mark">AD</span><span>Anime Director</span></div>
    <div class="top-actions"><a class="ghost-btn" href="lab.php" style="text-decoration:none;display:grid;place-items:center">Advanced</a><button class="icon-btn" id="newDirection" type="button">＋ New</button></div>
  </header>

  <main>
    <section class="hero">
      <span class="eyebrow">✦ Director Mode</span>
      <h1><span>Direct the anime</span><br>in your head.</h1>
      <p>Describe the story, action, camera, mood, or change. Anime Director turns your direction into shots while keeping character identity, world memory, scene memory, references, and production continuity attached.</p>
    </section>

    <section class="director-card" aria-label="Director prompt">
      <div class="director-inner">
        <div class="context-row">
          <div class="character-context"><span class="character-dot" id="directorCharacterAvatar"></span><span class="status-dot" id="directorStatusDot"></span><span id="directorCharacterName">Loading character…</span></div>
          <span class="character-context" id="directorSceneCount">0 shots</span>
        </div>
        <textarea class="director-input" id="directorPrompt" rows="3" placeholder="Describe the anime scene, feeling, or change you want…" aria-label="Direct your next scene"></textarea>
        <div class="compose-actions">
          <div class="compose-left"><button class="mode-btn" id="directorMode" type="button">Guide me ▾</button></div>
          <div class="compose-right"><button class="send-btn" id="directorSend" type="button" aria-label="Send direction">↑</button></div>
        </div>
      </div>
    </section>

    <div class="quick-row" id="quickRow" aria-label="Quick direction ideas"></div>
    <section class="conversation" id="conversation" aria-live="polite"></section>

    <section>
      <div class="section-head"><div><h2>Your production</h2><p>Latest directed shots. Generate a take here or open advanced controls when a shot needs exact performance driving.</p></div><a class="text-link" href="lab.php#scene">Open scene →</a></div>
      <div class="scene-list" id="directorScenes"><div class="empty">Loading production…</div></div>
    </section>

    <section class="workspace-grid" style="margin-top:22px">
      <article class="mini-panel"><h3>Director memory</h3><p>Character identity, World Memory, and Scene Memory belong to your production, not one AI provider. Shot direction stays attached even if the generation provider changes.</p></article>
      <article class="mini-panel"><h3>Perform it when needed</h3><p>Natural language is the fast path. For movement-critical animation, use ACT IT in Advanced and drive the character from your own performance.</p></article>
    </section>

    <section>
      <div class="section-head"><div><h2>Production capabilities</h2><p>Only working capabilities are shown as ready.</p></div></div>
      <div class="feature-list" id="capabilityGrid"></div>
    </section>
  </main>
</div>
<nav class="bottom-nav" aria-label="Primary navigation">
  <a href="director.php"><span class="nav-icon">⌂</span><span>Home</span></a>
  <a href="lab.php#character"><span class="nav-icon">◎</span><span>Characters</span></a>
  <a href="#" id="createNav" class="active"><span class="bottom-create">✦</span><span style="margin-top:25px">Direct</span></a>
  <a href="lab.php"><span class="nav-icon">▦</span><span>Advanced</span></a>
</nav>
<script>window.AD_CONFIG={mock:<?= ad_mock_mode() ? 'true' : 'false' ?>};</script>
<script src="assets/js/scene-memory.js?v=001"></script>
<script src="assets/js/director.js?v=002"></script>
<script src="assets/js/world-memory.js?v=001"></script>
<script src="assets/js/semantic-references.js?v=001"></script>
</body>
</html>
