<?php
// includes/admin_sidebar.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/auth.php';

$u = current_user();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="w-64 bg-slate-900 text-slate-300 flex flex-col shrink-0 min-h-screen">
    <!-- Brand Info -->
    <div class="p-5 border-b border-slate-800">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white font-bold shadow-md shadow-blue-500/20">
                <i data-lucide="shield-check" class="w-5 h-5"></i>
            </div>
            <div>
                <h2 class="text-sm font-extrabold text-white tracking-wide leading-tight">BAN ĐIỀU HÀNH</h2>
                <span class="text-[11px] text-blue-400 font-medium"><?= htmlspecialchars($u['role_name'] ?? 'Nhân viên') ?></span>
            </div>
        </div>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 p-4 space-y-1.5 text-xs font-semibold">
        <!-- 1. Tổng quan Dashboard (Tất cả nhân sự) -->
        <a href="<?= BASE_URL ?>modules/admin/dashboard.php" 
           class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl transition <?= $current_page === 'dashboard.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-slate-800 text-slate-400 hover:text-slate-200' ?>">
            <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Tổng quan hệ thống
        </a>

        <!-- 2. Quản trị & Điều hành Tour (Admin & Tour Operator) -->
        <?php if (has_role(['Administrator', 'Tour Operator'])): ?>
            <a href="<?= BASE_URL ?>modules/admin/tour_manager.php" 
               class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl transition <?= $current_page === 'tour_manager.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-slate-800 text-slate-400 hover:text-slate-200' ?>">
                <i data-lucide="map" class="w-4 h-4"></i> Quản lý Tour & Lịch trình
            </a>
            <a href="<?= BASE_URL ?>modules/admin/guide_assign.php" 
               class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl transition <?= $current_page === 'guide_assign.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-slate-800 text-slate-400 hover:text-slate-200' ?>">
                <i data-lucide="user-check" class="w-4 h-4"></i> Phân công Hướng dẫn viên
            </a>
        <?php endif; ?>

        <!-- 3. Quản lý Đơn đặt & Khách hàng (Admin, Sales Staff, Accountant) -->
        <?php if (has_role(['Administrator', 'Sales Staff', 'Accountant'])): ?>
            <a href="<?= BASE_URL ?>modules/admin/booking_manager.php" 
               class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl transition <?= $current_page === 'booking_manager.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-slate-800 text-slate-400 hover:text-slate-200' ?>">
                <i data-lucide="ticket" class="w-4 h-4"></i> Quản lý Đặt tour (Booking)
            </a>
        <?php endif; ?>

        <!-- 4. Duyệt đánh giá (Admin, Sales Staff) -->
        <?php if (has_role(['Administrator', 'Sales Staff'])): ?>
            <a href="<?= BASE_URL ?>modules/reviews/review_manager.php" 
               class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl transition <?= $current_page === 'review_manager.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-slate-800 text-slate-400 hover:text-slate-200' ?>">
                <i data-lucide="message-square" class="w-4 h-4"></i> Kiểm duyệt Đánh giá
            </a>
        <?php endif; ?>

        <!-- 5. Kế toán & Tài chính (Admin, Accountant) -->
        <?php if (has_role(['Administrator', 'Accountant'])): ?>
            <div class="pt-2 pb-1 text-[10px] font-bold uppercase text-slate-500 tracking-wider px-3">Tài chính - Kế toán</div>
            <a href="<?= BASE_URL ?>modules/accounting/invoices_list.php" 
               class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl transition <?= $current_page === 'invoices_list.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-slate-800 text-slate-400 hover:text-slate-200' ?>">
                <i data-lucide="file-text" class="w-4 h-4"></i> Hóa đơn điện tử VAT
            </a>
            <a href="<?= BASE_URL ?>modules/accounting/revenue_report.php" 
               class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl transition <?= $current_page === 'revenue_report.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-slate-800 text-slate-400 hover:text-slate-200' ?>">
                <i data-lucide="receipt" class="w-4 h-4"></i> Báo cáo doanh thu
            </a>
        <?php endif; ?>
    </nav>

    <!-- User Profile & Logout -->
    <div class="p-4 border-t border-slate-800 space-y-2">
        <div class="flex items-center justify-between text-xs">
            <span class="text-slate-400 truncate max-w-[140px] font-medium"><?= htmlspecialchars($u['fullname']) ?></span>
            <a href="<?= BASE_URL ?>modules/auth/logout.php" class="text-rose-400 hover:text-rose-300 font-bold" title="Đăng xuất">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
        <a href="<?= BASE_URL ?>index.php" class="block text-center w-full py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-[11px] font-semibold transition">
            Về Cổng Du Khách
        </a>
    </div>
</aside>