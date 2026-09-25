<?php
/**
 * GatherPass — Home / Event Discovery
 * Discover. Book. Enter.
 */
require_once __DIR__ . '/config.php';

$conn = getConnection();

// Search support
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
 $stmt = $conn->prepare(
 "SELECT e.*, p.full_name AS planner_name
 FROM events e
 LEFT JOIN planners p ON e.planner_id = p.id
 WHERE e.is_active = TRUE
 AND (e.name LIKE ? OR e.description LIKE ? OR e.venue LIKE ?)
 ORDER BY e.event_date ASC"
 );
 $like = "%{$search}%";
 $stmt->bind_param("sss", $like, $like, $like);
 $stmt->execute();
 $events = $stmt->get_result();
} else {
 $events = $conn->query(
 "SELECT e.*, p.full_name AS planner_name
 FROM events e
 LEFT JOIN planners p ON e.planner_id = p.id
 WHERE e.is_active = TRUE
 ORDER BY e.event_date ASC"
 );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>GatherPass — Discover. Book. Enter.</title>
 <meta name="description" content="<?php echo APP_DESCRIPTION; ?>">
 <link rel="preconnect" href="https://fonts.googleapis.com">
 <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
 <style>
 /* ── Design Tokens ──────────────────────────────── */
 :root {
 --navy: #0a0e27;
 --navy-light: #111640;
 --purple: #7c3aed;
 --purple-glow:#a855f7;
 --blue: #3b82f6;
 --blue-elec: #60a5fa;
 --gold: #f59e0b;
 --gold-warm: #fbbf24;
 --white: #f8fafc;
 --muted: #94a3b8;
 --card-bg: rgba(255,255,255,0.04);
 --card-border:rgba(255,255,255,0.08);
 --radius: 16px;
 --font: 'Inter', system-ui, -apple-system, sans-serif;
 }

 * { margin:0; padding:0; box-sizing:border-box; }

 body {
 font-family: var(--font);
 background: var(--navy);
 color: var(--white);
 min-height: 100vh;
 overflow-x: hidden;
 }

 /* ── Ambient blurs ────────────────────────────── */
 .glow { position:fixed; border-radius:50%; filter:blur(120px); pointer-events:none; z-index:0; }
 .glow-1 { width:600px; height:600px; background:var(--purple); opacity:.12; top:-200px; left:-100px; }
 .glow-2 { width:500px; height:500px; background:var(--blue); opacity:.08; bottom:-150px; right:-100px; }
 .glow-3 { width:350px; height:350px; background:var(--gold); opacity:.06; top:40%; left:50%; }

 /* ── Navigation ───────────────────────────────── */
 .nav {
 position:sticky; top:0; z-index:100;
 background:rgba(10,14,39,0.85);
 backdrop-filter:blur(20px);
 -webkit-backdrop-filter:blur(20px);
 border-bottom:1px solid var(--card-border);
 padding:0 24px;
 }
 .nav-inner {
 max-width:1200px; margin:0 auto;
 display:flex; align-items:center; justify-content:space-between;
 height:64px;
 }
 .nav-brand {
 font-size:1.4rem; font-weight:800; letter-spacing:-0.5px;
 background:linear-gradient(135deg,var(--purple-glow),var(--blue-elec));
 -webkit-background-clip:text; -webkit-text-fill-color:transparent;
 text-decoration:none;
 }
 .nav-links { display:flex; gap:8px; align-items:center; }
 .nav-links a {
 color:var(--muted); text-decoration:none; font-size:.875rem;
 font-weight:500; padding:8px 14px; border-radius:8px;
 transition:all .2s;
 }
 .nav-links a:hover { color:var(--white); background:var(--card-bg); }
 .nav-cta {
 background:linear-gradient(135deg,var(--purple),var(--blue)) !important;
 color:var(--white) !important; font-weight:600 !important;
 }
 .nav-cta:hover { opacity:.9; transform:translateY(-1px); box-shadow:0 4px 20px rgba(124,58,237,.4); }

 /* ── Hero ─────────────────────────────────────── */
 .hero {
 text-align:center; padding:80px 24px 40px;
 position:relative; z-index:1;
 }
 .hero-badge {
 display:inline-flex; align-items:center; gap:6px;
 background:var(--card-bg); border:1px solid var(--card-border);
 border-radius:100px; padding:6px 16px; font-size:.8rem;
 color:var(--gold); font-weight:600; margin-bottom:24px;
 }
 .hero h1 {
 font-size:clamp(2.2rem,6vw,4rem); font-weight:900; line-height:1.1;
 letter-spacing:-1.5px; margin-bottom:16px;
 background:linear-gradient(135deg,#fff 0%,var(--blue-elec) 50%,var(--purple-glow) 100%);
 -webkit-background-clip:text; -webkit-text-fill-color:transparent;
 }
 .hero p { color:var(--muted); font-size:1.15rem; max-width:520px; margin:0 auto 32px; line-height:1.7; }

 /* ── Search ───────────────────────────────────── */
 .search-wrap {
 max-width:600px; margin:0 auto 48px; position:relative; z-index:1;
 }
 .search-form {
 display:flex; background:var(--card-bg);
 border:1px solid var(--card-border); border-radius:14px;
 overflow:hidden; transition:border-color .3s;
 }
 .search-form:focus-within { border-color:var(--purple); }
 .search-input {
 flex:1; background:transparent; border:none; outline:none;
 padding:16px 20px; font-size:1rem; color:var(--white);
 font-family:var(--font);
 }
 .search-input::placeholder { color:var(--muted); }
 .search-btn {
 background:linear-gradient(135deg,var(--purple),var(--blue));
 border:none; color:#fff; padding:16px 28px; cursor:pointer;
 font-weight:600; font-size:.95rem; font-family:var(--font);
 transition:opacity .2s;
 }
 .search-btn:hover { opacity:.9; }

 /* ── Event Grid ───────────────────────────────── */
 .container { max-width:1200px; margin:0 auto; padding:0 24px 60px; position:relative; z-index:1; }
 .section-title {
 font-size:1.5rem; font-weight:700; margin-bottom:28px;
 display:flex; align-items:center; gap:10px;
 }
 .section-title span { color:var(--gold); }

 .events-grid {
 display:grid;
 grid-template-columns:repeat(auto-fill,minmax(340px,1fr));
 gap:24px;
 }

 /* ── Event Card ───────────────────────────────── */
 .event-card {
 background:var(--card-bg);
 border:1px solid var(--card-border);
 border-radius:var(--radius);
 overflow:hidden;
 transition:all .35s cubic-bezier(.4,0,.2,1);
 position:relative;
 }
 .event-card::before {
 content:''; position:absolute; top:0; left:0; right:0; height:1px;
 background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent);
 opacity:0; transition:opacity .4s;
 }
 .event-card:hover {
 transform:translateY(-6px);
 border-color:rgba(255,255,255,.15);
 box-shadow:0 20px 40px rgba(0,0,0,.4);
 }
 .event-card:hover::before { opacity:1; }

 .card-img {
 width:100%; height:200px; object-fit:cover;
 display:block; background:var(--navy-light);
 }

 .card-body { padding:20px; }
 .card-date {
 font-size:.75rem; font-weight:600; color:var(--blue-elec);
 text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px;
 }
 .card-title {
 font-size:1.2rem; font-weight:700; margin-bottom:8px; line-height:1.3;
 }
 .card-desc {
 color:var(--muted); font-size:.875rem; line-height:1.6;
 margin-bottom:16px;
 display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
 overflow:hidden;
 }
 .card-meta {
 display:flex; flex-wrap:wrap; gap:12px;
 font-size:.8rem; color:var(--muted); margin-bottom:18px;
 }
 .card-meta span { display:flex; align-items:center; gap:4px; }

 .card-footer {
 display:flex; align-items:center; justify-content:space-between;
 padding-top:16px; border-top:1px solid var(--card-border);
 }
 .card-price {
 font-size:1.3rem; font-weight:800;
 background:linear-gradient(135deg,var(--gold),var(--gold-warm));
 -webkit-background-clip:text; -webkit-text-fill-color:transparent;
 }
 .card-price small {
 font-size:.7rem; font-weight:500;
 -webkit-text-fill-color:var(--muted);
 }
 .card-btn {
 background:linear-gradient(135deg,var(--purple),var(--blue));
 color:#fff; text-decoration:none; padding:10px 20px;
 border-radius:10px; font-weight:600; font-size:.875rem;
 transition:all .2s; border:none; cursor:pointer;
 }
 .card-btn:hover {
 transform:translateY(-2px);
 box-shadow:0 6px 20px rgba(124,58,237,.4);
 }

 /* ── Empty state ──────────────────────────────── */
 .empty-state {
 text-align:center; padding:60px 20px; color:var(--muted);
 }
 .empty-state h2 { font-size:1.5rem; margin-bottom:8px; color:var(--white); }

 /* ── Assistant panel toggle ────────────────────── */
 .assistant-fab {
 position:fixed; bottom:28px; right:28px; z-index:200;
 width:56px; height:56px; border-radius:50%;
 background:linear-gradient(135deg,var(--purple),var(--blue));
 border:none; cursor:pointer; color:#fff; font-size:1.5rem;
 box-shadow:0 8px 30px rgba(124,58,237,.5);
 transition:all .3s; display:flex; align-items:center; justify-content:center;
 }
 .assistant-fab:hover { transform:scale(1.1); }

 .assistant-panel {
 position:fixed; bottom:100px; right:28px; z-index:200;
 width:380px; max-height:500px;
 background:var(--navy-light);
 border:1px solid var(--card-border);
 border-radius:var(--radius);
 box-shadow:0 20px 60px rgba(0,0,0,.6);
 display:none; flex-direction:column;
 overflow:hidden;
 }
 .assistant-panel.open { display:flex; }
 .assistant-header {
 padding:16px 20px; border-bottom:1px solid var(--card-border);
 display:flex; align-items:center; justify-content:space-between;
 }
 .assistant-header h3 { font-size:1rem; font-weight:700; }
 .assistant-close {
 background:none; border:none; color:var(--muted); cursor:pointer;
 font-size:1.2rem;
 }
 .assistant-body { flex:1; overflow-y:auto; padding:16px 20px; }
 .assistant-msg {
 margin-bottom:12px; padding:10px 14px; border-radius:12px;
 font-size:.875rem; line-height:1.5; max-width:90%;
 }
 .assistant-msg.bot {
 background:var(--card-bg); border:1px solid var(--card-border);
 color:var(--white);
 }
 .assistant-msg.user {
 background:linear-gradient(135deg,var(--purple),var(--blue));
 color:#fff; margin-left:auto;
 }
 .assistant-input-wrap {
 display:flex; border-top:1px solid var(--card-border);
 }
 .assistant-input {
 flex:1; background:transparent; border:none; outline:none;
 padding:14px 16px; color:var(--white); font-size:.875rem;
 font-family:var(--font);
 }
 .assistant-input::placeholder { color:var(--muted); }
 .assistant-send {
 background:linear-gradient(135deg,var(--purple),var(--blue));
 border:none; color:#fff; padding:14px 18px; cursor:pointer;
 font-weight:600; font-family:var(--font);
 }

 /* ── Footer ───────────────────────────────────── */
 .footer {
 border-top:1px solid var(--card-border);
 background:rgba(0,0,0,.3);
 backdrop-filter:blur(10px);
 padding:40px 24px; text-align:center;
 position:relative; z-index:1;
 }
 .footer-brand {
 font-size:1.3rem; font-weight:800; margin-bottom:8px;
 background:linear-gradient(135deg,var(--purple-glow),var(--blue-elec));
 -webkit-background-clip:text; -webkit-text-fill-color:transparent;
 }
 .footer-tagline { color:var(--muted); font-size:.875rem; margin-bottom:16px; }
 .footer-links { display:flex; justify-content:center; gap:24px; flex-wrap:wrap; margin-bottom:16px; }
 .footer-links a { color:var(--muted); text-decoration:none; font-size:.85rem; transition:color .2s; }
 .footer-links a:hover { color:var(--purple-glow); }
 .footer-copy { color:var(--muted); font-size:.75rem; opacity:.6; }

 .search-wrap {
 max-width:600px; margin:0 auto 48px; position:relative; z-index:1; padding: 0 16px;
 }

 /* ── Responsive ───────────────────────────────── */
 @media (max-width:768px) {
 .nav { padding: 0 16px; }
 .nav-inner { height: auto; padding: 12px 0; flex-direction: column; gap: 10px; align-items: flex-start; }
 .nav-links { width: 100%; justify-content: space-between; }
 .events-grid { grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
 .assistant-panel { width:calc(100vw - 32px); right:16px; bottom:85px; max-height: 440px; }
 .hero { padding:40px 16px 20px; }
 .hero h1 { font-size: 2.2rem; }
 .hero p { font-size: 1rem; margin-bottom: 24px; }
 .search-wrap { margin-bottom: 32px; }
 .container { padding: 0 16px 40px; }
 }

 @media (max-width:480px) {
 .events-grid { grid-template-columns: 1fr; }
 .search-form { flex-direction: column; }
 .search-btn { width: 100%; padding: 12px; }
 .card-body { padding: 16px; }
 }
 </style>
</head>
<body>
 <!-- Ambient glows -->
 <div class="glow glow-1"></div>
 <div class="glow glow-2"></div>
 <div class="glow glow-3"></div>

 <!-- Navigation -->
 <nav class="nav">
 <div class="nav-inner">
 <a href="index.php" class="nav-brand">GatherPass</a>
 <div class="nav-links">
 <a href="#events" class="nav-link">Explore Events</a>
 </div>
 </div>
 </nav>

 <!-- Hero -->
 <section class="hero">
 <div class="hero-badge">Your Event Ticketing Platform</div>
 <h1>Discover. Book. Enter.</h1>
 <p><?php echo APP_DESCRIPTION; ?></p>
 </section>

 <!-- Search -->
 <div class="search-wrap">
 <form class="search-form" method="GET" action="index.php">
 <input type="text"
 name="search"
 class="search-input"
 id="searchInput"
 placeholder="Search events by name, venue, or keyword…"
 value="<?php echo htmlspecialchars($search); ?>"
 aria-label="Search events">
 <button type="submit" class="search-btn">Search</button>
 </form>
 </div>

 <!-- Events -->
 <div class="container">
 <div class="section-title"><span></span> <?php echo $search ? 'Results for "'.htmlspecialchars($search).'"' : 'Featured Events'; ?></div>

 <?php if ($events->num_rows === 0): ?>
 <div class="empty-state">
 <h2>No events found</h2>
 <p>Try a different search or check back later for new events.</p>
 </div>
 <?php else: ?>
 <div class="events-grid">
 <?php while ($ev = $events->fetch_assoc()):
 $base = floatval($ev['base_ticket_price']);
 $fee = $base * PLATFORM_FEE_PERCENTAGE;
 $total = $base + $fee;
 $remain = intval($ev['available_tickets'] ?? 0);
 $dt = strtotime($ev['event_date']);
 $img = !empty($ev['picture']) ? $ev['picture'] : 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=800&q=80';
 ?>
 <article class="event-card">
 <img class="card-img"
 src="<?php echo htmlspecialchars($img); ?>"
 alt="<?php echo htmlspecialchars($ev['name']); ?>"
 loading="lazy">
 <div class="card-body">
 <div class="card-date"><?php echo date('D, M j, Y · g:i A', $dt); ?></div>
 <h2 class="card-title"><?php echo htmlspecialchars($ev['name']); ?></h2>
 <p class="card-desc"><?php echo htmlspecialchars($ev['description'] ?? ''); ?></p>
 <div class="card-meta">
 <span> <?php echo htmlspecialchars($ev['venue'] ?? 'TBD'); ?></span>
 <span> <?php echo $remain; ?>left</span>
 </div>
 <div class="card-footer">
 <div class="card-price">
 <?php echo APP_CURRENCY_SYMBOL . number_format($total, 2); ?>
 <small>incl. <?php echo PLATFORM_FEE_LABEL; ?>fee</small>
 </div>
 <a href="event.php?id=<?php echo $ev['id']; ?>" class="card-btn">View Details</a>
 </div>
 </div>
 </article>
 <?php endwhile; ?>
 </div>
 <?php endif; ?>
 </div>

 <!-- Footer -->
 <footer class="footer">
 <div class="footer-brand">GatherPass</div>
 <div class="footer-tagline"><?php echo APP_TAGLINE; ?></div>
 <div class="footer-links">
 <a href="index.php">Browse Events</a>
 <a href="verify.php">Verify Ticket</a>
 <a href="api/health.php">API Health</a>
 </div>
 <div class="footer-copy">© <?php echo date('Y'); ?>GatherPass · A hackathon project by Mustapha Ishmael</div>
 </footer>


</body>
</html>
<?php $conn->close(); ?>
