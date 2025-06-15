<?php
// Функция за показване на грешка
function showError($message, $type = 'danger') {
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
        ' . htmlspecialchars($message) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

// Функция за показване на успех
function showSuccess($message) {
    return showError($message, 'success');
}

// Функция за показване на предупреждение
function showWarning($message) {
    return showError($message, 'warning');
}

// Функция за показване на информация
function showInfo($message) {
    return showError($message, 'info');
}

// Функция за обработка на PDO грешки
function handlePDOError($e) {
    return showError('Грешка в базата данни: ' . $e->getMessage());
}

// Функция за обработка на общи грешки
function handleError($e) {
    $errorMessage = 'Възникна грешка: ' . $e->getMessage();
    return showError($errorMessage);
} 