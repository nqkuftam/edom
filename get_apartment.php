<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db.php';
require_once 'includes/auth.php';

// Проверка дали потребителят е логнат
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Проверка за ID параметър
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing ID parameter']);
    exit;
}

$id = (int)$_GET['id'];

try {
    // Взимане на данните за имота
    $stmt = $pdo->prepare("
        SELECT a.*, b.name as building_name,
               CONCAT(r.first_name, ' ', r.last_name) as owner_name,
               r.phone as owner_phone,
               r.email as owner_email
        FROM apartments a
        LEFT JOIN buildings b ON a.building_id = b.id
        LEFT JOIN residents r ON a.id = r.apartment_id AND r.is_owner = 1 AND r.is_primary = 1
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $apartment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$apartment) {
        http_response_code(404);
        echo json_encode(['error' => 'Apartment not found']);
        exit;
    }

    // Връщане на данните в JSON формат
    header('Content-Type: application/json');
    echo json_encode($apartment);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 