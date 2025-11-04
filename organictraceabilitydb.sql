-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 04, 2025 at 07:01 PM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `organictraceabilitydb`
--

-- --------------------------------------------------------

--
-- Table structure for table `manufacturerfarmerorders`
--

DROP TABLE IF EXISTS `manufacturerfarmerorders`;
CREATE TABLE IF NOT EXISTS `manufacturerfarmerorders` (
  `MF_OrderID` int NOT NULL AUTO_INCREMENT,
  `RawProductID` int NOT NULL,
  `FarmerID` int NOT NULL,
  `ManufacturerID` int NOT NULL,
  `OrderQuantity` decimal(10,2) NOT NULL,
  `TotalPrice` decimal(10,2) NOT NULL,
  `OrderDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `Status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending Confirmation',
  `AssignedBatchID` int DEFAULT NULL,
  `isManipulated` tinyint(1) NOT NULL DEFAULT '0',
  `TransactionHash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`MF_OrderID`),
  KEY `RawProductID` (`RawProductID`),
  KEY `FarmerID` (`FarmerID`),
  KEY `ManufacturerID` (`ManufacturerID`),
  KEY `AssignedBatchID` (`AssignedBatchID`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `manufacturerfarmerorders`
--

INSERT INTO `manufacturerfarmerorders` (`MF_OrderID`, `RawProductID`, `FarmerID`, `ManufacturerID`, `OrderQuantity`, `TotalPrice`, `OrderDate`, `Status`, `AssignedBatchID`, `isManipulated`, `TransactionHash`) VALUES
(6, 11, 33, 35, 21.00, 63.00, '2025-10-31 00:28:11', 'Shipped', 7, 0, NULL),
(7, 11, 33, 35, 13.00, 39.00, '2025-10-31 00:41:10', 'Shipped', 7, 0, NULL),
(8, 13, 33, 35, 21.00, 441.00, '2025-10-31 01:34:13', 'Shipped', 8, 0, NULL),
(9, 13, 33, 35, 12.00, 252.00, '2025-10-31 02:07:47', 'Pending Confirmation', NULL, 0, NULL),
(10, 15, 33, 35, 13.00, 403.00, '2025-10-31 02:12:15', 'Shipped', 9, 0, NULL),
(11, 16, 32, 34, 21.00, 840.00, '2025-10-31 02:27:01', 'Shipped', 12, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `OrderID` int NOT NULL AUTO_INCREMENT,
  `ProductID` int NOT NULL,
  `SellerID` int NOT NULL,
  `BuyerID` int NOT NULL,
  `OrderQuantity` decimal(10,2) NOT NULL,
  `TotalPrice` decimal(10,2) NOT NULL,
  `OrderDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `Status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending Confirmation',
  `SourceBatchID` int DEFAULT NULL,
  `SourceProductOrderID` int DEFAULT NULL,
  `AssignedDistributorID` int DEFAULT NULL,
  `PickupDate` datetime DEFAULT NULL,
  `DeliveryDate` datetime DEFAULT NULL,
  `ShippingAddress` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `isManipulated` tinyint(1) NOT NULL DEFAULT '0',
  `TransactionHash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`OrderID`),
  KEY `ProductID` (`ProductID`),
  KEY `SellerID` (`SellerID`),
  KEY `BuyerID` (`BuyerID`),
  KEY `SourceBatchID` (`SourceBatchID`),
  KEY `SourceProductOrderID` (`SourceProductOrderID`),
  KEY `AssignedDistributorID` (`AssignedDistributorID`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`OrderID`, `ProductID`, `SellerID`, `BuyerID`, `OrderQuantity`, `TotalPrice`, `OrderDate`, `Status`, `SourceBatchID`, `SourceProductOrderID`, `AssignedDistributorID`, `PickupDate`, `DeliveryDate`, `ShippingAddress`, `isManipulated`, `TransactionHash`) VALUES
