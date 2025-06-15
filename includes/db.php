<?php
// Конфигурация за връзка с базата данни
$db_host = '23.95.246.156';
$db_name = 'edomoupravitel';
$db_user = 'edomoupravitel_user';
$db_pass = 'E$L2NEN9uk';

try {
    // Създаване на PDO връзка
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            // Добавяне на таймаут за връзката
            PDO::ATTR_TIMEOUT => 5
        ]
    );
} catch (PDOException $e) {
    // При грешка при връзката
    die("Грешка при връзка с базата данни: " . $e->getMessage());
} 