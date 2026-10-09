-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 28, 2026 at 08:52 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aetheryx_db_new`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `entity_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `equipment_id` int UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`id`, `user_id`, `equipment_id`, `quantity`, `created_at`, `updated_at`) VALUES
(4, 13, 15, 2, '2026-09-16 03:42:56', '2026-09-16 03:43:03'),
(13, 14, 1, 2, '2026-09-23 10:35:09', '2026-09-23 10:35:11'),
(14, 14, 2, 1, '2026-09-23 10:35:12', '2026-09-23 10:35:12'),
(15, 14, 3, 1, '2026-09-23 10:35:13', '2026-09-23 10:35:13'),
(16, 14, 4, 1, '2026-09-23 10:35:14', '2026-09-23 10:35:14');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Weapons', 'Combat and defensive equipment.', '2026-09-15 20:55:06', '2026-09-15 20:55:06'),
(2, 'Armor', 'Protective equipment for field operations.', '2026-09-15 20:55:06', '2026-09-15 20:55:06'),
(3, 'Technology', 'Advanced technological equipment and devices.', '2026-09-15 20:55:06', '2026-09-15 20:55:06'),
(4, 'Medical', 'Medical and recovery equipment.', '2026-09-15 20:55:06', '2026-09-15 20:55:06'),
(5, 'Tools', 'Utility and maintenance equipment.', '2026-09-15 20:55:06', '2026-09-15 20:55:06');

-- --------------------------------------------------------

--
-- Table structure for table `compatibility`
--

