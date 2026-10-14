<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireRole('admin','staff');

ensureMessagingSchema();
$db = getDB();
$user = authUser();
$canOpenGuests = currentRole() === 'admin';

$success = getFlash('admin_message_success');
$error = getFlash('admin_message_error');

$contactsStmt = $db->prepare(
    "SELECT u.id, u.name, u.email, u.phone,
        lm.body AS last_body,
        lm.created_at AS last_at,
        (
            SELECT COUNT(*) FROM messages unread
            WHERE unread.sender_id=u.id AND unread.recipient_id=? AND unread.is_read=0
        ) AS unread_count
     FROM users u
     JOIN (
        SELECT
          CASE WHEN sender_id=? THEN recipient_id ELSE sender_id END AS contact_id,
          MAX(id) AS last_message_id
        FROM messages
        WHERE sender_id=? OR recipient_id=?
        GROUP BY contact_id
     ) conv ON conv.contact_id=u.id
     JOIN messages lm ON lm.id=conv.last_message_id
     WHERE u.role='customer' AND u.is_active=1
     ORDER BY lm.created_at DESC, lm.id DESC"
);
$contactsStmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$contacts = $contactsStmt->fetchAll();

$contactIds = array_map(static fn(array $contact): int => (int)$contact['id'], $contacts);
$requestedId = (int)($_GET['contact'] ?? 0);
$selectedId = $requestedId > 0 ? $requestedId : (int)($contacts[0]['id'] ?? 0);

$selectedContact = null;
foreach ($contacts as $contact) {
    if ((int)$contact['id'] === $selectedId) {
        $selectedContact = $contact;
        break;
    }
}

