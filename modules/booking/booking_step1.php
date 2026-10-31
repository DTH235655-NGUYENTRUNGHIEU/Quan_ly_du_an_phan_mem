<?php
// modules/booking/booking_step1.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$dep_id = intval($_GET['dep_id'] ?? 1);

// 1. Truy vấn thông tin đợt khởi hành & bảng giá theo chuẩn subquery/where
$sqlDep = "SELECT dep.*, t.title, t.tour_code, t.thumbnail, t.duration_days, t.duration_nights,
                  p.price_adult, p.price_child, p.price_infant, p.single_supplement,
                  (dep.max_seats - dep.booked_seats) AS available_seats
           FROM tour_departures dep, tours t, tour_prices p
           WHERE dep.tour_id = t.tour_id 
             AND dep.departure_id = p.departure_id 
             AND dep.departure_id = ?";
$stmt = $pdo->prepare($sqlDep);
$stmt->execute([$dep_id]);
$dep = $stmt->fetch();

if (!$dep || $dep['status'] === 'Đã khóa sổ' || $dep['available_seats'] <= 0) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'>
            <h2 style='color:#e11d48;'>Đợt khởi hành này hiện đã khóa sổ hoặc hết chỗ!</h2>
            <a href='" . BASE_URL . "index.php'>Chọn tour khác</a>
         </div>");
}

// 2. Lấy thông tin khách hàng nếu đã đăng nhập để tự điền form
$loggedInUser = current_user();
$customerProfile = null;
if ($loggedInUser) {
    $stmtC = $pdo->prepare("SELECT * FROM customers WHERE user_id = ? LIMIT 1");
    $stmtC->execute([$loggedInUser['user_id']]);
    $customerProfile = $stmtC->fetch();
}

$error_msg = "";

