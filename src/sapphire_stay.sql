-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 21, 2026 at 07:08 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Windows/XAMPP/phpMyAdmin friendly import setup.
-- This makes the dump self-contained and safe to re-import.
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS=0;

CREATE DATABASE IF NOT EXISTS `sapphire_stay`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `sapphire_stay`;

DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `booking_extensions`;
DROP TABLE IF EXISTS `invoices`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `room_images`;
DROP TABLE IF EXISTS `room_amenities`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `rooms`;
DROP TABLE IF EXISTS `room_categories`;
DROP TABLE IF EXISTS `amenities`;
DROP TABLE IF EXISTS `contact_messages`;
DROP TABLE IF EXISTS `gallery`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `users`;

--
-- Database: `sapphire_stay`
--

-- --------------------------------------------------------

--
-- Table structure for table `amenities`
--

CREATE TABLE `amenities` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `icon` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `amenities`
--

INSERT INTO `amenities` (`id`, `name`, `icon`) VALUES
(1, 'Free Wi-Fi', 'wifi'),
(2, 'Air Conditioning', 'thermometer'),
(3, 'Flat-Screen TV', 'monitor'),
(4, 'Mini Bar', 'wine'),
(5, 'Room Service', 'bell'),
(6, 'Safe Deposit Box', 'lock'),
(7, 'Balcony', 'sun'),
(8, 'Ocean View', 'eye'),
(9, 'Jacuzzi', 'droplet'),
(10, 'Kitchenette', 'coffee');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_ref` varchar(20) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `room_id` int(10) UNSIGNED NOT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `nights` tinyint(3) UNSIGNED NOT NULL,
  `adults` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `children` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `special_requests` text DEFAULT NULL,
  `status` enum('pending','confirmed','checked_in','checked_out','cancelled','expired','payment_failed') NOT NULL DEFAULT 'pending',
  `checked_in_at` datetime DEFAULT NULL,
  `checked_out_at` datetime DEFAULT NULL,
  `handled_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_ref`, `user_id`, `room_id`, `check_in`, `check_out`, `nights`, `adults`, `children`, `subtotal`, `tax_amount`, `total_amount`, `special_requests`, `status`, `checked_in_at`, `checked_out_at`, `handled_by`, `created_at`, `updated_at`) VALUES
(1, 'SAP-20260001', 32, 3, '2026-04-19', '2026-04-30', 11, 1, 0, 2145.00, 278.85, 2423.85, '', 'cancelled', NULL, NULL, NULL, '2026-04-19 20:06:14', '2026-04-19 20:16:40'),
(2, 'SAP-20260002', 32, 1, '2026-04-20', '2026-04-29', 9, 1, 0, 108000.00, 0.00, 108000.00, '', 'confirmed', NULL, NULL, NULL, '2026-04-19 20:15:45', '2026-04-19 20:16:23');

-- --------------------------------------------------------

--
-- Table structure for table `booking_extensions`
--

CREATE TABLE `booking_extensions` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `old_check_out` date NOT NULL,
  `new_check_out` date NOT NULL,
  `extra_nights` smallint(5) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'PKR',
  `status` enum('pending','confirmed','cancelled','expired','payment_failed') NOT NULL DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gallery`
--

