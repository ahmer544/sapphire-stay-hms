<?php
require_once '../config/database.php';
require_once '../config/helpers.php';

$db = getDB();
refreshBookingLifecycle($db);

// Filters
$checkIn   = $_GET['check_in']  ?? '';
$checkOut  = $_GET['check_out'] ?? '';
$guests    = (int)($_GET['guests']   ?? 1);
$category  = (int)($_GET['category'] ?? 0);
$maxPrice  = (float)($_GET['max_price'] ?? 999999);
$bedType   = $_GET['bed_type']  ?? '';
$sort      = $_GET['sort']      ?? 'featured';

$today = date('Y-m-d');
// Server-side past date guard: silently clamp to today/tomorrow
if ($checkIn && $checkIn < $today) { $checkIn = $today; }
if ($checkOut && $checkIn && $checkOut <= $checkIn) {
    $checkOut = date('Y-m-d', strtotime($checkIn . ' +1 day'));
}

$where = ["r.status <> 'maintenance'"];
$params = [];

$availabilityStart = $checkIn ?: date('Y-m-d');
$availabilityEnd = $checkOut ?: date('Y-m-d', strtotime('+1 day'));

if ($availabilityEnd <= $availabilityStart) {
  $availabilityEnd = date('Y-m-d', strtotime($availabilityStart . ' +1 day'));
}

$params[':availability_start_confirmed'] = $availabilityStart;
$params[':availability_end_confirmed'] = $availabilityEnd;
$params[':availability_start_pending'] = $availabilityStart;
$params[':availability_end_pending'] = $availabilityEnd;

if($guests  > 0)  { $where[] = 'r.capacity >= :guests';        $params[':guests']    = $guests; }
if($category > 0) { $where[] = 'r.category_id = :cat';         $params[':cat']       = $category; }
if($maxPrice < 999999){ $where[] = 'r.price_per_night <= :price';$params[':price']     = $maxPrice; }
if($bedType)        { $where[] = 'r.bed_type = :bed';           $params[':bed']       = $bedType; }

$orderMap = [
    'featured'  => 'r.is_featured DESC, r.price_per_night ASC',
    'price_asc' => 'r.price_per_night ASC',
    'price_desc'=> 'r.price_per_night DESC',
    'name'      => 'r.name ASC',
];
$order = $orderMap[$sort] ?? $orderMap['featured'];
$confirmedCondition = bookingConfirmedCondition('b');
$pendingCondition = bookingPendingHoldCondition('b');

$sql = "SELECT r.*, rc.name AS category_name,
    CASE
      WHEN EXISTS (
        SELECT 1 FROM bookings b
        WHERE b.room_id = r.id
          AND $confirmedCondition
          AND NOT (b.check_out <= :availability_start_confirmed OR b.check_in >= :availability_end_confirmed)
      ) THEN 'booked'
      WHEN EXISTS (
        SELECT 1 FROM bookings b
        WHERE b.room_id = r.id
          AND $pendingCondition
          AND NOT (b.check_out <= :availability_start_pending OR b.check_in >= :availability_end_pending)
      ) THEN 'pending'
      ELSE 'available'
    END AS display_status,
        (SELECT AVG(rv.rating) FROM reviews rv WHERE rv.room_id=r.id AND rv.is_approved=1) AS avg_rating
        FROM rooms r LEFT JOIN room_categories rc ON rc.id=r.category_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $order";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();
