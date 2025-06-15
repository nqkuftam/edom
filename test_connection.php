<?php
require_once 'includes/db.php';

try {
    // Тестова заявка
    $stmt = $pdo->query("SELECT 1");
    echo "Връзката с базата данни е успешна!";
} catch (PDOException $e) {
    echo "Грешка при връзка с базата данни: " . $e->getMessage();
} 