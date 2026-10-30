<?php
// modules/booking/booking_step1.php
require_once '../../config/database.php';
$dep_id = intval($_GET['dep_id'] ?? 1);

// 1. Lấy thông tin đợt khởi hành & bảng giá
$stmt = $pdo->prepare("SELECT dep.*, t.title, t.tour_code, p.price_adult, p.price_child, p.price_infant,
                              (dep.max_seats - dep.booked_seats) AS available_seats
                       FROM tour_departures dep
                       JOIN tours t ON dep.tour_id = t.tour_id
                       JOIN tour_prices p ON dep.departure_id = p.departure_id
                       WHERE dep.departure_id = ?");
$stmt->execute([$dep_id]);
$dep = $stmt->fetch();

if (!$dep) die("Đợt khởi hành không hợp lệ!");

$success_msg = "";
$error_msg = "";

// 2. Xử lý khi bấm nút "Xác Nhận Đặt Tour"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $fullname = trim($_POST['lead_fullname']);
        $phone = trim($_POST['lead_phone']);
        $email = trim($_POST['lead_email']);
        $address = trim($_POST['lead_address']);
        $num_adults = intval($_POST['num_adults']);
        $num_children = intval($_POST['num_children'] ?? 0);
        $promo_code = trim($_POST['promo_code'] ?? '');
        $special_notes = trim($_POST['special_notes'] ?? '');

        // 2.1. Thêm hoặc cập nhật bảng customers
        $stmtCust = $pdo->prepare("INSERT INTO customers (fullname, email, phone, address) VALUES (?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE customer_id=LAST_INSERT_ID(customer_id), phone=VALUES(phone)");
        $stmtCust->execute([$fullname, $email, $phone, $address]);
        $customer_id = $pdo->lastInsertId();

        // 2.2. Tính toán tiền và kiểm tra khuyến mãi (bảng promotions)
        $subtotal = ($num_adults * $dep['price_adult']) + ($num_children * $dep['price_child']);
        $discount = 0;
        $promo_id = null;

        if (!empty($promo_code)) {
            $stmtP = $pdo->prepare("SELECT * FROM promotions WHERE promo_code = ? AND status = 'Active' AND start_date <= CURDATE() AND end_date >= CURDATE()");
            $stmtP->execute([$promo_code]);
            $promo = $stmtP->fetch();
            if ($promo) {
                $promo_id = $promo['promotion_id'];
                if ($promo['discount_type'] === 'Percentage') {
                    $discount = ($subtotal * $promo['discount_value']) / 100;
                    if ($promo['max_discount_amount'] && $discount > $promo['max_discount_amount']) {
                        $discount = $promo['max_discount_amount'];
                    }
                } else {
                    $discount = $promo['discount_value'];
                }
            }
        }
        $total_amount = max(0, $subtotal - $discount);
        $deposit_amount = round($total_amount * 0.5); // Đặt cọc 50%
        $booking_code = 'BK' . date('ymd') . rand(100, 999);

        // 2.3. Thêm vào bảng bookings
        $stmtBook = $pdo->prepare("INSERT INTO bookings (booking_code, departure_id, customer_id, promotion_id, num_adults, num_children, subtotal, discount_amount, total_amount, paid_amount, payment_status, booking_status, special_notes)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Đã đặt cọc', 'Chờ duyệt', ?)");
        $stmtBook->execute([$booking_code, $dep_id, $customer_id, $promo_id, $num_adults, $num_children, $subtotal, $discount, $total_amount, $deposit_amount, $special_notes]);
        $booking_id = $pdo->lastInsertId();

        // 2.4. Lưu danh sách từng hành khách để làm BẢO HIỂM (bảng booking_passengers)
        $passengers = $_POST['passenger_name'] ?? [];
        $pass_genders = $_POST['passenger_gender'] ?? [];
        $pass_cards = $_POST['passenger_idcard'] ?? [];
        $pass_types = $_POST['passenger_type'] ?? [];

        $stmtPass = $pdo->prepare("INSERT INTO booking_passengers (booking_id, fullname, gender, passenger_type, id_card) VALUES (?, ?, ?, ?, ?)");
        for ($i = 0; $i < count($passengers); $i++) {
            if (!empty($passengers[$i])) {
                $stmtPass->execute([$booking_id, $passengers[$i], $pass_genders[$i] ?? 'Nam', $pass_types[$i] ?? 'Người lớn', $pass_cards[$i] ?? '']);
            }
        }

        // 2.5. Tạo bản ghi thanh toán phiếu thu tiền cọc (bảng payments)
        $pay_code = 'PAY' . date('ymd') . rand(100, 999);
        $stmtPay = $pdo->prepare("INSERT INTO payments (booking_id, payment_code, payment_method, payment_phase, amount, status) VALUES (?, ?, 'Chuyển khoản QR', 'Đặt cọc', ?, 'Thành công')");
        $stmtPay->execute([$booking_id, $pay_code, $deposit_amount]);

        // 2.6. Cập nhật trừ số chỗ đã đặt trên đợt khởi hành
        $total_seats = $num_adults + $num_children;
        $pdo->prepare("UPDATE tour_departures SET booked_seats = booked_seats + ? WHERE departure_id = ?")->execute([$total_seats, $dep_id]);

        $pdo->commit();
        $success_msg = "Đặt tour thành công! Mã đơn: <strong>{$booking_code}</strong>. Đã ghi nhận phiếu thu tiền cọc: <strong>" . number_format($deposit_amount, 0, ',', '.') . " đ</strong>.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Lỗi khi xử lý đặt tour: " . $e->getMessage();
    }
}

