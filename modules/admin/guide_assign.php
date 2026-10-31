<?php
// modules/admin/guide_assign.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// 1. Phân quyền: Cho phép Quản trị viên, Điều hành tour và Hướng dẫn viên truy cập[cite: 2]
require_roles(['Administrator', 'Tour Operator', 'Tour Guide']);

$u = current_user();
$is_guide = ($u['role_name'] === 'Tour Guide');
$is_manager = in_array($u['role_name'], ['Administrator', 'Tour Operator']);

$msg_success = "";
$msg_error = "";

// 2. Xử lý lưu phân công Hướng dẫn viên (Chỉ Admin và Điều hành tour)[cite: 2]
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_guide']) && $is_manager) {
    $dep_id = intval($_POST['departure_id'] ?? 0);
    $guide_id = !empty($_POST['guide_id']) ? intval($_POST['guide_id']) : null;

    if ($dep_id > 0) {
        if ($guide_id !== null) {
            // Lấy khoảng thời gian khởi hành của đợt này
            $stmtCur = $pdo->prepare("SELECT departure_date, return_date FROM tour_departures WHERE departure_id = ?");
            $stmtCur->execute([$dep_id]);
            $curDep = $stmtCur->fetch();

            if ($curDep) {
                // Kiểm tra xung đột thời gian dẫn đoàn của HDV với các chuyến khác (Không dùng JOIN)[cite: 2]
                $sqlCheckConflict = "SELECT dep.departure_id, dep.departure_date, dep.return_date, t.title AS tour_title 
                                     FROM tour_departures dep, tours t 
                                     WHERE dep.tour_id = t.tour_id 
                                       AND dep.tour_guide_id = ? 
                                       AND dep.departure_id != ? 
                                       AND dep.status NOT IN ('Đã hủy', 'Đã hoàn tất')
                                       AND dep.departure_date <= ? 
                                       AND dep.return_date >= ?";
                $stmtConflict = $pdo->prepare($sqlCheckConflict);
                $stmtConflict->execute([$guide_id, $dep_id, $curDep['return_date'], $curDep['departure_date']]);
                $conflict = $stmtConflict->fetch();

                if ($conflict) {
                    $msg_error = "Xung đột lịch trình: Hướng dẫn viên này đã có lịch phụ trách tour [<strong>" . htmlspecialchars($conflict['tour_title']) . "</strong>] từ ngày <strong>" . date('d/m/Y', strtotime($conflict['departure_date'])) . "</strong> đến <strong>" . date('d/m/Y', strtotime($conflict['return_date'])) . "</strong>!";
                } else {
                    $stmt = $pdo->prepare("UPDATE tour_departures SET tour_guide_id = ? WHERE departure_id = ?");
                    $stmt->execute([$guide_id, $dep_id]);
                    $msg_success = "Đã phân công Hướng dẫn viên thành công cho đợt khởi hành #DEP-{$dep_id}!";
                }
            }
        } else {
            // Hủy phân công hướng dẫn viên cho đợt này
            $stmt = $pdo->prepare("UPDATE tour_departures SET tour_guide_id = NULL WHERE departure_id = ?");
            $stmt->execute([$dep_id]);
            $msg_success = "Đã hủy phân công Hướng dẫn viên cho đợt khởi hành #DEP-{$dep_id}!";
        }
    }
}

