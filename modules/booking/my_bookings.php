<?php
// modules/booking/my_bookings.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$user = current_user();
$search_code = trim($_GET['booking_code'] ?? '');
$search_phone = trim($_GET['phone'] ?? '');

$bookings = [];

// 1. Trường hợp 1: Khách hàng đã đăng nhập tài khoản
if ($user && !empty($user['customer_id'])) {
    $sql = "SELECT b.*, t.title AS tour_title, t.tour_code, t.thumbnail,
                   dep.departure_date, dep.return_date,
                   inv.invoice_id, inv.invoice_number,
                   rev.review_id, rev.rating, rev.status AS review_status
            FROM bookings b
            JOIN tour_departures dep ON b.departure_id = dep.departure_id
            JOIN tours t ON dep.tour_id = t.tour_id
            LEFT JOIN invoices inv ON b.booking_id = inv.booking_id AND inv.status = 'Đã phát hành'
            LEFT JOIN reviews rev ON b.booking_id = rev.booking_id
            WHERE b.customer_id = ?
            ORDER BY b.booking_id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['customer_id']]);
    $bookings = $stmt->fetchAll();
} 
// 2. Trường hợp 2: Khách vãng lai tra cứu bằng Mã Booking + Số điện thoại
elseif (!empty($search_code) && !empty($search_phone)) {
    $sql = "SELECT b.*, t.title AS tour_title, t.tour_code, t.thumbnail,
                   dep.departure_date, dep.return_date,
                   inv.invoice_id, inv.invoice_number,
                   rev.review_id, rev.rating, rev.status AS review_status
            FROM bookings b
            JOIN customers c ON b.customer_id = c.customer_id
            JOIN tour_departures dep ON b.departure_id = dep.departure_id
            JOIN tours t ON dep.tour_id = t.tour_id
            LEFT JOIN invoices inv ON b.booking_id = inv.booking_id AND inv.status = 'Đã phát hành'
            LEFT JOIN reviews rev ON b.booking_id = rev.booking_id
            WHERE b.booking_code = ? AND c.phone = ?
            ORDER BY b.booking_id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$search_code, $search_phone]);
    $bookings = $stmt->fetchAll();
}

