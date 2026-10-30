<?php
// modules/tours/detail.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
$tour_id = intval($_GET['id'] ?? 1);

// 1. Lấy thông tin tổng quan tour
$stmt = $pdo->prepare("SELECT t.*, c.category_name, d.destination_name 
                       FROM tours t 
                       JOIN categories c ON t.category_id = c.category_id 
                       JOIN destinations d ON t.destination_id = d.destination_id 
                       WHERE t.tour_id = ?");
$stmt->execute([$tour_id]);
$tour = $stmt->fetch();

if (!$tour) {
    die("Không tìm thấy tour du lịch yêu cầu!");
}

// 2. Lấy lịch trình từng ngày (tour_itineraries)
$stmtItin = $pdo->prepare("SELECT * FROM tour_itineraries WHERE tour_id = ? ORDER BY day_number ASC");
$stmtItin->execute([$tour_id]);
$itineraries = $stmtItin->fetchAll();

// 3. Lấy các đợt khởi hành và bảng giá kèm theo (tour_departures & tour_prices)
$stmtDep = $pdo->prepare("SELECT dep.*, p.price_adult, p.price_child, p.price_infant, p.single_supplement,
                                 (dep.max_seats - dep.booked_seats) AS available_seats
                          FROM tour_departures dep
                          JOIN tour_prices p ON dep.departure_id = p.departure_id
                          WHERE dep.tour_id = ? AND dep.status = 'Mở bán' AND dep.departure_date >= CURDATE()
                          ORDER BY dep.departure_date ASC");
$stmtDep->execute([$tour_id]);
$departures = $stmtDep->fetchAll();

// 4. Lấy đánh giá của khách hàng (reviews & customers)
$stmtRev = $pdo->prepare("SELECT r.*, c.fullname 
                          FROM reviews r 
                          JOIN customers c ON r.customer_id = c.customer_id 
                          WHERE r.tour_id = ? AND r.status = 'Đã duyệt' 
                          ORDER BY r.review_id DESC");
$stmtRev->execute([$tour_id]);
$reviews = $stmtRev->fetchAll();

$page_title = $tour['title'] . " - Chi tiết Tour";
include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-xs text-slate-400 mb-6 font-medium">
        <a href="<?= BASE_URL ?>index.php" class="hover:text-blue-600">Trang chủ</a>
        <span>/</span>
        <a href="<?= BASE_URL ?>modules/tours/index.php" class="hover:text-blue-600"><?= htmlspecialchars($tour['category_name']) ?></a>
        <span>/</span>
        <span class="text-slate-800 font-bold line-clamp-1"><?= htmlspecialchars($tour['title']) ?></span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Cột trái: Thông tin & Lịch trình -->
        <div class="lg:col-span-2 space-y-8">
            <div>
                <span class="inline-block bg-blue-100 text-blue-700 text-xs font-bold px-3 py-1 rounded-md mb-2">
                    Mã Tour: <?= $tour['tour_code'] ?>
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 leading-tight">
                    <?= htmlspecialchars($tour['title']) ?>
                </h1>
                <div class="flex flex-wrap items-center gap-4 mt-3 text-xs text-slate-500 font-medium">
                    <span class="flex items-center gap-1.5"><i data-lucide="map-pin" class="w-4 h-4 text-rose-500"></i> <?= $tour['destination_name'] ?></span>
                    <span class="flex items-center gap-1.5"><i data-lucide="clock" class="w-4 h-4 text-amber-500"></i> <?= $tour['duration_days'] ?> Ngày <?= $tour['duration_nights'] ?> Đêm</span>
                    <span class="flex items-center gap-1.5"><i data-lucide="bus" class="w-4 h-4 text-emerald-500"></i> <?= $tour['transportation'] ?></span>
                </div>
            </div>

            <!-- Ảnh đại diện lớn -->
            <div class="rounded-2xl overflow-hidden shadow-lg border border-slate-200 h-96">
                <img src="<?= htmlspecialchars($tour['thumbnail']) ?>" class="w-full h-full object-cover" alt="Ảnh Tour">
            </div>

            <!-- Lịch trình chi tiết từng ngày (Bảng tour_itineraries) -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-6">
                <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                    <i data-lucide="calendar" class="w-5 h-5 text-blue-600"></i> Lịch Trình Tour Chi Tiết Từng Ngày
                </h2>

                <div class="space-y-6">
                    <?php if (empty($itineraries)): ?>
                        <p class="text-sm text-slate-400">Lịch trình đang được cập nhật...</p>
                    <?php endif; ?>
                    <?php foreach ($itineraries as $itin): ?>
                        <div class="relative pl-6 border-l-2 border-blue-500 space-y-2">
                            <div class="absolute -left-2 top-0 w-4 h-4 bg-blue-600 rounded-full border-2 border-white shadow"></div>
                            <span class="text-xs font-bold text-blue-600 uppercase tracking-wide">Ngày <?= $itin['day_number'] ?></span>
                            <h3 class="font-bold text-base text-slate-800"><?= htmlspecialchars($itin['day_title']) ?></h3>
                            <p class="text-sm text-slate-600 leading-relaxed"><?= nl2br(htmlspecialchars($itin['description'])) ?></p>
                            <div class="flex flex-wrap gap-4 text-xs text-slate-500 pt-1">
                                <span class="bg-slate-100 px-2.5 py-1 rounded">🍽 Ăn uống: <strong><?= $itin['meals'] ?></strong></span>
                                <span class="bg-slate-100 px-2.5 py-1 rounded">🏨 Khách sạn: <strong><?= $itin['accommodation'] ?></strong></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Đánh giá khách hàng (Bảng reviews) -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-4">
                <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                    <i data-lucide="message-circle" class="w-5 h-5 text-amber-500"></i> Đánh Giá Của Du Khách
                </h2>
                <?php if (empty($reviews)): ?>
                    <p class="text-sm text-slate-400">Chưa có đánh giá nào cho tour này.</p>
                <?php endif; ?>
                <?php foreach ($reviews as $rev): ?>
                    <div class="border-b border-slate-100 pb-3 last:border-0">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-sm text-slate-800"><?= htmlspecialchars($rev['fullname']) ?></span>
                            <span class="text-xs text-amber-500 font-bold"><?= str_repeat('★', $rev['rating']) ?></span>
                        </div>
                        <p class="text-xs text-slate-600 mt-1"><?= htmlspecialchars($rev['comment']) ?></p>
                        <span class="text-[10px] text-slate-400"><?= date('d/m/Y', strtotime($rev['review_date'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Cột phải: Chọn đợt khởi hành & Đặt tour (tour_departures & tour_prices) -->
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-2xl shadow-xl border border-slate-200 sticky top-24 space-y-5">
                <h3 class="font-bold text-base text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                    <i data-lucide="ticket-check" class="w-5 h-5 text-emerald-600"></i> Các Đợt Khởi Hành
                </h3>

                <?php if (empty($departures)): ?>
                    <div class="p-4 bg-amber-50 text-amber-800 rounded-xl text-xs font-semibold">
                        Hiện tour này tạm thời chưa có lịch khởi hành mới.
                    </div>
                <?php endif; ?>

                <div class="space-y-3">
                    <?php foreach ($departures as $dep): ?>
                        <div class="p-3.5 rounded-xl border border-slate-200 hover:border-blue-500 transition space-y-2 bg-slate-50/50">
                            <div class="flex justify-between items-center text-xs">
                                <span class="font-bold text-slate-800">
                                    <i data-lucide="calendar-days" class="w-3.5 h-3.5 inline text-blue-600"></i> <?= date('d/m/Y', strtotime($dep['departure_date'])) ?>
                                </span>
                                <span class="text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded font-bold text-[10px]">
                                    Còn <?= $dep['available_seats'] ?> chỗ
                                </span>
                            </div>

                            <div class="text-xs space-y-1 text-slate-500 border-t border-slate-200/60 pt-2">
                                <div class="flex justify-between">
                                    <span>Vé người lớn:</span>
                                    <strong class="text-rose-600"><?= number_format($dep['price_adult'], 0, ',', '.') ?> đ</strong>
                                </div>
                                <div class="flex justify-between">
                                    <span>Vé trẻ em (5-11t):</span>
                                    <span><?= number_format($dep['price_child'], 0, ',', '.') ?> đ</span>
                                </div>
                            </div>

                            <a href="<?= BASE_URL ?>modules/booking/booking_step1.php?dep_id=<?= $dep['departure_id'] ?>" 
                               class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded-lg text-xs flex items-center justify-center gap-1.5 transition mt-2 shadow">
                                <i data-lucide="check-circle" class="w-4 h-4"></i> Chọn Đợt Khởi Hành Này
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>  
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>