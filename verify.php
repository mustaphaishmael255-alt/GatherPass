<?php
/**
 * GatherPass — Entry Verification & Gate Scanner
 * Discover. Book. Enter.
 */
require_once __DIR__ . '/config.php';

$tokenInput = trim($_GET['token'] ?? $_POST['token'] ?? '');
$action = $_POST['action'] ?? '';
$verificationResult = null;

if ($tokenInput !== '') {
 $conn = getConnection();
 
 // Look up ticket
 $stmt = $conn->prepare(
 "SELECT t.*, e.name AS event_name, e.event_date, e.venue
 FROM tickets t
 JOIN events e ON t.event_id = e.id
 WHERE t.ticket_token = ?"
 );
 $stmt->bind_param("s", $tokenInput);
 $stmt->execute();
 $ticket = $stmt->get_result()->fetch_assoc();
 $stmt->close();

 if (!$ticket) {
 $verificationResult = [
 'status' => 'invalid',
 'title' => 'Invalid Ticket',
 'message' => 'No ticket matching this token was found in the database.',
 'token' => $tokenInput
 ];
 } elseif ($ticket['status'] === 'used') {
 $verificationResult = [
 'status' => 'warning',
 'title' => 'Already Scanned / Used',
 'message' => 'This ticket has already been used for entry.',
 'ticket' => $ticket
 ];
 } elseif ($ticket['status'] === 'valid' || $ticket['status'] === 'active') {
 // Mark as used if verifying or if auto-admit is active
 $upd = $conn->prepare("UPDATE tickets SET status = 'used', updated_at = NOW() WHERE ticket_token = ? AND status != 'used'");
 $upd->bind_param("s", $tokenInput);
 $upd->execute();
 $upd->close();

 $verificationResult = [
 'status' => 'success',
 'title' => 'Entry Granted',
 'message' => 'Valid ticket. Guest is cleared for entry.',
 'ticket' => $ticket
 ];
 } else {
 $verificationResult = [
 'status' => 'invalid',
 'title' => 'Ticket Cancelled / Void',
 'message' => 'This ticket is not active.',
 'ticket' => $ticket
 ];
 }
 
 $conn->close();
}