$page_title = "Quản Lý Chuyến Đi Của Tôi - Du Lịch Việt Nam";
include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="max-w-5xl mx-auto px-4 py-8 flex-1" x-data="{ showReviewModal: false, selectedBookingId: 0, selectedTourId: 0, selectedTourTitle: '' }">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-5 mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Chuyến Đi & Đơn Đặt Của Tôi</h1>
            <p class="text-xs text-slate-500 mt-1">Theo dõi trạng thái xác nhận, lịch thanh toán cọc và đánh giá chất lượng chuyến đi</p>
        </div>
        <?php if ($user): ?>
            <span class="text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200 px-3.5 py-1.5 rounded-full flex items-center gap-1.5 self-start">
                <i data-lucide="award" class="w-4 h-4 text-amber-500"></i> Điểm tích lũy: <?= $user['reward_points'] ?> điểm
            </span>
        <?php endif; ?>
    </div>

    <!-- Khung tra cứu nhanh cho khách chưa đăng nhập -->
    <?php if (!$user): ?>
        <form method="GET" class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 mb-8 space-y-3">
            <span class="text-xs font-bold text-slate-700 uppercase flex items-center gap-1.5">
                <i data-lucide="search" class="w-4 h-4 text-blue-600"></i> Tra Cứu Đơn Hàng Dành Cho Khách Đặt Nhanh
            </span>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                <div>
                    <label class="block font-semibold mb-1 text-slate-600">Mã đơn đặt tour (*):</label>
                    <input type="text" name="booking_code" required value="<?= htmlspecialchars($search_code) ?>" placeholder="BK261030123" class="w-full border rounded-lg p-2 font-mono uppercase">
                </div>
                <div>
                    <label class="block font-semibold mb-1 text-slate-600">Số điện thoại đặt tour (*):</label>
                    <input type="tel" name="phone" required value="<?= htmlspecialchars($search_phone) ?>" placeholder="0912345678" class="w-full border rounded-lg p-2">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold p-2.5 rounded-lg transition shadow flex items-center justify-center gap-1.5">
                        <i data-lucide="search" class="w-3.5 h-3.5"></i> Tìm chuyến đi
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <!-- Danh sách các tour đã đặt -->
    <div class="space-y-4">
        <?php if (empty($bookings)): ?>
            <div class="bg-white p-12 rounded-2xl border border-slate-200 text-center space-y-3">
                <i data-lucide="ticket-x" class="w-10 h-10 text-slate-300 mx-auto"></i>
                <h3 class="text-base font-bold text-slate-800">Không tìm thấy thông tin đơn đặt tour nào</h3>
                <p class="text-xs text-slate-400">Nếu bạn đã đăng ký tour nhưng chưa đăng nhập, vui lòng nhập Mã đơn và Số điện thoại vào ô tra cứu bên trên.</p>
                <div class="pt-2">
                    <a href="<?= BASE_URL ?>modules/tours/index.php" class="inline-block bg-blue-600 text-white font-bold px-4 py-2 rounded-xl text-xs hover:bg-blue-700 transition">
                        Khám phá tour ngay
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($bookings as $b): ?>
            <?php
            // Lấy danh sách hành khách đi đoàn
            $stmtPass = $pdo->prepare("SELECT fullname, passenger_type, id_card, single_room FROM booking_passengers WHERE booking_id = ?");
            $stmtPass->execute([$b['booking_id']]);
            $passengers = $stmtPass->fetchAll();
            ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden text-xs">
                <div class="p-4 bg-slate-50 border-b flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-bold text-sm text-blue-600 bg-blue-50 px-2.5 py-0.5 rounded border border-blue-200">#<?= $b['booking_code'] ?></span>
                        <span class="text-slate-400">Ngày đặt: <?= date('d/m/Y H:i', strtotime($b['booking_date'])) ?></span>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-md font-bold text-[11px] 
                            <?= $b['payment_status'] === 'Đã thanh toán đủ' ? 'bg-emerald-100 text-emerald-800' : ($b['payment_status'] === 'Đã đặt cọc' ? 'bg-blue-100 text-blue-800' : 'bg-rose-100 text-rose-800') ?>">
                            <?= $b['payment_status'] ?>
                        </span>
                        <span class="px-2.5 py-0.5 rounded-md font-bold text-[11px] 
                            <?= $b['booking_status'] === 'Hoàn tất' ? 'bg-purple-100 text-purple-800' : ($b['booking_status'] === 'Đã xác nhận' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800') ?>">
                            <?= $b['booking_status'] ?>
                        </span>
                    </div>
                </div>

                <div class="p-5 flex flex-col md:flex-row gap-5 items-start justify-between">
                    <div class="flex gap-4">
                        <img src="<?= htmlspecialchars($b['thumbnail']) ?>" alt="Ảnh tour" class="w-24 h-20 object-cover rounded-xl border border-slate-200 shrink-0">
                        <div class="space-y-1">
                            <span class="font-mono text-[11px] text-blue-600 font-bold"><?= $b['tour_code'] ?></span>
                            <h3 class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($b['tour_title']) ?></h3>
                            <p class="text-slate-500">
                                Khởi hành: <strong class="text-slate-800"><?= date('d/m/Y', strtotime($b['departure_date'])) ?></strong> &rarr; <?= date('d/m/Y', strtotime($b['return_date'])) ?>
                            </p>
                            <span class="text-slate-400 text-[11px]">Đoàn đi: <?= count($passengers) ?> hành khách</span>
                        </div>
                    </div>

                    <div class="w-full md:w-auto md:text-right border-t md:border-t-0 pt-3 md:pt-0 space-y-1">
                        <span class="text-slate-400 block text-[11px]">Tổng giá trị đơn:</span>
                        <div class="text-base font-extrabold text-slate-900"><?= number_format($b['total_amount'], 0, ',', '.') ?> đ</div>
                        <span class="text-[11px] text-emerald-600 font-semibold block">Đã thanh toán: <?= number_format($b['paid_amount'], 0, ',', '.') ?> đ</span>
                    </div>
                </div>

                <!-- Thanh nút chức năng -->
                <div class="px-5 py-3 bg-slate-50/70 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <a href="<?= BASE_URL ?>modules/booking/booking_confirm.php?code=<?= $b['booking_code'] ?>" target="_blank"
                           class="inline-flex items-center gap-1 bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 px-3 py-1.5 rounded-lg font-semibold transition">
                            <i data-lucide="printer" class="w-3.5 h-3.5"></i> In Phiếu Xác Nhận
                        </a>

                        <?php if (!empty($b['invoice_id'])): ?>
                            <a href="<?= BASE_URL ?>modules/accounting/invoice_detail.php?id=<?= $b['invoice_id'] ?>" target="_blank"
                               class="inline-flex items-center gap-1 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 px-3 py-1.5 rounded-lg font-semibold transition">
                                <i data-lucide="file-check" class="w-3.5 h-3.5 text-rose-600"></i> Hóa Đơn Điện Tử VAT
                            </a>
                        <?php endif; ?>
                    </div>

                    <div>
                        <!-- Nút gửi đánh giá (Kích hoạt khi chuyến đi đã hoàn tất) -->
                        <?php if ($b['booking_status'] === 'Hoàn tất'): ?>
                            <?php if (!empty($b['review_id'])): ?>
                                <span class="text-amber-500 font-bold flex items-center gap-1 bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-200">
                                    <?= str_repeat('★', $b['rating']) ?> (<?= $b['review_status'] ?>)
                                </span>
                            <?php else: ?>
                                <button @click="showReviewModal = true; selectedBookingId = <?= $b['booking_id'] ?>; selectedTourId = <?= $b['tour_id'] ?? 1 ?>; selectedTourTitle = '<?= htmlspecialchars(addslashes($b['tour_title'])) ?>'"
                                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-3.5 py-1.5 rounded-lg transition shadow flex items-center gap-1.5">
                                    <i data-lucide="star" class="w-3.5 h-3.5"></i> Đánh Giá Tour
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-slate-400 italic text-[11px]">Đánh giá khả dụng sau khi chuyến đi hoàn tất</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- MODAL GỬI ĐÁNH GIÁ (Alpine.js) -->
    <div x-show="showReviewModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-md w-full p-6 space-y-4">
            <div class="flex items-center justify-between border-b pb-3">
                <h2 class="text-base font-bold text-slate-800 flex items-center gap-1.5">
                    <i data-lucide="message-square-plus" class="w-5 h-5 text-blue-600"></i> Viết Đánh Giá Chuyến Đi
                </h2>
                <button @click="showReviewModal = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
            </div>

            <form action="<?= BASE_URL ?>modules/reviews/submit_review.php" method="POST" class="space-y-4 text-xs">
                <input type="hidden" name="booking_id" :value="selectedBookingId">
                <input type="hidden" name="tour_id" :value="selectedTourId">

                <div>
                    <span class="text-slate-500 block mb-1">Chương trình đã trải nghiệm:</span>
                    <strong class="text-slate-900" x-text="selectedTourTitle"></strong>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Mức độ hài lòng của bạn (*):</label>
                    <select name="rating" required class="w-full border rounded-lg p-2.5 text-xs font-bold text-amber-500">
                        <option value="5">★★★★★ (5 sao - Tuyệt vời vượt mong đợi)</option>
                        <option value="4">★★★★☆ (4 sao - Rất tốt, dịch vụ chu đáo)</option>
                        <option value="3">★★★☆☆ (3 sao - Tạm ổn, cần cải thiện thêm)</option>
                        <option value="2">★★☆☆☆ (2 sao - Chưa hài lòng)</option>
                        <option value="1">★☆☆☆☆ (1 sao - Trải nghiệm không tốt)</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nhận xét chi tiết (*):</label>
                    <textarea name="comment" required rows="4" class="w-full border rounded-lg p-2.5 text-xs" 
                              placeholder="Chia sẻ cảm nhận của bạn về khách sạn, bữa ăn, xe di chuyển và hướng dẫn viên đồng hành..."></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="showReviewModal = false" class="px-4 py-2 border rounded-lg hover:bg-slate-50 font-semibold">Đóng</button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold">Gửi Nhận Xét</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>