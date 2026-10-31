<?php
// index.php
require_once 'config/constants.php';
require_once 'config/database.php';
$page_title = "Công ty Du lịch Việt Nam - Khám phá vẻ đẹp bất tận";
include 'includes/header.php';
include 'includes/navbar.php';

// Truy vấn danh sách tour đang mở bán (gom nhóm theo tour để không bị lặp thẻ khi có nhiều đợt khởi hành)
$sql = "SELECT t.tour_id, t.title, t.duration_days, t.duration_nights, t.thumbnail,
               c.category_name, d.destination_name,
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
        WHERE t.status = 'Active' AND dep.status = 'Mở bán' AND dep.departure_date >= CURDATE()
        GROUP BY t.tour_id
        ORDER BY next_departure_date ASC LIMIT 6";
$tours = $pdo->query($sql)->fetchAll();
?>

<!-- Hero Banner -->
<div class="relative bg-slate-900 py-20 px-4 sm:px-6 lg:px-8 text-white overflow-hidden">
    <div class="absolute inset-0 opacity-30 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1528127269322-539801943592?w=1600');"></div>
    <div class="relative max-w-4xl mx-auto text-center space-y-4">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-500/20 text-blue-300 border border-blue-500/30">
            <i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Đồ án môn học SEE508 - ĐH An Giang
        </span>
        <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight">Khám Phá Hành Trình Di Sản Việt Nam</h1>
        <p class="text-slate-300 text-sm max-w-2xl mx-auto">Hệ thống đặt tour trực tuyến tiêu chuẩn, lưu trữ thông tin hành khách bảo hiểm lữ hành và xuất hóa đơn điện tử VAT tự động.</p>
    </div>
</div>

<!-- Tour Container -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 flex-1">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Các Tour Đang Mở Đăng Ký</h2>
            <p class="text-xs sm:text-sm text-slate-500">Lịch trình chi tiết, khởi hành định kỳ đúng cam kết chất lượng</p>
        </div>
        <a href="<?= BASE_URL ?>modules/tours/index.php" class="text-xs sm:text-sm font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1">
            Xem tất cả tour <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </a>
    </div>

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
                            <span class="flex items-center gap-1"><i data-lucide="map-pin" class="w-3.5 h-3.5 text-rose-500"></i> <?= $tour['destination_name'] ?></span>
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
</div>

<?php include 'includes/footer.php'; ?>