// 3. Xử lý khi nhấn nút Đặt Tour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $num_adults = max(1, intval($_POST['num_adults'] ?? 1));
        $num_children = max(0, intval($_POST['num_children'] ?? 0));
        $num_infants = max(0, intval($_POST['num_infants'] ?? 0));
        $total_passengers = $num_adults + $num_children;

        // 3.1. Chặn đặt vượt số chỗ trống còn lại (Overbooking)
        if ($total_passengers > $dep['available_seats']) {
            throw new Exception("Số chỗ bạn chọn ({$total_passengers} khách) vượt quá số chỗ trống còn lại ({$dep['available_seats']} chỗ)!");
        }

        $fullname = trim($_POST['lead_fullname'] ?? '');
        $phone = trim($_POST['lead_phone'] ?? '');
        $email = trim($_POST['lead_email'] ?? '');
        $address = trim($_POST['lead_address'] ?? '');
        $promo_code = trim($_POST['promo_code'] ?? '');
        $special_notes = trim($_POST['special_notes'] ?? '');

        if (empty($fullname) || empty($phone) || empty($email)) {
            throw new Exception("Vui lòng điền đầy đủ họ tên, số điện thoại và email liên hệ.");
        }

        $pdo->beginTransaction();

        // 3.2. Cập nhật hoặc lưu khách hàng (bảng customers)
        $userIdParam = $loggedInUser['user_id'] ?? null;
        $checkCust = $pdo->prepare("SELECT customer_id FROM customers WHERE email = ? LIMIT 1");
        $checkCust->execute([$email]);
        $existingCust = $checkCust->fetch();

        if ($existingCust) {
            $customer_id = $existingCust['customer_id'];
            $upCust = $pdo->prepare("UPDATE customers SET fullname = ?, phone = ?, address = ?, user_id = COALESCE(user_id, ?) WHERE customer_id = ?");
            $upCust->execute([$fullname, $phone, $address, $userIdParam, $customer_id]);
        } else {
            $inCust = $pdo->prepare("INSERT INTO customers (user_id, fullname, email, phone, address) VALUES (?, ?, ?, ?, ?)");
            $inCust->execute([$userIdParam, $fullname, $email, $phone, $address]);
            $customer_id = $pdo->lastInsertId();
        }

        // 3.3. Tính toán chi phí vé và phòng đơn
        $subtotal = ($num_adults * $dep['price_adult']) + ($num_children * $dep['price_child']) + ($num_infants * $dep['price_infant']);
        
        $single_rooms_checked = 0;
        if (!empty($_POST['passenger_single_room']) && is_array($_POST['passenger_single_room'])) {
            $single_rooms_checked = count($_POST['passenger_single_room']);
        }
        $single_supplement_total = $single_rooms_checked * $dep['single_supplement'];
        $gross_total = $subtotal + $single_supplement_total;

        // 3.4. Kiểm tra khuyến mãi (bảng promotions)
        $discount = 0;
        $promo_id = null;
        if (!empty($promo_code)) {
            $stmtP = $pdo->prepare("SELECT * FROM promotions 
                                    WHERE promo_code = ? 
                                      AND status = 'Active' 
                                      AND start_date <= CURDATE() 
                                      AND end_date >= CURDATE()");
            $stmtP->execute([$promo_code]);
            $promo = $stmtP->fetch();

            if ($promo) {
                if ($promo['usage_limit'] && $promo['used_count'] >= $promo['usage_limit']) {
                    throw new Exception("Mã khuyến mãi này đã hết lượt sử dụng.");
                }
                if ($gross_total < $promo['min_order_amount']) {
                    throw new Exception("Mã này chỉ áp dụng cho đơn từ " . number_format($promo['min_order_amount'], 0, ',', '.') . " đ trở lên.");
                }

                $promo_id = $promo['promotion_id'];
                if ($promo['discount_type'] === 'Percentage') {
                    $discount = ($gross_total * $promo['discount_value']) / 100;
                    if ($promo['max_discount_amount'] && $discount > $promo['max_discount_amount']) {
                        $discount = $promo['max_discount_amount'];
                    }
                } else {
                    $discount = min($gross_total, $promo['discount_value']);
                }

                // Tăng số lượt đã sử dụng voucher
                $pdo->prepare("UPDATE promotions SET used_count = used_count + 1 WHERE promotion_id = ?")->execute([$promo_id]);
            } else {
                throw new Exception("Mã khuyến mãi không tồn tại hoặc đã hết hạn áp dụng.");
            }
        }

        $total_amount = max(0, $gross_total - $discount);
        $deposit_amount = round($total_amount * 0.5); // Tiêu chuẩn cọc 50%
        $booking_code = 'BK' . date('ymd') . rand(100, 999);

        // 3.5. Lưu bản ghi đơn đặt tour (bảng bookings)
        $stmtBook = $pdo->prepare("INSERT INTO bookings (booking_code, departure_id, customer_id, promotion_id, num_adults, num_children, num_infants, subtotal, discount_amount, total_amount, paid_amount, payment_status, booking_status, special_notes)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Chưa thanh toán', 'Chờ duyệt', ?)");
        $stmtBook->execute([$booking_code, $dep_id, $customer_id, $promo_id, $num_adults, $num_children, $num_infants, $gross_total, $discount, $total_amount, $special_notes]);
        $booking_id = $pdo->lastInsertId();

        // 3.6. Lưu danh sách từng hành khách bảo hiểm (bảng booking_passengers)
        $pass_names = $_POST['passenger_name'] ?? [];
        $pass_genders = $_POST['passenger_gender'] ?? [];
        $pass_births = $_POST['passenger_birth'] ?? [];
        $pass_cards = $_POST['passenger_idcard'] ?? [];
        $pass_types = $_POST['passenger_type'] ?? [];
        $pass_singles = $_POST['passenger_single_room'] ?? [];

        $stmtPass = $pdo->prepare("INSERT INTO booking_passengers (booking_id, fullname, gender, birth_date, passenger_type, id_card, single_room) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");

        for ($i = 0; $i < count($pass_names); $i++) {
            if (!empty($pass_names[$i])) {
                $isSingle = isset($pass_singles[$i]) ? 1 : 0;
                $bDate = !empty($pass_births[$i]) ? $pass_births[$i] : null;
                $stmtPass->execute([
                    $booking_id,
                    trim($pass_names[$i]),
                    $pass_genders[$i] ?? 'Nam',
                    $bDate,
                    $pass_types[$i] ?? 'Người lớn',
                    trim($pass_cards[$i] ?? ''),
                    $isSingle
                ]);
            }
        }

        // 3.7. Cập nhật số ghế đã đặt trên đợt khởi hành
        $stmtUpDep = $pdo->prepare("UPDATE tour_departures 
                                    SET booked_seats = booked_seats + ?,
                                        status = IF(booked_seats + ? >= max_seats, 'Đã khóa sổ', status) 
                                    WHERE departure_id = ?");
        $stmtUpDep->execute([$total_passengers, $total_passengers, $dep_id]);

        $pdo->commit();

        // Chuyển sang bước 2: Quét mã QR thanh toán đặt cọc
        header("Location: " . BASE_URL . "modules/booking/booking_step2.php?code=" . $booking_code);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_msg = $e->getMessage();
    }
}

