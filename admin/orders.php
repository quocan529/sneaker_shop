<?php
// admin/orders.php
require_once '_layout.php';
adminHeader('Quản lý đơn hàng');

$msg = '';

// Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id        = (int)$_POST['order_id'];
    $newStatus = sanitize($conn, $_POST['status']);
    $allowed   = ['pending','confirmed','delivered','cancelled'];

    if (in_array($newStatus, $allowed)) {
        // Lấy trạng thái hiện tại trước khi thay đổi
        $cur = $conn->query("SELECT status FROM orders WHERE id=$id")->fetch_assoc();
        $oldStatus = $cur ? $cur['status'] : '';

        // Đơn đã giao thì khoá, không cho thay đổi trạng thái
        if ($oldStatus === 'delivered') {
            $msg = '<div class="alert alert-warning"><i class="bi bi-lock me-2"></i>Đơn hàng đã giao, không thể thay đổi trạng thái.</div>';
        } else if ($oldStatus === 'cancelled') {
            $msg = '<div class="alert alert-warning"><i class="bi bi-lock me-2"></i>Đơn hàng đã huỷ, không thể thay đổi trạng thái.</div>';    
        } else {
            // Chỉ trừ tồn kho khi chuyển sang "Đã giao" và hoàn lại hàng đã giữ khi chuyển sang "Đã huỷ"
            $conn->begin_transaction();

            try {
                $conn->query("UPDATE orders SET status='$newStatus' WHERE id=$id");

                $details = $conn->query("SELECT product_id, quantity FROM order_details WHERE order_id=$id");

                if ($newStatus === 'delivered') {
                    while ($d = $details->fetch_assoc()) {
                        $pid = (int)$d['product_id'];
                        $qty = (int)$d['quantity'];

                        $conn->query("
                            UPDATE products 
                            SET 
                                stock_quantity = stock_quantity - $qty,
                                reserved_quantity = reserved_quantity - $qty
                            WHERE id=$pid
                        ");
                    }

                    $msg = '<div class="alert alert-success">Đã giao hàng và trừ kho.</div>';

                } elseif ($newStatus === 'cancelled') {
                    while ($d = $details->fetch_assoc()) {
                        $pid = (int)$d['product_id'];
                        $qty = (int)$d['quantity'];

                        $conn->query("
                            UPDATE products 
                            SET reserved_quantity = reserved_quantity - $qty
                            WHERE id=$pid
                        ");
                    }

                    $msg = '<div class="alert alert-success">Đã huỷ đơn và hoàn lại hàng đã giữ.</div>';
                } else {
                    $msg = '<div class="alert alert-success">Đã cập nhật trạng thái.</div>';
                }

                $conn->commit();

            } catch (Exception $e) {
                $conn->rollback();
                $msg = '<div class="alert alert-danger">Lỗi: '.$e->getMessage().'</div>';
            }
        }
    }
}

$statusLabels = ['pending'=>'Chờ xử lý','confirmed'=>'Đã xác nhận','delivered'=>'Đã giao','cancelled'=>'Đã huỷ'];
$statusColors = ['pending'=>'warning','confirmed'=>'info','delivered'=>'success','cancelled'=>'danger'];

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

$order_by  = $sort_ward ? "o.ward, o.district" : "o.created_at DESC";
$total_o   = $conn->query("SELECT COUNT(*) as c FROM orders o JOIN users u ON o.user_id=u.id WHERE $where")->fetch_assoc()['c'];
$offset_o  = ($page_o - 1) * $per_page_o;
$orders    = $conn->query("SELECT o.*, u.full_name FROM orders o JOIN users u ON o.user_id=u.id WHERE $where ORDER BY $order_by LIMIT $per_page_o OFFSET $offset_o");
$params_o  = array_filter(['q'=>$search_o,'status'=>$filter_status,'date_from'=>$date_from,'date_to'=>$date_to,'sort_ward'=>$sort_ward?1:null]);

// Detail view
$detail_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$orderDetail = null;
if ($detail_id) {
    $orderDetail = $conn->query("SELECT o.*, u.full_name, u.email, u.phone FROM orders o JOIN users u ON o.user_id=u.id WHERE o.id=$detail_id")->fetch_assoc();
}
?>

<?= $msg ?>

<?php if ($orderDetail): ?>
<!-- Order Detail -->
<div class="mb-3">
    <a href="orders.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
</div>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-bold border-0 d-flex justify-content-between">
        <span>Đơn hàng: <?= htmlspecialchars($orderDetail['order_code']) ?></span>
        <span class="badge bg-<?= $statusColors[$orderDetail['status']] ?>"><?= $statusLabels[$orderDetail['status']] ?></span>
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
                <?php
                $pm = ['cash'=>'Tiền mặt (COD)','transfer'=>'Chuyển khoản','online'=>'Trực tuyến'];
                ?>
                <p class="mb-1">TT: <?= $pm[$orderDetail['payment_method']] ?></p>
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
                <tr><td colspan="3" class="text-end fw-bold">Tổng cộng:</td>
                    <td class="text-end fw-bold" style="color:#e74c3c"><?= formatPrice($orderDetail['total_amount']) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Update status -->
        <?php if ($orderDetail['status'] === 'delivered'): ?>
        <div class="alert alert-success mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-lock-fill fs-5"></i>
            <span>Đơn hàng đã được giao thành công. Không thể thay đổi trạng thái.</span>
        </div>
        <?php elseif ($orderDetail['status'] === 'cancelled'): ?>
        <div class="alert alert-danger mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-lock-fill fs-5"></i>
            <span>Đơn hàng đã bị huỷ. Không thể chuyển sang trạng thái khác.</span>
        </div>  
        <?php else: ?>
        <form method="POST" class="d-flex align-items-center gap-3">
            <input type="hidden" name="order_id" value="<?= $orderDetail['id'] ?>">
            <label class="fw-semibold">Cập nhật trạng thái:</label>
            <select name="status" class="form-select" style="width:200px">
                <?php
                // Đang xử lý & Đã xác nhận: cho phép chuyển sang nhau, Đã giao, hoặc Đã huỷ
                $allowedTransitions = ['pending', 'confirmed', 'delivered', 'cancelled'];
                foreach ($statusLabels as $k => $v):
                    if (!in_array($k, $allowedTransitions)) continue;
                ?>
                <option value="<?= $k ?>" <?= $orderDetail['status']==$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="update_status" class="btn btn-primary">Cập nhật</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- Order list with filters -->
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
                    <?php foreach ($statusLabels as $k=>$v): ?>
                    <option value="<?= $k ?>" <?= $filter_status==$k?'selected':'' ?>><?= $v ?></option>
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
                    <input class="form-check-input" type="checkbox" name="sort_ward" value="1" id="sortWard" <?= $sort_ward?'checked':'' ?> onchange="this.form.submit()">
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
                $pm_short = ['cash'=>'COD','transfer'=>'CK','online'=>'Online'];
                if ($orders->num_rows === 0): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Không tìm thấy đơn hàng nào.</td></tr>
                <?php endif;
                while ($o = $orders->fetch_assoc()):
                ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($o['order_code']) ?></td>
                    <td><?= htmlspecialchars($o['full_name']) ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($o['ward'].', '.$o['district']) ?></td>
                    <td class="text-end"><?= formatPrice($o['total_amount']) ?></td>
                    <td><span class="badge bg-light text-dark border"><?= $pm_short[$o['payment_method']] ?></span></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $statusColors[$o['status']] ?>"><?= $statusLabels[$o['status']] ?></span>
                    </td>
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