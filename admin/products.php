<?php
// admin/products.php
require_once '_layout.php';
adminHeader('Quản lý sản phẩm');

$msg = '';

// DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Check if product has been imported
    $has_import = $conn->query("SELECT COUNT(*) as c FROM import_details WHERE product_id=$id")->fetch_assoc()['c'];
    if ($has_import > 0) {
        $conn->query("UPDATE products SET status='hidden' WHERE id=$id");
        $msg = '<div class="alert alert-info">Sản phẩm đã được nhập hàng, đã ẩn sản phẩm.</div>';
    } else {
        $conn->query("DELETE FROM products WHERE id=$id");
        $msg = '<div class="alert alert-success">Đã xóa sản phẩm.</div>';
    }
}

// SAVE (add/edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $code        = sanitize($conn, $_POST['code'] ?? '');
    $name        = sanitize($conn, $_POST['name'] ?? '');
    $cat_id      = (int)$_POST['category_id'];
    $desc        = sanitize($conn, $_POST['description'] ?? '');
    $unit        = sanitize($conn, $_POST['unit'] ?? 'đôi');
    $stock       = (int)$_POST['stock_quantity'];
    $profit_rate = (float)$_POST['profit_rate'];
    $status      = sanitize($conn, $_POST['status'] ?? 'active');
    // New attribute fields
    $brand       = sanitize($conn, $_POST['brand'] ?? '');
    $gender      = sanitize($conn, $_POST['gender'] ?? 'unisex');
    $color       = sanitize($conn, $_POST['color'] ?? '');
    $material    = sanitize($conn, $_POST['material'] ?? '');
    $origin      = sanitize($conn, $_POST['origin'] ?? '');
    // Sizes: collect checked checkboxes or manual input
    $sizes_raw   = isset($_POST['sizes']) && is_array($_POST['sizes']) ? $_POST['sizes'] : [];
    $sizes_extra = sanitize($conn, $_POST['sizes_extra'] ?? '');
    if ($sizes_extra) {
        $extra = array_map('trim', explode(',', $sizes_extra));
        $sizes_raw = array_unique(array_merge($sizes_raw, $extra));
    }
    sort($sizes_raw, SORT_NUMERIC);
    $available_sizes = implode(',', array_filter($sizes_raw));
    $image       = '';

    if (!$code || !$name || !$cat_id) {
        $msg = '<div class="alert alert-danger">Vui lòng điền đầy đủ thông tin bắt buộc.</div>';
    } else {
        // Handle image upload
        if (!empty($_FILES['image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $msg = '<div class="alert alert-danger">Định dạng ảnh không hợp lệ.</div>';
                goto end_save;
            }
            $filename = uniqid('sp_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $filename);
            $image = $filename;
        }

        if ($_POST['action'] === 'add') {
            $check = $conn->query("SELECT id FROM products WHERE code='$code'");
            if ($check->num_rows > 0) {
                $msg = '<div class="alert alert-danger">Mã sản phẩm đã tồn tại.</div>';
            } else {
                $conn->query("INSERT INTO products (code,name,category_id,description,unit,stock_quantity,profit_rate,image,brand,gender,available_sizes,color,material,origin,status)
                    VALUES ('$code','$name',$cat_id,'$desc','$unit',$stock,$profit_rate,'$image','$brand','$gender','$available_sizes','$color','$material','$origin','$status')");
                $msg = '<div class="alert alert-success">Đã thêm sản phẩm thành công.</div>';
                $_POST = [];
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $img_sql = $image ? ", image='$image'" : '';
            if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
                $img_sql = ", image=''";
            }
            $conn->query("UPDATE products SET code='$code',name='$name',category_id=$cat_id,description='$desc',unit='$unit',
                profit_rate=$profit_rate,status='$status',brand='$brand',gender='$gender',
                available_sizes='$available_sizes',color='$color',material='$material',origin='$origin'$img_sql WHERE id=$id");
            $msg = '<div class="alert alert-success">Đã cập nhật sản phẩm.</div>';
        }
        end_save:;
    }
}

$edit_p = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit_p = $conn->query("SELECT * FROM products WHERE id=$id")->fetch_assoc();
}

$categories = $conn->query("SELECT * FROM categories ORDER BY name");

// List with filters + pagination + search
$filter_cat    = isset($_GET['cat'])    ? (int)$_GET['cat'] : 0;
$filter_status = isset($_GET['status']) ? sanitize($conn, $_GET['status']) : '';
$search_p      = isset($_GET['q'])      ? sanitize($conn, $_GET['q']) : '';
$page_p        = max(1, (int)($_GET['page'] ?? 1));
$per_page_p    = 15;

$where = "1=1";
if ($filter_cat > 0)  $where .= " AND p.category_id=$filter_cat";
if ($filter_status)   $where .= " AND p.status='$filter_status'";
if ($search_p !== '')  $where .= " AND (p.name LIKE '%$search_p%' OR p.code LIKE '%$search_p%' OR p.brand LIKE '%$search_p%')";

$total_p = $conn->query("SELECT COUNT(*) as c FROM products p WHERE $where")->fetch_assoc()['c'];
$offset_p = ($page_p - 1) * $per_page_p;
$products = $conn->query("SELECT p.*, c.name as cat_name, ROUND(p.import_price*(1+p.profit_rate/100)) as sell_price
    FROM products p JOIN categories c ON p.category_id=c.id
    WHERE $where ORDER BY p.created_at DESC
    LIMIT $per_page_p OFFSET $offset_p");
$params_p = array_filter(['q'=>$search_p,'cat'=>$filter_cat,'status'=>$filter_status]);
?>

<?= $msg ?>

<?php
// Shared helper to render size checkboxes
function renderSizeCheckboxes($existing_sizes_str) {
    $presets  = ['36','37','38','39','40','41','42','43','44','45'];
    $selected = array_filter(array_map('trim', explode(',', $existing_sizes_str)));
    $html  = '<div class="d-flex flex-wrap mb-2" style="gap:10px 8px;">';
    foreach ($presets as $s) {
        $chk   = in_array($s, $selected) ? 'checked' : '';
        $bg    = in_array($s, $selected) ? 'background:#1a1a2e;color:#fff;border-color:#1a1a2e;' : 'background:#fff;color:#333;';
        $html .= "<label style='display:inline-flex;align-items:center;gap:5px;border:1.5px solid #ccc;border-radius:8px;padding:5px 10px;cursor:pointer;font-size:0.85rem;min-width:46px;justify-content:center;$bg'>
            <input type='checkbox' name='sizes[]' value='$s' $chk style='display:none' onchange='updateSizeLabel(this)'>
            $s
        </label>";
    }
    $html .= '</div>';
    $extras = array_diff($selected, $presets);
    $html .= '<input type="text" name="sizes_extra" class="form-control form-control-sm" placeholder="Thêm size khác, cách nhau bởi dấu phẩy (VD: 46,47)" value="' . implode(',', $extras) . '">';
    $html .= '<script>
function updateSizeLabel(cb){
    var lbl=cb.parentElement;
    if(cb.checked){lbl.style.background="#1a1a2e";lbl.style.color="#fff";lbl.style.borderColor="#1a1a2e";}
    else{lbl.style.background="#fff";lbl.style.color="#333";lbl.style.borderColor="#ccc";}
}
</script>';
    return $html;
}
?>

<?php if ($edit_p): ?>
<!-- Edit Form -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header fw-bold bg-white border-0">
        <i class="bi bi-pencil me-2"></i>Sửa sản phẩm: <?= htmlspecialchars($edit_p['name']) ?>
        <a href="products.php" class="btn btn-sm btn-outline-secondary float-end">Hủy</a>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $edit_p['id'] ?>">

            <h6 class="text-muted fw-bold mb-3 small">THÔNG TIN CƠ BẢN</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-2">
                    <label class="form-label">Mã SP <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control" value="<?= htmlspecialchars($edit_p['code']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit_p['name']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Danh mục <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select" required>
                        <?php $categories->data_seek(0); while ($c = $categories->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= $edit_p['category_id']==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hình ảnh</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <?php if ($edit_p['image']): ?>
                    <div class="mt-2 d-flex align-items-center gap-2">
                        <img src="../uploads/<?= htmlspecialchars($edit_p['image']) ?>" width="50" height="50" style="object-fit:cover;border-radius:4px">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeImg">
                            <label class="form-check-label small text-danger" for="removeImg">Bỏ ảnh</label>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label">Mô tả</label>
                    <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($edit_p['description']) ?></textarea>
                </div>
            </div>

            <h6 class="text-muted fw-bold mb-3 small">THUỘC TÍNH SẢN PHẨM</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-2">
                    <label class="form-label">Thương hiệu</label>
                    <input type="text" name="brand" class="form-control" value="<?= htmlspecialchars($edit_p['brand'] ?? '') ?>" placeholder="Nike, Adidas...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Đối tượng</label>
                    <select name="gender" class="form-select">
                        <option value="unisex" <?= ($edit_p['gender']??'')=='unisex'?'selected':'' ?>>Unisex</option>
                        <option value="nam"    <?= ($edit_p['gender']??'')=='nam'?'selected':'' ?>>Nam</option>
                        <option value="nu"     <?= ($edit_p['gender']??'')=='nu'?'selected':'' ?>>Nữ</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Màu sắc</label>
                    <input type="text" name="color" class="form-control" value="<?= htmlspecialchars($edit_p['color'] ?? '') ?>" placeholder="Trắng, Đen...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Chất liệu</label>
                    <input type="text" name="material" class="form-control" value="<?= htmlspecialchars($edit_p['material'] ?? '') ?>" placeholder="Da thật, vải...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Xuất xứ</label>
                    <input type="text" name="origin" class="form-control" value="<?= htmlspecialchars($edit_p['origin'] ?? '') ?>" placeholder="Việt Nam, TQ...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Đơn vị</label>
                    <input type="text" name="unit" class="form-control" value="<?= htmlspecialchars($edit_p['unit']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">% Lợi nhuận</label>
                    <div class="input-group">
                        <input type="number" name="profit_rate" class="form-control" value="<?= $edit_p['profit_rate'] ?>" step="0.01" min="0">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hiện trạng</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $edit_p['status']=='active'?'selected':'' ?>>Đang bán</option>
                        <option value="hidden" <?= $edit_p['status']=='hidden'?'selected':'' ?>>Ẩn</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Size có sẵn</label>
                    <?= renderSizeCheckboxes($edit_p['available_sizes'] ?? '') ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary me-2"><i class="bi bi-save me-1"></i>Lưu thay đổi</button>
            <a href="products.php" class="btn btn-outline-secondary">Hủy</a>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Add Form (collapsed) -->
<?php if (!$edit_p): ?>
<div class="mb-4">
    <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addForm">
        <i class="bi bi-plus-circle me-2"></i>Thêm sản phẩm mới
    </button>
</div>
<div class="collapse mb-4" id="addForm">
    <div class="card border-0 shadow-sm">
        <div class="card-header fw-bold bg-white border-0"><i class="bi bi-plus me-2"></i>Thêm sản phẩm</div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">

                <h6 class="text-muted fw-bold mb-3 small">THÔNG TIN CƠ BẢN</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-2">
                        <label class="form-label">Mã SP <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" value="<?= htmlspecialchars($_POST['code'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Danh mục <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">-- Chọn danh mục --</option>
                            <?php $categories->data_seek(0); while ($c = $categories->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hình ảnh</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <h6 class="text-muted fw-bold mb-3 small">THUỘC TÍNH SẢN PHẨM</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-2">
                        <label class="form-label">Thương hiệu</label>
                        <input type="text" name="brand" class="form-control" placeholder="Nike, Adidas...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Đối tượng</label>
                        <select name="gender" class="form-select">
                            <option value="unisex">Unisex</option>
                            <option value="nam">Nam</option>
                            <option value="nu">Nữ</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Màu sắc</label>
                        <input type="text" name="color" class="form-control" placeholder="Trắng, Đen...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Chất liệu</label>
                        <input type="text" name="material" class="form-control" placeholder="Da thật, vải...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Xuất xứ</label>
                        <input type="text" name="origin" class="form-control" placeholder="Việt Nam, TQ...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Đơn vị</label>
                        <input type="text" name="unit" class="form-control" value="đôi">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">SL ban đầu</label>
                        <input type="number" name="stock_quantity" class="form-control" value="0" min="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">% Lợi nhuận</label>
                        <div class="input-group">
                            <input type="number" name="profit_rate" class="form-control" value="30" step="0.01" min="0">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Size có sẵn</label>
                        <?= renderSizeCheckboxes('') ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Thêm sản phẩm</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filters + List -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong><i class="bi bi-list-ul me-2"></i>Danh sách sản phẩm <span class="badge bg-secondary"><?= $total_p ?></span></strong>
            <form class="d-flex gap-2 flex-wrap" method="GET">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Tìm tên, mã, thương hiệu..." value="<?= htmlspecialchars($search_p) ?>" style="width:200px">
                <select name="cat" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
                    <option value="">Tất cả danh mục</option>
                    <?php $categories->data_seek(0); while ($c = $categories->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>" <?= $filter_cat==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <select name="status" class="form-select form-select-sm" style="width:130px" onchange="this.form.submit()">
                    <option value="">Tất cả</option>
                    <option value="active" <?= $filter_status==='active'?'selected':'' ?>>Đang bán</option>
                    <option value="hidden" <?= $filter_status==='hidden'?'selected':'' ?>>Đã ẩn</option>
                </select>
                <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
                <?php if ($search_p || $filter_cat || $filter_status): ?>
                <a href="products.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Mã</th><th>Sản phẩm</th><th>Danh mục</th><th>Thương hiệu</th><th>Đối tượng</th><th class="text-end">Giá vốn</th><th class="text-end">Giá bán</th><th class="text-center">Tồn kho</th><th class="text-center">Trạng thái</th><th class="text-center">Thao tác</th></tr>
            </thead>
            <tbody>
                <?php
                $genderBadge = ['nam'=>'<span class="badge bg-primary">Nam</span>','nu'=>'<span class="badge" style="background:#e91e8c">Nữ</span>','unisex'=>'<span class="badge bg-secondary">Unisex</span>'];
                while ($p = $products->fetch_assoc()): ?>
                <tr>
                    <td class="small text-muted"><?= htmlspecialchars($p['code']) ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($p['image'] && file_exists('../uploads/'.$p['image'])): ?>
                            <img src="../uploads/<?= htmlspecialchars($p['image']) ?>" width="40" height="40" style="object-fit:cover;border-radius:6px">
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
                                <?php if ($p['color']): ?>
                                <small class="text-muted"><?= htmlspecialchars($p['color']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="small"><?= htmlspecialchars($p['cat_name']) ?></td>
                    <td class="small"><?= htmlspecialchars($p['brand'] ?? '—') ?></td>
                    <td><?= $genderBadge[$p['gender'] ?? 'unisex'] ?? '' ?></td>
                    <td class="text-end small"><?= formatPrice($p['import_price']) ?></td>
                    <td class="text-end small fw-bold" style="color:#ff6b35"><?= formatPrice($p['sell_price']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $p['stock_quantity'] == 0 ? 'danger' : ($p['stock_quantity'] <= 5 ? 'warning' : 'success') ?>">
                            <?= $p['stock_quantity'] ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-<?= $p['status']=='active' ? 'success' : 'secondary' ?>">
                            <?= $p['status'] == 'active' ? 'Đang bán' : 'Ẩn' ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="products.php?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline-warning me-1" title="Sửa"><i class="bi bi-pencil"></i></a>
                        <a href="products.php?delete=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa/ẩn sản phẩm này?')" title="Xóa"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_p > $per_page_p): ?>
    <div class="card-footer bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">Hiển thị <?= min($offset_p+1,$total_p) ?>–<?= min($offset_p+$per_page_p,$total_p) ?> / <?= $total_p ?> sản phẩm</small>
            <?= renderPagination($total_p, $page_p, $per_page_p, $params_p) ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php adminFooter(); ?>
