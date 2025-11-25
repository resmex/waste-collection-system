-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 24, 2025 at 07:19 PM
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
-- Database: `wms`
--

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `driver_assigned_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`id`, `request_id`, `owner_id`, `driver_id`, `vehicle_id`, `assigned_by`, `assigned_at`, `driver_assigned_at`) VALUES
(1, 2, 4, 5, NULL, 2, '2025-11-23 07:02:03', '2025-11-23 10:02:35'),
(2, 1, 4, NULL, NULL, 2, '2025-11-23 20:55:40', NULL),
(3, 3, 4, 5, NULL, 2, '2025-11-23 21:01:48', '2025-11-24 00:02:32'),
(4, 4, 4, 5, NULL, 2, '2025-11-24 07:14:22', '2025-11-24 10:15:13'),
(5, 5, 4, 5, NULL, 2, '2025-11-24 11:58:39', '2025-11-24 15:54:36');

-- --------------------------------------------------------

--
-- Table structure for table `driver_status`
--

CREATE TABLE `driver_status` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `last_lat` decimal(10,7) DEFAULT NULL,
  `last_lng` decimal(10,7) DEFAULT NULL,
  `last_seen_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `driver_status`
--

INSERT INTO `driver_status` (`id`, `driver_id`, `is_available`, `last_lat`, `last_lng`, `last_seen_at`) VALUES
(1, 5, 1, -6.7176354, 39.1627692, '2025-11-24 20:28:22');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `request_id`, `resident_id`, `driver_id`, `rating`, `comments`, `created_at`) VALUES
(1, 5, 3, 5, 4, 'cool', '2025-11-24 12:56:29');

-- --------------------------------------------------------

--
-- Table structure for table `live_locations`
--

