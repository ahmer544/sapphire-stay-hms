<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireRole('admin','staff');
$user = authUser();

$db = getDB();

// Mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    $db->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([(int)$_POST['id']]);
    header('Location: ' . APP_URL . '/admin/inquiries.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_unread') {
    $db->prepare("UPDATE contact_messages SET is_read=0 WHERE id=?")->execute([(int)$_POST['id']]);
    header('Location: ' . APP_URL . '/admin/inquiries.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $db->prepare("DELETE FROM contact_messages WHERE id=?")->execute([(int)$_POST['id']]);
    header('Location: ' . APP_URL . '/admin/inquiries.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    $db->exec("UPDATE contact_messages SET is_read=1");
    header('Location: ' . APP_URL . '/admin/inquiries.php'); exit;
}

// Filters
$filter   = $_GET['filter']  ?? 'all';   // all | unread | read
$search   = trim($_GET['q']  ?? '');
$viewId   = (int)($_GET['view'] ?? 0);

$where  = [];
$params = [];
if ($filter === 'unread') { $where[] = 'is_read=0'; }
if ($filter === 'read')   { $where[] = 'is_read=1'; }
if ($search !== '') {
    $where[] = '(name LIKE :q OR email LIKE :q OR subject LIKE :q OR message LIKE :q)';
    $params[':q'] = "%$search%";
}
$sql = "SELECT * FROM contact_messages" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

$unreadCount = (int)$db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
$totalCount  = (int)$db->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();

// Viewing a single message — auto-mark read
$viewing = null;
if ($viewId) {
    $vs = $db->prepare("SELECT * FROM contact_messages WHERE id=?");
    $vs->execute([$viewId]);
    $viewing = $vs->fetch();
    if ($viewing && !$viewing['is_read']) {
        $db->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([$viewId]);
        $viewing['is_read'] = 1;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Contact Inquiries – Sapphire Stay Admin</title>
  <?php include 'partials/admin-head.php'; ?>
  <style>
    body{font-family:'Jost',sans-serif;background:#09090f;color:#e8e8ec;}
    .glass{background:rgba(255,255,255,0.04);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.08);}
    .sidebar-link{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;font-size:13px;letter-spacing:.04em;color:rgba(232,232,236,0.55);transition:all .2s;text-decoration:none;}
    .sidebar-link:hover{background:rgba(255,255,255,0.05);color:#e8e8ec;}
    .sidebar-link.active{background:rgba(51,80,245,0.18);color:#fff;border-left:2px solid #d4a843;}
    .msg-row{cursor:pointer;border-bottom:1px solid rgba(255,255,255,0.05);transition:background .15s;}
    .msg-row:hover{background:rgba(255,255,255,0.04);}
    .msg-row.unread td:first-child{border-left:3px solid #d4a843;}
    ::-webkit-scrollbar{width:4px;} ::-webkit-scrollbar-track{background:#09090f;} ::-webkit-scrollbar-thumb{background:#3350f5;border-radius:99px;}
  </style>
</head>
<body class="admin-shell flex min-h-screen" data-role="<?= e(currentRole()) ?>">

<?php include 'partials/sidebar.php'; ?>

<main class="flex-1 min-w-0 p-6 overflow-y-auto">

  <!-- Header -->
  <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
      <h1 class="font-display text-3xl text-white">Contact Inquiries</h1>
      <p class="text-white/30 text-xs mt-1"><?= $totalCount ?> total &bull; <span class="text-gold-400"><?= $unreadCount ?> unread</span></p>
    </div>
    <?php if ($unreadCount > 0): ?>
    <form method="POST">
      <input type="hidden" name="action" value="mark_all_read"/>
      <button class="flex items-center gap-2 text-xs tracking-widest px-4 py-2.5 rounded-xl transition-all"
              style="background:rgba(212,168,67,0.12);border:1px solid rgba(212,168,67,0.25);color:#d4a843;">
        <i data-lucide="check-check" class="w-4 h-4"></i> MARK ALL READ
      </button>
    </form>
    <?php endif; ?>
  </div>

  <?php if ($viewing): ?>
  <!-- ── Single Message View ── -->
  <div class="mb-5">
    <a href="<?= APP_URL ?>/admin/inquiries.php" class="flex items-center gap-2 text-xs tracking-widest text-white/40 hover:text-gold-400 transition w-fit">
      <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> BACK TO INBOX
    </a>
  </div>
  <div class="glass rounded-2xl p-7 max-w-3xl">
    <div class="flex items-start justify-between gap-4 mb-6">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center font-display text-xl font-semibold text-white flex-shrink-0"
             style="background:linear-gradient(135deg,rgba(51,80,245,0.4),rgba(212,168,67,0.2));">
          <?= strtoupper(substr($viewing['name'], 0, 1)) ?>
        </div>
        <div>
          <p class="text-white font-medium text-base"><?= e($viewing['name']) ?></p>
          <a href="mailto:<?= e($viewing['email']) ?>" class="text-gold-400 text-sm hover:underline"><?= e($viewing['email']) ?></a>
          <?php if ($viewing['phone']): ?>
            <span class="text-white/30 text-xs ml-3">· <?= e($viewing['phone']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <p class="text-white/30 text-xs whitespace-nowrap"><?= date('D d M Y, g:i A', strtotime($viewing['created_at'])) ?></p>
    </div>

    <?php if ($viewing['subject']): ?>
    <div class="mb-4">
      <span class="text-[10px] tracking-widest uppercase text-white/30">Subject</span>
      <p class="text-gold-400 text-sm font-medium mt-1"><?= e($viewing['subject']) ?></p>
    </div>
    <?php endif; ?>

    <div class="rounded-xl p-5 mb-6 text-white/75 text-sm leading-relaxed whitespace-pre-wrap"
         style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
      <?= e($viewing['message']) ?>
    </div>

    <div class="flex flex-wrap gap-3">
      <a href="mailto:<?= e($viewing['email']) ?>?subject=Re: <?= e($viewing['subject'] ?: 'Your Inquiry') ?>"
         class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm tracking-widest text-white transition-all"
         style="background:linear-gradient(135deg,#b8933e,#e0b96a);">
        <i data-lucide="reply" class="w-4 h-4"></i> REPLY BY EMAIL
      </a>
      <?php if ($viewing['phone']): ?>
      <a href="tel:<?= e(phoneHref($viewing['phone'])) ?>"
         class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm tracking-widest text-white/70 transition-all glass hover:text-white">
        <i data-lucide="phone" class="w-4 h-4"></i> CALL
      </a>
      <?php endif; ?>
      <form method="POST" class="inline">
        <input type="hidden" name="action" value="<?= $viewing['is_read'] ? 'mark_unread' : 'mark_read' ?>"/>
        <input type="hidden" name="id" value="<?= $viewing['id'] ?>"/>
        <button class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm tracking-widest text-white/50 glass hover:text-white transition-all">
          <i data-lucide="<?= $viewing['is_read'] ? 'mail' : 'mail-open' ?>" class="w-4 h-4"></i>
          <?= $viewing['is_read'] ? 'MARK UNREAD' : 'MARK READ' ?>
        </button>
      </form>
      <form method="POST" class="inline" onsubmit="return confirm('Delete this message?')">
        <input type="hidden" name="action" value="delete"/>
        <input type="hidden" name="id" value="<?= $viewing['id'] ?>"/>
        <button class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm tracking-widest transition-all"
                style="color:rgba(248,113,113,0.8);background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.15);">
          <i data-lucide="trash-2" class="w-4 h-4"></i> DELETE
        </button>
      </form>
    </div>
  </div>

  <?php else: ?>
  <!-- ── Inbox List ── -->

  <!-- Filters + Search -->
  <form method="GET" class="glass rounded-xl px-5 py-4 flex flex-wrap gap-4 items-end mb-5">
    <div class="flex-1 min-w-[200px]">
      <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Search</label>
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Name, email, subject…"
             class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-gold-500"/>
    </div>
    <div>
      <label class="text-white/30 text-xs tracking-widest uppercase block mb-1">Filter</label>
      <select name="filter" class="bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-gold-500">
        <option value="all"    <?= $filter==='all'    ?'selected':''?>>All Messages</option>
        <option value="unread" <?= $filter==='unread' ?'selected':''?>>Unread Only</option>
        <option value="read"   <?= $filter==='read'   ?'selected':''?>>Read</option>
      </select>
    </div>
    <button class="bg-white/5 border border-white/10 hover:border-gold-500/50 text-white px-5 py-2.5 rounded-lg text-sm tracking-widest transition-all">FILTER</button>
    <?php if ($search||$filter!=='all'): ?>
      <a href="<?= APP_URL ?>/admin/inquiries.php" class="text-white/30 text-xs hover:text-gold-400 transition self-center">Clear</a>
    <?php endif; ?>
  </form>

  <?php if (empty($messages)): ?>
  <div class="glass rounded-2xl p-16 text-center">
    <i data-lucide="inbox" class="w-14 h-14 text-white/15 mx-auto mb-5"></i>
    <p class="text-white/40 text-lg font-display">No inquiries yet</p>
    <p class="text-white/20 text-sm mt-2">Contact form submissions will appear here.</p>
  </div>
  <?php else: ?>
  <div class="glass rounded-2xl overflow-hidden">
    <table class="w-full text-sm">
      <thead>
        <tr style="border-bottom:1px solid rgba(255,255,255,0.07);">
          <th class="text-left text-white/30 text-[10px] tracking-widest uppercase px-5 py-3">Sender</th>
          <th class="text-left text-white/30 text-[10px] tracking-widest uppercase px-5 py-3 hidden md:table-cell">Subject</th>
          <th class="text-left text-white/30 text-[10px] tracking-widest uppercase px-5 py-3 hidden lg:table-cell">Preview</th>
          <th class="text-left text-white/30 text-[10px] tracking-widest uppercase px-5 py-3">Date</th>
          <th class="px-5 py-3"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($messages as $msg): ?>
        <tr class="msg-row <?= !$msg['is_read'] ? 'unread' : '' ?>"
            onclick="window.location='<?= APP_URL ?>/admin/inquiries.php?view=<?= $msg['id'] ?>'">
          <td class="px-5 py-4">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold text-white flex-shrink-0"
                   style="background:rgba(51,80,245,0.25);">
                <?= strtoupper(substr($msg['name'], 0, 1)) ?>
              </div>
              <div>
                <p class="<?= !$msg['is_read'] ? 'text-white font-semibold' : 'text-white/70' ?> text-sm leading-tight"><?= e($msg['name']) ?></p>
                <p class="text-white/30 text-xs"><?= e($msg['email']) ?></p>
              </div>
            </div>
          </td>
          <td class="px-5 py-4 hidden md:table-cell">
            <?php if ($msg['subject']): ?>
              <span class="text-xs px-2.5 py-1 rounded-full" style="background:rgba(212,168,67,0.12);color:#d4a843;"><?= e($msg['subject']) ?></span>
            <?php else: ?>
              <span class="text-white/25 text-xs">—</span>
            <?php endif; ?>
          </td>
          <td class="px-5 py-4 hidden lg:table-cell max-w-xs">
            <p class="text-white/40 text-xs truncate"><?= e(mb_substr($msg['message'], 0, 80)) ?>…</p>
          </td>
          <td class="px-5 py-4 whitespace-nowrap">
            <p class="text-white/40 text-xs"><?= date('d M Y', strtotime($msg['created_at'])) ?></p>
            <p class="text-white/25 text-[10px]"><?= date('g:i A', strtotime($msg['created_at'])) ?></p>
          </td>
          <td class="px-5 py-4" onclick="event.stopPropagation()">
            <div class="flex items-center gap-2">
              <?php if (!$msg['is_read']): ?>
              <form method="POST" class="inline">
                <input type="hidden" name="action" value="mark_read"/>
                <input type="hidden" name="id" value="<?= $msg['id'] ?>"/>
                <button title="Mark read" class="w-7 h-7 rounded-lg flex items-center justify-center text-white/30 hover:text-gold-400 hover:bg-white/5 transition">
                  <i data-lucide="mail-open" class="w-3.5 h-3.5"></i>
                </button>
              </form>
              <?php endif; ?>
              <form method="POST" class="inline" onsubmit="return confirm('Delete?')">
                <input type="hidden" name="action" value="delete"/>
                <input type="hidden" name="id" value="<?= $msg['id'] ?>"/>
                <button title="Delete" class="w-7 h-7 rounded-lg flex items-center justify-center transition" style="color:rgba(248,113,113,0.5);" onmouseover="this.style.background='rgba(239,68,68,0.1)'" onmouseout="this.style.background='transparent'">
                  <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</main>
<script>lucide.createIcons();</script>
</body>
</html>
