-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: Localhost:3307
-- Generation Time: Sep 14, 2025 at 10:29 AM
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
-- Database: `frsm`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_tokens`
--

CREATE TABLE `auth_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auth_tokens`
--

INSERT INTO `auth_tokens` (`id`, `user_id`, `token_hash`, `expires_at`, `created_at`) VALUES
(1, 1, '$2y$10$k8flzRrhIh3YIONNdzJigepkS0gcX6hw9Cka0SXXR64BE9XZsI2u.', '2025-10-13 20:50:15', '2025-09-13 12:50:15');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `department` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `shift_type_preference` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `hire_date` date DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(64) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `first_name`, `last_name`, `middle_name`, `username`, `email`, `password_hash`, `employee_id`, `department`, `position`, `shift_type_preference`, `is_active`, `hire_date`, `is_verified`, `verification_token`, `token_expires_at`, `reset_token`, `reset_token_expires`, `created_at`, `updated_at`) VALUES
(1, 'Juan', 'Dela Cruz', 'Santos', 'jdelacruz', 'juan.delacruz@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EMP001', 'Fire Suppression', 'Firefighter III', NULL, 1, NULL, 1, NULL, NULL, NULL, NULL, '2025-08-31 09:38:22', '2025-08-31 09:38:22'),
(2, 'Maria', 'Santos', 'Garcia', 'msantos', 'maria.santos@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EMP002', 'Emergency Medical Services', 'Paramedic', NULL, 1, NULL, 1, NULL, NULL, NULL, NULL, '2025-08-31 09:38:22', '2025-08-31 09:38:22'),
(3, 'Pedro', 'Reyes', 'Alvarez', 'preyes', 'pedro.reyes@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EMP003', 'Rescue Operations', 'Rescue Specialist', NULL, 1, NULL, 1, NULL, NULL, NULL, NULL, '2025-08-31 09:38:22', '2025-08-31 09:38:22'),
(4, 'Ana', 'Gonzales', 'Lopez', 'agonzales', 'ana.gonzales@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EMP004', 'Hazardous Materials', 'HazMat Technician', NULL, 1, NULL, 1, NULL, NULL, NULL, NULL, '2025-08-31 09:38:22', '2025-08-31 09:38:22'),
(5, 'Luis', 'Torres', 'Ramirez', 'ltorres', 'luis.torres@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EMP005', 'Fire Suppression', 'Firefighter II', NULL, 1, NULL, 1, NULL, NULL, NULL, NULL, '2025-08-31 09:38:22', '2025-08-31 09:38:22'),
(6, 'Yukki', 'Kyle', 'D', 'ykyle', 'yukki.kyle@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EMP006', 'Fire Suppression', 'Firefighter I', NULL, 1, NULL, 1, NULL, NULL, NULL, NULL, '2025-09-05 15:49:25', '2025-09-05 15:49:25'),
(7, 'Mariefe', 'Baturi', 'S', 'mbaturi', 'mariefe.baturi@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EMP007', 'Emergency Medical Services', 'Nurse', NULL, 1, NULL, 1, NULL, NULL, NULL, NULL, '2025-09-05 15:49:25', '2025-09-05 15:49:25'),
(8, 'Zaldy Jr', 'Solis', NULL, 'zsolis', 'zaldy.solis@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EMP008', 'Rescue Operations', 'Rescue Aide', NULL, 1, NULL, 1, NULL, NULL, NULL, NULL, '2025-09-05 15:49:25', '2025-09-05 15:49:25'),
(9, 'Norlika', 'Linog', 'A', 'nlinog', 'norlika.linog@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EMP009', 'Hazardous Materials', 'HazMat Support', NULL, 1, NULL, 1, NULL, NULL, NULL, NULL, '2025-09-05 15:49:25', '2025-09-05 15:49:25'),
(10, 'Dadang', 'Smiltzer', NULL, 'dsmiltzer', 'dadang.smiltzer@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EMP010', 'Fire Suppression', 'Driver', NULL, 1, NULL, 1, NULL, NULL, NULL, NULL, '2025-09-05 15:49:25', '2025-09-05 15:49:25');

-- --------------------------------------------------------

--
-- Table structure for table `employee_auth_tokens`
--

CREATE TABLE `employee_auth_tokens` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_contacts`
--

CREATE TABLE `employee_contacts` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `module_permissions`
--

CREATE TABLE `module_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `user_type` enum('user','employee') NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `role_id` int(11) DEFAULT 4,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(64) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `middle_name`, `username`, `email`, `password_hash`, `is_admin`, `role_id`, `is_verified`, `verification_token`, `token_expires_at`, `reset_token`, `reset_token_expires`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'Stephen', 'Viray', 'Delos Reyes', 'yukki', 'stephenviray12@gmail.com', '$2y$10$cmcxzuEoEp9YKUvdjXa39ez.m6I8q.Qd6FIP2x9VOKNm95/qfhTNq', 1, 1, 1, NULL, NULL, 'b4aafdeceb9d7174f16592b7a410a0f219dd204c360e8d42308b35eae23e19d3', '2025-09-13 19:18:11', '2025-08-31 09:38:22', '2025-09-13 16:18:11', '2025-09-02 18:02:05'),
(2, 'alliana', 'barrete', 'natingga', 'alliana', 'yannabarrete@gmail.com', '$2y$10$xSO3CBS4A1/5XtOIE3LSQOlY/Pu27W9ZM6zhd.z4pkoPfLq3ZWC/u', 0, 1, 1, NULL, NULL, NULL, NULL, '2025-09-01 07:18:36', '2025-09-04 16:41:17', '2025-09-02 18:02:05'),
(3, 'stephen', 'viray', 'kyle', 'teamsqu4d', 'yenajigumina12@gmail.com', '$2y$10$2p1px5B1l9a/ec55vtN7i./toVIbmeGLB8EMSLHwjEUAcnJzOrss.', 0, 4, 1, NULL, NULL, '6743e289827cd87c340806d9d031133af8c943f9613b491d4221ac1514d68247', '2025-09-13 14:32:38', '2025-09-13 11:31:55', '2025-09-13 11:32:38', '2025-09-13 11:31:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employee_auth_tokens`
--
ALTER TABLE `employee_auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employee_contacts`
--
ALTER TABLE `employee_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `module_permissions`
--
ALTER TABLE `module_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_module` (`user_id`,`module_name`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `token` (`token`);

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
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `employee_auth_tokens`
--
ALTER TABLE `employee_auth_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_contacts`
--
ALTER TABLE `employee_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `module_permissions`
--
ALTER TABLE `module_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD CONSTRAINT `auth_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_auth_tokens`
--
ALTER TABLE `employee_auth_tokens`
  ADD CONSTRAINT `employee_auth_tokens_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_contacts`
--
ALTER TABLE `employee_contacts`
  ADD CONSTRAINT `employee_contacts_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `module_permissions`
--
ALTER TABLE `module_permissions`
  ADD CONSTRAINT `module_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