CREATE TABLE `live_locations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('resident','driver') NOT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `live_locations`
--

INSERT INTO `live_locations` (`id`, `user_id`, `role`, `lat`, `lng`, `updated_at`) VALUES
(1, 5, 'driver', -6.7176354, 39.1627692, '2025-11-24 17:28:22'),
(13, 3, 'resident', -6.7176354, 39.1627692, '2025-11-24 13:00:09'),
(38, 1, 'resident', -6.7176354, 39.1627692, '2025-11-23 19:35:17');

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `ward_id` int(11) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `events` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`events`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`id`, `resident_id`, `ward_id`, `address`, `lat`, `lng`, `status`, `created_at`, `events`) VALUES
(1, 1, 3, '', -6.8140000, 39.2880000, 'pending', '2025-11-22 22:57:43', NULL),
(2, 3, 3, '', -6.8160000, 39.2800000, 'completed', '2025-11-23 07:01:07', NULL),
(3, 3, 3, '', -6.8160000, 39.2800000, 'completed', '2025-11-23 21:01:02', NULL),
(4, 3, 48, 'Goba, Ubungo Municipal, Dar es-Salaam, Coastal Zone, Tanzania', -6.7176354, 39.1627692, 'completed', '2025-11-24 07:13:41', NULL),
(5, 3, 34, 'Goba, Ubungo Municipal, Dar es-Salaam, Coastal Zone, Tanzania', -6.7176354, 39.1627692, 'completed', '2025-11-24 11:27:07', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `trip_status`
--

CREATE TABLE `trip_status` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','completed') NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('resident','driver','owner','councillor','municipal_admin','administrator') NOT NULL DEFAULT 'resident',
  `ward_id` int(11) DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `profile_pic`, `phone`, `password_hash`, `role`, `ward_id`, `owner_id`, `status`, `created_at`) VALUES
(1, 'System Admin', 'admin@ewaste.com', NULL, '0712345678', '$2y$10$wXq9E4R3o8btzveJrhq.I.yRf/XBKr3CgQoElZb1inw2Uo8fBOmzm', 'administrator', NULL, NULL, 'active', '2025-11-22 22:56:37'),
(2, 'Municipal Admin', 'municipal@ewaste.com', NULL, '0612345678', '$2y$10$qI9qBRgKSAIdwqlZyhfGeOPl6c4hxLzHVDneQsZLUXMhHiCowxu1a', 'municipal_admin', NULL, NULL, 'active', '2025-11-22 23:01:46'),
(3, 'Resident', 'resident@ewaste.com', NULL, '0722345678', '$2y$10$D9b.rNkQiccqgF5LIWQ3Gux4lAKCG9g0CfyFM2gVEvRzIFIXWAkwu', 'resident', NULL, NULL, 'active', '2025-11-23 06:55:30'),
(4, 'Owner', 'owner@ewaste.com', NULL, '0732345678', '$2y$10$zDyJjhJYVGRISDmyZWl1F.0QdvSwBJZx1bvcAxELJe1gkzPCP5UyC', 'owner', NULL, NULL, 'active', '2025-11-23 06:59:09'),
(5, 'Driver', 'driver@ewaste.com', NULL, '0742345678', '$2y$10$mhCH6fraPLKSWeYBWFPEOOu5E8mRxKIh37c7/hqr331RXdP1chXHi', 'driver', NULL, 4, 'active', '2025-11-23 07:00:37'),
(6, 'Councillor', 'councillor@ewaste.com', NULL, '0762345678', '$2y$10$C4.60IH2zENg54CU78Jh8OVCpt67LK9w3DqB5/Kqdtc77nxX1jMim', 'councillor', NULL, NULL, 'active', '2025-11-24 07:18:56');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `plate_no` varchar(32) NOT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `capacity_kg` int(11) DEFAULT NULL,
  `doc_url` varchar(255) DEFAULT NULL,
  `vehicle_pic` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `owner_id`, `plate_no`, `vehicle_type`, `capacity_kg`, `doc_url`, `vehicle_pic`, `status`, `rejection_reason`, `created_at`) VALUES
(1, 4, 'T1123', NULL, 0, 'uploads/doc/veh_4_1763989778_21d851.png', NULL, 'approved', NULL, '2025-11-24 13:09:38');

-- --------------------------------------------------------

--
-- Table structure for table `wards`
--

CREATE TABLE `wards` (
  `id` int(11) NOT NULL,
  `region` varchar(50) NOT NULL DEFAULT 'Dar es Salaam',
  `municipal` varchar(50) NOT NULL,
  `ward_name` varchar(100) NOT NULL,
  `lat` double DEFAULT NULL,
  `lng` double DEFAULT NULL,
  `min_lat` double DEFAULT NULL,
  `max_lat` double DEFAULT NULL,
  `min_lng` double DEFAULT NULL,
  `max_lng` double DEFAULT NULL,
  `level4_pcode` varchar(20) DEFAULT NULL,
  `level3_pcode` varchar(20) DEFAULT NULL,
  `level4_name_api` varchar(100) DEFAULT NULL,
  `level3_name_api` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wards`
--