CREATE TABLE `compatibility` (
  `id` int UNSIGNED NOT NULL,
  `equipment_id` int UNSIGNED NOT NULL,
  `resource_id` int UNSIGNED NOT NULL,
  `compatibility_type` enum('compatible','incompatible') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'compatible',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int UNSIGNED NOT NULL,
  `category_id` int UNSIGNED DEFAULT NULL,
  `access_role` enum('common','hero','enemy') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'common',
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `brand` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '0',
  `available_quantity` int UNSIGNED NOT NULL DEFAULT '0',
  `status` enum('available','unavailable','maintenance') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `image_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `category_id`, `access_role`, `name`, `description`, `brand`, `quantity`, `available_quantity`, `status`, `image_url`, `created_at`, `updated_at`) VALUES
(1, 3, 'common', 'Xythera Lens', 'A compact ÆTHERYX analysis instrument capable of interpreting resonance signals and environmental anomalies.', 'ÆTHERYX Research Division', 12, 12, 'available', NULL, '2026-09-15 21:07:52', '2026-09-28 20:29:21'),
(2, 3, 'common', 'Aevorin Signal Node', 'Portable communication equipment designed to maintain stable field synchronization between compatible devices.', 'ÆTHERYX Systems', 15, 15, 'available', NULL, '2026-09-15 21:07:52', '2026-09-15 21:07:52'),
(3, 5, 'common', 'Kryvex M-9', 'A modular maintenance instrument designed for calibration, diagnostics, and equipment repair.', 'ÆTHERYX Engineering', 10, 10, 'available', NULL, '2026-09-15 21:07:52', '2026-09-15 21:07:52'),
(4, 4, 'common', 'Seralyth Med-Core', 'A compact field-support unit containing diagnostic and emergency medical modules.', 'ÆTHERYX Medical', 8, 8, 'available', NULL, '2026-09-15 21:07:52', '2026-09-15 21:07:52'),
(5, 3, 'common', 'Orivane Mapper', 'A portable mapping instrument used to record operational environments and equipment locations.', 'ÆTHERYX Navigation', 14, 14, 'available', NULL, '2026-09-15 21:07:52', '2026-09-15 21:07:52'),
(6, 1, 'hero', 'Solvanyx Array', 'A precision ÆTHERYX system calibrated for Hero field operations and controlled energy output.', 'ÆTHERYX Vanguard Research', 6, 6, 'available', NULL, '2026-09-15 21:07:52', '2026-09-15 21:07:52'),
(7, 2, 'hero', 'Auralith Mantle', 'Advanced protective equipment engineered around a lightweight adaptive resonance lattice.', 'ÆTHERYX Defense', 7, 7, 'available', NULL, '2026-09-15 21:07:52', '2026-09-15 21:07:52'),
(8, 3, 'hero', 'Elyndra Core', 'A specialized power-management module designed for high-precision Hero equipment.', 'ÆTHERYX Coreworks', 5, 5, 'available', NULL, '2026-09-15 21:07:52', '2026-09-15 21:07:52'),
(9, 5, 'hero', 'Veyrion Field Kit', 'A specialized technical kit designed for rapid equipment calibration during Hero operations.', 'ÆTHERYX Engineering', 9, 9, 'available', NULL, '2026-09-15 21:07:52', '2026-09-15 21:07:52'),
(10, 4, 'hero', 'Luminara Recovery Unit', 'A Hero-oriented field support system designed for monitoring and recovery operations.', 'ÆTHERYX Medical', 6, 6, 'available', NULL, '2026-09-15 21:07:52', '2026-09-15 21:07:52'),
(11, 1, 'enemy', 'Noctyra Module', 'A specialized ÆTHERYX equipment module designed for covert operational environments.', 'ÆTHERYX Shadow Division', 6, 6, 'available', NULL, '2026-09-15 21:07:52', '2026-09-15 21:07:52'),
(12, 2, 'enemy', 'Veylith Carapace', 'Reinforced protective equipment constructed around a layered adaptive shell.', 'ÆTHERYX Defense', 7, 7, 'available', NULL, '2026-09-15 21:07:52', '2026-09-15 21:07:52'),
(13, 3, 'enemy', 'Kharvex Relay', 'A specialized signal-routing system designed for isolated field networks.', 'ÆTHERYX Systems', 5, 5, 'available', NULL, '2026-09-15 21:07:52', '2026-09-15 21:07:52'),
(14, 5, 'enemy', 'Zeravex Utility Rig', 'A modular technical platform designed for complex field maintenance tasks.', 'ÆTHERYX Engineering', 9, 9, 'available', NULL, '2026-09-15 21:07:52', '2026-09-15 21:07:52'),
(15, 4, 'enemy', 'Nythera Support Core', 'A specialized support unit designed for remote operational environments.', 'ÆTHERYX Medical', 6, 6, 'available', NULL, '2026-09-15 21:07:52', '2026-09-15 21:07:52');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_requests`
--

CREATE TABLE `equipment_requests` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `equipment_id` int UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '1',
  `status` enum('pending','approved','rejected','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int UNSIGNED DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equipment_requests`
--

INSERT INTO `equipment_requests` (`id`, `user_id`, `equipment_id`, `quantity`, `status`, `requested_at`, `reviewed_at`, `reviewed_by`, `notes`) VALUES
(1, 14, 1, 1, 'approved', '2026-09-15 22:49:22', '2026-09-15 22:52:34', 2, 'Test equipment request.'),
(2, 14, 4, 1, 'cancelled', '2026-09-22 07:10:02', '2026-09-28 19:42:21', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `equipment_id` int UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '1',
  `status` enum('assigned','returned','lost','damaged') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assigned',
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `returned_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `user_id`, `equipment_id`, `quantity`, `status`, `assigned_at`, `returned_at`, `notes`) VALUES
(1, 14, 1, 1, 'returned', '2026-09-15 22:52:34', '2026-09-28 20:29:21', 'Assigned from approved equipment request #1');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 14, 'Notification', 'Welcome to ÆTHERYX!', 'info', 1, '2026-09-20 16:29:31'),
(3, 13, 'Villain Alert', 'Welcome to the Villain Network!', 'info', 0, '2026-09-20 16:37:52'),
(4, 14, 'Equipment returned', 'Your equipment was returned and is available to others again.', 'success', 0, '2026-09-28 20:29:21');

-- --------------------------------------------------------

--
-- Table structure for table `resources`
--

CREATE TABLE `resources` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `resource_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('available','unavailable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rules`
--

CREATE TABLE `rules` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `rule_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condition_data` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `handle` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','hero','enemy') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hero',
  `status` enum('active','suspended','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `handle`, `email`, `password_hash`, `role`, `status`, `created_at`, `updated_at`) VALUES
(2, 'System Administrator', 'admin', 'admin@aetheryx.local', '$2y$10$3I0HCPnlnJoeeJRavzbmJu87S.n5WtsTv7rbGp0enR8v/3.bacY2W', 'admin', 'active', '2026-09-13 09:21:02', '2026-09-13 09:21:02'),
(13, 'jadu', 'jadu1', 'jadu@gmail.com', '$2y$10$5Zqh2YXSmF9zH8444.PyZevKro9.s7Fy0wqTwfXjjCUy6R/edO.oq', 'enemy', 'active', '2026-09-15 13:56:45', '2026-09-15 13:56:45'),
(14, 'hero', 'hero1', 'hero1@gmail.com', '$2y$10$lvcW8FnEmfSaqOHLZqHZh.XrBCk62fqdFwXt1ix0tMphn1s5y0Jee', 'hero', 'active', '2026-09-15 19:13:12', '2026-09-15 19:13:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_user` (`user_id`),
  ADD KEY `idx_activity_action` (`action`),
  ADD KEY `idx_activity_created` (`created_at`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_equipment` (`user_id`,`equipment_id`),
  ADD KEY `fk_cart_equipment` (`equipment_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `compatibility`
--
ALTER TABLE `compatibility`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_equipment_resource` (`equipment_id`,`resource_id`),
  ADD KEY `idx_compatibility_equipment` (`equipment_id`),
  ADD KEY `idx_compatibility_resource` (`resource_id`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_equipment_category` (`category_id`),
  ADD KEY `idx_equipment_status` (`status`);

--
-- Indexes for table `equipment_requests`
--
ALTER TABLE `equipment_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_requests_user` (`user_id`),
  ADD KEY `idx_requests_equipment` (`equipment_id`),
  ADD KEY `idx_requests_status` (`status`),
  ADD KEY `idx_requests_reviewer` (`reviewed_by`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_user` (`user_id`),
  ADD KEY `idx_inventory_equipment` (`equipment_id`),
  ADD KEY `idx_inventory_status` (`status`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user` (`user_id`),
  ADD KEY `idx_notifications_read` (`is_read`);

--
-- Indexes for table `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resources_status` (`status`);

--
-- Indexes for table `rules`
--
ALTER TABLE `rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rules_active` (`is_active`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `handle` (`handle`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `compatibility`
--
ALTER TABLE `compatibility`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `equipment_requests`
--
ALTER TABLE `equipment_requests`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `resources`
--
ALTER TABLE `resources`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rules`
--
ALTER TABLE `rules`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `fk_cart_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `compatibility`
--
ALTER TABLE `compatibility`
  ADD CONSTRAINT `fk_compatibility_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_compatibility_resource` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `equipment`
--
ALTER TABLE `equipment`
  ADD CONSTRAINT `fk_equipment_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `equipment_requests`
--
ALTER TABLE `equipment_requests`
  ADD CONSTRAINT `fk_requests_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_requests_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `fk_inventory_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
