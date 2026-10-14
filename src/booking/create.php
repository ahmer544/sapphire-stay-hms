<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireCustomer();
if($_SERVER['REQUEST_METHOD']!=='POST') redirect('pages/rooms.php');

$db      = getDB();
refreshBookingLifecycle($db);
$user    = authUser();
$roomId  = (int)($_POST['room_id']  ?? 0);
$checkIn = trim($_POST['check_in']  ?? '');
$checkOut= trim($_POST['check_out'] ?? '');
$guests  = max(1,(int)($_POST['guests']  ?? $_POST['adults'] ?? 1));
$adults  = $guests;
$children= 0;
$special = trim($_POST['special_requests'] ?? '');

// Validate
if(!$roomId||!$checkIn||!$checkOut) { flash('error','All fields required.'); redirect("pages/room-detail.php?id=$roomId"); }
$ci = strtotime($checkIn);
$co = strtotime($checkOut);
if($co <= $ci) { flash('error','Check-out must be after check-in.'); redirect("pages/room-detail.php?id=$roomId"); }
$nights = max(1, round(($co-$ci)/86400));

// Fetch room. Maintenance is the only hard room-level block; date availability is checked below.
$room = $db->prepare("SELECT * FROM rooms WHERE id=? AND status<>'maintenance'");
$room->execute([$roomId]);
$room = $room->fetch();
if(!$room) { flash('error','Room not available.'); redirect("pages/rooms.php"); }

// Check existing bookings
if(roomHasBlockingBooking($db, $roomId, $checkIn, $checkOut)) {
    flash('error','Room is not available for those dates.');
    redirect("pages/room-detail.php?id=$roomId&check_in=" . urlencode($checkIn) . "&check_out=" . urlencode($checkOut) . "&guests=$guests");
}

// Calc totals (tax-free booking)
$subtotal = round($room['price_per_night'] * $nights, 0);
$tax      = 0;
$total    = $subtotal;

$ref      = generateBookingRef();
$initialStatus = stripeChargeAmount((float)$total) >= stripeMinimumPkrAmount() ? 'pending' : 'payment_failed';

// Insert booking
$stmt = $db->prepare("INSERT INTO bookings (booking_ref,user_id,room_id,check_in,check_out,nights,adults,children,subtotal,tax_amount,total_amount,special_requests,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
$stmt->execute([$ref,$user['id'],$roomId,$checkIn,$checkOut,$nights,$adults,$children,$subtotal,$tax,$total,$special,$initialStatus]);
$bookingId = $db->lastInsertId();

// Generate invoice
$inv = generateInvoiceNumber();
$db->prepare("INSERT INTO invoices (booking_id,invoice_number,total) VALUES (?,?,?)")->execute([$bookingId,$inv,$total]);

// Redirect to payment
if ($initialStatus === 'payment_failed') {
    flash('error', 'Stripe cannot process this booking amount because it is below the card minimum. The room was not held.');
    redirect("pages/bookings.php");
}
redirect("payment/checkout.php?booking_id=$bookingId");
