<?php
// modules/reviews/submit_review.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$booking_id = intval($_POST['booking_id'] ?? 0);
$rating = max(1, min(5, intval($_POST['rating'] ?? 5)));
$comment = trim($_POST['comment'] ?? '');

if (empty($booking_id) || empty($comment)) {
    die("Dữ liệu đánh giá không hợp lệ!");
}

// 1. Kiểm tra đơn đặt tour có hợp lệ và đã hoàn tất hay chưa
$stmtB = $pdo->prepare("SELECT b.customer_id, dep.tour_id, b.booking_status 
                        FROM bookings b
                        JOIN tour_departures dep ON b.departure_id = dep.departure_id
                        WHERE b.booking_id = ?");
$stmtB->execute([$booking_id]);
$book = $stmtB->fetch();

if (!$book || $book['booking_status'] !== 'Hoàn tất') {
    die("Chỉ các đơn đặt tour đã hoàn tất chuyến đi mới được phép gửi đánh giá!");
}

// 2. Kiểm tra xem đơn này đã từng được đánh giá chưa
$stmtCheck = $pdo->prepare("SELECT review_id FROM reviews WHERE booking_id = ?");
$stmtCheck->execute([$booking_id]);
if ($stmtCheck->fetch()) {
    die("Bạn đã gửi đánh giá cho chuyến đi này rồi!");
}

// 3. Lưu vào bảng reviews với trạng thái ban đầu là 'Chờ duyệt'
$stmtIns = $pdo->prepare("INSERT INTO reviews (tour_id, customer_id, booking_id, rating, comment, status) 
                          VALUES (?, ?, ?, ?, ?, 'Chờ duyệt')");
$stmtIns->execute([$book['tour_id'], $book['customer_id'], $booking_id, $rating, $comment]);

// Quay về trang quản lý chuyến đi kèm thông báo
header("Location: " . BASE_URL . "modules/booking/my_bookings.php?review_success=1");
exit;