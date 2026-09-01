<?php declare(strict_types=1); require_once __DIR__ . '/app/bootstrap.php'; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#08080b">
<title>Anime Director</title>
<link rel="stylesheet" href="assets/css/director.css?v=001">
</head>
<body>
<div class="director-shell">
  <header class="director-topbar">
    <div class="brand"><span class="brand-mark">AD</span><span>Anime Director</span></div>
    <div class="top-actions"><a class="ghost-btn" href="index.php" style="text-decoration:none;display:grid;place-items:center">Lab</a><button class="icon-btn" id="newDirection" type="button">＋ New</button></div>
  </header>

  <main>
    <section class="hero">
      <span class="eyebrow">✦ Director Mode</span>
      <h1><span>Direct the anime</span><br>in your head.</h1>
      <p>Describe the story, action, camera, mood, or change. Anime Director turns your direction into production-ready shots while keeping your character and scene structure attached.</p>
    </section>

    <section class="director-card" aria-label="Director prompt">
      <div class="director-inner">
        <div class="context-row">
          <div class="character-context"><span class="character-dot" id="directorCharacterAvatar"></span><span class="status-dot" id="directorStatusDot"></span><span id="directorCharacterName">Loading character…</span></div>
          <span class="character-context" id="directorSceneCount">0 shots</span>
        </div>
        <textarea class="director-input" id="directorPrompt" rows="3" placeholder="Describe the anime scene, feeling, or change you want…" aria-label="Direct your next scene"></textarea>
        <div class="attachment-strip" id="attachmentStrip"></div>
        <div class="compose-actions">
          <div class="compose-left"><button class="round-btn" id="directorAttach" type="button" title="Add reference">＋</button><button class="mode-btn" id="directorMode" type="button">Guide me ▾</button></div>
          <div class="compose-right"><button class="send-btn" id="directorSend" type="button" aria-label="Send direction">↑</button></div>
        </div>
        <input class="hidden-input" id="directorFile" type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,audio/*" multiple>
      </div>
    </section>

    <div class="quick-row" id="quickRow" aria-label="Quick direction ideas"></div>
    <section class="conversation" id="conversation" aria-live="polite"></section>

    <section>
      <div class="section-head"><div><h2>Your production</h2><p>Latest directed shots. Generate a take here or open the shot in the technical Lab.</p></div><a class="text-link" href="index.php#scene">Open scene →</a></div>
      <div class="scene-list" id="directorScenes"><div class="empty">Loading production…</div></div>
    </section>

    <section class="workspace-grid" style="margin-top:22px">
      <article class="mini-panel"><h3>Director memory</h3><p>Character identity is owned by your Character Bible, not by a single AI provider. Direction is stored per shot so you can change providers without losing the creative intent.</p></article>
      <article class="mini-panel"><h3>Perform it + describe it</h3><p>Use natural-language direction for fast anime shots. For movement-critical animation, use ACT IT in Lab and drive the character from your own performance instead of asking text alone to guess the motion.</p></article>
    </section>

    <section>
      <div class="section-head"><div><h2>Production capabilities</h2><p>Green is usable in the current architecture. Amber is still planned.</p></div></div>
      <div class="feature-list" id="capabilityGrid"></div>
    </section>
  </main>
</div>
<nav class="bottom-nav" aria-label="Primary navigation">
  <a href="director.php"><span class="nav-icon">⌂</span><span>Home</span></a>
  <a href="index.php#character"><span class="nav-icon">◎</span><span>Characters</span></a>
  <a href="#" id="createNav" class="active"><span class="bottom-create">✦</span><span style="margin-top:25px">Direct</span></a>
  <a href="index.php"><span class="nav-icon">▦</span><span>Lab</span></a>
</nav>
<script>window.AD_CONFIG={mock:<?= ad_mock_mode() ? 'true' : 'false' ?>};</script>
<script src="assets/js/director.js?v=001"></script>
<script src="assets/js/director-generate.js?v=001"></script>
</body>
</html>
