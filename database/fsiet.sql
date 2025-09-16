-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: Localhost:3307
-- Generation Time: Sep 14, 2025 at 10:30 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fsiet`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `serial_no` varchar(50) NOT NULL,
  `status` enum('available','in-use','maintenance','retired') NOT NULL DEFAULT 'available',
  `assigned_unit` int(11) DEFAULT NULL,
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `name`, `type`, `serial_no`, `status`, `assigned_unit`, `last_maintenance`, `next_maintenance`, `created_at`, `updated_at`) VALUES
(1, 'Hose 100ft', 'Fire Suppression', 'HOSE-100FT-001', 'in-use', 1, '2025-07-15', '2025-10-15', '2025-08-31 09:39:05', '2025-09-01 08:29:37'),
(2, 'SCBA Set #5', 'Breathing Apparatus', 'SCBA-SET-005', 'available', 1, '2025-08-01', '2025-11-01', '2025-08-31 09:39:05', '2025-09-01 08:29:37'),
(3, 'Medical Kit A', 'Medical', 'MED-KIT-A-2025', 'available', 3, '2025-07-20', '2025-10-20', '2025-08-31 09:39:05', '2025-09-01 08:29:38'),
(4, 'Jaws of Life', 'Rescue', 'JOL-RESCUE-002', 'maintenance', 2, '2025-06-10', '2025-09-10', '2025-08-31 09:39:05', '2025-09-04 14:05:54'),
(5, 'Thermal Camera', 'Detection', 'THERM-CAM-007', 'maintenance', NULL, '2025-07-01', '2025-10-01', '2025-08-31 09:39:05', '2025-09-01 08:29:38'),
(6, 'jeff yarap', 'fire_truck', '1231231', 'in-use', 1, '2025-09-04', NULL, '2025-09-01 08:29:53', '2025-09-04 13:56:21');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_assignments`
--

CREATE TABLE `equipment_assignments` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_date` date NOT NULL,
  `expected_return` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `status` enum('assigned','in_use','returned','overdue') NOT NULL DEFAULT 'assigned',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment_assignments`
--

