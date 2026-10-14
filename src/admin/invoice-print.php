<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireRole('admin','staff');
$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);

$staffSelect = invoicesSupportGeneratedBy()
  ? 'gu.name AS staff_name'
  : 'NULL AS staff_name';

$staffJoin = invoicesSupportGeneratedBy()
  ? 'LEFT JOIN users gu ON gu.id=i.generated_by'
  : '';

$inv = $db->prepare("SELECT i.*, b.*, b.id AS bid, u.name AS guest_name, u.email, u.phone,
  r.name AS room_name, r.room_number, r.bed_type,
  $staffSelect
    FROM invoices i
    JOIN bookings b ON b.id=i.booking_id
    JOIN users u ON u.id=b.user_id
    JOIN rooms r ON r.id=b.room_id
  $staffJoin
    WHERE i.id=?");
$inv->execute([$id]);
$inv = $inv->fetch();
if (!$inv) die('Invoice not found');

$hotelAddress = hotelAddress();
$hotelPhone = hotelPhone();
$hotelEmail = hotelEmail();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Invoice <?= e($inv['invoice_number']) ?> – Sapphire Stay</title>
  <?php include '../partials/head.php'; ?>
  <style>
    @media print { .no-print {display:none!important;} }
    body { background:#fff; color:#1a1814; } 
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'Jost', sans-serif; background: #fff; color: #1a1a1a; padding: 0; }
    .invoice { max-width: 800px; margin: 0 auto; padding: 48px 48px; }
    .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 48px; border-bottom: 2px solid #09090f; padding-bottom: 24px; }
    .logo-name { font-family: 'Cormorant Garamond', serif; font-size: 32px; font-weight: 300; color: #09090f; }
    .logo-sub { font-size: 10px; letter-spacing: .3em; color: #888; margin-top:2px; }
    .hotel-address { font-size:11px; color:#666; text-align:right; line-height:1.6; }
    .invoice-meta { display:flex; justify-content:space-between; margin-bottom:36px; }
    .meta-block h4 { font-size:10px; letter-spacing:.2em; color:#888; text-transform:uppercase; margin-bottom:6px; }
    .meta-block p { font-size:14px; color:#1a1a1a; line-height:1.6; }
    .invoice-title { font-size:12px; letter-spacing:.25em; color:#888; text-transform:uppercase; margin-bottom:6px; }
    .invoice-num { font-size:28px; font-family:'Cormorant Garamond',serif; color:#09090f; }
    table { width:100%; border-collapse:collapse; margin-bottom:32px; }
    thead th { background:#09090f; color:#fff; padding:10px 14px; text-align:left; font-size:11px; font-weight:400; letter-spacing:.1em; }
    tbody td { padding:12px 14px; border-bottom:1px solid #eee; font-size:13px; }
    tbody tr:last-child td { border-bottom:none; }
    .total-section { display:flex; justify-content:flex-end; }
    .total-box { width:260px; }
    .total-row { display:flex; justify-content:space-between; padding:6px 0; font-size:13px; color:#444; }
    .total-row.grand { border-top:2px solid #09090f; margin-top:6px; padding-top:10px; font-size:16px; font-weight:600; color:#09090f; }
    .gold-bar { height:4px; background:linear-gradient(to right,#d4a843,#fde68a); border-radius:2px; margin-bottom:32px; }
    .footer { margin-top:48px; border-top:1px solid #eee; padding-top:16px; font-size:11px; color:#888; text-align:center; line-height:1.8; }
    .print-btn { position:fixed; top:20px; right:20px; background:#09090f; color:#fbbf24; border:none; padding:10px 22px; font-family:'Jost',sans-serif; font-size:12px; letter-spacing:.15em; cursor:pointer; border-radius:8px; }
    @media print { .print-btn { display:none; } body { padding:0; } }
  </style>
</head>
<body>
<button class="print-btn" onclick="window.print()">PRINT / SAVE PDF</button>

<div class="invoice">
  <div class="gold-bar"></div>

  <div class="header">
    <div>
      <div class="logo-name">Sapphire Stay</div>
      <div class="logo-sub">MUZAFFARABAD, PAKISTAN</div>
    </div>
    <div class="hotel-address">
      <?= nl2br(e($hotelAddress)) ?><br/>
      <?= e($hotelPhone) ?><br/>
      <?= e($hotelEmail) ?>
    </div>
  </div>

  <div class="invoice-meta">
    <div class="meta-block">
      <h4>Invoice To</h4>
      <p><strong><?= e($inv['guest_name']) ?></strong><br/>
      <?= e($inv['email']) ?><br/>
      <?php if ($inv['phone']): ?><?= e($inv['phone']) ?><?php endif; ?>
      </p>
    </div>
    <div class="meta-block" style="text-align:right">
      <p class="invoice-title">Invoice</p>
      <p class="invoice-num"><?= e($inv['invoice_number']) ?></p>
      <br/>
      <h4>Issued</h4>
      <p><?= date('d F Y', strtotime($inv['issued_at'])) ?></p>
    </div>
  </div>

  <!-- Stay Details Table -->
  <table>
    <thead>
      <tr>
        <th>Description</th>
        <th>Details</th>
        <th style="text-align:right">Amount (PKR)</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Room Accommodation</td>
        <td>
          <?= e($inv['room_name']) ?> (Room <?= e($inv['room_number']) ?>)<br/>
          <small style="color:#888;"><?= $inv['bed_type'] ?> bed &bull; Check-in: <?= date('d M Y', strtotime($inv['check_in'])) ?> &bull; Check-out: <?= date('d M Y', strtotime($inv['check_out'])) ?></small>
        </td>
        <td style="text-align:right"><?= currencyRaw($inv['subtotal']) ?></td>
      </tr>
      <tr>
        <td>Guests</td>
        <td><?= $inv['adults'] ?> Adults, <?= $inv['children'] ?> Children</td>
        <td style="text-align:right">—</td>
      </tr>
      <tr>
        <td>Duration</td>
        <td><?= $inv['nights'] ?> Night(s)</td>
        <td style="text-align:right">—</td>
      </tr>
      <?php if ($inv['special_requests']): ?>
      <tr>
        <td>Special Requests</td>
        <td><?= e($inv['special_requests']) ?></td>
        <td style="text-align:right">—</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- Totals -->
  <div class="total-section">
    <div class="total-box">
      <div class="total-row"><span>Booking Amount</span><span>₨ <?= currencyRaw($inv['total']) ?></span></div>
      <div class="total-row grand"><span>TOTAL</span><span>₨ <?= currencyRaw($inv['total']) ?></span></div>
    </div>
  </div>

  <!-- Notes -->
  <?php if ($inv['notes']): ?>
  <div style="background:#f9f9f9;padding:16px;border-radius:8px;margin-top:24px;font-size:13px;color:#555;">
    <strong>Notes:</strong> <?= e($inv['notes']) ?>
  </div>
  <?php endif; ?>

  <div class="footer">
    Booking Reference: <strong><?= e($inv['booking_ref']) ?></strong> &bull;
    Generated by: <?= e($inv['staff_name'] ?? 'System') ?> &bull;
    Generated on: <?= date('d M Y H:i', strtotime($inv['issued_at'])) ?><br/><br/>
    Thank you for choosing Sapphire Stay, Muzaffarabad.<br/>
    For queries: <?= e($hotelEmail) ?> &bull; <?= e($hotelPhone) ?>
  </div>
</div>
</body>
</html>
