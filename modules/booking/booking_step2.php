<?php
// modules/booking/booking_step2.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/business_helpers.php';

$booking_code = trim($_GET['code'] ?? '');

if (empty($booking_code)) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// 1. Lấy thông tin đơn đặt tour theo booking_code (Không dùng từ khóa JOIN)
$sql = "SELECT b.*, c.fullname AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
               dep.departure_date, dep.return_date, dep.max_seats, dep.booked_seats, dep.status AS departure_status,
               t.title AS tour_title, t.tour_code, t.start_location
        FROM bookings b, customers c, tour_departures dep, tours t
        WHERE b.customer_id = c.customer_id 
          AND b.departure_id = dep.departure_id 
          AND dep.tour_id = t.tour_id 
          AND b.booking_code = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$booking_code]);
$booking = $stmt->fetch();

if (!$booking) {
    die("Không tìm thấy thông tin đơn đặt tour hợp lệ!");
}

// 2. TỰ ĐỘNG ĐỒNG BỘ LẠI SỐ CHỖ VÀ TRẠNG THÁI CỦA ĐỢT KHỞI HÀNH
sync_departure_seats($pdo, (int)$booking['departure_id']);

// 3. Lấy thông tin phụ thu phòng đơn và số lượng khách yêu cầu phòng riêng thực tế
$stmtPrice = $pdo->prepare("SELECT single_supplement FROM tour_prices WHERE departure_id = ?");
$stmtPrice->execute([$booking['departure_id']]);
$single_supplement = (float)($stmtPrice->fetchColumn() ?: 0);

$stmtSingle = $pdo->prepare("SELECT COUNT(*) FROM booking_passengers WHERE booking_id = ? AND single_room = 1");
$stmtSingle->execute([$booking['booking_id']]);
$single_room_count = (int)$stmtSingle->fetchColumn();
$total_single_supplement = $single_room_count * $single_supplement;

// 4. Tính số tiền cọc cần đóng (50% tổng đơn) và số tiền còn lại
$deposit_amount = round($booking['total_amount'] * 0.5);
$remaining_amount = $booking['total_amount'] - $deposit_amount;