(14, 14, 35, 39, 31.00, 3945.00, '2025-10-31 02:07:29', 'Delivered', 8, NULL, NULL, '2025-10-31 02:31:32', '2025-10-31 02:31:33', NULL, 0, 'b650c29118e42d50a61f04935775d7ef1f3a8db8f96c2e702fd0cb2a33d17e59'),
(15, 17, 34, 39, 12.00, 2440.00, '2025-10-31 02:30:53', 'Delivered', 12, NULL, NULL, '2025-10-31 02:31:33', '2025-10-31 02:31:34', NULL, 0, 'a0ebbbdec71ebac3f4a1ca1811cf67d750ece6edc70b98b2f202b5e8e559b001'),
(16, 12, 35, 39, 13.00, 455.00, '2025-10-31 10:38:32', 'Delivered', 12, NULL, NULL, '2025-10-31 10:40:27', '2025-10-31 10:40:29', NULL, 0, '170a6ecc01cf44036deeaa71c268cbb17444518f942935c15dd845fa467835ee'),
(17, 17, 34, 47, 11.00, 1320.00, '2025-11-05 02:43:49', 'Delivered', 13, NULL, 46, '2025-11-05 02:45:23', '2025-11-05 02:45:24', NULL, 0, 'c4a90f0dc8e5f2bab5ea5464d2caf0f373f69b3ce5e9b2e86883b6a94971596b');

-- --------------------------------------------------------

--
-- Table structure for table `performance_logs`
--

DROP TABLE IF EXISTS `performance_logs`;
CREATE TABLE IF NOT EXISTS `performance_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `action_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `latency_ms` float DEFAULT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `performance_logs`
--

INSERT INTO `performance_logs` (`log_id`, `action_name`, `latency_ms`, `timestamp`) VALUES
(1, 'create_batch_hash', 0.025034, '2025-11-05 02:41:54'),
(2, 'create_order_hash', 0.0331402, '2025-11-05 02:43:49');

-- --------------------------------------------------------

--
-- Table structure for table `productbatches`
--

