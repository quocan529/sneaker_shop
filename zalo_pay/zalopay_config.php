<?php
// =====================================================
// ZALOPAY SANDBOX CONFIG
// Credentials sandbox public — dùng được luôn, không cần đăng ký
// =====================================================

define('ZALOPAY_APP_ID',   2553);
define('ZALOPAY_KEY1',     'PcY4iZIKFCIdgZvA6ueMcMHHUbRLYjPL');
define('ZALOPAY_KEY2',     'kLtgPl8HHhfvMuDHPwKfgfsY4Vu/kms31PDP4Czfts=');
define('ZALOPAY_ENDPOINT', 'https://sb-openapi.zalopay.vn/v2/create');

// ⚠️ THAY DÒNG NÀY bằng URL ngrok của bạn (xem hướng dẫn ngrok bên dưới)
// Ví dụ: 'https://abc123.ngrok-free.app'
// Không có dấu / ở cuối
define('APP_URL', 'https://uninfusive-audry-reptilelike.ngrok-free.dev');

// Đường dẫn tới thư mục zalo_pay trong project
// Ví dụ nếu project ở localhost/sneaker_shop thì:
define('APP_PATH', '/TMDT-UD_sneaker_shop/zalo_pay');

define('ZALOPAY_RETURN_URL',   APP_URL . APP_PATH . '/zalopay_return.php');
define('ZALOPAY_CALLBACK_URL', APP_URL . APP_PATH . '/zalopay_callback.php');
