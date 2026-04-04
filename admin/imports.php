<?php
// admin/imports.php
require_once '_layout.php';
adminHeader('Quản lý nhập hàng');

$msg = '';

// Complete receipt
if (isset($_GET['complete'])) {
    $id = (int)$_GET['complete'];
    $receipt = $conn->query("SELECT * FROM import_receipts WHERE id=$id AND status='pending'")->fetch_assoc();
    if ($receipt) {
        // Update stock and import price (weighted average)
        $details = $conn->query("SELECT * FROM import_details WHERE receipt_id=$id");
        while ($d = $details->fetch_assoc()) {
            $pid = $d['product_id'];
            $p = $conn->query("SELECT stock_quantity, import_price FROM products WHERE id=$pid")->fetch_assoc();
            $old_qty   = $p['stock_quantity'];
            $old_price = $p['import_price'];
            $new_qty   = $d['quantity'];
            $new_price = $d['import_price'];
            $avg_price = ($old_qty + $new_qty) > 0 ? ($old_qty * $old_price + $new_qty * $new_price) / ($old_qty + $new_qty) : $new_price;
            $conn->query("UPDATE products SET stock_quantity=stock_quantity+$new_qty, import_price=$avg_price WHERE id=$pid");
        }
        $conn->query("UPDATE import_receipts SET status='completed' WHERE id=$id");
        $msg = '<div class="alert alert-success">Phiếu nhập đã được hoàn thành. Tồn kho đã được cập nhật.</div>';
    }
}

// Delete receipt (only pending)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $receipt = $conn->query("SELECT * FROM import_receipts WHERE id=$id AND status='pending'")->fetch_assoc();
    if ($receipt) {
        $conn->query("DELETE FROM import_receipts WHERE id=$id");
        $msg = '<div class="alert alert-success">Đã xóa phiếu nhập.</div>';
    }
}

// Create new receipt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $date  = sanitize($conn, $_POST['import_date'] ?? date('Y-m-d'));
        $notes = sanitize($conn, $_POST['notes'] ?? '');
        $code  = generateCode('PN');
        $user_id = $_SESSION['user_id'];
        $conn->query("INSERT INTO import_receipts (receipt_code,import_date,notes,created_by) VALUES ('$code','$date','$notes',$user_id)");
        $rid = $conn->insert_id;
        $msg = '<div class="alert alert-success">Đã tạo phiếu nhập <strong>'.$code.'</strong>. <a href="imports.php?edit='.$rid.'">Thêm sản phẩm vào phiếu →</a></div>';
    }

    if ($_POST['action'] === 'add_item') {
        $rid   = (int)$_POST['receipt_id'];
        $pid   = (int)$_POST['product_id'];
        $qty   = (int)$_POST['quantity'];
        $price = (float)$_POST['import_price'];
        if ($pid && $qty > 0 && $price > 0) {
            // Check if product already in receipt, update qty
            $existing = $conn->query("SELECT id FROM import_details WHERE receipt_id=$rid AND product_id=$pid")->fetch_assoc();
            if ($existing) {
                $conn->query("UPDATE import_details SET quantity=quantity+$qty, import_price=$price WHERE receipt_id=$rid AND product_id=$pid");
            } else {
                $conn->query("INSERT INTO import_details (receipt_id,product_id,quantity,import_price) VALUES ($rid,$pid,$qty,$price)");
            }
            $msg = '<div class="alert alert-success">Đã thêm sản phẩm vào phiếu nhập.</div>';
        }
    }

    if ($_POST['action'] === 'remove_item') {
        $did = (int)$_POST['detail_id'];
        $conn->query("DELETE FROM import_details WHERE id=$did");
        $msg = '<div class="alert alert-info">Đã xóa dòng khỏi phiếu nhập.</div>';
    }
}

// Edit view
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_receipt = null;
if ($edit_id) {
    $edit_receipt = $conn->query("SELECT * FROM import_receipts WHERE id=$edit_id")->fetch_assoc();
}

