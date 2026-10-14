<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireRole('admin','staff');

$db = getDB();
$user = authUser();
refreshBookingLifecycle($db);
$canManageBookings = ($user['role'] ?? '') === 'admin';

$scope = $_GET['scope'] ?? 'active';
$search = trim($_GET['q'] ?? '');
$allowedScopes = ['active', 'today', 'upcoming', 'all'];
if (!in_array($scope, $allowedScopes, true)) {
  $scope = 'active';
}

$where = [];
$params = [];

if ($scope === 'active') {
  $where[] = "b.status IN ('confirmed','checked_in')";
  $where[] = "b.check_out >= CURDATE()";
} elseif ($scope === 'today') {
  $where[] = "b.status IN ('confirmed','checked_in')";
  $where[] = "CURDATE() >= b.check_in AND CURDATE() < b.check_out";
} elseif ($scope === 'upcoming') {
  $where[] = "b.status='confirmed'";
  $where[] = "b.check_in >= CURDATE()";
} else {
  $where[] = "b.status IN ('confirmed','checked_in','checked_out')";
}

if ($search !== '') {
  $where[] = "(b.booking_ref LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR r.room_number LIKE ? OR r.name LIKE ?)";
  $like = '%' . $search . '%';
  array_push($params, $like, $like, $like, $like, $like, $like);
}

$sql = "
  SELECT b.*, u.name AS guest_name, u.email, u.phone,
         r.room_number, r.name AS room_name, r.thumbnail,
         p.status AS payment_status, p.method AS payment_method
  FROM bookings b
  JOIN users u ON u.id=b.user_id
  JOIN rooms r ON r.id=b.room_id
  LEFT JOIN payments p ON p.id=(
    SELECT p2.id FROM payments p2 WHERE p2.booking_id=b.id ORDER BY p2.id DESC LIMIT 1
  )
  WHERE " . implode(' AND ', $where) . "
  ORDER BY b.check_in ASC, r.room_number ASC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$bookedRooms = $stmt->fetchAll();

