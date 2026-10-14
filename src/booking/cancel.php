<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireCustomer();

$db        = getDB();
$user      = authUser();
$bookingId = (int)($_GET['id'] ?? 0);

$booking = $db->prepare("SELECT * FROM bookings WHERE id=? AND user_id=? AND status='pending'");
$booking->execute([$bookingId, $user['id']]);
if(!$booking->fetch()) { redirect('pages/bookings.php'); }

$db->prepare("UPDATE bookings SET status='cancelled' WHERE id=? AND user_id=?")->execute([$bookingId, $user['id']]);
redirect('pages/bookings.php');
