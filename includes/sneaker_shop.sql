-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th3 17, 2026 lúc 10:37 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `sneaker_shop`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Sneaker Thể Thao', 'Giày sneaker dành cho hoạt động thể thao', '2026-03-17 07:45:05'),
(2, 'Sneaker Lifestyle', 'Giày sneaker thời trang hàng ngày', '2026-03-17 07:45:05'),
(3, 'Sneaker Running', 'Giày chạy bộ chuyên dụng hiệu năng cao', '2026-03-17 07:45:05'),
(4, 'Sneaker Basketball', 'Giày bóng rổ chuyên nghiệp', '2026-03-17 07:45:05'),
(5, 'Sneaker Skateboard', 'Giày trượt ván bền bỉ', '2026-03-17 07:45:05');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `import_details`
--

CREATE TABLE `import_details` (
  `id` int(11) NOT NULL,
  `receipt_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `import_price` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `import_details`
--

INSERT INTO `import_details` (`id`, `receipt_id`, `product_id`, `quantity`, `import_price`) VALUES
(1, 1, 1, 60, 1500000.00),
(2, 1, 2, 40, 2000000.00),
(3, 1, 3, 35, 2200000.00),
(4, 1, 6, 50, 1200000.00),
(5, 1, 7, 35, 2500000.00),
(6, 2, 11, 25, 3000000.00),
(7, 2, 12, 20, 3500000.00),
(8, 2, 13, 18, 3200000.00),
(9, 2, 14, 30, 1700000.00),
(10, 2, 15, 20, 3800000.00),
(11, 3, 4, 35, 2600000.00),
(12, 3, 5, 40, 1800000.00),
(13, 3, 16, 45, 1100000.00),
(14, 3, 17, 35, 1200000.00),
(15, 4, 8, 30, 1900000.00),
(16, 4, 9, 35, 1400000.00),
(17, 4, 10, 40, 1600000.00),
(18, 4, 18, 30, 1600000.00),
(19, 4, 19, 25, 2800000.00),
(20, 4, 20, 50, 900000.00),
(21, 5, 1, 30, 1550000.00),
(22, 5, 6, 25, 1250000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `import_receipts`
--

CREATE TABLE `import_receipts` (
  `id` int(11) NOT NULL,
  `receipt_code` varchar(50) NOT NULL,
  `import_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','completed') DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `import_receipts`
--

INSERT INTO `import_receipts` (`id`, `receipt_code`, `import_date`, `notes`, `status`, `created_by`, `created_at`) VALUES
(1, 'PN2025100101', '2025-10-01', 'Nhập hàng đầu kỳ Q4/2025 - Nike & Adidas', 'completed', 1, '2026-03-17 07:45:05'),
(2, 'PN2025101501', '2025-10-15', 'Nhập Jordan & New Balance theo đơn đặt hàng', 'completed', 1, '2026-03-17 07:45:05'),
(3, 'PN2025110101', '2025-11-01', 'Nhập bổ sung Nike Dunk & Vans chuẩn bị cuối năm', 'completed', 1, '2026-03-17 07:45:05'),
(4, 'PN2025111501', '2025-11-15', 'Đa thương hiệu: Adidas NMD, Samba, Puma, ASICS, Converse', 'completed', 1, '2026-03-17 07:45:05'),
(5, 'PN2026010101', '2026-01-10', 'Nhập hàng Q1/2026 - chưa hoàn thành', 'pending', 1, '2026-03-17 07:45:05');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_code` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `receiver_name` varchar(200) DEFAULT NULL,
  `receiver_phone` varchar(20) DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `ward` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `payment_method` enum('cash','transfer','online') DEFAULT 'cash',
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','confirmed','delivered','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `orders`
--

INSERT INTO `orders` (`id`, `order_code`, `user_id`, `receiver_name`, `receiver_phone`, `shipping_address`, `ward`, `district`, `city`, `payment_method`, `total_amount`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'DH20251101001', 2, 'Nguyễn Văn A', '0901234567', '12 Nguyễn Huệ', 'Bến Nghé', 'Quận 1', 'TP. Hồ Chí Minh', 'cash', 3780000.00, 'delivered', NULL, '2025-11-10 02:15:00', '2026-03-17 07:45:05'),
(2, 'DH20251115002', 3, 'Trần Thị B', '0912345678', '45 Lê Văn Sỹ', 'Phường 12', 'Quận 3', 'TP. Hồ Chí Minh', 'transfer', 4500000.00, 'delivered', NULL, '2025-11-20 07:30:00', '2026-03-17 07:45:06'),
(3, 'DH20251201003', 4, 'Lê Hoàng Hùng', '0923456789', '78 Hoàng Diệu', 'Phường 9', 'Quận 4', 'TP. Hồ Chí Minh', 'cash', 8010000.00, 'confirmed', NULL, '2025-12-01 03:00:00', '2026-03-17 07:45:06'),
(4, 'DH20260101004', 2, 'Nguyễn Văn A', '0901234567', '12 Nguyễn Huệ', 'Bến Nghé', 'Quận 1', 'TP. Hồ Chí Minh', 'online', 5250000.00, 'pending', NULL, '2026-01-05 09:45:00', '2026-03-17 07:45:06'),
(5, 'DH20260110005', 3, 'Trần Thị B', '0912345678', '45 Lê Văn Sỹ', 'Phường 12', 'Quận 3', 'TP. Hồ Chí Minh', 'cash', 1595000.00, 'cancelled', NULL, '2026-01-10 04:00:00', '2026-03-17 07:45:06');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_details`
--

CREATE TABLE `order_details` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `order_details`
--

INSERT INTO `order_details` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`) VALUES
(1, 1, 1, 1, 2100000.00),
(2, 1, 6, 1, 1740000.00),
(3, 2, 11, 1, 4500000.00),
(4, 3, 2, 1, 2700000.00),
(5, 3, 7, 2, 3250000.00),
(6, 4, 12, 1, 5250000.00),
(7, 5, 16, 1, 1595000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'đôi',
  `stock_quantity` int(11) DEFAULT 0,
  `import_price` decimal(15,2) DEFAULT 0.00,
  `profit_rate` decimal(5,2) DEFAULT 30.00,
  `image` varchar(255) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `gender` enum('nam','nu','unisex') DEFAULT 'unisex',
  `available_sizes` varchar(255) DEFAULT '',
  `color` varchar(100) DEFAULT NULL,
  `material` varchar(200) DEFAULT NULL,
  `origin` varchar(100) DEFAULT NULL,
  `status` enum('active','hidden') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `products`
--

INSERT INTO `products` (`id`, `code`, `name`, `category_id`, `description`, `unit`, `stock_quantity`, `import_price`, `profit_rate`, `image`, `brand`, `gender`, `available_sizes`, `color`, `material`, `origin`, `status`, `created_at`, `updated_at`) VALUES
(1, 'NK001', 'Nike Air Force 1 Low', 2, 'Giày Nike Air Force 1 Low cổ điển, thiết kế trắng tinh khiết không bao giờ lỗi mốt.', 'đôi', 59, 1500000.00, 40.00, 'nike-air-force-1-low.jpg', 'Nike', 'unisex', '36,37,38,39,40,41,42,43', 'Trắng', 'Da tổng hợp cao cấp', 'Việt Nam', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(2, 'NK002', 'Nike Air Max 270', 1, 'Đệm khí Air Max lớn nhất từ trước đến nay, mang lại cảm giác êm ái tuyệt vời cả ngày dài.', 'đôi', 39, 2000000.00, 35.00, 'nike-air-max-270.jpg', 'Nike', 'unisex', '38,39,40,41,42,43,44', 'Đen/Trắng', 'Vải Flyknit & da tổng hợp', 'Indonesia', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(3, 'NK003', 'Nike Air Max 97', 1, 'Thiết kế gợn sóng biểu tượng từ năm 1997, đệm khí toàn phần cực kỳ êm.', 'đôi', 35, 2200000.00, 38.00, 'nike-air-max-97.jpg', 'Nike', 'unisex', '38,39,40,41,42,43', 'Bạc/Đỏ', 'Da tổng hợp & lưới', 'Trung Quốc', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(4, 'NK004', 'Nike React Infinity Run', 3, 'Giày chạy bộ với đệm React cực êm, hỗ trợ chống chấn thương hiệu quả.', 'đôi', 35, 2600000.00, 32.00, 'nike-react-infinity-run.jpg', 'Nike', 'unisex', '38,39,40,41,42,43,44', 'Xanh/Trắng', 'Vải mesh thoáng khí', 'Việt Nam', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(5, 'NK005', 'Nike Dunk Low Retro', 2, 'Phiên bản Retro của Nike Dunk Low, màu sắc tươi tắn, kết hợp hoàn hảo với mọi outfit.', 'đôi', 40, 1800000.00, 42.00, 'nike-dunk-low-retro.jpg', 'Nike', 'unisex', '36,37,38,39,40,41,42,43', 'Trắng/Xanh', 'Da thật', 'Indonesia', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(6, 'AD001', 'Adidas Stan Smith', 2, 'Biểu tượng thời trang đường phố suốt 50 năm qua, thiết kế đơn giản mà tinh tế.', 'đôi', 49, 1200000.00, 45.00, 'adidas-stan-smith.jpg', 'Adidas', 'unisex', '36,37,38,39,40,41,42', 'Trắng/Xanh lá', 'Da tự nhiên', 'Trung Quốc', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(7, 'AD002', 'Adidas Ultraboost 22', 3, 'Công nghệ Boost đẳng cấp trả lại năng lượng mỗi bước chạy, lý tưởng cho runner nghiêm túc.', 'đôi', 33, 2500000.00, 30.00, 'adidas-ultraboost-22.jpg', 'Adidas', 'unisex', '38,39,40,41,42,43,44', 'Đen/Xanh navy', 'Vải Primeknit+', 'Việt Nam', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(8, 'AD003', 'Adidas NMD R1', 2, 'Phong cách street fashion tối thượng với đệm Boost thoải mái, phù hợp mọi hoàn cảnh.', 'đôi', 30, 1900000.00, 36.00, 'adidas-nmd-r1.jpg', 'Adidas', 'unisex', '38,39,40,41,42,43', 'Xám/Đỏ', 'Vải Primeknit', 'Đức', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(9, 'AD004', 'Adidas Gazelle', 2, 'Phong cách retro 70s, mũi giày da mềm mại, lót lưỡi gà đặc trưng tạo nên nét huyền thoại.', 'đôi', 35, 1400000.00, 40.00, 'adidas-gazelle.jpg', 'Adidas', 'unisex', '36,37,38,39,40,41,42', 'Xanh navy', 'Da lộn (suede)', 'Ấn Độ', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(10, 'AD005', 'Adidas Samba OG', 2, 'Cú trở lại ngoạn mục của dòng Samba huyền thoại, chiếm lĩnh street style toàn cầu.', 'đôi', 40, 1600000.00, 38.00, 'adidas-samba-og.jpg', 'Adidas', 'unisex', '36,37,38,39,40,41,42,43', 'Đen/Trắng', 'Da tự nhiên', 'Ấn Độ', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(11, 'JD001', 'Jordan 1 Retro High OG', 4, 'Huyền thoại bóng rổ trở thành biểu tượng streetwear, thiết kế Bred classic không đổi theo thời gian.', 'đôi', 24, 3000000.00, 50.00, 'jordan-1-retro-high-og.jpg', 'Jordan', 'nam', '40,41,42,43,44,45', 'Đỏ/Trắng/Đen', 'Da thật cao cấp', 'Trung Quốc', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(12, 'JD002', 'Jordan 4 Retro', 4, 'Air Jordan 4 với thiết kế lưới đặc trưng và Air unit ở đế giữa, một trong những AJ được yêu thích nhất.', 'đôi', 20, 3500000.00, 48.00, 'jordan-4-retro.jpg', 'Jordan', 'nam', '40,41,42,43,44,45', 'Trắng/Xám', 'Da thật & lưới', 'Trung Quốc', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(13, 'JD003', 'Jordan 11 Retro Low', 4, 'Thiết kế patent leather bóng loáng sang trọng kết hợp outsole trong suốt, cực kỳ đặc biệt.', 'đôi', 18, 3200000.00, 45.00, 'jordan-11-retro-low.jpg', 'Jordan', 'nam', '40,41,42,43,44,45', 'Đen/Varsity Royal', 'Da bóng & vải', 'Trung Quốc', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(14, 'NB001', 'New Balance 574', 2, 'Dòng giày biểu tượng từ thập niên 80, comfort từng bước chân với đế ENCAP độc quyền.', 'đôi', 30, 1700000.00, 38.00, 'new-balance-574.jpg', 'New Balance', 'unisex', '37,38,39,40,41,42,43', 'Xanh navy/Trắng', 'Da lộn & vải', 'Mỹ', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(15, 'NB002', 'New Balance 990v6', 3, 'Made in USA, chất lượng đỉnh cao, đệm êm ái chuẩn mực cho runner nghiêm túc.', 'đôi', 20, 3800000.00, 35.00, 'new-balance-990v6.jpg', 'New Balance', 'unisex', '38,39,40,41,42,43,44', 'Xám', 'Da lộn & lưới cao cấp', 'Mỹ', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(16, 'VN001', 'Vans Old Skool', 5, 'Giày skate huyền thoại với sọc jazz đặc trưng, đế waffle bám đường tuyệt vời.', 'đôi', 45, 1100000.00, 45.00, 'vans-old-skool.jpg', 'Vans', 'unisex', '36,37,38,39,40,41,42,43', 'Đen/Trắng', 'Canvas & da lộn', 'Việt Nam', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(17, 'VN002', 'Vans Sk8-Hi', 5, 'Cổ cao hỗ trợ mắt cá chân, padding dày, lý tưởng cho ván trượt và street style.', 'đôi', 35, 1200000.00, 43.00, 'vans-sk8-hi.jpg', 'Vans', 'unisex', '36,37,38,39,40,41,42,43,44', 'Trắng/Đen', 'Canvas', 'Trung Quốc', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(18, 'PU001', 'Puma RS-X', 1, 'Running System tái sinh đầy màu sắc, đế dày chunky cực hot, công nghệ RS đệm êm.', 'đôi', 30, 1600000.00, 40.00, 'puma-rs-x.jpg', 'Puma', 'unisex', '38,39,40,41,42,43', 'Trắng/Vàng/Đỏ', 'Da tổng hợp & lưới', 'Việt Nam', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(19, 'AS001', 'ASICS Gel-Kayano 30', 3, 'Kiểm soát motion tối ưu với GEL technology, lý tưởng cho overpronation.', 'đôi', 25, 2800000.00, 30.00, 'asics-gel-kayano-30.jpg', 'ASICS', 'nu', '35,36,37,38,39,40,41', 'Tím/Hồng', 'FlyteFoam & lưới', 'Việt Nam', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34'),
(20, 'CF001', 'Converse Chuck Taylor All Star', 2, 'Đôi giày vải huyền thoại có mặt trên hơn 100 quốc gia, không bao giờ lỗi thời.', 'đôi', 50, 900000.00, 50.00, 'converse-chuck-taylor-all-star.jpg', 'Converse', 'unisex', '36,37,38,39,40,41,42,43', 'Đen', 'Canvas', 'Việt Nam', 'active', '2026-03-17 07:45:05', '2026-03-17 09:29:34');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(200) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `ward` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `role` enum('customer','admin') DEFAULT 'customer',
  `status` enum('active','locked') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `address`, `ward`, `district`, `city`, `role`, `status`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quản Trị Viên', 'admin@sneakershop.vn', '0901000001', '1 Lê Lợi', 'Bến Nghé', 'Quận 1', 'TP. Hồ Chí Minh', 'admin', 'active', '2026-03-17 07:45:05'),
(2, 'nguyenvana', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nguyễn Văn A', 'vana@email.com', '0901234567', '12 Nguyễn Huệ', 'Bến Nghé', 'Quận 1', 'TP. Hồ Chí Minh', 'customer', 'active', '2026-03-17 07:45:05'),
(3, 'tranthib', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Trần Thị B', 'thib@email.com', '0912345678', '45 Lê Văn Sỹ', 'Phường 12', 'Quận 3', 'TP. Hồ Chí Minh', 'customer', 'active', '2026-03-17 07:45:05'),
(4, 'lehoanghung', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lê Hoàng Hùng', 'hoanghung@email.com', '0923456789', '78 Hoàng Diệu', 'Phường 9', 'Quận 4', 'TP. Hồ Chí Minh', 'customer', 'active', '2026-03-17 07:45:05');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `import_details`
--
ALTER TABLE `import_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `receipt_id` (`receipt_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Chỉ mục cho bảng `import_receipts`
--
ALTER TABLE `import_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_code` (`receipt_code`),
  ADD KEY `created_by` (`created_by`);

--
-- Chỉ mục cho bảng `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_code` (`order_code`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Chỉ mục cho bảng `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `category_id` (`category_id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `import_details`
--
ALTER TABLE `import_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT cho bảng `import_receipts`
--
ALTER TABLE `import_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `order_details`
--
ALTER TABLE `order_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `import_details`
--
ALTER TABLE `import_details`
  ADD CONSTRAINT `import_details_ibfk_1` FOREIGN KEY (`receipt_id`) REFERENCES `import_receipts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `import_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Các ràng buộc cho bảng `import_receipts`
--
ALTER TABLE `import_receipts`
  ADD CONSTRAINT `import_receipts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Các ràng buộc cho bảng `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Các ràng buộc cho bảng `order_details`
--
ALTER TABLE `order_details`
  ADD CONSTRAINT `order_details_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Các ràng buộc cho bảng `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