DROP TABLE IF EXISTS `productbatches`;
CREATE TABLE IF NOT EXISTS `productbatches` (
  `BatchID` int NOT NULL AUTO_INCREMENT,
  `ProductID` int NOT NULL,
  `UserID` int NOT NULL,
  `BatchNumber` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `SowingDate` date DEFAULT NULL,
  `HarvestedDate` date NOT NULL,
  `CropDetails` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `SoilDetails` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `FarmPractice` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `TransactionHash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `QRCodePath` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `DateCreated` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`BatchID`),
  UNIQUE KEY `unique_batch` (`UserID`,`BatchNumber`),
  KEY `ProductID` (`ProductID`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `productbatches`
--

INSERT INTO `productbatches` (`BatchID`, `ProductID`, `UserID`, `BatchNumber`, `SowingDate`, `HarvestedDate`, `CropDetails`, `SoilDetails`, `FarmPractice`, `TransactionHash`, `QRCodePath`, `DateCreated`) VALUES
(7, 11, 33, 'OT-2025-001', '2025-10-24', '2025-10-31', 'Bright red, medium-sized organic tomatoes grown naturally from open-pollinated seeds. The plants are healthy and productive, with firm, juicy fruits rich in natural sweetness.', 'Loamy soil enriched with compost and organic matter. The field is regularly mulched to retain moisture and prevent weeds, maintaining a balanced pH level for healthy root growth.', 'No synthetic chemicals or pesticides used. Only natural compost and organic pest control methods were applied. Watering was done using a drip irrigation system to conserve water. Each tomato was handpicked at peak ripeness to ensure the best quality and flavor.', '40f12a2973e4d43065349af85778a17957620f5786448e03b2d865e307c8a5bf', 'uploads/qr/batch_7.png', '2025-10-31 00:16:22'),
(8, 13, 33, 'OE-2025-003', '2025-10-08', '2025-11-08', 'Healthy, deep-purple organic eggplants grown from locally sourced organic seeds. The plants produced firm, glossy fruits with tender flesh and mild flavor, ideal for cooking and grilling. Each eggplant was handpicked at its best quality.', 'Planted in loamy soil rich in organic compost and natural nutrients. The field was well-drained and regularly mulched to retain moisture and prevent weeds. Soil fertility was maintained through crop rotation and organic matter enrichment.', 'No synthetic fertilizers or pesticides used. Natural compost and fermented plant juices were applied for plant nutrition. Organic pest control methods, such as neem extract and companion planting, were used to keep the crops healthy. Regular hand weeding and proper spacing ensured good air circulation and strong growth.', 'c038e6abb51304bcd15625776be545daabe870a107cd7935d2c62914a1467ec7', 'uploads/qr/batch_8.png', '2025-10-31 01:32:52'),
(9, 15, 33, 'OC-2025-004', '2025-10-15', '2025-11-05', 'Bright orange, crunchy carrots grown from organic seeds. Uniform in size and rich in natural sweetness, harvested early in the morning to maintain freshness.\r\n\r\nSoil Details:', 'Loamy soil enriched with organic compost and maintained through regular mulching. The field was watered using a drip irrigation system to reduce water waste and maintain soil health.', 'Used organic fertilizers like compost tea and vermicast. Natural pest control methods such as neem oil and companion planting were applied. No chemicals or synthetic pesticides used. Each batch is traceable and harvested by hand.', 'a977e23d71ca3e9eab85c825887e0ce9cf0c20521e18728c29e7d00021639127', 'uploads/qr/batch_9.png', '2025-10-31 02:11:44'),
(10, 16, 32, 'TEST-BATCH-123', '2025-10-10', '2025-11-08', 'Bright orange, crunchy carrots grown from organic seeds. Uniform in size and rich in natural sweetness, harvested early in the morning to maintain freshness.', 'Loamy soil enriched with organic compost and maintained through regular mulching. The field was watered using a drip irrigation system to reduce water waste and maintain soil health.', 'Used organic fertilizers like compost tea and vermicast. Natural pest control methods such as neem oil and companion planting were applied. No chemicals or synthetic pesticides used. Each batch is traceable and harvested by hand.', 'eb7755ff5bf774ea869352c4b4b03fc133a1a291ba4bc4ac5e4191469de4629c', 'uploads/qr/batch_10.png', '2025-10-31 02:27:56'),
(12, 16, 32, 'TEST-BATCH-1234', '2025-10-17', '2025-10-31', 'Original test data', 'Original test data', 'Original test data', 'fcf4049d2eefc91434f272d6a1a936bc265671c71caf4569948eda4858d5332a', 'uploads/qr/batch_12.png', '2025-10-31 02:29:06'),
(13, 18, 43, 'batch123', '2025-10-29', '2025-11-05', 'asdasd', 'dasdasd', 'fadsfsdf', '841f5ebb63934f8394fbc25c24284ba9cc8b6f8f9927eadd54d6021b989f60c8', 'uploads/qr/batch_13.png', '2025-11-05 02:41:53');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `ProductID` int NOT NULL AUTO_INCREMENT,
  `ProductName` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ProductDescription` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `CreatedByUserID` int NOT NULL,
  `ProductImage` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Quantity` decimal(10,2) DEFAULT NULL,
  `Price` decimal(10,2) DEFAULT NULL,
  `ShelfLifeDays` int DEFAULT NULL,
  `ProductType` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Processed',
  `DateAdded` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ProductID`),
  KEY `CreatedByUserID` (`CreatedByUserID`),
  KEY `idx_product_type` (`ProductType`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`ProductID`, `ProductName`, `ProductDescription`, `CreatedByUserID`, `ProductImage`, `Quantity`, `Price`, `ShelfLifeDays`, `ProductType`, `DateAdded`) VALUES
