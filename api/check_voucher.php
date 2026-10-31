<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$promo_code = isset($_REQUEST['code']) ? strtoupper(trim($_REQUEST['code'])) : '';
$order_total = isset($_REQUEST['subtotal']) ? (float)$_REQUEST['subtotal'] : 0;

if ($promo_code === '') {
    echo json_encode([
        'success' => false,
        'valid'   => false,
        'message' => 'Vui lòng nhập mã khuyến mãi.'
    ]);
    exit;
}

try {
    $today = date('Y-m-d');
    $sql = "SELECT * FROM promotions 
            WHERE UPPER(promo_code) = :code 
              AND status = 'Active' 
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':code' => $promo_code]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promo) {
        echo json_encode([
            'success' => false,
            'valid'   => false,
            'message' => 'Mã khuyến mãi không tồn tại hoặc đã bị vô hiệu hóa.'
        ]);
        exit;
    }

    if ($today < $promo['start_date'] || $today > $promo['end_date']) {
        echo json_encode([
            'success' => false,
            'valid'   => false,
            'message' => 'Mã khuyến mãi chưa đến đợt áp dụng hoặc đã hết hạn.'
        ]);
        exit;
    }

    if ((int)$promo['used_count'] >= (int)$promo['usage_limit']) {
        echo json_encode([
            'success' => false,
            'valid'   => false,
            'message' => 'Mã khuyến mãi đã hết lượt sử dụng.'
        ]);
        exit;
    }

    if ($order_total > 0 && $order_total < (float)$promo['min_order_amount']) {
        echo json_encode([
            'success' => false,
            'valid'   => false,
            'message' => 'Đơn hàng tối thiểu phải từ ' . number_format($promo['min_order_amount'], 0, ',', '.') . 'đ để áp dụng mã này.'
        ]);
        exit;
    }

    $discount_amount = 0;
    if ($promo['discount_type'] === 'Percentage') {
        $discount_amount = $order_total * ((float)$promo['discount_value'] / 100);
        if (!empty($promo['max_discount_amount']) && $discount_amount > (float)$promo['max_discount_amount']) {
            $discount_amount = (float)$promo['max_discount_amount'];
        }
    } else {
        $discount_amount = (float)$promo['discount_value'];
    }

    echo json_encode([
        'success'         => true,
        'valid'           => true,
        'promotion_id'    => (int)$promo['promotion_id'],
        'promo_code'      => $promo['promo_code'],
        'description'     => $promo['description'],
        'discount_type'   => $promo['discount_type'],
        'discount_value'  => (float)$promo['discount_value'],
        'max_discount'    => (float)$promo['max_discount_amount'],
        'discount_amount' => round($discount_amount),
        'final_total'     => max(0, round($order_total - $discount_amount)),
        'message'         => 'Áp dụng mã ' . $promo['promo_code'] . ' thành công!'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'valid'   => false,
        'message' => 'Lỗi hệ thống khi kiểm tra mã khuyến mãi.'
    ]);
}