INSERT INTO `equipment_assignments` (`id`, `equipment_id`, `assigned_to`, `assigned_by`, `assigned_date`, `expected_return`, `return_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 6, 1, 1, '2025-09-01', '2025-09-18', NULL, 'assigned', 'dfsfsdfsd', '2025-09-01 08:30:39', '2025-09-01 08:30:39');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_usage`
--

CREATE TABLE `equipment_usage` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `checkout_time` datetime NOT NULL,
  `return_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_categories`
--

CREATE TABLE `inventory_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_categories`
--

INSERT INTO `inventory_categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Fire Suppression', 'Equipment used for fire suppression and control', '2025-08-31 09:39:05', '2025-08-31 09:39:05'),
(2, 'Rescue Equipment', 'Tools and equipment for rescue operations', '2025-08-31 09:39:05', '2025-08-31 09:39:05'),
(3, 'Medical Supplies', 'Medical equipment and first aid supplies', '2025-08-31 09:39:05', '2025-08-31 09:39:05'),
(4, 'Personal Protective Equipment', 'Safety gear for firefighters', '2025-08-31 09:39:05', '2025-08-31 09:39:05'),
(5, 'Communication Devices', 'Radios and communication equipment', '2025-08-31 09:39:05', '2025-08-31 09:39:05'),
(6, 'Vehicle Equipment', 'Equipment specifically for fire vehicles', '2025-08-31 09:39:05', '2025-08-31 09:39:05');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `min_stock_level` int(11) NOT NULL DEFAULT 5,
  `unit` varchar(20) DEFAULT 'pcs',
  `storage_location` varchar(100) DEFAULT NULL,
  `status` enum('in_stock','low_stock','out_of_stock') NOT NULL DEFAULT 'in_stock',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `name`, `description`, `category_id`, `quantity`, `min_stock_level`, `unit`, `storage_location`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Fire Hose 2.5\"', '2.5 inch diameter fire hose, 100ft length', 1, 15, 5, 'pcs', 'Storage Room A', 'in_stock', '2025-08-31 09:39:05', '2025-08-31 09:39:05'),
(2, 'SCBA Cylinders', 'Self-Contained Breathing Apparatus air cylinders', 4, 8, 4, 'pcs', 'Equipment Room B', 'in_stock', '2025-08-31 09:39:05', '2025-08-31 09:39:05'),
(3, 'Fire Extinguisher ABC', 'ABC type fire extinguisher 10lbs', 1, 20, 10, 'pcs', 'Storage Room A', 'in_stock', '2025-08-31 09:39:05', '2025-08-31 09:39:05'),
(4, 'First Aid Kit', 'Comprehensive first aid kit for emergency response', 3, 5, 3, 'kits', 'Medical Cabinet', 'in_stock', '2025-08-31 09:39:05', '2025-08-31 09:39:05'),
(5, 'Rescue Rope', '150ft static kernmantle rope for rescue operations', 2, 6, 4, 'pcs', 'Rescue Equipment Locker', 'in_stock', '2025-08-31 09:39:05', '2025-08-31 09:39:05');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `transaction_type` enum('in','out','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_logs`
--

CREATE TABLE `maintenance_logs` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `maintenance_date` date NOT NULL,
  `performed_by` int(11) NOT NULL,
  `description` text NOT NULL,
  `parts_used` text DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `hours_spent` decimal(4,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_logs`
--

INSERT INTO `maintenance_logs` (`id`, `schedule_id`, `equipment_id`, `maintenance_date`, `performed_by`, `description`, `parts_used`, `cost`, `hours_spent`, `created_at`) VALUES
(1, 2, 6, '2025-09-04', 1, 'want', 'asdasd', 1321.00, 12.00, '2025-09-04 13:56:21');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_schedules`
--

CREATE TABLE `maintenance_schedules` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `schedule_type` enum('daily','weekly','monthly','quarterly','yearly') NOT NULL,
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','overdue') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_schedules`
--

INSERT INTO `maintenance_schedules` (`id`, `equipment_id`, `schedule_type`, `last_maintenance`, `next_maintenance`, `description`, `assigned_to`, `status`, `created_at`, `updated_at`) VALUES
(1, 4, '', NULL, '2025-09-16', 'wqeqwe', 1, 'completed', '2025-09-01 08:26:00', '2025-09-01 08:32:16'),
(2, 6, '', '2025-09-04', '2025-09-13', 'sdadas', 0, 'completed', '2025-09-04 13:55:54', '2025-09-04 13:56:21');

-- --------------------------------------------------------

--
-- Table structure for table `repair_requests`
--

CREATE TABLE `repair_requests` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `reported_by` int(11) NOT NULL,
  `reported_date` datetime NOT NULL DEFAULT current_timestamp(),
  `issue_description` text NOT NULL,
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('pending','approved','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `assigned_vendor` varchar(100) DEFAULT NULL,
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `actual_cost` decimal(10,2) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `repair_requests`
--

INSERT INTO `repair_requests` (`id`, `equipment_id`, `reported_by`, `reported_date`, `issue_description`, `priority`, `status`, `assigned_vendor`, `estimated_cost`, `actual_cost`, `start_date`, `completion_date`, `notes`, `created_at`, `updated_at`) VALUES
(1, 4, 1, '2025-09-04 22:05:54', 'asdasd', 'medium', 'pending', '', 121.00, 181.00, '2025-09-11', '2025-09-26', 'please paki tapos na', '2025-09-04 14:05:54', '2025-09-04 14:06:15');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `permissions` text DEFAULT NULL COMMENT 'JSON encoded permissions',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `name`, `permissions`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', '{\"inventory_manage\":true,\"equipment_track\":false,\"maintenance_schedule\":false,\"repair_manage\":false,\"reports_view\":false,\"reports_generate\":false,\"user_manage\":false}', '2025-09-03 03:24:01', '2025-09-04 16:42:12'),
(2, 'Manager', '{\"inventory_manage\":true,\"equipment_track\":true,\"maintenance_schedule\":true,\"repair_manage\":true,\"reports_view\":true,\"reports_generate\":true,\"user_manage\":false}', '2025-09-03 03:24:01', '2025-09-03 03:24:01'),
(3, 'Technician', '{\"inventory_manage\":false,\"equipment_track\":true,\"maintenance_schedule\":true,\"repair_manage\":true,\"reports_view\":true,\"reports_generate\":false,\"user_manage\":false}', '2025-09-03 03:24:01', '2025-09-03 03:24:01'),
(4, 'Viewer', '{\"inventory_manage\":false,\"equipment_track\":false,\"maintenance_schedule\":false,\"repair_manage\":false,\"reports_view\":true,\"reports_generate\":false,\"user_manage\":false}', '2025-09-03 03:24:01', '2025-09-03 03:24:01');

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `service_quality` enum('poor','fair','good','excellent') DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `action` (`action`),
  ADD KEY `table_name` (`table_name`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `type` (`type`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `equipment_assignments`
--
ALTER TABLE `equipment_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipment_id` (`equipment_id`);

--
-- Indexes for table `equipment_usage`
--
ALTER TABLE `equipment_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipment_id` (`equipment_id`);

--
-- Indexes for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `equipment_id` (`equipment_id`);

--
-- Indexes for table `maintenance_schedules`
--
ALTER TABLE `maintenance_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `repair_requests`
--
ALTER TABLE `repair_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `equipment_assignments`
--
ALTER TABLE `equipment_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `equipment_usage`
--
ALTER TABLE `equipment_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `maintenance_schedules`
--
ALTER TABLE `maintenance_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `repair_requests`
--
ALTER TABLE `repair_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `equipment_assignments`
--
ALTER TABLE `equipment_assignments`
  ADD CONSTRAINT `equipment_assignments_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `equipment_usage`
--
ALTER TABLE `equipment_usage`
  ADD CONSTRAINT `equipment_usage_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  ADD CONSTRAINT `maintenance_logs_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `maintenance_schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_logs_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_schedules`
--
ALTER TABLE `maintenance_schedules`
  ADD CONSTRAINT `maintenance_schedules_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `repair_requests`
--
ALTER TABLE `repair_requests`
  ADD CONSTRAINT `repair_requests_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