(11, 'Organic Tomato', 'I grow my organic tomatoes using natural methods — no chemicals, no synthetic fertilizers, just healthy soil and clean water. Each tomato is cared for by hand, from planting to harvest. They’re fresh, sweet, and full of real flavor, just the way nature made them. Grown with love and patience, these tomatoes are safe for your family and good for the earth.', 33, 'uploads/prod_d9fcca613e9f3614.jpg', 1.00, 3.00, 9, 'Raw', '2025-10-31 00:15:05'),
(12, 'Tomato Sauce', 'Tomato Sauce is made from freshly picked, sun-ripened organic tomatoes grown with care on our farm. Each batch is slow-cooked to bring out the natural sweetness and rich flavor of real tomatoes. We don’t use any artificial additives or preservatives — just pure, wholesome ingredients. Perfect for pasta, stews, or any home-cooked meal, it’s a healthy and delicious taste straight from the farm to your table.', 35, 'uploads/prod_2235712d893b3765.jpg', 1.00, 35.00, 45, 'Processed', '2025-10-31 00:42:30'),
(13, 'Organic Eggplant', 'Our Organic Eggplants are grown naturally in fertile, compost-rich soil without using any chemicals or synthetic fertilizers. Each fruit is handpicked at the right size — firm, glossy, and full of flavor. Perfect for grilling, frying, or adding to your favorite vegetable dishes. Fresh from the farm, safe for your family, and good for the earth.', 33, 'uploads/prod_a0b4a73b155c6bce.jpg', 1.00, 21.00, 10, 'Raw', '2025-10-31 01:28:00'),
(14, 'Eggplant Chips', 'Our Organic Eggplant Chips are made from freshly harvested eggplants grown naturally on our farm. Each slice is thinly cut, lightly seasoned with natural herbs, and gently dried to lock in its flavor and crispness — no preservatives, no artificial flavorings. A healthy, crunchy snack that’s full of farm-fresh goodness and perfect for all ages.', 35, 'uploads/prod_de0c4c63749694dd.jpg', 1.00, 95.00, 90, 'Processed', '2025-10-31 01:40:52'),
(15, 'Organic Carrot', 'Our Organic Carrots are grown naturally in rich, well-drained soil using only organic compost and natural pest control. Each carrot is handpicked — sweet, crunchy, and full of nutrients. Perfect for fresh salads, juices, or healthy meals. Grown with care and without any chemicals or synthetic fertilizers.\r\nPhoto (optional, max 5MB): (Attach image of freshly harvested carrots with greens)', 33, 'uploads/prod_438b0ffe62e97b07.jpg', 1.00, 31.00, 7, 'Raw', '2025-10-31 02:10:48'),
(16, 'Organic Carrot', 'Our Organic Carrots are grown naturally in rich, compost-fed soil without the use of chemicals or synthetic fertilizers. Each carrot is hand-harvested at the right time to ensure crisp texture, natural sweetness, and full nutrition.', 32, 'uploads/prod_cdaca40ef808f626.jpg', 1.00, 40.00, 10, 'Raw', '2025-10-31 02:26:01'),
(17, 'Carrot Pickles', 'Made from freshly harvested organic carrots from our farm. These pickles are naturally fermented and seasoned with organic spices and vinegar. No artificial flavorings or preservatives — just a tangy, crunchy, and healthy snack straight from the farm.', 34, 'uploads/prod_46c650f040bdfba4.jpg', 1.00, 120.00, 21, 'Processed', '2025-10-31 02:30:29'),
(18, 'Organic Coconut', 'ASDFTREW', 43, 'uploads/prod_f9dc5c12dab323d9.jpg', 20.00, 10.00, 7, 'Raw', '2025-11-05 02:41:29');

-- --------------------------------------------------------

--
-- Table structure for table `trust_survey`
--

