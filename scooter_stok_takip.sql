-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 24 Eyl 2025, 23:19:53
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `scooter_stok_takip`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `goods_receipts`
--

CREATE TABLE `goods_receipts` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `receipt_number` varchar(100) NOT NULL,
  `receipt_date` date NOT NULL,
  `inspector_notes` text DEFAULT NULL,
  `status` enum('draft','inspecting','approved','rejected','completed') DEFAULT 'draft',
  `inspected_by` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `goods_receipt_items`
--

CREATE TABLE `goods_receipt_items` (
  `id` int(11) NOT NULL,
  `goods_receipt_id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `expected_quantity` decimal(10,2) NOT NULL,
  `received_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `accepted_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `rejected_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `inventory_counts`
--

CREATE TABLE `inventory_counts` (
  `id` int(11) NOT NULL,
  `count_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `inventory_count_lines`
--

CREATE TABLE `inventory_count_lines` (
  `id` int(11) NOT NULL,
  `inventory_count_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `counted_quantity` decimal(10,2) NOT NULL,
  `system_quantity` decimal(10,2) NOT NULL,
  `difference` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `uom_id` int(11) NOT NULL,
  `min_stock_level` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `movement_types`
--

CREATE TABLE `movement_types` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `multiplier` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(100) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_date` date DEFAULT NULL,
  `status_id` int(11) NOT NULL,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `order_activities`
--

CREATE TABLE `order_activities` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `order_statuses`
--

CREATE TABLE `order_statuses` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `color` varchar(20) DEFAULT '#6c757d',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `production_issues`
--

CREATE TABLE `production_issues` (
  `id` int(11) NOT NULL,
  `issue_number` varchar(100) NOT NULL,
  `production_order_id` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `issued_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `production_issue_items`
--

CREATE TABLE `production_issue_items` (
  `id` int(11) NOT NULL,
  `production_issue_id` int(11) NOT NULL,
  `production_order_item_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `production_orders`
--

CREATE TABLE `production_orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(100) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `target_quantity` int(11) NOT NULL,
  `planned_date` date NOT NULL,
  `deadline_date` date DEFAULT NULL,
  `status` enum('planned','in_progress','completed','cancelled') DEFAULT 'planned',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `production_order_items`
--

CREATE TABLE `production_order_items` (
  `id` int(11) NOT NULL,
  `production_order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `required_quantity` decimal(10,2) NOT NULL,
  `issued_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_consumption` decimal(10,4) NOT NULL DEFAULT 1.0000,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `production_returns`
--

CREATE TABLE `production_returns` (
  `id` int(11) NOT NULL,
  `return_number` varchar(100) NOT NULL,
  `production_order_id` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `returned_by` int(11) NOT NULL,
  `reason` text NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `production_return_items`
--

CREATE TABLE `production_return_items` (
  `id` int(11) NOT NULL,
  `production_return_id` int(11) NOT NULL,
  `production_order_item_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `permissions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `movement_type_id` int(11) NOT NULL,
  `movement_date` date NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `stock_movement_lines`
--

CREATE TABLE `stock_movement_lines` (
  `id` int(11) NOT NULL,
  `stock_movement_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uoms`
--

CREATE TABLE `uoms` (
  `id` int(11) NOT NULL,
  `name` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `goods_receipts`
--
ALTER TABLE `goods_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `inspected_by` (`inspected_by`),
  ADD KEY `created_by` (`created_by`);

--
-- Tablo için indeksler `goods_receipt_items`
--
ALTER TABLE `goods_receipt_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `goods_receipt_id` (`goods_receipt_id`),
  ADD KEY `order_item_id` (`order_item_id`);

--
-- Tablo için indeksler `inventory_counts`
--
ALTER TABLE `inventory_counts`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `inventory_count_lines`
--
ALTER TABLE `inventory_count_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_count_id` (`inventory_count_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Tablo için indeksler `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `uom_id` (`uom_id`);

--
-- Tablo için indeksler `movement_types`
--
ALTER TABLE `movement_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Tablo için indeksler `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `status_id` (`status_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Tablo için indeksler `order_activities`
--
ALTER TABLE `order_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Tablo için indeksler `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Tablo için indeksler `order_statuses`
--
ALTER TABLE `order_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Tablo için indeksler `production_issues`
--
ALTER TABLE `production_issues`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `issue_number` (`issue_number`),
  ADD KEY `production_order_id` (`production_order_id`),
  ADD KEY `issued_by` (`issued_by`);

--
-- Tablo için indeksler `production_issue_items`
--
ALTER TABLE `production_issue_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_issue_id` (`production_issue_id`),
  ADD KEY `production_order_item_id` (`production_order_item_id`);

--
-- Tablo için indeksler `production_orders`
--
ALTER TABLE `production_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `created_by` (`created_by`);

--
-- Tablo için indeksler `production_order_items`
--
ALTER TABLE `production_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_order_id` (`production_order_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Tablo için indeksler `production_returns`
--
ALTER TABLE `production_returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_number` (`return_number`),
  ADD KEY `production_order_id` (`production_order_id`),
  ADD KEY `returned_by` (`returned_by`);

--
-- Tablo için indeksler `production_return_items`
--
ALTER TABLE `production_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_return_id` (`production_return_id`),
  ADD KEY `production_order_item_id` (`production_order_item_id`);

--
-- Tablo için indeksler `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Tablo için indeksler `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `movement_type_id` (`movement_type_id`);

--
-- Tablo için indeksler `stock_movement_lines`
--
ALTER TABLE `stock_movement_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_movement_id` (`stock_movement_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Tablo için indeksler `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Tablo için indeksler `uoms`
--
ALTER TABLE `uoms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `goods_receipts`
--
ALTER TABLE `goods_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `goods_receipt_items`
--
ALTER TABLE `goods_receipt_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `inventory_counts`
--
ALTER TABLE `inventory_counts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `inventory_count_lines`
--
ALTER TABLE `inventory_count_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `movement_types`
--
ALTER TABLE `movement_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `order_activities`
--
ALTER TABLE `order_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `order_statuses`
--
ALTER TABLE `order_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `production_issues`
--
ALTER TABLE `production_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `production_issue_items`
--
ALTER TABLE `production_issue_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `production_orders`
--
ALTER TABLE `production_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `production_order_items`
--
ALTER TABLE `production_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `production_returns`
--
ALTER TABLE `production_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `production_return_items`
--
ALTER TABLE `production_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `stock_movement_lines`
--
ALTER TABLE `stock_movement_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uoms`
--
ALTER TABLE `uoms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `goods_receipts`
--
ALTER TABLE `goods_receipts`
  ADD CONSTRAINT `goods_receipts_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `goods_receipts_ibfk_2` FOREIGN KEY (`inspected_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `goods_receipts_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `goods_receipt_items`
--
ALTER TABLE `goods_receipt_items`
  ADD CONSTRAINT `goods_receipt_items_ibfk_1` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `goods_receipt_items_ibfk_2` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`);

--
-- Tablo kısıtlamaları `inventory_count_lines`
--
ALTER TABLE `inventory_count_lines`
  ADD CONSTRAINT `inventory_count_lines_ibfk_1` FOREIGN KEY (`inventory_count_id`) REFERENCES `inventory_counts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_count_lines_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Tablo kısıtlamaları `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`uom_id`) REFERENCES `uoms` (`id`);

--
-- Tablo kısıtlamaları `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `order_statuses` (`id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `order_activities`
--
ALTER TABLE `order_activities`
  ADD CONSTRAINT `order_activities_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_activities_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Tablo kısıtlamaları `production_issues`
--
ALTER TABLE `production_issues`
  ADD CONSTRAINT `production_issues_ibfk_1` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`),
  ADD CONSTRAINT `production_issues_ibfk_2` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `production_issue_items`
--
ALTER TABLE `production_issue_items`
  ADD CONSTRAINT `production_issue_items_ibfk_1` FOREIGN KEY (`production_issue_id`) REFERENCES `production_issues` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `production_issue_items_ibfk_2` FOREIGN KEY (`production_order_item_id`) REFERENCES `production_order_items` (`id`);

--
-- Tablo kısıtlamaları `production_orders`
--
ALTER TABLE `production_orders`
  ADD CONSTRAINT `production_orders_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `production_order_items`
--
ALTER TABLE `production_order_items`
  ADD CONSTRAINT `production_order_items_ibfk_1` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `production_order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Tablo kısıtlamaları `production_returns`
--
ALTER TABLE `production_returns`
  ADD CONSTRAINT `production_returns_ibfk_1` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`),
  ADD CONSTRAINT `production_returns_ibfk_2` FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `production_return_items`
--
ALTER TABLE `production_return_items`
  ADD CONSTRAINT `production_return_items_ibfk_1` FOREIGN KEY (`production_return_id`) REFERENCES `production_returns` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `production_return_items_ibfk_2` FOREIGN KEY (`production_order_item_id`) REFERENCES `production_order_items` (`id`);

--
-- Tablo kısıtlamaları `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`movement_type_id`) REFERENCES `movement_types` (`id`);

--
-- Tablo kısıtlamaları `stock_movement_lines`
--
ALTER TABLE `stock_movement_lines`
  ADD CONSTRAINT `stock_movement_lines_ibfk_1` FOREIGN KEY (`stock_movement_id`) REFERENCES `stock_movements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_movement_lines_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Tablo kısıtlamaları `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