// 5. Khởi tạo bản ghi thanh toán ở bảng payments nếu chưa có
$checkPay = $pdo->prepare("SELECT payment_id FROM payments WHERE booking_id = ? AND payment_phase = 'Đặt cọc'");
$checkPay->execute([$booking['booking_id']]);
if (!$checkPay->fetch()) {
    $pay_code = 'PAY' . date('ymd') . rand(100, 999);
    $inPay = $pdo->prepare("INSERT INTO payments (booking_id, payment_code, payment_method, payment_phase, amount, status) 
                            VALUES (?, ?, 'Chuyển khoản QR', 'Đặt cọc', ?, 'Đang xử lý')");
    $inPay->execute([$booking['booking_id'], $pay_code, $deposit_amount]);
}

// 6. Cấu hình thông tin tài khoản ngân hàng thụ hưởng
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

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xl border border-slate-200 space-y-6">
        
        <!-- Tiêu đề tiến trình -->
        <div class="text-center border-b border-slate-100 pb-5">
            <span class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 font-extrabold text-xs px-3.5 py-1 rounded-full mb-2 border border-blue-200">
                <i data-lucide="shield-check" class="w-3.5 h-3.5 text-blue-600"></i> Bước 2: Thanh Toán Đặt Cọc Chuyến Đi
            </span>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Quét Mã VietQR Chuyển Khoản</h1>
            <p class="text-xs text-slate-500 mt-1">
                Mã đơn booking: <strong class="font-mono text-blue-600 text-sm"><?= htmlspecialchars($booking['booking_code']) ?></strong>
                <span class="mx-1 text-slate-300">•</span>
                Ngày đặt: <strong><?= date('d/m/Y H:i', strtotime($booking['booking_date'])) ?></strong>
            </p>
        </div>

        <!-- Tóm tắt tour & cơ cấu hành khách -->
        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 text-xs text-slate-700 space-y-2">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 border-b border-slate-200/80 pb-2">
                <span class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($booking['tour_title']) ?></span>
                <span class="font-mono font-bold text-slate-500">Mã tour: <?= $booking['tour_code'] ?></span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-1 text-[11px]">
                <div>
                    <span class="text-slate-400">Thời gian:</span>
                    <strong class="text-slate-800 ml-1"><?= date('d/m/Y', strtotime($booking['departure_date'])) ?> &rarr; <?= date('d/m/Y', strtotime($booking['return_date'])) ?></strong>
                </div>
                <div>
                    <span class="text-slate-400">Điểm khởi hành:</span>
                    <strong class="text-slate-800 ml-1"><?= htmlspecialchars($booking['start_location'] ?? 'TP. Long Xuyên') ?></strong>
                </div>
                <div>
                    <span class="text-slate-400">Số lượng khách:</span>
                    <strong class="text-blue-700 ml-1">
                        <?= $booking['num_adults'] ?> Người lớn
                        <?= $booking['num_children'] > 0 ? ', ' . $booking['num_children'] . ' Trẻ em' : '' ?>
                        <?= $booking['num_infants'] > 0 ? ', ' . $booking['num_infants'] . ' Em bé' : '' ?>
                    </strong>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
            <!-- Cột trái: Mã QR động VietQR -->
            <div class="flex flex-col items-center justify-center p-6 bg-slate-50 rounded-2xl border border-slate-200">
                <div class="relative group">
                    <img src="<?= $qr_url ?>" alt="Mã VietQR Đặt Cọc" class="w-64 sm:w-72 h-auto rounded-xl shadow-lg border-2 border-white transition group-hover:scale-[1.01]">
                </div>
                <div class="text-center mt-3 space-y-1">
                    <p class="text-xs font-bold text-slate-800">Quét mã bằng ứng dụng Ngân hàng hoặc Ví MoMo/VNPAY</p>
                    <p class="text-[11px] text-slate-400">Hệ thống tự điền sẵn chính xác số tiền và cú pháp chuyển tiền</p>
                </div>
            </div>

            <!-- Cột phải: Chi tiết tài khoản & Bảng phân tích chi phí -->
            <div class="space-y-4 text-xs">
                <!-- Thông tin chuyển khoản ngân hàng -->
                <div class="p-4 bg-blue-50/80 border border-blue-200/80 rounded-2xl space-y-2.5">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500">Ngân hàng thụ hưởng:</span>
                        <strong class="text-slate-800 font-semibold">MB Bank (Quân Đội)</strong>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500">Chủ tài khoản:</span>
                        <strong class="text-slate-800 uppercase font-bold"><?= $account_name ?></strong>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500">Số tài khoản:</span>
                        <div class="flex items-center gap-1.5">
                            <strong id="accNo" class="font-mono text-sm text-blue-700 font-bold"><?= $account_no ?></strong>
                            <button type="button" onclick="copyToClipboard('accNo', 'Đã chép số tài khoản')" 
                                    class="text-blue-600 hover:text-blue-800 bg-white border border-blue-200 px-1.5 py-0.5 rounded text-[10px] font-semibold transition" title="Sao chép">
                                Chép
                            </button>
                        </div>
                    </div>
                    <div class="flex justify-between items-center border-t border-blue-200/60 pt-2">
                        <span class="text-slate-500">Nội dung chuyển khoản:</span>
                        <div class="flex items-center gap-1.5">
                            <strong id="transCode" class="font-mono text-xs text-emerald-800 bg-emerald-100/80 px-2 py-0.5 rounded border border-emerald-300 font-bold"><?= $transfer_content ?></strong>
                            <button type="button" onclick="copyToClipboard('transCode', 'Đã chép nội dung chuyển khoản')" 
                                    class="text-emerald-700 hover:text-emerald-900 bg-white border border-emerald-200 px-1.5 py-0.5 rounded text-[10px] font-semibold transition" title="Sao chép">
                                Chép
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Bảng phân rã chi phí & Phụ thu phòng đơn -->
                <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2 text-[11px]">
                    <div class="flex justify-between text-slate-600">
                        <span>Giá vé tour gốc (Subtotal):</span>
                        <span class="font-semibold"><?= number_format($booking['subtotal'] - $total_single_supplement, 0, ',', '.') ?> đ</span>
                    </div>

                    <?php if ($single_room_count > 0): ?>
                        <div class="flex justify-between text-amber-700 bg-amber-50/80 px-2 py-1 rounded border border-amber-200">
                            <span>Phụ thu phòng đơn (<?= $single_room_count ?> phòng x <?= number_format($single_supplement, 0, ',', '.') ?>đ):</span>
                            <span class="font-bold">+<?= number_format($total_single_supplement, 0, ',', '.') ?> đ</span>
                        </div>
                    <?php endif; ?>

                    <?php if ((float)$booking['discount_amount'] > 0): ?>
                        <div class="flex justify-between text-emerald-600">
                            <span>Mã giảm giá / Voucher:</span>
                            <span class="font-bold">-<?= number_format($booking['discount_amount'], 0, ',', '.') ?> đ</span>
                        </div>
                    <?php endif; ?>

                    <div class="flex justify-between border-t border-slate-200 pt-2 text-xs">
                        <span class="font-bold text-slate-800">Tổng giá trị đơn tour:</span>
                        <strong class="font-bold text-slate-900"><?= number_format($booking['total_amount'], 0, ',', '.') ?> đ</strong>
                    </div>
                    <div class="flex justify-between border-t border-slate-200/80 pt-2 text-xs text-rose-600">
                        <span class="font-bold">Số tiền cọc cần thanh toán (50%):</span>
                        <strong class="text-base font-black"><?= number_format($deposit_amount, 0, ',', '.') ?> đ</strong>
                    </div>
                    <div class="flex justify-between text-[11px] text-slate-400">
                        <span>Số tiền còn lại (thanh toán khi đi tour):</span>
                        <span><?= number_format($remaining_amount, 0, ',', '.') ?> đ</span>
                    </div>
                </div>

                <!-- Cảnh báo lưu ý -->
                <div class="p-3 bg-amber-50 text-amber-800 rounded-xl border border-amber-200 text-[11px] leading-relaxed flex items-start gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                    <div>
                        <strong>Lưu ý quan trọng:</strong> Giữ nguyên nội dung chuyển khoản <strong><?= $transfer_content ?></strong> để kế toán đối soát tự động kích hoạt vé và bảo hiểm du lịch.
                    </div>
                </div>

                <!-- Nút xác nhận hoàn tất -->
                <a href="<?= BASE_URL ?>modules/booking/booking_confirm.php?code=<?= $booking['booking_code'] ?>" 
                   class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold py-3.5 rounded-2xl text-center block transition shadow-lg shadow-emerald-600/20 text-sm tracking-wide">
                    TÔI ĐÃ CHUYỂN KHOẢN XONG &rarr;
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Toast thông báo chép clipboard -->
<div id="copyToast" class="fixed bottom-5 right-5 bg-slate-900 text-white text-xs px-4 py-2.5 rounded-xl shadow-2xl transition opacity-0 pointer-events-none z-50 flex items-center gap-2">
    <i data-lucide="check" class="w-4 h-4 text-emerald-400"></i>
    <span id="toastMsg">Đã sao chép thành công!</span>
</div>

<script>
    function copyToClipboard(elementId, message) {
        const text = document.getElementById(elementId).innerText.trim();
        navigator.clipboard.writeText(text).then(() => {
            const toast = document.getElementById('copyToast');
            document.getElementById('toastMsg').innerText = message;
            toast.classList.remove('opacity-0', 'pointer-events-none');
            setTimeout(() => {
                toast.classList.add('opacity-0', 'pointer-events-none');
            }, 2500);
        });
    }
    lucide.createIcons();
</script>

<?php include '../../includes/footer.php'; ?>