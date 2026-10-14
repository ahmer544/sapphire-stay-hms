<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireLogin();

$user = authUser();
if (in_array($user['role'], ['admin', 'staff'], true)) {
    redirectUrl(adminUrl('messages'));
}

ensureMessagingSchema();
$db = getDB();

$success = getFlash('message_success');
$error = getFlash('message_error');

$contactsStmt = $db->prepare(
    "SELECT u.id, u.name, u.email, u.role,
        (
            SELECT body FROM messages m
            WHERE ((m.sender_id=u.id AND m.recipient_id=?) OR (m.sender_id=? AND m.recipient_id=u.id))
            ORDER BY m.created_at DESC, m.id DESC
            LIMIT 1
        ) AS last_body,
        (
            SELECT created_at FROM messages m
            WHERE ((m.sender_id=u.id AND m.recipient_id=?) OR (m.sender_id=? AND m.recipient_id=u.id))
            ORDER BY m.created_at DESC, m.id DESC
            LIMIT 1
        ) AS last_at,
        (
            SELECT COUNT(*) FROM messages m
            WHERE m.sender_id=u.id AND m.recipient_id=? AND m.is_read=0
        ) AS unread_count
     FROM users u
     WHERE u.role IN ('admin','staff') AND u.is_active=1
     ORDER BY (last_at IS NULL), last_at DESC, FIELD(u.role, 'staff', 'admin'), u.name ASC"
);
$contactsStmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$contacts = $contactsStmt->fetchAll();

