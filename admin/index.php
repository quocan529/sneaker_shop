<?php
// admin/index.php
require_once '_layout.php';
adminHeader('Dashboard');

// Stats
$total_products    = $conn->query("SELECT COUNT(*) as c FROM products WHERE status='active'")->fetch_assoc()['c'];
$total_orders      = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$pending_orders    = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='pending'")->fetch_assoc()['c'];
$awaiting_orders   = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='awaiting_payment'")->fetch_assoc()['c'];
$total_revenue     = $conn->query("SELECT COALESCE(SUM(total_amount),0) as s FROM orders WHERE status IN ('confirmed','delivered')")->fetch_assoc()['s'];
$total_users       = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='customer'")->fetch_assoc()['c'];

// Low stock
$low_stock = $conn->query("SELECT * FROM products WHERE stock_quantity <= 5 AND status='active' ORDER BY stock_quantity ASC LIMIT 5");

// Recent orders
$recent_orders = $conn->query("SELECT o.*, u.full_name FROM orders o JOIN users u ON o.user_id=u.id ORDER BY o.created_at DESC LIMIT 8");

// ✅ Đã thêm awaiting_payment vào cả 2 mảng
$statusColor = [
    'awaiting_payment' => 'secondary',
    'pending'          => 'warning',
    'confirmed'        => 'info',
    'delivered'        => 'success',
    'cancelled'        => 'danger',
];
$statusLabel = [
    'awaiting_payment' => 'Chờ thanh toán',
    'pending'          => 'Chờ xử lý',
    'confirmed'        => 'Đã xác nhận',
    'delivered'        => 'Đã giao',
    'cancelled'        => 'Đã huỷ',
];
?>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm" style="background:linear-gradient(135deg,#667eea,#764ba2);color:white">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-box-seam fs-2 opacity-75"></i>
                <div>
                    <div class="fs-4 fw-bold"><?= $total_products ?></div>
                    <div class="small opacity-75">Sản phẩm đang bán</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm" style="background:linear-gradient(135deg,#f093fb,#f5576c);color:white">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-bag-check fs-2 opacity-75"></i>
                <div>
                    <div class="fs-4 fw-bold"><?= $total_orders ?></div>
                    <div class="small opacity-75">
                        Tổng đơn hàng
                        <?php if ($pending_orders > 0): ?>
                        <span class="badge bg-white text-danger"><?= $pending_orders ?> chờ xử lý</span>
                        <?php endif; ?>
                        <?php if ($awaiting_orders > 0): ?>
                        <span class="badge bg-white text-secondary"><?= $awaiting_orders ?> chờ TT</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm" style="background:linear-gradient(135deg,#4facfe,#00f2fe);color:white">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-people fs-2 opacity-75"></i>
                <div>
                    <div class="fs-4 fw-bold"><?= $total_users ?></div>
                    <div class="small opacity-75">Khách hàng</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm" style="background:linear-gradient(135deg,#43e97b,#38f9d7);color:white">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-currency-dollar fs-2 opacity-75"></i>
                <div>
                    <div class="fs-4 fw-bold"><?= number_format($total_revenue/1000000,1) ?>M</div>
                    <div class="small opacity-75">Doanh thu (đã xác nhận)</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent orders -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold border-0">
                <i class="bi bi-clock-history me-2"></i>Đơn hàng gần đây
                <a href="orders.php" class="btn btn-sm btn-outline-secondary float-end">Xem tất cả</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Mã đơn</th><th>Khách hàng</th><th>Tổng tiền</th><th>Trạng thái</th><th>Ngày</th></tr>
                    </thead>
                    <tbody>
                        <?php while ($o = $recent_orders->fetch_assoc()):
                            // ✅ Dùng ?? để tránh undefined nếu có status lạ
                            $sc = $statusColor[$o['status']] ?? 'secondary';
                            $sl = $statusLabel[$o['status']] ?? $o['status'];
                        ?>
                        <tr>
                            <td><a href="orders.php?id=<?= $o['id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($o['order_code']) ?></a></td>
                            <td><?= htmlspecialchars($o['full_name']) ?></td>
                            <td><?= formatPrice($o['total_amount']) ?></td>
                            <td><span class="badge bg-<?= $sc ?>"><?= $sl ?></span></td>
                            <td class="text-muted small"><?= date('d/m H:i', strtotime($o['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Low stock alert -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold border-0">
                <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Sắp hết hàng
            </div>
            <div class="list-group list-group-flush">
                <?php if ($low_stock->num_rows === 0): ?>
                <div class="list-group-item text-muted">Không có sản phẩm sắp hết hàng</div>
                <?php endif; ?>
                <?php while ($p = $low_stock->fetch_assoc()): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="small"><?= htmlspecialchars($p['name']) ?></span>
                    <span class="badge bg-<?= $p['stock_quantity'] == 0 ? 'danger' : 'warning' ?>"><?= $p['stock_quantity'] ?> còn</span>
                </div>
                <?php endwhile; ?>
                <a href="inventory.php" class="list-group-item list-group-item-action text-center text-primary small">Xem báo cáo tồn kho →</a>
            </div>
        </div>
    </div>
</div>

<?php adminFooter(); ?>