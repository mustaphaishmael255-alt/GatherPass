<?php
/**
 * GatherPass — Database Setup & Seed
 * Run once to create tables and insert demo data.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>GatherPass Setup</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:700px;margin:40px auto;padding:20px;background:#0a0e27;color:#e2e8f0}';
echo '.ok{color:#4ade80}.err{color:#f87171}.info{color:#60a5fa}h1{color:#c084fc}code{background:#1e293b;padding:2px 6px;border-radius:4px}</style></head><body>';
echo '<h1>GatherPass Setup</h1>';

try {
 $conn = getConnection();
 echo '<p class="ok">Connected to MySQL</p>';

 // ── Create tables ─────────────────────────────────────────
 $conn->query("CREATE TABLE IF NOT EXISTS planners (
 id INT AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(255) NOT NULL,
 email VARCHAR(255) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
 phone VARCHAR(20) DEFAULT NULL,
 is_active BOOLEAN DEFAULT TRUE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_email (email)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 echo '<p class="ok">Table <code>planners</code>ready</p>';

 $conn->query("CREATE TABLE IF NOT EXISTS events (
 id INT AUTO_INCREMENT PRIMARY KEY,
 planner_id INT NOT NULL,
 name VARCHAR(255) NOT NULL,
 description TEXT DEFAULT NULL,
 event_date DATETIME NOT NULL,
 venue VARCHAR(255) DEFAULT NULL,
 base_ticket_price DECIMAL(10,2) DEFAULT 0.00,
 available_tickets INT DEFAULT 100,
 picture VARCHAR(500) DEFAULT NULL,
 is_active BOOLEAN DEFAULT TRUE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (planner_id) REFERENCES planners(id) ON DELETE CASCADE,
 INDEX idx_planner (planner_id),
 INDEX idx_active (is_active),
 INDEX idx_date (event_date)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 echo '<p class="ok">Table <code>events</code>ready</p>';

 $conn->query("CREATE TABLE IF NOT EXISTS tickets (
 id INT AUTO_INCREMENT PRIMARY KEY,
 event_id INT NOT NULL,
 buyer_name VARCHAR(255) NOT NULL,
 buyer_email VARCHAR(255) NOT NULL,
 buyer_phone VARCHAR(30) DEFAULT NULL,
 quantity INT DEFAULT 1,
 ticket_type VARCHAR(100) DEFAULT 'General Admission',
 ticket_price DECIMAL(10,2) DEFAULT 0.00,
 platform_fee DECIMAL(10,2) DEFAULT 0.00,
 total_paid DECIMAL(10,2) DEFAULT 0.00,
 ticket_token VARCHAR(64) NOT NULL UNIQUE,
 status VARCHAR(20) DEFAULT 'valid',
 checked_in_at DATETIME DEFAULT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
 INDEX idx_event (event_id),
 INDEX idx_token (ticket_token),
 INDEX idx_status (status),
 INDEX idx_buyer (buyer_email)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 echo '<p class="ok">Table <code>tickets</code>ready</p>';

 // ── Seed demo planner ─────────────────────────────────────
 $demoEmail = 'demo@gatherpass.app';
 $check = $conn->prepare("SELECT id FROM planners WHERE email = ?");
 $check->bind_param("s", $demoEmail);
 $check->execute();
 $check->store_result();

 if ($check->num_rows === 0) {
 $hash = password_hash('gatherpass2026', PASSWORD_DEFAULT);
 $ins = $conn->prepare("INSERT INTO planners (full_name, email, password, phone) VALUES (?, ?, ?, ?)");
 $name = 'GatherPass Demo';
 $phone = '+233000000000';
 $ins->bind_param("ssss", $name, $demoEmail, $hash, $phone);
 $ins->execute();
 echo '<p class="ok">Demo planner created — <code>demo@gatherpass.app</code> / <code>gatherpass2026</code></p>';
 } else {
 echo '<p class="info">ℹ Demo planner already exists</p>';
 }
 $check->close();

 // ── Seed events ───────────────────────────────────────────
 $eventCheck = $conn->query("SELECT COUNT(*) AS c FROM events");
 $count = $eventCheck->fetch_assoc()['c'];

 if ($count == 0) {
 $plannerId = 1;
 $events = [
 [
 'Accra Tech Summit 2026',
 'The premier West-African technology conference featuring keynotes on AI, cloud computing, and fintech. Three days of workshops, live demos, and networking with 50+ industry leaders.',
 '2026-12-15 09:00:00',
 'Accra International Conference Centre',
 100.00, 400,
 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&q=80'
 ],
 [
 'Back Yard Party',
 'An evening of live Afrobeats, great food, and good vibes. Local DJs, food trucks, a bonfire zone, and starlit dancing under the open sky.',
 '2026-09-28 17:00:00',
 'Garden City, Kumasi',
 50.00, 200,
 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=800&q=80'
 ],
 [
 'Startup Pitch Night',
 'Watch ten handpicked startups pitch to a panel of investors and mentors. Audience voting decides the People\'s Choice award. Refreshments included.',
 '2026-11-05 18:30:00',
 'Impact Hub, Osu',
 30.00, 150,
 'https://images.unsplash.com/photo-1559223607-a43c990c692c?w=800&q=80'
 ]
 ];

 $stmt = $conn->prepare("INSERT INTO events (planner_id, name, description, event_date, venue, base_ticket_price, available_tickets, picture, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE)");

 foreach ($events as $e) {
 $stmt->bind_param("issssdi s",
 $plannerId, $e[0], $e[1], $e[2], $e[3], $e[4], $e[5], $e[6]);
 $stmt->execute();
 echo '<p class="ok">Seeded event: <code>' . htmlspecialchars($e[0]) . '</code></p>';
 }
 $stmt->close();
 } else {
 echo '<p class="info">ℹ Events already seeded (' . $count . ' found)</p>';
 }

 echo '<hr><p class="ok" style="font-size:1.2em">GatherPass is ready!</p>';
 echo '<p><a href="index.php" style="color:#c084fc;font-weight:600">→ Open GatherPass</a></p>';

 $conn->close();

} catch (Exception $e) {
 echo '<p class="err">Setup failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
 echo '<p class="info">Make sure MySQL is running and check your <code>.env</code>settings.</p>';
}

echo '</body></html>';
?>
