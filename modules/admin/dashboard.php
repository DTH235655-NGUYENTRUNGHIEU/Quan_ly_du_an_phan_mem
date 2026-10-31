<?php
// modules/admin/dashboard.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// 1. Phân quyền: Chỉ 5 nhóm nhân sự nội bộ mới được vào Dashboard
require_roles(['Administrator', 'Sales Staff', 'Tour Operator', 'Accountant', 'Tour Guide']);

$user = current_user();

// 2. Truy vấn dữ liệu thống kê tổng quan
// 2.1. Tổng số tour đang mở bán
$total_tours = $pdo->query("SELECT COUNT(*) FROM tours WHERE status = 'Active'")->fetchColumn();

// 2.2. Số đơn đặt tour đang chờ duyệt
$pending_bookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'Chờ duyệt'")->fetchColumn();

// 2.3. Số đợt khởi hành sắp diễn ra trong tương lai
$upcoming_departures = $pdo->query("SELECT COUNT(*) FROM tour_departures WHERE departure_date >= CURDATE() AND status = 'Mở bán'")->fetchColumn();

// 2.4. Doanh thu tháng hiện tại lấy từ View v_monthly_revenue
$cur_year = date('Y');
$cur_month = date('m');
$stmtRev = $pdo->prepare("SELECT total_monthly_revenue FROM v_monthly_revenue WHERE year = ? AND month = ?");
$stmtRev->execute([$cur_year, $cur_month]);
$month_revenue = $stmtRev->fetchColumn() ?: 0;

// 3. Danh sách 5 đơn đặt tour mới nhất cần xử lý
$sqlRecentBookings = "SELECT b.booking_id, b.booking_code, b.booking_date, b.total_amount, b.paid_amount,
                             b.payment_status, b.booking_status,
                             c.fullname AS customer_name, c.phone AS customer_phone,
                             t.title AS tour_title, dep.departure_date
                      FROM bookings b
                      JOIN customers c ON b.customer_id = c.customer_id
                      JOIN tour_departures dep ON b.departure_id = dep.departure_id
                      JOIN tours t ON dep.tour_id = t.tour_id
                      ORDER BY b.booking_id DESC LIMIT 5";
$recent_bookings = $pdo->query($sqlRecentBookings)->fetchAll();

$page_title = "Bàn Làm Việc Quản Trị - Du Lịch Việt Nam";
include '../../includes/header.php';
?>

