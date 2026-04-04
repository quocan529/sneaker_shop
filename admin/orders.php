<?php
// admin/orders.php
require_once '_layout.php';
adminHeader('Quản lý đơn hàng');

$msg = '';

// Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id        = (int)$_POST['order_id'];
    $newStatus = sanitize($conn, $_POST['status']);

    // ⚠️ 'awaiting_payment' KHÔNG có trong danh sách cho phép — admin không được đặt về trạng thái này
    $allowed = ['pending', 'confirmed', 'delivered', 'cancelled'];

    if (in_array($newStatus, $allowed)) {
        $cur       = $conn->query("SELECT status FROM orders WHERE id=$id")->fetch_assoc();
        $oldStatus = $cur ? $cur['status'] : '';

        $conn->query("UPDATE orders SET status='$newStatus' WHERE id=$id");

        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            // Huỷ đơn → chỉ hoàn tồn kho nếu đơn ĐÃ được trừ tồn kho trước đó
            // Đơn 'awaiting_payment': chưa trừ tồn kho → KHÔNG hoàn
            // Đơn 'pending'/'confirmed'/'delivered': đã trừ tồn kho → hoàn lại
            if ($oldStatus !== 'awaiting_payment') {
                $details = $conn->query("SELECT product_id, quantity FROM order_details WHERE order_id=$id");
                while ($d = $details->fetch_assoc()) {
                    $conn->query("UPDATE products SET stock_quantity = stock_quantity + {$d['quantity']} WHERE id={$d['product_id']}");
                }
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Đã huỷ đơn hàng và hoàn lại tồn kho.</div>';
            } else {
                // awaiting_payment: huỷ nhưng không hoàn tồn kho
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Đã huỷ đơn hàng (đơn chưa trừ tồn kho, không cần hoàn).</div>';
            }
        } elseif ($oldStatus === 'cancelled' && $newStatus !== 'cancelled') {
            // Bỏ huỷ đơn → trừ lại tồn kho
            $details = $conn->query("SELECT product_id, quantity FROM order_details WHERE order_id=$id");
            while ($d = $details->fetch_assoc()) {
                $conn->query("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - {$d['quantity']}) WHERE id={$d['product_id']}");
            }
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Đã cập nhật trạng thái đơn hàng.</div>';
        } else {
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Đã cập nhật trạng thái đơn hàng.</div>';
        }
    }
}

// Labels & colors — bao gồm awaiting_payment để hiển thị, nhưng KHÔNG trong dropdown cập nhật
$statusLabels = [
    'awaiting_payment' => 'Chờ thanh toán',
    'pending'          => 'Chờ xử lý',
    'confirmed'        => 'Đã xác nhận',
    'delivered'        => 'Đã giao',
    'cancelled'        => 'Đã huỷ',
];
$statusColors = [
    'awaiting_payment' => 'secondary',
    'pending'          => 'warning',
    'confirmed'        => 'info',
    'delivered'        => 'success',
    'cancelled'        => 'danger',
];

// Chỉ các trạng thái admin được phép đặt (KHÔNG bao gồm awaiting_payment)
$statusEditable = [
    'pending'   => 'Chờ xử lý',
    'confirmed' => 'Đã xác nhận',
    'delivered' => 'Đã giao',
    'cancelled' => 'Đã huỷ',
];

// Filters
$filter_status = isset($_GET['status'])    ? sanitize($conn, $_GET['status']) : '';
$date_from     = isset($_GET['date_from']) ? sanitize($conn, $_GET['date_from']) : '';
$date_to       = isset($_GET['date_to'])   ? sanitize($conn, $_GET['date_to']) : '';
$sort_ward     = isset($_GET['sort_ward']) ? 1 : 0;
$search_o      = isset($_GET['q'])         ? sanitize($conn, $_GET['q']) : '';
$page_o        = max(1, (int)($_GET['page'] ?? 1));
$per_page_o    = 15;

