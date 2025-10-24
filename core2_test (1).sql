-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3307
-- Generation Time: Sep 23, 2025 at 01:04 AM
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
-- Database: `core2_test`
--

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Philippines',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `first_name`, `last_name`, `email`, `password`, `phone`, `gender`, `birth_date`, `address_line1`, `address_line2`, `city`, `province`, `postal_code`, `country`, `created_at`, `updated_at`) VALUES
(1, 'Juan', 'Dela Cruz', 'juan@example.com', '$2y$10$hashedpassword', '09170000001', NULL, NULL, 'lilac street ', NULL, 'Manila', 'Metro Manila', NULL, 'Philippines', '2025-09-21 19:10:40', '2025-09-22 10:35:57'),
(2, 'Maria', 'Santos', 'maria@example.com', '$2y$10$hashedpassword', '09170000002', NULL, NULL, 'maligaya ', NULL, 'Quezon', 'Metro Manila', NULL, 'Philippines', '2025-09-21 19:10:40', '2025-09-22 10:33:44');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','shipped','delivered','cancelled') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `cancellation_reason` text DEFAULT NULL,
  `refund_reason` varchar(255) DEFAULT NULL,
  `refund_method` varchar(100) DEFAULT NULL,
  `refund_notes` text DEFAULT NULL,
  `partial_refund_reason` varchar(255) DEFAULT NULL,
  `refund_processed_date` datetime DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT NULL,
  `refund_reference` varchar(100) DEFAULT NULL,
  `refund_requested_date` datetime DEFAULT NULL,
  `refund_status` enum('requested','approved','processing','completed','rejected') DEFAULT NULL,
  `payment_method` enum('COD','GCash','Credit Card','PayPal') DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `rejection_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `seller_id`, `product_id`, `quantity`, `total_price`, `status`, `created_at`, `cancellation_reason`, `refund_reason`, `refund_method`, `refund_notes`, `partial_refund_reason`, `refund_processed_date`, `refund_amount`, `refund_reference`, `refund_requested_date`, `refund_status`, `payment_method`, `rejection_reason`, `rejection_date`) VALUES
(4, 1, 1, 1, 2, 998.00, 'approved', '2025-09-21 19:13:44', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'rejected', NULL, 'LAGPAS NA SA 7 DAYS RETURN GARANTEE\r\n', '2025-09-22 20:20:51'),
(5, 2, 1, 2, 1, 2500.00, 'delivered', '2025-09-21 19:13:44', 'Out of stock', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'requested', NULL, NULL, NULL),
(6, 1, 1, 3, 1, 899.00, 'pending', '2025-09-21 19:13:44', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'requested', NULL, NULL, NULL),
(21, 1, 1, 1, 2, 998.00, 'pending', '2025-09-22 11:47:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'processing', NULL, NULL, NULL),
(22, 2, 2, 2, 1, 2500.00, 'shipped', '2025-09-22 11:47:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'requested', NULL, NULL, NULL),
(23, 1, 2, 3, 3, 2697.00, 'delivered', '2025-09-22 11:47:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'requested', NULL, NULL, NULL),
(24, 2, 1, 1, 1, 499.00, 'cancelled', '2025-09-22 11:47:16', 'Change of mind', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'rejected', NULL, '', '2025-09-22 20:16:41'),
(28, 1, 1, 1, 2, 998.00, 'cancelled', '2025-09-22 11:48:28', 'REFUND: Customer changed mind | Method: GCash | Notes: Processed successfully', 'Customer changed mind', 'GCash', 'Refund sent successfully', NULL, '2025-09-22 15:56:21', 998.00, NULL, NULL, 'completed', 'GCash', NULL, NULL),
(29, 2, 1, 2, 1, 2500.00, 'cancelled', '2025-09-22 11:48:28', 'REFUND: Damaged product | Method: Bank Transfer | Notes: Full refund issued', 'Damaged product', 'Bank Transfer', 'Full refund completed', NULL, '2025-09-22 16:13:54', 2500.00, NULL, NULL, 'completed', 'GCash', NULL, NULL),
(30, 2, 1, 3, 1, 899.00, 'cancelled', '2025-09-22 11:48:28', 'REFUND: Wrong item | Method: GCash | Notes: Partial refund only', 'Wrong item', 'GCash', 'Partial refund of 400 processed', NULL, '2025-09-22 11:48:28', 400.00, NULL, NULL, 'rejected', 'GCash', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `category` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `seller_id`, `name`, `description`, `price`, `stock`, `category`, `image`, `created_at`, `updated_at`) VALUES
(1, 1, 'Wireless Mouse', 'Ergonomic wireless mouse', 499.00, 50, 'Electronics', NULL, '2025-09-21 19:11:57', '2025-09-21 19:11:57'),
(2, 1, 'Mechanical Keyboard', 'RGB backlit keyboard', 2500.00, 20, 'Electronics', NULL, '2025-09-21 19:11:57', '2025-09-21 19:11:57'),
(3, 1, 'USB-C Charger', 'Fast charging USB-C charger', 899.00, 30, 'Accessories', NULL, '2025-09-21 19:11:57', '2025-09-21 19:11:57');

-- --------------------------------------------------------

--
-- Table structure for table `sellers`
--

CREATE TABLE `sellers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `store_name` varchar(100) DEFAULT NULL,
  `store_address` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sellers`
--

INSERT INTO `sellers` (`id`, `name`, `email`, `password`, `phone`, `store_name`, `store_address`, `notes`, `created_at`) VALUES
(1, 'Eustas', 'hakdogeti@gmail.com', '$2y$10$s6nI4BaQkbPuqQ4OcDholO3Q9v4ZwUZakNGZ8rjuYEg6WRT49qqMW', NULL, NULL, NULL, NULL, '2025-09-21 19:10:45'),
(2, 'seller2', 'nero@gmail.com', '$2y$10$Pu1SeAoxQnYrAcTHPJWc/eos45smwCDXqQV5Cei3UOa/yebyAr2Je', NULL, NULL, NULL, NULL, '2025-09-21 20:46:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `sellers`
--
ALTER TABLE `sellers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sellers`
--
ALTER TABLE `sellers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