<div class="flex min-h-screen bg-slate-100">
    <!-- Sidebar Admin dùng chung -->
    <?php include '../../includes/admin_sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-8 space-y-8 overflow-y-auto">
        <!-- Top Greeting Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-5">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Bàn Làm Việc Tổng Quan</h1>
                <p class="text-xs text-slate-500 mt-1">
                    Xin chào <strong class="text-slate-800"><?= htmlspecialchars($user['fullname']) ?></strong>, bạn đang làm việc với vai trò 
                    <span class="inline-block bg-blue-100 text-blue-700 font-bold px-2 py-0.5 rounded text-[11px]"><?= $user['role_name'] ?></span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold bg-white border border-slate-200 px-3.5 py-2 rounded-xl text-slate-600 shadow-sm flex items-center gap-1.5">
                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-blue-600"></i> Hôm nay: <?= date('d/m/Y') ?>
                </span>
            </div>
        </div>

        <!-- 4 Thẻ chỉ số tổng quan (KPI Cards) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Đơn chờ duyệt -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Đơn Chờ Duyệt</span>
                    <div class="text-2xl font-black text-amber-600 mt-1"><?= number_format($pending_bookings) ?></div>
                    <span class="text-[11px] text-slate-400">Cần tư vấn xác nhận</span>
                </div>
                <div class="p-3 bg-amber-50 text-amber-600 rounded-xl"><i data-lucide="clock" class="w-6 h-6"></i></div>
            </div>

            <!-- Doanh thu tháng này -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Doanh Thu T<?= $cur_month ?></span>
                    <div class="text-2xl font-black text-emerald-600 mt-1"><?= number_format($month_revenue, 0, ',', '.') ?> đ</div>
                    <span class="text-[11px] text-slate-400">Đã chốt từ hóa đơn VAT</span>
                </div>
                <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl"><i data-lucide="trending-up" class="w-6 h-6"></i></div>
            </div>

            <!-- Đợt khởi hành sắp đi -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Đoàn Sắp Khởi Hành</span>
                    <div class="text-2xl font-black text-blue-600 mt-1"><?= number_format($upcoming_departures) ?></div>
                    <span class="text-[11px] text-slate-400">Đang tiếp tục mở bán</span>
                </div>
                <div class="p-3 bg-blue-50 text-blue-600 rounded-xl"><i data-lucide="bus" class="w-6 h-6"></i></div>
            </div>

            <!-- Tổng tour active -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Tour Hoạt Động</span>
                    <div class="text-2xl font-black text-slate-800 mt-1"><?= number_format($total_tours) ?></div>
                    <span class="text-[11px] text-emerald-600 font-medium">Sẵn sàng nhận khách</span>
                </div>
                <div class="p-3 bg-purple-50 text-purple-600 rounded-xl"><i data-lucide="map-pin" class="w-6 h-6"></i></div>
            </div>
        </div>

        <!-- Bảng 5 đơn đặt tour mới phát sinh gần nhất -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden space-y-3">
            <div class="p-4 sm:px-6 bg-slate-50/70 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="shopping-bag" class="w-4 h-4 text-blue-600"></i>
                    <h2 class="text-sm font-bold text-slate-800">Đơn Đăng Ký Đặt Tour Vừa Gửi Đến</h2>
                </div>
                <a href="<?= BASE_URL ?>modules/admin/booking_manager.php" class="text-xs font-semibold text-blue-600 hover:underline">
                    Xem tất cả đơn &rarr;
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 text-slate-700 uppercase font-bold text-[11px]">
                        <tr>
                            <th class="p-3.5">Mã Đơn</th>
                            <th class="p-3.5">Khách Đại Diện</th>
                            <th class="p-3.5">Tour & Ngày Khởi Hành</th>
                            <th class="p-3.5 text-right">Tổng Tiền</th>
                            <th class="p-3.5 text-center">Thanh Toán</th>
                            <th class="p-3.5 text-center">Trạng Thái Đơn</th>
                            <th class="p-3.5 text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($recent_bookings)): ?>
                            <tr>
                                <td colspan="7" class="p-6 text-center text-slate-400">Chưa có đơn đặt tour nào phát sinh.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($recent_bookings as $b): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-3.5 font-bold font-mono text-blue-600">#<?= $b['booking_code'] ?></td>
                                <td class="p-3.5">
                                    <strong class="text-slate-800 block"><?= htmlspecialchars($b['customer_name']) ?></strong>
                                    <span class="text-slate-400 text-[11px]"><?= $b['customer_phone'] ?></span>
                                </td>
                                <td class="p-3.5 max-w-xs">
                                    <span class="line-clamp-1 font-medium text-slate-800"><?= htmlspecialchars($b['tour_title']) ?></span>
                                    <span class="text-slate-400 text-[11px]">Đi ngày: <?= date('d/m/Y', strtotime($b['departure_date'])) ?></span>
                                </td>
                                <td class="p-3.5 text-right font-bold text-slate-900">
                                    <?= number_format($b['total_amount'], 0, ',', '.') ?> đ
                                </td>
                                <td class="p-3.5 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold 
                                        <?= $b['payment_status'] === 'Đã thanh toán đủ' ? 'bg-emerald-100 text-emerald-800' : ($b['payment_status'] === 'Đã đặt cọc' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800') ?>">
                                        <?= $b['payment_status'] ?>
                                    </span>
                                </td>
                                <td class="p-3.5 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold 
                                        <?= $b['booking_status'] === 'Đã xác nhận' ? 'bg-emerald-100 text-emerald-800' : ($b['booking_status'] === 'Chờ duyệt' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') ?>">
                                        <?= $b['booking_status'] ?>
                                    </span>
                                </td>
                                <td class="p-3.5 text-center">
                                    <a href="<?= BASE_URL ?>modules/admin/booking_manager.php?code=<?= $b['booking_code'] ?>" 
                                       class="inline-block bg-slate-100 hover:bg-blue-600 hover:text-white px-2.5 py-1 rounded text-slate-600 font-bold transition">
                                        Chi tiết
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>lucide.createIcons();</script>
</body>
</html>