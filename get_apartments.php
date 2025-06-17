<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!isset($_GET['building_id'])) {
    echo json_encode([]);
    exit;
}

$building_id = (int)$_GET['building_id'];

try {
    $stmt = $pdo->prepare("
        SELECT a.*, b.name AS building_name, ab.balance
        FROM apartments a
        JOIN buildings b ON a.building_id = b.id
        LEFT JOIN apartment_balances ab ON a.id = ab.apartment_id
        WHERE a.building_id = ?
        ORDER BY a.number
    ");
    $stmt->execute([$building_id]);
    $apartments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($apartments);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error']);
} 