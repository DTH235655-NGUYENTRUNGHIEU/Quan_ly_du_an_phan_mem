<?php
// modules/admin/tour_manager.php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// 1. Phân quyền: Chỉ Administrator và Tour Operator được quản lý tour
require_roles(['Administrator', 'Tour Operator']);

$currentUser = current_user();
$msg_success = "";
$msg_error = "";

// Hàm tạo slug thân thiện URL
function create_slug($str) {
    $str = mb_strtolower(trim($str), 'UTF-8');
    $str = preg_replace('/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/u', 'a', $str);
    $str = preg_replace('/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/u', 'e', $str);
    $str = preg_replace('/(ì|í|ị|ỉ|ĩ)/u', 'i', $str);
    $str = preg_replace('/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/u', 'o', $str);
    $str = preg_replace('/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/u', 'u', $str);
    $str = preg_replace('/(ỳ|ý|ỵ|ỷ|ỹ)/u', 'y', $str);
    $str = preg_replace('/(đ)/u', 'd', $str);
    $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
    $str = preg_replace('/[\s-]+/', '-', $str);
    return trim($str, '-');
}

// 2. Xử lý các thao tác Thêm / Sửa / Mở đợt khởi hành
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        // 2.1. Thêm Tour du lịch mới
        if ($_POST['action'] === 'add_tour') {
            $category_id = intval($_POST['category_id']);
            $destination_id = intval($_POST['destination_id']);
            $tour_code = strtoupper(trim($_POST['tour_code']));
            $title = trim($_POST['title']);
            $slug = create_slug($title) . '-' . strtolower($tour_code);
            $duration_days = max(1, intval($_POST['duration_days']));
            $duration_nights = max(0, intval($_POST['duration_nights']));
            $start_location = trim($_POST['start_location']);
            $transportation = trim($_POST['transportation']);
            $thumbnail = trim($_POST['thumbnail']) ?: 'https://images.unsplash.com/photo-1528127269322-539801943592?w=800';
            $included_services = trim($_POST['included_services']);
            $excluded_services = trim($_POST['excluded_services']);

            // Kiểm tra trùng mã tour
            $stmtCheck = $pdo->prepare("SELECT tour_id FROM tours WHERE tour_code = ?");
            $stmtCheck->execute([$tour_code]);
            if ($stmtCheck->fetch()) {
                throw new Exception("Mã tour [{$tour_code}] đã tồn tại trong hệ thống!");
            }

            $stmtAdd = $pdo->prepare("INSERT INTO tours (category_id, destination_id, tour_code, title, slug, duration_days, duration_nights, start_location, transportation, included_services, excluded_services, thumbnail, status) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
            $stmtAdd->execute([$category_id, $destination_id, $tour_code, $title, $slug, $duration_days, $duration_nights, $start_location, $transportation, $included_services, $excluded_services, $thumbnail]);

            $msg_success = "Đã thêm thành công chương trình tour mới: <strong>{$title}</strong>!";
        }

        // 2.2. Mở đợt khởi hành mới & Bảng giá
        elseif ($_POST['action'] === 'add_departure') {
            $tour_id = intval($_POST['tour_id']);
            $departure_date = $_POST['departure_date'];
            $return_date = $_POST['return_date'];
            $max_seats = max(10, intval($_POST['max_seats']));
            $price_adult = floatval($_POST['price_adult']);
            $price_child = floatval($_POST['price_child']);
            $price_infant = floatval($_POST['price_infant'] ?? 0);
            $single_supplement = floatval($_POST['single_supplement'] ?? 0);

            if (strtotime($departure_date) < strtotime(date('Y-m-d'))) {
                throw new Exception("Ngày khởi hành không được nằm trong quá khứ!");
            }
            if (strtotime($return_date) < strtotime($departure_date)) {
                throw new Exception("Ngày kết thúc tour phải sau ngày khởi hành!");
            }

            $pdo->beginTransaction();

            // Thêm đợt khởi hành
            $stmtDep = $pdo->prepare("INSERT INTO tour_departures (tour_id, departure_date, return_date, max_seats, booked_seats, status) 
                                      VALUES (?, ?, ?, ?, 0, 'Mở bán')");
            $stmtDep->execute([$tour_id, $departure_date, $return_date, $max_seats]);
            $departure_id = $pdo->lastInsertId();

            // Thêm bảng giá tương ứng
            $stmtPrice = $pdo->prepare("INSERT INTO tour_prices (departure_id, price_adult, price_child, price_infant, single_supplement) 
                                        VALUES (?, ?, ?, ?, ?)");
            $stmtPrice->execute([$departure_id, $price_adult, $price_child, $price_infant, $single_supplement]);

            $pdo->commit();
            $msg_success = "Đã mở bán thành công đợt khởi hành ngày " . date('d/m/Y', strtotime($departure_date)) . "!";
        }

        // 2.3. Bật / Tắt trạng thái hiển thị Tour (Bảo vệ dữ liệu lịch sử)
        elseif ($_POST['action'] === 'toggle_status') {
            $tour_id = intval($_POST['tour_id']);
            $current_status = $_POST['current_status'];
            $new_status = ($current_status === 'Active') ? 'Hidden' : 'Active';

            $pdo->prepare("UPDATE tours SET status = ? WHERE tour_id = ?")->execute([$new_status, $tour_id]);
            $msg_success = "Đã chuyển trạng thái tour sang: <strong>{$new_status}</strong>!";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $msg_error = $e->getMessage();
    }
}

// 3. Lấy dữ liệu danh mục & điểm đến để nạp vào form chọn
$categories = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC")->fetchAll();
$destinations = $pdo->query("SELECT destination_id, destination_name, region FROM destinations ORDER BY region, destination_name ASC")->fetchAll();

// 4. Lấy danh sách toàn bộ các tour du lịch kèm số lượng đợt khởi hành và số đơn đã đặt
$sqlTours = "SELECT t.*, c.category_name, d.destination_name, d.region,
                    COUNT(DISTINCT dep.departure_id) AS total_departures,
                    COUNT(DISTINCT b.booking_id) AS total_bookings
             FROM tours t
             LEFT JOIN categories c ON t.category_id = c.category_id
             LEFT JOIN destinations d ON t.destination_id = d.destination_id
             LEFT JOIN tour_departures dep ON t.tour_id = dep.tour_id
             LEFT JOIN bookings b ON dep.departure_id = b.departure_id
             GROUP BY t.tour_id
             ORDER BY t.tour_id DESC";
$tours = $pdo->query($sqlTours)->fetchAll();

$page_title = "Quản Lý Chương Trình Tour & Đợt Khởi Hành";
include '../../includes/header.php';
?>

<div class="flex min-h-screen bg-slate-100">
    <!-- Nạp Sidebar Admin dùng chung -->
    <?php include '../../includes/admin_sidebar.php'; ?>

    <main class="flex-1 p-8 space-y-6 overflow-y-auto" x-data="{ showAddTour: false, showAddDep: false, selectedTourId: 0, selectedTourTitle: '' }">
        <!-- Header & Nút hành động nhanh -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Quản Lý Tour & Đợt Khởi Hành</h1>
                <p class="text-xs text-slate-500 mt-1">Điều hành sản phẩm du lịch, thiết lập bảng giá nhiều độ tuổi và lên lịch khởi hành</p>
            </div>
            <button @click="showAddTour = true" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition shadow flex items-center gap-1.5 self-start">
                <i data-lucide="plus-circle" class="w-4 h-4"></i> Thêm Tour Mới
            </button>
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

        <!-- Bảng danh sách toàn bộ Tour -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200 font-bold text-xs text-slate-700 uppercase tracking-wide flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <i data-lucide="map" class="w-4 h-4 text-blue-600"></i> Danh Sách Chương Trình Tour (<?= count($tours) ?>)
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[11px]">
                        <tr>
                            <th class="p-3.5">Ảnh</th>
                            <th class="p-3.5">Mã & Tên Tour</th>
                            <th class="p-3.5">Danh Mục / Điểm Đến</th>
                            <th class="p-3.5">Thời Lượng</th>
                            <th class="p-3.5 text-center">Đợt Mở Bán</th>
                            <th class="p-3.5 text-center">Số Booking</th>
                            <th class="p-3.5 text-center">Trạng Thái</th>
                            <th class="p-3.5 text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($tours as $t): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-3.5">
                                    <img src="<?= htmlspecialchars($t['thumbnail']) ?>" alt="Ảnh" class="w-14 h-10 object-cover rounded-lg border border-slate-200">
                                </td>
                                <td class="p-3.5 max-w-xs">
                                    <span class="font-mono text-[11px] font-bold text-blue-600"><?= $t['tour_code'] ?></span>
                                    <a href="<?= BASE_URL ?>modules/tours/detail.php?id=<?= $t['tour_id'] ?>" target="_blank" class="block font-bold text-slate-800 hover:text-blue-600 line-clamp-2">
                                        <?= htmlspecialchars($t['title']) ?>
                                    </a>
                                </td>
                                <td class="p-3.5">
                                    <span class="block text-slate-700 font-semibold"><?= htmlspecialchars($t['category_name']) ?></span>
                                    <span class="text-slate-400 text-[11px]"><?= htmlspecialchars($t['destination_name']) ?> (<?= $t['region'] ?>)</span>
                                </td>
                                <td class="p-3.5 font-medium">
                                    <?= $t['duration_days'] ?>N<?= $t['duration_nights'] ?>Đ
                                    <span class="block text-slate-400 text-[10px]"><?= htmlspecialchars($t['transportation']) ?></span>
                                </td>
                                <td class="p-3.5 text-center">
                                    <span class="bg-blue-50 text-blue-700 font-bold px-2 py-0.5 rounded-full border border-blue-200">
                                        <?= $t['total_departures'] ?> đợt
                                    </span>
                                </td>
                                <td class="p-3.5 text-center font-bold text-emerald-600">
                                    <?= $t['total_bookings'] ?> đơn
                                </td>
                                <td class="p-3.5 text-center">
                                    <span class="px-2 py-0.5 rounded font-bold text-[10px] <?= $t['status'] === 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>">
                                        <?= $t['status'] === 'Active' ? 'Hiển thị' : 'Đang ẩn' ?>
                                    </span>
                                </td>
                                <td class="p-3.5 text-center space-x-1">
                                    <!-- Nút mở đợt khởi hành mới cho tour này -->
                                    <button @click="showAddDep = true; selectedTourId = <?= $t['tour_id'] ?>; selectedTourTitle = '<?= htmlspecialchars(addslashes($t['title'])) ?>'"
                                            class="inline-block bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white px-2 py-1 rounded text-[11px] font-bold transition" title="Mở ngày khởi hành mới">
                                        + Lịch đi
                                    </button>

                                    <!-- Nút ẩn/hiển thị tour an toàn (không xóa cứng) -->
                                    <form method="POST" class="inline-block" onsubmit="return confirm('Bạn có muốn đổi trạng thái hiển thị của tour này?')">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="tour_id" value="<?= $t['tour_id'] ?>">
                                        <input type="hidden" name="current_status" value="<?= $t['status'] ?>">
                                        <button type="submit" class="inline-block <?= $t['status'] === 'Active' ? 'bg-rose-50 text-rose-600 hover:bg-rose-600' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-600' ?> hover:text-white px-2 py-1 rounded text-[11px] font-bold transition">
                                            <?= $t['status'] === 'Active' ? 'Ẩn' : 'Hiện' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL 1: Form Thêm Tour Mới (Sử dụng Alpine.js) -->
        <div x-show="showAddTour" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h2 class="text-base font-bold text-slate-800 flex items-center gap-1.5">
                        <i data-lucide="plus-circle" class="w-5 h-5 text-blue-600"></i> Khởi Tạo Tour Du Lịch Mới
                    </h2>
                    <button @click="showAddTour = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                </div>

                <form method="POST" class="space-y-4 text-xs">
                    <input type="hidden" name="action" value="add_tour">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold mb-1 text-slate-700">Mã định danh Tour (*):</label>
                            <input type="text" name="tour_code" required placeholder="TOUR-PQ-04" class="w-full border rounded-lg p-2 font-mono uppercase">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1 text-slate-700">Danh mục Tour (*):</label>
                            <select name="category_id" required class="w-full border rounded-lg p-2">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Tên chương trình Tour (*):</label>
                        <input type="text" name="title" required placeholder="Phú Quốc - Câu Cá Lặn Ngắm San Hô - Cáp Treo Hòn Thơm" class="w-full border rounded-lg p-2">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold mb-1 text-slate-700">Điểm đến chính (*):</label>
                            <select name="destination_id" required class="w-full border rounded-lg p-2">
                                <?php foreach ($destinations as $dest): ?>
                                    <option value="<?= $dest['destination_id'] ?>"><?= htmlspecialchars($dest['destination_name']) ?> (<?= $dest['region'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1 text-slate-700">Số ngày (*):</label>
                            <input type="number" name="duration_days" value="3" min="1" required class="w-full border rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1 text-slate-700">Số đêm (*):</label>
                            <input type="number" name="duration_nights" value="2" min="0" required class="w-full border rounded-lg p-2">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold mb-1 text-slate-700">Điểm khởi hành / Đón khách:</label>
                            <input type="text" name="start_location" value="Long Xuyên, An Giang" required class="w-full border rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1 text-slate-700">Phương tiện vận chuyển:</label>
                            <input type="text" name="transportation" value="Xe du lịch đời mới / Máy bay" required class="w-full border rounded-lg p-2">
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Link ảnh đại diện (Thumbnail URL):</label>
                        <input type="url" name="thumbnail" placeholder="https://images.unsplash.com/..." class="w-full border rounded-lg p-2">
                    </div>

                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Dịch vụ bao gồm:</label>
                        <textarea name="included_services" rows="2" class="w-full border rounded-lg p-2" placeholder="Khách sạn 3 sao, các bữa ăn theo lịch trình, vé tham quan, bảo hiểm lữ hành 50tr/vụ..."></textarea>
                    </div>

                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Dịch vụ không bao gồm:</label>
                        <textarea name="excluded_services" rows="2" class="w-full border rounded-lg p-2" placeholder="Chi phí cá nhân ngoài chương trình, tiền tip hướng dẫn viên, VAT..."></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button type="button" @click="showAddTour = false" class="px-4 py-2 border rounded-lg hover:bg-slate-50 font-semibold">Hủy</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold">Lưu Tour Mới</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 2: Form Mở Đợt Khởi Hành Mới & Bảng Giá -->
        <div x-show="showAddDep" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h2 class="text-base font-bold text-slate-800 flex items-center gap-1.5">
                        <i data-lucide="calendar-plus" class="w-5 h-5 text-emerald-600"></i> Mở Đợt Khởi Hành Mới
                    </h2>
                    <button @click="showAddDep = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                </div>

                <form method="POST" class="space-y-4 text-xs">
                    <input type="hidden" name="action" value="add_departure">
                    <input type="hidden" name="tour_id" :value="selectedTourId">

                    <div class="p-3 bg-blue-50 text-blue-900 rounded-xl font-medium">
                        Tour: <strong x-text="selectedTourTitle"></strong>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold mb-1 text-slate-700">Ngày khởi hành (*):</label>
                            <input type="date" name="departure_date" required class="w-full border rounded-lg p-2">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1 text-slate-700">Ngày về (*):</label>
                            <input type="date" name="return_date" required class="w-full border rounded-lg p-2">
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Số chỗ mở bán tối đa (*):</label>
                        <input type="number" name="max_seats" value="30" min="5" required class="w-full border rounded-lg p-2">
                    </div>

                    <div class="p-3 bg-slate-50 border rounded-xl space-y-3">
                        <span class="font-bold text-slate-700 block uppercase text-[11px]">Bảng giá áp dụng cho đợt này:</span>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold mb-1 text-slate-600">Vé Người lớn (đ) (*):</label>
                                <input type="number" name="price_adult" required placeholder="1850000" class="w-full border rounded-lg p-2 font-bold text-rose-600">
                            </div>
                            <div>
                                <label class="block font-semibold mb-1 text-slate-600">Vé Trẻ em 5-11t (đ) (*):</label>
                                <input type="number" name="price_child" required placeholder="1290000" class="w-full border rounded-lg p-2">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold mb-1 text-slate-600">Vé Em bé &lt;5t (đ):</label>
                                <input type="number" name="price_infant" value="0" class="w-full border rounded-lg p-2">
                            </div>
                            <div>
                                <label class="block font-semibold mb-1 text-slate-600">Phụ thu phòng đơn (đ):</label>
                                <input type="number" name="single_supplement" value="450000" class="w-full border rounded-lg p-2">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button type="button" @click="showAddDep = false" class="px-4 py-2 border rounded-lg hover:bg-slate-50 font-semibold">Hủy</button>
                        <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold">Mở Bán Đợt Này</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script>lucide.createIcons();</script>
</body>
</html>