$categories = $db->query("SELECT * FROM room_categories ORDER BY name")->fetchAll();
$isBackOfficeViewer = isBackOfficeAccount();
$availableCount = 0;
foreach ($rooms as $room) {
  if (($room['display_status'] ?? 'available') === 'available') {
    $availableCount++;
  }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Our Rooms – Sapphire Stay</title>
  <meta name="description" content="Browse luminous rooms and suites at Sapphire Stay in the Himalayan foothills."/>
  <?php include '../partials/head.php'; ?>
</head>
<body>
<?php include '../partials/nav.php'; ?>

<!-- Page Header - Lapis & Chinar -->
<div class="page-hero pt-28 pb-16 text-center relative overflow-hidden min-h-[240px] flex items-center">
  <img src="../imgs/banner.jpeg" alt="Rooms" class="absolute inset-0 w-full h-full object-cover opacity-25"/>
  <div class="absolute inset-0 mist-overlay"></div>
  <div class="relative z-10 w-full max-w-4xl mx-auto px-6">
    <div class="page-hero-panel">
    <div class="uppercase tracking-[0.2em] text-xs text-[var(--text-faint)] mb-2">Accommodations</div>
    <h1 class="font-display text-5xl md:text-6xl text-[var(--lapis)]">Our Rooms</h1>
    <p class="text-[var(--text-muted)] mt-3">
      <?= $availableCount ?> room<?= $availableCount !== 1 ? 's' : '' ?> available
      <?php if ($checkIn && $checkOut): ?>
        · <?= date('d M', strtotime($checkIn)) ?> – <?= date('d M Y', strtotime($checkOut)) ?>
      <?php endif; ?>
    </p>
    </div>
  </div>
</div>


<div class="max-w-7xl mx-auto px-6 pb-24">
  <div class="flex flex-col lg:flex-row gap-8">

    <!-- ── Filters Sidebar ── -->
    <aside class="lg:w-72 shrink-0">
      <form method="GET" class="lapis-card rounded-2xl p-6 space-y-6 sticky top-24">
        <h3 class="font-display text-xl text-[var(--lapis)]">Filter Rooms</h3>

        <!-- Category -->
        <div>
          <label class="form-label">Category</label>
          <select name="category">
            <option value="0">All Categories</option>
            <?php foreach($categories as $cat): ?>
              <option value="<?=$cat['id']?>" <?=$cat['id']==$category?'selected':''?>><?=e($cat['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Bed type -->
        <div>
          <label class="form-label">Bed Type</label>
          <select name="bed_type">
            <option value="">Any</option>
            <?php foreach(['Single','Double','King','Queen','Twin'] as $b): ?>
              <option value="<?=$b?>" <?=$b===$bedType?'selected':''?>><?=$b?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Max price -->
        <div>
          <label class="form-label">Max Price / Night (PKR)</label>
          <input type="range" name="max_price" min="1" max="100000" step="100"
                 value="<?=$maxPrice<999999?$maxPrice:100000?>"
                 oninput="this.nextElementSibling.textContent='₨ '+parseInt(this.value).toLocaleString()"/>
          <span class="text-[var(--gold)] text-sm"><?= $maxPrice<999999 ? '₨ '.number_format($maxPrice,0) : 'Any' ?></span>
        </div>

        <!-- Sort -->
        <div>
          <label class="form-label">Sort By</label>
          <select name="sort">
            <option value="featured"   <?=$sort==='featured'?'selected':''?>>Featured</option>
            <option value="price_asc"  <?=$sort==='price_asc'?'selected':''?>>Price: Low to High</option>
            <option value="price_desc" <?=$sort==='price_desc'?'selected':''?>>Price: High to Low</option>
            <option value="name"       <?=$sort==='name'?'selected':''?>>Name A–Z</option>
          </select>
        </div>

        <button type="submit" class="btn-primary w-full py-3 rounded-2xl text-xs tracking-[0.14em]">
          APPLY FILTERS
        </button>
        <a href="rooms.php" class="block text-center text-[var(--text-faint)] text-xs hover:text-[var(--gold)] transition">Clear All</a>
      </form>
    </aside>

    <!-- ── Room Grid ── -->
    <div class="flex-1">
      <?php if(empty($rooms)): ?>
        <div class="lapis-card rounded-2xl p-16 text-center">
          <i data-lucide="search-x" class="w-16 h-16 text-[var(--text-faint)] mx-auto mb-6"></i>
          <h3 class="font-display text-3xl text-[var(--lapis)] mb-3">No rooms found</h3>
          <p class="text-[var(--text-muted)]">Try adjusting your filters or dates.</p>
        </div>
      <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-2 gap-6">
        <?php foreach($rooms as $room): ?>
           <?php $isBookable = ($room['display_status'] ?? 'available') === 'available'; ?>
           <div class="room-card lapis-card rounded-2xl overflow-hidden group cursor-pointer"
             onclick="window.location='room-detail.php?id=<?=$room['id']?>&check_in=<?=e($checkIn)?>&check_out=<?=e($checkOut)?>&guests=<?=$guests?>'">
          <div class="relative overflow-hidden h-52">
            <img src="../<?=e($room['thumbnail'])?>" alt="<?=e($room['name'])?>"
                 class="w-full h-full object-cover"/>
            <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
            <!-- Category -->
            <div class="absolute top-3 left-3">
              <span class="bg-white/90 text-[var(--lapis)] text-xs px-3 py-px rounded tracking-widest"><?=e($room['category_name'])?></span>
            </div>
            <!-- Status -->
            <div class="absolute bottom-3 left-3">
              <?php
                $statusMap = [
                  'available' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                  'booked'    => 'bg-rose-100 text-rose-700 border-rose-200',
                  'pending'   => 'bg-amber-100 text-amber-700 border-amber-200',
                ];
                $sc = $statusMap[$room['display_status']] ?? 'bg-stone-100 text-stone-600 border-stone-200';
              ?>
              <span class="text-[10px] px-2 py-0.5 rounded border <?= $sc ?> tracking-widest uppercase"><?= e($room['display_status']) ?></span>
            </div>
            <!-- Price -->
            <div class="absolute bottom-3 right-3 text-right">
              <div class="inline-block rounded-lg bg-white/95 px-3 py-1 shadow-sm">
                <div class="price text-[var(--lapis)] text-2xl">PKR <?= currencyRaw((float)$room['price_per_night']) ?></div>
                <div class="text-[var(--text-muted)] text-xs">/night</div>
              </div>
            </div>
            <?php if($room['is_featured']): ?>
            <div class="absolute top-3 right-3 text-[var(--gold)]"><i data-lucide="star" class="w-4 h-4 fill-current"></i></div>
            <?php endif; ?>
          </div>
          <div class="p-5">
            <div class="flex items-start justify-between mb-1">
              <h3 class="font-display text-xl text-[var(--lapis)]"><?=e($room['name'])?></h3>
              <?php if($room['avg_rating']): ?>
              <span class="flex items-center gap-1 text-[var(--gold)] text-sm">
                <i data-lucide="star" class="w-3.5 h-3.5 fill-current"></i>
                <?=number_format($room['avg_rating'],1)?>
              </span>
              <?php endif; ?>
            </div>
            <p class="text-[var(--text-muted)] text-sm leading-relaxed mb-3 line-clamp-2"><?=e($room['description'])?></p>
            <div class="flex gap-x-3 text-[var(--text-faint)] text-xs mb-3 flex-wrap">
              <span class="flex items-center gap-1"><i data-lucide="users" class="w-3 h-3"></i><?=$room['capacity']?></span>
              <span class="flex items-center gap-1"><i data-lucide="bed" class="w-3 h-3"></i><?=$room['bed_type']?></span>
              <span><i data-lucide="layers" class="w-3 h-3 inline"></i> Fl. <?=$room['floor']?></span>
              <?php if($room['size_sqft']): ?><span><?=$room['size_sqft']?> sqft</span><?php endif; ?>
            </div>
            <?php if($checkIn && $checkOut): ?>
            <?php
              $nights = max(1, (strtotime($checkOut)-strtotime($checkIn)) / 86400);
              $total  = $room['price_per_night'] * $nights;
            ?>
            <div class="text-xs flex justify-between items-center mb-3 text-[var(--text-muted)]">
              <span><?=$nights?> night<?=$nights>1?'s':''?></span>
              <span class="text-[var(--gold)] font-medium"><?=currency($total)?> total</span>
            </div>
            <?php endif; ?>
            <?php if ($isBookable && !$isBackOfficeViewer): ?>
            <a href="room-detail.php?id=<?=$room['id']?>&check_in=<?=e($checkIn)?>&check_out=<?=e($checkOut)?>&guests=<?=$guests?>"
               class="block w-full text-center btn-primary text-xs tracking-widest py-2.5 rounded-2xl">
              VIEW &amp; BOOK
            </a>
            <?php else: ?>
            <a href="room-detail.php?id=<?=$room['id']?>&check_in=<?=e($checkIn)?>&check_out=<?=e($checkOut)?>&guests=<?=$guests?>"
               class="block w-full text-center border border-[var(--stone)] text-[var(--text-muted)] text-xs tracking-widest py-2.5 rounded-2xl hover:border-[var(--gold)] hover:text-[var(--gold)]">
              VIEW DETAILS
            </a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include '../partials/footer.php'; ?>
<script>lucide.createIcons();</script>
</body>
</html>