$scopeLabels = [
  'active' => 'Active',
  'today' => 'In House Today',
  'upcoming' => 'Upcoming',
  'all' => 'All Booked',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Booked Rooms – Sapphire Stay</title>
  <?php include 'partials/admin-head.php'; ?>
  <style>
    body{font-family:'Jost',sans-serif;background:#09090f;color:#e8e8ec;}
    .glass{background:rgba(255,255,255,0.04);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.08);}
    .sidebar-link{display:flex;align-items:center;gap:12px;padding:9px 14px;border-radius:10px;font-size:13px;letter-spacing:.06em;transition:all .2s;color:rgba(232,232,236,.45);}
    .sidebar-link:hover,.sidebar-link.active{background:rgba(255,255,255,.06);color:#fff;}.sidebar-link.active{color:#fbbf24;}
    .badge-pending{background:rgba(251,191,36,0.15);color:#fbbf24;border:1px solid rgba(251,191,36,0.3);}
    .badge-confirmed{background:rgba(74,222,128,0.15);color:#4ade80;border:1px solid rgba(74,222,128,0.3);}
    .badge-checked_in{background:rgba(96,165,250,0.15);color:#60a5fa;border:1px solid rgba(96,165,250,0.3);}
    .badge-checked_out{background:rgba(148,163,184,0.15);color:#94a3b8;border:1px solid rgba(148,163,184,0.3);}
    ::-webkit-scrollbar{width:4px;} ::-webkit-scrollbar-track{background:#09090f;} ::-webkit-scrollbar-thumb{background:#3350f5;border-radius:99px;}
  </style>
</head>
<body class="admin-shell flex min-h-screen" data-role="<?= e(currentRole()) ?>">
<?php include 'partials/sidebar.php'; ?>
<main class="flex-1 overflow-auto">
  <div class="glass border-b border-white/10 px-6 py-4 flex items-center justify-between sticky top-0 z-30">
    <div>
      <h1 class="font-display text-2xl text-white">Booked Rooms</h1>
      <p class="text-white/30 text-xs"><?= count($bookedRooms) ?> <?= strtolower($scopeLabels[$scope]) ?> booking(s)</p>
    </div>
    <?php if ($canManageBookings): ?>
    <a href="<?= APP_URL ?>/admin/bookings.php" class="text-gold-400 text-xs tracking-widest hover:text-gold-300 transition">ALL BOOKINGS</a>
    <?php endif; ?>
  </div>

  <div class="p-6 space-y-5">
    <form method="GET" class="glass rounded-xl px-5 py-4 flex flex-wrap gap-4 items-end">
      <input type="hidden" name="page" value="booked-rooms"/>
      <div>
        <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">View</label>
        <select name="scope" class="bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-gold-500">
          <?php foreach ($scopeLabels as $value => $label): ?>
          <option value="<?= $value ?>" <?= $scope === $value ? 'selected' : '' ?> style="background:#150f57;"><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex-1 min-w-[220px]">
        <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Search</label>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Guest, room, phone, booking ref"
               class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-gold-500"/>
      </div>
      <button type="submit" class="bg-gradient-to-r from-gold-600 to-gold-500 text-white px-5 py-2.5 rounded-lg text-sm tracking-widest">FILTER</button>
      <a href="<?= adminUrl('booked-rooms') ?>" class="text-white/30 hover:text-white text-sm py-2.5 px-3 transition">Clear</a>
    </form>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <?php foreach ($bookedRooms as $booking): ?>
      <div class="glass rounded-2xl overflow-hidden">
        <div class="flex">
          <?php if (!empty($booking['thumbnail'])): ?>
          <img src="../<?= e($booking['thumbnail']) ?>" alt="" class="w-32 sm:w-40 object-cover"/>
          <?php endif; ?>
          <div class="flex-1 p-5 min-w-0">
            <div class="flex items-start justify-between gap-3 mb-3">
              <div>
                <p class="text-gold-400 font-mono text-xs mb-1"><?= e($booking['booking_ref']) ?></p>
                <h2 class="font-display text-2xl text-white leading-none">Room <?= e($booking['room_number']) ?></h2>
                <p class="text-white/45 text-sm mt-1"><?= e($booking['room_name']) ?></p>
              </div>
              <span class="badge-<?= e($booking['status']) ?> text-xs px-2.5 py-1 rounded-full whitespace-nowrap"><?= e(ucfirst(str_replace('_', ' ', $booking['status']))) ?></span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div>
                <p class="text-white/30 text-xs tracking-widest uppercase mb-0.5">Booked To</p>
                <p class="text-white/85"><?= e($booking['guest_name']) ?></p>
                <p class="text-white/35 text-xs truncate"><?= e($booking['email']) ?></p>
                <?php if (!empty($booking['phone'])): ?><p class="text-white/35 text-xs"><?= e($booking['phone']) ?></p><?php endif; ?>
              </div>
              <div>
                <p class="text-white/30 text-xs tracking-widest uppercase mb-0.5">Dates</p>
                <p class="text-white/80"><?= date('d M Y', strtotime($booking['check_in'])) ?> to <?= date('d M Y', strtotime($booking['check_out'])) ?></p>
                <p class="text-white/35 text-xs"><?= (int)$booking['nights'] ?> night(s) · <?= (int)$booking['adults'] ?> adult(s)</p>
              </div>
              <div>
                <p class="text-white/30 text-xs tracking-widest uppercase mb-0.5">Amount</p>
                <p class="text-gold-400"><?= currency((float)$booking['total_amount']) ?></p>
              </div>
              <div>
                <p class="text-white/30 text-xs tracking-widest uppercase mb-0.5">Payment</p>
                <p class="text-white/60 capitalize"><?= e($booking['payment_status'] ?? 'not recorded') ?></p>
              </div>
            </div>

            <div class="flex flex-wrap gap-2 mt-4">
              <a href="<?= $canManageBookings ? APP_URL . '/admin/bookings.php?edit=' . (int)$booking['id'] : adminUrl('checkin') ?>"
                 class="glass text-gold-400 hover:text-white px-3 py-1.5 rounded-lg text-xs tracking-widest transition">
                <?= $canManageBookings ? 'EDIT BOOKING' : 'CHECK-IN DESK' ?>
              </a>
              <a href="<?= adminUrl('messages', ['contact' => (int)$booking['user_id']]) ?>"
                 class="glass text-sapphire-300 hover:text-white px-3 py-1.5 rounded-lg text-xs tracking-widest transition">
                MESSAGE GUEST
              </a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($bookedRooms)): ?>
      <div class="xl:col-span-2 glass rounded-2xl p-12 text-center">
        <i data-lucide="calendar-check" class="w-14 h-14 text-white/15 mx-auto mb-4"></i>
        <h2 class="font-display text-3xl text-white/55 mb-2">No booked rooms found</h2>
        <p class="text-white/30 text-sm">Try a different filter or search term.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>
<script>lucide.createIcons();</script>
</body>
</html>