DROP TABLE IF EXISTS `trust_survey`;
CREATE TABLE IF NOT EXISTS `trust_survey` (
  `survey_id` int NOT NULL AUTO_INCREMENT,
  `batch_id` int NOT NULL,
  `trust_score` int NOT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`survey_id`),
  KEY `batch_id` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trust_survey`
--

INSERT INTO `trust_survey` (`survey_id`, `batch_id`, `trust_score`, `timestamp`) VALUES
(1, 12, 5, '2025-11-05 02:51:43'),
(2, 8, 4, '2025-11-05 02:57:57'),
(3, 13, 5, '2025-11-05 02:59:26');

-- --------------------------------------------------------

--
-- Table structure for table `userroles`
--

DROP TABLE IF EXISTS `userroles`;
CREATE TABLE IF NOT EXISTS `userroles` (
  `RoleID` int NOT NULL AUTO_INCREMENT,
  `RoleName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`RoleID`),
  UNIQUE KEY `RoleName` (`RoleName`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `userroles`
--

INSERT INTO `userroles` (`RoleID`, `RoleName`) VALUES
(6, 'Admin'),
(5, 'Consumer'),
(3, 'Distributor'),
(1, 'Farmer'),
(2, 'Manufacturer'),
(4, 'Retailer');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `UserID` int NOT NULL AUTO_INCREMENT,
  `Username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `PasswordHash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `RoleID` int NOT NULL,
  `FullName` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ContactNumber` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `CertificateInfo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `VerificationStatus` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending',
  `VerifiedByAdminID` int DEFAULT NULL,
  `VerificationDate` datetime DEFAULT NULL,
  `DateRegistered` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`UserID`),
  UNIQUE KEY `Username` (`Username`),
  UNIQUE KEY `Email` (`Email`),
  KEY `RoleID` (`RoleID`),
  KEY `VerifiedByAdminID` (`VerifiedByAdminID`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`UserID`, `Username`, `PasswordHash`, `RoleID`, `FullName`, `Email`, `ContactNumber`, `Address`, `CertificateInfo`, `VerificationStatus`, `VerifiedByAdminID`, `VerificationDate`, `DateRegistered`) VALUES
