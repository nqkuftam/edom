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
if (!isset($_SESSION['user_id']) || !isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Неоторизиран достъп']);
    exit();
}

try {
    $property_id = (int)($_GET['property_id'] ?? 0);
    
    if ($property_id <= 0) {
        throw new Exception('Невалиден ID на имот');
    }
    
    // Вземане на обитателите за имота
    $stmt = $pdo->prepare("
        SELECT * FROM residents 
        WHERE property_id = ? 
        ORDER BY FIELD(status, 'owner', 'tenant', 'user'), last_name, first_name
    ");
    $stmt->execute([$property_id]);
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Връщане на резултата като JSON
    header('Content-Type: application/json');
    echo json_encode($residents);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 