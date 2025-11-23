-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 16, 2025 at 01:06 PM
-- Server version: 12.0.2-MariaDB
-- PHP Version: 8.2.12

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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
  `admin_username` varchar(100) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `action_type` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `audit_trail`
--

INSERT INTO `audit_trail` (`id`, `admin_id`, `admin_username`, `timestamp`, `action_type`, `description`, `created_at`) VALUES
(100, 3, 'owner', '2025-10-16 01:01:14', 'PAYMENT_VERIFIED', 'Admin \'owner\' verified payment #26.', '2025-10-16 01:01:14'),
(101, 3, 'owner', '2025-10-16 01:01:43', 'WALKIN_ADDED', 'Walk-in reservation added by owner for customer: Johnlloyd Buenaflor (Honda GTR, 2025)', '2025-10-16 01:01:43'),
(102, 3, 'owner', '2025-10-16 01:06:22', 'SERVICE_ADD', 'New service added: Tune Up (₱12, 60 mins)', '2025-10-16 01:06:22'),
(103, 3, 'owner', '2025-10-16 01:07:15', 'SERVICE_DELETE', 'Deleted service: Tune Up (₱700.00, 60)', '2025-10-16 01:07:15'),
(104, 3, 'owner', '2025-10-16 01:08:42', 'SERVICE_DELETE', 'Deleted service: porkchop (₱50,000.00, 60)', '2025-10-16 01:08:42'),
(105, 3, 'owner', '2025-10-16 01:09:30', 'SERVICE_DELETE', 'Deleted service: wtwet (₱23,242,352.00, 30)', '2025-10-16 01:09:30'),
(106, 3, 'owner', '2025-10-16 01:10:13', 'SERVICE_DELETE', 'Deleted service: Tune Up (₱12.00, 60)', '2025-10-16 01:10:13'),
(107, 3, 'owner', '2025-10-16 01:10:34', 'SERVICE_ADD', 'New service added: Tune Up (₱500, 60 mins)', '2025-10-16 01:10:34'),
(108, 3, 'owner', '2025-10-16 01:11:58', 'SERVICE_DELETE', 'Deleted service: Tune Up (₱500.00, 60)', '2025-10-16 01:11:58'),
(109, 3, 'owner', '2025-10-16 01:12:32', 'SERVICE_ADD', 'New service added: Tune Up (₱500, 60 mins)', '2025-10-16 01:12:32'),
(110, 3, 'owner', '2025-10-16 01:15:03', 'RESERVATION_DELETE', 'Reservation #36 (Johnlloyd Buenaflor - Honda GTR) scheduled on 2025-10-16 at 13:30:00 was deleted by admin \'owner\'.', '2025-10-16 01:15:03'),
(111, 3, 'owner', '2025-10-16 01:15:10', 'RESERVATION_DELETE', 'Reservation #37 (Johnlloyd Buenaflor - Honda GTR) scheduled on 2025-10-16 at 08:00:00 was deleted by admin \'owner\'.', '2025-10-16 01:15:10'),
(112, 3, 'owner', '2025-10-16 01:15:56', 'PAYMENT_VERIFIED', 'Admin \'owner\' verified payment #27.', '2025-10-16 01:15:56'),
(113, 3, 'owner', '2025-10-16 01:20:22', 'PAYMENT_VERIFIED', 'Admin \'owner\' verified payment #27.', '2025-10-16 01:20:22'),
(114, 3, 'owner', '2025-10-16 01:22:07', 'PAYMENT_VERIFIED', 'Admin \'owner\' verified payment #27.', '2025-10-16 01:22:07'),
(115, 3, 'owner', '2025-10-16 01:22:38', 'PAYMENT_VERIFIED', 'Admin \'owner\' verified payment #27.', '2025-10-16 01:22:38'),
(116, 3, 'owner', '2025-10-16 01:23:22', 'PAYMENT_REJECTED', 'Admin \'owner\' rejected payment #27.', '2025-10-16 01:23:22'),
(119, 3, 'owner', '2025-10-16 01:27:23', 'PAYMENT_REJECTED', 'Admin \'owner\' rejected payment #27.', '2025-10-16 01:27:23'),
(120, 3, 'owner', '2025-10-16 01:44:55', 'SERVICE_UPDATE', 'Updated service: Tune Up (₱500.00, 60 mins)', '2025-10-16 01:44:55'),
(121, 3, 'owner', '2025-10-16 01:45:01', 'SERVICE_UPDATE', 'Updated service: Tune Up (₱600.00, 60 mins)', '2025-10-16 01:45:01'),
(122, 3, 'owner', '2025-10-16 01:47:20', 'SERVICE_UPDATE', 'Updated service: Tune Up (₱500.00, 60 mins)', '2025-10-16 01:47:20'),
(123, 3, 'owner', '2025-10-16 01:47:30', 'SERVICE_UPDATE', 'Updated service: Tune Up (₱600.00, 60 mins)', '2025-10-16 01:47:30'),
(124, 3, 'owner', '2025-10-16 01:49:16', 'USER_LOGIN', 'User logged in: owner', '2025-10-16 01:49:16'),
(125, 3, 'owner', '2025-10-16 01:56:10', 'USER_LOGIN', 'User logged in: owner', '2025-10-16 01:56:10'),
(126, 3, 'owner', '2025-10-16 02:00:54', 'USER_LOGIN', 'User logged in: owner', '2025-10-16 02:00:54'),
(127, 3, 'owner', '2025-10-16 02:02:26', 'USER_LOGOUT', 'Admin \'owner\' logged out.', '2025-10-16 02:02:26'),
(128, 3, 'owner', '2025-10-16 02:02:31', 'USER_LOGIN', 'User logged in: owner', '2025-10-16 02:02:31'),
(129, 3, 'owner', '2025-10-16 02:02:55', 'USER_LOGOUT', ' \'owner\' logged out.', '2025-10-16 02:02:55'),
(130, 3, 'owner', '2025-10-16 02:02:58', 'USER_LOGIN', 'User logged in: owner', '2025-10-16 02:02:58'),
(131, 3, 'owner', '2025-10-16 02:03:05', 'USER_LOGOUT', ' \'owner\' logged out.', '2025-10-16 02:03:05'),
(132, 4, 'admin', '2025-10-16 02:03:09', 'USER_LOGIN', 'User logged in: admin', '2025-10-16 02:03:09'),
(133, 4, 'admin', '2025-10-16 02:03:12', 'USER_LOGOUT', ' \'admin\' logged out.', '2025-10-16 02:03:12'),
(134, 3, 'owner', '2025-10-16 02:03:15', 'USER_LOGIN', 'User logged in: owner', '2025-10-16 02:03:15'),
(135, 3, 'owner', '2025-10-16 02:03:30', 'USER_LOGOUT', ' \'owner\' logged out.', '2025-10-16 02:03:30'),
(136, 3, 'owner', '2025-10-16 02:05:57', 'USER_LOGIN', 'User logged in: owner', '2025-10-16 02:05:57'),
(137, 3, 'owner', '2025-10-16 02:07:32', 'USER_LOGOUT', ' \'owner\' logged out.', '2025-10-16 02:07:32'),
(138, 3, 'owner', '2025-10-16 02:07:35', 'USER_LOGIN', 'User logged in: owner', '2025-10-16 02:07:35'),
(139, 3, 'owner', '2025-10-16 02:08:07', 'USER_LOGOUT', ' \'owner\' logged out.', '2025-10-16 02:08:07'),
(140, 3, 'owner', '2025-10-16 02:08:36', 'USER_LOGIN', 'User logged in: owner', '2025-10-16 02:08:36');

