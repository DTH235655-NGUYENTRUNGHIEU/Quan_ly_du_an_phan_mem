<?php
// modules/admin/guide_assign.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// 1. Phân quyền: Chỉ Administrator và Tour Operator được phân công nhân sự dẫn đoàn
require_roles(['Administrator', 'Tour Operator']);

$msg_success = "";
$msg_error = "";

// 2. Xử lý lưu phân công Hướng dẫn viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_guide'])) {
    $dep_id = intval($_POST['departure_id'] ?? 0);
    $guide_id = !empty($_POST['guide_id']) ? intval($_POST['guide_id']) : null;

    if ($dep_id > 0) {
        if ($guide_id !== null) {
            // Lấy khoảng thời gian khởi hành của đợt này
            $stmtCur = $pdo->prepare("SELECT departure_date, return_date FROM tour_departures WHERE departure_id = ?");
            $stmtCur->execute([$dep_id]);
            $curDep = $stmtCur->fetch();

            if ($curDep) {
                // Kiểm tra xung đột thời gian dẫn đoàn với các đợt khác của HDV này
                $sqlCheckConflict = "SELECT dep.departure_id, dep.departure_date, dep.return_date, t.title AS tour_title 
                                     FROM tour_departures dep, tours t 
                                     WHERE dep.tour_id = t.tour_id 
                                       AND dep.tour_guide_id = ? 
                                       AND dep.departure_id != ? 
                                       AND dep.status != 'Đã hủy'
                                       AND dep.departure_date <= ? 
                                       AND dep.return_date >= ?";
                $stmtConflict = $pdo->prepare($sqlCheckConflict);
                $stmtConflict->execute([$guide_id, $dep_id, $curDep['return_date'], $curDep['departure_date']]);
                $conflict = $stmtConflict->fetch();

                if ($conflict) {
                    $msg_error = "Xung đột lịch trình: Hướng dẫn viên này đã có lịch phụ trách tour [<strong>" . htmlspecialchars($conflict['tour_title']) . "</strong>] từ ngày <strong>" . date('d/m/Y', strtotime($conflict['departure_date'])) . "</strong> đến ngày <strong>" . date('d/m/Y', strtotime($conflict['return_date'])) . "</strong>!";
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

// 3. Lấy danh sách nhân viên có vai trò Hướng dẫn viên (roles & users)
$sqlGuides = "SELECT u.user_id, u.fullname, u.phone 
              FROM users u, roles r 
              WHERE u.role_id = r.role_id 
                AND r.role_name = 'Tour Guide' 
                AND u.status = 'Active'
              ORDER BY u.fullname ASC";
$guides = $pdo->query($sqlGuides)->fetchAll();

// 4. Lấy danh sách các đợt khởi hành kèm thông tin tour và HDV phụ trách
$sqlDepartures = "SELECT dep.*, t.title AS tour_title, t.tour_code,
                         (SELECT u.fullname FROM users u WHERE u.user_id = dep.tour_guide_id) AS guide_name,
                         (SELECT u.phone FROM users u WHERE u.user_id = dep.tour_guide_id) AS guide_phone
                  FROM tour_departures dep, tours t 
                  WHERE dep.tour_id = t.tour_id 
                  ORDER BY dep.departure_date ASC";
$departures = $pdo->query($sqlDepartures)->fetchAll();

$page_title = "Phân Công Hướng Dẫn Viên Đoàn - Ban Điều Hành";
include '../../includes/header.php';
?>

<div class="flex min-h-screen bg-slate-100">
    <!-- Nạp Sidebar Admin dùng chung -->
    <?php include '../../includes/admin_sidebar.php'; ?>

    <main class="flex-1 p-8 space-y-6 overflow-y-auto">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Điều Hành Lữ Hành: Phân Công Hướng Dẫn Viên</h1>
                <p class="text-xs text-slate-500 mt-1">Phân bổ nhân sự dẫn đoàn cho các đợt khởi hành và kiểm soát xung đột lịch trình</p>
            </div>
            <a href="<?= BASE_URL ?>modules/admin/tour_manager.php" class="bg-white border text-slate-600 hover:bg-slate-50 text-xs font-semibold px-3.5 py-2 rounded-xl transition shadow-sm">
                Quản lý lịch khởi hành &rarr;
            </a>
        </div>

        <?php if (!empty($msg_success)): ?>
            <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-center gap-2 font-bold">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i> <?= $msg_success ?>
            </div>
        <?php elseif (!empty($msg_error)): ?>
            <div class="p-3.5 bg-rose-50 border border-rose-200 text-rose-800 text-xs rounded-xl flex items-center gap-2 font-bold">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i> <?= $msg_error ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200 font-bold text-xs text-slate-700 uppercase tracking-wide flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <i data-lucide="user-check" class="w-4 h-4 text-blue-600"></i> Danh Sách Đợt Khởi Hành Cần Phân Bổ Nhân Sự (<?= count($departures) ?>)
                </span>
                <span class="text-slate-400 font-normal lowercase">Tổng số HDV sẵn sàng: <?= count($guides) ?> nhân sự</span>
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
                            <th class="p-3.5 text-center">Thao Tác Gán</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($departures)): ?>
                            <tr>
                                <td colspan="7" class="p-6 text-center text-slate-400">Hiện chưa có đợt khởi hành nào trong hệ thống.</td>
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
                                <td class="p-3.5 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold 
                                        <?= $d['status'] === 'Mở bán' ? 'bg-emerald-100 text-emerald-800' : ($d['status'] === 'Đã khóa sổ' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700') ?>">
                                        <?= $d['status'] ?>
                                    </span>
                                </td>
                                <td class="p-3.5">
                                    <?php if (!empty($d['guide_name'])): ?>
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-xs">
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
                                <td class="p-3.5 text-center">
                                    <form method="POST" class="flex items-center justify-center gap-1.5">
                                        <input type="hidden" name="departure_id" value="<?= $d['departure_id'] ?>">
                                        <select name="guide_id" class="border border-slate-300 rounded-lg p-1.5 text-xs focus:ring-1 focus:ring-blue-500">
                                            <option value="">-- Bỏ phân công --</option>
                                            <?php foreach ($guides as $g): ?>
                                                <option value="<?= $g['user_id'] ?>" <?= $d['tour_guide_id'] == $g['user_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($g['fullname']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="assign_guide" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg font-bold transition shadow-sm">
                                            Lưu
                                        </button>
                                    </form>
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