$contactIds = array_map(static fn(array $contact): int => (int)$contact['id'], $contacts);
$selectedId = (int)($_GET['contact'] ?? ($contacts[0]['id'] ?? 0));
if (!in_array($selectedId, $contactIds, true)) {
    $selectedId = (int)($contacts[0]['id'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipientId = (int)($_POST['recipient_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if (!in_array($recipientId, $contactIds, true)) {
        flash('message_error', 'Please choose a valid staff member.');
    } else {
        try {
            sendDirectMessage((int)$user['id'], $recipientId, $body, $subject);
            flash('message_success', 'Message sent successfully.');
        } catch (Throwable $e) {
            flash('message_error', $e->getMessage() ?: 'Unable to send message right now.');
        }
    }

    redirectUrl(APP_URL . '/pages/messages.php?contact=' . $recipientId);
}

$selectedContact = null;
foreach ($contacts as $contact) {
    if ((int)$contact['id'] === $selectedId) {
        $selectedContact = $contact;
        break;
    }
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
  <title>Messages – Sapphire Stay</title>
  <?php include '../partials/head.php'; ?>
  <style>
    body{font-family:'Spectral', serif;background:var(--bg);color:var(--text);}
    .form-input {
      background: #fff;
      border: 1px solid var(--stone);
      color: var(--text);
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    .form-input:focus {
      border-color: var(--gold);
      box-shadow: 0 0 0 1px var(--gold);
      outline: none;
    }
  </style>
</head>
<body>
<?php include '../partials/nav.php'; ?>
<div class="min-h-screen pt-28 pb-16 px-6">
  <div class="max-w-7xl mx-auto">
    <div class="page-hero-panel mb-10">
      <p class="text-[var(--gold)] tracking-[0.4em] text-xs uppercase mb-2">Support</p>
      <h1 class="font-display text-5xl text-[var(--lapis)]">Hotel <span class="text-[var(--gold)]">Messages</span></h1>
      <p class="text-[var(--text-muted)] mt-3 max-w-2xl">Chat directly with Sapphire Stay staff for booking help, special requests, and stay assistance.</p>
    </div>

    <?php if ($success): ?>
      <div class="mb-5 bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl p-4"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="mb-5 bg-red-50 border border-red-200 text-red-800 text-sm rounded-xl p-4"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <aside class="lg:col-span-4 bg-white border border-[var(--stone)] rounded-2xl overflow-hidden shadow-sm">
        <div class="px-5 py-4 border-b border-[var(--stone)]">
          <h2 class="font-display text-2xl text-[var(--lapis)]">Staff Contacts</h2>
        </div>
        <div class="divide-y divide-[var(--stone)]/30">
          <?php foreach ($contacts as $contact): ?>
            <a href="?contact=<?= $contact['id'] ?>" class="block px-5 py-4 hover:bg-[var(--bg-alt)]/30 transition <?= (int)$contact['id'] === $selectedId ? 'bg-[var(--bg-alt)]/65' : '' ?>">
              <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-[var(--lapis)] flex items-center justify-center text-white font-display"><?= strtoupper(substr($contact['name'],0,1)) ?></div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center justify-between gap-2">
                    <p class="text-[var(--text)] font-semibold truncate"><?= e($contact['name']) ?></p>
                    <?php if ((int)$contact['unread_count'] > 0): ?><span class="min-w-5 h-5 px-1.5 rounded-full bg-[var(--gold)] text-white text-[10px] font-semibold flex items-center justify-center leading-none"><?= (int)$contact['unread_count'] ?></span><?php endif; ?>
                  </div>
                  <p class="text-[var(--text-faint)] text-xs uppercase tracking-widest mt-0.5"><?= e($contact['role']) ?></p>
                  <p class="text-[var(--text-muted)] text-sm truncate mt-2"><?= e((string)($contact['last_body'] ?: 'Start a conversation with hotel staff')) ?></p>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
          <?php if (!$contacts): ?>
            <div class="px-5 py-10 text-center text-[var(--text-faint)]">No staff accounts are available.</div>
          <?php endif; ?>
        </div>
      </aside>

      <section class="lg:col-span-8 bg-white border border-[var(--stone)] rounded-2xl overflow-hidden flex flex-col min-h-[620px] shadow-sm">
        <?php if ($selectedContact): ?>
          <div class="px-6 py-4 border-b border-[var(--stone)] flex items-center gap-3">
            <div class="w-11 h-11 rounded-full bg-[var(--lapis)] flex items-center justify-center text-white font-display text-lg"><?= strtoupper(substr($selectedContact['name'],0,1)) ?></div>
            <div>
              <h2 class="text-[var(--text)] font-semibold"><?= e($selectedContact['name']) ?></h2>
              <p class="text-[var(--text-faint)] text-xs uppercase tracking-widest"><?= e($selectedContact['role']) ?></p>
            </div>
          </div>

          <div class="flex-1 px-6 py-5 space-y-4 overflow-y-auto min-h-[320px]">
            <?php foreach ($messages as $message): ?>
              <?php $isMine = (int)$message['sender_id'] === (int)$user['id']; ?>
              <div class="flex <?= $isMine ? 'justify-end' : 'justify-start' ?>">
                <div class="max-w-xl rounded-2xl px-4 py-3 <?= $isMine ? 'bg-[var(--gold)]/10 border border-[var(--gold)]/20 text-[var(--text)]' : 'bg-[var(--bg-alt)]/50 border border-[var(--stone)]/40 text-[var(--text)]' ?>">
                  <p class="text-[10px] uppercase tracking-widest <?= $isMine ? 'text-[var(--gold)] font-semibold' : 'text-[var(--text-faint)]' ?> mb-2"><?= e($message['sender_name']) ?> • <?= date('d M Y H:i', strtotime($message['created_at'])) ?></p>
                  <?php if (!empty($message['subject'])): ?><p class="text-[var(--text)] font-semibold mb-1"><?= e($message['subject']) ?></p><?php endif; ?>
                  <p class="text-[var(--text-muted)] text-sm leading-relaxed whitespace-pre-line"><?= e($message['body']) ?></p>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (!$messages): ?>
              <div class="h-full flex items-center justify-center text-center text-[var(--text-faint)]">
                <div>
                  <i data-lucide="messages-square" class="w-12 h-12 mx-auto mb-3 opacity-45 text-[var(--text-faint)]"></i>
                  <p>No messages yet. Send your first message below.</p>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <form method="POST" class="border-t border-[var(--stone)] p-6 space-y-4">
            <input type="hidden" name="recipient_id" value="<?= $selectedContact['id'] ?>"/>
            <div>
              <label class="text-[var(--text-muted)] text-xs tracking-widest uppercase block mb-2">Subject</label>
              <input type="text" name="subject" id="subject" maxlength="180" class="w-full form-input rounded-xl px-4 py-3 text-sm" placeholder="Optional subject"/>
            </div>
            <div>
              <label class="text-[var(--text-muted)] text-xs tracking-widest uppercase block mb-2">Message</label>
              <textarea name="body" id="body" rows="4" required class="w-full form-input rounded-xl px-4 py-3 text-sm resize-none" placeholder="Write your message to staff..."></textarea>
            </div>
            <button type="submit" class="btn-primary px-6 py-3 rounded-xl text-sm tracking-widest transition-all">SEND MESSAGE</button>
          </form>
        <?php else: ?>
          <div class="flex-1 flex items-center justify-center text-[var(--text-faint)]">No staff members available.</div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</div>
<?php include '../partials/footer.php'; ?>
<script>lucide.createIcons();</script>
</body>
</html>