-- --------------------------------------------------------

--
-- Table structure for table `chat_history`
--

CREATE TABLE `chat_history` (
  `id` int(11) NOT NULL,
  `user_message` text NOT NULL,
  `bot_reply` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_history`
--

INSERT INTO `chat_history` (`id`, `user_message`, `bot_reply`, `created_at`) VALUES
(1, 'hi', 'I’m learning! I don’t know the answer yet.', '2025-08-25 09:31:42'),
(2, 'hello', 'I’m learning! I don’t know the answer yet.', '2025-08-25 09:31:47'),
(3, 'hi', 'I’m learning! I don’t know the answer yet.', '2025-08-25 09:31:49'),
(4, 'hello', 'I’m learning! I don’t know the answer yet.', '2025-08-25 09:38:38'),
(5, 'hi', 'I’m learning! I don’t know the answer yet.', '2025-08-25 09:38:41'),
(6, 'hello', 'I’m learning! I don’t know the answer yet.', '2025-08-25 09:38:43'),
(7, 'Hello', 'I’m learning! I don’t know the answer yet.', '2025-08-25 09:38:49'),
(8, 'hi', 'I’m learning! I don’t know the answer yet.', '2025-08-25 09:40:01'),
(9, 'hello', 'I’m learning! I don’t know the answer yet.', '2025-08-25 09:40:06'),
(10, 'hi', 'I’m learning! I don’t know the answer yet.', '2025-08-25 09:40:08'),
(11, 'hello', 'hi', '2025-08-25 09:40:43'),
(12, 'hi', 'hello', '2025-08-25 09:40:44'),
(13, 'haha', 'hello', '2025-08-25 09:41:00'),
(14, 'hahahaha', 'I’m learning! I don’t know the answer yet.', '2025-08-25 09:41:03'),
(15, 'haha', 'hello', '2025-08-25 09:41:05'),
(16, 'hehe', 'hello', '2025-08-25 09:41:07'),
(17, 'hihi', 'hello', '2025-08-25 09:41:09'),
(18, 'hello', 'hi', '2025-08-25 09:41:12'),
(19, 'high', 'hello', '2025-08-25 09:41:15'),
(20, 'hi', 'hello', '2025-08-25 09:43:26'),
(21, 'hi', 'hello', '2025-08-25 09:43:27'),
(22, 'hi', 'hello', '2025-08-25 09:43:28'),
(23, 'hi', 'hello', '2025-08-25 09:43:28'),
(24, 'hi', 'hello', '2025-08-25 09:43:29'),
(25, 'hi', 'hello', '2025-08-25 09:43:29'),
(26, 'hi', 'hello', '2025-08-25 09:43:29'),
(27, 'hi', 'hello', '2025-08-25 09:43:29'),
(28, 'hi', 'hello', '2025-08-25 09:43:30'),
(29, 'yes?', 'I’m learning! I don’t know the answer yet.', '2025-08-25 09:43:32'),
(30, 'hello', 'hi', '2025-10-06 02:43:27'),
(31, 'im john lloyd', 'I understand you\'re having vehicle issues. To provide the best recommendation, I\'d need to know more about your specific problem. You can describe the symptoms, or I can recommend our **General Diagnostics** to identify the issue. What specific problem are you experiencing?', '2025-10-06 02:43:34'),
(32, 'My engine is making a strange noise', 'Based on your description, I recommend:\n\n• **Engine Diagnostics** - to identify the source of the noise\n• **Transmission Diagnostics** - to identify transmission issues\n• **Suspension Inspection** - to check for worn components\n\nWould you like to schedule an appointment for any of these services?', '2025-10-06 03:37:12');

-- --------------------------------------------------------

--
-- Table structure for table `chat_knowledge`
--

CREATE TABLE `chat_knowledge` (
  `id` int(11) NOT NULL,
  `trigger1` text NOT NULL,
  `response` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_knowledge`
--

INSERT INTO `chat_knowledge` (`id`, `trigger1`, `response`) VALUES
(1, 'hi', 'hello'),
(2, 'hello', 'hi'),
(3, 'hahahaha', 'I’m learning! I don’t know the answer yet.'),
(4, 'hi', 'hell'),
(5, 'hi', 'yes?'),
(6, 'yes?', 'I’m learning! I don’t know the answer yet.');

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
(37, NULL, 'Johnlloyd Buenaflor', '09070220027', 'johnlloydbuenaflor19@gmail.com', '2025-10-15 17:01:43');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `reservation_id`, `account_name`, `amount_paid`, `payment_proof`, `payment_status`, `verified_by`, `verified_at`, `notes`, `created_at`) VALUES
(27, 38, 'johnlloyd buenaflor', 500.00, 'proof_68efd6bb92a90.jpg', 'rejected', 3, '2025-10-15 17:27:23', 'aaa', '2025-10-15 17:15:39');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `customer_id`, `vehicle_make`, `vehicle_model`, `vehicle_year`, `reservation_date`, `reservation_time`, `end_time`, `method`, `status`, `created_at`) VALUES
(38, 36, 'Honda', 'GTR', '2025', '2025-10-16', '16:00:00', '17:00:00', 'Online', 'confirmed', '2025-10-15 17:15:39');

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
(41, 38, 16);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `service_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `service_name`, `description`, `duration`, `price`, `photo`) VALUES
(16, 'Tune Up', 'Tune up only', '60', 600.00, 'svc_68efd600577e02.00741128.jpg');

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
(5, 'lloyd', 'johnlloydbuenaflor12@gmail.com', '$2y$10$SR8oLWxLYZQgOZJw/Q.mA.UHczixi9krqxbyY5xaWaPLnYLlRCCWG', NULL, '2025-10-11 08:07:11');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_history`
--
ALTER TABLE `chat_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_knowledge`
--
ALTER TABLE `chat_knowledge`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reservation_id` (`reservation_id`),
  ADD KEY `payment_status` (`payment_status`);

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
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- AUTO_INCREMENT for table `chat_history`
--
ALTER TABLE `chat_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `chat_knowledge`
--
ALTER TABLE `chat_knowledge`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `reservation_services`
--
ALTER TABLE `reservation_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
