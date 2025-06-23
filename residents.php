<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/building_selector.php';
require_once 'includes/error_handler.php';
require_once 'includes/navigation.php';

// Проверка дали потребителят е логнат
if (!isset($_SESSION['user_id']) || !isLoggedIn()) {
    $_SESSION['error'] = 'Моля, влезте в системата за да продължите.';
    header('Location: login.php');
    exit();
}
// require_once 'includes/styles.php';

$error = '';
$success = '';

// Вземане на избрания имот от GET параметър
$selectedPropertyId = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;

try {
    // Вземане на текущата сграда
    $currentBuilding = getCurrentBuilding();
    
    // Обработка на POST заявки
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_resident':
                    $property_id = (int)($_POST['property_id'] ?? 0);
                    $first_name = $_POST['first_name'] ?? '';
                    $middle_name = $_POST['middle_name'] ?? '';
                    $last_name = $_POST['last_name'] ?? '';
                    $phone = $_POST['phone'] ?? '';
                    $email = $_POST['email'] ?? '';
                    $status = $_POST['status'] ?? 'user';
                    $move_in_date = $_POST['move_in_date'] ?? '';
                    $move_out_date = $_POST['move_out_date'] ?? '';
                    $notes = $_POST['notes'] ?? '';
                    
                    if ($property_id > 0 && !empty($first_name) && !empty($last_name) && !empty($move_in_date)) {
                        $stmt = $pdo->prepare("INSERT INTO residents (property_id, first_name, middle_name, last_name, phone, email, status, move_in_date, move_out_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$property_id, $first_name, $middle_name, $last_name, $phone, $email, $status, $move_in_date, $move_out_date ?: null, $notes]);
                        $success = showSuccess('Обитателят е добавен успешно.');
                        header('Location: residents.php');
                        exit();
                    } else {
                        $error = showError('Моля, попълнете всички задължителни полета.');
                    }
                    break;
                    
                case 'edit_resident':
                    $id = (int)($_POST['id'] ?? 0);
                    $property_id = (int)($_POST['property_id'] ?? 0);
                    $first_name = $_POST['first_name'] ?? '';
                    $middle_name = $_POST['middle_name'] ?? '';
                    $last_name = $_POST['last_name'] ?? '';
                    $phone = $_POST['phone'] ?? '';
                    $email = $_POST['email'] ?? '';
                    $status = $_POST['status'] ?? 'user';
                    $move_in_date = $_POST['move_in_date'] ?? '';
                    $move_out_date = $_POST['move_out_date'] ?? '';
                    $notes = $_POST['notes'] ?? '';
                    
                    if ($id > 0 && $property_id > 0 && !empty($first_name) && !empty($last_name) && !empty($move_in_date)) {
                        $stmt = $pdo->prepare("UPDATE residents SET property_id = ?, first_name = ?, middle_name = ?, last_name = ?, phone = ?, email = ?, status = ?, move_in_date = ?, move_out_date = ?, notes = ? WHERE id = ?");
                        $stmt->execute([$property_id, $first_name, $middle_name, $last_name, $phone, $email, $status, $move_in_date, $move_out_date ?: null, $notes, $id]);
                        $success = showSuccess('Обитателят е редактиран успешно.');
                        header('Location: residents.php');
                        exit();
                    } else {
                        $error = showError('Моля, попълнете всички задължителни полета.');
                    }
                    break;
                    
                case 'delete_resident':
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id > 0) {
                        $stmt = $pdo->prepare("DELETE FROM residents WHERE id = ?");
                        $stmt->execute([$id]);
                        $success = showSuccess('Обитателят е изтрит успешно.');
                        header('Location: residents.php');
                        exit();
                    }
                    break;
            }
        }
    }
    
    // Вземане на всички имоти за падащото меню
    $query = "SELECT a.*, b.name as building_name 
              FROM properties a 
              JOIN buildings b ON a.building_id = b.id";
    $params = [];
    
    if ($currentBuilding) {
        $query .= " WHERE a.building_id = ?";
        $params[] = $currentBuilding['id'];
    }
    
    $query .= " ORDER BY b.name, a.number";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Вземане на обитателите според избрания имот
    $query = "
        SELECT r.*, a.number as property_number, b.name as building_name 
        FROM residents r 
        JOIN properties a ON r.property_id = a.id 
        JOIN buildings b ON a.building_id = b.id 
    ";
    $params = [];
    
    if ($currentBuilding) {
        $query .= " WHERE b.id = ?";
        $params[] = $currentBuilding['id'];
    }
    
    // Ако е избран имот, добавяме филтър
    if ($selectedPropertyId > 0) {
        $query .= ($currentBuilding ? " AND" : " WHERE") . " a.id = ?";
        $params[] = $selectedPropertyId;
    }
    
    $query .= " ORDER BY b.name, a.number, r.last_name, r.first_name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = handlePDOError($e);
} catch (Exception $e) {
    $error = handleError($e);
}