$page_title = "Đăng Ký Đặt Tour & Nhập Thông Tin Bảo Hiểm";
include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<!-- Ẩn các giá trị đơn giá để Javascript tính toán thời gian thực -->
<input type="hidden" id="price_adult_val" value="<?= $dep['price_adult'] ?>">
<input type="hidden" id="price_child_val" value="<?= $dep['price_child'] ?>">
<input type="hidden" id="price_infant_val" value="<?= $dep['price_infant'] ?>">
<input type="hidden" id="price_single_val" value="<?= $dep['single_supplement'] ?>">

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-xl border border-slate-200">
        <div class="border-b pb-4 flex items-center justify-between">
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
                <i data-lucide="shield-plus" class="w-6 h-6 text-blue-600"></i> ĐĂNG KÝ TOUR & KHAI BÁO BẢO HIỂM
            </h1>
            <span class="text-xs bg-emerald-100 text-emerald-800 font-bold px-3 py-1 rounded-full">
                Còn <?= $dep['available_seats'] ?> chỗ
            </span>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="my-4 p-4 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6 mt-6">
            <!-- 1. Tóm tắt thông tin tour -->
            <div class="p-4 bg-blue-50/70 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs border border-blue-100">
                <div>
                    <span class="text-slate-400 block font-medium">Tour đã chọn:</span>
                    <strong class="text-sm text-slate-800 font-bold"><?= htmlspecialchars($dep['title']) ?></strong>
                    <span class="text-slate-500 block mt-0.5">Mã tour: <strong><?= $dep['tour_code'] ?></strong></span>
                </div>
                <div class="sm:text-right">
                    <span class="text-slate-400 block font-medium">Khởi hành:</span>
                    <strong class="text-sm text-blue-600"><?= date('d/m/Y', strtotime($dep['departure_date'])) ?></strong>
                    <span class="text-slate-500 block mt-0.5">Thời gian: <?= $dep['duration_days'] ?>N<?= $dep['duration_nights'] ?>Đ</span>
                </div>
            </div>

            <!-- 2. Thông tin người đại diện đặt tour (bảng customers) -->
            <div class="space-y-3">
                <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wide border-b pb-1.5 flex items-center justify-between">
                    <span>1. Thông tin người đại diện liên hệ</span>
                    <?php if ($loggedInUser): ?>
                        <span class="text-emerald-600 font-normal normal-case">✓ Đã tự động điền từ tài khoản</span>
                    <?php endif; ?>
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Họ và tên (*):</label>
                        <input type="text" name="lead_fullname" required 
                               value="<?= htmlspecialchars($_POST['lead_fullname'] ?? ($customerProfile['fullname'] ?? ($loggedInUser['fullname'] ?? ''))) ?>" 
                               class="w-full border rounded-lg p-2.5" placeholder="Nguyễn Văn A">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Số điện thoại (*):</label>
                        <input type="tel" name="lead_phone" required 
                               value="<?= htmlspecialchars($_POST['lead_phone'] ?? ($customerProfile['phone'] ?? ($loggedInUser['phone'] ?? ''))) ?>" 
                               class="w-full border rounded-lg p-2.5" placeholder="0912345678">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Email nhận vé điện tử (*):</label>
                        <input type="email" name="lead_email" required 
                               value="<?= htmlspecialchars($_POST['lead_email'] ?? ($customerProfile['email'] ?? ($loggedInUser['email'] ?? ''))) ?>" 
                               class="w-full border rounded-lg p-2.5" placeholder="email@gmail.com">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Địa chỉ thường trú:</label>
                        <input type="text" name="lead_address" 
                               value="<?= htmlspecialchars($_POST['lead_address'] ?? ($customerProfile['address'] ?? '')) ?>" 
                               class="w-full border rounded-lg p-2.5" placeholder="TP. Long Xuyên, An Giang">
                    </div>
                </div>
            </div>

            <!-- 3. Số lượng vé đặt & Voucher (bảng tour_prices & promotions) -->
            <div class="space-y-3">
                <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wide border-b pb-1.5">2. Số lượng hành khách & Mã giảm giá</h2>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-xs">
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Người lớn (&ge; 12t) (*):</label>
                        <input type="number" id="num_adults" name="num_adults" value="<?= htmlspecialchars($_POST['num_adults'] ?? '1') ?>" min="1" max="<?= $dep['available_seats'] ?>" required class="w-full border rounded-lg p-2.5 font-bold text-blue-600">
                        <span class="text-[11px] text-slate-400 mt-0.5 block"><?= number_format($dep['price_adult'], 0, ',', '.') ?> đ/khách</span>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Trẻ em (5-11 tuổi):</label>
                        <input type="number" id="num_children" name="num_children" value="<?= htmlspecialchars($_POST['num_children'] ?? '0') ?>" min="0" max="10" class="w-full border rounded-lg p-2.5 font-bold">
                        <span class="text-[11px] text-slate-400 mt-0.5 block"><?= number_format($dep['price_child'], 0, ',', '.') ?> đ/khách</span>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Em bé (&lt; 5 tuổi):</label>
                        <input type="number" id="num_infants" name="num_infants" value="<?= htmlspecialchars($_POST['num_infants'] ?? '0') ?>" min="0" max="5" class="w-full border rounded-lg p-2.5 font-bold">
                        <span class="text-[11px] text-slate-400 mt-0.5 block"><?= number_format($dep['price_infant'], 0, ',', '.') ?> đ/khách</span>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Mã khuyến mãi:</label>
                        <input type="text" name="promo_code" value="<?= htmlspecialchars($_POST['promo_code'] ?? '') ?>" placeholder="DUXUAN2026" class="w-full border rounded-lg p-2.5 uppercase font-bold text-blue-600">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Nhập mã nếu có</span>
                    </div>
                </div>
            </div>

            <!-- 4. Danh sách hành khách đi đoàn (bảng booking_passengers) -->
            <div class="space-y-3">
                <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wide border-b pb-1.5 flex items-center justify-between">
                    <span>3. Danh sách hành khách đi đoàn (Khai báo mua bảo hiểm du lịch)</span>
                    <span class="text-[11px] text-rose-500 font-normal">Quy định bắt buộc của Tổng cục Du lịch</span>
                </h2>
                <!-- Khu vực tự động hiển thị số dòng tương ứng từ booking.js -->
                <div class="space-y-3 text-xs" id="passenger_list"></div>
            </div>

            <!-- Ghi chú đoàn -->
            <div>
                <label class="block font-semibold text-xs mb-1 text-slate-700">Yêu cầu đặc biệt cho đoàn:</label>
                <textarea name="special_notes" rows="2" class="w-full border rounded-lg p-2.5 text-xs" placeholder="Ăn chay, người lớn tuổi xếp ghế trước xe, điểm đón riêng..."><?= htmlspecialchars($_POST['special_notes'] ?? '') ?></textarea>
            </div>

            <!-- Tổng chi phí ước tính -->
            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-2">
                <div class="flex justify-between text-slate-600">
                    <span>Tiền vé tour:</span>
                    <strong id="display_subtotal" class="font-bold text-slate-800">0 đ</strong>
                </div>
                <div class="flex justify-between text-slate-600">
                    <span>Phụ thu phòng đơn riêng:</span>
                    <strong id="display_single_total" class="font-bold text-slate-800">0 đ</strong>
                </div>
                <div class="flex justify-between text-sm font-extrabold text-slate-900 border-t border-slate-200 pt-2">
                    <span>TỔNG TIỀN ĐƠN TOUR:</span>
                    <span id="display_total" class="text-rose-600 text-base">0 đ</span>
                </div>
                <div class="flex justify-between text-xs font-bold text-blue-600 border-t border-slate-200/60 pt-1.5">
                    <span>TIỀN ĐẶT CỌC GIỮ CHỖ (50%):</span>
                    <span id="display_deposit" class="text-sm">0 đ</span>
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl text-xs sm:text-sm transition shadow-lg shadow-blue-500/20 flex items-center justify-center gap-2">
                <i data-lucide="qr-code" class="w-4 h-4"></i> TIẾP TỤC: XÁC NHẬN & QUÉT MÃ VIETQR ĐẶT CỌC
            </button>
        </form>
    </div>
</div>

<!-- Nạp tệp Javascript xử lý tính tiền và thêm khách tự động -->
<script src="<?= BASE_URL ?>assets/js/booking.js"></script>
<?php include '../../includes/footer.php'; ?>