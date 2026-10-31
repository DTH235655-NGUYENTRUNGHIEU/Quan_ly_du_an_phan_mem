<?php
// modules/booking/booking_step2.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$booking_code = trim($_GET['code'] ?? '');

if (empty($booking_code)) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// 1. Lấy thông tin đơn đặt tour theo booking_code
$sql = "SELECT b.*, c.fullname AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
               dep.departure_date, dep.return_date, t.title AS tour_title, t.tour_code
        FROM bookings b, customers c, tour_departures dep, tours t
        WHERE b.customer_id = c.customer_id 
          AND b.departure_id = dep.departure_id 
          AND dep.tour_id = t.tour_id 
          AND b.booking_code = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$booking_code]);
$booking = $stmt->fetch();

if (!$booking) {
    die("Không tìm thấy thông tin đơn đặt tour!");
}

// 2. Tính số tiền cọc cần đóng (50% tổng đơn)
$deposit_amount = round($booking['total_amount'] * 0.5);

// 3. Khởi tạo bản ghi thanh toán ở bảng payments nếu chưa có
$checkPay = $pdo->prepare("SELECT payment_id FROM payments WHERE booking_id = ? AND payment_phase = 'Đặt cọc'");
$checkPay->execute([$booking['booking_id']]);
if (!$checkPay->fetch()) {
    $pay_code = 'PAY' . date('ymd') . rand(100, 999);
    $inPay = $pdo->prepare("INSERT INTO payments (booking_id, payment_code, payment_method, payment_phase, amount, status) 
                            VALUES (?, ?, 'Chuyển khoản QR', 'Đặt cọc', ?, 'Đang xử lý')");
    $inPay->execute([$booking['booking_id'], $pay_code, $deposit_amount]);
}

// 4. Cấu hình thông tin tài khoản ngân hàng thụ hưởng
$bank_id = "MB"; // Ngân hàng Quân Đội (MBBank)
$account_no = "0912345678"; // Số tài khoản công ty
$account_name = "CONG TY DU LICH VIET NAM";
$transfer_content = $booking['booking_code'];

// Tạo URL ảnh VietQR chuẩn quốc gia
$qr_url = "https://img.vietqr.io/image/{$bank_id}-{$account_no}-compact2.png?amount={$deposit_amount}&addInfo=" . urlencode($transfer_content) . "&accountName=" . urlencode($account_name);

$page_title = "Quét Mã VietQR Đặt Cọc Tour " . $booking['booking_code'];
include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-xl border border-slate-200 space-y-6">
        <div class="text-center border-b pb-4">
            <span class="inline-block bg-blue-100 text-blue-700 font-bold text-xs px-3 py-1 rounded-full mb-2">Bước 2: Thanh Toán Đặt Cọc</span>
            <h1 class="text-2xl font-black text-slate-900">Quét Mã VietQR Chuyển Khoản</h1>
            <p class="text-xs text-slate-500 mt-1">Đơn đặt tour: <strong class="font-mono text-blue-600 text-sm"><?= $booking['booking_code'] ?></strong></p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
            <!-- Cột trái: Mã VietQR tự động -->
            <div class="flex flex-col items-center justify-center p-4 bg-slate-50 rounded-2xl border border-slate-200">
                <img src="<?= $qr_url ?>" alt="Mã VietQR" class="w-64 h-auto rounded-xl shadow-md border border-white">
                <p class="text-[11px] text-slate-500 text-center mt-3">Mở ứng dụng Ngân hàng bất kỳ hoặc Ví điện tử để quét mã</p>
            </div>

            <!-- Cột phải: Chi tiết thông tin chuyển khoản -->
            <div class="space-y-3 text-xs">
                <div class="p-4 bg-blue-50/70 border border-blue-100 rounded-xl space-y-2">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Ngân hàng thụ hưởng:</span>
                        <strong class="text-slate-800">MB Bank (Quân Đội)</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Chủ tài khoản:</span>
                        <strong class="text-slate-800"><?= $account_name ?></strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Số tài khoản:</span>
                        <strong class="font-mono text-sm text-blue-700"><?= $account_no ?></strong>
                    </div>
                    <div class="flex justify-between border-t border-blue-200/60 pt-2">
                        <span class="text-slate-500">Số tiền đặt cọc (50%):</span>
                        <strong class="text-rose-600 font-extrabold text-base"><?= number_format($deposit_amount, 0, ',', '.') ?> đ</strong>
                    </div>
                    <div class="flex justify-between border-t border-blue-200/60 pt-2">
                        <span class="text-slate-500">Nội dung chuyển:</span>
                        <strong class="font-mono text-sm text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200"><?= $transfer_content ?></strong>
                    </div>
                </div>

                <div class="p-3 bg-amber-50 text-amber-800 rounded-xl border border-amber-200 text-[11px] leading-relaxed">
                    <p><strong>Lưu ý quan trọng:</strong> Quý khách vui lòng giữ nguyên nội dung chuyển khoản <strong><?= $transfer_content ?></strong> để hệ thống và kế toán đối soát tự động kích hoạt vé tour.</p>
                </div>

                <a href="<?= BASE_URL ?>modules/booking/booking_confirm.php?code=<?= $booking['booking_code'] ?>" 
                   class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl text-center block transition shadow-lg shadow-emerald-600/20">
                    TÔI ĐÃ CHUYỂN KHOẢN XONG
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>