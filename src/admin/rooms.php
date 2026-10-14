<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireAdmin();
$db   = getDB();
$user = authUser();
refreshBookingLifecycle($db);

$roomError = getFlash('room_error');
$roomSuccess = getFlash('room_success');

$allowedBedTypes = ['Single','Double','King','Queen','Twin'];
$allowedStatuses = ['available','occupied','maintenance','reserved'];

// ── Handle form actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
    $id          = (int)($_POST['id'] ?? 0);
    $catId       = (int)($_POST['category_id'] ?? 0);
    $roomNum     = trim($_POST['room_number'] ?? '');
    $name        = trim($_POST['name'] ?? '');
    $desc        = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price_per_night'] ?? 0);
    $capacity    = (int)($_POST['capacity'] ?? 0);
    $floor       = ($_POST['floor'] ?? '') !== '' ? (int)$_POST['floor'] : null;
    $sizeSqft    = ($_POST['size_sqft'] ?? '') !== '' ? (int)$_POST['size_sqft'] : null;
    $bedType     = $_POST['bed_type'] ?? '';
        $status      = $_POST['status'] ?? '';
        $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;
        $thumbnail   = trim($_POST['thumbnail'] ?? '');
        $removeThumbnail = isset($_POST['remove_thumbnail']);

    if ($catId <= 0 || $roomNum === '' || $name === '' || $price <= 0 || $capacity <= 0) {
      flash('room_error', 'Please fill all required room fields correctly.');
      redirectUrl(adminUrl('rooms', $action === 'edit' ? ['edit' => $id] : ['add' => 1]));
    }

    if (!in_array($bedType, $allowedBedTypes, true) || !in_array($status, $allowedStatuses, true)) {
      flash('room_error', 'Invalid room type or status selected.');
      redirectUrl(adminUrl('rooms', $action === 'edit' ? ['edit' => $id] : ['add' => 1]));
    }

        // Handle thumbnail upload
        if (!empty($_FILES['thumbnail_file']['name'])) {
            $up = uploadFile('thumbnail_file', 'imgs/');
      if ($up) {
        $thumbnail = $up;
      } else {
        flash('room_error', 'Image upload failed. Please use JPG, PNG, WebP, or GIF.');
        redirectUrl(adminUrl('rooms', $action === 'edit' ? ['edit' => $id] : ['add' => 1]));
      }
        }

    try {
      $db->beginTransaction();

      if ($action === 'add') {
        $db->prepare("INSERT INTO rooms (category_id,room_number,name,description,price_per_night,capacity,floor,size_sqft,bed_type,status,is_featured,thumbnail) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$catId,$roomNum,$name,$desc,$price,$capacity,$floor,$sizeSqft,$bedType,$status,$isFeatured,$thumbnail !== '' ? $thumbnail : null]);
        $roomId = (int)$db->lastInsertId();
      } else {
        $currentThumb = $db->prepare("SELECT thumbnail FROM rooms WHERE id=?");
        $currentThumb->execute([$id]);
        $existingRoom = $currentThumb->fetch();

        if (!$existingRoom) {
          throw new RuntimeException('Room not found.');
        }

        $thumbnailToSave = $thumbnail !== '' ? $thumbnail : ($removeThumbnail ? null : ($existingRoom['thumbnail'] ?? null));

        $db->prepare("UPDATE rooms SET category_id=?,room_number=?,name=?,description=?,price_per_night=?,capacity=?,floor=?,size_sqft=?,bed_type=?,status=?,is_featured=?,thumbnail=? WHERE id=?")
           ->execute([$catId,$roomNum,$name,$desc,$price,$capacity,$floor,$sizeSqft,$bedType,$status,$isFeatured,$thumbnailToSave,$id]);
        $roomId = $id;
      }

      $db->prepare("DELETE FROM room_amenities WHERE room_id=?")->execute([$roomId]);
      foreach ($_POST['amenities'] ?? [] as $aId) {
        $db->prepare("INSERT IGNORE INTO room_amenities VALUES (?,?)")->execute([$roomId,(int)$aId]);
      }

      // Handle multiple room images upload
      $maxSortStmt = $db->prepare("SELECT COALESCE(MAX(sort_order),0) FROM room_images WHERE room_id=?");
      $maxSortStmt->execute([$roomId]);
      $maxSort = (int)$maxSortStmt->fetchColumn();

      if (!empty($_FILES['room_images']['name'][0])) {
        $allowedMime = ['image/jpeg','image/png','image/webp','image/gif'];
        foreach ($_FILES['room_images']['tmp_name'] as $i => $tmp) {
          if ($_FILES['room_images']['error'][$i] !== UPLOAD_ERR_OK) continue;
          $mime = mime_content_type($tmp);
          if (!in_array($mime, $allowedMime)) continue;
          $ext  = strtolower(pathinfo($_FILES['room_images']['name'][$i], PATHINFO_EXTENSION));
          $name = uniqid('room_', true) . '.' . $ext;
          $dest = __DIR__ . '/../imgs/' . $name;
          if (move_uploaded_file($tmp, $dest)) {
            $maxSort++;
            $caption = pathinfo($_FILES['room_images']['name'][$i], PATHINFO_FILENAME);
            $db->prepare("INSERT INTO room_images (room_id,image_path,caption,sort_order) VALUES (?,?,?,?)")
               ->execute([$roomId, 'imgs/' . $name, $caption, $maxSort]);
          }
        }
      }

      // Delete selected room image records from this room gallery.
      $deleteImageIds = array_values(array_filter(array_map('intval', $_POST['delete_room_images'] ?? [])));
      foreach ($deleteImageIds as $imgId) {
        $db->prepare("DELETE FROM room_images WHERE id=? AND room_id=?")->execute([$imgId, $roomId]);
      }

      $db->commit();
      flash('room_success', $action === 'add' ? 'Room added successfully.' : 'Room updated successfully.');
    } catch (Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }

      $message = str_contains(strtolower($e->getMessage()), 'uq_room_number') || str_contains(strtolower($e->getMessage()), 'duplicate')
        ? 'Room number already exists. Please use a unique room number.'
        : 'Unable to save room right now. Please check the form and try again.';

      flash('room_error', $message);
      redirectUrl(adminUrl('rooms', $action === 'edit' ? ['edit' => $id] : ['add' => 1]));
        }

    redirectUrl(adminUrl('rooms', ['saved' => 1]));
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
    try {
      $db->prepare("DELETE FROM rooms WHERE id=?")->execute([$id]);
      flash('room_success', 'Room deleted successfully.');
    } catch (Throwable $e) {
      flash('room_error', 'This room cannot be deleted because it is linked to existing bookings.');
    }
    redirectUrl(adminUrl('rooms', ['deleted' => 1]));
    }
}