// 3. Xử lý cập nhật trạng thái chuyến đi (Hướng dẫn viên cập nhật chuyến của mình, Quản lý cập nhật mọi chuyến)[cite: 2]
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $dep_id = intval($_POST['departure_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    $allowed_statuses = ['Mở bán', 'Sắp hết chỗ', 'Đã khóa sổ', 'Đã khởi hành', 'Đã hoàn tất', 'Đã hủy'];

    if ($dep_id > 0 && in_array($new_status, $allowed_statuses, true)) {
        if ($is_guide) {
            $stmt = $pdo->prepare("UPDATE tour_departures SET status = ? WHERE departure_id = ? AND tour_guide_id = ?");
            $stmt->execute([$new_status, $dep_id, $u['user_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE tour_departures SET status = ? WHERE departure_id = ?");
            $stmt->execute([$new_status, $dep_id]);
        }
        $msg_success = "Đã cập nhật trạng thái đợt khởi hành #DEP-{$dep_id} sang [<strong>{$new_status}</strong>]!";
    }
}

// 4. Lấy danh sách nhân viên có vai trò Hướng dẫn viên (Dành cho Quản lý chọn dropdown - Không dùng JOIN)[cite: 2]
$guides = [];
if ($is_manager) {
    $sqlGuides = "SELECT u.user_id, u.fullname, u.phone 
                  FROM users u, roles r 
                  WHERE u.role_id = r.role_id 
                    AND r.role_name = 'Tour Guide' 
                    AND u.status = 'Active'
                  ORDER BY u.fullname ASC";
    $guides = $pdo->query($sqlGuides)->fetchAll();
}

// 5. Lấy danh sách các đợt khởi hành (Nếu là HDV thì chỉ lấy các tour do mình phụ trách)[cite: 2]
if ($is_guide) {
    $sqlDepartures = "SELECT dep.*, t.title AS tour_title, t.tour_code, t.start_location, t.transportation,
                             (SELECT u.fullname FROM users u WHERE u.user_id = dep.tour_guide_id) AS guide_name,
                             (SELECT u.phone FROM users u WHERE u.user_id = dep.tour_guide_id) AS guide_phone
                      FROM tour_departures dep, tours t 
                      WHERE dep.tour_id = t.tour_id 
                        AND dep.tour_guide_id = ?
                      ORDER BY dep.departure_date ASC";
    $stmtDep = $pdo->prepare($sqlDepartures);
    $stmtDep->execute([$u['user_id']]);
    $departures = $stmtDep->fetchAll();
} else {
    $sqlDepartures = "SELECT dep.*, t.title AS tour_title, t.tour_code, t.start_location, t.transportation,
                             (SELECT u.fullname FROM users u WHERE u.user_id = dep.tour_guide_id) AS guide_name,
                             (SELECT u.phone FROM users u WHERE u.user_id = dep.tour_guide_id) AS guide_phone
                      FROM tour_departures dep, tours t 
                      WHERE dep.tour_id = t.tour_id 
                      ORDER BY dep.departure_date ASC";
    $departures = $pdo->query($sqlDepartures)->fetchAll();
}

// 6. Xử lý xem Danh sách hành khách trong đoàn để điểm danh (Không dùng JOIN)[cite: 2]
$view_dep_id = intval($_GET['view_passengers'] ?? 0);
$passengers = [];
$dep_info = null;

if ($view_dep_id > 0) {
    $stmtDepInfo = $pdo->prepare("SELECT dep.*, t.title AS tour_title, t.tour_code, t.start_location, t.transportation 
                                  FROM tour_departures dep, tours t 
                                  WHERE dep.tour_id = t.tour_id AND dep.departure_id = ?");
    $stmtDepInfo->execute([$view_dep_id]);
    $dep_info = $stmtDepInfo->fetch();

    if ($dep_info) {
        // Kiểm tra quyền: nếu là HDV thì chỉ được xem danh sách khách của tour mình được giao[cite: 2]
        if ($is_guide && (int)$dep_info['tour_guide_id'] !== (int)$u['user_id']) {
            $msg_error = "Bạn không có quyền truy cập danh sách đoàn của chuyến đi này!";
            $dep_info = null;
        } else {
            $sqlPax = "SELECT bp.*, b.booking_code, b.special_notes, c.fullname AS booker_name, c.phone AS booker_phone
                       FROM booking_passengers bp, bookings b, customers c
                       WHERE bp.booking_id = b.booking_id
                         AND b.customer_id = c.customer_id
                         AND b.departure_id = ?
                         AND b.booking_status != 'Đã hủy'
                       ORDER BY b.booking_code ASC, bp.passenger_id ASC";
            $stmtPax = $pdo->prepare($sqlPax);
            $stmtPax->execute([$view_dep_id]);
            $passengers = $stmtPax->fetchAll();
        }
    }
}

$page_title = $is_guide ? "Lịch Phân Công Dẫn Đoàn Của Tôi" : "Điều Hành & Phân Công Hướng Dẫn Viên";
include '../../includes/header.php';
?>

<div class="flex min-h-screen bg-slate-100">
    <!-- Nạp Sidebar Admin dùng chung -->
    <?php include '../../includes/admin_sidebar.php'; ?>

    <main class="flex-1 p-8 space-y-6 overflow-y-auto">
        <!-- Tiêu đề trang -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">
                    <?= $is_guide ? 'Lịch Phân Công Dẫn Đoàn Của Tôi' : 'Điều Hành Lữ Hành: Phân Công Hướng Dẫn Viên' ?>
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    <?= $is_guide ? 'Theo dõi các tour du lịch được bàn giao phụ trách, cập nhật tiến độ và quản lý danh sách đoàn' : 'Phân bổ nhân sự dẫn đoàn cho các đợt khởi hành và kiểm soát xung đột lịch trình' ?>
                </p>
            </div>
            <?php if ($is_manager): ?>
                <a href="<?= BASE_URL ?>modules/admin/tour_manager.php" class="bg-white border text-slate-600 hover:bg-slate-50 text-xs font-semibold px-3.5 py-2 rounded-xl transition shadow-sm inline-flex items-center gap-1.5">
                    <i data-lucide="map" class="w-4 h-4"></i> Quản lý lịch khởi hành &rarr;
                </a>
            <?php endif; ?>
        </div>

        <!-- Thông báo -->
        <?php if (!empty($msg_success)): ?>
            <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-center gap-2 font-bold shadow-sm">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i> <?= $msg_success ?>
            </div>
        <?php elseif (!empty($msg_error)): ?>
            <div class="p-3.5 bg-rose-50 border border-rose-200 text-rose-800 text-xs rounded-xl flex items-center gap-2 font-bold shadow-sm">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i> <?= $msg_error ?>
            </div>
        <?php endif; ?>

        <!-- KHU VỰC DANH SÁCH ĐOÀN KHÁCH (Khi chọn xem chuyến đi cụ thể)[cite: 2] -->
        <?php if ($dep_info): ?>
            <div id="manifest" class="bg-white rounded-2xl shadow-md border-2 border-blue-500 overflow-hidden transition-all">
                <div class="p-4 bg-blue-600 text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="bg-white/20 text-white text-[10px] font-extrabold px-2 py-0.5 rounded uppercase">Danh Sách Đoàn Khách Đi Tour</span>
                            <span class="font-mono text-xs font-bold text-blue-100">#DEP-<?= $dep_info['departure_id'] ?> (<?= $dep_info['tour_code'] ?>)</span>
                        </div>
                        <h2 class="text-base font-bold mt-1"><?= htmlspecialchars($dep_info['tour_title']) ?></h2>
                        <p class="text-xs text-blue-100 mt-0.5">
                            Khởi hành: <strong><?= date('d/m/Y', strtotime($dep_info['departure_date'])) ?></strong> 
                            &rarr; Về: <strong><?= date('d/m/Y', strtotime($dep_info['return_date'])) ?></strong> | 
                            Điểm tập trung: <strong><?= htmlspecialchars($dep_info['start_location']) ?></strong>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="window.print()" class="bg-white hover:bg-slate-100 text-blue-700 px-3 py-1.5 rounded-lg text-xs font-bold transition shadow-sm inline-flex items-center gap-1.5">
                            <i data-lucide="printer" class="w-3.5 h-3.5"></i> In danh sách
                        </button>
                        <a href="<?= BASE_URL ?>modules/admin/guide_assign.php" class="bg-blue-700 hover:bg-blue-800 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition inline-flex items-center gap-1">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i> Đóng
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[11px] border-b border-slate-200">
                            <tr>
                                <th class="p-3 text-center">STT</th>
                                <th class="p-3">Mã Booking</th>
                                <th class="p-3">Họ Tên Hành Khách</th>
                                <th class="p-3">Giới Tính</th>
                                <th class="p-3">Ngày Sinh</th>
                                <th class="p-3">Loại Khách</th>
                                <th class="p-3">CCCD / Hộ Chiếu</th>
                                <th class="p-3 text-center">Phòng Đơn</th>
                                <th class="p-3">Người Đặt & Liên Hệ</th>
                                <th class="p-3">Ghi Chú Đặc Biệt</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($passengers)): ?>
                                <tr>
                                    <td colspan="10" class="p-6 text-center text-slate-400">Chưa có hành khách nào đăng ký trong chuyến đi này.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($passengers as $idx => $pax): ?>
                                    <tr class="hover:bg-blue-50/50 transition">
                                        <td class="p-3 text-center font-bold text-slate-400"><?= $idx + 1 ?></td>
                                        <td class="p-3 font-mono font-bold text-blue-600"><?= $pax['booking_code'] ?></td>
                                        <td class="p-3 font-bold text-slate-800 text-[13px]"><?= htmlspecialchars($pax['fullname']) ?></td>
                                        <td class="p-3"><?= $pax['gender'] ?></td>
                                        <td class="p-3 whitespace-nowrap"><?= $pax['birth_date'] ? date('d/m/Y', strtotime($pax['birth_date'])) : '—' ?></td>
                                        <td class="p-3">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold 
                                                <?= $pax['passenger_type'] === 'Người lớn' ? 'bg-slate-100 text-slate-700' : ($pax['passenger_type'] === 'Trẻ em' ? 'bg-amber-100 text-amber-800' : 'bg-pink-100 text-pink-700') ?>">
                                                <?= $pax['passenger_type'] ?>
                                            </span>
                                        </td>
                                        <td class="p-3 font-mono font-semibold text-slate-700"><?= $pax['id_card'] ?: '<span class="text-slate-400 italic">Chưa cấp</span>' ?></td>
                                        <td class="p-3 text-center">
                                            <?= $pax['single_room'] ? '<span class="text-amber-600 font-bold bg-amber-50 px-2 py-0.5 rounded border border-amber-200">1 người/phòng</span>' : '<span class="text-slate-400">Ở ghép</span>' ?>
                                        </td>
                                        <td class="p-3">
                                            <strong class="text-slate-800 block"><?= htmlspecialchars($pax['booker_name']) ?></strong>
                                            <span class="text-blue-600 font-mono"><?= $pax['booker_phone'] ?></span>
                                        </td>
                                        <td class="p-3 max-w-xs text-rose-600 font-medium italic">
                                            <?= htmlspecialchars($pax['special_notes'] ?? '') ?: '—' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- BẢNG LỊCH PHÂN CÔNG KHỞI HÀNH[cite: 2] -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200 font-bold text-xs text-slate-700 uppercase tracking-wide flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <i data-lucide="calendar-check" class="w-4 h-4 text-blue-600"></i> 
                    <?= $is_guide ? "Các Chuyến Đi Bạn Được Phụ Trách (" . count($departures) . ")" : "Danh Sách Đợt Khởi Hành Cần Phân Bổ Nhân Sự (" . count($departures) . ")" ?>
                </span>
                <?php if ($is_manager): ?>
                    <span class="text-slate-400 font-normal lowercase">Tổng số HDV sẵn sàng: <?= count($guides) ?> nhân sự</span>
                <?php endif; ?>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[11px]">
                        <tr>
                            <th class="p-3.5">Mã Đợt</th>
                            <th class="p-3.5">Tên Tour Khởi Hành</th>
                            <th class="p-3.5">Ngày Đi - Về</th>
                            <th class="p-3.5 text-center">Chỗ Đã Đặt</th>
                            <th class="p-3.5 text-center">Trạng Thái</th>
                            <th class="p-3.5">Hướng Dẫn Viên Phụ Trách</th>
                            <th class="p-3.5 text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($departures)): ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <i data-lucide="calendar-x" class="w-8 h-8 text-slate-300"></i>
                                        <span><?= $is_guide ? 'Bạn hiện chưa có lịch phân công dẫn đoàn nào sắp tới.' : 'Hiện chưa có đợt khởi hành nào trong hệ thống.' ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($departures as $d): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-3.5 font-bold font-mono text-blue-600">#DEP-<?= $d['departure_id'] ?></td>
                                <td class="p-3.5 max-w-xs">
                                    <span class="font-bold text-slate-800 block line-clamp-1"><?= htmlspecialchars($d['tour_title']) ?></span>
                                    <span class="text-slate-400 font-mono text-[11px]">Mã tour: <?= $d['tour_code'] ?></span>
                                </td>
                                <td class="p-3.5 whitespace-nowrap">
                                    <strong class="text-slate-800"><?= date('d/m/Y', strtotime($d['departure_date'])) ?></strong> 
                                    <span class="text-slate-400">&rarr;</span> 
                                    <strong class="text-slate-800"><?= date('d/m/Y', strtotime($d['return_date'])) ?></strong>
                                </td>
                                <td class="p-3.5 text-center font-bold text-emerald-600">
                                    <?= $d['booked_seats'] ?> / <?= $d['max_seats'] ?>
                                </td>
                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <?php if ($is_guide || $is_manager): ?>
                                        <!-- Form cập nhật nhanh trạng thái chuyến đi[cite: 2] -->
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="departure_id" value="<?= $d['departure_id'] ?>">
                                            <select name="status" onchange="this.form.submit()" 
                                                    class="border rounded px-2 py-1 text-[11px] font-bold focus:ring-1 focus:ring-blue-500 cursor-pointer
                                                        <?= $d['status'] === 'Mở bán' ? 'bg-emerald-50 text-emerald-700 border-emerald-300' : 
                                                           ($d['status'] === 'Đang đi tour' || $d['status'] === 'Đã khởi hành' ? 'bg-blue-50 text-blue-700 border-blue-300' : 
                                                           ($d['status'] === 'Đã hoàn tất' ? 'bg-slate-100 text-slate-600 border-slate-300' : 'bg-amber-50 text-amber-700 border-amber-300')) ?>">
                                                <option value="Mở bán" <?= $d['status'] === 'Mở bán' ? 'selected' : '' ?>>Mở bán</option>
                                                <option value="Sắp hết chỗ" <?= $d['status'] === 'Sắp hết chỗ' ? 'selected' : '' ?>>Sắp hết chỗ</option>
                                                <option value="Đã khóa sổ" <?= $d['status'] === 'Đã khóa sổ' ? 'selected' : '' ?>>Đã khóa sổ</option>
                                                <option value="Đã khởi hành" <?= $d['status'] === 'Đã khởi hành' ? 'selected' : '' ?>>Đã khởi hành</option>
                                                <option value="Đã hoàn tất" <?= $d['status'] === 'Đã hoàn tất' ? 'selected' : '' ?>>Đã hoàn tất</option>
                                                <option value="Đã hủy" <?= $d['status'] === 'Đã hủy' ? 'selected' : '' ?>>Đã hủy</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 text-slate-700">
                                            <?= $d['status'] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5">
                                    <?php if (!empty($d['guide_name'])): ?>
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-xs shrink-0">
                                                <?= mb_substr($d['guide_name'], 0, 1) ?>
                                            </div>
                                            <div>
                                                <strong class="text-blue-700 block"><?= htmlspecialchars($d['guide_name']) ?></strong>
                                                <span class="text-slate-400 text-[10px]"><?= $d['guide_phone'] ?: 'Chưa có SĐT' ?></span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-rose-500 bg-rose-50 border border-rose-200 px-2.5 py-1 rounded-md font-semibold text-[11px] inline-flex items-center gap-1">
                                            <i data-lucide="user-x" class="w-3.5 h-3.5"></i> Chưa phân công
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <!-- Nút xem danh sách hành khách đoàn để điểm danh[cite: 2] -->
                                        <a href="<?= BASE_URL ?>modules/admin/guide_assign.php?view_passengers=<?= $d['departure_id'] ?>#manifest" 
                                           class="bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-700 border border-slate-200 px-2.5 py-1.5 rounded-lg font-bold text-[11px] transition shadow-sm inline-flex items-center gap-1">
                                            <i data-lucide="users" class="w-3.5 h-3.5 text-blue-600"></i> Danh sách đoàn
                                        </a>

                                        <?php if ($is_manager): ?>
                                            <!-- Form phân công HDV của Quản trị / Điều hành[cite: 2] -->
                                            <form method="POST" class="flex items-center gap-1">
                                                <input type="hidden" name="departure_id" value="<?= $d['departure_id'] ?>">
                                                <select name="guide_id" class="border border-slate-300 rounded-lg p-1.5 text-xs focus:ring-1 focus:ring-blue-500 max-w-[130px]">
                                                    <option value="">-- Bỏ phân công --</option>
                                                    <?php foreach ($guides as $g): ?>
                                                        <option value="<?= $g['user_id'] ?>" <?= $d['tour_guide_id'] == $g['user_id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($g['fullname']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="assign_guide" class="bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1.5 rounded-lg font-bold transition shadow-sm text-xs">
                                                    Lưu
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>