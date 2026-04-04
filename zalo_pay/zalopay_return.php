<?php
// zalopay_return.php — Trừ tồn kho khi thành công, giữ đơn awaiting_payment khi thất bại
require_once '../includes/db.php';
require_once 'zalopay_config.php';

$status       = (int)($_GET['status'] ?? -1);
$app_trans_id = $conn->real_escape_string($_GET['apptransid'] ?? '');

// Tìm đơn hàng theo app_trans_id (không dùng user_id vì session có thể mất)
$order = null;
if ($app_trans_id) {
    $order = $conn->query(
        "SELECT * FROM orders WHERE app_trans_id='$app_trans_id' LIMIT 1"
    )->fetch_assoc();
}

// Xác định thành công: ZaloPay báo status=1 VÀ tìm được đơn hàng trong DB
$success = ($status === 1) && ($order !== null);

if ($success && $order) {
    // ✅ THÀNH CÔNG
    $_SESSION['cart'] = [];

    // Trừ tồn kho LẦN ĐẦU (ZaloPay không trừ lúc tạo đơn)
    // Dùng payment_status != 'paid' để tránh trừ 2 lần nếu callback về trước
    if ($order['payment_status'] !== 'paid') {
        $items = $conn->query(
            "SELECT product_id, quantity FROM order_details WHERE order_id={$order['id']}"
        );
        while ($item = $items->fetch_assoc()) {
            $conn->query(
                "UPDATE products
                 SET stock_quantity = stock_quantity - {$item['quantity']}
                 WHERE id = {$item['product_id']} AND stock_quantity >= {$item['quantity']}"
            );
        }
        $conn->query(
            "UPDATE orders
             SET status         = 'confirmed',
                 payment_status = 'paid'
             WHERE id = {$order['id']} AND payment_status != 'paid'"
        );
    }

    // Reload dữ liệu mới nhất
    $order = $conn->query("SELECT * FROM orders WHERE id={$order['id']}")->fetch_assoc();

} elseif (!$success && $order) {
    // ❌ THẤT BẠI / HỦY / BACK
    // KHÔNG xóa đơn, KHÔNG hoàn tồn kho (chưa trừ lần nào)
    // Chuyển sang trạng thái "Chờ thanh toán" để user có thể thanh toán lại
    if ($order['payment_status'] !== 'paid') {
        $conn->query(
            "UPDATE orders SET status='awaiting_payment' WHERE id={$order['id']}"
        );
    }
    $order = $conn->query("SELECT * FROM orders WHERE id={$order['id']}")->fetch_assoc();
}

$pageTitle = $success ? 'Thanh toán thành công' : 'Thanh toán thất bại';
require_once '../includes/header.php';
?>

<div class="container my-5">
  <?php if ($success && $order): ?>
  <!-- ✅ THÀNH CÔNG -->
  <div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
      <i class="bi bi-check-circle-fill" style="font-size:5rem;color:#0068ff"></i>
      <h3 class="fw-bold mt-3" style="color:#0068ff">Thanh toán ZaloPay thành công!</h3>
      <p class="text-muted mb-1">
        Mã đơn hàng: <strong style="color:#0068ff"><?= htmlspecialchars($order['order_code']) ?></strong>
      </p>
      <p class="text-muted mb-4">Chúng tôi sẽ xử lý đơn hàng của bạn sớm nhất.</p>

      <div class="card mx-auto text-start" style="max-width:520px">
        <div class="card-header fw-bold bg-light">Tóm tắt đơn hàng</div>
        <div class="card-body">
          <?php
          $details = $conn->query(
              "SELECT od.*, p.name FROM order_details od
               JOIN products p ON od.product_id = p.id
               WHERE od.order_id = {$order['id']}"
          );
          while ($d = $details->fetch_assoc()):
          ?>
          <div class="d-flex justify-content-between mb-1 small">
            <span><?= htmlspecialchars($d['name']) ?> <span class="text-muted">×<?= $d['quantity'] ?></span></span>
            <strong><?= formatPrice($d['unit_price'] * $d['quantity']) ?></strong>
          </div>
          <?php endwhile; ?>
          <hr class="my-2">
          <div class="d-flex justify-content-between fw-bold" style="color:#0068ff">
            <span>Tổng cộng:</span>
            <span><?= formatPrice($order['total_amount']) ?></span>
          </div>
          <hr class="my-2">
          <div class="small text-muted">
            <p class="mb-1"><i class="bi bi-person me-1"></i><?= htmlspecialchars($order['receiver_name']) ?> · <?= htmlspecialchars($order['receiver_phone']) ?></p>
            <p class="mb-0"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($order['shipping_address'].', '.$order['ward'].', '.$order['district'].', '.$order['city']) ?></p>
          </div>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2 justify-content-center">
        <a href="my_orders.php" class="btn btn-primary"><i class="bi bi-bag-check me-2"></i>Xem đơn hàng</a>
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-house me-2"></i>Trang chủ</a>
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- ❌ THẤT BẠI / HỦY — Đơn vẫn còn, chờ thanh toán -->
  <div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
      <i class="bi bi-clock-history text-warning" style="font-size:5rem"></i>
      <h3 class="text-warning fw-bold mt-3">Thanh toán chưa hoàn tất</h3>
      <p class="text-muted mb-1">Đơn hàng của bạn vẫn được giữ lại.</p>
      <p class="text-muted mb-4">Bạn có thể thanh toán lại hoặc đổi sang phương thức khác.</p>
      <?php if ($order): ?>
      <p class="mb-4">Mã đơn: <strong style="color:#ff6b35"><?= htmlspecialchars($order['order_code']) ?></strong></p>
      <div class="d-flex gap-2 justify-content-center">
        <a href="checkout.php?repay=<?= $order['id'] ?>" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-repeat me-2"></i>Đổi phương thức thanh toán
        </a>
        <a href="zalo_pay/zalopay_create.php?order_id=<?= $order['id'] ?>" class="btn btn-primary" style="background:#0068ff;border-color:#0068ff">
          <i class="bi bi-phone me-2"></i>Thanh toán lại qua ZaloPay
        </a>
      </div>
      <?php else: ?>
      <a href="cart.php" class="btn btn-primary"><i class="bi bi-cart3 me-2"></i>Quay lại giỏ hàng</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>