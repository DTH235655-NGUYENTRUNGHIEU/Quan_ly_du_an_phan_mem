<?php
// modules/accounting/revenue_report.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// 1. Phân quyền chặt chẽ ngay từ đầu: Chỉ Admin và Kế toán viên mới được xem
require_roles(['Administrator', 'Accountant']);

$page_title = "Báo cáo Doanh thu & Quản lý Hóa đơn VAT";
include '../../includes/header.php';

// 2. Lấy dữ liệu doanh thu tháng hiện tại và năm nay từ 2 SQL Views
$cur_year = date('Y');
$cur_month = date('m');

$stmtMonth = $pdo->prepare("SELECT * FROM v_monthly_revenue WHERE year = ? AND month = ?");
$stmtMonth->execute([$cur_year, $cur_month]);
$month_data = $stmtMonth->fetch();

$stmtYear = $pdo->prepare("SELECT * FROM v_yearly_revenue WHERE year = ?");
$stmtYear->execute([$cur_year]);
$year_data = $stmtYear->fetch();

// 3. Lấy danh sách các hóa đơn đã phát hành gần nhất
$invoices = $pdo->query("SELECT i.*, b.booking_code 
                         FROM invoices i 
                         JOIN bookings b ON i.booking_id = b.booking_id 
                         ORDER BY i.invoice_id DESC LIMIT 10")->fetchAll();
?>

<div class="flex min-h-screen bg-slate-100">
    <!-- Nạp Sidebar Admin dùng chung đã phân quyền động -->
    <?php include '../../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-8 space-y-6 overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-200 pb-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900">Báo Cáo Doanh Thu & Hóa Đơn Điện Tử VAT</h1>
                <p class="text-xs text-slate-500">Tự động tổng hợp dữ liệu từ 2 View v_monthly_revenue và v_yearly_revenue</p>
            </div>
            <span class="text-xs bg-emerald-100 text-emerald-800 font-semibold px-3 py-1 rounded-full border border-emerald-200">
                Kỳ tài chính: Năm <?= $cur_year ?>
            </span>
        </div>

        <!-- 3 Thẻ thống kê doanh thu -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 font-semibold uppercase">Doanh Thu Tháng <?= $cur_month ?>/<?= $cur_year ?></span>
                    <div class="text-2xl font-black text-emerald-600 mt-1">
                        <?= number_format($month_data['total_monthly_revenue'] ?? 0, 0, ',', '.') ?> đ
                    </div>
                    <span class="text-[11px] text-slate-400">Thuế VAT: <?= number_format($month_data['total_vat_collected'] ?? 0, 0, ',', '.') ?> đ</span>
                </div>
                <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl"><i data-lucide="trending-up" class="w-6 h-6"></i></div>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 font-semibold uppercase">Doanh Thu Cả Năm <?= $cur_year ?></span>
                    <div class="text-2xl font-black text-blue-600 mt-1">
                        <?= number_format($year_data['total_yearly_revenue'] ?? 0, 0, ',', '.') ?> đ
                    </div>
                    <span class="text-[11px] text-slate-400">Trước thuế: <?= number_format($year_data['subtotal_revenue'] ?? 0, 0, ',', '.') ?> đ</span>
                </div>
                <div class="p-3 bg-blue-50 text-blue-600 rounded-xl"><i data-lucide="calendar" class="w-6 h-6"></i></div>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 font-semibold uppercase">Tổng Hóa Đơn Phát Hành</span>
                    <div class="text-2xl font-black text-slate-800 mt-1">
                        <?= number_format($year_data['total_invoices_issued'] ?? 0, 0, ',', '.') ?>
                    </div>
                    <span class="text-[11px] text-emerald-600 font-semibold">Thuế suất chuẩn: 8.0%</span>
                </div>
                <div class="p-3 bg-purple-50 text-purple-600 rounded-xl"><i data-lucide="file-check" class="w-6 h-6"></i></div>
            </div>
        </div>

        <!-- Bảng danh sách hóa đơn điện tử VAT -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200 font-bold text-sm text-slate-700 flex items-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4 text-blue-600"></i> Danh Sách Hóa Đơn VAT Vừa Phát Hành
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[11px]">
                        <tr>
                            <th class="p-3.5">Số Hóa Đơn</th>
                            <th class="p-3.5">Mã Booking</th>
                            <th class="p-3.5">Đơn vị / Người mua</th>
                            <th class="p-3.5">Mã Số Thuế</th>
                            <th class="p-3.5 text-right">Tiền Trước Thuế</th>
                            <th class="p-3.5 text-right">VAT (8%)</th>
                            <th class="p-3.5 text-right">Tổng Thanh Toán</th>
                            <th class="p-3.5 text-center">Trạng Thái</th>
                            <th class="p-3.5 text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php foreach ($invoices as $inv): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-3.5 font-bold text-blue-600"><?= $inv['invoice_number'] ?></td>
                                <td class="p-3.5 font-mono text-slate-500"><?= $inv['booking_code'] ?></td>
                                <td class="p-3.5 font-semibold text-slate-800"><?= htmlspecialchars($inv['buyer_name']) ?></td>
                                <td class="p-3.5 font-mono text-slate-500"><?= $inv['tax_code'] ?: 'Khách cá nhân' ?></td>
                                <td class="p-3.5 text-right"><?= number_format($inv['subtotal_amount'], 0, ',', '.') ?> đ</td>
                                <td class="p-3.5 text-right text-rose-600 font-medium"><?= number_format($inv['vat_amount'], 0, ',', '.') ?> đ</td>
                                <td class="p-3.5 text-right font-bold text-emerald-600"><?= number_format($inv['total_amount'], 0, ',', '.') ?> đ</td>
                                <td class="p-3.5 text-center">
                                    <span class="bg-emerald-100 text-emerald-800 text-[10px] px-2 py-0.5 rounded font-bold">
                                        <?= $inv['status'] ?>
                                    </span>
                                </td>
                                <td class="p-3.5 text-center">
                                    <a href="/dulichvietnam/modules/accounting/invoice_detail.php?id=<?= $inv['invoice_id'] ?>" 
                                       class="inline-block bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white px-2.5 py-1 rounded text-[11px] font-bold transition">
                                        Xem & In
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