// Return JSON if requested via AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || isset($_GET['format']) && $_GET['format'] === 'json') {
 header('Content-Type: application/json');
 echo json_encode($verificationResult ?? ['status' => 'error', 'message' => 'No token supplied']);
 exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>Gate Verification — GatherPass</title>
 <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
 <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
 <style>
 :root {
 --navy: #0a0e27;
 --navy-light: #111640;
 --purple: #7c3aed;
 --purple-glow: #a855f7;
 --blue: #3b82f6;
 --green: #10b981;
 --green-glow: #34d399;
 --red: #ef4444;
 --gold: #f59e0b;
 --white: #f8fafc;
 --muted: #94a3b8;
 --card-bg: rgba(255, 255, 255, 0.04);
 --card-border: rgba(255, 255, 255, 0.08);
 --radius: 18px;
 --font: 'Inter', system-ui, sans-serif;
 --font-mono: 'JetBrains Mono', monospace;
 }

 * { margin: 0; padding: 0; box-sizing: border-box; }
 body {
 font-family: var(--font);
 background: var(--navy);
 color: var(--white);
 min-height: 100vh;
 display: flex;
 flex-direction: column;
 align-items: center;
 padding: 2rem 1rem 4rem;
 }

 .glow {
 position: fixed;
 border-radius: 50%;
 filter: blur(120px);
 pointer-events: none;
 z-index: 0;
 }
 .glow-1 { width: 500px; height: 500px; background: var(--purple); opacity: .1; top: -150px; right: -100px; }
 .glow-2 { width: 400px; height: 400px; background: var(--blue); opacity: .08; bottom: -100px; left: -50px; }

 .container {
 width: 100%;
 max-width: 620px;
 position: relative;
 z-index: 1;
 }

 .header {
 display: flex;
 align-items: center;
 justify-content: space-between;
 margin-bottom: 2rem;
 }
 .brand {
 display: flex;
 align-items: center;
 gap: .5rem;
 font-size: 1.25rem;
 font-weight: 800;
 text-decoration: none;
 color: #fff;
 }
 .brand-dot {
 width: 10px;
 height: 10px;
 border-radius: 50%;
 background: linear-gradient(135deg, var(--purple-glow), #60a5fa);
 }

 .card {
 background: var(--card-bg);
 border: 1px solid var(--card-border);
 border-radius: var(--radius);
 backdrop-filter: blur(20px);
 padding: 1.75rem;
 margin-bottom: 1.5rem;
 }

 /* Result State Alerts */
 .result-box {
 padding: 1.75rem;
 border-radius: var(--radius);
 margin-bottom: 1.5rem;
 display: flex;
 flex-direction: column;
 gap: 1rem;
 animation: slideDown .3s ease-out;
 }
 @keyframes slideDown {
 from { opacity: 0; transform: translateY(-10px); }
 to { opacity: 1; transform: translateY(0); }
 }

 .res-success {
 background: rgba(16, 185, 129, 0.12);
 border: 1px solid rgba(16, 185, 129, 0.35);
 }
 .res-warning {
 background: rgba(245, 158, 11, 0.12);
 border: 1px solid rgba(245, 158, 11, 0.35);
 }
 .res-invalid {
 background: rgba(239, 68, 68, 0.12);
 border: 1px solid rgba(239, 68, 68, 0.35);
 }

 .res-header {
 display: flex;
 align-items: center;
 gap: .75rem;
 }
 .res-icon {
 width: 44px;
 height: 44px;
 border-radius: 50%;
 display: flex;
 align-items: center;
 justify-content: center;
 font-size: 1.3rem;
 font-weight: 800;
 flex-shrink: 0;
 }
 .res-success .res-icon { background: var(--green); color: #fff; }
 .res-warning .res-icon { background: var(--gold); color: #000; }
 .res-invalid .res-icon { background: var(--red); color: #fff; }

 .res-title { font-size: 1.3rem; font-weight: 800; }
 .res-success .res-title { color: var(--green-glow); }
 .res-warning .res-title { color: var(--gold); }
 .res-invalid .res-title { color: #f87171; }

 .res-desc { font-size: .9rem; color: var(--muted); }

 .ticket-details-grid {
 display: grid;
 grid-template-columns: 1fr 1fr;
 gap: .75rem 1.25rem;
 background: rgba(0, 0, 0, 0.25);
 padding: 1rem;
 border-radius: 12px;
 font-size: .85rem;
 }
 .td-item span { display: block; }
 .td-label { color: var(--muted); font-size: .75rem; text-transform: uppercase; letter-spacing: .5px; }
 .td-val { color: var(--white); font-weight: 600; margin-top: .15rem; }

 /* Scanner Area */
 .scanner-tabs {
 display: flex;
 gap: .5rem;
 margin-bottom: 1.25rem;
 }
 .tab-btn {
 flex: 1;
 padding: .65rem 1rem;
 background: rgba(255, 255, 255, 0.04);
 border: 1px solid var(--card-border);
 color: var(--muted);
 border-radius: 10px;
 cursor: pointer;
 font-weight: 600;
 font-size: .85rem;
 transition: all .2s;
 }
 .tab-btn.active {
 background: rgba(124, 58, 237, 0.2);
 border-color: var(--purple-glow);
 color: #fff;
 }

 #reader {
 width: 100%;
 border-radius: 12px;
 overflow: hidden;
 border: 2px dashed rgba(255, 255, 255, 0.15);
 background: #000;
 }

 .input-group {
 display: flex;
 gap: .5rem;
 }
 .text-input {
 flex: 1;
 background: rgba(0, 0, 0, 0.35);
 border: 1px solid var(--card-border);
 border-radius: 12px;
 padding: .85rem 1rem;
 color: #fff;
 font-family: var(--font-mono);
 font-size: .95rem;
 }
 .text-input:focus {
 outline: none;
 border-color: var(--purple-glow);
 box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.25);
 }

 .btn-submit {
 background: linear-gradient(135deg, var(--purple), #6366f1);
 color: #fff;
 border: none;
 border-radius: 12px;
 padding: .85rem 1.5rem;
 font-weight: 700;
 cursor: pointer;
 transition: all .2s;
 }
 .btn-submit:hover { opacity: .9; transform: translateY(-1px); }

 .btn-reset {
 display: block;
 width: 100%;
 text-align: center;
 background: rgba(255, 255, 255, 0.08);
 color: #fff;
 padding: .85rem;
 border-radius: 12px;
 text-decoration: none;
 font-weight: 700;
 margin-top: 1rem;
 transition: background .2s;
 }
 .btn-reset:hover { background: rgba(255, 255, 255, 0.15); }

 /* Responsive Mobile Styles */
 @media (max-width: 600px) {
 body { padding: 1rem .75rem 3rem; }
 .header { flex-direction: column; align-items: flex-start; gap: .75rem; margin-bottom: 1.25rem; }
 .header >a:last-child { align-self: flex-start; }
 .card { padding: 1.25rem; border-radius: 14px; }
 .result-box { padding: 1.25rem; border-radius: 14px; }
 .res-title { font-size: 1.15rem; }
 .ticket-details-grid { grid-template-columns: 1fr; gap: .65rem; padding: .85rem; }
 .scanner-tabs { gap: .35rem; }
 .tab-btn { padding: .55rem .5rem; font-size: .8rem; }
 .input-group { flex-direction: column; gap: .6rem; }
 .btn-submit { width: 100%; padding: .85rem; }
 #reader { min-height: 240px; }
 #reader video { width: 100% !important; max-height: 320px; object-fit: cover; }
 }
 </style>
</head>
<body>
 <div class="glow glow-1"></div>
 <div class="glow glow-2"></div>

 <div class="container">
 <div class="header">
 <a href="index.php" class="brand">
 <span class="brand-dot"></span>GatherPass Gate Scanner
 </a>
 <a href="index.php" style="color: var(--muted); font-size: .85rem; text-decoration: none;">← Public Site</a>
 </div>

 <?php if ($verificationResult): ?>
 <div class="result-box res-<?php echo $verificationResult['status']; ?>">
 <div class="res-header">
 <div class="res-icon">
 <?php if ($verificationResult['status'] === 'success'): ?>
 <?php elseif ($verificationResult['status'] === 'warning'): ?>!
 <?php else: ?><?php endif; ?>
 </div>
 <div>
 <div class="res-title"><?php echo htmlspecialchars($verificationResult['title']); ?></div>
 <div class="res-desc"><?php echo htmlspecialchars($verificationResult['message']); ?></div>
 </div>
 </div>

 <?php if (!empty($verificationResult['ticket'])): $t = $verificationResult['ticket']; ?>
 <div class="ticket-details-grid">
 <div class="td-item">
 <span class="td-label">Event</span>
 <span class="td-val"><?php echo htmlspecialchars($t['event_name']); ?></span>
 </div>
 <div class="td-item">
 <span class="td-label">Guest</span>
 <span class="td-val"><?php echo htmlspecialchars($t['buyer_name'] ?: 'Guest'); ?></span>
 </div>
 <div class="td-item">
 <span class="td-label">Quantity</span>
 <span class="td-val"><?php echo intval($t['quantity'] ?? 1); ?>Ticket(s)</span>
 </div>
 <div class="td-item">
 <span class="td-label">Paid</span>
 <span class="td-val"><?php echo formatCurrency($t['total_paid']); ?></span>
 </div>
 </div>
 <?php endif; ?>

 <a href="verify.php" class="btn-reset">Scan Next Attendee</a>
 </div>
 <?php endif; ?>

 <div class="card">
 <div class="scanner-tabs">
 <button class="tab-btn active" id="tabCamera" onclick="switchMode('camera')">Camera Scanner</button>
 <button class="tab-btn" id="tabManual" onclick="switchMode('manual')">Enter Token</button>
 </div>

 <!-- Camera section -->
 <div id="cameraSection">
 <div id="reader"></div>
 <p style="text-align: center; color: var(--muted); font-size: .8rem; margin-top: .75rem;">Point your device camera at attendee's GatherPass QR Code.
 </p>
 </div>

 <!-- Manual input section -->
 <div id="manualSection" style="display: none;">
 <form method="GET" action="verify.php">
 <div class="input-group">
 <input type="text" name="token" class="text-input" placeholder="Paste 32-character token here..." required autofocus>
 <button type="submit" class="btn-submit">Verify</button>
 </div>
 </form>
 </div>
 </div>
 </div>

 <script>function playAudioFeedback(type) {
 try {
 const ctx = new (window.AudioContext || window.webkitAudioContext)();
 const osc = ctx.createOscillator();
 const gain = ctx.createGain();
 osc.connect(gain);
 gain.connect(ctx.destination);

 if (type === 'success') {
 osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
 osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1); // A5
 gain.gain.setValueAtTime(0.3, ctx.currentTime);
 gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
 osc.start(ctx.currentTime);
 osc.stop(ctx.currentTime + 0.3);
 } else {
 osc.frequency.setValueAtTime(220, ctx.currentTime); // A3
 gain.gain.setValueAtTime(0.4, ctx.currentTime);
 gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
 osc.start(ctx.currentTime);
 osc.stop(ctx.currentTime + 0.4);
 }
 } catch(e) {}
 }

 <?php if ($verificationResult): ?>playAudioFeedback('<?php echo $verificationResult['status']; ?>');
 <?php endif; ?>function switchMode(mode) {
 const camSec = document.getElementById('cameraSection');
 const manSec = document.getElementById('manualSection');
 const tabCam = document.getElementById('tabCamera');
 const tabMan = document.getElementById('tabManual');

 if (mode === 'camera') {
 camSec.style.display = 'block';
 manSec.style.display = 'none';
 tabCam.classList.add('active');
 tabMan.classList.remove('active');
 startScanner();
 } else {
 camSec.style.display = 'none';
 manSec.style.display = 'block';
 tabCam.classList.remove('active');
 tabMan.classList.add('active');
 stopScanner();
 }
 }

 let html5QrCode = null;
 function startScanner() {
 if (!html5QrCode) {
 html5QrCode = new Html5Qrcode("reader");
 }
 html5QrCode.start(
 { facingMode: "environment" },
 { fps: 10, qrbox: { width: 250, height: 250 } },
 (decodedText) => {
 // Check if URL with token or raw token
 let token = decodedText;
 try {
 const url = new URL(decodedText);
 const t = url.searchParams.get('token');
 if (t) token = t;
 } catch(e) {}

 stopScanner();
 window.location.href = 'verify.php?token=' + encodeURIComponent(token);
 },
 (errorMessage) => {}
 ).catch(err => {
 console.warn("Camera start failed, fallback to manual input", err);
 });
 }

 function stopScanner() {
 if (html5QrCode && html5QrCode.isScanning) {
 html5QrCode.stop().catch(() => {});
 }
 }

 // Auto-start scanner on load if not viewing a result
 <?php if (!$verificationResult): ?>window.addEventListener('DOMContentLoaded', () => {
 startScanner();
 });
 <?php endif; ?>
 </script>
</body>
</html>