$page_title = "Đăng Ký Đặt Tour & Nhập Thông Tin Bảo Hiểm";
include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-xl border border-slate-200">
        <h1 class="text-2xl font-black text-slate-900 border-b pb-4 flex items-center gap-2">
            <i data-lucide="shield-plus" class="w-6 h-6 text-blue-600"></i> ĐĂNG KÝ TOUR & THÔNG TIN BẢO HIỂM DU LỊCH
        </h1>

        <?php if ($success_msg): ?>
            <div class="my-4 p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-200 text-sm">
                <?= $success_msg ?>
                <div class="mt-3">
                    <a href="<?= BASE_URL ?>index.php" class="bg-emerald-600 text-white font-bold px-4 py-2 rounded-lg text-xs">Về trang chủ</a>
                </div>
            </div>
        <?php elseif ($error_msg): ?>
            <div class="my-4 p-4 bg-rose-50 text-rose-800 rounded-xl border border-rose-200 text-sm"><?= $error_msg ?></div>
        <?php endif; ?>

        <?php if (!$success_msg): ?>
        <form method="POST" class="space-y-6 mt-6">
            <!-- Tóm tắt tour -->
            <div class="p-4 bg-blue-50 rounded-xl flex justify-between items-center text-xs">
                <div>
                    <span class="text-slate-400 block font-medium">Tour bạn chọn:</span>
                    <strong class="text-sm text-slate-800"><?= htmlspecialchars($dep['title']) ?></strong>
                </div>
                <div class="text-right">
                    <span class="text-slate-400 block font-medium">Khởi hành:</span>
                    <strong class="text-sm text-blue-600"><?= date('d/m/Y', strtotime($dep['departure_date'])) ?></strong>
                </div>
            </div>

            <!-- Người đại diện liên hệ (bảng customers) -->
            <div class="space-y-3">
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide border-b pb-1">1. Thông tin người đại diện đặt tour</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <label class="block font-semibold mb-1">Họ và tên (*):</label>
                        <input type="text" name="lead_fullname" required class="w-full border rounded-lg p-2.5" placeholder="Nguyễn Văn A">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Số điện thoại (*):</label>
                        <input type="tel" name="lead_phone" required class="w-full border rounded-lg p-2.5" placeholder="0912345678">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Email (*):</label>
                        <input type="email" name="lead_email" required class="w-full border rounded-lg p-2.5" placeholder="email@gmail.com">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Địa chỉ:</label>
                        <input type="text" name="lead_address" class="w-full border rounded-lg p-2.5" placeholder="Long Xuyên, An Giang">
                    </div>
                </div>
            </div>

            <!-- Số lượng khách & Áp voucher (bảng promotions) -->
            <div class="space-y-3">
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide border-b pb-1">2. Số lượng hành khách & Mã giảm giá</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <label class="block font-semibold mb-1">Số người lớn (*):</label>
                        <input type="number" name="num_adults" value="2" min="1" max="10" required class="w-full border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Số trẻ em (5-11 tuổi):</label>
                        <input type="number" name="num_children" value="0" min="0" max="5" class="w-full border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Mã Voucher (nếu có):</label>
                        <input type="text" name="promo_code" placeholder="DUXUAN2026" class="w-full border rounded-lg p-2.5 uppercase font-bold text-blue-600">
                    </div>
                </div>
            </div>

            <!-- Danh sách thành viên trong đoàn để làm BẢO HIỂM (bảng booking_passengers) -->
            <div class="space-y-3">
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide border-b pb-1 flex items-center justify-between">
                    <span>3. Danh sách hành khách đi đoàn (Khai báo mua bảo hiểm lữ hành)</span>
                    <span class="text-[11px] text-rose-500 font-normal">Bắt buộc theo quy định</span>
                </h2>
                
                <div class="space-y-3 text-xs" id="passenger_list">
                    <!-- Khách 1 -->
                    <div class="p-3 bg-slate-50 border rounded-xl grid grid-cols-1 sm:grid-cols-4 gap-2">
                        <input type="text" name="passenger_name[]" required placeholder="Họ tên khách 1" class="border rounded p-2">
                        <select name="passenger_gender[]" class="border rounded p-2">
                            <option value="Nam">Nam</option>
                            <option value="Nữ">Nữ</option>
                        </select>
                        <input type="text" name="passenger_idcard[]" required placeholder="Số CCCD khách 1" class="border rounded p-2">
                        <select name="passenger_type[]" class="border rounded p-2">
                            <option value="Người lớn">Người lớn</option>
                            <option value="Trẻ em">Trẻ em</option>
                        </select>
                    </div>
                    <!-- Khách 2 -->
                    <div class="p-3 bg-slate-50 border rounded-xl grid grid-cols-1 sm:grid-cols-4 gap-2">
                        <input type="text" name="passenger_name[]" required placeholder="Họ tên khách 2" class="border rounded p-2">
                        <select name="passenger_gender[]" class="border rounded p-2">
                            <option value="Nữ">Nữ</option>
                            <option value="Nam">Nam</option>
                        </select>
                        <input type="text" name="passenger_idcard[]" placeholder="Số CCCD khách 2" class="border rounded p-2">
                        <select name="passenger_type[]" class="border rounded p-2">
                            <option value="Người lớn">Người lớn</option>
                            <option value="Trẻ em">Trẻ em</option>
                        </select>
                    </div>
                </div>
            </div>

            <div>
                <label class="block font-semibold text-xs mb-1">Ghi chú đặc biệt cho đoàn:</label>
                <textarea name="special_notes" rows="2" class="w-full border rounded-lg p-2.5 text-xs" placeholder="Ăn chay, đón tại điểm hẹn riêng..."></textarea>
            </div>

            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 rounded-xl text-sm transition shadow-lg shadow-emerald-600/20">
                XÁC NHẬN ĐẶT TOUR & TẠO PHIẾU THU ĐẶT CỌC (50%)
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>