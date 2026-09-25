<?php
/**
 * GatherPass — Event Details & Checkout
 * Shows event info, pricing, ticket selection, and demo checkout.
 */
require_once __DIR__ . '/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header('Location: index.php'); exit; }

$conn = getConnection();
$stmt = $conn->prepare(
 "SELECT e.*, p.full_name AS planner_name
 FROM events e
 LEFT JOIN planners p ON e.planner_id = p.id
 WHERE e.id = ? AND e.is_active = TRUE"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$ev = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ev) { header('Location: index.php'); exit; }

$base = floatval($ev['base_ticket_price']);
$fee = $base * PLATFORM_FEE_PERCENTAGE;
$total = $base + $fee;
$remaining = intval($ev['available_tickets'] ?? 0);
$dt = strtotime($ev['event_date']);
$img = !empty($ev['picture']) ? $ev['picture'] : 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=800&q=80';

// Handle demo checkout POST
$checkoutResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
 $buyerName = trim($_POST['buyer_name'] ?? '');
 $buyerEmail = trim($_POST['buyer_email'] ?? '');
 $buyerPhone = trim($_POST['buyer_phone'] ?? '');
 $qty = max(1, min(10, intval($_POST['quantity'] ?? 1)));

 $errors = [];
 if ($buyerName === '') $errors[] = 'Name is required.';
 if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
 if ($qty < 1 || $qty > 10) $errors[] = 'Quantity must be 1–10.';
 if ($qty > $remaining) $errors[] = 'Not enough tickets available.';

 if (empty($errors)) {
 // Server-side price calculation (never trust client)
 $serverBase = $base;
 $serverFee = $base * PLATFORM_FEE_PERCENTAGE;
 $serverTotal = ($serverBase + $serverFee) * $qty;
 $token = bin2hex(random_bytes(16));

 $ins = $conn->prepare(
 "INSERT INTO tickets (event_id, buyer_name, buyer_email, buyer_phone, quantity, ticket_type, ticket_price, platform_fee, total_paid, ticket_token, status)
 VALUES (?, ?, ?, ?, ?, 'General Admission', ?, ?, ?, ?, 'valid')"
 );
 $unitTotal = $serverBase + $serverFee;
 $ins->bind_param("isssiddds",
 $id, $buyerName, $buyerEmail, $buyerPhone, $qty,
 $serverBase, $serverFee, $serverTotal, $token
 );

 if ($ins->execute()) {
 // Decrement available tickets
 $conn->query("UPDATE events SET available_tickets = GREATEST(0, available_tickets - {$qty}) WHERE id = {$id}");
 // Redirect to ticket page
 header("Location: ticket.php?token=" . urlencode($token));
 exit;
 } else {
 $errors[] = 'Could not create ticket. Please try again.';
 }
 $ins->close();
 }
 $checkoutResult = $errors;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title><?php echo htmlspecialchars($ev['name']); ?> — GatherPass</title>
 <meta name="description" content="<?php echo htmlspecialchars(substr($ev['description'] ?? '', 0, 160)); ?>">
 <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
 <style>
 :root {
 --navy:#0a0e27;--navy-light:#111640;--purple:#7c3aed;--purple-glow:#a855f7;
 --blue:#3b82f6;--blue-elec:#60a5fa;--gold:#f59e0b;--gold-warm:#fbbf24;
 --white:#f8fafc;--muted:#94a3b8;--card-bg:rgba(255,255,255,.04);
 --card-border:rgba(255,255,255,.08);--radius:16px;
 --font:'Inter',system-ui,sans-serif;
 }
 *{margin:0;padding:0;box-sizing:border-box}
 body{font-family:var(--font);background:var(--navy);color:var(--white);min-height:100vh}

 .glow{position:fixed;border-radius:50%;filter:blur(120px);pointer-events:none;z-index:0}
 .glow-1{width:500px;height:500px;background:var(--purple);opacity:.1;top:-150px;right:-100px}
 .glow-2{width:400px;height:400px;background:var(--blue);opacity:.07;bottom:-100px;left:-50px}

 /* Nav */
 .nav{position:sticky;top:0;z-index:100;background:rgba(10,14,39,.85);backdrop-filter:blur(20px);border-bottom:1px solid var(--card-border);padding:0 24px}
 .nav-inner{max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;height:64px}
 .nav-brand{font-size:1.4rem;font-weight:800;letter-spacing:-.5px;background:linear-gradient(135deg,var(--purple-glow),var(--blue-elec));-webkit-background-clip:text;-webkit-text-fill-color:transparent;text-decoration:none}
 .nav-back{color:var(--muted);text-decoration:none;font-size:.875rem;font-weight:500;padding:8px 14px;border-radius:8px;transition:all .2s}
 .nav-back:hover{color:var(--white);background:var(--card-bg)}

 .page{max-width:900px;margin:0 auto;padding:24px;position:relative;z-index:1}

 /* Hero image */
 .event-hero{width:100%;height:320px;object-fit:cover;border-radius:var(--radius);display:block;margin-bottom:28px}

 /* Info section */
 .event-info{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);padding:28px;margin-bottom:24px}
 .event-info h1{font-size:1.8rem;font-weight:800;margin-bottom:8px;line-height:1.2}
 .event-tagline{color:var(--muted);font-size:.95rem;margin-bottom:20px;line-height:1.7}
 .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
 .info-item{display:flex;gap:10px;align-items:flex-start}
 .info-icon{font-size:1.3rem;flex-shrink:0}
 .info-label{font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
 .info-value{font-weight:600;font-size:.95rem}

 /* Pricing */
 .pricing{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);padding:28px;margin-bottom:24px}
 .pricing h2{font-size:1.2rem;font-weight:700;margin-bottom:18px}
 .price-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--card-border);font-size:.95rem}
 .price-row:last-child{border-bottom:none}
 .price-total{font-weight:800;font-size:1.1rem;color:var(--gold)}
 .price-total span{color:var(--gold)}

 /* Checkout form */
 .checkout{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);padding:28px;margin-bottom:24px}
 .checkout h2{font-size:1.2rem;font-weight:700;margin-bottom:6px}
 .checkout-note{color:var(--gold);font-size:.8rem;margin-bottom:18px;font-weight:500}
 .form-group{margin-bottom:16px}
 .form-group label{display:block;font-size:.85rem;font-weight:600;color:var(--muted);margin-bottom:6px}
 .form-group input,.form-group select{
 width:100%;padding:12px 16px;background:var(--navy);
 border:1px solid var(--card-border);border-radius:10px;
 color:var(--white);font-size:.95rem;font-family:var(--font);
 transition:border-color .2s;outline:none;
 }
 .form-group input:focus,.form-group select:focus{border-color:var(--purple)}
 .form-group input::placeholder{color:var(--muted)}

 .qty-wrap{display:flex;align-items:center;gap:12px}
 .qty-btn{width:42px;height:42px;border-radius:10px;border:1px solid var(--card-border);background:var(--card-bg);color:var(--white);font-size:1.2rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s}
 .qty-btn:hover{border-color:var(--purple);background:var(--purple);color:#fff}
 .qty-btn:disabled{opacity:.4;cursor:not-allowed;background:var(--card-bg)}
 .qty-num{font-size:1.3rem;font-weight:700;min-width:40px;text-align:center}

 .submit-btn{
 width:100%;padding:16px;border:none;border-radius:12px;
 background:linear-gradient(135deg,var(--purple),var(--blue));
 color:#fff;font-size:1.05rem;font-weight:700;cursor:pointer;
 font-family:var(--font);transition:all .2s;
 }
 .submit-btn:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(124,58,237,.4)}
 .submit-btn:disabled{opacity:.5;cursor:not-allowed;transform:none}

 .error-box{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:14px;margin-bottom:18px;color:#fca5a5;font-size:.875rem}
 .sold-out{color:#f87171;font-weight:700;font-size:1rem}

 /* Footer */
 .footer{border-top:1px solid var(--card-border);background:rgba(0,0,0,.3);padding:30px 24px;text-align:center;margin-top:40px;position:relative;z-index:1}
 .footer-brand{font-size:1.1rem;font-weight:800;background:linear-gradient(135deg,var(--purple-glow),var(--blue-elec));-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:6px}
 .footer-copy{color:var(--muted);font-size:.75rem;opacity:.6}

 @media(max-width:768px){
 .nav { padding: 0 16px; }
 .page { padding: 16px; }
 .event-info, .pricing, .checkout { padding: 18px; border-radius: 14px; }
 .info-grid { grid-template-columns: 1fr; gap: 12px; }
 .event-hero { height: 200px; margin-bottom: 20px; }
 .event-info h1 { font-size: 1.5rem; }
 }

 @media(max-width:480px){
 .page { padding: 12px 10px; }
 .qty-wrap { justify-content: center; }
 .submit-btn { padding: 14px; font-size: 1rem; }
 }
 </style>
</head>
<body>
 <div class="glow glow-1"></div>
 <div class="glow glow-2"></div>

 <nav class="nav">
 <div class="nav-inner">
 <a href="index.php" class="nav-brand">GatherPass</a>
 <a href="index.php" class="nav-back">← Back to events</a>
 </div>
 </nav>

 <div class="page">
 <img class="event-hero"
 src="<?php echo htmlspecialchars($img); ?>"
 alt="<?php echo htmlspecialchars($ev['name']); ?>">

 <!-- Event Info -->
 <div class="event-info">
 <h1><?php echo htmlspecialchars($ev['name']); ?></h1>
 <p class="event-tagline"><?php echo nl2br(htmlspecialchars($ev['description'] ?? '')); ?></p>

 <div class="info-grid">
 <div class="info-item">
 <span class="info-icon"></span>
 <div>
 <div class="info-label">Date & Time</div>
 <div class="info-value"><?php echo date('F j, Y · g:i A', $dt); ?></div>
 </div>
 </div>
 <div class="info-item">
 <span class="info-icon"></span>
 <div>
 <div class="info-label">Venue</div>
 <div class="info-value"><?php echo htmlspecialchars($ev['venue']); ?></div>
 </div>
 </div>
 <div class="info-item">
 <span class="info-icon"></span>
 <div>
 <div class="info-label">Organizer</div>
 <div class="info-value"><?php echo htmlspecialchars($ev['planner_name'] ?? 'GatherPass'); ?></div>
 </div>
 </div>
 <div class="info-item">
 <span class="info-icon"></span>
 <div>
 <div class="info-label">Availability</div>
 <div class="info-value"><?php echo $remaining > 0 ? "{$remaining} tickets remaining" : '<span class="sold-out">Sold Out</span>'; ?></div>
 </div>
 </div>
 </div>
 </div>

 <!-- Pricing -->
 <div class="pricing">
 <h2>Pricing Breakdown</h2>
 <div class="price-row">
 <span>Base ticket price</span>
 <span><?php echo APP_CURRENCY_SYMBOL . number_format($base, 2); ?></span>
 </div>
 <div class="price-row">
 <span>Platform fee (<?php echo PLATFORM_FEE_LABEL; ?>)</span>
 <span><?php echo APP_CURRENCY_SYMBOL . number_format($fee, 2); ?></span>
 </div>
 <div class="price-row price-total">
 <span>Total per ticket</span>
 <span><?php echo APP_CURRENCY_SYMBOL . number_format($total, 2); ?></span>
 </div>
 </div>

 <!-- Checkout -->
 <?php if ($remaining > 0): ?>
 <div class="checkout">
 <h2>Get Your Pass</h2>
 <div class="checkout-note">This is a demo checkout — no real payment is collected.</div>

 <?php if (!empty($checkoutResult)): ?>
 <div class="error-box">
 <?php foreach ($checkoutResult as $err): ?>
 <div><?php echo htmlspecialchars($err); ?></div>
 <?php endforeach; ?>
 </div>
 <?php endif; ?>

 <form method="POST" id="checkoutForm">
 <input type="hidden" name="checkout" value="1">

 <div class="form-group">
 <label for="quantity">Number of Tickets</label>
 <div class="qty-wrap">
 <button type="button" class="qty-btn" id="qtyDown" onclick="changeQty(-1)">−</button>
 <div class="qty-num" id="qtyDisplay">1</div>
 <button type="button" class="qty-btn" id="qtyUp" onclick="changeQty(1)">+</button>
 <input type="hidden" name="quantity" id="qtyInput" value="1">
 <span style="color:var(--muted);font-size:.8rem;margin-left:8px" id="qtyInfo">Max <?php echo min(10, $remaining); ?></span>
 </div>
 </div>

 <div class="form-group">
 <label for="buyer_name">Full Name</label>
 <input type="text" id="buyer_name" name="buyer_name" placeholder="Jane Doe" required
 value="<?php echo htmlspecialchars($_POST['buyer_name'] ?? ''); ?>">
 </div>

 <div class="form-group">
 <label for="buyer_email">Email Address</label>
 <input type="email" id="buyer_email" name="buyer_email" placeholder="jane@example.com" required
 value="<?php echo htmlspecialchars($_POST['buyer_email'] ?? ''); ?>">
 </div>

 <div class="form-group">
 <label for="buyer_phone">Phone Number (optional)</label>
 <input type="tel" id="buyer_phone" name="buyer_phone" placeholder="+233 000 000 000"
 value="<?php echo htmlspecialchars($_POST['buyer_phone'] ?? ''); ?>">
 </div>

 <button type="submit" class="submit-btn" id="submitBtn">Complete Demo Checkout — <?php echo APP_CURRENCY_SYMBOL; ?><span id="totalDisplay"><?php echo number_format($total, 2); ?></span>
 </button>
 </form>
 </div>
 <?php else: ?>
 <div class="checkout" style="text-align:center;padding:40px">
 <div style="font-size:2.5rem;margin-bottom:12px"></div>
 <p class="sold-out">This event is sold out</p>
 <p style="color:var(--muted);margin-top:8px">Check back later or browse other events.</p>
 <a href="index.php" style="display:inline-block;margin-top:18px;color:var(--blue-elec);text-decoration:none;font-weight:600">← Browse events</a>
 </div>
 <?php endif; ?>
 </div>

 <footer class="footer">
 <div class="footer-brand">GatherPass</div>
 <div class="footer-copy">© <?php echo date('Y'); ?>GatherPass · Discover. Book. Enter.</div>
 </footer>

 <script>const pricePerTicket = <?php echo $total; ?>;
 const maxQty = <?php echo min(10, $remaining); ?>;
 let qty = 1;

 function changeQty(d) {
 qty = Math.max(1, Math.min(maxQty, qty + d));
 document.getElementById('qtyDisplay').textContent = qty;
 document.getElementById('qtyInput').value = qty;
 document.getElementById('totalDisplay').textContent = (pricePerTicket * qty).toLocaleString('en', {minimumFractionDigits:2, maximumFractionDigits:2});
 document.getElementById('qtyDown').disabled = qty <= 1;
 document.getElementById('qtyUp').disabled = qty >= maxQty;
 }

 // Prevent double-submit
 document.getElementById('checkoutForm')?.addEventListener('submit', function() {
 document.getElementById('submitBtn').disabled = true;
 document.getElementById('submitBtn').textContent = 'Processing…';
 });
 </script>
</body>
</html>
