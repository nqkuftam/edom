<?php
// Функция за проверка дали потребителят е логнат
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Функция за проверка на логин
function login($username, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            return true;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Login Error: " . $e->getMessage());
        return false;
    }
}

// Функция за изход
function logout() {
    session_destroy();
    session_start();
}

// Функция за промяна на парола
function changePassword($userId, $currentPassword, $newPassword) {
    global $pdo;
    
    try {
        // Проверка на текущата парола
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return false;
        }
        
        // Промяна на паролата
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashedPassword, $userId]);
    } catch (PDOException $e) {
        error_log("Password Change Error: " . $e->getMessage());
        return false;
    }
} 