-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Dec 17, 2025 at 01:37 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u563434200_papsipaps`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','staff') DEFAULT 'staff',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(3, 'owner', 'admin@gmail.com', '$2y$10$5PXk3JfRiGb7gdYaz6llBeSEOyI0xg0EaqjRlj9wXyNxn0kSUc.4W', 'superadmin', 'active', '2025-10-10 13:07:42', '2025-10-10 13:07:42'),
(4, 'admin', 'staff@gmail.com', '$2y$10$ed6taCJPceNeX3xdSANQAu/Nq3o2KdIHo.xpD8AOvv8fkGkMFy7mi', 'staff', 'active', '2025-10-10 13:08:05', '2025-10-10 13:08:05');

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `admin_username` varchar(100) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_trail`
--

INSERT INTO `audit_trail` (`id`, `admin_id`, `admin_username`, `action_type`, `table_name`, `record_id`, `old_values`, `new_values`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(157, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-11-26 02:38:03'),
(158, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-11-26 02:42:59'),
(159, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-11-26 02:48:44'),
(160, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-11-26 02:48:44'),
(161, 3, 'owner', 'SERVICE_UPDATE', '', NULL, NULL, NULL, 'Updated service: Change Oil (₱800.00, 60 mins)', '', NULL, '2025-11-26 02:56:19'),
(162, 3, 'owner', 'SERVICE_UPDATE', '', NULL, NULL, NULL, 'Updated service: Engine Tune Up (₱2000.00, 120 mins)', '', NULL, '2025-11-26 02:56:32'),
(163, 3, 'owner', 'SERVICE_UPDATE', '', NULL, NULL, NULL, 'Updated service: Auto Painting (₱30000.00, 60 mins)', '', NULL, '2025-11-26 02:56:43'),
(164, 3, 'owner', 'SERVICE_UPDATE', '', NULL, NULL, NULL, 'Updated service: Engine Tune Up (₱2000.00, 60 mins)', '', NULL, '2025-11-26 02:56:53'),
(165, 3, 'owner', 'PAYMENT_REJECTED', '', NULL, NULL, NULL, 'Admin \'owner\' rejected payment #0.', '', NULL, '2025-11-26 02:58:54'),
(166, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-11-26 03:43:50'),
(167, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-26 03:46:59'),
(168, 3, 'owner', 'PAYMENT_REJECTED', '', NULL, NULL, NULL, 'Admin \'owner\' rejected payment #0.', '', NULL, '2025-11-26 03:48:33'),
(169, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-26 04:00:42'),
(170, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-26 04:00:45'),
(171, 3, 'owner', 'PAYMENT_REJECTED', '', NULL, NULL, NULL, 'Admin \'owner\' rejected payment #0.', '', NULL, '2025-11-26 04:02:58'),
(172, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #6.', '', NULL, '2025-11-26 04:05:44'),
(173, 3, 'owner', 'PAYMENT_REJECTED', '', NULL, NULL, NULL, 'Admin \'owner\' rejected payment #7.', '', NULL, '2025-11-26 04:09:51'),
(174, 3, 'owner', 'RESERVATION_APPROVED', '', NULL, NULL, NULL, 'Reservation #22 for ewan q mcqueen approved by admin \'owner\'', '', NULL, '2025-11-26 06:02:44'),
(175, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-11-26 06:29:02'),
(176, 3, 'owner', 'WALKIN_ADDED', '', NULL, NULL, NULL, 'Walk-in reservation added by owner for customer: joyce (vios nikka, 30923)', '', NULL, '2025-11-26 06:32:33'),
(177, 3, 'owner', 'RESERVATION_APPROVED', '', NULL, NULL, NULL, 'Reservation #23 for vios nikka approved by admin \'owner\'', '', NULL, '2025-11-26 06:32:40'),
(178, 3, 'owner', 'SERVICE_ADD', '', NULL, NULL, NULL, 'New service added: jun (₱2323, 23)', '', NULL, '2025-11-26 06:59:41'),
(179, 3, 'owner', 'SERVICE_ARCHIVE', '', NULL, NULL, NULL, 'Archived service: jun (₱2,323.00, 23)', '', NULL, '2025-11-26 06:59:51'),
(180, 3, 'owner', 'SERVICE_RESTORE', '', NULL, NULL, NULL, 'Restored service: jun (₱2,323.00, 23)', '', NULL, '2025-11-26 06:59:56'),
(181, 3, 'owner', 'SERVICE_ARCHIVE', '', NULL, NULL, NULL, 'Archived service: jun (₱2,323.00, 23)', '', NULL, '2025-11-26 07:00:14'),
(182, 3, 'owner', 'SERVICE_DELETED', '', NULL, NULL, NULL, 'Deleted service: jun (₱2,323.00, 23)', '', NULL, '2025-11-26 07:00:20'),
(183, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #8.', '', NULL, '2025-11-26 07:04:27'),
(184, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #9.', '', NULL, '2025-11-26 07:54:10'),
(185, 3, 'owner', 'USER_LOGOUT', '', NULL, NULL, NULL, ' \'owner\' logged out.', '', NULL, '2025-11-26 07:58:14'),
(186, 0, 'Unknown', 'USER_LOGOUT', '', NULL, NULL, NULL, ' \'Unknown\' logged out.', '', NULL, '2025-11-26 07:58:21'),
(187, 4, 'admin', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: admin', '', NULL, '2025-11-26 07:58:22'),
(188, 0, 'Unknown', 'USER_LOGOUT', '', NULL, NULL, NULL, ' \'Unknown\' logged out.', '', NULL, '2025-12-13 15:28:37'),
(189, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-12-13 15:28:46'),
(190, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-12-13 15:36:12'),
(191, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-12-14 14:02:27'),
(192, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #10.', '', NULL, '2025-12-14 14:14:56'),
(193, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-12-15 13:52:52'),
(194, 3, 'owner', 'RESERVATION_ARCHIVED', '', NULL, NULL, NULL, 'Reservation #21 (Kristine - ewan q mcqueen) scheduled on 2025-11-27 at 07:00:00 was archived by admin \'owner\'.', '', NULL, '2025-12-15 13:53:22'),
(195, 3, 'owner', 'WALKIN_ADDED', '', NULL, NULL, NULL, 'Walk-in reservation added and automatically completed by owner for customer: a (a a, a)', '', NULL, '2025-12-15 14:07:37'),
(196, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #11.', '', NULL, '2025-12-15 14:12:12'),
(197, 3, 'owner', 'PAYMENT_REJECTED', '', NULL, NULL, NULL, 'Admin \'owner\' rejected payment #12.', '', NULL, '2025-12-15 15:12:07'),
(198, 3, 'owner', 'RESERVATION_APPROVED', '', NULL, NULL, NULL, 'Reservation #29 for salamangka meron ka approved by admin \'owner\'', '', NULL, '2025-12-15 15:13:24'),
(199, 3, 'owner', 'RESERVATION_COMPLETED', '', NULL, NULL, NULL, 'Reservation #29 (kite - salamangka meron ka) marked as completed by admin \'owner\'.', '', NULL, '2025-12-15 15:16:35'),
(200, 3, 'owner', 'RESERVATION_RESTORED', '', NULL, NULL, NULL, 'Reservation #29 restored from completed to pending by admin \'owner\'.', '', NULL, '2025-12-15 15:26:23'),
(201, 3, 'owner', 'RESERVATION_APPROVED', '', NULL, NULL, NULL, 'Reservation #29 for salamangka meron ka approved and moved to completed by admin \'owner\'', '', NULL, '2025-12-15 15:26:30'),
(202, 3, 'owner', 'WALKIN_ADDED', '', NULL, NULL, NULL, 'Walk-in reservation added and automatically completed by owner for customer: ay akin (asd addd, aa)', '', NULL, '2025-12-15 15:29:57'),
(203, 3, 'owner', 'RESERVATION_RESTORED', '', NULL, NULL, NULL, 'Reservation #30 restored from completed to pending by admin \'owner\'.', '', NULL, '2025-12-15 15:30:28'),
(204, 3, 'owner', 'RESERVATION_DECLINED', '', NULL, NULL, NULL, 'Reservation #30 for asd addd declined by admin \'owner\'', '', NULL, '2025-12-15 15:30:33'),
(205, 3, 'owner', 'RESERVATION_DECLINED', '', NULL, NULL, NULL, 'Reservation #26 for gie ss declined by admin \'owner\'', '', NULL, '2025-12-15 15:46:09'),
(206, 3, 'owner', 'RESERVATION_DELETED', '', NULL, NULL, NULL, 'Declined reservation #26 (kite - gie ss) permanently deleted by admin \'owner\'.', '', NULL, '2025-12-15 15:46:22'),
(207, 3, 'owner', 'USER_LOGOUT', '', NULL, NULL, NULL, ' \'owner\' logged out.', '', NULL, '2025-12-15 15:53:51'),
(208, 4, 'admin', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: admin', '', NULL, '2025-12-15 15:54:02'),
(209, 4, 'admin', 'USER_LOGOUT', '', NULL, NULL, NULL, ' \'admin\' logged out.', '', NULL, '2025-12-15 16:09:22'),
(210, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-12-15 16:09:27'),
(211, 3, 'owner', 'USER_LOGOUT', '', NULL, NULL, NULL, ' \'owner\' logged out.', '', NULL, '2025-12-15 16:09:56');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `user_id`, `name`, `phone`, `email`, `created_at`) VALUES
(35, 5, 'lloyd', '09070220027', 'johnlloydbuenaflor12@gmail.com', '2025-10-11 08:12:37'),
(36, 2, 'Johnlloyd Buenaflor', '09070220027', 'admin@mepfs.com', '2025-10-11 09:13:04'),
(37, NULL, 'Johnlloyd Buenaflor', '09070220027', 'johnlloydbuenaflor19@gmail.com', '2025-10-15 17:01:43'),
(38, 6, 'Rojale', '09120731273', 'rojaleeeking@gmail.com', '2025-11-20 05:10:01'),
(39, NULL, 'ritz dela isla', '09120731273', 'rojaleeeking@gmail.com', '2025-11-20 05:29:57'),
(40, 8, 'sad', '123', 'me@gmail.com', '2025-11-22 02:28:14'),
(41, NULL, 'me', 'wqe', 'why@gmail.com', '2025-11-22 02:47:51'),
(42, NULL, 'kite', '09824', 'kite@gmail.com', '2025-11-22 03:18:15'),
(43, 12, 'kite', '093232', 'kite@gmail.com', '2025-11-22 04:12:01'),
(44, NULL, 'mingmin', '9278394', 'mingming@gmail.com', '2025-11-22 05:06:21'),
(45, NULL, 'me', 'me', 'me@gmail.com', '2025-11-22 07:18:57'),
(46, 13, 'dragon', '0919324', 'dragon@gmail.com', '2025-11-22 07:28:54'),
(47, NULL, 'baron', '009024', 'baron@gmail.com', '2025-11-22 07:31:35'),
(48, NULL, 'as', 'sd', 'sd@gmail.com', '2025-11-22 07:47:08'),
(49, NULL, 'as', 'sd', 'sd@gmail.com', '2025-11-22 07:48:06'),
(50, NULL, 'hi', '0942', 'hi@gmail.com', '2025-11-22 12:01:54'),
(51, NULL, 'hi', '0942', 'hi@gmail.com', '2025-11-22 12:02:47'),
(52, NULL, 'yelo', '090342', 'yelo@gmail.com', '2025-11-22 12:04:48'),
(53, 14, 'me', '020940', 'me@gmail.com', '2025-11-22 12:33:20'),
(54, 15, 'wala', '0856596', 'ritzkarldelahoya@gmail.com', '2025-11-24 07:00:40'),
(55, NULL, 'basta', 'as', 'baron@gmail.com', '2025-11-24 14:14:19'),
(56, NULL, 'asd', '324', 'me@gmail.com', '2025-11-24 14:38:17'),
(57, 16, 'bry reguyal', '0923042084', 'breguyal03@gmail.com', '2025-11-24 16:36:27'),
(58, 17, 'rojale', '09342422', 'rojaleeeking@gmail.com', '2025-11-25 03:44:24'),
(59, 18, 'Kristine', '099239857273502', 'khianbuenaflor586@gmail.com', '2025-11-25 03:58:43'),
(60, 19, 'COMPLETO, DEXTER L.', '09056582049', 'dexter.completo01@gmail.com', '2025-11-25 13:19:54'),
(61, 21, 'Hans Paraon', '09123456789', 'hans@gmail.com', '2025-11-26 00:11:46'),
(62, NULL, 'joyce', '89340394', 'jdss@gmail.com', '2025-11-26 06:32:33'),
(63, 23, 'rod', '32423423', 'christianrodmemoracion@gmail.com', '2025-11-26 07:04:02'),
(64, 3, 'kristine', '0943425235', 'rci.bsis.buenaflorkristinemae@gmail.com', '2025-11-26 07:52:12'),
(65, 24, 'kite', '0932423', 'rci.bsis.delaislaritzkarl@gmail.com', '2025-12-14 14:14:16'),
(66, NULL, 'a', 'a', 'a@gmail.com', '2025-12-15 14:04:32'),
(67, NULL, 'a', 'a', 'a@gmail.com', '2025-12-15 14:07:37'),
(68, NULL, 'ay akin', '0293`', 'dada@gmail.com', '2025-12-15 15:29:57');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_proof` varchar(255) NOT NULL,
  `payment_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `reservation_id`, `account_name`, `amount_paid`, `payment_proof`, `payment_status`, `verified_by`, `verified_at`, `notes`, `created_at`) VALUES
(6, 21, 'dada', 400.00, 'proof_69267c6f3cc6d.jpg', 'verified', 3, '2025-11-26 04:05:41', '', '2025-11-26 04:05:03'),
(7, 22, 'dada', 200.00, 'proof_69267d7926bb6.jpg', 'rejected', 3, '2025-11-26 04:09:51', 'sorry', '2025-11-26 04:09:29'),
(8, 24, 'krsitne', 400.00, 'proof_6926a66eed807.png', 'verified', 3, '2025-11-26 07:04:23', '', '2025-11-26 07:04:14'),
(9, 25, 'kristine', 400.00, 'proof_6926b1d7e1900.jpg', 'verified', 3, '2025-11-26 07:54:07', '', '2025-11-26 07:52:55'),
(10, 26, 'f', 950.00, 'proof_693ec6504224a.png', 'verified', 3, '2025-12-14 14:14:52', '', '2025-12-14 14:14:40'),
(11, 28, '23', 30000.00, 'proof_6940172c2d101.jpg', 'verified', 3, '2025-12-15 14:12:09', '', '2025-12-15 14:11:56'),
(12, 29, 'isa', 1000.00, 'proof_69402533b32e7.png', 'rejected', 3, '2025-12-15 15:12:07', 'well', '2025-12-15 15:11:47');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `vehicle_make` varchar(50) DEFAULT NULL,
  `vehicle_model` varchar(50) DEFAULT NULL,
  `vehicle_year` varchar(10) DEFAULT NULL,
  `reservation_date` date DEFAULT NULL,
  `reservation_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `method` enum('Walk-In','Online') NOT NULL DEFAULT 'Online',
  `status` varchar(20) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `customer_id`, `vehicle_make`, `vehicle_model`, `vehicle_year`, `reservation_date`, `reservation_time`, `end_time`, `method`, `status`, `created_at`, `archived`) VALUES
(21, 59, 'ewan q', 'mcqueen', '2000', '2025-11-27', '07:00:00', '09:00:00', 'Online', 'confirmed', '2025-11-26 04:05:03', 1),
(22, 59, 'ewan q', 'mcqueen', '2000', '2025-11-27', '09:30:00', '10:30:00', 'Online', 'approved', '2025-11-26 04:09:29', 0),
(23, 62, 'vios', 'nikka', '30923', '2025-12-06', '17:30:00', '19:30:00', 'Walk-In', 'approved', '2025-11-26 06:32:33', 0),
(24, 63, 'ew', 'er', 're', '2025-12-05', '17:30:00', '19:30:00', 'Online', 'confirmed', '2025-11-26 07:04:14', 0),
(25, 64, 'yamaha', 'mio', '324234', '2025-11-27', '15:00:00', '17:00:00', 'Online', 'confirmed', '2025-11-26 07:52:55', 0),
(27, 67, 'a', 'a', 'a', '2025-12-24', '08:00:00', '10:00:00', 'Walk-In', 'approved', '2025-12-15 14:07:37', 1),
(28, 65, 'sd', 'sad', 'sd', '2025-12-23', '16:30:00', '17:30:00', 'Online', 'confirmed', '2025-12-15 14:11:56', 0),
(29, 65, 'salamangka', 'meron ka', '303', '2026-01-08', '08:00:00', '08:30:00', 'Online', 'approved', '2025-12-15 15:11:47', 1),
(30, 68, 'asd', 'addd', 'aa', '2026-01-10', '16:30:00', '17:30:00', 'Walk-In', 'declined', '2025-12-15 15:29:57', 0);

-- --------------------------------------------------------

--
-- Table structure for table `reservation_services`
--

CREATE TABLE `reservation_services` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservation_services`
--

INSERT INTO `reservation_services` (`id`, `reservation_id`, `service_id`) VALUES
(71, 56, 16),
(72, 56, 17),
(73, 57, 16),
(74, 57, 17),
(75, 57, 19),
(76, 58, 16),
(77, 58, 17),
(78, 59, 16),
(79, 59, 17),
(80, 60, 16),
(81, 60, 17),
(82, 60, 19),
(83, 61, 16),
(84, 61, 17),
(85, 1, 16),
(86, 2, 16),
(87, 3, 16),
(88, 3, 17),
(89, 4, 16),
(90, 4, 17),
(91, 5, 16),
(92, 5, 17),
(93, 6, 16),
(94, 6, 17),
(95, 7, 27),
(96, 8, 19),
(97, 9, 19),
(98, 9, 20),
(99, 10, 19),
(100, 10, 20),
(101, 11, 23),
(102, 12, 19),
(103, 12, 20),
(104, 13, 19),
(105, 13, 25),
(106, 14, 25),
(107, 15, 19),
(108, 15, 20),
(109, 15, 22),
(110, 16, 23),
(111, 16, 25),
(112, 17, 19),
(113, 18, 19),
(114, 18, 20),
(115, 19, 19),
(116, 20, 20),
(117, 20, 23),
(118, 21, 19),
(119, 21, 20),
(120, 22, 19),
(121, 23, 19),
(122, 23, 20),
(123, 24, 19),
(124, 24, 20),
(125, 25, 19),
(126, 25, 20),
(129, 27, 20),
(130, 27, 22),
(131, 28, 23),
(132, 29, 25),
(133, 30, 19);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `service_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `duration` varchar(2555) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `service_name`, `description`, `duration`, `price`, `photo`, `is_archived`) VALUES
(19, 'Electrical', 'Diagnosis and repair of the vehicle’s electrical components to restore proper function to systems such as lights, wiring, sensors, and electronics.', '60', 200.00, 'svc_691eb854946ee4.36325979.jpg', 0),
(20, 'Auto Body Repair', 'Restoration of damaged vehicle panels and structure to bring the car back to its proper shape and safety condition.', '60', 200.00, 'svc_691eb8e0380af1.14068011.jpg', 0),
(22, 'Aircon Cleaning', 'A thorough cleaning of the vehicle’s AC system to improve cooling performance, remove dirt and bacteria, and ensure fresh, clean cabin air.', '60', 750.00, 'svc_6921bc7473d912.68055761.webp', 0),
(23, 'Auto Painting', 'Application of fresh automotive paint to restore color, improve appearance, or repair paint damage.', '60', 30000.00, 'svc_6921bd8c63b045.05306007.jpg', 0),
(25, 'Under Wash', 'Cleaning of the vehicle’s undercarriage to remove dirt, mud, and debris that can cause rust and damage.', '30', 1000.00, 'svc_6921beb1ed4a46.85683860.jpg', 0),
(26, 'Engine Tune Up', 'Adjustment and replacement of key engine components to improve fuel efficiency, performance, and reliability.', '60', 2000.00, 'svc_6921bf848a8ee5.58582476.jpg', 0),
(27, 'Change Oil', 'Replacement of old engine oil and oil filter to protect the engine and maintain smooth, efficient operation.', '60', 800.00, 'svc_6921bfe19bc0b8.07869680.jpg', 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `code` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `code`, `created_at`) VALUES
(1, 'KuyaJack', 'johnlloydbuenaflor19@gmail.com', '$2y$10$2uylwXbvtIDqQSD7RzrtquHk/kErST8Px3fDcsMxiHPKUutuTlZ92', NULL, '2025-08-25 09:52:40'),
(2, 'admin', 'johnlloydbuenaflor@gmail.com', '$2y$10$w6n2s9Nd6LpbJHyVVBWljO9UmovdW5vg6hZX.sJAKRC.fP.JSxvEe', NULL, '2025-10-06 02:42:33'),
(3, 'kristine', 'rci.bsis.buenaflorkristinemae@gmail.com', '$2y$10$1Dlrb8yK6ugQS9VGxEycy.AfA0FgcTVK.RPL1fKLwP9ygQM3qFE2G', '591150', '2025-10-09 10:39:08'),
(5, 'lloyd', 'johnlloydbuenaflor12@gmail.com', '$2y$10$SR8oLWxLYZQgOZJw/Q.mA.UHczixi9krqxbyY5xaWaPLnYLlRCCWG', NULL, '2025-10-11 08:07:11'),
(6, 'test', 'test@gmail.com', '$2y$10$uYgOAJmMZmxTwizM0LZsr.nOJKLkp5OhDYg.Gg0l/1RWwwCGrMoli', NULL, '2025-11-20 04:32:16'),
(7, 'rets', 'ritzdelaisla@gmail.com', '$2y$10$HdVQ0yTVQwIHA5eUwftwf.e5ngIOTu.buOq8ThujyzWo7WXFhtjey', NULL, '2025-11-20 06:33:41'),
(8, 'me', 'me@gmail.com', '$2y$10$WcJ4mmyAkq6ItCVH.PJziern2U3NP2k1mtRmcx8OM4B6u00oH5Ll2', NULL, '2025-11-21 15:22:52'),
(11, 'meow', 'meow@gmail.com', '$2y$10$yDD7szTVMeulOOZSqvUX0O7ZJhoWOOW2KIsRAEP0cSp5GTroMoAmK', NULL, '2025-11-21 15:47:20'),
(12, 'kite', 'kite@gmail.com', '$2y$10$q7ofHCT8MCN.zrQc8UwzpOWKpBOGJQ7yMKf2/lufjp9/Z0W/Drkse', NULL, '2025-11-22 04:11:11'),
(13, 'baron', 'baron@gmail.com', '$2y$10$XVHGz88LNoSs1a.xFfoKD.Tw4/qZt.0MqcrJo4wXzagyRws.FPls2', NULL, '2025-11-22 07:28:16'),
(14, 'memyself', 'memyself@gmail.com', '$2y$10$wxKANaFGKU5InWskyVshRees8YMCRQdeLvyadfVNRH.gSavrVe7ge', NULL, '2025-11-22 12:32:15'),
(15, 'ritzkarldelahoya', 'ritzkarldelahoya@gmail.com', '$2y$10$whnMBCAVGXlqY/G5al6TlOAt8GjvG0ysTq/lmQFELQTZdKwMNz41.', NULL, '2025-11-24 06:57:05'),
(16, 'bry', 'breguyal03@gmail.com', '$2y$10$fSu67ySKWNE5JKf3ilBRCuaHWWAVnCcQ3wPqMSH9LP0yAeKA20ncO', NULL, '2025-11-24 16:34:01'),
(17, 'rojale', 'rojaleeeking@gmail.com', '$2y$10$GFfXFOlSQlJRbGn5s4QjmOA8UbFOEijOz/AF1HqRjRuTjN.zcJ4xW', NULL, '2025-11-25 03:43:49'),
(18, 'dadats', 'khianbuenaflor586@gmail.com', '$2y$10$pdfW31OA5zjCg9OR9eBq3O/NaInENLO1bcx99D1CZWzVRw67OozXe', NULL, '2025-11-25 03:56:59'),
(19, 'kese', 'dexter.completo01@gmail.com', '$2y$10$BvedatmQTwp6h/OpekWPy.TPyTVWPhS5SsjZrQyUYT8wMmv1qtWuK', NULL, '2025-11-25 13:18:18'),
(20, 'recca', 'recca@gmail.com', '$2y$10$iX1Kw2bv2CHZTuS9xDZXxuzS1ZPjNDxP7O53Jf22D0Lbt2uLOb/1u', NULL, '2025-11-25 17:36:03'),
(21, 'hollowvesp04', 'hans@gmail.com', '$2y$10$5hv.CPMZxHpUajb0mMyfB.zLVNrPfOo5qTXeQX1CqMrSMvRDjdMqO', NULL, '2025-11-26 00:07:23'),
(22, 'hh', 'h@gmail.com', '$2y$10$irR/MZJPIcrgfUQeDWgg7eQtTg.XHzpTcHJsMyebq3JFpgo.4eUBG', NULL, '2025-11-26 04:06:13'),
(23, 'rod', 'christianrodmemoracion@gmail.com', '$2y$10$fYLGBX3BUwTj43Hc6DvRcOupshvORymefOMSZBUmgqrLJpQ9LUZum', NULL, '2025-11-26 07:03:21'),
(24, 'kiteeee', 'rci.bsis.delaislaritzkarl@gmail.com', '$2y$10$uxyG8Jr2R3ZVEjLIXGelN.wJc59l6oVRif2eYsz57Pq8Iz6cwvucm', '734988', '2025-12-13 15:09:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_table_name` (`table_name`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reservation_services`
--
ALTER TABLE `reservation_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reservation_id` (`reservation_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=212;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `reservation_services`
--
ALTER TABLE `reservation_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
