<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Проверка дали потребителят е логнат
if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized');
}

// Добавяне на CORS хедъри
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Content-Type: application/json; charset=utf-8');

// Проверка за apartment_id параметър
if (!isset($_GET['apartment_id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit(json_encode(['error' => 'Missing apartment_id parameter']));
}

$apartment_id = (int)$_GET['apartment_id'];

// Проверка дали апартаментът съществува
$stmt = $pdo->prepare("SELECT id FROM apartments WHERE id = ?");
$stmt->execute([$apartment_id]);
if (!$stmt->fetch()) {
    header('HTTP/1.1 404 Not Found');
    exit(json_encode(['error' => 'Apartment not found']));
}

// Вземане на неплатените такси за избрания апартамент
$stmt = $pdo->prepare("
    SELECT 
        f.*,
        FORMAT(f.amount, 2) as formatted_amount
    FROM fees f 
    WHERE f.apartment_id = ? 
    AND f.id NOT IN (SELECT fee_id FROM payments)
    ORDER BY f.created_at DESC
");
$stmt->execute([$apartment_id]);
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Връщане на резултата като JSON
echo json_encode($fees, JSON_UNESCAPED_UNICODE); 