$where = "1=1";
if ($filter_status) $where .= " AND o.status='$filter_status'";
if ($date_from)     $where .= " AND DATE(o.created_at) >= '$date_from'";
if ($date_to)       $where .= " AND DATE(o.created_at) <= '$date_to'";
if ($search_o)      $where .= " AND (o.order_code LIKE '%$search_o%' OR u.full_name LIKE '%$search_o%' OR o.receiver_phone LIKE '%$search_o%')";

$order_by = $sort_ward ? "o.ward, o.district" : "o.created_at DESC";
$total_o  = $conn->query("SELECT COUNT(*) as c FROM orders o JOIN users u ON o.user_id=u.id WHERE $where")->fetch_assoc()['c'];
$offset_o = ($page_o - 1) * $per_page_o;
$orders   = $conn->query("SELECT o.*, u.full_name FROM orders o JOIN users u ON o.user_id=u.id WHERE $where ORDER BY $order_by LIMIT $per_page_o OFFSET $offset_o");
$params_o = array_filter(['q'=>$search_o,'status'=>$filter_status,'date_from'=>$date_from,'date_to'=>$date_to,'sort_ward'=>$sort_ward?1:null]);

// Detail view
$detail_id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$orderDetail = null;
if ($detail_id) {
    $orderDetail = $conn->query(
        "SELECT o.*, u.full_name, u.email, u.phone FROM orders o JOIN users u ON o.user_id=u.id WHERE o.id=$detail_id"
    )->fetch_assoc();
}
?>

<?= $msg ?>

<?php if ($orderDetail): ?>
<!-- ── Chi tiết đơn hàng ── -->
<div class="mb-3">
    <a href="orders.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
