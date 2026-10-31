<?php
require_once __DIR__ . '/../../includes/auth.php';

require_roles(['Administrator', 'Tour Operator']);

// Xử lý phân công Hướng dẫn viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_guide'])) {
    $dep_id = intval($_POST['departure_id']);
    $guide_id = intval($_POST['guide_id']);

    $stmt = $pdo->prepare("UPDATE tour_departures SET tour_guide_id = ? WHERE departure_id = ?");
    $stmt->execute([$guide_id, $dep_id]);
    $msg = "Đã phân công Hướng dẫn viên thành công!";
}

// 1. Lấy danh sách nhân viên có vai trò là Hướng dẫn viên (roles & users)
$sqlGuides = "SELECT u.user_id, u.fullname, u.phone 
              FROM users u 
              JOIN roles r ON u.role_id = r.role_id 
              WHERE r.role_name = 'Tour Guide' AND u.status = 'Active'";
$guides = $pdo->query($sqlGuides)->fetchAll();

// 2. Lấy danh sách các đợt khởi hành sắp tới
$sqlDepartures = "SELECT dep.*, t.title AS tour_title, u.fullname AS guide_name 
                  FROM tour_departures dep 
                  JOIN tours t ON dep.tour_id = t.tour_id 
                  LEFT JOIN users u ON dep.tour_guide_id = u.user_id 
                  ORDER BY dep.departure_date ASC";
$departures = $pdo->query($sqlDepartures)->fetchAll();

$page_title = "Phân Công Hướng Dẫn Viên Đoàn - Ban Điều Hành";
include '../../includes/header.php';
?>

<div class="flex min-h-screen bg-slate-100">
    <?php include '../../includes/admin_sidebar.php'; ?>

    <div class="flex-1 p-8 space-y-6">
        <div class="border-b pb-4">
            <h1 class="text-2xl font-black text-slate-900">Điều Hành Lữ Hành: Phân Công Hướng Dẫn Viên</h1>
            <p class="text-xs text-slate-500">Quản lý phân bổ nhân sự (bảng users, roles) cho các đợt khởi hành (bảng tour_departures)</p>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="p-3 bg-emerald-100 text-emerald-800 rounded-lg text-xs font-bold"><?= $msg ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[11px]">
                    <tr>
                        <th class="p-3.5">Mã Đợt</th>
                        <th class="p-3.5">Tên Tour Khởi Hành</th>
                        <th class="p-3.5">Ngày Đi - Về</th>
                        <th class="p-3.5">Chỗ Đã Đặt</th>
                        <th class="p-3.5">Hướng Dẫn Viên Phụ Trách</th>
                        <th class="p-3.5 text-center">Thao Tác Gán</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($departures as $d): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="p-3.5 font-bold">#DEP-<?= $d['departure_id'] ?></td>
                            <td class="p-3.5 font-bold text-slate-800"><?= htmlspecialchars($d['tour_title']) ?></td>
                            <td class="p-3.5"><?= date('d/m/Y', strtotime($d['departure_date'])) ?> - <?= date('d/m/Y', strtotime($d['return_date'])) ?></td>
                            <td class="p-3.5 font-bold text-emerald-600"><?= $d['booked_seats'] ?> / <?= $d['max_seats'] ?></td>
                            <td class="p-3.5">
                                <span class="badge font-bold <?= $d['guide_name'] ? 'text-blue-600 bg-blue-50 px-2 py-1 rounded' : 'text-slate-400' ?>">
                                    <?= $d['guide_name'] ?: 'Chưa phân công' ?>
                                </span>
                            </td>
                            <td class="p-3.5 text-center">
                                <form method="POST" class="flex items-center justify-center gap-1.5">
                                    <input type="hidden" name="departure_id" value="<?= $d['departure_id'] ?>">
                                    <select name="guide_id" class="border rounded p-1 text-xs">
                                        <?php foreach ($guides as $g): ?>
                                            <option value="<?= $g['user_id'] ?>" <?= $d['tour_guide_id'] == $g['user_id'] ? 'selected' : '' ?>>
                                                <?= $g['fullname'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="assign_guide" class="bg-blue-600 text-white px-2.5 py-1 rounded font-bold hover:bg-blue-700">Lưu</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>