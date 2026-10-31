<?php
// includes/business_helpers.php
// Bộ hàm nghiệp vụ tự động hóa quản lý tour, giá vé và điểm thưởng

/**
 * 1. Tự động tính lại số chỗ đã đặt và cập nhật trạng thái đợt khởi hành
 */
function sync_departure_seats(PDO $pdo, int $departure_id): bool {
    if ($departure_id <= 0) return false;

    // Lấy thông tin đợt khởi hành
    $stmtDep = $pdo->prepare("SELECT max_seats, status FROM tour_departures WHERE departure_id = ?");
    $stmtDep->execute([$departure_id]);
    $dep = $stmtDep->fetch(PDO::FETCH_ASSOC);
    if (!$dep) return false;

    $max_seats = (int)$dep['max_seats'];
    $current_status = $dep['status'];

    // Tính tổng số khách (người lớn + trẻ em + em bé) của các đơn KHÔNG BỊ HỦY
    $stmtSeats = $pdo->prepare("
        SELECT COALESCE(SUM(num_adults + num_children + num_infants), 0) AS total_booked
        FROM bookings 
        WHERE departure_id = ? 
          AND booking_status != 'Đã hủy'
    ");
    $stmtSeats->execute([$departure_id]);
    $booked_seats = (int)$stmtSeats->fetchColumn();

    // Xác định trạng thái tự động (giữ nguyên nếu tour đã khởi hành, hoàn tất hoặc đã hủy)
    $new_status = $current_status;
    if (!in_array($current_status, ['Đã khởi hành', 'Đã hoàn tất', 'Đã hủy'])) {
        if ($booked_seats >= $max_seats) {
            $new_status = 'Đã khóa sổ';
        } elseif (($max_seats - $booked_seats) <= 5 && ($max_seats - $booked_seats) > 0) {
            $new_status = 'Sắp hết chỗ';
        } else {
            $new_status = 'Mở bán';
        }
    }

    // Cập nhật lại booked_seats và status vào CSDL
    $stmtUpdate = $pdo->prepare("
        UPDATE tour_departures 
        SET booked_seats = :booked, status = :status 
        WHERE departure_id = :dep_id
    ");
    return $stmtUpdate->execute([
        'booked' => $booked_seats,
        'status' => $new_status,
        'dep_id' => $departure_id
    ]);
}

/**
 * 2. Tính tiền tour theo bảng giá và số lượng hành khách chọn phòng đơn thực tế
 */
function calculate_booking_pricing(PDO $pdo, int $departure_id, int $adults, int $children, int $infants, int $single_rooms): array {
    // Lấy bảng giá theo đợt khởi hành
    $stmtPrice = $pdo->prepare("SELECT * FROM tour_prices WHERE departure_id = ?");
    $stmtPrice->execute([$departure_id]);
    $price = $stmtPrice->fetch(PDO::FETCH_ASSOC);

    if (!$price) {
        throw new Exception("Chưa thiết lập bảng giá cho đợt khởi hành này!");
    }

    $price_adult = (float)$price['price_adult'];
    $price_child = (float)$price['price_child'];
    $price_infant = (float)($price['price_infant'] ?? 0);
    $single_supplement = (float)($price['single_supplement'] ?? 0);

    $cost_adults = $adults * $price_adult;
    $cost_children = $children * $price_child;
    $cost_infants = $infants * $price_infant;
    $cost_single_room = $single_rooms * $single_supplement;

    // Tổng tiền chưa trừ khuyến mãi
    $subtotal = $cost_adults + $cost_children + $cost_infants + $cost_single_room;

    return [
        'price_adult'       => $price_adult,
        'price_child'       => $price_child,
        'price_infant'      => $price_infant,
        'single_supplement' => $single_supplement,
        'cost_adults'       => $cost_adults,
        'cost_children'     => $cost_children,
        'cost_infants'      => $cost_infants,
        'cost_single_room'  => $cost_single_room,
        'subtotal'          => $subtotal
    ];
}

/**
 * 3. Tự động cộng điểm thưởng cho khách hàng khi đơn đặt chuyển sang 'Hoàn tất'
 * Quy tắc: Mỗi 100.000 VNĐ thực thanh toán = 1 điểm thưởng
 */
function reward_points_on_booking_complete(PDO $pdo, int $booking_id): bool {
    // Lấy thông tin đơn hàng và kiểm tra cờ is_rewarded
    $stmt = $pdo->prepare("SELECT customer_id, paid_amount, booking_status, is_rewarded FROM bookings WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    $b = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$b || $b['booking_status'] !== 'Hoàn tất' || (int)$b['is_rewarded'] === 1) {
        return false;
    }

    $customer_id = (int)$b['customer_id'];
    $paid_amount = (float)$b['paid_amount'];

    // Tính điểm thưởng tích lũy: 100.000đ = 1 điểm
    $points_earned = (int)floor($paid_amount / 100000);

    if ($points_earned > 0) {
        // Cộng điểm vào bảng customers
        $stmtPoint = $pdo->prepare("UPDATE customers SET reward_points = reward_points + ? WHERE customer_id = ?");
        $stmtPoint->execute([$points_earned, $customer_id]);
    }

    // Đánh dấu đơn đã được cộng điểm
    $stmtFlag = $pdo->prepare("UPDATE bookings SET is_rewarded = 1 WHERE booking_id = ?");
    return $stmtFlag->execute([$booking_id]);
}