<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['property_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Не е посочен имот']);
    exit;
}

$property_id = (int)$_GET['property_id'];

try {
    $stmt = $pdo->prepare("
        SELECT fa.id, f.description, fa.amount, f.type
        FROM fee_properties fa
        JOIN fees f ON fa.fee_id = f.id
        WHERE fa.property_id = ? AND fa.is_paid = 0
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$property_id]);
    $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($fees);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Възникна грешка при зареждането на таксите']);
} 