<?php
// modules/tours/index.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';

// 1. Nhận các tham số lọc từ URL
$keyword = trim($_GET['keyword'] ?? '');
$category_id = intval($_GET['category'] ?? 0);
$region = trim($_GET['region'] ?? '');
$price_range = trim($_GET['price'] ?? '');
$sort = trim($_GET['sort'] ?? 'date_asc');

// 2. Lấy danh sách danh mục & vùng miền cho form lọc
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();
$regions = ['Miền Bắc', 'Miền Trung', 'Tây Nguyên', 'Đông Nam Bộ', 'Miền Tây', 'Quốc tế'];

// 3. Xây dựng câu truy vấn lọc danh sách tour
$where = ["t.status = 'Active'", "dep.status = 'Mở bán'", "dep.departure_date >= CURDATE()"];
$params = [];

if (!empty($keyword)) {
    $where[] = "(t.title LIKE ? OR t.tour_code LIKE ? OR d.destination_name LIKE ?)";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
}

if ($category_id > 0) {
    $where[] = "t.category_id = ?";
    $params[] = $category_id;
}

if (!empty($region)) {
    $where[] = "d.region = ?";
    $params[] = $region;
}

if ($price_range === 'under_2m') {
    $where[] = "p.price_adult < 2000000";
} elseif ($price_range === '2m_5m') {
    $where[] = "p.price_adult BETWEEN 2000000 AND 5000000";
} elseif ($price_range === 'over_5m') {
    $where[] = "p.price_adult > 5000000";
}

$where_sql = implode(" AND ", $where);

// Xử lý thứ tự sắp xếp
$order_by = "next_departure_date ASC";
if ($sort === 'price_asc') {
    $order_by = "min_price ASC";
} elseif ($sort === 'price_desc') {
    $order_by = "min_price DESC";
}

$sqlTours = "SELECT t.tour_id, t.tour_code, t.title, t.duration_days, t.duration_nights, t.thumbnail,
                    c.category_name, d.destination_name, d.region,
                    MIN(dep.departure_date) AS next_departure_date,
                    MIN(p.price_adult) AS min_price,
                    SUM(dep.max_seats - dep.booked_seats) AS total_available_seats,
                    (SELECT departure_id FROM tour_departures 
                     WHERE tour_id = t.tour_id AND status = 'Mở bán' AND departure_date >= CURDATE() 
                     ORDER BY departure_date ASC LIMIT 1) AS next_departure_id
             FROM tours t
             JOIN categories c ON t.category_id = c.category_id
             JOIN destinations d ON t.destination_id = d.destination_id
             JOIN tour_departures dep ON t.tour_id = dep.tour_id
             JOIN tour_prices p ON dep.departure_id = p.departure_id
             WHERE {$where_sql}
             GROUP BY t.tour_id
             ORDER BY {$order_by}";

$stmt = $pdo->prepare($sqlTours);
$stmt->execute($params);
$tours = $stmt->fetchAll();

