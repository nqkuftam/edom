<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Проверка дали потребителят е логнат
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Неоторизиран достъп']);
    exit;
}

try {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('Невалиден ID на запис');
    }

    $stmt = $pdo->prepare("
        SELECT 
            rh.*,
            a.number as apartment_number,
            a.type as apartment_type
        FROM resident_history rh
        JOIN apartments a ON rh.apartment_id = a.id
        WHERE rh.id = ?
    ");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new Exception('Записът не е намерен');
    }

    header('Content-Type: application/json');
    echo json_encode($record);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 