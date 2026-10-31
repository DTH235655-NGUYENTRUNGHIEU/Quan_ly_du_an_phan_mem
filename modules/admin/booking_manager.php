<?php
// modules/admin/booking_manager.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/business_helpers.php';

// 1. Phân quyền: Administrator, Sales Staff và Accountant được phép xử lý đơn tour[cite: 2]
require_roles(['Administrator', 'Sales Staff', 'Accountant']);

$currentUser = current_user();
$msg_success = "";
$msg_error = "";

// 2. Xử lý các thao tác quản trị đơn tour
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $booking_id = intval($_POST['booking_id'] ?? 0);

    try {
        $pdo->beginTransaction();

        // 2.1. Thao tác Xác nhận tiền cọc (Dành cho Kế toán / Sales)[cite: 2]
        if ($_POST['action'] === 'confirm_deposit') {
            // Lấy thông tin phiếu thu cọc đang xử lý
            $stmtPay = $pdo->prepare("SELECT payment_id, amount FROM payments WHERE booking_id = ? AND payment_phase = 'Đặt cọc' AND status = 'Đang xử lý' LIMIT 1");
            $stmtPay->execute([$booking_id]);
            $pay = $stmtPay->fetch();

            if ($pay) {
                // Cập nhật trạng thái phiếu thu thành công và lưu nhân sự duyệt tiền[cite: 2]
                $upPay = $pdo->prepare("UPDATE payments SET status = 'Thành công', processed_by = ? WHERE payment_id = ?");
                $upPay->execute([$currentUser['user_id'], $pay['payment_id']]);

                // Lấy thông tin đợt khởi hành
                $stmtDepId = $pdo->prepare("SELECT departure_id FROM bookings WHERE booking_id = ?");
                $stmtDepId->execute([$booking_id]);
                $dep_id = (int)$stmtDepId->fetchColumn();

                // Cập nhật số tiền đã đóng và chuyển booking sang Đã xác nhận[cite: 2]
                $upBook = $pdo->prepare("UPDATE bookings 
                                         SET paid_amount = paid_amount + ?, 
                                             payment_status = 'Đã đặt cọc', 
                                             booking_status = 'Đã xác nhận' 
                                         WHERE booking_id = ?");
                $upBook->execute([$pay['amount'], $booking_id]);

                // Đồng bộ lại số chỗ khởi hành tự động[cite: 2]
                if ($dep_id > 0) {
                    sync_departure_seats($pdo, $dep_id);
                }

                $msg_success = "Đã xác nhận thanh toán tiền cọc (" . number_format($pay['amount'], 0, ',', '.') . " đ) và chuyển đơn sang trạng thái ĐÃ XÁC NHẬN!";
            } else {
                throw new Exception("Không tìm thấy phiếu thu cọc đang chờ xử lý cho đơn này.");
            }
        }

        // 2.2. Thao tác Cập nhật trạng thái đơn (Chờ duyệt, Đã xác nhận, Đang đi tour, Hoàn tất, Đã hủy)[cite: 2]
        elseif ($_POST['action'] === 'update_booking_status') {
            $new_status = $_POST['new_booking_status'] ?? '';
            $allowed_statuses = ['Chờ duyệt', 'Đã xác nhận', 'Đang đi tour', 'Hoàn tất', 'Đã hủy'];

            if (in_array($new_status, $allowed_statuses, true)) {
                // Lấy thông tin đơn hiện tại
                $stmtCur = $pdo->prepare("SELECT departure_id, booking_status FROM bookings WHERE booking_id = ?");
                $stmtCur->execute([$booking_id]);
                $bCur = $stmtCur->fetch();

                if ($bCur) {
                    $dep_id = (int)$bCur['departure_id'];

                    // Cập nhật trạng thái đơn đặt
                    $pdo->prepare("UPDATE bookings SET booking_status = ? WHERE booking_id = ?")->execute([$new_status, $booking_id]);

                    // TỰ ĐỘNG ĐỒNG BỘ LẠI SỐ CHỖ CỦA ĐỢT KHỞI HÀNH (Trừ chỗ khi hủy hoặc cộng lại chỗ khi phục hồi đơn)[cite: 2]
                    sync_departure_seats($pdo, $dep_id);

                    // TỰ ĐỘNG TÍCH LŨY ĐIỂM THƯỞNG CHO KHÁCH HÀNG KHI ĐƠN CHUYỂN SANG 'Hoàn tất'[cite: 2]
                    if ($new_status === 'Hoàn tất') {
                        $rewarded = reward_points_on_booking_complete($pdo, $booking_id);
                        if ($rewarded) {
                            $msg_success = "Đã cập nhật trạng thái đơn #BK-{$booking_id} sang [Hoàn tất] và tự động tích lũy điểm thưởng vào tài khoản khách hàng!";
                        } else {
                            $msg_success = "Đã cập nhật trạng thái đơn sang [Hoàn tất] (điểm thưởng đã được tích lũy trước đó hoặc chưa đủ điều kiện).";
                        }
                    } else {
                        $msg_success = "Đã cập nhật trạng thái đơn sang: {$new_status} và đồng bộ lại số chỗ khởi hành!";
                    }
                }
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg_error = "Lỗi xử lý: " . $e->getMessage();
    }
}

// 3. Xây dựng bộ lọc tìm kiếm đơn đặt tour (Không sử dụng từ khóa JOIN)[cite: 2]
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$payment_filter = trim($_GET['payment'] ?? '');
$selected_code = trim($_GET['code'] ?? '');

$where_clauses = ["b.customer_id = c.customer_id", "b.departure_id = dep.departure_id", "dep.tour_id = t.tour_id"];
$params = [];

if (!empty($selected_code)) {
    $where_clauses[] = "b.booking_code = ?";
    $params[] = $selected_code;
} else {
    if (!empty($search)) {
        $where_clauses[] = "(b.booking_code LIKE ? OR c.fullname LIKE ? OR c.phone LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    if (!empty($status_filter)) {
        $where_clauses[] = "b.booking_status = ?";
        $params[] = $status_filter;
    }
    if (!empty($payment_filter)) {
        $where_clauses[] = "b.payment_status = ?";
        $params[] = $payment_filter;
    }
}

$where_sql = implode(" AND ", $where_clauses);
$sqlBookings = "SELECT b.*, c.fullname AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
                       dep.departure_date, dep.return_date, dep.max_seats, dep.booked_seats, dep.status AS departure_status,
                       t.title AS tour_title, t.tour_code
                FROM bookings b, customers c, tour_departures dep, tours t
                WHERE {$where_sql}
                ORDER BY b.booking_id DESC";
$stmt = $pdo->prepare($sqlBookings);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$page_title = "Quản Lý Đơn Đặt Tour & Danh Sách Khách Đi Đoàn";
include '../../includes/header.php';
?>

<div class="flex min-h-screen bg-slate-100">
    <!-- Nạp Sidebar Admin dùng chung[cite: 1] -->
    <?php include '../../includes/admin_sidebar.php'; ?>

    <main class="flex-1 p-8 space-y-6 overflow-y-auto">
        <!-- Tiêu đề & Thông báo -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Quản Lý Đơn Đặt Tour (Bookings)</h1>
                <p class="text-xs text-slate-500 mt-1">Duyệt hồ sơ đăng ký, xác nhận chuyển khoản cọc và quản lý danh sách bảo hiểm du khách</p>
            </div>
            <a href="<?= BASE_URL ?>modules/admin/dashboard.php" class="text-xs text-slate-600 bg-white border px-3 py-1.5 rounded-lg font-semibold hover:bg-slate-50 shadow-sm inline-flex items-center gap-1">
                &larr; Quay lại bàn làm việc
            </a>
        </div>

        <?php if (!empty($msg_success)): ?>
            <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-center gap-2 font-bold shadow-sm">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i> <?= $msg_success ?>
            </div>
        <?php elseif (!empty($msg_error)): ?>
            <div class="p-3.5 bg-rose-50 border border-rose-200 text-rose-800 text-xs rounded-xl flex items-center gap-2 font-bold shadow-sm">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i> <?= $msg_error ?>
            </div>
        <?php endif; ?>

        <!-- Thanh công cụ tìm kiếm và lọc dữ liệu -->
        <form method="GET" class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
            <div>
                <label class="block font-semibold text-slate-600 mb-1">Tìm theo Mã đơn / Tên / SĐT:</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nhập BK... hoặc số điện thoại" class="w-full border rounded-lg p-2 focus:ring-1 focus:ring-blue-500">
            </div>
            <div>
                <label class="block font-semibold text-slate-600 mb-1">Trạng thái đặt tour:</label>
                <select name="status" class="w-full border rounded-lg p-2 focus:ring-1 focus:ring-blue-500">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="Chờ duyệt" <?= $status_filter === 'Chờ duyệt' ? 'selected' : '' ?>>Chờ duyệt</option>
                    <option value="Đã xác nhận" <?= $status_filter === 'Đã xác nhận' ? 'selected' : '' ?>>Đã xác nhận</option>
                    <option value="Đang đi tour" <?= $status_filter === 'Đang đi tour' ? 'selected' : '' ?>>Đang đi tour</option>
                    <option value="Hoàn tất" <?= $status_filter === 'Hoàn tất' ? 'selected' : '' ?>>Hoàn tất</option>
                    <option value="Đã hủy" <?= $status_filter === 'Đã hủy' ? 'selected' : '' ?>>Đã hủy</option>
                </select>
            </div>
            <div>
                <label class="block font-semibold text-slate-600 mb-1">Trạng thái thanh toán:</label>
                <select name="payment" class="w-full border rounded-lg p-2 focus:ring-1 focus:ring-blue-500">
                    <option value="">-- Tất cả thanh toán --</option>
                    <option value="Chưa thanh toán" <?= $payment_filter === 'Chưa thanh toán' ? 'selected' : '' ?>>Chưa thanh toán</option>
                    <option value="Đã đặt cọc" <?= $payment_filter === 'Đã đặt cọc' ? 'selected' : '' ?>>Đã đặt cọc</option>
                    <option value="Đã thanh toán đủ" <?= $payment_filter === 'Đã thanh toán đủ' ? 'selected' : '' ?>>Đã thanh toán đủ</option>
                    <option value="Đã hoàn tiền" <?= $payment_filter === 'Đã hoàn tiền' ? 'selected' : '' ?>>Đã hoàn tiền</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold p-2.5 rounded-lg transition flex items-center justify-center gap-1.5 shadow-sm">
                    <i data-lucide="filter" class="w-3.5 h-3.5"></i> Lọc đơn
                </button>
                <a href="<?= BASE_URL ?>modules/admin/booking_manager.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold p-2.5 rounded-lg transition" title="Xóa lọc">
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        </form>

        <!-- Bảng danh sách đơn đặt tour -->
        <div class="space-y-4">
            <?php if (empty($bookings)): ?>
                <div class="bg-white p-8 rounded-2xl border text-center text-slate-400 text-xs">
                    Không tìm thấy đơn đặt tour nào phù hợp với điều kiện tìm kiếm.
                </div>
            <?php endif; ?>

            <?php foreach ($bookings as $b): ?>
                <?php
                // Lấy danh sách hành khách bảo hiểm thuộc đơn này
                $stmtPass = $pdo->prepare("SELECT * FROM booking_passengers WHERE booking_id = ?");
                $stmtPass->execute([$b['booking_id']]);
                $passengers = $stmtPass->fetchAll();

                // Đếm số lượng yêu cầu phòng riêng
                $single_room_count = 0;
                foreach ($passengers as $p) {
                    if (!empty($p['single_room'])) $single_room_count++;
                }

                // Lấy thông tin các lần thanh toán
                $stmtP = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ?");
                $stmtP->execute([$b['booking_id']]);
                $payments = $stmtP->fetchAll();
                ?>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden text-xs">
                    <!-- Thanh tiêu đề của từng đơn -->
                    <div class="p-4 bg-slate-50 border-b flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="font-mono font-bold text-sm text-blue-600 bg-blue-50 px-2.5 py-1 rounded-md border border-blue-200">
                                #<?= htmlspecialchars($b['booking_code']) ?>
                            </span>
                            <span class="text-slate-400">Ngày đặt: <?= date('d/m/Y H:i', strtotime($b['booking_date'])) ?></span>
                            <?php if (!empty($b['is_rewarded'])): ?>
                                <span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1 border border-amber-300">
                                    <i data-lucide="award" class="w-3 h-3 text-amber-600"></i> Đã tích điểm thưởng
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Badge thanh toán -->
                            <span class="px-2.5 py-1 rounded-md font-bold text-[11px] 
                                <?= $b['payment_status'] === 'Đã thanh toán đủ' ? 'bg-emerald-100 text-emerald-800' : ($b['payment_status'] === 'Đã đặt cọc' ? 'bg-blue-100 text-blue-800' : 'bg-rose-100 text-rose-800') ?>">
                                <?= $b['payment_status'] ?>
                            </span>

                            <!-- Badge trạng thái đơn -->
                            <span class="px-2.5 py-1 rounded-md font-bold text-[11px] 
                                <?= $b['booking_status'] === 'Đã xác nhận' ? 'bg-emerald-100 text-emerald-800' : ($b['booking_status'] === 'Hoàn tất' ? 'bg-blue-100 text-blue-800' : ($b['booking_status'] === 'Chờ duyệt' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700')) ?>">
                                <?= $b['booking_status'] ?>
                            </span>
                        </div>
                    </div>

                    <!-- Nội dung chi tiết đơn -->
                    <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Cột 1: Thông tin tour & Ngày đi -->
                        <div class="space-y-1.5 border-b md:border-b-0 md:border-r border-slate-100 pb-4 md:pb-0 pr-4">
                            <span class="text-[11px] font-bold uppercase text-slate-400">Thông tin chương trình</span>
                            <h3 class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($b['tour_title']) ?></h3>
                            <p class="text-slate-500">Mã tour: <strong><?= $b['tour_code'] ?></strong></p>
                            <p class="text-slate-500">
                                Khởi hành: <strong class="text-blue-600"><?= date('d/m/Y', strtotime($b['departure_date'])) ?></strong> &rarr; <?= date('d/m/Y', strtotime($b['return_date'])) ?>
                            </p>
                            <p class="text-[11px] text-slate-500">
                                Chỗ đợt này: <strong class="text-emerald-700 font-bold"><?= $b['booked_seats'] ?> / <?= $b['max_seats'] ?></strong> 
                                (<span class="font-semibold text-slate-600"><?= $b['departure_status'] ?></span>)
                            </p>
                            <div class="pt-2 text-slate-600">
                                Số lượng khách: <strong><?= $b['num_adults'] ?> lớn, <?= $b['num_children'] ?> trẻ em, <?= $b['num_infants'] ?> bé</strong>
                                <?php if ($single_room_count > 0): ?>
                                    <span class="text-amber-700 font-semibold block mt-0.5">• Có <?= $single_room_count ?> khách yêu cầu phòng đơn riêng</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($b['special_notes'])): ?>
                                <p class="text-amber-700 bg-amber-50 p-2 rounded-lg mt-2 italic border border-amber-200">Ghi chú: <?= htmlspecialchars($b['special_notes']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Cột 2: Đại diện đặt tour & Tài chính -->
                        <div class="space-y-1.5 border-b md:border-b-0 md:border-r border-slate-100 pb-4 md:pb-0 pr-4">
                            <span class="text-[11px] font-bold uppercase text-slate-400">Người đại diện liên hệ</span>
                            <p class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($b['customer_name']) ?></p>
                            <p class="text-slate-500">Điện thoại: <strong class="text-slate-700 font-mono"><?= $b['customer_phone'] ?></strong></p>
                            <p class="text-slate-500">Email: <?= $b['customer_email'] ?></p>
                            
                            <div class="pt-3 border-t border-slate-100 space-y-1">
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Tổng giá trị đơn:</span>
                                    <strong class="text-slate-900 font-bold"><?= number_format($b['total_amount'], 0, ',', '.') ?> đ</strong>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Đã thanh toán:</span>
                                    <strong class="text-emerald-600 font-bold"><?= number_format($b['paid_amount'], 0, ',', '.') ?> đ</strong>
                                </div>
                                <div class="flex justify-between text-rose-600 font-bold">
                                    <span>Còn lại phải thu:</span>
                                    <span><?= number_format(max(0, $b['total_amount'] - $b['paid_amount']), 0, ',', '.') ?> đ</span>
                                </div>
                            </div>
                        </div>

                        <!-- Cột 3: Thao tác duyệt & Xác nhận cọc -->
                        <div class="space-y-3 flex flex-col justify-between">
                            <div class="space-y-2">
                                <span class="text-[11px] font-bold uppercase text-slate-400">Thao tác xử lý nghiệp vụ</span>
                                
                                <!-- Nút xác nhận tiền cọc VietQR nếu đang chờ duyệt -->
                                <?php if ($b['payment_status'] === 'Chưa thanh toán'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                                        <input type="hidden" name="action" value="confirm_deposit">
                                        <button type="submit" onclick="return confirm('Xác nhận đã nhận đủ tiền cọc 50% qua chuyển khoản QR?')"
                                                class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 rounded-xl transition shadow flex items-center justify-center gap-1.5">
                                            <i data-lucide="check-check" class="w-4 h-4"></i> Xác Nhận Nhận Cọc (50%)
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Cập nhật trạng thái booking & tự động đồng bộ số chỗ / điểm thưởng -->
                                <form method="POST" class="flex gap-1.5 items-center">
                                    <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                                    <input type="hidden" name="action" value="update_booking_status">
                                    <select name="new_booking_status" class="border rounded-lg p-2 text-xs flex-1 focus:ring-1 focus:ring-blue-500">
                                        <option value="Chờ duyệt" <?= $b['booking_status'] === 'Chờ duyệt' ? 'selected' : '' ?>>Chờ duyệt</option>
                                        <option value="Đã xác nhận" <?= $b['booking_status'] === 'Đã xác nhận' ? 'selected' : '' ?>>Đã xác nhận</option>
                                        <option value="Đang đi tour" <?= $b['booking_status'] === 'Đang đi tour' ? 'selected' : '' ?>>Đang đi tour</option>
                                        <option value="Hoàn tất" <?= $b['booking_status'] === 'Hoàn tất' ? 'selected' : '' ?>>Hoàn tất (Tích điểm)</option>
                                        <option value="Đã hủy" <?= $b['booking_status'] === 'Đã hủy' ? 'selected' : '' ?>>Hủy đơn (Hoàn chỗ)</option>
                                    </select>
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg font-bold shadow-sm transition">Lưu</button>
                                </form>
                            </div>

                            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                <a href="<?= BASE_URL ?>modules/booking/booking_confirm.php?code=<?= $b['booking_code'] ?>" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1 font-semibold">
                                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Xem phiếu xác nhận
                                </a>
                                <a href="<?= BASE_URL ?>modules/accounting/invoice_detail.php?booking_id=<?= $b['booking_id'] ?>" class="text-slate-600 hover:text-slate-900 flex items-center gap-1 font-semibold">
                                    <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Chi tiết Hóa đơn VAT
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Bảng danh sách hành khách mua bảo hiểm của đơn này[cite: 2] -->
                    <div class="px-5 pb-5">
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 space-y-2">
                            <span class="font-bold text-slate-700 flex items-center gap-1.5 text-[11px] uppercase">
                                <i data-lucide="shield" class="w-3.5 h-3.5 text-blue-600"></i> Danh Sách Hành Khách Khai Báo Bảo Hiểm (<?= count($passengers) ?> người)
                            </span>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-[11px] text-slate-600">
                                    <thead>
                                        <tr class="text-slate-400 border-b border-slate-200">
                                            <th class="pb-1">STT</th>
                                            <th class="pb-1">Họ và Tên</th>
                                            <th class="pb-1">Đối tượng</th>
                                            <th class="pb-1">Giới tính</th>
                                            <th class="pb-1">Ngày sinh</th>
                                            <th class="pb-1">Số CCCD / Khai sinh</th>
                                            <th class="pb-1 text-center">Phòng đơn riêng</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <?php foreach ($passengers as $pIdx => $p): ?>
                                            <tr>
                                                <td class="py-1 font-bold"><?= $pIdx + 1 ?></td>
                                                <td class="py-1 font-semibold text-slate-800"><?= htmlspecialchars($p['fullname']) ?></td>
                                                <td class="py-1"><?= $p['passenger_type'] ?></td>
                                                <td class="py-1"><?= $p['gender'] ?></td>
                                                <td class="py-1 whitespace-nowrap"><?= !empty($p['birth_date']) ? date('d/m/Y', strtotime($p['birth_date'])) : '—' ?></td>
                                                <td class="py-1 font-mono"><?= $p['id_card'] ?: 'Chưa cập nhật' ?></td>
                                                <td class="py-1 text-center">
                                                    <?= !empty($p['single_room']) ? '<span class="text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded font-bold">1 người/phòng</span>' : '<span class="text-slate-400">Ở ghép chung</span>' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>