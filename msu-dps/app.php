<?php require __DIR__ . '/inc/bootstrap.php';
if (check_timeout()) { header('Location: login.php?expired=1'); exit; }
$u = me(); if (!$u) { header('Location: login.php'); exit; }
if (!isset($_SESSION['last_activity'])) $_SESSION['last_activity'] = time();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$boot = ['me' => $u, 'csrf' => $_SESSION['csrf'], 'timeout' => SESSION_TIMEOUT]; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>MSU Dropout Prediction System</title>
<link rel="icon" type="image/png" href="assets/img/logo.png"><link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head><body>
<div class="shell">
  <aside id="side" class="sidebar">
    <div class="brand"><img src="assets/img/logo.png" alt="MSU crest"><div>Dropout<br><span>Prediction System</span></div></div>
    <nav class="nav" id="nav"></nav>
    <div class="side-foot muted small">Midlands State University</div>
  </aside>
  <div class="scrim" id="scrim"></div>
  <div class="content">
    <header class="topbar">
      <button class="burger" id="burger" aria-label="Open menu" aria-expanded="false"><span></span><span></span><span></span></button>
      <h2 id="title">Dashboard</h2>
      <div class="top-right"><button class="bell hidden" id="bell" aria-label="Alerts">🔔<b id="bellN">0</b></button>
        <div class="user-chip"><span class="avatar" id="av"></span><span class="uc-text"><b id="uname"></b><small id="urole"></small></span></div>
        <button class="btn btn-secondary" id="logout">Sign out</button></div>
    </header>
    <main class="main" id="view"></main>
  </div>
</div>
<div id="modal" class="modal hidden"></div><div id="toast" class="toast"></div>
<script>window.BOOT=<?= json_encode($boot) ?>;</script>
<script src="assets/js/session.js"></script>
<script src="assets/js/app.js"></script>
</body></html>