// Функция за показване на статус с подходящ цвят
function getStatusBadge($status) {
    switch ($status) {
        case 'owner':
            return '<span class="badge bg-primary">Собственик</span>';
        case 'tenant':
            return '<span class="badge bg-success">Наемател</span>';
        case 'resident':
            return '<span class="badge bg-info">Обитател</span>';
        case 'user':
            return '<span class="badge bg-secondary">Ползвател</span>';
        default:
            return '<span class="badge bg-light text-dark">' . htmlspecialchars($status) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Обитатели | Електронен Домоуправител</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once 'includes/styles.php'; ?>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Обитатели</h1>
            <?php echo renderNavigation('residents'); ?>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Назад към таблото</a>
        
        <?php echo renderBuildingSelector(); ?>
        
        <!-- Форма за избор на имот -->
        <form method="GET" class="mb-3" style="max-width:400px;">
            <div class="input-group">
                <label class="input-group-text" for="property_id"><i class="fas fa-home"></i> Избери имот</label>
                <select class="form-select" id="property_id" name="property_id" onchange="this.form.submit()">
                    <option value="0">-- Всички имоти --</option>
                    <?php foreach ($properties as $property): ?>
                        <option value="<?php echo $property['id']; ?>" <?php if ($selectedPropertyId == $property['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($property['building_name'] . ' - Имот ' . $property['number']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        
        <?php if ($error): ?>
            <?php echo $error; ?>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <?php echo $success; ?>
        <?php endif; ?>
        
        <button class="btn btn-primary mb-3" onclick="showAddModal()">
            <i class="fas fa-plus"></i> Добави нов обитател
        </button>
        
        <div class="grid">
            <?php foreach ($residents as $resident): ?>
            <div class="card">
                <h3><i class="fas fa-user"></i> <?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></h3>
                <p><strong><i class="fas fa-home"></i> Имот:</strong> <?php
                    // Вземи типа на имота от properties
                    $propertyType = '';
                    foreach ($properties as $p) {
                        if ($p['id'] == $resident['property_id']) {
                            $propertyType = $p['type'] ?? '';
                            break;
                        }
                    }
                    $typeShort = [
                        'apartment' => 'Ап.',
                        'garage' => 'Гараж',
                        'room' => 'Стая',
                        'office' => 'Офис',
                        'shop' => 'Магазин',
                        'warehouse' => 'Склад',
                        'parking' => 'Паркомясто'
                    ];
                    echo ($typeShort[$propertyType] ?? $propertyType) . ' ' . htmlspecialchars($resident['property_number']);
                ?></p>
                <?php if ($resident['phone']): ?>
                <p><strong><i class="fas fa-phone"></i> Телефон:</strong> <?php echo htmlspecialchars($resident['phone']); ?></p>
                <?php endif; ?>
                <?php if ($resident['email']): ?>
                <p><strong><i class="fas fa-envelope"></i> Имейл:</strong> <?php echo htmlspecialchars($resident['email']); ?></p>
                <?php endif; ?>
                <p><strong><i class="fas fa-key"></i> Статус:</strong> <?php echo getStatusBadge($resident['status']); ?></p>
                <p><strong><i class="fas fa-calendar-alt"></i> Настаняване:</strong> <?php echo date('d.m.Y', strtotime($resident['move_in_date'])); ?></p>
                <?php if ($resident['move_out_date']): ?>
                <p><strong><i class="fas fa-calendar-times"></i> Напуснал на:</strong> <?php echo date('d.m.Y', strtotime($resident['move_out_date'])); ?></p>
                <?php endif; ?>
                <?php if ($resident['notes']): ?>
                <p><strong><i class="fas fa-sticky-note"></i> Бележки:</strong> <?php echo nl2br(htmlspecialchars($resident['notes'])); ?></p>
                <?php endif; ?>
                <div class="payment-actions">
                    <button class="btn btn-warning" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($resident)); ?>)">
                        <i class="fas fa-edit"></i> Редактирай
                    </button>
                    <button class="btn btn-danger" onclick="deleteResident(<?php echo $resident['id']; ?>)">
                        <i class="fas fa-trash"></i> Изтрий
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Модален прозорец за добавяне -->
    <div id="addModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Добави нов обитател</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_resident">
                        <div class="form-group">
                            <label for="property_id" class="form-label">Имот:</label>
                            <select class="form-control" id="property_id" name="property_id" required>
                                <option value="">Изберете имот</option>
                                <?php foreach ($properties as $property): ?>
                                <option value="<?php echo $property['id']; ?>">
                                    <?php echo htmlspecialchars($property['building_name'] . ' - Имот ' . $property['number']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="first_name" class="form-label">Име:</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="middle_name" class="form-label">Презиме:</label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name">
                        </div>
                        <div class="form-group">
                            <label for="last_name" class="form-label">Фамилия:</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                        <div class="form-group">
                            <label for="phone" class="form-label">Телефон:</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Имейл:</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="form-group">
                            <label for="status" class="form-label">Статус:</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="user">Ползвател</option>
                                <option value="resident">Обитател</option>
                                <option value="tenant">Наемател</option>
                                <option value="owner">Собственик</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="move_in_date" class="form-label">Дата на настаняване:</label>
                            <input type="date" class="form-control" id="move_in_date" name="move_in_date" required>
                        </div>
                        <div class="form-group">
                            <label for="move_out_date" class="form-label">Дата на напускане:</label>
                            <input type="date" class="form-control" id="move_out_date" name="move_out_date">
                        </div>
                        <div class="form-group">
                            <label for="notes" class="form-label">Бележки:</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                            <button type="submit" class="btn btn-primary">Добави</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Модален прозорец за редактиране -->
    <div id="editModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Редактирай обитател</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_resident">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="form-group">
                            <label for="edit_property_id" class="form-label">Имот:</label>
                            <select class="form-control" id="edit_property_id" name="property_id" required>
                                <option value="">Изберете имот</option>
                                <?php foreach ($properties as $property): ?>
                                <option value="<?php echo $property['id']; ?>">
                                    <?php echo htmlspecialchars($property['building_name'] . ' - Имот ' . $property['number']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_first_name" class="form-label">Име:</label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_middle_name" class="form-label">Презиме:</label>
                            <input type="text" class="form-control" id="edit_middle_name" name="middle_name">
                        </div>
                        <div class="form-group">
                            <label for="edit_last_name" class="form-label">Фамилия:</label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_phone" class="form-label">Телефон:</label>
                            <input type="tel" class="form-control" id="edit_phone" name="phone">
                        </div>
                        <div class="form-group">
                            <label for="edit_email" class="form-label">Имейл:</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        <div class="form-group">
                            <label for="edit_status" class="form-label">Статус:</label>
                            <select class="form-control" id="edit_status" name="status" required>
                                <option value="user">Ползвател</option>
                                <option value="resident">Обитател</option>
                                <option value="tenant">Наемател</option>
                                <option value="owner">Собственик</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_move_in_date" class="form-label">Дата на настаняване:</label>
                            <input type="date" class="form-control" id="edit_move_in_date" name="move_in_date" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_move_out_date" class="form-label">Дата на напускане:</label>
                            <input type="date" class="form-control" id="edit_move_out_date" name="move_out_date">
                        </div>
                        <div class="form-group">
                            <label for="edit_notes" class="form-label">Бележки:</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                            <button type="submit" class="btn btn-primary">Запази</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showAddModal() {
            new bootstrap.Modal(document.getElementById('addModal')).show();
        }

        function showEditModal(resident) {
            document.getElementById('edit_id').value = resident.id;
            document.getElementById('edit_property_id').value = resident.property_id;
            document.getElementById('edit_first_name').value = resident.first_name;
            document.getElementById('edit_middle_name').value = resident.middle_name || '';
            document.getElementById('edit_last_name').value = resident.last_name;
            document.getElementById('edit_phone').value = resident.phone;
            document.getElementById('edit_email').value = resident.email;
            document.getElementById('edit_status').value = resident.status;
            document.getElementById('edit_move_in_date').value = resident.move_in_date;
            document.getElementById('edit_move_out_date').value = resident.move_out_date || '';
            document.getElementById('edit_notes').value = resident.notes;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deleteResident(id) {
            if (confirm('Сигурни ли сте, че искате да изтриете този обитател?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_resident">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html> 