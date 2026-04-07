-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 07, 2026 at 09:18 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `itcprs_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int UNSIGNED NOT NULL,
  `contract_number` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique contract reference e.g. CTR-2026-001',
  `client_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Client / plant name e.g. B-Meg Tarlac',
  `destination` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Delivery destination e.g. Tarlac, Pangasinan',
  `origin` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Bataan' COMMENT 'Pickup origin — almost always Bataan',
  `truck_id` int UNSIGNED DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `rate_per_trip` decimal(10,2) DEFAULT NULL COMMENT 'Agreed rate per delivery trip (PHP)',
  `status` enum('active','expiring','expired','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int UNSIGNED DEFAULT NULL COMMENT 'FK → users.id (admin or secretary)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Delivery contracts — ITCPRS';

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int UNSIGNED NOT NULL,
  `part_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descriptive name e.g. Rear Tire 700R',
  `part_number` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'OEM or supplier part number / SKU',
  `category` enum('tires','batteries','engine','transmission','electrical','brakes','suspension','filters','fluids','body','tools','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `unit` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pcs' COMMENT 'Unit of measure: pcs, liters, sets, rolls…',
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Current stock on hand',
  `reorder_point` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Trigger low-stock alert when qty falls to this',
  `unit_cost` decimal(10,2) DEFAULT NULL COMMENT 'Average unit purchase cost (PHP)',
  `supplier` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Shelf / bin location in garage',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Garage spare parts inventory — ITCPRS';

-- --------------------------------------------------------

--
-- Table structure for table `parts_requests`
--

CREATE TABLE `parts_requests` (
  `id` int UNSIGNED NOT NULL,
  `part_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pcs',
  `inventory_item_id` int UNSIGNED DEFAULT NULL,
  `truck_id` int UNSIGNED DEFAULT NULL,
  `requested_by` int UNSIGNED DEFAULT NULL COMMENT 'FK → users.id',
  `resolved_by` int UNSIGNED DEFAULT NULL COMMENT 'FK → users.id (admin/secretary/staff)',
  `resolved_at` datetime DEFAULT NULL,
  `urgency` enum('low','normal','urgent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `status` enum('pending','sourcing','ordered','installed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Truck parts requests — ITCPRS';

-- --------------------------------------------------------

--
-- Table structure for table `trucks`
--

CREATE TABLE `trucks` (
  `id` int UNSIGNED NOT NULL,
  `plate_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Stored uppercase, unique',
  `make` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g. Isuzu, Hino, Fuso',
  `model` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g. Elf NHR, 500 Series',
  `year` smallint DEFAULT NULL,
  `color` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chassis_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Used by garage custodian for parts tracking',
  `engine_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity_tons` decimal(6,2) DEFAULT NULL COMMENT 'Payload in metric tons',
  `status` enum('active','inactive','repair') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `photo_plate` varchar(260) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Filename of plate number photo — stored in /assets/uploads/trucks/',
  `photo_truck` varchar(260) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Filename of truck photo — stored in /assets/uploads/trucks/',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fleet trucks — ITCPRS';

-- --------------------------------------------------------

--
-- Table structure for table `truck_drivers`
--

CREATE TABLE `truck_drivers` (
  `id` int UNSIGNED NOT NULL,
  `truck_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'FK → users.id, must have role = driver',
  `position` enum('driver','helper') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Driver + helper crew assignments per truck';

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo` varchar(260) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Profile photo filename — stored in /assets/uploads/drivers/',
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','secretary','staff','driver') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'driver',
  `status` enum('active','inactive','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `reset_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `email`, `contact_number`, `photo`, `password_hash`, `role`, `status`, `reset_token`, `reset_expires`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'System Administrator', 'admin', 'admin@itcprs.local', '09000000000', NULL, '$2y$10$rrKjSJ7LCHsZ2sKCiT1mHOuRUiZ/W2F/1N6Ru0NZ6YPC6G6sF141W', 'admin', 'active', NULL, NULL, '2026-03-11 13:57:58', '2026-04-07 16:52:55', '2026-04-07 16:52:55'),
(2, 'John Leee', 'jonleemagsaysay', 'juan7@gmail.com', '09000000000', NULL, '$2y$10$rrKjSJ7LCHsZ2sKCiT1mHOuRUiZ/W2F/1N6Ru0NZ6YPC6G6sF141W', 'driver', 'active', NULL, NULL, '2026-03-12 10:36:43', '2026-04-07 16:52:21', '2026-04-07 16:52:21'),
(3, 'Juana Dela cruz', 'juana', '', '', NULL, '', 'driver', 'active', NULL, NULL, '2026-03-19 20:42:55', '2026-04-06 19:02:29', NULL),
(5, 'Maria Santos', 'mariasantos', 'mariasantos@gmail.com', '', NULL, '$2y$12$gDcwATuo7Z/eNCq4hITYWOBLrDsYly0Pnz1AitaH9qx79BG3w6QJK', 'staff', 'active', NULL, NULL, '2026-04-07 17:14:02', '2026-04-07 17:14:23', '2026-04-07 17:14:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_contract_number` (`contract_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_end_date` (`end_date`),
  ADD KEY `idx_truck` (`truck_id`),
  ADD KEY `fk_contract_creator` (`created_by`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_part_name` (`part_name`(50));

--
-- Indexes for table `parts_requests`
--
ALTER TABLE `parts_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_urgency` (`urgency`),
  ADD KEY `idx_truck` (`truck_id`),
  ADD KEY `idx_requested` (`requested_by`),
  ADD KEY `fk_pr_resolver` (`resolved_by`),
  ADD KEY `fk_pr_inventory` (`inventory_item_id`);

--
-- Indexes for table `trucks`
--
ALTER TABLE `trucks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_plate` (`plate_number`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `truck_drivers`
--
ALTER TABLE `truck_drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_truck_position` (`truck_id`,`position`),
  ADD UNIQUE KEY `uq_driver_assignment` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parts_requests`
--
ALTER TABLE `parts_requests`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trucks`
--
ALTER TABLE `trucks`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `truck_drivers`
--
ALTER TABLE `truck_drivers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `fk_contract_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contract_truck` FOREIGN KEY (`truck_id`) REFERENCES `trucks` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `parts_requests`
--
ALTER TABLE `parts_requests`
  ADD CONSTRAINT `fk_pr_inventory` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pr_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pr_resolver` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pr_truck` FOREIGN KEY (`truck_id`) REFERENCES `trucks` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `truck_drivers`
--
ALTER TABLE `truck_drivers`
  ADD CONSTRAINT `fk_td_truck` FOREIGN KEY (`truck_id`) REFERENCES `trucks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_td_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