$page_title = "Danh Mục Tour Du Lịch - Du Lịch Việt Nam";
include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1">
    <!-- Tiêu đề trang -->
    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Danh Mục Chương Trình Tour</h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">Khám phá các điểm đến hấp dẫn trên khắp mọi miền tổ quốc</p>
    </div>

    <!-- Bộ lọc nâng cao -->
    <form method="GET" class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 mb-8 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
            <div>
                <label class="block font-bold text-slate-700 mb-1">Từ khóa tìm kiếm:</label>
                <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="Tên tour, điểm đến..." class="w-full border rounded-lg p-2 focus:ring-1 focus:ring-blue-500">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Vùng miền:</label>
                <select name="region" class="w-full border rounded-lg p-2">
                    <option value="">-- Tất cả vùng miền --</option>
                    <?php foreach ($regions as $r): ?>
                        <option value="<?= $r ?>" <?= $region === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Chủ đề / Danh mục:</label>
                <select name="category" class="w-full border rounded-lg p-2">
                    <option value="0">-- Tất cả danh mục --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= $category_id == $cat['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Mức giá người lớn:</label>
                <select name="price" class="w-full border rounded-lg p-2">
                    <option value="">-- Mọi mức giá --</option>
                    <option value="under_2m" <?= $price_range === 'under_2m' ? 'selected' : '' ?>>Dưới 2.000.000 đ</option>
                    <option value="2m_5m" <?= $price_range === '2m_5m' ? 'selected' : '' ?>>2.000.000 đ - 5.000.000 đ</option>
                    <option value="over_5m" <?= $price_range === 'over_5m' ? 'selected' : '' ?>>Trên 5.000.000 đ</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Sắp xếp theo:</label>
                <select name="sort" class="w-full border rounded-lg p-2">
                    <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Khởi hành gần nhất</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Giá tăng dần</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Giá giảm dần</option>
                </select>
            </div>
        </div>

        <div class="flex items-center justify-between border-t border-slate-100 pt-3">
            <span class="text-xs text-slate-500">Tìm thấy <strong class="text-blue-600"><?= count($tours) ?></strong> chương trình tour phù hợp</span>
            <div class="flex gap-2">
                <a href="<?= BASE_URL ?>modules/tours/index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-3 py-1.5 rounded-lg text-xs transition">
                    Xóa bộ lọc
                </a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-1.5 rounded-lg text-xs transition shadow flex items-center gap-1">
                    <i data-lucide="search" class="w-3.5 h-3.5"></i> Tìm kiếm
                </button>
            </div>
        </div>
    </form>

    <!-- Danh sách thẻ Tour -->
    <?php if (empty($tours)): ?>
        <div class="bg-white p-12 rounded-2xl border border-slate-200 text-center space-y-3">
            <i data-lucide="map-pin-off" class="w-10 h-10 text-slate-400 mx-auto"></i>
            <h3 class="text-base font-bold text-slate-800">Không tìm thấy tour phù hợp</h3>
            <p class="text-xs text-slate-500">Hãy thử thay đổi điều kiện lọc vùng miền hoặc khoảng giá tìm kiếm khác.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($tours as $tour): ?>
                <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all border border-slate-100 flex flex-col group">
                    <div class="relative h-48 overflow-hidden">
                        <img src="<?= htmlspecialchars($tour['thumbnail']) ?>" alt="<?= htmlspecialchars($tour['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        <div class="absolute top-3 left-3 bg-slate-900/80 backdrop-blur-md text-white text-[11px] font-semibold px-2.5 py-1 rounded-md">
                            <?= htmlspecialchars($tour['category_name']) ?>
                        </div>
                        <div class="absolute bottom-3 right-3 bg-emerald-600 text-white text-xs font-bold px-2 py-0.5 rounded shadow">
                            Còn <?= $tour['total_available_seats'] ?> chỗ
                        </div>
                    </div>

                    <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                        <div>
                            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1.5">
                                <span class="flex items-center gap-1"><i data-lucide="map-pin" class="w-3.5 h-3.5 text-rose-500"></i> <?= $tour['destination_name'] ?> (<?= $tour['region'] ?>)</span>
                                <span>•</span>
                                <span class="flex items-center gap-1"><i data-lucide="clock" class="w-3.5 h-3.5 text-amber-500"></i> <?= $tour['duration_days'] ?>N<?= $tour['duration_nights'] ?>Đ</span>
                            </div>
                            <a href="<?= BASE_URL ?>modules/tours/detail.php?id=<?= $tour['tour_id'] ?>" class="block font-bold text-slate-900 text-base group-hover:text-blue-600 transition line-clamp-2">
                                <?= htmlspecialchars($tour['title']) ?>
                            </a>
                            <span class="text-[11px] text-slate-500 mt-1 block">
                                Khởi hành gần nhất: <strong class="text-blue-600"><?= date('d/m/Y', strtotime($tour['next_departure_date'])) ?></strong>
                            </span>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                            <div>
                                <span class="text-[11px] text-slate-400 block">Giá từ</span>
                                <span class="text-base sm:text-lg font-extrabold text-rose-600"><?= number_format($tour['min_price'], 0, ',', '.') ?> đ</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <a href="<?= BASE_URL ?>modules/tours/detail.php?id=<?= $tour['tour_id'] ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-xl text-xs font-bold transition">
                                    Chi tiết
                                </a>
                                <a href="<?= BASE_URL ?>modules/booking/booking_step1.php?dep_id=<?= $tour['next_departure_id'] ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-3.5 py-2 rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-1">
                                    Đặt Chỗ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>