<?php
$role = $_SESSION['user']['role'] ?? '';
$panelLabel = $role === 'staff' ? 'Staff Desk' : 'Admin Panel';
?>
<aside class="admin-sidebar w-64 shrink-0 flex flex-col min-h-screen sticky top-0 h-screen overflow-y-auto border-r bg-white border-[var(--stone)]">
  <!-- Logo -->
  <div class="p-5 border-b border-[var(--stone)]">
    <a href="../index.php" class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-full bg-[var(--lapis)] flex items-center justify-center flex-shrink-0 ring-1 ring-[var(--stone)]">
        <span class="font-display font-semibold text-sm text-[#f7f4ed]">S</span>
      </div>
      <div class="leading-tight min-w-0">
        <div class="font-display text-[17px] text-[var(--lapis)] truncate">Sapphire <span class="text-[var(--gold)]">Stay</span></div>
        <div class="text-[9px] tracking-[0.18em] uppercase text-[var(--text-faint)]"><?= e($panelLabel) ?></div>
      </div>
    </a>
  </div>

  <!-- User -->
  <div class="px-4 py-3 border-b border-[var(--stone)]">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center font-display text-sm bg-[var(--lapis)] text-[#f7f4ed]">
        <?= strtoupper(substr($_SESSION['user']['name'] ?? 'A', 0, 1)) ?>
      </div>
      <div class="min-w-0">
        <div class="text-sm text-[var(--text)] truncate font-medium"><?= htmlspecialchars($_SESSION['user']['name'] ?? '') ?></div>
        <div class="text-[10px] text-[var(--text-faint)] truncate"><?= htmlspecialchars($_SESSION['user']['email'] ?? '') ?></div>
      </div>
    </div>
  </div>

  <!-- Navigation -->
  <?php
  $currentScript = basename($_SERVER['PHP_SELF']);
  $currentRoute = $_GET['page'] ?? match ($currentScript) {
    'dashboard.php' => 'dashboard',
    'bookings.php' => 'bookings',
    default => pathinfo($currentScript, PATHINFO_FILENAME),
  };

  $links = $role === 'staff'
    ? [
      ['dashboard', adminUrl('dashboard'), 'layout-dashboard', 'Staff Dashboard'],
      ['checkin', adminUrl('checkin'), 'log-in', 'Check-in / Out'],
      ['booked-rooms', adminUrl('booked-rooms'), 'calendar-days', 'Booked Rooms'],
      ['messages', adminUrl('messages'), 'messages-square', 'Guest Messages'],
      ['payments', adminUrl('payments'), 'credit-card', 'Payments'],
      ['invoices', adminUrl('invoices'), 'file-text', 'Invoices'],
    ]
    : [
      ['dashboard', adminUrl('dashboard'), 'layout-dashboard', 'Dashboard'],
      ['bookings', APP_URL . '/admin/bookings.php', 'calendar', 'Bookings'],
      ['booked-rooms', adminUrl('booked-rooms'), 'calendar-days', 'Booked Rooms'],
      ['payments', adminUrl('payments'), 'credit-card', 'Payments'],
      ['messages', adminUrl('messages'), 'messages-square', 'Messages'],
      ['checkin', adminUrl('checkin'), 'log-in', 'Check-in / Out'],
      ['invoices', adminUrl('invoices'), 'file-text', 'Invoices'],
      ['inquiries', APP_URL . '/admin/inquiries.php', 'inbox', 'Contact Inquiries'],
    ];
  $adminOnly = [
    ['rooms', adminUrl('rooms'), 'bed', 'Rooms'],
    ['guests', adminUrl('guests'), 'users', 'Guests'],
    ['reports', APP_URL . '/admin/reports.php', 'bar-chart-2', 'Reports'],
  ];
  $adminOnly = array_values(array_filter($adminOnly, function (array $item): bool {
    return file_exists(dirname(__DIR__) . '/' . basename(parse_url($item[1], PHP_URL_PATH)));
  }));
  $unreadMessageCount = messageUnreadCount((int)($_SESSION['user']['id'] ?? 0));
  try { $unreadInquiryCount = (int)getDB()->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn(); } catch(\Throwable $e) { $unreadInquiryCount = 0; }
  ?>
  <nav class="flex-1 px-3 py-4 overflow-y-auto space-y-0.5 text-sm">
    <div class="px-3 mb-1.5 text-[10px] tracking-[0.14em] uppercase text-[var(--text-faint)]"><?= $role === 'staff' ? 'Operations' : 'Main' ?></div>
    <?php foreach ($links as [$route, $href, $icon, $label]):
      $isActive = ($currentRoute === $route);
    ?>
    <a href="<?= $href ?>" class="sidebar-link flex items-center gap-2.5 px-3 py-2 rounded-xl <?= $isActive ? 'is-active' : '' ?>">
      <i data-lucide="<?= $icon ?>" class="w-4 h-4 flex-shrink-0"></i>
      <span class="truncate flex-1"><?= $label ?></span>
      <?php if ($route === 'messages' && $unreadMessageCount > 0): ?>
        <span class="min-w-[18px] h-[18px] px-1 rounded-full bg-[var(--gold)] text-[#f7f4ed] text-[10px] font-semibold flex items-center justify-center"><?= $unreadMessageCount ?></span>
      <?php endif; ?>
      <?php if ($route === 'inquiries' && $unreadInquiryCount > 0): ?>
        <span class="min-w-[18px] h-[18px] px-1 rounded-full bg-[var(--gold)] text-[#f7f4ed] text-[10px] font-semibold flex items-center justify-center"><?= $unreadInquiryCount ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>

    <?php if (in_array($role, ['admin'])): ?>
    <div class="px-3 mt-4 mb-1.5 text-[10px] tracking-[0.14em] uppercase text-[var(--text-faint)]">Admin</div>
    <?php foreach ($adminOnly as [$route, $href, $icon, $label]):
      $isActive = ($currentRoute === $route);
    ?>
    <a href="<?= $href ?>" class="sidebar-link flex items-center gap-2.5 px-3 py-2 rounded-xl <?= $isActive ? 'is-active' : '' ?>">
      <i data-lucide="<?= $icon ?>" class="w-4 h-4 flex-shrink-0"></i>
      <span class="truncate"><?= $label ?></span>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
  </nav>

  <!-- Bottom actions -->
  <div class="px-3 pb-5 pt-3 border-t border-[var(--stone)] text-sm">
    <a href="<?= APP_URL ?>/index.php" target="_blank" class="sidebar-link flex items-center gap-2 px-3 py-2 text-[var(--text-muted)] hover:text-[var(--text)]">
      <i data-lucide="external-link" class="w-4 h-4"></i>
      <span>View Live Site</span>
    </a>
    <a href="<?= APP_URL ?>/auth/logout.php" class="sidebar-link flex items-center gap-2 px-3 py-2 text-red-600/70 hover:text-red-600">
      <i data-lucide="log-out" class="w-4 h-4"></i>
      <span>Sign Out</span>
    </a>
  </div>
</aside>

<style>
  /* Sidebar link base (scoped) */
  .sidebar-link { transition: all .15s ease; font-size: 13px; }
  .sidebar-link:hover:not(.is-active) { color: var(--text); }
</style>
