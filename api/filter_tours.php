<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$keyword        = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$category_id    = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$destination_id = isset($_GET['destination_id']) ? (int)$_GET['destination_id'] : 0;
$region         = isset($_GET['region']) ? trim($_GET['region']) : '';
$max_price      = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;

try {
    $params = [];
    $sql = "SELECT 
                t.tour_id, t.tour_code, t.title, t.slug, t.duration_days, t.duration_nights,
                t.start_location, t.transportation, t.thumbnail,
                c.category_name, d.destination_name, d.region,
                MIN(tp.price_adult) AS min_price,
                MIN(td.departure_date) AS next_departure,
                COALESCE(ROUND(AVG(r.rating), 1), 5.0) AS avg_rating
            FROM tours t
            INNER JOIN categories c ON t.category_id = c.category_id
            INNER JOIN destinations d ON t.destination_id = d.destination_id
            LEFT JOIN tour_departures td ON t.tour_id = td.tour_id AND td.status = 'Mở bán'
            LEFT JOIN tour_prices tp ON td.departure_id = tp.departure_id
            LEFT JOIN reviews r ON t.tour_id = r.tour_id AND r.status = 'Đã duyệt'
            WHERE t.status = 'Active'";

    if ($keyword !== '') {
        $sql .= " AND (t.title LIKE :kw OR t.tour_code LIKE :kw OR d.destination_name LIKE :kw)";
        $params[':kw'] = '%' . $keyword . '%';
    }
    if ($category_id > 0) {
        $sql .= " AND t.category_id = :cat_id";
        $params[':cat_id'] = $category_id;
    }
    if ($destination_id > 0) {
        $sql .= " AND t.destination_id = :dest_id";
        $params[':dest_id'] = $destination_id;
    }
    if ($region !== '') {
        $sql .= " AND d.region = :region";
        $params[':region'] = $region;
    }

    $sql .= " GROUP BY t.tour_id";

    if ($max_price > 0) {
        $sql .= " HAVING MIN(tp.price_adult) <= :max_price";
        $params[':max_price'] = $max_price;
    }

    $sql .= " ORDER BY t.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count'   => count($tours),
        'data'    => $tours
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể tải dữ liệu lọc tour.'
    ]);
}