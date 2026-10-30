<?php
// config/constants.php - Khai báo hằng số toàn hệ thống
if (!defined('BASE_URL')) {
    define('BASE_URL', '/dulichvietnam/');
}
define('SITE_NAME', 'Công ty Du lịch Việt Nam');
define('COMPANY_TAX_CODE', '1600173428');
define('VAT_RATE', 0.08); // Thuế suất VAT 8%

define('STATUS_PENDING', 'Chờ duyệt');
define('STATUS_CONFIRMED', 'Đã xác nhận');
define('STATUS_CANCELLED', 'Đã hủy');