(8, 'admin', '$2y$10$dcv/KRMo09RmLS2YkCVqk.G9JkKPOZnLlHm59QTAQ6YEx8ACgbcim', 6, 'Admin User', 'admin@systems.com', NULL, NULL, NULL, 'Approved', NULL, NULL, '2025-10-28 21:17:57'),
(32, 'farm_juan', '$2y$10$EciF5CRVxAwO8uM1mqzkrOimJ.n207Vu2iAUZUcGulYzkbtUyngPa', 1, 'Farm Ni Juan', 'farmjuan@gmail.com', '09365412987', 'Barangay Iberica, Labo, Camarines Norte', 'uploads/cert_4ef9df16a38372b1.pdf', 'Approved', 8, '2025-10-31 00:07:21', '2025-10-31 00:03:43'),
(33, 'farm_jo', '$2y$10$udqMYNYMDZm2h3AOUa.pxeRtiUhOeP7SLh4KhCSSjR8xhkQezLf/S', 1, 'Farm ni Jo', 'farmjo@gmail.com', '09124789563', 'Barangay Fundado, Labo, Camarines Norte', 'uploads/cert_d0648ceb0bfed100.pdf', 'Approved', 8, '2025-10-31 00:07:22', '2025-10-31 00:04:33'),
(34, 'mfr_maria', '$2y$10$rXLgpvcbLgMnLSkcJQfBLuzRQ5AsDLC.UWghgeQLuR5uRbVpva4qi', 2, 'Maria Processing Inc.', 'mfrmaria@gmail.com', '09563221794', 'Barangay Dogongan, Labo, Camarines Norte', 'uploads/cert_9a4ae5fd2bff8510.pdf', 'Approved', 8, '2025-10-31 00:07:23', '2025-10-31 00:05:18'),
(35, 'mfr_jo@gmail.com', '$2y$10$WjQYas/Mcv7wq7mq4sYHsOIjOuWtIHhZjECizIzYZFAyXn33B6ZbK', 2, 'Jo Processing Inc.', 'joprocess@gmail.com', '09326541987', 'Barangay Malasugui, Labo, Camarines Norte', 'uploads/cert_945dde88aedd6ded.pdf', 'Approved', 8, '2025-10-31 00:07:24', '2025-10-31 00:07:09'),
(39, 'retail_jo', '$2y$10$Ka0ruOC2VXNVcGV3H1zQs.QhXPDLaGX0kI90MoH2uyS5TPpWz6sSG', 4, 'Jo Fresh Mart', 'joretailer@gmail.com', '091566445656', 'Barangay Dalas, Labo, Camarines Norte', NULL, 'Approved', NULL, NULL, '2025-10-31 00:10:43'),
(43, 'farmer123', '$2y$10$RcoIPo6aR2KepQkgRq97vOyu9DuYXjN0s4dSeugsnShDNq2buBOmO', 1, 'Ako Ang Farmer', 'farmer123@gmail.com', '09123654789', 'Fundado CN', 'uploads/cert_88ac2ccbca066f06.pdf', 'Approved', 8, '2025-11-05 02:18:40', '2025-11-05 02:15:35'),
(44, 'manufacturer123', '$2y$10$dAQ/AopAmTJWmmajandjJOagEtp.quzoo0b7TGMBbigrQbM.xs.yy', 2, 'Ako ang Manufacturer', 'manufacturer@gmail.com', NULL, NULL, 'uploads/cert_cc6a9e00de7d19e1.pdf', 'Approved', 8, '2025-11-05 02:18:41', '2025-11-05 02:16:56'),
(45, 'distributor123', '$2y$10$GtfoZmNRZgMKxjw0pvXPJeBNHUDt8lPz6fp04rIf2AUER07jTNCMC', 3, 'Ako and Distributor', 'distributor@gmail.com', NULL, NULL, NULL, 'Approved', NULL, NULL, '2025-11-05 02:17:59'),
(46, 'dis123', '$2y$10$nEIcWpZhtv4m57Zk9jwW6.pOtp5Q5Vam9FYUJw2umMv3uBR1OPZne', 3, 'dis', 'dis123@gmail.com', '09123654723', 'Fundado CNda', NULL, 'Approved', NULL, NULL, '2025-11-05 02:19:13'),
(47, 'ret123', '$2y$10$oUAfBqYBOoU5FLNkrQXG2uEHbcTtdODVhU2/wdx/XqgzavuEn8eu6', 4, 'retailer', 'ret@gmail.com', '09707897044', 'dasdas', NULL, 'Approved', NULL, NULL, '2025-11-05 02:19:46'),
(48, 'farmfarm', '$2y$10$h7vm5r6iZdOhTJDFDtGM..enrS1/Wzix7qe2bdTdhZntRZMG9qihi', 1, 'farmfarm', 'farmfarm@gmail.com', NULL, NULL, 'uploads/cert_4c48cdc3495dc291.pdf', 'Approved', 8, '2025-11-05 02:39:03', '2025-11-05 02:38:41'),
(49, 'mark123', '$2y$10$nwHP8Oas/OnalLyupSSqBOLMCetIJokIlIT54XzTU7EOZXA2qqbjC', 5, 'Mark Jomar San Juan Calmateo', 'markjomarcalmateo123@gmail.com', NULL, NULL, NULL, 'Approved', NULL, NULL, '2025-11-05 02:55:52');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `manufacturerfarmerorders`
--
ALTER TABLE `manufacturerfarmerorders`
  ADD CONSTRAINT `manufacturerfarmerorders_ibfk_1` FOREIGN KEY (`RawProductID`) REFERENCES `products` (`ProductID`),
  ADD CONSTRAINT `manufacturerfarmerorders_ibfk_2` FOREIGN KEY (`FarmerID`) REFERENCES `users` (`UserID`),
  ADD CONSTRAINT `manufacturerfarmerorders_ibfk_3` FOREIGN KEY (`ManufacturerID`) REFERENCES `users` (`UserID`),
  ADD CONSTRAINT `manufacturerfarmerorders_ibfk_4` FOREIGN KEY (`AssignedBatchID`) REFERENCES `productbatches` (`BatchID`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`SellerID`) REFERENCES `users` (`UserID`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`BuyerID`) REFERENCES `users` (`UserID`),
  ADD CONSTRAINT `orders_ibfk_4` FOREIGN KEY (`SourceBatchID`) REFERENCES `productbatches` (`BatchID`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_5` FOREIGN KEY (`SourceProductOrderID`) REFERENCES `manufacturerfarmerorders` (`MF_OrderID`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_6` FOREIGN KEY (`AssignedDistributorID`) REFERENCES `users` (`UserID`) ON DELETE SET NULL;

--
-- Constraints for table `productbatches`
--
ALTER TABLE `productbatches`
  ADD CONSTRAINT `productbatches_ibfk_1` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`) ON DELETE CASCADE,
  ADD CONSTRAINT `productbatches_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`CreatedByUserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`RoleID`) REFERENCES `userroles` (`RoleID`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`VerifiedByAdminID`) REFERENCES `users` (`UserID`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
