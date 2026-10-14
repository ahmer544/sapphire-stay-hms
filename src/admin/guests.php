<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireAdmin();
$db = getDB();

$guestError = getFlash('guest_error');
$guestSuccess = getFlash('guest_success');
$editGuestId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_guest') {
  $guestId = (int)($_POST['guest_id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));
  $phone = trim($_POST['phone'] ?? '');
  $isActive = isset($_POST['is_active']) ? 1 : 0;

  if ($guestId <= 0 || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('guest_error', 'Valid name and email are required.');
    redirectUrl(APP_URL . '/admin/guests.php?edit=' . $guestId);
  }

  try {
    $exists = $db->prepare("SELECT id FROM users WHERE email=? AND id<>?");
    $exists->execute([$email, $guestId]);
    if ($exists->fetch()) {
      throw new RuntimeException('Another guest already uses this email address.');
    }

    $db->prepare("UPDATE users SET name=?, email=?, phone=?, is_active=? WHERE id=? AND role='customer'")
      ->execute([$name, $email, $phone !== '' ? $phone : null, $isActive, $guestId]);
    flash('guest_success', 'Guest updated successfully.');
    redirect('admin/guests.php');
  } catch (Throwable $e) {
    flash('guest_error', $e->getMessage() ?: 'Unable to update guest right now.');
    redirectUrl(APP_URL . '/admin/guests.php?edit=' . $guestId);
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_guest') {
  $guestId = (int)($_POST['guest_id'] ?? 0);

  try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE user_id=?");
    $countStmt->execute([$guestId]);
    if ((int)$countStmt->fetchColumn() > 0) {
      throw new RuntimeException('This guest has booking history. Please keep the account and mark it inactive instead.');
    }

    $db->prepare("DELETE FROM messages WHERE sender_id=? OR recipient_id=?")->execute([$guestId, $guestId]);
    $db->prepare("DELETE FROM reviews WHERE user_id=?")->execute([$guestId]);
    $db->prepare("DELETE FROM users WHERE id=? AND role='customer'")->execute([$guestId]);
    flash('guest_success', 'Guest deleted successfully.');
  } catch (Throwable $e) {
    flash('guest_error', $e->getMessage() ?: 'Unable to delete guest right now.');
  }

  redirect('admin/guests.php');
}

$guests = $db->query("
    SELECT u.*,
      COALESCE(bg.total_bookings, 0) AS total_bookings,
      COALESCE(bg.total_spent, 0) AS total_spent,
      bg.last_booking
    FROM users u
    LEFT JOIN (
      SELECT user_id,
        COUNT(*) AS total_bookings,
        COALESCE(SUM(total_amount), 0) AS total_spent,
        MAX(created_at) AS last_booking
      FROM bookings
      GROUP BY user_id
    ) bg ON bg.user_id = u.id
    WHERE u.role='customer'
    ORDER BY u.created_at DESC
")->fetchAll();

  $editGuest = null;
  if ($editGuestId > 0) {
    foreach ($guests as $guest) {
      if ((int)$guest['id'] === $editGuestId) {
        $editGuest = $guest;
        break;
      }
    }
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Guests – Sapphire Stay Admin</title>
  <?php include 'partials/admin-head.php'; ?>
</head>
<body class="admin-shell flex min-h-screen" data-role="<?= e(currentRole()) ?>">
<?php include 'partials/sidebar.php'; ?>
<main class="flex-1 overflow-auto">

  <div class="glass border-b border-white/10 px-6 py-4 sticky top-0 z-30">
    <h1 class="font-display text-2xl text-white">Guest Management</h1>
    <p class="text-white/30 text-xs"><?= count($guests) ?> registered guests</p>
  </div>

  <div class="p-6 space-y-5">
    <?php if ($guestError): ?>
    <div class="bg-red-500/20 border border-red-500/40 text-red-300 text-sm rounded-xl p-3 flex items-center gap-2">
      <i data-lucide="alert-circle" class="w-4 h-4"></i> <?= e($guestError) ?>
    </div>
    <?php endif; ?>

    <?php if ($guestSuccess): ?>
    <div class="bg-green-500/20 border border-green-500/40 text-green-300 text-sm rounded-xl p-3 flex items-center gap-2">
      <i data-lucide="check-circle" class="w-4 h-4"></i> <?= e($guestSuccess) ?>
    </div>
    <?php endif; ?>

    <?php if ($editGuest): ?>
    <div class="glass rounded-2xl p-5">
      <div class="flex items-center justify-between gap-4 mb-4 flex-wrap">
        <div>
          <h2 class="font-display text-xl text-white">Edit Guest</h2>
          <p class="text-white/30 text-xs mt-1">Update customer details or disable the account.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/guests.php" class="text-white/40 hover:text-white text-xs tracking-widest transition">CLOSE</a>
      </div>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="update_guest"/>
        <input type="hidden" name="guest_id" value="<?= $editGuest['id'] ?>"/>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Name</label>
            <input type="text" name="name" required value="<?= e($editGuest['name']) ?>" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-gold-500"/>
          </div>
          <div>
            <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Email</label>
            <input type="email" name="email" required value="<?= e($editGuest['email']) ?>" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-gold-500"/>
          </div>
          <div>
            <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Phone</label>
            <input type="text" name="phone" value="<?= e((string)$editGuest['phone']) ?>" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-gold-500"/>
          </div>
        </div>

        <label class="flex items-center gap-3 text-white/60 text-sm">
          <input type="checkbox" name="is_active" value="1" class="accent-gold-500" <?= (int)$editGuest['is_active'] === 1 ? 'checked' : '' ?>/>
          Keep guest account active
        </label>

        <div class="flex justify-end gap-3">
          <a href="<?= adminUrl('messages', ['contact' => $editGuest['id']]) ?>" class="glass text-sapphire-400 hover:text-white px-4 py-2.5 rounded-lg text-sm">Message</a>
          <button type="submit" class="bg-gradient-to-r from-gold-600 to-gold-500 text-white px-5 py-2.5 rounded-lg text-sm tracking-widest">SAVE GUEST</button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <div class="glass rounded-2xl overflow-hidden overflow-x-auto">
      <table class="w-full text-sm min-w-[750px]">
        <thead>
          <tr class="border-b border-white/10">
            <?php foreach (['Guest','Email','Phone','Bookings','Total Spent','Member Since','Status','Actions'] as $h): ?>
            <th class="px-4 py-3 text-left text-white/30 font-normal text-xs tracking-widest"><?= $h ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($guests as $g): ?>
          <tr class="border-b border-white/5 hover:bg-white/[0.02] transition">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-sapphire-900/60 flex items-center justify-center text-white font-display text-sm flex-shrink-0">
                  <?= strtoupper(substr($g['name'],0,1)) ?>
                </div>
                <div class="text-white/80 font-medium"><?= e($g['name']) ?></div>
              </div>
            </td>
            <td class="px-4 py-3 text-white/50 text-xs"><?= e($g['email']) ?></td>
            <td class="px-4 py-3 text-white/50 text-xs"><?= e($g['phone'] ?? '—') ?></td>
            <td class="px-4 py-3 text-sapphire-400"><?= $g['total_bookings'] ?></td>
            <td class="px-4 py-3 text-gold-400"><?= currency($g['total_spent']) ?></td>
            <td class="px-4 py-3 text-white/40 text-xs"><?= date('d M Y', strtotime($g['created_at'])) ?></td>
            <td class="px-4 py-3">
              <?php if ($g['is_active']): ?>
                <span class="text-green-400 text-xs glass px-2 py-0.5 rounded-full border border-green-500/30">Active</span>
              <?php else: ?>
                <span class="text-red-400 text-xs glass px-2 py-0.5 rounded-full border border-red-500/30">Inactive</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-1.5">
                <a href="<?= APP_URL ?>/admin/guests.php?edit=<?= $g['id'] ?>" class="glass text-gold-400 hover:text-white px-2 py-1 rounded-lg text-xs transition" title="Edit Guest">
                  <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                </a>
                <a href="<?= adminUrl('messages', ['contact' => $g['id']]) ?>" class="glass text-sapphire-400 hover:text-white px-2 py-1 rounded-lg text-xs transition" title="Message Guest">
                  <i data-lucide="messages-square" class="w-3.5 h-3.5"></i>
                </a>
                <form method="POST" onsubmit="return confirm('Delete this guest? This works only if no bookings exist.')">
                  <input type="hidden" name="action" value="delete_guest"/>
                  <input type="hidden" name="guest_id" value="<?= $g['id'] ?>"/>
                  <button type="submit" class="glass text-red-400 hover:text-white px-2 py-1 rounded-lg text-xs transition" title="Delete Guest">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($guests)): ?>
          <tr><td colspan="8" class="text-center py-10 text-white/20">No guests registered yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<script>lucide.createIcons();</script>
</body>
</html>
