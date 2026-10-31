<?php
// includes/navbar.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/auth.php';
$user = current_user();

// Đếm số chuyến đi sắp tới được phân công cho Hướng dẫn viên (role_name = 'Tour Guide')
$assigned_count = 0;
if ($user && isset($user['role_name']) && $user['role_name'] === 'Tour Guide' && isset($user['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    if (isset($pdo)) {
        $stmtCount = $pdo->prepare("
            SELECT COUNT(*) 
            FROM tour_departures 
            WHERE tour_guide_id = :guide_id 
              AND status IN ('Mở bán', 'Sắp hết chỗ', 'Đã khóa sổ', 'Đã khởi hành')
        ");
        $stmtCount->execute(['guide_id' => $user['user_id']]);
        $assigned_count = (int)$stmtCount->fetchColumn();
    }
}
?>
<nav class="bg-slate-900 text-white sticky top-0 z-50 shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo thương hiệu -->
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white shadow-lg shadow-blue-500/30">
                    <i data-lucide="compass" class="w-6 h-6"></i>
                </div>
                <div>
                    <a href="<?= BASE_URL ?>index.php" class="font-bold text-lg tracking-tight">DU LỊCH VIỆT NAM</a>
                    <p class="text-[10px] text-blue-400 font-medium leading-none">Hệ thống lữ hành & Quản lý booking</p>
                </div>
            </div>
            
            <!-- Menu liên kết chính -->
            <div class="hidden md:flex items-center space-x-6 text-sm font-medium">
                <a href="<?= BASE_URL ?>index.php" class="hover:text-blue-400 transition">Trang chủ</a>
                <a href="<?= BASE_URL ?>modules/tours/index.php" class="hover:text-blue-400 transition">Danh mục Tour</a>

                <?php if ($user && $user['role_name'] === 'Tour Guide'): ?>
                    <!-- Hiển thị riêng cho Hướng dẫn viên -->
                    <a href="<?= BASE_URL ?>modules/admin/guide_assign.php" 
                       class="flex items-center gap-1.5 text-blue-400 hover:text-blue-300 font-semibold transition">
                        <i data-lucide="calendar-check" class="w-4 h-4"></i>
                        <span>Lịch phân công của tôi</span>
                        <?php if ($assigned_count > 0): ?>
                            <span class="bg-rose-500 text-white text-[10px] px-1.5 py-0.5 rounded-full font-bold ml-0.5 animate-pulse">
                                <?= $assigned_count ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php elseif ($user && in_array($user['role_name'], ['Administrator', 'Tour Operator'])): ?>
                    <!-- Hiển thị cho Quản trị viên và Điều hành tour -->
                    <a href="<?= BASE_URL ?>modules/admin/guide_assign.php" 
                       class="flex items-center gap-1.5 hover:text-blue-400 transition">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                        <span>Phân công HDV</span>
                    </a>
                <?php else: ?>
                    <!-- Hiển thị cho Khách hàng hoặc khách chưa đăng nhập -->
                    <a href="<?= BASE_URL ?>modules/booking/my_bookings.php" class="hover:text-blue-400 transition">Chuyến đi của tôi</a>
                <?php endif; ?>

                <a href="#" class="hover:text-blue-400 transition">Liên hệ</a>
            </div>

            <!-- Khu vực Tài khoản & Phân quyền -->
            <div class="flex items-center space-x-3 text-xs">
                <?php if ($user): ?>
                    <!-- Nếu là nhân viên nội bộ -->
                    <?php if (in_array($user['role_name'], ['Administrator', 'Sales Staff', 'Tour Operator', 'Accountant', 'Tour Guide'])): ?>
                        <?php 
                            // Hướng dẫn viên được dẫn thẳng đến trang Lịch phân công để xem việc ngay
                            $portal_url = ($user['role_name'] === 'Tour Guide') 
                                ? BASE_URL . 'modules/admin/guide_assign.php' 
                                : BASE_URL . 'modules/admin/dashboard.php';
                        ?>
                        <a href="<?= $portal_url ?>" 
                           class="flex items-center space-x-1.5 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg font-semibold transition shadow">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                            <span>Cổng Quản Trị (<?= htmlspecialchars($user['role_name']) ?>)</span>
                        </a>
                    <?php else: ?>
                        <!-- Nếu là Khách hàng -->
                        <span class="text-slate-300 hidden sm:inline">
                            Xin chào, <strong class="text-white"><?= htmlspecialchars($user['fullname']) ?></strong>
                            <span class="bg-amber-500/20 text-amber-300 text-[10px] px-2 py-0.5 rounded-full font-bold ml-1">
                                <?= $user['reward_points'] ?> điểm
                            </span>
                        </span>
                    <?php endif; ?>

                    <!-- Nút Đăng xuất -->
                    <a href="<?= BASE_URL ?>modules/auth/logout.php" 
                       class="flex items-center gap-1 bg-slate-800 hover:bg-rose-600 text-slate-300 hover:text-white px-3 py-1.5 rounded-lg border border-slate-700 transition">
                        <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                        <span>Thoát</span>
                    </a>

                <?php else: ?>
                    <!-- Chưa đăng nhập -->
                    <a href="<?= BASE_URL ?>modules/auth/login.php" 
                       class="text-slate-300 hover:text-white px-3 py-1.5 rounded-lg transition font-medium">
                        Đăng nhập
                    </a>
                    <a href="<?= BASE_URL ?>modules/auth/register.php" 
                       class="bg-blue-600 hover:bg-blue-700 text-white px-3.5 py-1.5 rounded-lg font-semibold transition shadow shadow-blue-500/20">
                        Đăng ký
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>