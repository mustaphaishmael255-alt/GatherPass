<?php
/**
 * GatherPass — Digital Ticket & QR Code
 * Discover. Book. Enter.
 */
require_once __DIR__ . '/config.php';

$token = trim($_GET['token'] ?? '');
if ($token === '') {
 header('Location: index.php');
 exit;
}

$conn = getConnection();
$stmt = $conn->prepare(
 "SELECT t.*, e.name AS event_name, e.description AS event_description, 
 e.event_date, e.venue, e.picture AS event_picture, p.full_name AS planner_name
 FROM tickets t
 JOIN events e ON t.event_id = e.id
 LEFT JOIN planners p ON e.planner_id = p.id
 WHERE t.ticket_token = ?"
);
$stmt->bind_param("s", $token);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$ticket) {
 header('Location: index.php');
 exit;
}

$eventDate = strtotime($ticket['event_date']);
$isUsed = ($ticket['status'] === 'used');
$isValid = ($ticket['status'] === 'valid' || $ticket['status'] === 'active');
$verifyUrl = APP_URL . '/verify.php?token=' . urlencode($ticket['ticket_token']);
$qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($verifyUrl) . '&margin=10&bgcolor=0a0e27&color=ffffff&qzone=1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>Your Ticket: <?php echo htmlspecialchars($ticket['event_name']); ?> — GatherPass</title>
 <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
 <style>
 :root {
 --navy: #0a0e27;
 --navy-light: #111640;
 --purple: #7c3aed;
 --purple-glow: #a855f7;
 --blue: #3b82f6;
 --blue-elec: #60a5fa;
 --green: #10b981;
 --gold: #f59e0b;
 --white: #f8fafc;
 --muted: #94a3b8;
 --card-bg: rgba(255, 255, 255, 0.04);
 --card-border: rgba(255, 255, 255, 0.08);
 --radius: 20px;
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
 .glow-1 { width: 500px; height: 500px; background: var(--purple); opacity: .12; top: -150px; right: -100px; }
 .glow-2 { width: 400px; height: 400px; background: var(--blue); opacity: .08; bottom: -100px; left: -50px; }

 .container {
 width: 100%;
 max-width: 580px;
 position: relative;
 z-index: 1;
 }

 .nav-back {
 display: inline-flex;
 align-items: center;
 gap: .5rem;
 color: var(--muted);
 text-decoration: none;
 font-size: .9rem;
 margin-bottom: 1.5rem;
 transition: color .2s;
 }
 .nav-back:hover { color: var(--white); }

 .ticket-card {
 background: linear-gradient(180deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0.02) 100%);
 border: 1px solid var(--card-border);
 border-radius: var(--radius);
 backdrop-filter: blur(20px);
 overflow: hidden;
 box-shadow: 0 20px 50px rgba(0,0,0,0.5);
 position: relative;
 }

 /* Top header of ticket */
 .ticket-header {
 padding: 1.5rem 2rem;
 background: rgba(124, 58, 237, 0.15);
 border-bottom: 1px solid rgba(124, 58, 237, 0.2);
 display: flex;
 align-items: center;
 justify-content: space-between;
 }
 .brand-pill {
 display: flex;
 align-items: center;
 gap: .5rem;
 font-weight: 800;
 font-size: 1.1rem;
 letter-spacing: -0.5px;
 }
 .brand-dot {
 width: 10px;
 height: 10px;
 border-radius: 50%;
 background: linear-gradient(135deg, var(--purple-glow), var(--blue-elec));
 }

 .status-badge {
 display: inline-flex;
 align-items: center;
 gap: .4rem;
 padding: .35rem .75rem;
 border-radius: 9999px;
 font-size: .75rem;
 font-weight: 700;
 text-transform: uppercase;
 letter-spacing: .5px;
 }
 .status-valid {
 background: rgba(16, 185, 129, 0.2);
 color: #34d399;
 border: 1px solid rgba(16, 185, 129, 0.4);
 }
 .status-used {
 background: rgba(148, 163, 184, 0.15);
 color: #94a3b8;
 border: 1px solid rgba(148, 163, 184, 0.3);
 }
 .status-dot {
 width: 7px;
 height: 7px;
 border-radius: 50%;
 background: currentColor;
 }
 .pulse { animation: pulseAnim 2s infinite; }
 @keyframes pulseAnim {
 0%, 100% { opacity: 1; transform: scale(1); }
 50% { opacity: .4; transform: scale(0.85); }
 }

 /* Ticket Body */
 .ticket-body {
 padding: 2rem;
 }
 .event-title {
 font-size: 1.5rem;
 font-weight: 800;
 line-height: 1.25;
 margin-bottom: .5rem;
 color: #fff;
 }
 .event-planner {
 font-size: .85rem;
 color: var(--muted);
 margin-bottom: 1.5rem;
 }

 .grid-info {
 display: grid;
 grid-template-columns: 1fr 1fr;
 gap: 1.25rem;
 margin-bottom: 2rem;
 }
 .info-item {
 display: flex;
 flex-direction: column;
 gap: .25rem;
 }
 .info-label {
 font-size: .75rem;
 color: var(--muted);
 text-transform: uppercase;
 letter-spacing: .5px;
 font-weight: 600;
 }
 .info-value {
 font-size: .95rem;
 font-weight: 600;
 color: var(--white);
 }

 /* Perforated Divider Line */
 .tear-line {
 position: relative;
 height: 1px;
 border-top: 2px dashed rgba(255, 255, 255, 0.15);
 margin: 0 -2rem 2rem;
 }
 .tear-notch {
 position: absolute;
 top: -12px;
 width: 24px;
 height: 24px;
 background: var(--navy);
 border-radius: 50%;
 }
 .tear-left { left: -12px; }
 .tear-right { right: -12px; }

 /* QR Section */
 .qr-section {
 display: flex;
 flex-direction: column;
 align-items: center;
 text-align: center;
 gap: 1rem;
 }
 .qr-frame {
 background: #fff;
 padding: .75rem;
 border-radius: 16px;
 display: inline-flex;
 box-shadow: 0 8px 30px rgba(0,0,0,0.4);
 }
 .qr-frame img {
 display: block;
 width: 180px;
 height: 180px;
 border-radius: 8px;
 }

 .token-display {
 font-family: var(--font-mono);
 font-size: .8rem;
 background: rgba(0, 0, 0, 0.4);
 border: 1px solid var(--card-border);
 padding: .5rem 1rem;
 border-radius: 8px;
 color: var(--blue-elec);
 letter-spacing: 1px;
 display: inline-flex;
 align-items: center;
 justify-content: center;
 gap: .5rem;
 cursor: pointer;
 word-break: break-all;
 max-width: 100%;
 transition: background .2s;
 }
 .token-display:hover {
 background: rgba(255, 255, 255, 0.08);
 }

 .qr-hint {
 font-size: .8rem;
 color: var(--muted);
 max-width: 320px;
 }

 /* Actions */
 .actions-bar {
 display: flex;
 gap: .75rem;
 margin-top: 1.5rem;
 }
 .btn {
 flex: 1;
 display: inline-flex;
 align-items: center;
 justify-content: center;
 gap: .5rem;
 padding: .85rem 1.25rem;
 border-radius: 12px;
 font-weight: 700;
 font-size: .9rem;
 text-decoration: none;
 cursor: pointer;
 border: none;
 transition: all .2s;
 }
 .btn-primary {
 background: linear-gradient(135deg, var(--purple), #6366f1);
 color: #fff;
 box-shadow: 0 4px 20px rgba(124, 58, 237, 0.3);
 }
 .btn-primary:hover {
 transform: translateY(-2px);
 box-shadow: 0 6px 25px rgba(124, 58, 237, 0.45);
 }
 .btn-secondary {
 background: rgba(255, 255, 255, 0.06);
 color: var(--white);
 border: 1px solid var(--card-border);
 }
 .btn-secondary:hover {
 background: rgba(255, 255, 255, 0.1);
 }

 /* Demo badge footer */
 .demo-callout {
 margin-top: 1.5rem;
 padding: 1rem;
 background: rgba(245, 158, 11, 0.08);
 border: 1px solid rgba(245, 158, 11, 0.2);
 border-radius: 12px;
 font-size: .8rem;
 color: var(--gold-warm);
 text-align: center;
 line-height: 1.4;
 }

 @media (max-width: 540px) {
 body { padding: 1rem .75rem 3rem; }
 .ticket-header { padding: 1.25rem; }
 .ticket-body { padding: 1.25rem; }
 .event-title { font-size: 1.3rem; }
 .grid-info { grid-template-columns: 1fr; gap: .85rem; }
 .tear-line { margin: 0 -1.25rem 1.5rem; }
 .actions-bar { flex-direction: column; }
 }

 @media print {
 body { background: #fff; color: #000; padding: 0; }
 .glow, .nav-back, .actions-bar, .demo-callout { display: none !important; }
 .ticket-card { box-shadow: none; border: 2px solid #000; background: #fff; }
 .ticket-header { background: #f0f0f0; border-color: #ccc; }
 .tear-notch { display: none; }
 .token-display { color: #000; border-color: #ccc; background: #f8f8f8; }
 .info-value, .event-title { color: #000 !important; }
 .info-label, .event-planner, .qr-hint { color: #555 !important; }
 }
 </style>
</head>
<body>
 <div class="glow glow-1"></div>
 <div class="glow glow-2"></div>

 <div class="container">
 <a href="index.php" class="nav-back">
 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>Back to Explore Events
 </a>

 <div class="ticket-card">
 <div class="ticket-header">
 <div class="brand-pill">
 <span class="brand-dot"></span>GatherPass
 </div>
 <div class="status-badge <?php echo $isValid ? 'status-valid' : 'status-used'; ?>">
 <span class="status-dot <?php echo $isValid ? 'pulse' : ''; ?>"></span>
 <?php echo $isValid ? 'Valid Ticket' : 'Already Used'; ?>
 </div>
 </div>

 <div class="ticket-body">
 <h1 class="event-title"><?php echo htmlspecialchars($ticket['event_name']); ?></h1>
 <div class="event-planner">Organized by <?php echo htmlspecialchars($ticket['planner_name'] ?? 'Official Organizer'); ?></div>

 <div class="grid-info">
 <div class="info-item">
 <span class="info-label">Date & Time</span>
 <span class="info-value"><?php echo date('D, M j, Y • g:i A', $eventDate); ?></span>
 </div>
 <div class="info-item">
 <span class="info-label">Venue</span>
 <span class="info-value"><?php echo htmlspecialchars($ticket['venue']); ?></span>
 </div>
 <div class="info-item">
 <span class="info-label">Attendee</span>
 <span class="info-value"><?php echo htmlspecialchars($ticket['buyer_name'] ?: 'Guest Attendee'); ?></span>
 </div>
 <div class="info-item">
 <span class="info-label">Tickets</span>
 <span class="info-value"><?php echo intval($ticket['quantity'] ?? 1); ?> × <?php echo htmlspecialchars($ticket['ticket_tier_name'] ?? 'General Admission'); ?></span>
 </div>
 <div class="info-item">
 <span class="info-label">Email</span>
 <span class="info-value"><?php echo htmlspecialchars($ticket['buyer_email']); ?></span>
 </div>
 <div class="info-item">
 <span class="info-label">Amount Paid</span>
 <span class="info-value" style="color: var(--green);"><?php echo formatCurrency($ticket['total_paid']); ?></span>
 </div>
 </div>

 <div class="tear-line">
 <div class="tear-notch tear-left"></div>
 <div class="tear-notch tear-right"></div>
 </div>

 <div class="qr-section">
 <div class="qr-frame">
 <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>" alt="GatherPass QR Code Ticket">
 </div>
 <div class="token-display" onclick="copyToken()" title="Click to copy token">
 <span id="tokenText"><?php echo htmlspecialchars($ticket['ticket_token']); ?></span>
 <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
 </div>
 <p class="qr-hint">Present this QR code or token at event check-in. One scan grants entry for your party.</p>
 </div>
 </div>
 </div>

 <div class="actions-bar">
 <button onclick="window.print()" class="btn btn-secondary">
 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>Print / Save Pass
 </button>
 <a href="verify.php?token=<?php echo urlencode($ticket['ticket_token']); ?>" class="btn btn-primary">
 <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>Test Entry Verification
 </a>
 </div>

 <div class="demo-callout">
 <strong>GatherPass Simulation Mode:</strong>No real payment was charged. This QR ticket is generated live with cryptographic tokens and is ready for real-time scanner validation.
 </div>
 </div>

 <script>function copyToken() {
 const text = document.getElementById('tokenText').innerText;
 navigator.clipboard.writeText(text).then(() => {
 alert('Ticket token copied to clipboard!');
 });
 }
 </script>
</body>
</html>