// List receipts with search + pagination
$search_i   = isset($_GET['search']) ? sanitize($conn, $_GET['search']) : '';
$filter_ist = isset($_GET['ist'])    ? sanitize($conn, $_GET['ist']) : '';
$page_i     = max(1, (int)($_GET['page'] ?? 1));
$per_page_i = 12;
$where_i    = "1=1";
if ($search_i)   $where_i .= " AND (ir.receipt_code LIKE '%$search_i%' OR u.full_name LIKE '%$search_i%')";
if ($filter_ist) $where_i .= " AND ir.status='$filter_ist'";
$total_i  = $conn->query("SELECT COUNT(*) as c FROM import_receipts ir JOIN users u ON ir.created_by=u.id WHERE $where_i")->fetch_assoc()['c'];
$offset_i = ($page_i - 1) * $per_page_i;
$receipts = $conn->query("SELECT ir.*, u.full_name FROM import_receipts ir JOIN users u ON ir.created_by=u.id WHERE $where_i ORDER BY ir.created_at DESC LIMIT $per_page_i OFFSET $offset_i");
$params_i = array_filter(['search'=>$search_i,'ist'=>$filter_ist]);

$products_list = $conn->query("SELECT id,code,name FROM products WHERE status='active' ORDER BY name");
?>

<?= $msg ?>

