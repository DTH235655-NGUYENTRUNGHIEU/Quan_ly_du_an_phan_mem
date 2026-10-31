<?php
// modules/booking/booking_confirm.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$booking_code = trim($_GET['code'] ?? '');

if (empty($booking_code)) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// 1. Lấy thông tin đơn và thông tin đợt khởi hành
$sql = "SELECT b.*, c.fullname AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
               dep.departure_date, dep.return_date, t.title AS tour_title, t.tour_code, t.start_location
        FROM bookings b, customers c, tour_departures dep, tours t
        WHERE b.customer_id = c.customer_id 
          AND b.departure_id = dep.departure_id 
          AND dep.tour_id = t.tour_id 
          AND b.booking_code = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$booking_code]);
$b = $stmt->fetch();

if (!$b) die("Không tìm thấy đơn đặt tour!");

// 2. Lấy danh sách hành khách đi đoàn trong đơn này
$stmtP = $pdo->prepare("SELECT * FROM booking_passengers WHERE booking_id = ?");
$stmtP->execute([$b['booking_id']]);
$passengers = $stmtP->fetchAll();

$page_title = "Biên Nhận Đăng Ký Đặt Tour " . $b['booking_code'];
include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-xl border border-slate-200 space-y-6">
        <!-- Thông báo trạng thái tiếp nhận -->
        <div class="text-center space-y-2 border-b pb-6">
            <div class="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-full mx-auto flex items-center justify-center">
                <i data-lucide="check-check" class="w-8 h-8"></i>
            </div>
            <h1 class="text-2xl font-black text-slate-900">TIẾP NHẬN ĐƠN ĐẶT TOUR THÀNH CÔNG!</h1>
            <p class="text-xs text-slate-500 max-w-md mx-auto">
                Cảm ơn Quý khách <strong class="text-slate-800"><?= htmlspecialchars($b['customer_name']) ?></strong>. Nhân viên tư vấn của Công ty Du lịch Việt Nam sẽ liên hệ xác nhận chuyến đi qua số điện thoại <strong class="text-slate-800"><?= $b['customer_phone'] ?></strong> trong vòng 15-30 phút.
            </p>
        </div>

        <!-- Chi tiết tóm tắt đơn tour -->
        <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-3">
            <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                <span class="text-slate-500">Mã đơn đặt tour:</span>
                <span class="font-mono font-bold text-sm text-blue-600">#<?= $b['booking_code'] ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-500">Tên chương trình tour:</span>
                <strong class="text-slate-800"><?= htmlspecialchars($b['tour_title']) ?></strong>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-500">Ngày khởi hành - Về:</span>
                <span class="font-bold text-slate-800"><?= date('d/m/Y', strtotime($b['departure_date'])) ?> &rarr; <?= date('d/m/Y', strtotime($b['return_date'])) ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-500">Điểm đón khách:</span>
                <span class="text-slate-700"><?= htmlspecialchars($b['start_location']) ?></span>
            </div>
            <div class="flex justify-between items-center border-t border-slate-200 pt-2 text-sm font-extrabold">
                <span class="text-slate-700">Tổng thanh toán:</span>
                <span class="text-rose-600"><?= number_format($b['total_amount'], 0, ',', '.') ?> đ</span>
            </div>
        </div>

        <!-- Danh sách thành viên trong đoàn được mua bảo hiểm -->
        <div class="space-y-3">
            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600"></i> Danh Sách Hành Khách Đã Đăng Ký Bảo Hiểm
            </h2>
            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-100 font-bold text-slate-700">
                        <tr>
                            <th class="p-2.5">STT</th>
                            <th class="p-2.5">Họ và Tên</th>
                            <th class="p-2.5">Đối tượng</th>
                            <th class="p-2.5">Giới tính</th>
                            <th class="p-2.5">Số CCCD / Ngày sinh</th>
                            <th class="p-2.5 text-center">Phòng đơn</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($passengers as $idx => $p): ?>
                            <tr>
                                <td class="p-2.5 font-bold"><?= $idx + 1 ?></td>
                                <td class="p-2.5 font-semibold text-slate-800"><?= htmlspecialchars($p['fullname']) ?></td>
                                <td class="p-2.5"><?= $p['passenger_type'] ?></td>
                                <td class="p-2.5"><?= $p['gender'] ?></td>
                                <td class="p-2.5 font-mono"><?= $p['id_card'] ?: date('d/m/Y', strtotime($p['birth_date'])) ?></td>
                                <td class="p-2.5 text-center">
                                    <?= $p['single_room'] ? '<span class="text-blue-600 font-bold">Có</span>' : '<span class="text-slate-400">Không</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Các nút thao tác -->
        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t">
            <button onclick="window.print()" class="flex-1 bg-slate-800 hover:bg-slate-900 text-white font-bold py-2.5 rounded-xl text-xs flex items-center justify-center gap-1.5 transition">
                <i data-lucide="printer" class="w-4 h-4"></i> In Phiếu Xác Nhận Này
            </button>
            <a href="<?= BASE_URL ?>index.php" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-xl text-xs text-center flex items-center justify-center gap-1.5 transition">
                <i data-lucide="home" class="w-4 h-4"></i> Về Trang Chủ Khám Phá Thêm
            </a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>