<?php
// api/get_chart_data.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Kiểm tra quyền: Chỉ nội bộ công ty được lấy dữ liệu tài chính
if (!is_logged_in() || !has_role(['Administrator', 'Accountant'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$year = intval($_GET['year'] ?? date('Y'));

// Khởi tạo mảng 12 tháng mặc định bằng 0
$monthly_data = array_fill(1, 12, 0);

// Truy vấn tổng doanh thu từng tháng trong năm từ SQL View
$stmt = $pdo->prepare("SELECT month, total_monthly_revenue FROM v_monthly_revenue WHERE year = ?");
$stmt->execute([$year]);
$results = $stmt->fetchAll();

foreach ($results as $row) {
    $monthly_data[intval($row['month'])] = floatval($row['total_monthly_revenue']);
}

echo json_encode([
    'year' => $year,
    'labels' => ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12'],
    'revenue' => array_values($monthly_data)
]);