<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireRole('admin','staff');

$proxiedPages = ['guests', 'rooms', 'booked-rooms', 'payments', 'checkin', 'invoices', 'invoice-print', 'messages'];
$requestedPage = $_GET['page'] ?? 'dashboard';

if (in_array($requestedPage, $proxiedPages, true)) {
  require __DIR__ . '/' . $requestedPage . '.php';
  exit;
}

$db   = getDB();
$user = authUser();
refreshBookingLifecycle($db);
$isStaffDashboard = ($user['role'] ?? '') === 'staff';
$dashboardTitle = $isStaffDashboard ? 'Staff Dashboard' : 'Dashboard';
$dashboardSubtitle = $isStaffDashboard ? 'Front-desk operations' : 'Admin overview';
$today = date('Y-m-d');
$thisMonth = date('Y-m');

// ── STATS ──────────────────────────────────────────────────────────────────
$stats = [
    'rooms_total'     => (int)$db->query("SELECT COUNT(*) FROM rooms")->fetchColumn(),
    'rooms_available' => (int)$db->query("SELECT COUNT(*) FROM rooms WHERE status='available'")->fetchColumn(),
    'bookings_active' => (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status IN('confirmed','checked_in')")->fetchColumn(),
    'bookings_pending'=> (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn(),
    'guests_total'    => (int)$db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn(),
    'checkin_today'   => (int)$db->query("SELECT COUNT(*) FROM bookings WHERE check_in='$today' AND status='confirmed'")->fetchColumn(),
    'checkout_today'  => (int)$db->query("SELECT COUNT(*) FROM bookings WHERE check_out='$today' AND status='checked_in'")->fetchColumn(),
    'revenue_total'   => (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'")->fetchColumn(),
    'revenue_month'   => (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed' AND DATE_FORMAT(paid_at,'%Y-%m')='$thisMonth'")->fetchColumn(),
];

$deadlineExpr = checkoutDeadlineExpression('b');
$stats['checkout_due_soon'] = (int)$db->query("
    SELECT COUNT(*)
    FROM bookings b
    WHERE b.status='checked_in'
      AND $deadlineExpr BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR)
")->fetchColumn();
$stats['checkout_overdue'] = (int)$db->query("
    SELECT COUNT(*)
    FROM bookings b
    WHERE b.status='checked_in'
      AND $deadlineExpr < NOW()
")->fetchColumn();
$stats['checkout_alerts'] = $stats['checkout_due_soon'] + $stats['checkout_overdue'];

$checkoutAlerts = $db->query("
    SELECT b.*, u.name AS guest, u.phone, r.name AS room, r.room_number,
           $deadlineExpr AS checkout_deadline,
           TIMESTAMPDIFF(MINUTE, NOW(), $deadlineExpr) AS minutes_to_deadline
    FROM bookings b
    JOIN users u ON u.id=b.user_id
    JOIN rooms r ON r.id=b.room_id
    WHERE b.status='checked_in'
      AND $deadlineExpr <= DATE_ADD(NOW(), INTERVAL 2 HOUR)
    ORDER BY $deadlineExpr ASC
    LIMIT 8
")->fetchAll();

// ── Monthly Revenue (12 months) ──────────────────────────────────────────────
$monthlyRev = [];
for ($m = 1; $m <= 12; $m++) {
    $ym = date('Y') . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $s = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed' AND DATE_FORMAT(paid_at,'%Y-%m')=?");
    $s->execute([$ym]);
    $monthlyRev[] = (float)$s->fetchColumn();
}

// ── Bookings by status (pie) ─────────────────────────────────────────────────
$statusData = $db->query("SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status")->fetchAll();
$statusLabels = array_column($statusData,'status');
$statusCounts = array_column($statusData,'cnt');

// ── Weekly bookings (bar) ────────────────────────────────────────────────────
$weeklyData = [];
for ($d = 6; $d >= 0; $d--) {
    $date = date('Y-m-d', strtotime("-$d days"));
    $s = $db->prepare("SELECT COUNT(*) FROM bookings WHERE DATE(created_at)=?");
    $s->execute([$date]);
    $weeklyData[] = ['date' => date('D', strtotime($date)), 'count' => (int)$s->fetchColumn()];
}

// ── Recent bookings ──────────────────────────────────────────────────────────
$recentBookings = $db->query("
    SELECT b.*, u.name AS guest_name, r.name AS room_name
    FROM bookings b
    JOIN users u ON u.id = b.user_id
    JOIN rooms r ON r.id = b.room_id
    ORDER BY b.created_at DESC LIMIT 8
")->fetchAll();

// ── Room occupancy ───────────────────────────────────────────────────────────
$blockingCondition = bookingBlockingCondition('b');
$occupancy = $db->query("
    SELECT r.name, r.status,
      (SELECT COUNT(*) FROM bookings b WHERE b.room_id=r.id AND $blockingCondition) AS bookings
    FROM rooms r ORDER BY r.room_number
")->fetchAll();

// ── Today's arrivals & departures ─────────────────────────────────────────
$arrivals   = $db->query("SELECT b.*,u.name AS guest,u.phone,r.name AS room FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id WHERE b.check_in='$today' AND b.status='confirmed'")->fetchAll();
$departures = $db->query("SELECT b.*,u.name AS guest,u.phone,r.name AS room FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id WHERE b.check_out='$today' AND b.status='checked_in'")->fetchAll();

// ── Messages unread ───────────────────────────────────────────────────────────
$unreadMsgs = (int)$db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
$unreadDirectMessages = messageUnreadCount((int)$user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($dashboardTitle) ?> – Sapphire Stay</title>
  <?php include 'partials/admin-head.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    /* Admin dashboard: data-dense but beautiful with Lapis & Chinar tokens */
    body { background: var(--bg); color: var(--text); }
    .stat-card { background:#fff; border:1px solid var(--stone); border-radius:16px; padding:18px 20px; transition: transform .2s, box-shadow .2s, border-color .2s; }
    .stat-card:hover { border-color: var(--gold); transform: translateY(-1px); box-shadow: 0 10px 25px -12px rgb(0 0 0 / 0.08); }
    .admin-table th { font-weight:500; color:var(--text-faint); text-transform:uppercase; letter-spacing:.08em; font-size:10px; padding-bottom:8px; }
    .admin-table td { border-top:1px solid var(--stone); padding-top:10px; padding-bottom:10px; }
    .status-pill { font-size:10px; letter-spacing:.05em; padding:1px 8px; border-radius:999px; border:1px solid currentColor; display:inline-flex; align-items:center; gap:3px; white-space:nowrap; }
    .admin-topbar { background:rgba(247, 244, 237, 0.85); backdrop-filter:blur(12px); border-bottom:1px solid var(--stone); }
    
    .badge-pending { background: rgba(251, 191, 36, 0.15); color: #b45309; border: 1px solid rgba(251, 191, 36, 0.3); }
    .badge-confirmed { background: rgba(74, 222, 128, 0.15); color: #15803d; border: 1px solid rgba(74, 222, 128, 0.3); }
    .badge-checked_in { background: rgba(96, 165, 250, 0.15); color: #1d4ed8; border: 1px solid rgba(96, 165, 250, 0.3); }
    .badge-checked_out { background: rgba(148, 163, 184, 0.15); color: #475569; border: 1px solid rgba(148, 163, 184, 0.3); }
    .badge-cancelled { background: rgba(248, 113, 113, 0.15); color: #b91c1c; border: 1px solid rgba(248, 113, 113, 0.3); }
  </style>
</head>
<body class="admin-shell flex min-h-screen" data-role="<?= e(currentRole()) ?>">

<?php include 'partials/sidebar.php'; ?>

<main class="flex-1 overflow-auto">
  <!-- Topbar -->
  <div class="admin-topbar px-8 py-4 flex items-center justify-between sticky top-0 z-30">
    <div>
      <h1 class="font-display text-2xl text-[var(--lapis)] tracking-tight"><?= e($dashboardTitle) ?></h1>
      <p class="text-[var(--text-faint)] text-xs"><?= e($dashboardSubtitle) ?> • <?= date('l, d F Y') ?> • <?= date('H:i') ?> PKT</p>
    </div>
    <div class="flex items-center gap-3 text-sm">
      <?php if($stats['bookings_pending'] > 0): ?>
      <a href="<?= $isStaffDashboard ? adminUrl('payments') : 'bookings.php?status=pending' ?>" class="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs border border-[var(--gold)] text-[var(--gold)] hover:bg-[var(--gold)] hover:text-white transition">
        <i data-lucide="clock" class="w-3.5 h-3.5"></i> <?= $stats['bookings_pending'] ?> pending
      </a>
      <?php endif; ?>
      <?php if($stats['checkout_alerts'] > 0): ?>
      <a href="<?= adminUrl('checkin') ?>" class="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs border border-rose-400 text-rose-300 hover:bg-rose-500 hover:text-white transition">
        <i data-lucide="alarm-clock" class="w-3.5 h-3.5"></i> <?= $stats['checkout_alerts'] ?> checkout alert<?= $stats['checkout_alerts'] === 1 ? '' : 's' ?>
      </a>
      <?php endif; ?>
      <?php if(!$isStaffDashboard && $unreadMsgs > 0): ?>
      <a href="<?= adminUrl('inquiries') ?>" class="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs border border-[var(--lapis)] text-[var(--lapis)] hover:bg-[var(--lapis)] hover:text-white transition">
        <i data-lucide="inbox" class="w-3.5 h-3.5"></i> <?= $unreadMsgs ?> inquiries
      </a>
      <?php endif; ?>
      <?php if($unreadDirectMessages > 0): ?>
      <a href="<?= adminUrl('messages') ?>" class="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs border border-[var(--lapis)] text-[var(--lapis)] hover:bg-[var(--lapis)] hover:text-white transition">
        <i data-lucide="message-circle" class="w-3.5 h-3.5"></i> <?= $unreadDirectMessages ?> chats
      </a>
      <?php endif; ?>
      <a href="../index.php" target="_blank" class="text-[var(--text-faint)] hover:text-[var(--text)] text-xs tracking-widest flex items-center gap-1 transition">LIVE SITE <i data-lucide="external-link" class="w-3 h-3"></i></a>
    </div>
  </div>

  <div class="p-6 space-y-6">
    <?php if ($isStaffDashboard): ?>
    <div class="twilight bg-[var(--lapis)] rounded-2xl p-5 flex flex-col lg:flex-row lg:items-center gap-4 justify-between border border-[var(--gold)]/30">
      <div>
        <p class="text-[var(--gold-bright)] text-xs tracking-[0.25em] uppercase mb-1">Today’s Desk Queue</p>
        <h2 class="font-display text-2xl text-[#f7f4ed]">Keep arrivals, departures, payments, and guest messages moving.</h2>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="<?= adminUrl('checkin') ?>" class="border border-[var(--stone)]/30 hover:border-[var(--gold-bright)] text-[#f7f4ed] px-4 py-2 rounded-xl text-xs tracking-widest transition hover:bg-white/5">CHECK-IN / OUT</a>
        <a href="<?= adminUrl('payments') ?>" class="border border-[var(--gold-soft)] text-[var(--gold-bright)] px-4 py-2 rounded-xl text-xs tracking-widest transition hover:bg-white/5">PAYMENTS</a>
        <a href="<?= adminUrl('messages') ?>" class="border border-[var(--stone)]/30 text-[var(--text-muted)] px-4 py-2 rounded-xl text-xs tracking-widest transition hover:text-white hover:bg-white/5">MESSAGES</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Stat Cards (Lapis & Chinar) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
      <?php
      $cards = $isStaffDashboard ? [
        ['Pending', $stats['bookings_pending'], 'clock', 'Pending bookings'],
        ['Active', $stats['bookings_active'], 'calendar-check', 'Confirmed & checked-in'],
        ['Arrivals', $stats['checkin_today'], 'log-in', 'Today'],
        ['Departures', $stats['checkout_today'], 'log-out', 'Today'],
        ['Checkout Alerts', $stats['checkout_alerts'], 'alarm-clock', $stats['checkout_overdue'] . ' overdue'],
        ['Inquiries', $unreadMsgs, 'inbox', 'Unread contact'],
      ] : [
        ['Revenue (This Month)',  currency($stats['revenue_month']),   'trending-up',      'This calendar month'],
        ['Total Revenue (PKR)',   currency($stats['revenue_total']),   'banknote',         'All-time completed payments'],
        ['Active Bookings',       $stats['bookings_active'],           'calendar-check',   'Confirmed & checked-in'],
        ['Pending Bookings',      $stats['bookings_pending'],          'clock',            'Awaiting approval/payment'],
        ['Total Guests',          $stats['guests_total'],              'users',            'Registered customers'],
        ['Check-ins Today',       $stats['checkin_today'],             'log-in',           'Expected arrival today'],
        ['Check-outs Today',      $stats['checkout_today'],            'log-out',          'Expected departure today'],
        ['Checkout Alerts',       $stats['checkout_alerts'],           'alarm-clock',      $stats['checkout_overdue'] . ' overdue'],
        ['Rooms Available',       $stats['rooms_available'] . '/' . $stats['rooms_total'], 'bed', 'Rooms currently available'],
      ];
      foreach ($cards as [$label, $val, $icon, $subtitle]): ?>
      <div class="stat-card">
        <div class="flex items-start justify-between">
          <div>
            <div class="text-2xl font-display text-[var(--lapis)] tabular-nums font-semibold"><?= is_numeric($val) ? number_format((float)$val) : e($val) ?></div>
            <div class="text-xs text-[var(--text-muted)] mt-1"><?= e($label) ?></div>
          </div>
          <div class="w-9 h-9 rounded-xl bg-[var(--bg-alt)] flex items-center justify-center text-[var(--gold)]">
            <i data-lucide="<?= $icon ?>" class="w-4 h-4"></i>
          </div>
        </div>
        <?php if (!empty($subtitle)): ?><div class="text-[10px] text-[var(--text-faint)] mt-2"><?= e($subtitle) ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($checkoutAlerts): ?>
    <div class="glass rounded-2xl overflow-hidden border border-amber-400/25">
      <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between gap-4">
        <div>
          <h3 class="font-display text-xl text-white">Checkout Attention</h3>
          <p class="text-white/35 text-xs">Guests due within 2 hours or past checkout time. Staff must check them out manually.</p>
        </div>
        <a href="<?= adminUrl('checkin') ?>" class="text-xs tracking-widest text-[var(--gold-bright)] hover:text-white">OPEN DESK</a>
      </div>
      <div class="divide-y divide-white/10">
        <?php foreach ($checkoutAlerts as $alert): ?>
        <?php
          $mins = (int)$alert['minutes_to_deadline'];
          $isOverdue = $mins < 0;
        ?>
        <div class="px-5 py-3 flex flex-col md:flex-row md:items-center justify-between gap-3">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-white font-semibold"><?= e($alert['guest']) ?></span>
              <span class="text-white/35 text-xs">Room <?= e($alert['room_number']) ?> · <?= e($alert['booking_ref']) ?></span>
              <span class="<?= $isOverdue ? 'text-rose-200 border-rose-400/40 bg-rose-500/10' : 'text-amber-200 border-amber-400/40 bg-amber-400/10' ?> border text-[11px] px-2.5 py-1 rounded-full">
                <?= $isOverdue ? 'Expired ' . abs($mins) . ' min ago' : 'Expiring in ' . $mins . ' min' ?>
              </span>
            </div>
            <div class="text-white/35 text-xs mt-1">Deadline <?= date('d M Y, H:i', strtotime($alert['checkout_deadline'])) ?></div>
          </div>
          <div class="flex gap-2">
            <a href="checkin.php?action=checkout&id=<?= (int)$alert['id'] ?>" class="bg-blue-700 hover:bg-blue-800 text-white text-xs px-3 py-1.5 rounded-lg transition">Check Out</a>
            <a href="<?= adminUrl('checkin') ?>" class="border border-[var(--gold)]/50 text-[var(--gold-bright)] hover:bg-[var(--gold)]/10 text-xs px-3 py-1.5 rounded-lg transition">Extend</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!$isStaffDashboard): ?>
    <!-- ── Charts Row ── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Monthly Revenue Bar -->
      <div class="lg:col-span-2 bg-white border border-[var(--stone)] rounded-2xl p-6">
        <div class="flex items-center justify-between mb-5">
          <h3 class="font-display text-xl text-[var(--lapis)]">Monthly Revenue <span class="text-[var(--text-faint)] text-sm font-sans"><?= date('Y') ?></span></h3>
          <span class="text-[var(--gold)] text-xs bg-[var(--bg-alt)] border border-[var(--stone)] px-3 py-1 rounded-full"><?= currency($stats['revenue_total']) ?> total</span>
        </div>
        <div class="chart-container" style="height:220px;">
          <canvas id="revenueChart"></canvas>
        </div>
      </div>

      <!-- Booking Status Doughnut -->
      <div class="bg-white border border-[var(--stone)] rounded-2xl p-6">
        <h3 class="font-display text-xl text-[var(--lapis)] mb-5">Booking Status</h3>
        <div class="chart-container" style="height:180px;">
          <canvas id="statusChart"></canvas>
        </div>
        <div class="mt-4 space-y-1.5">
          <?php
          $colors = ['pending'=>'#fbbf24','confirmed'=>'#22c55e','checked_in'=>'#3b82f6','checked_out'=>'#64748b','cancelled'=>'#ef4444','expired'=>'#94a3b8','payment_failed'=>'#ef4444'];
          foreach ($statusData as $sd): ?>
          <div class="flex items-center justify-between text-xs">
            <span class="flex items-center gap-2 text-[var(--text-muted)]">
              <span class="w-2.5 h-2.5 rounded-full" style="background:<?= $colors[$sd['status']] ?? '#666' ?>"></span>
              <?= ucfirst(str_replace('_',' ',$sd['status'])) ?>
            </span>
            <span class="text-[var(--text)] font-semibold"><?= $sd['cnt'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Weekly Bookings + Room Occupancy ── -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Weekly bookings -->
      <div class="bg-white border border-[var(--stone)] rounded-2xl p-6">
        <h3 class="font-display text-xl text-[var(--lapis)] mb-5">Bookings This Week</h3>
        <div class="chart-container" style="height:180px;">
          <canvas id="weeklyChart"></canvas>
        </div>
      </div>

      <!-- Room Occupancy -->
      <div class="bg-white border border-[var(--stone)] rounded-2xl p-6">
        <h3 class="font-display text-xl text-[var(--lapis)] mb-4">Room Status</h3>
        <div class="space-y-2.5">
          <?php foreach ($occupancy as $r): ?>
          <div class="flex items-center gap-3">
            <div class="flex-1">
              <div class="flex justify-between text-xs mb-1">
                <span class="text-[var(--text-muted)] font-medium"><?= e($r['name']) ?></span>
                <span class="<?=
                  $r['status']==='available' ? 'text-green-600 font-semibold' :
                  ($r['status']==='occupied' ? 'text-blue-600 font-semibold' :
                  ($r['status']==='maintenance' ? 'text-yellow-600 font-semibold' : 'text-[var(--text-faint)]'))
                ?>"><?= ucfirst($r['status']) ?></span>
              </div>
              <div class="h-1.5 bg-[var(--bg-alt)] rounded-full overflow-hidden">
                <div class="h-full rounded-full <?=
                  $r['status']==='available' ? 'bg-green-500' :
                  ($r['status']==='occupied' ? 'bg-blue-500' :
                  ($r['status']==='maintenance' ? 'bg-yellow-500' : 'bg-stone-300'))
                ?>" style="width:<?= $r['status']==='available'?'100':($r['status']==='occupied'?'85':'30') ?>%"></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ── Today's Activity ── -->
    <?php if ($arrivals || $departures): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Arrivals -->
      <div class="bg-white border border-[var(--stone)] rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-[var(--stone)] flex items-center gap-3">
          <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
            <i data-lucide="log-in" class="w-4 h-4 text-green-700"></i>
          </div>
          <h3 class="font-display text-lg text-[var(--lapis)]">Today's Arrivals (<?= count($arrivals) ?>)</h3>
        </div>
        <?php if ($arrivals): foreach ($arrivals as $a): ?>
        <div class="px-5 py-3 border-b border-[var(--stone)]/30 flex items-center justify-between">
          <div>
            <div class="text-[var(--text)] text-sm font-medium"><?= e($a['guest']) ?></div>
            <div class="text-[var(--text-faint)] text-xs"><?= e($a['room']) ?> &bull; <?= e($a['booking_ref']) ?></div>
          </div>
          <a href="checkin.php?action=checkin&id=<?= $a['id'] ?>"
             class="bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-1.5 rounded-lg transition font-medium">
            Check In
          </a>
        </div>
        <?php endforeach; else: ?>
        <div class="px-5 py-6 text-center text-[var(--text-faint)] text-sm">No arrivals today</div>
        <?php endif; ?>
      </div>

      <!-- Departures -->
      <div class="bg-white border border-[var(--stone)] rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-[var(--stone)] flex items-center gap-3">
          <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
            <i data-lucide="log-out" class="w-4 h-4 text-blue-700"></i>
          </div>
          <h3 class="font-display text-lg text-[var(--lapis)]">Today's Departures (<?= count($departures) ?>)</h3>
        </div>
        <?php if ($departures): foreach ($departures as $d): ?>
        <div class="px-5 py-3 border-b border-[var(--stone)]/30 flex items-center justify-between">
          <div>
            <div class="text-[var(--text)] text-sm font-medium"><?= e($d['guest']) ?></div>
            <div class="text-[var(--text-faint)] text-xs"><?= e($d['room']) ?> &bull; <?= e($d['booking_ref']) ?></div>
          </div>
          <a href="checkin.php?action=checkout&id=<?= $d['id'] ?>"
             class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1.5 rounded-lg transition font-medium">
            Check Out
          </a>
        </div>
        <?php endforeach; else: ?>
        <div class="px-5 py-6 text-center text-[var(--text-faint)] text-sm">No departures today</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Recent Bookings -->
    <div class="lapis-card rounded-2xl overflow-hidden">
      <div class="flex items-center justify-between px-6 py-4 border-b border-[var(--stone)]">
        <h3 class="font-display text-xl text-[var(--lapis)]">Recent Bookings</h3>
        <a href="<?= $isStaffDashboard ? adminUrl('booked-rooms') : 'bookings.php' ?>" class="text-xs tracking-[0.14em] text-[var(--gold)] hover:underline"><?= $isStaffDashboard ? 'SCHEDULE' : 'ALL' ?> →</a>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[700px] admin-table">
          <thead>
            <tr class="border-b border-[var(--stone)]">
              <?php foreach (['Ref','Guest','Room','Check-In','Nights','Total','Status'] as $h): ?>
              <th class="px-5 py-3 text-left text-[var(--text-faint)] font-normal text-xs tracking-widest"><?= $h ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentBookings as $b): ?>
            <tr class="border-b border-[var(--stone)]/30 hover:bg-[var(--bg-alt)]/20 transition">
              <td class="px-5 py-3 text-[var(--gold)] font-mono text-xs"><?= e($b['booking_ref']) ?></td>
              <td class="px-5 py-3 text-[var(--text)] font-medium"><?= e($b['guest_name']) ?></td>
              <td class="px-5 py-3 text-[var(--text-muted)]"><?= e($b['room_name']) ?></td>
              <td class="px-5 py-3 text-[var(--text-muted)] text-xs"><?= date('d M Y', strtotime($b['check_in'])) ?></td>
              <td class="px-5 py-3 text-[var(--text-muted)]"><?= $b['nights'] ?></td>
              <td class="px-5 py-3 text-[var(--text)] font-semibold"><?= currency($b['total_amount']) ?></td>
              <td class="px-5 py-3">
                <span class="badge-<?= $b['status'] ?> text-xs px-2.5 py-1 rounded-full"><?= ucfirst(str_replace('_',' ',$b['status'])) ?></span>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentBookings)): ?>
            <tr><td colspan="7" class="text-center py-10 text-[var(--text-faint)]">No bookings yet</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<script>
lucide.createIcons();

const chartDefaults = {
  color: 'rgba(26,24,20,0.6)',
  font: { family: 'Spectral, serif', size: 11 }
};
Chart.defaults.color = chartDefaults.color;
Chart.defaults.font  = chartDefaults.font;

<?php if (!$isStaffDashboard): ?>
// ── Monthly Revenue Bar ───────────────────────────────────────────────────
new Chart(document.getElementById('revenueChart'), {
  type: 'bar',
  data: {
    labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    datasets: [{
      label: 'Revenue (₨)',
      data: <?= json_encode($monthlyRev) ?>,
      backgroundColor: ctx => {
        const g = ctx.chart.ctx.createLinearGradient(0,0,0,220);
        g.addColorStop(0,'rgba(163,124,58,0.85)'); g.addColorStop(1,'rgba(163,124,58,0.15)');
        return g;
      },
      borderColor: 'rgba(163,124,58,1)',
      borderWidth: 1.5,
      borderRadius: 8,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend:{ display:false }, tooltip:{ callbacks:{ label: c => '₨ ' + c.raw.toLocaleString() } } },
    scales: {
      x: { grid:{ color:'rgba(26,24,20,0.06)' }, ticks:{ color:'rgba(26,24,20,0.6)' } },
      y: { grid:{ color:'rgba(26,24,20,0.06)' }, ticks:{ color:'rgba(26,24,20,0.6)', callback: v => '₨'+v.toLocaleString() } }
    }
  }
});

// ── Status Doughnut ────────────────────────────────────────────────────────
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_map(fn($s)=>ucfirst(str_replace('_',' ',$s)),$statusLabels)) ?>,
    datasets: [{
      data: <?= json_encode($statusCounts) ?>,
      backgroundColor: ['#fbbf24','#22c55e','#3b82f6','#64748b','#ef4444'],
      borderColor: '#ffffff',
      borderWidth: 3,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false, cutout:'70%',
    plugins: { legend:{ display:false } }
  }
});
<?php endif; ?>

// ── Weekly Bar ─────────────────────────────────────────────────────────────
new Chart(document.getElementById('weeklyChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($weeklyData,'date')) ?>,
    datasets: [{
      label: 'Bookings',
      data: <?= json_encode(array_column($weeklyData,'count')) ?>,
      backgroundColor: 'rgba(19,42,79,0.7)',
      borderColor: 'rgba(19,42,79,1)',
      borderWidth: 1.5,
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend:{ display:false } },
    scales: {
      x: { grid:{ color:'rgba(26,24,20,0.06)' }, ticks:{ color:'rgba(26,24,20,0.6)' } },
      y: { grid:{ color:'rgba(26,24,20,0.06)' }, ticks:{ color:'rgba(26,24,20,0.6)', stepSize:1 } }
    }
  }
});
</script>
</body>
</html>
