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
    $apartment_id = (int)($_GET['apartment_id'] ?? 0);
    
    if ($apartment_id <= 0) {
        throw new Exception('Невалиден ID на апартамент');
    }
    
    // Вземане на обитателите за апартамента
    $stmt = $pdo->prepare("
        SELECT r.*, 
               CASE 
                   WHEN r.is_owner = 1 THEN CONCAT(r.first_name, ' ', r.last_name, ' (', 
                       CASE r.owner_type
                           WHEN 'individual' THEN 'Физическо лице'
                           WHEN 'company' THEN 'Юридическо лице'
                           WHEN 'inheritance' THEN 'Наследство'
                           ELSE 'Друго'
                       END, ')')
                   ELSE CONCAT(r.first_name, ' ', r.last_name)
               END as full_name
        FROM residents r 
        WHERE r.apartment_id = ? 
        ORDER BY r.is_owner DESC, r.is_primary DESC, r.last_name, r.first_name
    ");
    $stmt->execute([$apartment_id]);
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Връщане на резултата като JSON
    header('Content-Type: application/json');
    echo json_encode($residents);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 