INSERT INTO `wards` (`id`, `region`, `municipal`, `ward_name`, `lat`, `lng`, `min_lat`, `max_lat`, `min_lng`, `max_lng`, `level4_pcode`, `level3_pcode`, `level4_name_api`, `level3_name_api`) VALUES
(1, 'Dar es Salaam', 'Ilala', 'Buguruni', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'Dar es Salaam', 'Ilala', 'Kariakoo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'Dar es Salaam', 'Ilala', 'Kivukoni', NULL, NULL, -6.84, -6.8, 39.26, 39.32, NULL, NULL, NULL, NULL),
(4, 'Dar es Salaam', 'Ilala', 'Tabata', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'Dar es Salaam', 'Ilala', 'Gongo la Mboto', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'Dar es Salaam', 'Ilala', 'Pugu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'Dar es Salaam', 'Ilala', 'Ukonga', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'Dar es Salaam', 'Ilala', 'Segerea', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'Dar es Salaam', 'Ilala', 'Kipawa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 'Dar es Salaam', 'Ilala', 'Msongola', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'Dar es Salaam', 'Kinondoni', 'Kawe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'Dar es Salaam', 'Kinondoni', 'Msasani', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(13, 'Dar es Salaam', 'Kinondoni', 'Kijitonyama', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(14, 'Dar es Salaam', 'Kinondoni', 'Kinondoni', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'Dar es Salaam', 'Kinondoni', 'Magomeni', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 'Dar es Salaam', 'Kinondoni', 'Tandale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'Dar es Salaam', 'Kinondoni', 'Mabibo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 'Dar es Salaam', 'Kinondoni', 'Mbezi Juu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'Dar es Salaam', 'Kinondoni', 'Mabwepande', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 'Dar es Salaam', 'Kinondoni', 'Kunduchi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 'Dar es Salaam', 'Temeke', 'Mbagala', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 'Dar es Salaam', 'Temeke', 'Mbagala Kuu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 'Dar es Salaam', 'Temeke', 'Chamazi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(24, 'Dar es Salaam', 'Temeke', 'Kurasini', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(25, 'Dar es Salaam', 'Temeke', 'Buza', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(26, 'Dar es Salaam', 'Temeke', 'Keko', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(27, 'Dar es Salaam', 'Temeke', 'Temeke', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(28, 'Dar es Salaam', 'Temeke', 'Kijichi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(29, 'Dar es Salaam', 'Temeke', 'Toangoma', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(30, 'Dar es Salaam', 'Temeke', 'Yombo Vituka', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(31, 'Dar es Salaam', 'Ubungo', 'Kimara', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(32, 'Dar es Salaam', 'Ubungo', 'Manzese', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(33, 'Dar es Salaam', 'Ubungo', 'Mabibo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(34, 'Dar es Salaam', 'Ubungo', 'Goba', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(35, 'Dar es Salaam', 'Ubungo', 'Kibamba', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(36, 'Dar es Salaam', 'Ubungo', 'Mbezi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(37, 'Dar es Salaam', 'Ubungo', 'Makuburi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(38, 'Dar es Salaam', 'Ubungo', 'Msigani', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(39, 'Dar es Salaam', 'Ubungo', 'Sinza', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(40, 'Dar es Salaam', 'Ubungo', 'Ubungo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(41, 'Dar es Salaam', 'Kigamboni', 'Kibada', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(42, 'Dar es Salaam', 'Kigamboni', 'Kigamboni', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(43, 'Dar es Salaam', 'Kigamboni', 'Mjimwema', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(44, 'Dar es Salaam', 'Kigamboni', 'Vijibweni', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(45, 'Dar es Salaam', 'Kigamboni', 'Kimbiji', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(46, 'Dar es Salaam', 'Kigamboni', 'Somangila', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(47, 'Dar es Salaam', 'Kigamboni', 'Tungi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(48, 'Dar es Salaam', 'Kigamboni', 'Kisarawe II', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_request` (`request_id`),
  ADD KEY `idx_owner` (`owner_id`),
  ADD KEY `idx_driver` (`driver_id`);

--
-- Indexes for table `driver_status`
--
ALTER TABLE `driver_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_req` (`request_id`),
  ADD KEY `idx_driver` (`driver_id`),
  ADD KEY `fk_fb_resident` (`resident_id`);

--
-- Indexes for table `live_locations`
--
ALTER TABLE `live_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_role` (`user_id`,`role`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resident` (`resident_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_ward` (`ward_id`);

--
-- Indexes for table `trip_status`
--
ALTER TABLE `trip_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_req` (`request_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_ts_driver` (`driver_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_ward` (`ward_id`),
  ADD KEY `idx_owner` (`owner_id`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_no` (`plate_no`),
  ADD KEY `idx_owner` (`owner_id`);

--
-- Indexes for table `wards`
--
ALTER TABLE `wards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ward` (`municipal`,`ward_name`),
  ADD UNIQUE KEY `uq_ward_code` (`level4_pcode`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `driver_status`
--
ALTER TABLE `driver_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `live_locations`
--
ALTER TABLE `live_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=162;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `trip_status`
--
ALTER TABLE `trip_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `wards`
--
ALTER TABLE `wards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `fk_assign_driver` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_assign_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_assign_req` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `driver_status`
--
ALTER TABLE `driver_status`
  ADD CONSTRAINT `driver_status_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_fb_driver` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fb_req` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fb_resident` FOREIGN KEY (`resident_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `live_locations`
--
ALTER TABLE `live_locations`
  ADD CONSTRAINT `fk_live_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `fk_request_resident` FOREIGN KEY (`resident_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_request_ward` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`);

--
-- Constraints for table `trip_status`
--
ALTER TABLE `trip_status`
  ADD CONSTRAINT `fk_ts_driver` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ts_req` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_ward` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`);

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `fk_vehicle_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
