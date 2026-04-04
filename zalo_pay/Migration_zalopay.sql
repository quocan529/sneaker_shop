-- =====================================================
-- Migration: Thêm cột ZaloPay vào bảng orders
-- Chạy 1 lần duy nhất trong phpMyAdmin hoặc MySQL CLI
-- =====================================================

ALTER TABLE `orders`
  ADD COLUMN `payment_status` ENUM('pending','paid','failed') DEFAULT NULL
      COMMENT 'Trạng thái thanh toán ZaloPay' AFTER `status`,
  ADD COLUMN `app_trans_id` VARCHAR(100) DEFAULT NULL
      COMMENT 'Mã giao dịch gửi lên ZaloPay (để đối chiếu callback)' AFTER `payment_status`,
  ADD COLUMN `zp_trans_id` VARCHAR(100) DEFAULT NULL
      COMMENT 'Mã giao dịch ZaloPay trả về sau khi thanh toán thành công' AFTER `app_trans_id`;

ALTER TABLE `orders`
  MODIFY COLUMN `status`
    ENUM('awaiting_payment','pending','confirmed','delivered','cancelled')
    DEFAULT 'pending';

-- Index để tìm nhanh theo app_trans_id trong callback
ALTER TABLE `orders`
  ADD INDEX `idx_app_trans_id` (`app_trans_id`);