CREATE TABLE `gallery` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `category` varchar(60) DEFAULT 'general',
  `sort_order` smallint(6) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gallery`
--

INSERT INTO `gallery` (`id`, `title`, `image_path`, `category`, `sort_order`, `created_at`) VALUES
(1, 'Hotel Exterior', 'imgs/banner.jpeg', 'exterior', 1, '2026-04-17 18:50:00'),
(2, 'Hotel Building', 'imgs/1000199454.jpg', 'exterior', 2, '2026-04-17 18:50:00'),
(3, 'Luxury Suite', 'imgs/Gemini_Generated_Image_qinyneqinyneqiny.png', 'rooms', 3, '2026-04-17 18:50:00'),
(4, 'Deluxe King Room', 'imgs/room2.jpeg', 'rooms', 4, '2026-04-17 18:50:00'),
(5, 'Executive Suite', 'imgs/room6.jpeg', 'rooms', 5, '2026-04-17 18:50:00'),
(6, 'Junior Suite', 'imgs/room5.jpeg', 'rooms', 6, '2026-04-17 18:50:00'),
(7, 'Standard Room', 'imgs/room.jpeg', 'rooms', 7, '2026-04-17 18:50:00'),
(8, 'Standard Twin Room', 'imgs/room1.jpeg', 'rooms', 8, '2026-04-17 18:50:00'),
(9, 'Deluxe Double', 'imgs/room3.jpeg', 'rooms', 9, '2026-04-17 18:50:00'),
(10, 'Private Balcony', 'imgs/balcony.jpeg', 'amenities', 10, '2026-04-17 18:50:00'),
(11, 'Luxury Bathroom', 'imgs/bathroom.jpeg', 'amenities', 11, '2026-04-17 18:50:00'),
(12, 'Hotel Turf & Grounds', 'imgs/turf.jpeg', 'exterior', 12, '2026-04-17 18:50:00');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `invoice_number` varchar(30) NOT NULL,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `due_date` date DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `generated_by` int(10) UNSIGNED DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `booking_id`, `invoice_number`, `issued_at`, `due_date`, `total`, `notes`, `generated_by`, `pdf_path`) VALUES
(1, 1, 'INV-20260419-001', '2026-04-19 20:06:14', NULL, 2423.85, NULL, NULL, NULL),
(2, 2, 'INV-20260419-002', '2026-04-19 20:15:45', NULL, 108000.00, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(10) UNSIGNED NOT NULL,
  `recipient_id` int(10) UNSIGNED NOT NULL,
  `subject` varchar(180) DEFAULT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `recipient_id`, `subject`, `body`, `is_read`, `read_at`, `created_at`, `updated_at`) VALUES
(1, 32, 2, 'please', 'hi', 1, '2026-04-18 00:45:40', '2026-04-18 00:44:00', '2026-04-18 00:45:40');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `extension_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `method` enum('stripe') NOT NULL DEFAULT 'stripe',
  `status` enum('pending','processing','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `stripe_payment_intent_id` varchar(120) DEFAULT NULL,
  `stripe_charge_id` varchar(120) DEFAULT NULL,
  `stripe_customer_id` varchar(120) DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `extension_id`, `user_id`, `amount`, `currency`, `method`, `status`, `stripe_payment_intent_id`, `stripe_charge_id`, `stripe_customer_id`, `reviewed_by`, `reviewed_at`, `review_notes`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 2, NULL, 32, 108000.00, 'PKR', 'stripe', 'completed', 'pi_3TNx57QzZGsxXyQH0hwtP8si', NULL, NULL, NULL, NULL, NULL, '2026-04-19 20:16:23', '2026-04-19 20:16:21', '2026-04-19 20:16:23');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `room_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `room_id`, `user_id`, `rating`, `title`, `body`, `is_approved`, `created_at`) VALUES
(1, 3, 5, 4, 'Great Value for PKR 12,000', 'PKR 12,000 per night mein jo mila woh barhiya tha. Bed bohot comfortable tha, AC kaam karta raha raat bhar. Ek baar gaye toh socha 4 star banega — it actually felt like 5. Staff responsive aur polite.', 1, '2026-02-25 20:10:00'),
(2, 5, 6, 5, 'Suite Life at Its Best!', 'Junior Suite ne humari family trip mein chand laganay ka kaam kiya. Living area alag tha toh baray aur bachay dono comfortable rehe. Bathroom bhi bohot saaf tha. Neelum Valley ke raastay par yeh jagah ek nayab tohfa hai.', 1, '2026-03-05 16:35:00'),
(3, 5, 7, 5, 'Shukria Sapphire Stay!', 'Mujhe nahi pata tha Muzaffarabad mein aisa koi hotel bhi exist karta hai. Suite fully furnished thi, mini bar mein drinks thy, room service bhi fast tha. Ek raat aur ruk jaata agar budget allow karta.', 1, '2026-03-22 11:10:00'),
(4, 5, 8, 4, 'Cozy and Premium Feel', 'Comfortable aur well-maintained suite. Balcony pe chai peena morning highlight tha. Checkout time thora strict tha — 12 baje sharp. But overall experience 10/10. Will visit again next Eid.', 1, '2026-04-01 08:55:00'),
(5, 6, 9, 5, 'Top Notch Luxury in AJK', 'AJK mein Sapphire Stay jaise hotel ka hona fakhre ki baat hai. Executive Suite mein sab kuch premium tha — linens, furniture, toiletries. View toh waise bhi lajawab hai lekin yahan se dekho toh dil khush ho jata hai.', 1, '2026-02-14 19:22:00'),
(6, 6, 10, 5, 'Valentine\'s Getaway — Perfect!', 'Hum ne Valentine\'s Day pe book kiya tha. Hotel walo ne room mein petals aur candles arrange kiye bina kehe. Yeh extra touch bohot dil ko choo geya. Khana bhi ghar jaisa tha. ₨ 24,000 fair price for such luxury.', 1, '2026-02-15 22:00:00'),
(7, 6, 11, 4, 'Business Trip Comfort', 'Work ke silsile mein Muzaffarabad aaya tha. Executive Suite ne ghar ki kami mehsoos nahi hone di. Fast Wi-Fi, quiet environment, good desk space. Breakfast aur dinner dono ka option mil jata toh aur acha hota.', 1, '2026-03-30 07:15:00'),
(8, 7, 12, 5, 'Pakistan Ka Best Hotel Experience', 'Penthouse Suite ne Lahore aur Islamabad ke top hotels ko bhi peeche chhod diya! Rooftop terrace pe raat ka nazar — mountains, stars, aur khula aasman — yeh experience words mein bayaan nahi ho sakta. Staff butler service mein koi kami nahi chhodi.', 1, '2026-03-15 23:30:00'),
(9, 1, 6, 4, 'Clean and Comfortable', 'Budget friendly option Muzaffarabad mein. Standard Room chhota nahi tha — expected se bada mila. Sab kuch saaf tha. For PKR 8,000 per night this is a great deal compared to other options in the city.', 1, '2026-01-20 10:00:00'),
(10, 1, 7, 4, 'Achi Service, Achi Rate', 'Staff bohat helpful tha. Room service raat ko bhi available tha. Standard room mein sab zaruri cheezein thi. Agla baar suite try karoonga. Sapphire Stay ne ek loyal customer bana liya.', 1, '2026-02-08 12:30:00'),
(14, 3, 5, 4, 'Great Value for PKR 12,000', 'PKR 12,000 per night mein jo mila woh barhiya tha. Bed bohot comfortable tha, AC kaam karta raha raat bhar. Ek baar gaye toh socha 4 star banega — it actually felt like 5. Staff responsive aur polite.', 1, '2026-02-25 20:10:00'),
(15, 5, 6, 5, 'Suite Life at Its Best!', 'Junior Suite ne humari family trip mein chand laganay ka kaam kiya. Living area alag tha toh baray aur bachay dono comfortable rehe. Bathroom bhi bohot saaf tha. Neelum Valley ke raastay par yeh jagah ek nayab tohfa hai.', 1, '2026-03-05 16:35:00'),
(16, 5, 7, 5, 'Shukria Sapphire Stay!', 'Mujhe nahi pata tha Muzaffarabad mein aisa koi hotel bhi exist karta hai. Suite fully furnished thi, mini bar mein drinks thy, room service bhi fast tha. Ek raat aur ruk jaata agar budget allow karta.', 1, '2026-03-22 11:10:00'),
(17, 5, 8, 4, 'Cozy and Premium Feel', 'Comfortable aur well-maintained suite. Balcony pe chai peena morning highlight tha. Checkout time thora strict tha — 12 baje sharp. But overall experience 10/10. Will visit again next Eid.', 1, '2026-04-01 08:55:00'),
(18, 6, 9, 5, 'Top Notch Luxury in AJK', 'AJK mein Sapphire Stay jaise hotel ka hona fakhre ki baat hai. Executive Suite mein sab kuch premium tha — linens, furniture, toiletries. View toh waise bhi lajawab hai lekin yahan se dekho toh dil khush ho jata hai.', 1, '2026-02-14 19:22:00'),
(19, 6, 10, 5, 'Valentine\'s Getaway — Perfect!', 'Hum ne Valentine\'s Day pe book kiya tha. Hotel walo ne room mein petals aur candles arrange kiye bina kehe. Yeh extra touch bohot dil ko choo geya. Khana bhi ghar jaisa tha. ₨ 24,000 fair price for such luxury.', 1, '2026-02-15 22:00:00'),
(20, 6, 11, 4, 'Business Trip Comfort', 'Work ke silsile mein Muzaffarabad aaya tha. Executive Suite ne ghar ki kami mehsoos nahi hone di. Fast Wi-Fi, quiet environment, good desk space. Breakfast aur dinner dono ka option mil jata toh aur acha hota.', 1, '2026-03-30 07:15:00'),
(21, 7, 12, 5, 'Pakistan Ka Best Hotel Experience', 'Penthouse Suite ne Lahore aur Islamabad ke top hotels ko bhi peeche chhod diya! Rooftop terrace pe raat ka nazar — mountains, stars, aur khula aasman — yeh experience words mein bayaan nahi ho sakta. Staff butler service mein koi kami nahi chhodi.', 1, '2026-03-15 23:30:00'),
(22, 1, 6, 4, 'Clean and Comfortable', 'Budget friendly option Muzaffarabad mein. Standard Room chhota nahi tha — expected se bada mila. Sab kuch saaf tha. For PKR 8,000 per night this is a great deal compared to other options in the city.', 1, '2026-01-20 10:00:00'),
(23, 1, 7, 4, 'Achi Service, Achi Rate', 'Staff bohat helpful tha. Room service raat ko bhi available tha. Standard room mein sab zaruri cheezein thi. Agla baar suite try karoonga. Sapphire Stay ne ek loyal customer bana liya.', 1, '2026-02-08 12:30:00'),
(27, 3, 5, 4, 'Great Value for PKR 12,000', 'PKR 12,000 per night mein jo mila woh barhiya tha. Bed bohot comfortable tha, AC kaam karta raha raat bhar. Ek baar gaye toh socha 4 star banega — it actually felt like 5. Staff responsive aur polite.', 1, '2026-02-25 20:10:00'),
(28, 5, 6, 5, 'Suite Life at Its Best!', 'Junior Suite ne humari family trip mein chand laganay ka kaam kiya. Living area alag tha toh baray aur bachay dono comfortable rehe. Bathroom bhi bohot saaf tha. Neelum Valley ke raastay par yeh jagah ek nayab tohfa hai.', 1, '2026-03-05 16:35:00'),
(29, 5, 7, 5, 'Shukria Sapphire Stay!', 'Mujhe nahi pata tha Muzaffarabad mein aisa koi hotel bhi exist karta hai. Suite fully furnished thi, mini bar mein drinks thy, room service bhi fast tha. Ek raat aur ruk jaata agar budget allow karta.', 1, '2026-03-22 11:10:00'),
(30, 5, 8, 4, 'Cozy and Premium Feel', 'Comfortable aur well-maintained suite. Balcony pe chai peena morning highlight tha. Checkout time thora strict tha — 12 baje sharp. But overall experience 10/10. Will visit again next Eid.', 1, '2026-04-01 08:55:00'),
(31, 6, 9, 5, 'Top Notch Luxury in AJK', 'AJK mein Sapphire Stay jaise hotel ka hona fakhre ki baat hai. Executive Suite mein sab kuch premium tha — linens, furniture, toiletries. View toh waise bhi lajawab hai lekin yahan se dekho toh dil khush ho jata hai.', 1, '2026-02-14 19:22:00'),
(32, 6, 10, 5, 'Valentine\'s Getaway — Perfect!', 'Hum ne Valentine\'s Day pe book kiya tha. Hotel walo ne room mein petals aur candles arrange kiye bina kehe. Yeh extra touch bohot dil ko choo geya. Khana bhi ghar jaisa tha. ₨ 24,000 fair price for such luxury.', 1, '2026-02-15 22:00:00'),
(33, 6, 11, 4, 'Business Trip Comfort', 'Work ke silsile mein Muzaffarabad aaya tha. Executive Suite ne ghar ki kami mehsoos nahi hone di. Fast Wi-Fi, quiet environment, good desk space. Breakfast aur dinner dono ka option mil jata toh aur acha hota.', 1, '2026-03-30 07:15:00'),
(34, 7, 12, 5, 'Pakistan Ka Best Hotel Experience', 'Penthouse Suite ne Lahore aur Islamabad ke top hotels ko bhi peeche chhod diya! Rooftop terrace pe raat ka nazar — mountains, stars, aur khula aasman — yeh experience words mein bayaan nahi ho sakta. Staff butler service mein koi kami nahi chhodi.', 1, '2026-03-15 23:30:00'),
(35, 1, 6, 4, 'Clean and Comfortable', 'Budget friendly option Muzaffarabad mein. Standard Room chhota nahi tha — expected se bada mila. Sab kuch saaf tha. For PKR 8,000 per night this is a great deal compared to other options in the city.', 1, '2026-01-20 10:00:00'),
(36, 1, 7, 4, 'Achi Service, Achi Rate', 'Staff bohat helpful tha. Room service raat ko bhi available tha. Standard room mein sab zaruri cheezein thi. Agla baar suite try karoonga. Sapphire Stay ne ek loyal customer bana liya.', 1, '2026-02-08 12:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `room_number` varchar(20) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `price_per_night` decimal(10,2) NOT NULL,
  `capacity` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `floor` tinyint(3) UNSIGNED DEFAULT NULL,
  `size_sqft` smallint(5) UNSIGNED DEFAULT NULL,
  `bed_type` enum('Single','Double','Queen','King','Twin') NOT NULL DEFAULT 'Double',
  `status` enum('available','occupied','maintenance','reserved') NOT NULL DEFAULT 'available',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `thumbnail` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `category_id`, `room_number`, `name`, `description`, `price_per_night`, `capacity`, `floor`, `size_sqft`, `bed_type`, `status`, `is_featured`, `thumbnail`, `created_at`, `updated_at`) VALUES
(1, 1, '101', 'Standard Room', 'Cosy room with all essential amenities.', 12000.00, 2, 1, 280, 'Double', 'available', 1, 'imgs/room.jpeg', '2026-04-17 18:04:48', '2026-04-19 20:15:01'),
(2, 1, '102', 'Standard Twin', 'Ideal for two guests travelling separately.', 110.00, 2, 1, 280, 'Twin', 'available', 0, 'imgs/room1.jpeg', '2026-04-17 18:04:48', '2026-04-17 18:04:48'),
(3, 2, '201', 'Deluxe King', 'Spacious deluxe room with king bed and city view.', 195.00, 2, 2, 380, 'King', 'available', 1, 'imgs/room2.jpeg', '2026-04-17 18:04:48', '2026-04-17 18:04:48'),
(4, 2, '202', 'Deluxe Double', 'Premium room with sleek contemporary décor.', 175.00, 3, 2, 360, 'Double', 'available', 0, 'imgs/room3.jpeg', '2026-04-17 18:04:48', '2026-04-17 18:04:48'),
(5, 3, '301', 'Junior Suite', 'Separate living area with a plush king bed.', 290.00, 2, 3, 520, 'King', 'available', 1, 'imgs/room5.jpeg', '2026-04-17 18:04:48', '2026-04-17 18:04:48'),
(6, 3, '302', 'Executive Suite', 'Corner suite with panoramic views and private balcony.', 380.00, 2, 3, 680, 'King', 'available', 1, 'imgs/room6.jpeg', '2026-04-17 18:04:48', '2026-04-17 18:04:48'),
(7, 4, '401', 'Penthouse Suite', 'Entire top floor with rooftop terrace and butler service.', 750.00, 4, 4, 1400, 'King', 'available', 1, 'imgs/balcony.jpeg', '2026-04-17 18:04:48', '2026-04-17 18:04:48');

-- --------------------------------------------------------

--
-- Table structure for table `room_amenities`
--

CREATE TABLE `room_amenities` (
  `room_id` int(10) UNSIGNED NOT NULL,
  `amenity_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_amenities`
--

INSERT INTO `room_amenities` (`room_id`, `amenity_id`) VALUES
(1, 1),
(1, 6),
(2, 1),
(2, 2),
(2, 3),
(2, 6),
(3, 1),
(3, 2),
(3, 3),
(3, 4),
(3, 5),
(3, 6),
(3, 8),
(4, 1),
(4, 2),
(4, 3),
(4, 4),
(4, 5),
(4, 6),
(5, 1),
(5, 2),
(5, 3),
(5, 4),
(5, 5),
(5, 6),
(5, 7),
(6, 1),
(6, 2),
(6, 3),
(6, 4),
(6, 5),
(6, 6),
(6, 7),
(6, 8),
(7, 1),
(7, 2),
(7, 3),
(7, 4),
(7, 5),
(7, 6),
(7, 7),
(7, 8),
(7, 9),
(7, 10);

-- --------------------------------------------------------

--
-- Table structure for table `room_categories`
--

CREATE TABLE `room_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_categories`
--

INSERT INTO `room_categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Standard', 'Comfortable rooms for solo travellers and couples', '2026-04-17 18:04:47'),
(2, 'Deluxe', 'Spacious rooms with premium furnishings', '2026-04-17 18:04:47'),
(3, 'Suite', 'Luxurious suites with separate living areas', '2026-04-17 18:04:47'),
(4, 'Penthouse', 'Top-floor suites with panoramic city views', '2026-04-17 18:04:47');