<?php if ($edit_receipt): ?>
<!-- Edit Receipt -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-bold border-0 d-flex justify-content-between">
        <span><i class="bi bi-pencil me-2"></i>Phiếu nhập: <?= htmlspecialchars($edit_receipt['receipt_code']) ?>
            <?php if ($edit_receipt['status'] === 'completed'): ?>
            <span class="badge bg-success ms-2">Đã hoàn thành</span>
            <?php else: ?>
            <span class="badge bg-warning ms-2">Chưa hoàn thành</span>
            <?php endif; ?>
        </span>
        <a href="imports.php" class="btn btn-sm btn-outline-secondary">← Quay lại</a>
    </div>
    <div class="card-body">
        <?php if ($edit_receipt['status'] === 'pending'): ?>
        <!-- Add item form -->
        <form method="POST" class="row g-3 mb-4 p-3 bg-light rounded">
            <input type="hidden" name="action" value="add_item">
            <input type="hidden" name="receipt_id" value="<?= $edit_receipt['id'] ?>">
            <div class="col-md-5">
                <label class="form-label">Sản phẩm</label>
                <select name="product_id" class="form-select" required>
                    <option value="">-- Chọn sản phẩm --</option>
                    <?php while ($p = $products_list->fetch_assoc()): ?>
                    <option value="<?= $p['id'] ?>">[<?= htmlspecialchars($p['code']) ?>] <?= htmlspecialchars($p['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Số lượng</label>
                <input type="number" name="quantity" class="form-control" min="1" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Giá nhập (₫)</label>
                <input type="number" name="import_price" class="form-control" min="0" step="1000" required>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus"></i></button>
            </div>
        </form>
        <?php endif; ?>

        <!-- Items table -->
        <?php
        $items = $conn->query("SELECT id.*, p.name, p.code FROM import_details id JOIN products p ON id.product_id=p.id WHERE id.receipt_id={$edit_receipt['id']}");
        $subtotal = 0;
        ?>
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr><th>Sản phẩm</th><th class="text-center">SL</th><th class="text-end">Giá nhập</th><th class="text-end">Thành tiền</th><?= $edit_receipt['status']=='pending' ? '<th></th>' : '' ?></tr>
            </thead>
            <tbody>
                <?php while ($item = $items->fetch_assoc()):
                    $line = $item['quantity'] * $item['import_price'];
                    $subtotal += $line;
                ?>
                <tr>
                    <td>[<?= htmlspecialchars($item['code']) ?>] <?= htmlspecialchars($item['name']) ?></td>
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-end"><?= formatPrice($item['import_price']) ?></td>
                    <td class="text-end fw-semibold"><?= formatPrice($line) ?></td>
                    <?php if ($edit_receipt['status'] === 'pending'): ?>
                    <td class="text-center">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="remove_item">
                            <input type="hidden" name="receipt_id" value="<?= $edit_receipt['id'] ?>">
                            <input type="hidden" name="detail_id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
                <tr>
                    <td colspan="3" class="text-end fw-bold">Tổng giá trị nhập:</td>
                    <td class="text-end fw-bold text-primary"><?= formatPrice($subtotal) ?></td>
                    <?php if ($edit_receipt['status'] === 'pending'): ?><td></td><?php endif; ?>
                </tr>
            </tbody>
        </table>

        <?php if ($edit_receipt['status'] === 'pending'): ?>
        <div class="text-end">
            <a href="imports.php?complete=<?= $edit_receipt['id'] ?>" class="btn btn-success" onclick="return confirm('Hoàn thành phiếu nhập? Tồn kho sẽ được cập nhật.')">
                <i class="bi bi-check-circle me-2"></i>Hoàn thành phiếu nhập
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Create new receipt -->
<?php if (!$edit_receipt): ?>
<div class="mb-4">
    <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#createForm">
        <i class="bi bi-plus-circle me-2"></i>Tạo phiếu nhập mới
    </button>
</div>
<div class="collapse mb-4" id="createForm">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="action" value="create">
                <div class="col-md-4">
                    <label class="form-label">Ngày nhập</label>
                    <input type="date" name="import_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ghi chú</label>
                    <input type="text" name="notes" class="form-control" placeholder="Ghi chú (tùy chọn)">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Tạo phiếu</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Receipts list -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong><i class="bi bi-list-ul me-2"></i>Danh sách phiếu nhập <span class="badge bg-secondary"><?= $total_i ?></span></strong>
            <form method="GET" class="d-flex gap-2 flex-wrap">
                <div class="input-group input-group-sm" style="width:220px">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Tìm mã phiếu, người tạo..." value="<?= htmlspecialchars($search_i) ?>">
                </div>
                <select name="ist" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
                    <option value="">Tất cả trạng thái</option>
                    <option value="pending"   <?= $filter_ist==='pending'?'selected':'' ?>>Chưa hoàn thành</option>
                    <option value="completed" <?= $filter_ist==='completed'?'selected':'' ?>>Đã hoàn thành</option>
                </select>
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Tìm</button>
                <?php if ($search_i || $filter_ist): ?>
                <a href="imports.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Mã phiếu</th><th>Ngày nhập</th><th>Người tạo</th><th>Ghi chú</th><th class="text-center">Trạng thái</th><th class="text-center">Thao tác</th></tr>
            </thead>
            <tbody>
                <?php if ($receipts->num_rows === 0): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Không tìm thấy phiếu nhập nào.</td></tr>
                <?php endif; ?>
                <?php while ($r = $receipts->fetch_assoc()): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($r['receipt_code']) ?></td>
                    <td><?= date('d/m/Y', strtotime($r['import_date'])) ?></td>
                    <td><?= htmlspecialchars($r['full_name']) ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($r['notes']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $r['status']==='completed' ? 'success' : 'warning' ?>">
                            <?= $r['status']==='completed' ? 'Đã hoàn thành' : 'Chưa xong' ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="imports.php?edit=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Xem/Sửa"><i class="bi bi-eye"></i></a>
                        <?php if ($r['status'] === 'pending'): ?>
                        <a href="imports.php?delete=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa phiếu nhập này?')"><i class="bi bi-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_i > $per_page_i): ?>
    <div class="card-footer bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">Hiển thị <?= min($offset_i+1,$total_i) ?>–<?= min($offset_i+$per_page_i,$total_i) ?> / <?= $total_i ?> phiếu nhập</small>
            <?= renderPagination($total_i, $page_i, $per_page_i, $params_i) ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php adminFooter(); ?>
