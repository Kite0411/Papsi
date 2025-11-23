-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 23, 2025 at 02:36 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `autorepair_db`
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
(1, 3, 'owner', 'USER_LOGOUT', '', NULL, NULL, NULL, ' \'owner\' logged out.', '', NULL, '2025-11-22 02:27:24'),
(2, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-11-22 02:28:41'),
(3, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 02:28:51'),
(4, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 02:28:54'),
(5, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 02:28:57'),
(6, 3, 'owner', 'UPDATE', '', NULL, NULL, NULL, 'Approved reservation (#41) for ritz dela isla', '', NULL, '2025-11-22 02:46:23'),
(7, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 02:47:22'),
(8, 3, 'owner', 'WALKIN_ADDED', '', NULL, NULL, NULL, 'Walk-in reservation added by owner for customer: me (wqe qwe, wqe)', '', NULL, '2025-11-22 02:47:51'),
(9, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #38 (Johnlloyd Buenaflor - Honda GTR) scheduled on 2025-10-16 at 16:00:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 02:48:08'),
(10, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #40 (Rojale - aa aa) scheduled on 2025-11-21 at 07:30:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 02:48:10'),
(11, 3, 'owner', 'UPDATE', '', NULL, NULL, NULL, 'Declined reservation (#45) for me', '', NULL, '2025-11-22 02:48:13'),
(12, 3, 'owner', 'RESERVATION_PENDING', '', NULL, NULL, NULL, 'Reservation #42 set to PENDING after payment verification by \'owner\'.', '', NULL, '2025-11-22 03:14:30'),
(13, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 03:14:33'),
(14, 3, 'owner', 'WALKIN_ADDED', '', NULL, NULL, NULL, 'Walk-in reservation added by owner for customer: kite (bmw s, 291)', '', NULL, '2025-11-22 03:18:15'),
(15, 3, 'owner', 'UPDATE', '', NULL, NULL, NULL, 'Approved reservation (#47) for kite', '', NULL, '2025-11-22 03:19:37'),
(16, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 03:29:41'),
(17, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0. Reservation status set to \'confirmed\'.', '', NULL, '2025-11-22 03:36:17'),
(18, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 03:48:23'),
(19, 3, 'owner', 'RESERVATION_APPROVED', '', NULL, NULL, NULL, 'Reservation #50 for we we approved by admin \'owner\'.', '', NULL, '2025-11-22 03:48:55'),
(20, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 04:05:16'),
(21, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 04:09:06'),
(22, 3, 'owner', 'RESERVATION_APPROVED', '', NULL, NULL, NULL, 'Reservation #53 for sd sda approved by admin \'owner\'.', '', NULL, '2025-11-22 04:09:49'),
(23, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 04:12:24'),
(24, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 04:12:27'),
(25, 3, 'owner', 'RESERVATION_APPROVED', '', NULL, NULL, NULL, 'Reservation #54 for kite kite approved by admin \'owner\'.', '', NULL, '2025-11-22 04:13:20'),
(26, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #54 (kite - kite kite) scheduled on 2025-12-11 at 18:00:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:13:43'),
(27, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 04:22:46'),
(28, 3, 'owner', 'RESERVATION_APPROVED', '', NULL, NULL, NULL, 'Reservation #55 for kitee kitee approved by admin \'owner\'.', '', NULL, '2025-11-22 04:22:50'),
(29, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #55 (kite - kitee kitee) scheduled on 2025-12-23 at 17:00:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:24:44'),
(30, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #53 (sad - sd sda) scheduled on 2025-12-05 at 17:30:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:24:46'),
(31, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #48 (sad - ss s) scheduled on 2025-11-28 at 18:00:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:24:48'),
(32, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #43 (sad - asd asd) scheduled on 2025-11-28 at 15:00:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:24:51'),
(33, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #46 (sad - ds f) scheduled on 2025-11-28 at 13:00:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:24:53'),
(34, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #45 (me - wqe qwe) scheduled on 2025-11-28 at 08:00:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:24:55'),
(35, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #47 (kite - bmw s) scheduled on 2025-11-27 at 18:30:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:24:58'),
(36, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #44 (sad - wqe qwe) scheduled on 2025-11-27 at 15:00:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:25:00'),
(37, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #52 (sad - 2 paldo) scheduled on 2025-11-27 at 10:00:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:25:02'),
(38, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #49 (sad - sss sss) scheduled on 2025-11-27 at 07:00:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:25:04'),
(39, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #50 (sad - we we) scheduled on 2025-11-26 at 17:30:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:25:07'),
(40, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #51 (sad - s ss) scheduled on 2025-11-24 at 18:00:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:25:09'),
(41, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #41 (ritz dela isla - aa aa) scheduled on 2025-11-20 at 15:30:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:25:11'),
(42, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #42 (Rojale - aa aa) scheduled on 2025-11-20 at 15:30:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:25:12'),
(43, 3, 'owner', 'RESERVATION_DELETE', '', NULL, NULL, NULL, 'Reservation #39 (Rojale - aa aa) scheduled on 2025-11-20 at 14:00:00 was deleted by admin \'owner\'.', '', NULL, '2025-11-22 04:25:14'),
(44, 3, 'owner', 'SERVICE_ARCHIVE', '', NULL, NULL, NULL, 'Archived service: Auto Body Repair (₱200.00, 60)', '', NULL, '2025-11-22 04:31:57'),
(45, 3, 'owner', 'SERVICE_RESTORE', '', NULL, NULL, NULL, 'Restored service: Auto Body Repair (₱200.00, 60)', '', NULL, '2025-11-22 04:41:26'),
(46, 3, 'owner', 'SERVICE_ARCHIVE', '', NULL, NULL, NULL, 'Archived service: Auto Body Repair (₱200.00, 60)', '', NULL, '2025-11-22 04:49:42'),
(47, 3, 'owner', 'SERVICE_RESTORE', '', NULL, NULL, NULL, 'Restored service: Auto Body Repair (₱200.00, 60)', '', NULL, '2025-11-22 04:49:46'),
(48, 3, 'owner', 'SERVICE_ARCHIVE', '', NULL, NULL, NULL, 'Archived service: Auto Body Repair (₱200.00, 60)', '', NULL, '2025-11-22 04:49:50'),
(49, 3, 'owner', 'SERVICE_RESTORE', '', NULL, NULL, NULL, 'Restored service: Auto Body Repair (₱200.00, 60)', '', NULL, '2025-11-22 04:50:06'),
(50, 3, 'owner', 'SERVICE_ARCHIVE', '', NULL, NULL, NULL, 'Archived service: Auto Body Repair (₱200.00, 60)', '', NULL, '2025-11-22 04:56:35'),
(51, 3, 'owner', 'SERVICE_RESTORE', '', NULL, NULL, NULL, 'Restored service: Auto Body Repair (₱200.00, 60)', '', NULL, '2025-11-22 04:56:52'),
(52, 3, 'owner', 'SERVICE_ARCHIVE', '', NULL, NULL, NULL, 'Archived service: Auto Body Repair (₱200.00, 60)', '', NULL, '2025-11-22 05:05:09'),
(53, 3, 'owner', 'SERVICE_RESTORE', '', NULL, NULL, NULL, 'Restored service: Auto Body Repair (₱200.00, 60)', '', NULL, '2025-11-22 05:05:17'),
(54, 3, 'owner', 'WALKIN_ADDED', '', NULL, NULL, NULL, 'Walk-in reservation added by owner for customer: mingmin (ming ming, 0233)', '', NULL, '2025-11-22 05:06:21'),
(55, 3, 'owner', 'RESERVATION_ARCHIVE', '', NULL, NULL, NULL, 'Reservation #56 (mingmin - ming ming) scheduled on 2025-11-29 at 18:00:00 was archived by admin \'owner\'.', '', NULL, '2025-11-22 05:13:02'),
(56, 3, 'owner', 'RESERVATION_ARCHIVE', '', NULL, NULL, NULL, 'Reservation #56 (mingmin - ming ming) scheduled on 2025-11-29 at 18:00:00 was archived by admin \'owner\'.', '', NULL, '2025-11-22 05:13:06'),
(57, 3, 'owner', 'RESERVATION_ARCHIVE', '', NULL, NULL, NULL, 'Reservation #56 (mingmin - ming ming) scheduled on 2025-11-29 at 18:00:00 was archived by admin \'owner\'.', '', NULL, '2025-11-22 05:13:27'),
(58, 3, 'owner', 'RESERVATION_ARCHIVE', '', NULL, NULL, NULL, 'Reservation #56 (mingmin - ming ming) scheduled on 2025-11-29 at 18:00:00 was archived by admin \'owner\'.', '', NULL, '2025-11-22 05:14:25'),
(59, 3, 'owner', 'RESERVATION_ARCHIVE', '', NULL, NULL, NULL, 'Reservation #56 (mingmin - ming ming) scheduled on 2025-11-29 at 18:00:00 was archived by admin \'owner\'.', '', NULL, '2025-11-22 05:14:30'),
(60, 3, 'owner', 'RESERVATION_ARCHIVE', '', NULL, NULL, NULL, 'Reservation #56 (mingmin - ming ming) scheduled on 2025-11-29 at 18:00:00 was archived by admin \'owner\'.', '', NULL, '2025-11-22 05:15:03'),
(61, 3, 'owner', 'RESERVATION_ARCHIVED', '', NULL, NULL, NULL, 'Reservation #56 (mingmin - ming ming) scheduled on 2025-11-29 at 18:00:00 was archived by admin \'owner\'.', '', NULL, '2025-11-22 05:34:04'),
(62, 3, 'owner', 'RESERVATION_RESTORED', '', NULL, NULL, NULL, 'Reservation #56 restored by admin \'owner\'.', '', NULL, '2025-11-22 05:34:19'),
(63, 3, 'owner', 'RESERVATION_ARCHIVED', '', NULL, NULL, NULL, 'Reservation #56 (mingmin - ming ming) scheduled on 2025-11-29 at 18:00:00 was archived by admin \'owner\'.', '', NULL, '2025-11-22 05:36:15'),
(64, 3, 'owner', 'RESERVATION_RESTORED', '', NULL, NULL, NULL, 'Reservation #56 restored by admin \'owner\'.', '', NULL, '2025-11-22 05:39:47'),
(65, 3, 'owner', 'SERVICE_ADD', '', NULL, NULL, NULL, 'New service added: AUTO PAINTING (₱2000, 60)', '', NULL, '2025-11-22 05:58:59'),
(66, 3, 'owner', 'USER_LOGOUT', '', NULL, NULL, NULL, ' \'owner\' logged out.', '', NULL, '2025-11-22 05:59:25'),
(67, 4, 'admin', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: admin', '', NULL, '2025-11-22 05:59:34'),
(68, 4, 'admin', 'USER_LOGOUT', '', NULL, NULL, NULL, ' \'admin\' logged out.', '', NULL, '2025-11-22 06:10:46'),
(69, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-11-22 06:10:51'),
(70, 3, 'owner', 'USER_LOGOUT', '', NULL, NULL, NULL, ' \'owner\' logged out.', '', NULL, '2025-11-22 06:10:58'),
(71, 4, 'admin', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: admin', '', NULL, '2025-11-22 06:11:05'),
(72, 4, 'admin', 'USER_LOGOUT', '', NULL, NULL, NULL, ' \'admin\' logged out.', '', NULL, '2025-11-22 06:15:25'),
(73, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-11-22 06:15:36'),
(74, 3, 'owner', 'RESERVATION_ARCHIVED', '', NULL, NULL, NULL, 'Reservation #56 (mingmin - ming ming) scheduled on 2025-11-29 at 18:00:00 was archived by admin \'owner\'.', '', NULL, '2025-11-22 06:15:46'),
(75, 3, 'owner', 'RESERVATION_RESTORED', '', NULL, NULL, NULL, 'Reservation #56 restored by admin \'owner\'.', '', NULL, '2025-11-22 06:15:51'),
(76, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 07:17:11'),
(77, 3, 'owner', 'USER_LOGOUT', '', NULL, NULL, NULL, ' \'owner\' logged out.', '', NULL, '2025-11-22 07:18:28'),
(78, 4, 'admin', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: admin', '', NULL, '2025-11-22 07:18:36'),
(79, 4, 'admin', 'WALKIN_ADDED', '', NULL, NULL, NULL, 'Walk-in reservation added by admin for customer: me (me me, 2033)', '', NULL, '2025-11-22 07:18:57'),
(80, 4, 'admin', 'RESERVATION_APPROVED', '', NULL, NULL, NULL, 'Reservation #59 for bmw basta approved by admin \'admin\'', '', NULL, '2025-11-22 07:29:13'),
(81, 4, 'admin', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'admin\' verified payment #0.', '', NULL, '2025-11-22 07:29:30'),
(82, 4, 'admin', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'admin\' verified payment #0.', '', NULL, '2025-11-22 07:30:57'),
(83, 4, 'admin', 'RESERVATION_APPROVED', '', NULL, NULL, NULL, 'Reservation #60 for ss sss approved by admin \'admin\'', '', NULL, '2025-11-22 07:31:01'),
(84, 4, 'admin', 'WALKIN_ADDED', '', NULL, NULL, NULL, 'Walk-in reservation added by admin for customer: baron (hhhh hhhh, 20031)', '', NULL, '2025-11-22 07:31:35'),
(85, 4, 'admin', 'WALKIN_ADDED', '', NULL, NULL, NULL, 'Walk-in reservation added by admin for customer: as (sd d, 234)', '', NULL, '2025-11-22 07:47:08'),
(86, 4, 'admin', 'WALKIN_ADDED', '', NULL, NULL, NULL, 'Walk-in reservation added by admin for customer: as (sd d, 234)', '', NULL, '2025-11-22 07:48:06'),
(87, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-11-22 07:53:34'),
(88, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-11-22 11:50:53'),
(89, 3, 'owner', 'WALKIN_ADDED', '', NULL, NULL, NULL, 'Walk-in reservation added by owner for customer: hi (HII HIII, 29003)', '', NULL, '2025-11-22 12:02:47'),
(90, 3, 'owner', 'RESERVATION_APPROVED', '', NULL, NULL, NULL, 'Reservation #3 for HII HIII approved by admin \'owner\'', '', NULL, '2025-11-22 12:02:57'),
(91, 3, 'owner', 'WALKIN_ADDED', '', NULL, NULL, NULL, 'Walk-in reservation added by owner for customer: yelo (ds s, 213)', '', NULL, '2025-11-22 12:04:48'),
(92, 3, 'owner', 'RESERVATION_APPROVED', '', NULL, NULL, NULL, 'Reservation #4 for ds s approved by admin \'owner\'', '', NULL, '2025-11-22 12:04:58'),
(93, 3, 'owner', 'RESERVATION_ARCHIVED', '', NULL, NULL, NULL, 'Reservation #3 (hi - HII HIII) scheduled on 2025-12-05 at 07:30:00 was archived by admin \'owner\'.', '', NULL, '2025-11-22 12:07:22'),
(94, 3, 'owner', 'RESERVATION_ARCHIVED', '', NULL, NULL, NULL, 'Reservation #1 (as - sd d) scheduled on 2025-12-03 at 16:30:00 was archived by admin \'owner\'.', '', NULL, '2025-11-22 12:07:24'),
(95, 3, 'owner', 'USER_LOGOUT', '', NULL, NULL, NULL, ' \'owner\' logged out.', '', NULL, '2025-11-22 12:15:20'),
(96, 4, 'admin', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: admin', '', NULL, '2025-11-22 12:15:26'),
(97, 4, 'admin', 'RESERVATION_ARCHIVED', '', NULL, NULL, NULL, 'Reservation #2 (as - sd d) scheduled on 2025-12-03 at 16:30:00 was archived by admin \'admin\'.', '', NULL, '2025-11-22 12:16:14'),
(98, 4, 'admin', 'RESERVATION_ARCHIVED', '', NULL, NULL, NULL, 'Reservation #4 (yelo - ds s) scheduled on 2025-12-03 at 14:00:00 was archived by admin \'admin\'.', '', NULL, '2025-11-22 12:16:17'),
(99, 4, 'admin', 'RESERVATION_RESTORED', '', NULL, NULL, NULL, 'Reservation #3 restored by admin \'admin\'.', '', NULL, '2025-11-22 12:16:23'),
(100, 4, 'admin', 'USER_LOGOUT', '', NULL, NULL, NULL, ' \'admin\' logged out.', '', NULL, '2025-11-22 12:19:40'),
(101, 3, 'owner', 'USER_LOGIN', '', NULL, NULL, NULL, 'User logged in: owner', '', NULL, '2025-11-22 12:19:49'),
(102, 3, 'owner', 'RESERVATION_APPROVED', '', NULL, NULL, NULL, 'Reservation #5 for sa love approved by admin \'owner\'', '', NULL, '2025-11-22 12:23:49'),
(103, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 12:24:17'),
(104, 3, 'owner', 'RESERVATION_APPROVED', '', NULL, NULL, NULL, 'Reservation #6 for me myself approved by admin \'owner\'', '', NULL, '2025-11-22 12:33:40'),
(105, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 12:33:54'),
(106, 3, 'owner', 'PAYMENT_VERIFIED', '', NULL, NULL, NULL, 'Admin \'owner\' verified payment #0.', '', NULL, '2025-11-22 12:33:57'),
(107, 3, 'owner', 'SERVICE_ARCHIVE', '', NULL, NULL, NULL, 'Archived service: AUTO PAINTING (₱2,000.00, 60)', '', NULL, '2025-11-22 13:23:43'),
(108, 3, 'owner', 'SERVICE_ARCHIVE', '', NULL, NULL, NULL, 'Archived service: Aircon Cleaning (₱200.00, 60)', '', NULL, '2025-11-22 13:23:48'),
(109, 3, 'owner', 'SERVICE_ARCHIVE', '', NULL, NULL, NULL, 'Archived service: Tune Up (₱600.00, 60)', '', NULL, '2025-11-22 13:23:50'),
(110, 3, 'owner', 'SERVICE_DELETED', '', NULL, NULL, NULL, 'Deleted service: AUTO PAINTING (₱2,000.00, 60)', '', NULL, '2025-11-22 13:26:59'),
(111, 3, 'owner', 'SERVICE_DELETED', '', NULL, NULL, NULL, 'Deleted service: Aircon Cleaning (₱200.00, 60)', '', NULL, '2025-11-22 13:27:04'),
(112, 3, 'owner', 'SERVICE_DELETED', '', NULL, NULL, NULL, 'Deleted service: Tune Up (₱600.00, 60)', '', NULL, '2025-11-22 13:27:07'),
(113, 3, 'owner', 'SERVICE_DELETED', '', NULL, NULL, NULL, 'Deleted service: Aircon Cleaning (₱200.00, 60)', '', NULL, '2025-11-22 13:27:14'),
(114, 3, 'owner', 'SERVICE_RESTORE', '', NULL, NULL, NULL, 'Restored service: Aircon Cleaning (₱200.00, 60)', '', NULL, '2025-11-22 13:27:16'),
(115, 3, 'owner', 'SERVICE_RESTORE', '', NULL, NULL, NULL, 'Restored service: Tune Up (₱600.00, 60)', '', NULL, '2025-11-22 13:27:19'),
(116, 3, 'owner', 'SERVICE_ARCHIVE', '', NULL, NULL, NULL, 'Archived service: Aircon Cleaning (₱200.00, 60)', '', NULL, '2025-11-22 13:27:23'),
(117, 3, 'owner', 'SERVICE_ARCHIVE', '', NULL, NULL, NULL, 'Archived service: Tune Up (₱600.00, 60)', '', NULL, '2025-11-22 13:27:27'),
(118, 3, 'owner', 'SERVICE_DELETED', '', NULL, NULL, NULL, 'Deleted service: Aircon Cleaning (₱200.00, 60)', '', NULL, '2025-11-22 13:27:30'),
(119, 3, 'owner', 'SERVICE_DELETED', '', NULL, NULL, NULL, 'Deleted service: Tune Up (₱600.00, 60)', '', NULL, '2025-11-22 13:27:33'),
(120, 3, 'owner', 'SERVICE_ADD', '', NULL, NULL, NULL, 'New service added: AIRCON CLEANING (₱750, 60)', '', NULL, '2025-11-22 13:36:52'),
(121, 3, 'owner', 'SERVICE_UPDATE', '', NULL, NULL, NULL, 'Updated service: Aircon Cleaning (₱750.00, 60 mins)', '', NULL, '2025-11-22 13:37:45'),
(122, 3, 'owner', 'SERVICE_ADD', '', NULL, NULL, NULL, 'New service added: Auto Painting (₱30000, 5,760)', '', NULL, '2025-11-22 13:41:32'),
(123, 3, 'owner', 'SERVICE_UPDATE', '', NULL, NULL, NULL, 'Updated service: Auto Painting (₱30000.00, 5,760 mins)', '', NULL, '2025-11-22 13:43:09'),
(124, 3, 'owner', 'SERVICE_ADD', '', NULL, NULL, NULL, 'New service added: Wash Over (₱1000, 30)', '', NULL, '2025-11-22 13:45:47'),
(125, 3, 'owner', 'SERVICE_ARCHIVE', '', NULL, NULL, NULL, 'Archived service: Wash Over (₱1,000.00, 30)', '', NULL, '2025-11-22 13:45:54'),
(126, 3, 'owner', 'SERVICE_DELETED', '', NULL, NULL, NULL, 'Deleted service: Wash Over (₱1,000.00, 30)', '', NULL, '2025-11-22 13:46:01'),
(127, 3, 'owner', 'SERVICE_ADD', '', NULL, NULL, NULL, 'New service added: Wash Over (₱1000, 30)', '', NULL, '2025-11-22 13:46:25'),
(128, 3, 'owner', 'SERVICE_UPDATE', '', NULL, NULL, NULL, 'Updated service: Under Wash (₱1000.00, 30 mins)', '', NULL, '2025-11-22 13:47:10'),
(129, 3, 'owner', 'SERVICE_ADD', '', NULL, NULL, NULL, 'New service added: Engine Tune Up (₱2000, 180)', '', NULL, '2025-11-22 13:49:56'),
(130, 3, 'owner', 'SERVICE_ADD', '', NULL, NULL, NULL, 'New service added: Change Oil (₱800, 180)', '', NULL, '2025-11-22 13:51:29'),
(131, 3, 'owner', 'SERVICE_DELETED', '', NULL, NULL, NULL, 'Deleted service: Tune Up (₱600.00, 60)', '', NULL, '2025-11-22 13:53:10'),
(132, 3, 'owner', 'SERVICE_DELETED', '', NULL, NULL, NULL, 'Deleted service: Tune Up (₱600.00, 60)', '', NULL, '2025-11-22 13:53:14'),
(133, 3, 'owner', 'SERVICE_DELETED', '', NULL, NULL, NULL, 'Deleted service: Tune Up (₱600.00, 60)', '', NULL, '2025-11-22 13:53:17'),
(134, 3, 'owner', 'SERVICE_DELETED', '', NULL, NULL, NULL, 'Deleted service: Tune Up (₱600.00, 60)', '', NULL, '2025-11-22 13:53:33');

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
(53, 14, 'me', '020940', 'me@gmail.com', '2025-11-22 12:33:20');

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
(0, 40, 'jaffef', 600.00, 'proof_691ea44dc89b2.jpg', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-20 05:17:01'),
(0, 42, 'jaffef', 600.00, 'proof_691ea7b090937.jpg', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-20 05:31:28'),
(0, 43, 'ew', 800.00, 'proof_69211fc7c0c85.png', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 02:28:23'),
(0, 44, '13', 200.00, 'proof_69212425d104d.jpg', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 02:47:01'),
(0, 46, 's', 600.00, 'proof_69212a8cc46ed.jpg', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 03:14:20'),
(0, 48, 'w', 400.00, 'proof_69212e194589e.jpg', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 03:29:29'),
(0, 49, 's', 800.00, 'proof_69212fa62a1e3.png', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 03:36:06'),
(0, 50, 'sss', 800.00, 'proof_6921327dc21c7.png', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 03:48:13'),
(0, 51, 'w', 800.00, 'proof_6921364d9520c.png', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 04:04:29'),
(0, 52, 'body', 800.00, 'proof_692136b99a593.png', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 04:06:17'),
(0, 53, 'sssssss', 1000.00, 'proof_69213748aabc4.jpg', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 04:08:40'),
(0, 54, 'kite', 400.00, 'proof_6921381aacdf4.jpg', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 04:12:10'),
(0, 55, 'ew', 800.00, 'proof_69213a3acf5df.png', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 04:21:14'),
(0, 57, 'wqe', 1000.00, 'proof_6921636675a5a.png', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 07:16:54'),
(0, 59, 'baron', 800.00, 'proof_6921664036379.jpg', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 07:29:04'),
(0, 60, 'bzaron', 1000.00, 'proof_6921669c1d8e1.png', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 07:30:36'),
(0, 5, 'love', 800.00, 'proof_6921ab4e7f0e8.jpg', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 12:23:42'),
(0, 6, 'memyself', 800.00, 'proof_6921ad9b0598d.png', 'verified', 3, '2025-11-22 12:33:54', '', '2025-11-22 12:33:31');

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
(1, 48, 'sd', 'd', '234', '2025-12-03', '16:30:00', '17:30:00', 'Walk-In', 'Pending', '2025-11-22 07:47:08', 1),
(2, 49, 'sd', 'd', '234', '2025-12-03', '16:30:00', '17:30:00', 'Walk-In', 'Pending', '2025-11-22 07:48:06', 1),
(3, 51, 'HII', 'HIII', '29003', '2025-12-05', '07:30:00', '09:30:00', 'Walk-In', 'approved', '2025-11-22 12:02:47', 0),
(4, 52, 'ds', 's', '213', '2025-12-03', '14:00:00', '16:00:00', 'Walk-In', 'approved', '2025-11-22 12:04:48', 1),
(5, 40, 'sa', 'love', '2004', '2025-12-04', '18:00:00', '20:00:00', 'Online', 'confirmed', '2025-11-22 12:23:42', 0),
(6, 53, 'me', 'myself', '0342', '2025-12-06', '16:30:00', '18:30:00', 'Online', 'approved', '2025-11-22 12:33:31', 0);

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
(94, 6, 17);

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
(23, 'Auto Painting', 'Application of fresh automotive paint to restore color, improve appearance, or repair paint damage.', '5,760', 30000.00, 'svc_6921bd8c63b045.05306007.jpg', 0),
(25, 'Under Wash', 'Cleaning of the vehicle’s undercarriage to remove dirt, mud, and debris that can cause rust and damage.', '30', 1000.00, 'svc_6921beb1ed4a46.85683860.jpg', 0),
(26, 'Engine Tune Up', 'Adjustment and replacement of key engine components to improve fuel efficiency, performance, and reliability.', '180', 2000.00, 'svc_6921bf848a8ee5.58582476.jpg', 0),
(27, 'Change Oil', 'Replacement of old engine oil and oil filter to protect the engine and maintain smooth, efficient operation.', '180', 800.00, 'svc_6921bfe19bc0b8.07869680.jpg', 0);

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
(3, 'kristine', 'rci.bsis.buenaflorkristinemae@gmail.com', '$2y$10$Ma7InTJAMxyJM2VfK3.xlOJVoKQL/495Unj4C.H0Y9Io8besdc/Pm', NULL, '2025-10-09 10:39:08'),
(5, 'lloyd', 'johnlloydbuenaflor12@gmail.com', '$2y$10$SR8oLWxLYZQgOZJw/Q.mA.UHczixi9krqxbyY5xaWaPLnYLlRCCWG', NULL, '2025-10-11 08:07:11'),
(6, 'test', 'test@gmail.com', '$2y$10$uYgOAJmMZmxTwizM0LZsr.nOJKLkp5OhDYg.Gg0l/1RWwwCGrMoli', NULL, '2025-11-20 04:32:16'),
(7, 'rets', 'ritzdelaisla@gmail.com', '$2y$10$HdVQ0yTVQwIHA5eUwftwf.e5ngIOTu.buOq8ThujyzWo7WXFhtjey', NULL, '2025-11-20 06:33:41'),
(8, 'me', 'me@gmail.com', '$2y$10$WcJ4mmyAkq6ItCVH.PJziern2U3NP2k1mtRmcx8OM4B6u00oH5Ll2', NULL, '2025-11-21 15:22:52'),
(11, 'meow', 'meow@gmail.com', '$2y$10$yDD7szTVMeulOOZSqvUX0O7ZJhoWOOW2KIsRAEP0cSp5GTroMoAmK', NULL, '2025-11-21 15:47:20'),
(12, 'kite', 'kite@gmail.com', '$2y$10$q7ofHCT8MCN.zrQc8UwzpOWKpBOGJQ7yMKf2/lufjp9/Z0W/Drkse', NULL, '2025-11-22 04:11:11'),
(13, 'baron', 'baron@gmail.com', '$2y$10$XVHGz88LNoSs1a.xFfoKD.Tw4/qZt.0MqcrJo4wXzagyRws.FPls2', NULL, '2025-11-22 07:28:16'),
(14, 'memyself', 'memyself@gmail.com', '$2y$10$wxKANaFGKU5InWskyVshRees8YMCRQdeLvyadfVNRH.gSavrVe7ge', NULL, '2025-11-22 12:32:15');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=135;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `reservation_services`
--
ALTER TABLE `reservation_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `reservation_services`
--
ALTER TABLE `reservation_services`
  ADD CONSTRAINT `reservation_services_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`),
  ADD CONSTRAINT `reservation_services_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