-- --------------------------------------------------------

--
-- Table structure for table `room_images`
--

CREATE TABLE `room_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `room_id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `caption` varchar(150) DEFAULT NULL,
  `sort_order` tinyint(3) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_images`
--

INSERT INTO `room_images` (`id`, `room_id`, `image_path`, `caption`, `sort_order`) VALUES
(1, 3, 'imgs/room2.jpeg', 'Deluxe King Room', 1),
(2, 3, 'imgs/bathroom.jpeg', 'Ensuite Bathroom', 2),
(3, 5, 'imgs/room5.jpeg', 'Junior Suite', 1),
(4, 5, 'imgs/balcony.jpeg', 'Private Balcony', 2),
(5, 6, 'imgs/room6.jpeg', 'Executive Suite', 1),
(6, 6, 'imgs/balcony.jpeg', 'Suite Balcony', 2),
(7, 7, 'imgs/balcony.jpeg', 'Rooftop Terrace', 1),
(8, 7, 'imgs/room6.jpeg', 'Master Bedroom', 2),
(9, 3, 'imgs/room2.jpeg', 'Deluxe King Room', 1),
(10, 3, 'imgs/bathroom.jpeg', 'Ensuite Bathroom', 2),
(11, 5, 'imgs/room5.jpeg', 'Junior Suite', 1),
(12, 5, 'imgs/balcony.jpeg', 'Private Balcony', 2),
(13, 6, 'imgs/room6.jpeg', 'Executive Suite', 1),
(14, 6, 'imgs/balcony.jpeg', 'Suite Balcony', 2),
(15, 7, 'imgs/balcony.jpeg', 'Rooftop Terrace', 1),
(16, 7, 'imgs/room6.jpeg', 'Master Bedroom', 2),
(17, 3, 'imgs/room2.jpeg', 'Deluxe King Room', 1),
(18, 3, 'imgs/bathroom.jpeg', 'Ensuite Bathroom', 2),
(19, 5, 'imgs/room5.jpeg', 'Junior Suite', 1),
(20, 5, 'imgs/balcony.jpeg', 'Private Balcony', 2),
(21, 6, 'imgs/room6.jpeg', 'Executive Suite', 1),
(22, 6, 'imgs/balcony.jpeg', 'Suite Balcony', 2),
(23, 7, 'imgs/balcony.jpeg', 'Rooftop Terrace', 1),
(24, 7, 'imgs/room6.jpeg', 'Master Bedroom', 2);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `last_activity` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`key`, `value`) VALUES
('check_in_time', '14:00'),
('check_out_time', '12:00'),
('currency', 'PKR'),
('currency_symbol', '₨'),
('hotel_address', 'Gojra Bypass Road, Muzaffarabad, Pakistan, 13100'),
('hotel_city', 'Muzaffarabad'),
('hotel_country', 'Pakistan'),
('hotel_email', 'sapphire.stay@gmail.com'),
('hotel_map_lat', '34.3599'),
('hotel_map_lng', '73.4720'),
('hotel_name', 'Sapphire Stay'),
('hotel_phone', '+92 317 9219995'),
('hotel_tagline', 'Muzaffarabad\'s Premier Luxury Hotel'),
('tax_rate', '13');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('customer','staff','admin') NOT NULL DEFAULT 'customer',
  `avatar` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `email_verified_at` datetime DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `avatar`, `is_active`, `email_verified_at`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'admin@sapphirestay.com', '+1-000-000-0000', '$2y$12$meF.cstDbNbsyAv9EmSPRuY8o62vNRIP6Aj/jM/6GGaqlph1ZxJIi', 'admin', NULL, 1, NULL, NULL, '2026-04-17 18:04:47', '2026-04-17 23:58:40'),
(2, 'Front Desk', 'staff@sapphirestay.com', '+1-000-000-0001', '$2y$12$uD5UWH5CdsePkyTuMXFMKe2tL8OLJ1auIlVmt28vYI5kbSnF8oQva', 'staff', NULL, 1, NULL, NULL, '2026-04-17 18:04:47', '2026-04-17 23:58:40'),
(5, 'Ahmad Raza', 'ahmad.raza@gmail.com', '+923001234567', '$2y$12$w6wtXfjusTsp6CfzUkL/R.NpH7kgUGEeJDnAa0vdrAMi1T.Nl4V86', 'customer', NULL, 1, NULL, NULL, '2026-04-17 18:50:00', '2026-04-17 18:50:00'),
(6, 'Fatima Malik', 'fatima.malik@gmail.com', '+923009876543', '$2y$12$w6wtXfjusTsp6CfzUkL/R.NpH7kgUGEeJDnAa0vdrAMi1T.Nl4V86', 'customer', NULL, 1, NULL, NULL, '2026-04-17 18:50:00', '2026-04-17 18:50:00'),
(7, 'Usman Butt', 'usman.butt@yahoo.com', '+923451122334', '$2y$12$w6wtXfjusTsp6CfzUkL/R.NpH7kgUGEeJDnAa0vdrAMi1T.Nl4V86', 'customer', NULL, 1, NULL, NULL, '2026-04-17 18:50:00', '2026-04-17 18:50:00'),
(8, 'Sana Iqbal', 'sana.iqbal@gmail.com', '+923331234000', '$2y$12$w6wtXfjusTsp6CfzUkL/R.NpH7kgUGEeJDnAa0vdrAMi1T.Nl4V86', 'customer', NULL, 1, NULL, NULL, '2026-04-17 18:50:00', '2026-04-17 18:50:00'),
(9, 'Bilal Hussain', 'bilal.hussain@gmail.com', '+923215567890', '$2y$12$w6wtXfjusTsp6CfzUkL/R.NpH7kgUGEeJDnAa0vdrAMi1T.Nl4V86', 'customer', NULL, 1, NULL, NULL, '2026-04-17 18:50:00', '2026-04-17 18:50:00'),
(10, 'Amna Sheikh', 'amna.sheikh@hotmail.com', '+923124456789', '$2y$12$w6wtXfjusTsp6CfzUkL/R.NpH7kgUGEeJDnAa0vdrAMi1T.Nl4V86', 'customer', NULL, 1, NULL, NULL, '2026-04-17 18:50:00', '2026-04-17 18:50:00'),
(11, 'Tariq Mehmood', 'tariq.mehmood@gmail.com', '+923009988776', '$2y$12$w6wtXfjusTsp6CfzUkL/R.NpH7kgUGEeJDnAa0vdrAMi1T.Nl4V86', 'customer', NULL, 1, NULL, NULL, '2026-04-17 18:50:00', '2026-04-17 18:50:00'),
(12, 'Zara Nawaz', 'zara.nawaz@gmail.com', '+923451234567', '$2y$12$w6wtXfjusTsp6CfzUkL/R.NpH7kgUGEeJDnAa0vdrAMi1T.Nl4V86', 'customer', NULL, 1, NULL, NULL, '2026-04-17 18:50:00', '2026-04-17 18:50:00'),
(13, 'Hassan Kamran', 'hassan.kamran@gmail.com', '+923361122334', '$2y$12$w6wtXfjusTsp6CfzUkL/R.NpH7kgUGEeJDnAa0vdrAMi1T.Nl4V86', 'customer', NULL, 1, NULL, NULL, '2026-04-17 18:50:00', '2026-04-17 18:50:00'),
(14, 'Nadia Farooq', 'nadia.farooq@gmail.com', '+923111234567', '$2y$12$w6wtXfjusTsp6CfzUkL/R.NpH7kgUGEeJDnAa0vdrAMi1T.Nl4V86', 'customer', NULL, 1, NULL, NULL, '2026-04-17 18:50:00', '2026-04-17 18:50:00'),
(15, 'Imran Khan', 'imran.khan.mzd@gmail.com', '+923009871234', '$2y$12$w6wtXfjusTsp6CfzUkL/R.NpH7kgUGEeJDnAa0vdrAMi1T.Nl4V86', 'customer', NULL, 1, NULL, NULL, '2026-04-17 18:50:00', '2026-04-17 18:50:00'),
(16, 'Ayesha Siddiqui', 'ayesha.siddiqui@gmail.com', '+923217654321', '$2y$12$w6wtXfjusTsp6CfzUkL/R.NpH7kgUGEeJDnAa0vdrAMi1T.Nl4V86', 'customer', NULL, 1, NULL, NULL, '2026-04-17 18:50:00', '2026-04-17 18:50:00'),
(17, 'wajid', 'wajid@gmail.com', NULL, '123wajid', 'admin', NULL, 1, NULL, NULL, '2026-04-17 23:53:32', '2026-04-17 23:53:32'),
(32, 'ali', 'ali@gmail.com', '898909090', '$2y$12$9A8QwWjx8M9LpRjy3vBSMO1vhDF.HNCuFHasn59NTG6Xygwf4z2Am', 'customer', NULL, 1, NULL, NULL, '2026-04-18 00:31:33', '2026-04-18 00:31:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `amenities`
--
ALTER TABLE `amenities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_booking_ref` (`booking_ref`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `handled_by` (`handled_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_check_in` (`check_in`),
  ADD KEY `idx_check_out` (`check_out`);

--
-- Indexes for table `booking_extensions`
--
ALTER TABLE `booking_extensions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_extension_status_created` (`status`,`created_at`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gallery`
--
ALTER TABLE `gallery`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_invoice_number` (`invoice_number`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `generated_by` (`generated_by`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipient_read` (`recipient_id`,`is_read`),
  ADD KEY `idx_sender_recipient` (`sender_id`,`recipient_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `extension_id` (`extension_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_stripe_pi` (`stripe_payment_intent_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_approved` (`is_approved`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_room_number` (`room_number`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `room_amenities`
--
ALTER TABLE `room_amenities`
  ADD PRIMARY KEY (`room_id`,`amenity_id`),
  ADD KEY `amenity_id` (`amenity_id`);

--
-- Indexes for table `room_categories`
--
ALTER TABLE `room_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `room_images`
--
ALTER TABLE `room_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_last_activity` (`last_activity`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `amenities`
--
ALTER TABLE `amenities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `booking_extensions`
--
ALTER TABLE `booking_extensions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gallery`
--
ALTER TABLE `gallery`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `room_categories`
--
ALTER TABLE `room_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `room_images`
--
ALTER TABLE `room_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`),
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `booking_extensions`
--
ALTER TABLE `booking_extensions`
  ADD CONSTRAINT `booking_extensions_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_extensions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `payments_ibfk_4` FOREIGN KEY (`extension_id`) REFERENCES `booking_extensions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `room_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `room_amenities`
--
ALTER TABLE `room_amenities`
  ADD CONSTRAINT `room_amenities_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_amenities_ibfk_2` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_images`
--
ALTER TABLE `room_images`
  ADD CONSTRAINT `room_images_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;
COMMIT;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
