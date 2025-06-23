<?php
// Функция за форматиране на сума
function formatAmount($amount) {
    return number_format($amount, 2, '.', ' ') . ' лв.';
}

// Функция за форматиране на дата
function formatDate($date) {
    return date('d.m.Y', strtotime($date));
}

// Функция за валидация на дата
function isValidDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// Функция за извличане на месец и година от дата
function getMonthYear($date) {
    return [
        'month' => date('m', strtotime($date)),
        'year' => date('Y', strtotime($date))
    ];
}

// Функция за проверка на задължения (нов ledger вариант)
function checkDebt($propertyId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM property_ledger WHERE property_id = ?");
        $stmt->execute([$propertyId]);
        $balance = $stmt->fetchColumn();
        return $balance ?: 0;
    } catch (PDOException $e) {
        error_log("Debt Check Error: " . $e->getMessage());
        return false;
    }
}

// Функция за генериране на уникален номер на плащане
function generatePaymentNumber() {
    return date('Ymd') . rand(1000, 9999);
}

// Функция за проверка на права
function hasPermission($permission) {
    // В този случай имаме само един потребител (администратор)
    // с пълни права, така че винаги връща true
    return true;
}

// Функция за логване на действия
function logAction($action, $details = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, details, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $details
        ]);
    } catch (PDOException $e) {
        error_log("Log Action Error: " . $e->getMessage());
        return false;
    }
}

// Функция за изчистване на XSS
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Функция за валидация на email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Функция за проверка на телефонен номер
function isValidPhone($phone) {
    return preg_match('/^[0-9+\-\s()]{8,15}$/', $phone);
} 