// ── Edit mode ────────────────────────────────────────────────────────────────
$editRoom = null;
$editAmenities = [];
$editRoomImages = [];
if (isset($_GET['edit'])) {
    $editRoom = $db->prepare("SELECT * FROM rooms WHERE id=?");
    $editRoom->execute([(int)$_GET['edit']]);
    $editRoom = $editRoom->fetch();
    if ($editRoom) {
        $ea = $db->prepare("SELECT amenity_id FROM room_amenities WHERE room_id=?");
        $ea->execute([$editRoom['id']]);
        $editAmenities = array_column($ea->fetchAll(), 'amenity_id');

        $ri = $db->prepare("SELECT * FROM room_images WHERE room_id=? ORDER BY sort_order");
        $ri->execute([$editRoom['id']]);
        $editRoomImages = $ri->fetchAll();
    }
}

$rooms      = $db->query("
  SELECT
    r.*,
    rc.name AS cat,
    CASE
      WHEN r.status='maintenance' THEN 'maintenance'
      WHEN EXISTS (
        SELECT 1
        FROM bookings b
        WHERE b.room_id=r.id
          AND b.status IN ('confirmed','checked_in')
          AND b.check_out >= CURDATE()
      ) THEN 'booked'
      ELSE r.status
    END AS display_status,
    (
      SELECT CONCAT(b.booking_ref, ' · ', u.name, ' · ', DATE_FORMAT(b.check_in, '%d %b'), ' - ', DATE_FORMAT(b.check_out, '%d %b %Y'))
      FROM bookings b
      JOIN users u ON u.id=b.user_id
      WHERE b.room_id=r.id
        AND b.status IN ('confirmed','checked_in')
        AND b.check_out >= CURDATE()
      ORDER BY
        CASE WHEN CURDATE() >= b.check_in AND CURDATE() < b.check_out THEN 0 ELSE 1 END,
        b.check_in ASC,
        b.id ASC
      LIMIT 1
    ) AS active_booking_label
  FROM rooms r
  LEFT JOIN room_categories rc ON rc.id=r.category_id
  ORDER BY r.room_number
")->fetchAll();
$categories = $db->query("SELECT * FROM room_categories ORDER BY name")->fetchAll();
$amenities  = $db->query("SELECT * FROM amenities ORDER BY name")->fetchAll();
$imageOptions = array_values(array_unique(array_merge(
  glob('../imgs/*.jpg') ?: [],
  glob('../imgs/*.jpeg') ?: [],
  glob('../imgs/*.png') ?: [],
  glob('../imgs/*.webp') ?: [],
  glob('../imgs/*.gif') ?: []
)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Rooms – Sapphire Stay</title>
  <?php include 'partials/admin-head.php'; ?>
  <style>
    body{font-family:'Jost',sans-serif;background:#09090f;color:#e8e8ec;}
    .glass{background:rgba(255,255,255,0.04);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.08);}
    .sidebar-link{display:flex;align-items:center;gap:12px;padding:9px 14px;border-radius:10px;font-size:13px;letter-spacing:.06em;transition:all .2s;color:rgba(232,232,236,.45);}
    .sidebar-link:hover,.sidebar-link.active{background:rgba(255,255,255,.06);color:#fff;}.sidebar-link.active{color:#fbbf24;}
    select option{background:#150f57;color:#e8e8ec;}
    ::-webkit-scrollbar{width:4px;} ::-webkit-scrollbar-track{background:#09090f;} ::-webkit-scrollbar-thumb{background:#3350f5;border-radius:99px;}
    .badge-available{background:rgba(74,222,128,0.15);color:#4ade80;border:1px solid rgba(74,222,128,0.3);}
    .badge-occupied{background:rgba(96,165,250,0.15);color:#60a5fa;border:1px solid rgba(96,165,250,0.3);}
    .badge-booked{background:rgba(244,63,94,0.15);color:#fb7185;border:1px solid rgba(244,63,94,0.35);}
    .badge-maintenance{background:rgba(251,191,36,0.15);color:#fbbf24;border:1px solid rgba(251,191,36,0.3);}
    .badge-reserved{background:rgba(192,132,252,0.15);color:#c084fc;border:1px solid rgba(192,132,252,0.3);}
  </style>
</head>
<body class="admin-shell flex min-h-screen" data-role="<?= e(currentRole()) ?>">
<?php include 'partials/sidebar.php'; ?>
<main class="flex-1 overflow-auto">

  <!-- Topbar -->
  <div class="glass border-b border-white/10 px-6 py-4 flex items-center justify-between sticky top-0 z-30">
    <div>
      <h1 class="font-display text-2xl text-white">Manage Rooms</h1>
      <p class="text-white/30 text-xs"><?= count($rooms) ?> rooms total</p>
    </div>
    <a href="<?= adminUrl('rooms', ['add' => 1]) ?>" class="flex items-center gap-2 bg-gradient-to-r from-gold-600 to-gold-500 hover:from-gold-500 hover:to-gold-400 text-white px-4 py-2 rounded-xl text-sm tracking-widest transition-all">
      <i data-lucide="plus" class="w-4 h-4"></i> ADD ROOM
    </a>
  </div>

  <?php if ($roomError): ?>
  <div class="mx-6 mt-4 bg-red-500/20 border border-red-500/40 text-red-300 text-sm rounded-xl p-3 flex items-center gap-2">
    <i data-lucide="alert-circle" class="w-4 h-4"></i> <?= e($roomError) ?>
  </div>
  <?php endif; ?>

  <?php if ($roomSuccess): ?>
  <div class="mx-6 mt-4 bg-green-500/20 border border-green-500/40 text-green-300 text-sm rounded-xl p-3 flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i> <?= e($roomSuccess) ?>
  </div>
  <?php endif; ?>

  <div class="p-6 grid grid-cols-1 xl:grid-cols-3 gap-6">

    <!-- ── Rooms Table ── -->
    <div class="xl:col-span-2 glass rounded-2xl overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-white/10">
            <?php foreach (['#','Thumbnail','Room','Category','Price/Night','Status','Actions'] as $h): ?>
            <th class="px-4 py-3 text-left text-white/30 font-normal text-xs tracking-widest"><?= $h ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rooms as $r): ?>
          <?php $displayStatus = $r['display_status'] ?? $r['status']; ?>
          <tr class="border-b border-white/5 hover:bg-white/[0.02] transition">
            <td class="px-4 py-3 text-white/30 text-xs"><?= $r['room_number'] ?></td>
            <td class="px-4 py-3">
              <?php if ($r['thumbnail']): ?>
              <img src="../<?= e($r['thumbnail']) ?>" alt="" class="w-14 h-10 object-cover rounded-lg"/>
              <?php else: ?><div class="w-14 h-10 bg-white/5 rounded-lg flex items-center justify-center"><i data-lucide="image" class="w-4 h-4 text-white/20"></i></div><?php endif; ?>
            </td>
            <td class="px-4 py-3">
              <div class="text-white/80 font-medium"><?= e($r['name']) ?></div>
              <div class="text-white/30 text-xs"><?= $r['bed_type'] ?> &bull; <?= $r['capacity'] ?> guests</div>
            </td>
            <td class="px-4 py-3 text-white/50 text-xs"><?= e($r['cat'] ?? '—') ?></td>
            <td class="px-4 py-3 text-gold-400"><?= currency($r['price_per_night']) ?></td>
            <td class="px-4 py-3">
              <span class="badge-<?= e($displayStatus) ?> text-xs px-2 py-0.5 rounded-full"><?= ucfirst(str_replace('_', ' ', $displayStatus)) ?></span>
              <?php if ($displayStatus === 'booked' && !empty($r['active_booking_label'])): ?>
              <div class="text-white/30 text-[11px] mt-1 max-w-[190px] truncate"><?= e($r['active_booking_label']) ?></div>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                 <a href="<?= adminUrl('rooms', ['edit' => $r['id']]) ?>"
                   class="text-sapphire-400 hover:text-white transition text-xs glass px-2 py-1 rounded-lg">Edit</a>
                <form method="POST" onsubmit="return confirm('Delete this room?')">
                  <input type="hidden" name="action" value="delete"/>
                  <input type="hidden" name="id" value="<?= $r['id'] ?>"/>
                  <button type="submit" class="text-red-400 hover:text-white transition text-xs glass px-2 py-1 rounded-lg">Del</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($rooms)): ?>
          <tr><td colspan="7" class="text-center py-10 text-white/20">No rooms yet. Add one →</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- ── Add / Edit Form ── -->
    <div class="glass rounded-2xl p-6">
      <h3 class="font-display text-xl text-white mb-5">
        <?= $editRoom ? 'Edit Room: '.e($editRoom['name']) : (isset($_GET['add']) ? 'Add New Room' : 'Select a room to edit') ?>
      </h3>

      <?php if ($editRoom || isset($_GET['add'])): ?>
      <form method="POST" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="action" value="<?= $editRoom ? 'edit' : 'add' ?>"/>
        <?php if ($editRoom): ?><input type="hidden" name="id" value="<?= $editRoom['id'] ?>"/><?php endif; ?>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Room No.</label>
            <input type="text" name="room_number" required value="<?= e($editRoom['room_number'] ?? '') ?>"
                   class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-gold-500"/>
          </div>
          <div>
            <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Category</label>
            <select name="category_id" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-gold-500">
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($editRoom['category_id'] ?? 0)==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Room Name</label>
          <input type="text" name="name" required value="<?= e($editRoom['name'] ?? '') ?>"
                 class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-gold-500"/>
        </div>

        <div>
          <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Description</label>
          <textarea name="description" rows="2"
                    class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-gold-500 resize-none"><?= e($editRoom['description'] ?? '') ?></textarea>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Price (PKR/night)</label>
                 <input type="number" name="price_per_night" min="1" step="1" required value="<?= $editRoom['price_per_night'] ?? '' ?>"
                   class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-gold-500"/>
          </div>
          <div>
            <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Capacity</label>
            <input type="number" name="capacity" min="1" max="10" value="<?= $editRoom['capacity'] ?? 2 ?>"
                   class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-gold-500"/>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Floor</label>
            <input type="number" name="floor" min="1" max="20" value="<?= $editRoom['floor'] ?? 1 ?>"
                   class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-gold-500"/>
          </div>
          <div>
            <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Size (sqft)</label>
            <input type="number" name="size_sqft" value="<?= $editRoom['size_sqft'] ?? '' ?>"
                   class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-gold-500"/>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Bed Type</label>
            <select name="bed_type" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-gold-500">
              <?php foreach (['Single','Double','King','Queen','Twin'] as $b): ?>
              <option value="<?= $b ?>" <?= ($editRoom['bed_type'] ?? '')===$b?'selected':'' ?>><?= $b ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Status</label>
            <select name="status" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-gold-500">
              <?php foreach (['available','occupied','maintenance','reserved'] as $s): ?>
              <option value="<?= $s ?>" <?= ($editRoom['status'] ?? 'available')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Thumbnail -->
        <div>
          <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Thumbnail Image</label>
          <?php if (!empty($editRoom['thumbnail'])): ?>
          <img src="../<?= e($editRoom['thumbnail']) ?>" class="w-full h-24 object-cover rounded-lg mb-2"/>
          <label class="flex items-center gap-2 text-red-300 hover:text-white text-xs mb-2 cursor-pointer">
            <input type="checkbox" name="remove_thumbnail" value="1" class="accent-red-500"/>
            Remove current thumbnail
          </label>
          <?php endif; ?>
          <label class="text-white/30 text-xs block mb-1">Use from imgs/ folder:</label>
          <select name="thumbnail" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-xs focus:outline-none focus:border-gold-500 mb-2">
            <option value="">— select —</option>
            <?php foreach ($imageOptions as $img):
              $rel = 'imgs/' . basename($img); ?>
            <option value="<?= $rel ?>" <?= ($editRoom['thumbnail'] ?? '') === $rel ? 'selected':'' ?>><?= basename($img) ?></option>
            <?php endforeach; ?>
          </select>
          <label class="text-white/30 text-xs block mb-1">Or upload new:</label>
          <input type="file" name="thumbnail_file" accept="image/*"
                 class="w-full text-xs text-white/40 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-gold-600/20 file:text-gold-400"/>
        </div>

        <!-- Featured -->
        <label class="flex items-center gap-3 cursor-pointer">
          <input type="checkbox" name="is_featured" value="1"
                 <?= ($editRoom['is_featured'] ?? 0) ? 'checked' : '' ?>
                 class="w-4 h-4 accent-gold-500"/>
          <span class="text-white/60 text-sm">Featured on homepage</span>
        </label>

        <!-- Amenities -->
        <div>
          <label class="text-white/30 text-xs tracking-widest uppercase block mb-2">Amenities</label>
          <div class="grid grid-cols-2 gap-1.5 max-h-32 overflow-y-auto pr-1">
            <?php foreach ($amenities as $a): ?>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" name="amenities[]" value="<?= $a['id'] ?>"
                     <?= in_array($a['id'], $editAmenities) ? 'checked' : '' ?>
                     class="w-3.5 h-3.5 accent-gold-500"/>
              <span class="text-white/50 text-xs"><?= e($a['name']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Room Images (multiple) -->
        <div>
          <label class="text-white/30 text-xs tracking-widest uppercase block mb-2">Room Gallery Images</label>
          <?php if (!empty($editRoomImages)): ?>
          <div class="grid grid-cols-3 gap-2 mb-3">
            <?php foreach ($editRoomImages as $ri): ?>
            <div class="rounded-lg overflow-hidden border border-white/10 bg-white/[0.03]">
              <img src="../<?= e($ri['image_path']) ?>" class="w-full h-16 object-cover rounded-lg"/>
              <label class="flex items-center justify-center gap-1.5 px-2 py-1.5 cursor-pointer text-red-300 hover:text-white transition">
                <input type="checkbox" name="delete_room_images[]" value="<?= $ri['id'] ?>" class="accent-red-500"/>
                <span class="text-[11px]">Remove</span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <label class="text-white/30 text-xs block mb-1">Upload new images:</label>
          <input type="file" name="room_images[]" accept="image/*" multiple
                 class="w-full text-xs text-white/40 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-sapphire-900/40 file:text-sapphire-400 mb-2"/>
          <p class="text-white/20 text-xs mt-1">Upload images here to keep this gallery specific to this room.</p>
        </div>

        <div class="flex gap-3 pt-2">
          <button type="submit"
                  class="flex-1 bg-gradient-to-r from-gold-600 to-gold-500 text-white py-2.5 rounded-xl text-sm tracking-widest transition-all">
            <?= $editRoom ? 'SAVE CHANGES' : 'ADD ROOM' ?>
          </button>
          <a href="<?= adminUrl('rooms') ?>" class="glass text-white/50 hover:text-white px-4 py-2.5 rounded-xl text-sm transition-all flex items-center">Cancel</a>
        </div>
      </form>
      <?php else: ?>
      <div class="text-center py-10 text-white/20">
        <i data-lucide="bed" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
        <p class="text-sm">Click "Add Room" or select Edit on a room</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>
<script>lucide.createIcons();</script>
</body>
</html>
