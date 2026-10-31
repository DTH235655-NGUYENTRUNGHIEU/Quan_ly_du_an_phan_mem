-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1:3306
-- Thời gian đã tạo: Th10 31, 2026 lúc 12:08 PM
-- Phiên bản máy phục vụ: 8.4.7
-- Phiên bản PHP: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `tour_dulich_vietnam`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `bookings`
--

DROP TABLE IF EXISTS `bookings`;
CREATE TABLE IF NOT EXISTS `bookings` (
  `booking_id` int NOT NULL AUTO_INCREMENT,
  `booking_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Mã đơn: BK202611001',
  `departure_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `promotion_id` int DEFAULT NULL,
  `booking_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `num_adults` int NOT NULL DEFAULT '1',
  `num_children` int NOT NULL DEFAULT '0',
  `num_infants` int NOT NULL DEFAULT '0',
  `subtotal` decimal(12,0) NOT NULL,
  `discount_amount` decimal(12,0) DEFAULT '0',
  `total_amount` decimal(12,0) NOT NULL,
  `paid_amount` decimal(12,0) DEFAULT '0',
  `payment_status` enum('Chưa thanh toán','Đã đặt cọc','Đã thanh toán đủ','Đã hoàn tiền') COLLATE utf8mb4_unicode_ci DEFAULT 'Chưa thanh toán',
  `booking_status` enum('Chờ duyệt','Đã xác nhận','Đang đi tour','Hoàn tất','Đã hủy') COLLATE utf8mb4_unicode_ci DEFAULT 'Chờ duyệt',
  `special_notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Yêu cầu ăn chay, ghế ngồi phía trước...',
  `is_rewarded` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1: Đã cộng điểm thưởng tích lũy',
  PRIMARY KEY (`booking_id`),
  UNIQUE KEY `booking_code` (`booking_code`),
  KEY `departure_id` (`departure_id`),
  KEY `promotion_id` (`promotion_id`),
  KEY `idx_bookings_customer` (`customer_id`),
  KEY `idx_bookings_status` (`booking_status`,`payment_status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `bookings`
--

INSERT INTO `bookings` (`booking_id`, `booking_code`, `departure_id`, `customer_id`, `promotion_id`, `booking_date`, `num_adults`, `num_children`, `num_infants`, `subtotal`, `discount_amount`, `total_amount`, `paid_amount`, `payment_status`, `booking_status`, `special_notes`, `is_rewarded`) VALUES
(1, 'BK202611001', 1, 1, 1, '2026-10-28 02:30:00', 2, 1, 1, 4990000, 249500, 4740500, 2500000, 'Đã đặt cọc', 'Đã xác nhận', 'Gia đình có trẻ nhỏ, xin xếp hàng ghế số 3 phía trước xe', 0),
(2, 'BK261031240', 1, 3, NULL, '2026-10-31 08:20:16', 1, 0, 0, 1850000, 0, 1850000, 1850000, 'Đã thanh toán đủ', 'Hoàn tất', '', 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `booking_passengers`
--

DROP TABLE IF EXISTS `booking_passengers`;
CREATE TABLE IF NOT EXISTS `booking_passengers` (
  `passenger_id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `fullname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` enum('Nam','Nữ','Khác') COLLATE utf8mb4_unicode_ci DEFAULT 'Nam',
  `birth_date` date DEFAULT NULL,
  `passenger_type` enum('Người lớn','Trẻ em','Em bé') COLLATE utf8mb4_unicode_ci DEFAULT 'Người lớn',
  `id_card` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CCCD/Hộ chiếu phục vụ mua bảo hiểm',
  `single_room` tinyint(1) DEFAULT '0' COMMENT '1: Yêu cầu ở phòng đơn riêng',
  PRIMARY KEY (`passenger_id`),
  KEY `booking_id` (`booking_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `booking_passengers`
--

INSERT INTO `booking_passengers` (`passenger_id`, `booking_id`, `fullname`, `gender`, `birth_date`, `passenger_type`, `id_card`, `single_room`) VALUES
(1, 1, 'Lê Hoàng Nam', 'Nam', '1990-05-12', 'Người lớn', '089098001234', 0),
(2, 1, 'Phạm Thu Thảo', 'Nữ', '1992-10-24', 'Người lớn', '089192004321', 0),
(3, 1, 'Lê Hoàng Minh', 'Nam', '2018-03-15', 'Trẻ em', NULL, 0),
(4, 1, 'Lê Thảo An', 'Nữ', '2023-07-08', 'Em bé', NULL, 0),
(5, 2, 'hueu', 'Nam', '2005-02-28', 'Người lớn', '089200009199', 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_name` (`category_name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `slug`, `description`) VALUES
(1, 'Tour Miền Tây - Đồng Bằng Sông Cửu Long', 'tour-mien-tay', 'Khám phá sông nước, chợ nổi, rừng tràm và hành hương tâm linh'),
(2, 'Tour Miền Trung - Con Đường Di Sản', 'tour-mien-trung', 'Thăm cố đô Huế, phố cổ Hội An, biển Đà Nẵng, hang động Quảng Bình'),
(3, 'Tour Miền Bắc - Danh Thắng Hùng Vĩ', 'tour-mien-bac', 'Khám phá thủ đô Hà Nội ngàn năm, kỳ quan Vịnh Hạ Long, Sa Pa mờ sương');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `customers`
--

DROP TABLE IF EXISTS `customers`;
CREATE TABLE IF NOT EXISTS `customers` (
  `customer_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL COMMENT 'Khách có tài khoản hoặc khách đặt vãng lai',
  `fullname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_card` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Số CCCD hoặc Hộ chiếu',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` enum('Nam','Nữ','Khác') COLLATE utf8mb4_unicode_ci DEFAULT 'Nam',
  `birth_date` date DEFAULT NULL,
  `reward_points` int DEFAULT '0' COMMENT 'Điểm thưởng tích lũy',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `uq_customers_email` (`email`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `customers`
--

INSERT INTO `customers` (`customer_id`, `user_id`, `fullname`, `email`, `phone`, `id_card`, `address`, `gender`, `birth_date`, `reward_points`, `created_at`) VALUES
(1, NULL, 'Lê Hoàng Nam', 'hoangnam@gmail.com', '0903123456', '089098001234', 'Phường Mỹ Xuyên, TP. Long Xuyên, An Giang', 'Nam', '1990-05-12', 150, '2026-10-28 16:08:58'),
(2, NULL, 'Nguyễn Thị Mai', 'mainguyen@gmail.com', '0918765432', '089195005678', 'Thị xã Tân Châu, An Giang', 'Nữ', '1995-08-20', 0, '2026-10-28 16:08:58'),
(3, 5, 'Nguyễn Văn Huy', 'huy@gmail.com', '0987654123', '089200003177', 'An Giang', 'Nam', '2001-06-13', 18, '2026-10-31 08:19:05');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `destinations`
--

DROP TABLE IF EXISTS `destinations`;
CREATE TABLE IF NOT EXISTS `destinations` (
  `destination_id` int NOT NULL AUTO_INCREMENT,
  `destination_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `region` enum('Miền Bắc','Miền Trung','Tây Nguyên','Đông Nam Bộ','Miền Tây','Quốc tế') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`destination_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `destinations`
--

INSERT INTO `destinations` (`destination_id`, `destination_name`, `region`, `description`) VALUES
(1, 'An Giang (Châu Đốc - Núi Sam - Trà Sư)', 'Miền Tây', 'Vùng đất tâm linh Bảy Núi hùng vĩ và hệ sinh thái rừng tràm ngập nước đặc trưng'),
(2, 'Đà Nẵng - Hội An', 'Miền Trung', 'Thành phố đáng sống với bãi biển Mỹ Khê và đô thị cổ di sản văn hóa thế giới'),
(3, 'Quảng Ninh (Vịnh Hạ Long)', 'Miền Bắc', 'Kỳ quan thiên nhiên thế giới với hàng nghìn hòn đảo đá vôi kỳ thú');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `invoices`
--

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE IF NOT EXISTS `invoices` (
  `invoice_id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL COMMENT 'Liên kết đơn đặt tour',
  `invoice_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Số hóa đơn (VD: HD2026-0001)',
  `invoice_series` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'C26TAA' COMMENT 'Ký hiệu mẫu hóa đơn',
  `issue_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Ngày lập hóa đơn',
  `buyer_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tên người mua / Đại diện đoàn',
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tên đơn vị / Cơ quan cần xuất hóa đơn VAT',
  `tax_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mã số thuế doanh nghiệp',
  `company_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Địa chỉ cơ quan theo ĐKKD',
  `buyer_email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Email nhận hóa đơn điện tử',
  `payment_method` enum('Chuyển khoản','Tiền mặt','VNPAY','Thẻ tín dụng') COLLATE utf8mb4_unicode_ci DEFAULT 'Chuyển khoản',
  `subtotal_amount` decimal(12,0) NOT NULL COMMENT 'Tổng tiền trước thuế',
  `vat_rate` decimal(4,1) DEFAULT '8.0' COMMENT 'Thuế suất VAT (%) (VD: 8% hoặc 10%)',
  `vat_amount` decimal(12,0) NOT NULL COMMENT 'Tiền thuế VAT',
  `total_amount` decimal(12,0) NOT NULL COMMENT 'Tổng cộng tiền thanh toán',
  `status` enum('Đã phát hành','Chờ duyệt','Đã hủy') COLLATE utf8mb4_unicode_ci DEFAULT 'Đã phát hành',
  `created_by` int DEFAULT NULL COMMENT 'Nhân viên kế toán xuất hóa đơn',
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `booking_id` (`booking_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `invoices`
--

INSERT INTO `invoices` (`invoice_id`, `booking_id`, `invoice_number`, `invoice_series`, `issue_date`, `buyer_name`, `company_name`, `tax_code`, `company_address`, `buyer_email`, `payment_method`, `subtotal_amount`, `vat_rate`, `vat_amount`, `total_amount`, `status`, `created_by`) VALUES
(1, 1, 'HD2026-0001', 'C26TAA', '2026-10-28 03:15:00', 'Lê Hoàng Nam', 'Trường Đại học An Giang - ĐHQG-HCM', '1600173428', 'Số 18 Ung Văn Khiêm, P. Đông Xuyên, TP. Long Xuyên, An Giang', 'hoangnam@gmail.com', 'Chuyển khoản', 4389352, 8.0, 351148, 4740500, 'Đã phát hành', 3),
(2, 2, 'HD2026-0002', 'C26TAA', '2026-10-31 08:44:09', 'Nguyễn Văn Huy', 'Nguyễn Văn Huy', '', '', 'huy@gmail.com', 'Chuyển khoản', 1712963, 8.0, 137037, 1850000, 'Đã phát hành', 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `invoice_details`
--

DROP TABLE IF EXISTS `invoice_details`;
CREATE TABLE IF NOT EXISTS `invoice_details` (
  `detail_id` int NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `item_type` enum('Vé người lớn','Vé trẻ em','Vé em bé','Phụ thu phòng đơn','Bảo hiểm','Dịch vụ khác') COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Mô tả chi tiết dòng mục',
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Khách' COMMENT 'Đơn vị tính',
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` decimal(12,0) NOT NULL,
  `discount_amount` decimal(12,0) DEFAULT '0',
  `amount` decimal(12,0) NOT NULL COMMENT 'Thành tiền = (quantity * unit_price) - discount',
  PRIMARY KEY (`detail_id`),
  KEY `invoice_id` (`invoice_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `invoice_details`
--

INSERT INTO `invoice_details` (`detail_id`, `invoice_id`, `item_type`, `item_name`, `unit`, `quantity`, `unit_price`, `discount_amount`, `amount`) VALUES
(1, 1, 'Vé người lớn', 'Tour Rừng Tràm Trà Sư - Miếu Bà Chúa Xứ (Người lớn)', 'Khách', 2, 1850000, 185000, 3515000),
(2, 1, 'Vé trẻ em', 'Tour Rừng Tràm Trà Sư - Miếu Bà Chúa Xứ (Trẻ em 5-11 tuổi)', 'Khách', 1, 1290000, 64500, 1225500),
(3, 1, 'Vé em bé', 'Vé em bé dưới 5 tuổi (Miễn phí tiền vé, bảo hiểm trọn gói)', 'Khách', 1, 0, 0, 0),
(4, 2, 'Vé người lớn', 'Hành Hương Miếu Bà Chúa Xứ - Khám Phá Rừng Tràm Trà Sư (Người lớn)', 'Khách', 1, 1850000, 0, 1850000);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `monthly_revenue_reports`
--

DROP TABLE IF EXISTS `monthly_revenue_reports`;
CREATE TABLE IF NOT EXISTS `monthly_revenue_reports` (
  `report_id` int NOT NULL AUTO_INCREMENT,
  `report_month` tinyint NOT NULL COMMENT 'Tháng báo cáo (1 - 12)',
  `report_year` smallint NOT NULL COMMENT 'Năm báo cáo (VD: 2026)',
  `total_bookings` int DEFAULT '0' COMMENT 'Tổng số đơn trong tháng',
  `confirmed_bookings` int DEFAULT '0' COMMENT 'Số đơn thành công',
  `cancelled_bookings` int DEFAULT '0' COMMENT 'Số đơn đã hủy',
  `gross_revenue` decimal(14,0) DEFAULT '0' COMMENT 'Doanh thu gộp',
  `discount_total` decimal(14,0) DEFAULT '0' COMMENT 'Tổng tiền giảm giá khuyến mãi',
  `net_revenue` decimal(14,0) DEFAULT '0' COMMENT 'Doanh thu thuần thực tế sau giảm trừ',
  `status` enum('Draft','Closed') COLLATE utf8mb4_unicode_ci DEFAULT 'Closed' COMMENT 'Trạng thái khóa sổ kế toán',
  `closed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_by` int DEFAULT NULL COMMENT 'Kế toán trưởng duyệt chốt sổ',
  PRIMARY KEY (`report_id`),
  UNIQUE KEY `unique_month_year` (`report_month`,`report_year`),
  KEY `closed_by` (`closed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `monthly_revenue_reports`
--

INSERT INTO `monthly_revenue_reports` (`report_id`, `report_month`, `report_year`, `total_bookings`, `confirmed_bookings`, `cancelled_bookings`, `gross_revenue`, `discount_total`, `net_revenue`, `status`, `closed_at`, `closed_by`) VALUES
(1, 10, 2026, 2, 2, 0, 6840000, 249500, 6590500, 'Closed', '2026-10-28 16:18:52', 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `payment_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_method` enum('Tiền mặt','Chuyển khoản QR','VNPAY','MoMo','Thẻ tín dụng') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_phase` enum('Đặt cọc','Thanh toán đợt 2','Tất toán','Phụ thu') COLLATE utf8mb4_unicode_ci DEFAULT 'Đặt cọc',
  `amount` decimal(12,0) NOT NULL,
  `transaction_ref` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mã tham chiếu ngân hàng/cổng thanh toán',
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('Thành công','Thất bại','Đang xử lý') COLLATE utf8mb4_unicode_ci DEFAULT 'Thành công',
  `processed_by` int DEFAULT NULL COMMENT 'Nhân viên kế toán xác nhận nhận tiền',
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `payment_code` (`payment_code`),
  KEY `booking_id` (`booking_id`),
  KEY `processed_by` (`processed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `payments`
--

INSERT INTO `payments` (`payment_id`, `booking_id`, `payment_code`, `payment_method`, `payment_phase`, `amount`, `transaction_ref`, `payment_date`, `status`, `processed_by`) VALUES
(1, 1, 'PAY202611001-01', 'Chuyển khoản QR', 'Đặt cọc', 2500000, 'VCB.MB12893847', '2026-10-28 02:45:00', 'Thành công', 3),
(2, 2, 'PAY261031227', 'Chuyển khoản QR', 'Đặt cọc', 925000, NULL, '2026-10-31 08:20:16', 'Thành công', 1),
(3, 2, 'PAY261031471', 'Tiền mặt', 'Tất toán', 925000, 'TIEN-MAT-HOAC-CK', '2026-10-31 08:44:09', 'Thành công', 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `promotions`
--

DROP TABLE IF EXISTS `promotions`;
CREATE TABLE IF NOT EXISTS `promotions` (
  `promotion_id` int NOT NULL AUTO_INCREMENT,
  `promo_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_type` enum('Percentage','Fixed') COLLATE utf8mb4_unicode_ci DEFAULT 'Percentage',
  `discount_value` decimal(12,0) NOT NULL COMMENT '% giảm hoặc số tiền giảm cố định',
  `min_order_amount` decimal(12,0) DEFAULT '0',
  `max_discount_amount` decimal(12,0) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `usage_limit` int DEFAULT '100',
  `used_count` int DEFAULT '0',
  `status` enum('Active','Expired','Disabled') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  PRIMARY KEY (`promotion_id`),
  UNIQUE KEY `promo_code` (`promo_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `promotions`
--

INSERT INTO `promotions` (`promotion_id`, `promo_code`, `description`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount_amount`, `start_date`, `end_date`, `usage_limit`, `used_count`, `status`) VALUES
(1, 'DUXUAN2026', 'Giảm 5% cho đơn đặt tour sớm dịp cuối năm', 'Percentage', 5, 2000000, 500000, '2026-10-01', '2026-12-31', 100, 1, 'Active'),
(2, 'TRIANAGU', 'Giảm trực tiếp 200.000đ tri ân sinh viên & giảng viên AGU', 'Fixed', 200000, 1500000, 200000, '2026-10-01', '2026-11-30', 50, 0, 'Active');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `reviews`
--

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE IF NOT EXISTS `reviews` (
  `review_id` int NOT NULL AUTO_INCREMENT,
  `tour_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `booking_id` int DEFAULT NULL,
  `rating` tinyint NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `review_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('Chờ duyệt','Đã duyệt','Ẩn') COLLATE utf8mb4_unicode_ci DEFAULT 'Đã duyệt',
  PRIMARY KEY (`review_id`),
  KEY `tour_id` (`tour_id`),
  KEY `customer_id` (`customer_id`),
  KEY `booking_id` (`booking_id`)
) ;

--
-- Đang đổ dữ liệu cho bảng `reviews`
--

INSERT INTO `reviews` (`review_id`, `tour_id`, `customer_id`, `booking_id`, `rating`, `comment`, `review_date`, `status`) VALUES
(1, 1, 1, 1, 5, 'Chuyến đi Rừng tràm Trà Sư rất đẹp và thanh bình, đồ ăn trưa miền Tây đậm đà, hướng dẫn viên nhiệt tình vui vẻ!', '2026-10-28 03:00:00', 'Đã duyệt'),
(2, 1, 3, 2, 5, 'quá tuyệt vời', '2026-10-31 09:39:02', 'Đã duyệt');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `role_id` int NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tên vai trò: Admin, Sales, Operator, Guide, Accountant',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `description`) VALUES
(1, 'Administrator', 'Quản trị viên toàn quyền hệ thống'),
(2, 'Sales Staff', 'Nhân viên tư vấn, tiếp nhận và duyệt đơn đặt tour'),
(3, 'Tour Operator', 'Điều hành tour, quản lý lịch khởi hành và phân bổ xe/hướng dẫn viên'),
(4, 'Tour Guide', 'Hướng dẫn viên lữ hành phụ trách đoàn'),
(5, 'Accountant', 'Kế toán viên xác nhận giao dịch thanh toán và xuất hóa đơn'),
(6, 'Customer', 'Du khách đăng ký tài khoản trực tuyến trên website');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tours`
--

DROP TABLE IF EXISTS `tours`;
CREATE TABLE IF NOT EXISTS `tours` (
  `tour_id` int NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL,
  `destination_id` int NOT NULL,
  `tour_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Mã định danh tour (VD: TOUR-AG-01)',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_days` int NOT NULL DEFAULT '1',
  `duration_nights` int NOT NULL DEFAULT '0',
  `start_location` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Long Xuyên, An Giang',
  `transportation` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Xe du lịch đời mới / Máy bay',
  `included_services` text COLLATE utf8mb4_unicode_ci COMMENT 'Dịch vụ bao gồm: Xe, Ăn uống, Khách sạn, Vé tham quan',
  `excluded_services` text COLLATE utf8mb4_unicode_ci COMMENT 'Dịch vụ không bao gồm: Chi phí cá nhân, Thuế VAT',
  `thumbnail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'default_tour.jpg',
  `status` enum('Active','Draft','Hidden') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tour_id`),
  UNIQUE KEY `tour_code` (`tour_code`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_tours_destination` (`destination_id`),
  KEY `idx_tours_category` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tours`
--

INSERT INTO `tours` (`tour_id`, `category_id`, `destination_id`, `tour_code`, `title`, `slug`, `duration_days`, `duration_nights`, `start_location`, `transportation`, `included_services`, `excluded_services`, `thumbnail`, `status`, `created_at`) VALUES
(1, 1, 1, 'TOUR-AG-01', 'Hành Hương Miếu Bà Chúa Xứ - Khám Phá Rừng Tràm Trà Sư', 'hanh-huong-mieu-ba-chua-xu-rung-tram-tra-su', 2, 1, 'TP. Long Xuyên / Cần Thơ', 'Xe du lịch 29-45 chỗ máy lạnh đời mới', 'Khách sạn 3 sao Châu Đốc, 3 bữa chính + 1 bữa sáng buffet, vé tham quan, bảo hiểm du lịch 50 triệu/vụ', 'Chi phí cá nhân, đồ uống gọi thêm, tiền tip hướng dẫn viên, VAT', 'https://images.unsplash.com/photo-1596422846543-75c6fc197f07?w=800', 'Active', '2026-10-28 16:08:58'),
(2, 2, 2, 'TOUR-DN-02', 'Đà Nẵng - Bán Đảo Sơn Trà - Phố Cổ Hội An - Bà Nà Hills', 'da-nang-son-tra-hoi-an-ba-na-hills', 3, 2, 'TP. Hồ Chí Minh / Hà Nội', 'Máy bay khứ hồi + Xe du lịch phục vụ theo đoàn', 'Vé máy bay khứ hồi có 20kg ký gửi, khách sạn 4 sao gần biển, vé cáp treo Bà Nà, ăn uống theo lịch trình', 'Vé tham quan Hầm rượu cổ Debay, thuế VAT, tiền tip', 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?w=800', 'Active', '2026-10-28 16:08:58'),
(3, 3, 3, 'TOUR-HL-03', 'Hà Nội - Du Thuyền Vịnh Hạ Long - Đảo Tuần Châu', 'ha-noi-du-thuyen-vinh-ha-long', 4, 3, 'Hà Nội', 'Xe ô tô Limousine VIP + Du thuyền 4 sao', '1 đêm du thuyền 4 sao ngắm vịnh, 2 đêm khách sạn Hạ Long, trọn gói các bữa ăn hải sản cao cấp', 'Chi phí chèo thuyền Kayak tự túc, giặt ủi, VAT', 'https://images.unsplash.com/photo-1528127269322-539801943592?w=800', 'Active', '2026-10-28 16:08:58');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tour_departures`
--

DROP TABLE IF EXISTS `tour_departures`;
CREATE TABLE IF NOT EXISTS `tour_departures` (
  `departure_id` int NOT NULL AUTO_INCREMENT,
  `tour_id` int NOT NULL,
  `departure_date` date NOT NULL,
  `return_date` date NOT NULL,
  `max_seats` int NOT NULL DEFAULT '30',
  `booked_seats` int NOT NULL DEFAULT '0',
  `tour_guide_id` int DEFAULT NULL COMMENT 'Hướng dẫn viên được phân công phụ trách',
  `status` enum('Mở bán','Sắp hết chỗ','Đã khóa sổ','Đã khởi hành','Đã hoàn tất','Đã hủy') COLLATE utf8mb4_unicode_ci DEFAULT 'Mở bán',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`departure_id`),
  KEY `tour_id` (`tour_id`),
  KEY `tour_guide_id` (`tour_guide_id`),
  KEY `idx_departures_date` (`departure_date`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tour_departures`
--

INSERT INTO `tour_departures` (`departure_id`, `tour_id`, `departure_date`, `return_date`, `max_seats`, `booked_seats`, `tour_guide_id`, `status`, `created_at`) VALUES
(1, 1, '2026-11-14', '2026-11-15', 30, 5, 4, 'Mở bán', '2026-10-28 16:08:58'),
(2, 1, '2026-11-21', '2026-11-22', 30, 0, 4, 'Mở bán', '2026-10-28 16:08:58'),
(3, 2, '2026-11-20', '2026-11-22', 25, 3, 4, 'Mở bán', '2026-10-28 16:08:58'),
(4, 3, '2026-11-25', '2026-11-28', 20, 0, NULL, 'Mở bán', '2026-10-28 16:08:58');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tour_images`
--

DROP TABLE IF EXISTS `tour_images`;
CREATE TABLE IF NOT EXISTS `tour_images` (
  `image_id` int NOT NULL AUTO_INCREMENT,
  `tour_id` int NOT NULL,
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`image_id`),
  KEY `tour_id` (`tour_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tour_itineraries`
--

DROP TABLE IF EXISTS `tour_itineraries`;
CREATE TABLE IF NOT EXISTS `tour_itineraries` (
  `itinerary_id` int NOT NULL AUTO_INCREMENT,
  `tour_id` int NOT NULL,
  `day_number` int NOT NULL COMMENT 'Ngày thứ 1, Ngày thứ 2...',
  `day_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tiêu đề ngày: TP. Long Xuyên - Rừng Tràm Trà Sư',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Hoạt động tham quan cụ thể trong ngày',
  `meals` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT 'Sáng, Trưa, Tối' COMMENT 'Các bữa ăn được phục vụ',
  `accommodation` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT 'Khách sạn 3 sao' COMMENT 'Nơi lưu trú đêm',
  PRIMARY KEY (`itinerary_id`),
  KEY `tour_id` (`tour_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tour_itineraries`
--

INSERT INTO `tour_itineraries` (`itinerary_id`, `tour_id`, `day_number`, `day_title`, `description`, `meals`, `accommodation`) VALUES
(1, 1, 1, 'Long Xuyên - Rừng Tràm Trà Sư - Châu Đốc', 'Sáng: Đón khách tại điểm hẹn TP. Long Xuyên, khởi hành đi Tịnh Biên. Đến Rừng tràm Trà Sư, du khách lên tắc ráng len lỏi qua thảm bèo xanh mướt ngắm chim nước. Trưa: Thưởng thức đặc sản lẩu cá linh bông điên điển, cá lóc nướng trui. Chiều: Về TP. Châu Đốc nhận phòng khách sạn 3 sao nghỉ ngơi. Tối: Xe đưa đoàn viếng Miếu Bà Chúa Xứ Núi Sam và Tây An Cổ Tự về đêm.', 'Trưa, Tối', 'Khách sạn Châu Phố 3 sao'),
(2, 1, 2, 'Châu Đốc - Làng Bè Nổi Chăm - Mua Sắm Chợ Châu Đốc - Trở Về', 'Sáng: Đi thuyền tham quan làng bè nuôi cá basa trên sông Hậu và làng dệt thổ cẩm người Chăm Đa Phước. Sau đó ghé Chợ Châu Đốc mua sắm đặc sản mắm cá, đường thốt nốt. Trưa: Ăn trưa tại nhà hàng. Chiều: Khởi hành về lại điểm xuất phát TP. Long Xuyên, hướng dẫn viên chia tay đoàn.', 'Sáng, Trưa', 'Không có (Trả phòng)'),
(3, 2, 1, 'Đón Sân Bay Đà Nẵng - Bán Đảo Sơn Trà - Phố Cổ Hội An', 'Sáng: Đón khách tại sân bay Đà Nẵng, nhận phòng khách sạn 4 sao nghỉ ngơi. Chiều: Viếng chùa Linh Ứng Bãi Bụt trên Bán đảo Sơn Trà. Tối: Khởi hành đi Phố cổ Hội An, tham quan Chùa Cầu, thả đèn hoa đăng trên sông Hoài và thưởng thức cao lầu.', 'Trưa, Tối', 'Khách sạn 4 sao Đà Nẵng'),
(4, 2, 2, 'Khu Du Lịch Bà Nà Hills - Cầu Vàng - Làng Pháp', 'Sáng: Dùng điểm tâm buffet, khởi hành lên đỉnh Bà Nà bằng cáp treo đạt kỷ lục thế giới. Check-in Cầu Vàng, vườn hoa Le Jardin D’Amour, Khu Làng Pháp. Trưa: Ăn buffet tại nhà hàng 4 sao trên đỉnh Bà Nà. Chiều: Vui chơi tại Fantasy Park và tắm biển Mỹ Khê.', 'Sáng, Trưa, Tối', 'Khách sạn 4 sao Đà Nẵng'),
(5, 2, 3, 'Đà Nẵng - Chợ Hàn - Mua Sắm Đặc Sản - Tiễn Sân Bay', 'Sáng: Ăn sáng tại khách sạn, tự do tắm biển Mỹ Khê. Đoàn ghé Chợ Hàn mua sắm đặc sản miền Trung. Trưa: Ăn trưa đặc sản bánh tráng thịt heo 2 đầu da, xe tiễn đoàn ra sân bay Đà Nẵng.', 'Sáng, Trưa', 'Không có (Trả phòng)'),
(6, 3, 1, 'Hà Nội - Hạ Long - Nghỉ Đêm Du Thuyền 4 Sao', 'Sáng: Xe Limousine đón đoàn tại Hà Nội đi Quảng Ninh qua cao tốc. Trưa: Lên du thuyền 4 sao tại cảng Tuần Châu, thưởng thức bữa trưa hải sản khi tàu lướt qua Hòn Đỉnh Hương, Hòn Trống Mái. Chiều: Thăm Hang Sửng Sốt và tắm biển đảo Titop.', 'Trưa, Tối', 'Du thuyền 4 sao Vịnh Hạ Long'),
(7, 3, 2, 'Ngắm Bình Minh Trên Vịnh - Làng Ngọc Trai - Bãi Cháy', 'Sáng: Tập dưỡng sinh đón bình minh trên boong tàu, tham quan khu nuôi cấy Ngọc Trai. Trưa: Trả phòng du thuyền, ăn trưa tại nhà hàng Bãi Cháy và nhận phòng khách sạn 4 sao. Tối: Tự do dạo chợ đêm Hạ Long.', 'Sáng, Trưa, Tối', 'Khách sạn 4 sao Hạ Long'),
(8, 3, 3, 'Khám Phá Đảo Tuần Châu - Bảo Tàng Quảng Ninh', 'Sáng: Tham quan và chụp ảnh tại Bảo tàng Quảng Ninh, Cung Cá Heo. Chiều: Tự do vui chơi tại khu du lịch Quốc tế Tuần Châu hoặc công viên Sun World Ha Long.', 'Sáng, Trưa, Tối', 'Khách sạn 4 sao Hạ Long'),
(9, 3, 4, 'Hạ Long - Mua Sắm Chả Mực - Trở Về Hà Nội', 'Sáng: Ăn sáng buffet, ghé chợ Hạ Long mua đặc sản chả mực giã tay. Trưa: Ăn trưa tại nhà hàng, sau đó lên xe Limousine trở về Hà Nội, kết thúc hành trình.', 'Sáng, Trưa', 'Không có (Trả phòng)');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tour_prices`
--

DROP TABLE IF EXISTS `tour_prices`;
CREATE TABLE IF NOT EXISTS `tour_prices` (
  `price_id` int NOT NULL AUTO_INCREMENT,
  `departure_id` int NOT NULL,
  `price_adult` decimal(12,0) NOT NULL COMMENT 'Giá vé người lớn (từ 12 tuổi)',
  `price_child` decimal(12,0) NOT NULL COMMENT 'Giá vé trẻ em (5 - 11 tuổi)',
  `price_infant` decimal(12,0) DEFAULT '0' COMMENT 'Giá vé em bé (dưới 5 tuổi)',
  `single_supplement` decimal(12,0) DEFAULT '0' COMMENT 'Phụ thu phòng đơn',
  PRIMARY KEY (`price_id`),
  UNIQUE KEY `uq_departure_price` (`departure_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tour_prices`
--

INSERT INTO `tour_prices` (`price_id`, `departure_id`, `price_adult`, `price_child`, `price_infant`, `single_supplement`) VALUES
(1, 1, 1850000, 1290000, 0, 450000),
(2, 2, 1950000, 1360000, 0, 500000),
(3, 3, 4290000, 3190000, 500000, 1200000),
(4, 4, 5490000, 4100000, 800000, 1500000);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `role_id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fullname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Active','Inactive','Locked') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`user_id`, `role_id`, `username`, `password_hash`, `fullname`, `email`, `phone`, `status`, `created_at`) VALUES
(1, 1, 'hieu_pm', '$2y$10$55vnATBPKY.kjKjvp7HaH.7iCj8nrzS3ZpqNpMt84590QOX0NfX7W', 'Nguyễn Trung Hiếu', 'hieu.pm@dulichvietnam.vn', '0912345678', 'Active', '2026-10-28 16:08:58'),
(2, 3, 'huy_dev', '$2y$10$55vnATBPKY.kjKjvp7HaH.7iCj8nrzS3ZpqNpMt84590QOX0NfX7W', 'Huỳnh Công Huy', 'huy.dev@dulichvietnam.vn', '0912345679', 'Active', '2026-10-28 16:08:58'),
(3, 2, 'khai_qa', '$2y$10$55vnATBPKY.kjKjvp7HaH.7iCj8nrzS3ZpqNpMt84590QOX0NfX7W', 'Trương Quốc Khải', 'khai.qa@dulichvietnam.vn', '0912345680', 'Active', '2026-10-28 16:08:58'),
(4, 4, 'guide_nam', '$2y$10$55vnATBPKY.kjKjvp7HaH.7iCj8nrzS3ZpqNpMt84590QOX0NfX7W', 'Trần Văn Nam', 'nam.guide@dulichvietnam.vn', '0988112233', 'Active', '2026-10-28 16:08:58'),
(5, 6, 'huy_khach', '$2y$10$rOqi.tRfGOkxV6cK7M4yiOywzeD0phIOSd4BwJjWxjF70r/l9MC3O', 'Nguyễn Văn Huy', 'huy@gmail.com', '0987654123', 'Active', '2026-10-31 08:19:05');

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_monthly_revenue`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_monthly_revenue`;
CREATE TABLE IF NOT EXISTS `v_monthly_revenue` (
`month` int
,`subtotal_revenue` decimal(34,0)
,`total_invoices_issued` bigint
,`total_monthly_revenue` decimal(34,0)
,`total_vat_collected` decimal(34,0)
,`year` year
);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_yearly_revenue`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_yearly_revenue`;
CREATE TABLE IF NOT EXISTS `v_yearly_revenue` (
`subtotal_revenue` decimal(34,0)
,`total_invoices_issued` bigint
,`total_vat_collected` decimal(34,0)
,`total_yearly_revenue` decimal(34,0)
,`year` year
);

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_monthly_revenue`
--
DROP TABLE IF EXISTS `v_monthly_revenue`;

DROP VIEW IF EXISTS `v_monthly_revenue`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_monthly_revenue`  AS SELECT year(`invoices`.`issue_date`) AS `year`, month(`invoices`.`issue_date`) AS `month`, count(`invoices`.`invoice_id`) AS `total_invoices_issued`, sum(`invoices`.`subtotal_amount`) AS `subtotal_revenue`, sum(`invoices`.`vat_amount`) AS `total_vat_collected`, sum(`invoices`.`total_amount`) AS `total_monthly_revenue` FROM `invoices` WHERE (`invoices`.`status` = 'Đã phát hành') GROUP BY year(`invoices`.`issue_date`), month(`invoices`.`issue_date`) ORDER BY `year` DESC, `month` DESC ;

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_yearly_revenue`
--
DROP TABLE IF EXISTS `v_yearly_revenue`;

DROP VIEW IF EXISTS `v_yearly_revenue`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_yearly_revenue`  AS SELECT year(`invoices`.`issue_date`) AS `year`, count(`invoices`.`invoice_id`) AS `total_invoices_issued`, sum(`invoices`.`subtotal_amount`) AS `subtotal_revenue`, sum(`invoices`.`vat_amount`) AS `total_vat_collected`, sum(`invoices`.`total_amount`) AS `total_yearly_revenue` FROM `invoices` WHERE (`invoices`.`status` = 'Đã phát hành') GROUP BY year(`invoices`.`issue_date`) ORDER BY `year` DESC ;

--
-- Ràng buộc đối với các bảng kết xuất
--

--
-- Ràng buộc cho bảng `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`departure_id`) REFERENCES `tour_departures` (`departure_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`promotion_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `booking_passengers`
--
ALTER TABLE `booking_passengers`
  ADD CONSTRAINT `booking_passengers_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `invoice_details`
--
ALTER TABLE `invoice_details`
  ADD CONSTRAINT `invoice_details_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `monthly_revenue_reports`
--
ALTER TABLE `monthly_revenue_reports`
  ADD CONSTRAINT `monthly_revenue_reports_ibfk_1` FOREIGN KEY (`closed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`tour_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `tours`
--
ALTER TABLE `tours`
  ADD CONSTRAINT `tours_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `tours_ibfk_2` FOREIGN KEY (`destination_id`) REFERENCES `destinations` (`destination_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `tour_departures`
--
ALTER TABLE `tour_departures`
  ADD CONSTRAINT `tour_departures_ibfk_1` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`tour_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tour_departures_ibfk_2` FOREIGN KEY (`tour_guide_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `tour_images`
--
ALTER TABLE `tour_images`
  ADD CONSTRAINT `tour_images_ibfk_1` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`tour_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `tour_itineraries`
--
ALTER TABLE `tour_itineraries`
  ADD CONSTRAINT `tour_itineraries_ibfk_1` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`tour_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `tour_prices`
--
ALTER TABLE `tour_prices`
  ADD CONSTRAINT `tour_prices_ibfk_1` FOREIGN KEY (`departure_id`) REFERENCES `tour_departures` (`departure_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE RESTRICT ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
