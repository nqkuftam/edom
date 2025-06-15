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
    $errorMessage = 'Грешка в базата данни: ';
    
    // Добавяме специфично съобщение според кода на грешката
    switch ($e->getCode()) {
        case '23000': // Нарушение на уникален ключ
            $errorMessage .= 'Вече съществува запис с тези данни.';
            break;
        case '42S02': // Липсваща таблица
            $errorMessage .= 'Липсваща таблица в базата данни.';
            break;
        case '42S22': // Липсваща колона
            $errorMessage .= 'Липсваща колона в таблицата.';
            break;
        case 'HY000': // Обща грешка
            $errorMessage .= 'Възникна проблем при работа с базата данни.';
            break;
        default:
            $errorMessage .= $e->getMessage();
    }
    
    return showError($errorMessage);
}

// Функция за обработка на общи грешки
function handleError($e) {
    $errorMessage = 'Възникна грешка: ' . $e->getMessage();
    return showError($errorMessage);
} 