</div>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-bold border-0 d-flex justify-content-between">
        <span>Đơn hàng: <?= htmlspecialchars($orderDetail['order_code']) ?></span>
        <span class="badge bg-<?= $statusColors[$orderDetail['status']] ?? 'secondary' ?>">
            <?= $statusLabels[$orderDetail['status']] ?? $orderDetail['status'] ?>
        </span>
    </div>
    <div class="card-body">
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <h6 class="fw-bold text-muted">KHÁCH HÀNG</h6>
                <p class="mb-1"><?= htmlspecialchars($orderDetail['full_name']) ?></p>
                <p class="mb-1 text-muted small"><?= htmlspecialchars($orderDetail['email']) ?></p>
                <p class="mb-0 text-muted small"><?= htmlspecialchars($orderDetail['phone']) ?></p>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold text-muted">GIAO HÀNG</h6>
                <p class="mb-1"><?= htmlspecialchars($orderDetail['receiver_name']) ?></p>
                <p class="mb-1 text-muted small"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($orderDetail['receiver_phone']) ?></p>
                <p class="mb-0 text-muted small"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($orderDetail['shipping_address'].', '.$orderDetail['ward'].', '.$orderDetail['district'].', '.$orderDetail['city']) ?></p>
            </div>
            <div class="col-md-4">
                <h6 class="fw-bold text-muted">ĐƠN HÀNG</h6>
                <p class="mb-1">Ngày: <?= date('d/m/Y H:i', strtotime($orderDetail['created_at'])) ?></p>
                <?php $pm = ['cash'=>'Tiền mặt (COD)','transfer'=>'Chuyển khoản','online'=>'Trực tuyến (ZaloPay)']; ?>
                <p class="mb-1">TT: <?= $pm[$orderDetail['payment_method']] ?? $orderDetail['payment_method'] ?></p>
                <?php if ($orderDetail['notes']): ?>
                <p class="text-muted small">Ghi chú: <?= htmlspecialchars($orderDetail['notes']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <table class="table table-bordered">
            <thead class="table-light">
                <tr><th>Sản phẩm</th><th class="text-center">SL</th><th class="text-end">Đơn giá</th><th class="text-end">Thành tiền</th></tr>
            </thead>
            <tbody>
                <?php
                $details = $conn->query("SELECT od.*, p.name, p.code FROM order_details od JOIN products p ON od.product_id=p.id WHERE od.order_id={$orderDetail['id']}");
                while ($d = $details->fetch_assoc()):
                ?>
                <tr>
                    <td>[<?= htmlspecialchars($d['code']) ?>] <?= htmlspecialchars($d['name']) ?></td>
                    <td class="text-center"><?= $d['quantity'] ?></td>
                    <td class="text-end"><?= formatPrice($d['unit_price']) ?></td>
                    <td class="text-end fw-bold"><?= formatPrice($d['unit_price']*$d['quantity']) ?></td>
                </tr>
                <?php endwhile; ?>
                <tr>
                    <td colspan="3" class="text-end fw-bold">Tổng cộng:</td>
                    <td class="text-end fw-bold" style="color:#e74c3c"><?= formatPrice($orderDetail['total_amount']) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Cập nhật trạng thái — KHÔNG có awaiting_payment trong dropdown -->
        <form method="POST" class="d-flex align-items-center gap-3">
            <input type="hidden" name="order_id" value="<?= $orderDetail['id'] ?>">
            <label class="fw-semibold">Cập nhật trạng thái:</label>

            <?php if ($orderDetail['status'] === 'awaiting_payment'): ?>
            <!-- Đơn đang chờ thanh toán: chỉ cho phép huỷ hoặc giữ nguyên -->
            <select name="status" class="form-select" style="width:220px">
                <option value="awaiting_payment" selected disabled>Chờ thanh toán (hiện tại)</option>
                <option value="cancelled">Đã huỷ</option>
            </select>
            <button type="submit" name="update_status" class="btn btn-primary">Cập nhật</button>
            <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Không thể đổi sang trạng thái khác khi chờ thanh toán</span>
            <?php else: ?>
            <select name="status" class="form-select" style="width:200px">
                <?php foreach ($statusEditable as $k => $v): ?>
                <option value="<?= $k ?>" <?= $orderDetail['status'] == $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="update_status" class="btn btn-primary">Cập nhật</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php else: ?>
<!-- ── Danh sách đơn hàng ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Mã đơn, tên KH, SĐT..." value="<?= htmlspecialchars($search_o) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Tất cả TT</option>
                    <?php foreach ($statusLabels as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filter_status == $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $date_from ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $date_to ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Lọc</button>
                <a href="orders.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
            <div class="col-md-1 d-flex align-items-center">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="sort_ward" value="1" id="sortWard" <?= $sort_ward ? 'checked' : '' ?> onchange="this.form.submit()">
                    <label class="form-check-label small" for="sortWard">Theo phường</label>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold border-0">
        <i class="bi bi-bag-check me-2"></i>Danh sách đơn hàng <span class="badge bg-secondary"><?= $total_o ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Mã đơn</th><th>Khách hàng</th><th>Địa chỉ giao</th><th class="text-end">Tổng tiền</th><th>Thanh toán</th><th class="text-center">Trạng thái</th><th>Ngày đặt</th><th class="text-center">Chi tiết</th></tr>
            </thead>
            <tbody>
                <?php
                $pm_short = ['cash'=>'COD','transfer'=>'CK','online'=>'ZaloPay'];
                if ($orders->num_rows === 0): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Không tìm thấy đơn hàng nào.</td></tr>
                <?php endif;
                while ($o = $orders->fetch_assoc()):
                    $sc = $statusColors[$o['status']] ?? 'secondary';
                    $sl = $statusLabels[$o['status']] ?? $o['status'];
                ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($o['order_code']) ?></td>
                    <td><?= htmlspecialchars($o['full_name']) ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($o['ward'].', '.$o['district']) ?></td>
                    <td class="text-end"><?= formatPrice($o['total_amount']) ?></td>
                    <td><span class="badge bg-light text-dark border"><?= $pm_short[$o['payment_method']] ?? $o['payment_method'] ?></span></td>
                    <td class="text-center"><span class="badge bg-<?= $sc ?>"><?= $sl ?></span></td>
                    <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                    <td class="text-center">
                        <a href="orders.php?id=<?= $o['id'] ?>&<?= http_build_query($params_o) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_o > $per_page_o): ?>
    <div class="card-footer bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">Hiển thị <?= min($offset_o+1,$total_o) ?>–<?= min($offset_o+$per_page_o,$total_o) ?> / <?= $total_o ?> đơn hàng</small>
            <?= renderPagination($total_o, $page_o, $per_page_o, $params_o) ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php adminFooter(); ?>