if (!$selectedContact && $requestedId > 0) {
    $guestStmt = $db->prepare(
        "SELECT id, name, email, phone, NULL AS last_body, NULL AS last_at, 0 AS unread_count
         FROM users
         WHERE id=? AND role='customer' AND is_active=1
         LIMIT 1"
    );
    $guestStmt->execute([$requestedId]);
    $selectedContact = $guestStmt->fetch();
    if ($selectedContact) {
        $selectedId = (int)$selectedContact['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipientId = (int)($_POST['recipient_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $isSelectedGuest = $selectedContact && $recipientId === (int)$selectedContact['id'];

    if (!in_array($recipientId, $contactIds, true) && !$isSelectedGuest) {
        flash('admin_message_error', 'Please choose a valid guest.');
    } else {
        try {
            sendDirectMessage((int)$user['id'], $recipientId, $body, $subject);
            flash('admin_message_success', 'Message sent to guest.');
        } catch (Throwable $e) {
            flash('admin_message_error', $e->getMessage() ?: 'Unable to send message right now.');
        }
    }

    redirectUrl(adminUrl('messages', ['contact' => $recipientId]));
}

$messages = [];
if ($selectedContact) {
    markConversationRead((int)$user['id'], $selectedId);

    $messagesStmt = $db->prepare(
        "SELECT m.*, sender.name AS sender_name
         FROM messages m
         JOIN users sender ON sender.id=m.sender_id
         WHERE (m.sender_id=? AND m.recipient_id=?) OR (m.sender_id=? AND m.recipient_id=?)
         ORDER BY m.created_at ASC, m.id ASC"
    );
    $messagesStmt->execute([(int)$user['id'], $selectedId, $selectedId, (int)$user['id']]);
    $messages = $messagesStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Messages – Sapphire Stay Admin</title>
  <?php include 'partials/admin-head.php'; ?>
  <style>
    .message-shell{display:grid;grid-template-columns:minmax(280px,340px) minmax(0,1fr);gap:24px;align-items:start;}
    .contact-list{max-height:680px;overflow-y:auto;}
    .conversation-body{min-height:440px;max-height:440px;overflow-y:auto;background:linear-gradient(180deg,rgba(255,255,255,0.01),rgba(255,255,255,0.03));}
    .message-bubble{box-shadow:0 20px 45px rgba(0,0,0,.18);}
    @media (max-width: 1279px){.message-shell{grid-template-columns:1fr;}}
    ::-webkit-scrollbar{width:4px;} ::-webkit-scrollbar-track{background:#09090f;} ::-webkit-scrollbar-thumb{background:#3350f5;border-radius:99px;}
  </style>
</head>
<body class="admin-shell flex min-h-screen" data-role="<?= e(currentRole()) ?>">
<?php include 'partials/sidebar.php'; ?>
<main class="flex-1 overflow-auto">
  <div class="glass border-b border-white/10 px-6 py-4 sticky top-0 z-30">
    <h1 class="font-display text-2xl text-white">Guest Messages</h1>
    <p class="text-white/30 text-xs">Reply to guest questions and send updates from the front desk.</p>
  </div>

  <div class="p-6 space-y-5">
    <?php if ($success): ?>
      <div class="bg-green-500/20 border border-green-500/40 text-green-300 text-sm rounded-xl p-4"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="bg-red-500/20 border border-red-500/40 text-red-300 text-sm rounded-xl p-4"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="message-shell">
      <aside class="glass rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-white/10">
          <h2 class="font-display text-xl text-white">Conversations</h2>
        </div>
        <div class="divide-y divide-white/5 contact-list">
          <?php foreach ($contacts as $contact): ?>
            <a href="<?= adminUrl('messages', ['contact' => $contact['id']]) ?>" class="block px-5 py-4 hover:bg-white/[0.03] transition <?= (int)$contact['id'] === $selectedId ? 'bg-white/[0.04] border-l-2 border-gold-500' : '' ?>">
              <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-sapphire-900/60 flex items-center justify-center text-white font-display"><?= strtoupper(substr($contact['name'],0,1)) ?></div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center justify-between gap-2">
                    <p class="text-white font-medium truncate"><?= e($contact['name']) ?></p>
                    <?php if ((int)$contact['unread_count'] > 0): ?><span class="min-w-5 h-5 px-1.5 rounded-full bg-gold-500 text-sapphire-950 text-[10px] font-semibold flex items-center justify-center leading-none"><?= (int)$contact['unread_count'] ?></span><?php endif; ?>
                  </div>
                  <p class="text-white/30 text-xs truncate mt-1"><?= e($contact['email']) ?></p>
                  <p class="text-white/35 text-sm truncate mt-2"><?= e((string)$contact['last_body']) ?></p>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
          <?php if (!$contacts): ?>
            <div class="px-5 py-10 text-center text-white/25">
              <p>No conversations yet.</p>
              <?php if ($canOpenGuests): ?>
              <a href="<?= APP_URL ?>/admin/guests.php" class="inline-flex items-center gap-2 mt-4 text-gold-400 hover:text-gold-300 text-xs tracking-widest">
                <i data-lucide="users" class="w-4 h-4"></i> GUESTS
              </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </aside>

      <section class="glass rounded-2xl overflow-hidden flex flex-col min-h-[680px]">
        <?php if ($selectedContact): ?>
          <div class="px-6 py-5 border-b border-white/10 flex items-center justify-between gap-4 bg-white/[0.02]">
            <div>
              <h2 class="text-white font-medium text-2xl leading-none"><?= e($selectedContact['name']) ?></h2>
              <p class="text-white/35 text-xs mt-2 tracking-wide"><?= e($selectedContact['email']) ?><?= !empty($selectedContact['phone']) ? ' • ' . e($selectedContact['phone']) : '' ?></p>
            </div>
          </div>

          <div class="flex-1 px-6 py-5 space-y-4 conversation-body">
            <?php foreach ($messages as $message): ?>
              <?php $isMine = (int)$message['sender_id'] === (int)$user['id']; ?>
              <div class="flex <?= $isMine ? 'justify-end' : 'justify-start' ?>">
                <div class="message-bubble max-w-2xl rounded-2xl px-5 py-4 <?= $isMine ? 'bg-gold-500/20 border border-gold-500/30' : 'glass border border-white/6' ?>">
                  <p class="text-[11px] uppercase tracking-widest <?= $isMine ? 'text-gold-300' : 'text-white/30' ?> mb-2"><?= e($message['sender_name']) ?> • <?= date('d M Y H:i', strtotime($message['created_at'])) ?></p>
                  <?php if (!empty($message['subject'])): ?><p class="text-white font-medium mb-1"><?= e($message['subject']) ?></p><?php endif; ?>
                  <p class="text-white/80 text-sm leading-relaxed whitespace-pre-line"><?= e($message['body']) ?></p>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (!$messages): ?>
              <div class="h-full flex items-center justify-center text-center text-white/25">
                <div>
                  <i data-lucide="messages-square" class="w-12 h-12 mx-auto mb-3"></i>
                  <p>No conversation yet. Send the first message to this guest.</p>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <form method="POST" class="border-t border-white/10 p-6 space-y-4">
            <input type="hidden" name="recipient_id" value="<?= $selectedContact['id'] ?>"/>
            <div>
              <label class="text-white/35 text-xs tracking-widest uppercase block mb-2">Subject</label>
              <input type="text" name="subject" maxlength="180" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-gold-500" placeholder="Optional subject"/>
            </div>
            <div>
              <label class="text-white/35 text-xs tracking-widest uppercase block mb-2">Message</label>
              <textarea name="body" rows="4" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-gold-500 resize-none" placeholder="Write your reply to the guest..."></textarea>
            </div>
            <button type="submit" class="bg-gradient-to-r from-gold-600 to-gold-500 hover:from-gold-500 hover:to-gold-400 text-white px-6 py-3 rounded-xl text-sm tracking-widest transition-all">SEND MESSAGE</button>
          </form>
        <?php else: ?>
          <div class="flex-1 flex items-center justify-center text-center text-white/25 px-6">
            <div>
              <i data-lucide="messages-square" class="w-12 h-12 mx-auto mb-3"></i>
              <p>No conversation selected.</p>
              <?php if ($canOpenGuests): ?>
              <a href="<?= APP_URL ?>/admin/guests.php" class="inline-flex items-center gap-2 mt-4 text-gold-400 hover:text-gold-300 text-xs tracking-widest">
                <i data-lucide="users" class="w-4 h-4"></i> OPEN GUESTS
              </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</main>
<script>lucide.createIcons();</script>
</body>
</html>
