<?php
// admin/inventory.php
require_once '_layout.php';

// ══════════════════════════════════════════════════════════════════════════════
// AJAX: Trả JSON cho drill-down modal (trước khi output HTML)
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detail') {
    $type = $_GET['type'] ?? '';          // 'import' hoặc 'sold'
    $pid  = (int)($_GET['pid']  ?? 0);
    $from = sanitize($conn, $_GET['from'] ?? '');
    $to   = sanitize($conn, $_GET['to']   ?? '');

    header('Content-Type: application/json');

    if ($type === 'import' && $pid && $from && $to) {
        $rows = $conn->query("
            SELECT ir.receipt_code, ir.import_date, p.name as product_name,
                   id2.quantity, id2.import_price
            FROM import_details id2
            JOIN import_receipts ir ON id2.receipt_id = ir.id
            JOIN products p ON id2.product_id = p.id
            WHERE id2.product_id = $pid
              AND ir.status = 'completed'
              AND ir.import_date BETWEEN '$from' AND '$to'
            ORDER BY ir.import_date
        ");
        $data = [];
        while ($r = $rows->fetch_assoc()) $data[] = $r;
        echo json_encode(['ok' => true, 'data' => $data]);

    } elseif ($type === 'sold' && $pid && $from && $to) {
        $rows = $conn->query("
            SELECT o.order_code, DATE(o.created_at) as order_date,
                   p.name as product_name, od.quantity, od.unit_price
            FROM order_details od
            JOIN orders o ON od.order_id = o.id
            JOIN products p ON od.product_id = p.id
            WHERE od.product_id = $pid
              AND o.status IN ('pending','confirmed','delivered')
              AND DATE(o.created_at) BETWEEN '$from' AND '$to'
            ORDER BY o.created_at
        ");
        $data = [];
        while ($r = $rows->fetch_assoc()) $data[] = $r;
        echo json_encode(['ok' => true, 'data' => $data]);

    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// Bắt đầu render trang admin
// ══════════════════════════════════════════════════════════════════════════════
adminHeader('Tồn kho & Báo cáo');

$tab      = isset($_GET['tab']) ? $_GET['tab'] : 'stock';
$per_page = 15;

// ── Low stock threshold ───────────────────────────────────────────────────────
$low_threshold = 5;
if (isset($_GET['threshold']) && is_numeric($_GET['threshold'])) {
    $low_threshold = max(1, (int)$_GET['threshold']);
}

// ── Stock tab params ──────────────────────────────────────────────────────────
$stock_date = isset($_GET['stock_date']) ? sanitize($conn, $_GET['stock_date']) : date('Y-m-d');
$stock_cat  = isset($_GET['stock_cat']) ? (int)$_GET['stock_cat'] : 0;
$page_stock = max(1, (int)($_GET['page'] ?? 1));
$where_cat  = $stock_cat ? "AND p.category_id=$stock_cat" : '';

$total_stock  = $conn->query("SELECT COUNT(*) as c FROM products p JOIN categories c ON p.category_id=c.id WHERE p.status='active' $where_cat")->fetch_assoc()['c'];
$offset_stock = ($page_stock - 1) * $per_page;

$stocks = $conn->query("SELECT p.id, p.code, p.name, c.name as cat_name, p.stock_quantity,
    p.import_price, p.profit_rate,
    ROUND(p.import_price*(1+p.profit_rate/100)) as sell_price,
    COALESCE((SELECT SUM(id2.quantity) FROM import_details id2
              JOIN import_receipts ir2 ON id2.receipt_id=ir2.id
              WHERE id2.product_id=p.id AND ir2.status='completed'), 0) as total_imported_all,
    COALESCE((SELECT SUM(id2.quantity) FROM import_details id2
              JOIN import_receipts ir2 ON id2.receipt_id=ir2.id
              WHERE id2.product_id=p.id AND ir2.status='completed' AND ir2.import_date > '$stock_date'), 0) as imported_after,
    COALESCE((SELECT SUM(od.quantity) FROM order_details od
              JOIN orders o ON od.order_id=o.id
              WHERE od.product_id=p.id AND o.status NOT IN ('cancelled')), 0) as total_sold_all,
    COALESCE((SELECT SUM(od.quantity) FROM order_details od
              JOIN orders o ON od.order_id=o.id
              WHERE od.product_id=p.id AND o.status NOT IN ('cancelled') AND DATE(o.created_at) > '$stock_date'), 0) as sold_after
FROM products p JOIN categories c ON p.category_id=c.id
WHERE p.status='active' $where_cat
ORDER BY p.name
LIMIT $per_page OFFSET $offset_stock");

// ── Report tab params ─────────────────────────────────────────────────────────
$rep_from = isset($_GET['rep_from']) ? sanitize($conn, $_GET['rep_from']) : date('Y-m-01');
$rep_to   = isset($_GET['rep_to'])   ? sanitize($conn, $_GET['rep_to'])   : date('Y-m-d');
$page_rep = max(1, (int)($_GET['page'] ?? 1));
$total_rep  = $conn->query("SELECT COUNT(*) as c FROM products p WHERE p.status='active'")->fetch_assoc()['c'];
$offset_rep = ($page_rep - 1) * $per_page;

$report = $conn->query("SELECT p.id, p.code, p.name, c.name as cat_name,
    COALESCE((SELECT SUM(id2.quantity) FROM import_details id2 JOIN import_receipts ir2 ON id2.receipt_id=ir2.id
              WHERE id2.product_id=p.id AND ir2.status='completed' AND ir2.import_date BETWEEN '$rep_from' AND '$rep_to'), 0) as qty_imported,
    COALESCE((SELECT SUM(od.quantity) FROM order_details od JOIN orders o ON od.order_id=o.id
              WHERE od.product_id=p.id AND o.status IN ('pending','confirmed','delivered')
              AND DATE(o.created_at) BETWEEN '$rep_from' AND '$rep_to'), 0) as qty_sold,
    p.stock_quantity,
    COALESCE((SELECT SUM(od2.quantity) FROM order_details od2 JOIN orders o2 ON od2.order_id=o2.id
              WHERE od2.product_id=p.id AND o2.status IN ('pending','confirmed','delivered')), 0) as total_sold_ever
FROM products p JOIN categories c ON p.category_id=c.id
WHERE p.status='active'
ORDER BY qty_sold DESC, p.name
LIMIT $per_page OFFSET $offset_rep");

// ── Alert tab params ──────────────────────────────────────────────────────────
$page_alert   = max(1, (int)($_GET['page'] ?? 1));
$total_alert  = $conn->query("SELECT COUNT(*) as c FROM products p JOIN categories c ON p.category_id=c.id WHERE p.status='active' AND p.stock_quantity <= $low_threshold")->fetch_assoc()['c'];
$offset_alert = ($page_alert - 1) * $per_page;
$low_products = $conn->query("SELECT p.*, c.name as cat_name
    FROM products p JOIN categories c ON p.category_id=c.id
    WHERE p.status='active' AND p.stock_quantity <= $low_threshold
    ORDER BY p.stock_quantity ASC, p.name ASC
    LIMIT $per_page OFFSET $offset_alert");

// ── Price tab params ──────────────────────────────────────────────────────────
$page_price   = max(1, (int)($_GET['page'] ?? 1));
$search_price = isset($_GET['search_price']) ? sanitize($conn, trim($_GET['search_price'])) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_price'])) {
    $pid  = (int)$_POST['product_id'];
    $rate = (float)$_POST['profit_rate'];
    $conn->query("UPDATE products SET profit_rate=$rate WHERE id=$pid");
    echo '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Đã cập nhật tỉ lệ lợi nhuận.</div>';
}

$where_price = "p.status='active'";
if ($search_price !== '') {
    $where_price .= " AND (p.name LIKE '%$search_price%' OR p.brand LIKE '%$search_price%' OR c.name LIKE '%$search_price%')";
}
$total_price  = $conn->query("SELECT COUNT(*) as c FROM products p JOIN categories c ON p.category_id=c.id WHERE $where_price")->fetch_assoc()['c'];
$offset_price = ($page_price - 1) * $per_page;
$prices = $conn->query("SELECT p.*, c.name as cat_name, ROUND(p.import_price*(1+p.profit_rate/100)) as sell_price
    FROM products p JOIN categories c ON p.category_id=c.id
    WHERE $where_price ORDER BY p.name
    LIMIT $per_page OFFSET $offset_price");

$categories = $conn->query("SELECT * FROM categories ORDER BY name");
?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab==='stock'?'active':'' ?>" href="?tab=stock&stock_date=<?= $stock_date ?>&stock_cat=<?= $stock_cat ?>">
            <i class="bi bi-box me-1"></i>Tra cứu tồn kho
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab==='report'?'active':'' ?>" href="?tab=report&rep_from=<?= $rep_from ?>&rep_to=<?= $rep_to ?>">
            <i class="bi bi-bar-chart me-1"></i>Báo cáo nhập-xuất
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab==='alert'?'active':'' ?>" href="?tab=alert&threshold=<?= $low_threshold ?>">
            <i class="bi bi-exclamation-triangle me-1"></i>Cảnh báo hết hàng
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab==='price'?'active':'' ?>" href="?tab=price">
            <i class="bi bi-tags me-1"></i>Quản lý giá bán
        </a>
    </li>
</ul>

<?php // ══════════════════════════════════════════════════════════════════════
// TAB 1: TRA CỨU TỒN KHO
if ($tab === 'stock'): ?>
<form method="GET" class="row g-3 mb-4">
    <input type="hidden" name="tab" value="stock">
    <div class="col-md-3">
        <label class="form-label">Xem tồn kho tại ngày</label>
        <input type="date" name="stock_date" class="form-control" value="<?= $stock_date ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Danh mục</label>
        <select name="stock_cat" class="form-select">
            <option value="">Tất cả danh mục</option>
            <?php while ($c = $categories->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>" <?= $stock_cat==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Tra cứu</button>
    </div>
</form>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 fw-bold">
        Tồn kho tại ngày <?= date('d/m/Y', strtotime($stock_date)) ?>
        <span class="badge bg-secondary ms-2"><?= $total_stock ?> sản phẩm</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Mã</th><th>Sản phẩm</th><th>Danh mục</th><th class="text-center">Tổng nhập</th><th class="text-center">Tổng bán</th><th class="text-center">Tồn kho</th><th class="text-end">Giá vốn</th><th class="text-end">Giá bán</th></tr>
            </thead>
            <tbody>
                <?php while ($s = $stocks->fetch_assoc()):
                    $ton_at_date  = max(0, $s['stock_quantity'] - $s['imported_after'] + $s['sold_after']);
                    $imp_to_date  = $s['total_imported_all'] == 0 ? $s['stock_quantity'] + $s['total_sold_all'] : $s['total_imported_all'] - $s['imported_after'];
                    $sold_to_date = $s['total_sold_all'] - $s['sold_after'];
                ?>
                <tr>
                    <td class="small text-muted"><?= htmlspecialchars($s['code']) ?></td>
                    <td><?= htmlspecialchars($s['name']) ?></td>
                    <td class="small"><?= htmlspecialchars($s['cat_name']) ?></td>
                    <td class="text-center"><?= $imp_to_date ?></td>
                    <td class="text-center"><?= $sold_to_date ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $ton_at_date<=0?'danger':($ton_at_date<=5?'warning':'success') ?>"><?= $ton_at_date ?></span>
                    </td>
                    <td class="text-end small"><?= formatPrice($s['import_price']) ?></td>
                    <td class="text-end small fw-bold" style="color:#e74c3c"><?= formatPrice($s['sell_price']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_stock > $per_page): ?>
    <div class="card-footer bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">Hiển thị <?= min($offset_stock+1,$total_stock) ?>–<?= min($offset_stock+$per_page,$total_stock) ?> / <?= $total_stock ?></small>
            <?= renderPagination($total_stock, $page_stock, $per_page, ['tab'=>'stock','stock_date'=>$stock_date,'stock_cat'=>$stock_cat]) ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php // ══════════════════════════════════════════════════════════════════════
// TAB 2: BÁO CÁO NHẬP XUẤT
elseif ($tab === 'report'): ?>
<form method="GET" class="row g-3 mb-4">
    <input type="hidden" name="tab" value="report">
    <div class="col-md-3">
        <label class="form-label">Từ ngày</label>
        <input type="date" name="rep_from" class="form-control" value="<?= $rep_from ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Đến ngày</label>
        <input type="date" name="rep_to" class="form-control" value="<?= $rep_to ?>">
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Xem báo cáo</button>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 fw-bold">
        Báo cáo nhập-xuất từ <?= date('d/m/Y', strtotime($rep_from)) ?> đến <?= date('d/m/Y', strtotime($rep_to)) ?>
        <span class="text-muted fw-normal small ms-2">— Nhấp vào số lượng để xem chi tiết</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Mã</th><th>Sản phẩm</th><th>Danh mục</th>
                    <th class="text-center">SL nhập (kỳ)</th>
                    <th class="text-center">SL bán (kỳ)</th>
                    <th class="text-center">Tổng bán (all)</th>
                    <th class="text-center">Tồn hiện tại</th>
                </tr>
            </thead>
            <tbody>
                <?php $ti=0; $ts=0;
                while ($r = $report->fetch_assoc()):
                    $ti += $r['qty_imported']; $ts += $r['qty_sold'];
                ?>
                <tr>
                    <td class="small text-muted"><?= htmlspecialchars($r['code']) ?></td>
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td class="small"><?= htmlspecialchars($r['cat_name']) ?></td>
                    <!-- SL nhập kỳ: clickable nếu > 0 -->
                    <td class="text-center">
                        <?php if ($r['qty_imported'] > 0): ?>
                        <a href="#" class="fw-bold text-primary text-decoration-none drill-btn"
                           data-type="import"
                           data-pid="<?= $r['id'] ?>"
                           data-name="<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>"
                           data-from="<?= $rep_from ?>" data-to="<?= $rep_to ?>"
                           title="Xem chi tiết phiếu nhập">
                            <?= $r['qty_imported'] ?> <i class="bi bi-zoom-in" style="font-size:.7rem"></i>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <!-- SL bán kỳ: clickable nếu > 0 -->
                    <td class="text-center">
                        <?php if ($r['qty_sold'] > 0): ?>
                        <a href="#" class="fw-bold text-success text-decoration-none drill-btn"
                           data-type="sold"
                           data-pid="<?= $r['id'] ?>"
                           data-name="<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>"
                           data-from="<?= $rep_from ?>" data-to="<?= $rep_to ?>"
                           title="Xem chi tiết đơn bán">
                            <?= $r['qty_sold'] ?> <i class="bi bi-zoom-in" style="font-size:.7rem"></i>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center text-secondary"><?= $r['total_sold_ever'] ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $r['stock_quantity']==0?'danger':($r['stock_quantity']<=5?'warning':'success') ?>">
                            <?= $r['stock_quantity'] ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot class="table-secondary">
                <tr>
                    <td colspan="3" class="fw-bold">Tổng cộng:</td>
                    <td class="text-center fw-bold text-primary"><?= $ti ?></td>
                    <td class="text-center fw-bold text-success"><?= $ts ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php if ($total_rep > $per_page): ?>
    <div class="card-footer bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">Hiển thị <?= min($offset_rep+1,$total_rep) ?>–<?= min($offset_rep+$per_page,$total_rep) ?> / <?= $total_rep ?></small>
            <?= renderPagination($total_rep, $page_rep, $per_page, ['tab'=>'report','rep_from'=>$rep_from,'rep_to'=>$rep_to]) ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal drill-down -->
<div class="modal fade" id="drillModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="drillTitle">Chi tiết</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="drillBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Đang tải...</p>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto" id="drillSub"></small>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.drill-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const type  = this.dataset.type;
        const pid   = this.dataset.pid;
        const name  = this.dataset.name;
        const from  = this.dataset.from;
        const to    = this.dataset.to;

        const isImport = type === 'import';
        const title = isImport
            ? `Chi tiết nhập kho: ${name}`
            : `Chi tiết đơn bán: ${name}`;

        document.getElementById('drillTitle').textContent = title;
        document.getElementById('drillSub').textContent =
            `Từ ${from.split('-').reverse().join('/')} đến ${to.split('-').reverse().join('/')}`;
        document.getElementById('drillBody').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">Đang tải...</p>
            </div>`;

        new bootstrap.Modal(document.getElementById('drillModal')).show();

        fetch(`?ajax=detail&type=${type}&pid=${pid}&from=${from}&to=${to}`)
            .then(r => r.json())
            .then(res => {
                if (!res.ok || !res.data.length) {
                    document.getElementById('drillBody').innerHTML =
                        '<p class="text-center text-muted py-3">Không có dữ liệu.</p>';
                    return;
                }
                const rows = res.data;
                let html = '<div class="table-responsive"><table class="table table-bordered table-sm align-middle mb-0"><thead class="table-light"><tr>';

                if (isImport) {
                    html += '<th>Mã phiếu</th><th>Ngày nhập</th><th>Sản phẩm</th><th class="text-center">SL</th><th class="text-end">Giá nhập</th><th class="text-end">Thành tiền</th>';
                } else {
                    html += '<th>Mã đơn hàng</th><th>Ngày bán</th><th>Sản phẩm</th><th class="text-center">SL</th><th class="text-end">Đơn giá</th><th class="text-end">Thành tiền</th>';
                }
                html += '</tr></thead><tbody>';

                let totalQty = 0, totalAmt = 0;
                rows.forEach(r => {
                    const qty   = parseInt(r.quantity);
                    const price = parseFloat(isImport ? r.import_price : r.unit_price);
                    const total = qty * price;
                    totalQty += qty; totalAmt += total;

                    const dateStr = (isImport ? r.import_date : r.order_date) || '';
                    const code    = isImport ? r.receipt_code : r.order_code;
                    const fmtP    = price.toLocaleString('vi-VN') + ' ₫';
                    const fmtT    = total.toLocaleString('vi-VN') + ' ₫';

                    html += `<tr>
                        <td class="fw-semibold small">${code}</td>
                        <td class="small">${dateStr}</td>
                        <td>${r.product_name}</td>
                        <td class="text-center fw-bold">${qty}</td>
                        <td class="text-end small">${fmtP}</td>
                        <td class="text-end fw-semibold">${fmtT}</td>
                    </tr>`;
                });

                // Footer tổng
                html += `<tr class="table-secondary fw-bold">
                    <td colspan="3">Tổng cộng</td>
                    <td class="text-center">${totalQty}</td>
                    <td></td>
                    <td class="text-end">${totalAmt.toLocaleString('vi-VN')} ₫</td>
                </tr>`;
                html += '</tbody></table></div>';
                document.getElementById('drillBody').innerHTML = html;
            })
            .catch(() => {
                document.getElementById('drillBody').innerHTML =
                    '<p class="text-center text-danger py-3">Lỗi khi tải dữ liệu.</p>';
            });
    });
});
</script>

<?php // ══════════════════════════════════════════════════════════════════════
// TAB 3: CẢNH BÁO HẾT HÀNG
elseif ($tab === 'alert'): ?>
<form method="GET" class="row g-3 mb-4">
    <input type="hidden" name="tab" value="alert">
    <div class="col-md-4">
        <label class="form-label fw-semibold">Ngưỡng cảnh báo (số lượng tồn kho)</label>
        <div class="input-group">
            <input type="number" name="threshold" class="form-control" value="<?= $low_threshold ?>" min="1" max="9999">
            <button class="btn btn-warning fw-semibold"><i class="bi bi-funnel me-1"></i>Áp dụng</button>
        </div>
        <div class="form-text">Hiển thị sản phẩm có tồn kho ≤ ngưỡng này.</div>
    </div>
</form>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-warning text-dark border-0 fw-bold">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>Sản phẩm sắp hết hàng (≤ <?= $low_threshold ?>)
        <span class="badge bg-dark ms-2"><?= $total_alert ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Mã</th><th>Sản phẩm</th><th>Danh mục</th><th class="text-center">Tồn kho</th><th class="text-center">Hành động</th></tr>
            </thead>
            <tbody>
                <?php if ($low_products->num_rows === 0): ?>
                <tr><td colspan="5" class="text-center text-success py-4">
                    <i class="bi bi-check-circle me-2"></i>Không có sản phẩm nào sắp hết hàng.
                </td></tr>
                <?php endif; ?>
                <?php while ($p = $low_products->fetch_assoc()): ?>
                <tr>
                    <td class="small text-muted"><?= htmlspecialchars($p['code']) ?></td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><?= htmlspecialchars($p['cat_name']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $p['stock_quantity']==0?'danger':'warning' ?>"><?= $p['stock_quantity'] ?></span>
                    </td>
                    <td class="text-center">
                        <a href="imports.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-truck me-1"></i>Nhập hàng
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_alert > $per_page): ?>
    <div class="card-footer bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">Hiển thị <?= min($offset_alert+1,$total_alert) ?>–<?= min($offset_alert+$per_page,$total_alert) ?> / <?= $total_alert ?></small>
            <?= renderPagination($total_alert, $page_alert, $per_page, ['tab'=>'alert','threshold'=>$low_threshold]) ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php // ══════════════════════════════════════════════════════════════════════
// TAB 4: QUẢN LÝ GIÁ BÁN
elseif ($tab === 'price'): ?>

<!-- Thanh tìm kiếm -->
<form method="GET" class="mb-3 d-flex gap-2" id="priceSearchForm">
    <input type="hidden" name="tab" value="price">
    <div class="input-group" style="max-width:360px">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="search_price" class="form-control"
               placeholder="Tìm tên sản phẩm, thương hiệu, danh mục..."
               value="<?= htmlspecialchars($search_price) ?>">
        <?php if ($search_price): ?>
        <a href="?tab=price" class="btn btn-outline-secondary" title="Xóa bộ lọc"><i class="bi bi-x"></i></a>
        <?php endif; ?>
    </div>
    <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Tìm</button>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 fw-bold">
        <i class="bi bi-tags me-2"></i>Quản lý giá bán theo sản phẩm
        <span class="badge bg-secondary ms-2"><?= $total_price ?> sản phẩm<?= $search_price ? ' (đã lọc)' : '' ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Sản phẩm</th>
                    <th class="text-end">Giá vốn (bình quân)</th>
                    <th class="text-center" style="width:220px">% Lợi nhuận</th>
                    <th class="text-end">Giá bán</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($p = $prices->fetch_assoc()): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($p['name']) ?>
                        <br><small class="text-muted"><?= htmlspecialchars($p['cat_name']) ?></small>
                    </td>
                    <td class="text-end"><?= formatPrice($p['import_price']) ?></td>
                    <td class="text-center">
                        <form method="POST" class="d-flex align-items-center gap-2 justify-content-center">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="tab" value="price">
                            <input type="hidden" name="page" value="<?= $page_price ?>">
                            <input type="hidden" name="search_price" value="<?= htmlspecialchars($search_price) ?>">
                            <div class="input-group input-group-sm" style="width:110px">
                                <input type="number" name="profit_rate" class="form-control" value="<?= $p['profit_rate'] ?>" step="0.01" min="0">
                                <span class="input-group-text">%</span>
                            </div>
                            <button type="submit" name="update_price" class="btn btn-sm btn-primary">Lưu</button>
                        </form>
                    </td>
                    <td class="text-end fw-bold" style="color:#e74c3c"><?= formatPrice($p['sell_price']) ?></td>
                </tr>
                <?php endwhile; ?>
                <?php if ($total_price === 0): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">Không tìm thấy sản phẩm nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_price > $per_page): ?>
    <div class="card-footer bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">Hiển thị <?= min($offset_price+1,$total_price) ?>–<?= min($offset_price+$per_page,$total_price) ?> / <?= $total_price ?></small>
            <?= renderPagination($total_price, $page_price, $per_page, ['tab'=>'price','search_price'=>$search_price]) ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php adminFooter(); ?>