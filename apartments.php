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
require_once 'includes/styles.php';

$error = '';
$success = '';

// Добавяне на константа за типовете имоти
define('PROPERTY_TYPES', [
    'apartment' => 'Апартамент',
    'garage' => 'Гараж',
    'room' => 'Помещение',
    'office' => 'Офис',
    'shop' => 'Магазин',
    'warehouse' => 'Склад'
]);

try {
    // Вземане на текущата сграда
    $currentBuilding = getCurrentBuilding();
    
    // Обработка на POST заявки
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_apartment':
                    $building_id = (int)($_POST['building_id'] ?? 0);
                    $number = $_POST['number'] ?? '';
                    $floor = (int)($_POST['floor'] ?? 0);
                    $area = (float)($_POST['area'] ?? 0);
                    $people_count = (int)($_POST['people_count'] ?? 1);
                    
                    // Данни за собственика
                    $owner_first_name = $_POST['owner_first_name'] ?? '';
                    $owner_last_name = $_POST['owner_last_name'] ?? '';
                    $owner_phone = $_POST['owner_phone'] ?? '';
                    $owner_email = $_POST['owner_email'] ?? '';
                    $owner_move_in_date = $_POST['owner_move_in_date'] ?? date('Y-m-d');
                    
                    // Данни за допълнителни обитатели
                    $additional_residents = [];
                    if (isset($_POST['additional_residents']) && is_array($_POST['additional_residents'])) {
                        foreach ($_POST['additional_residents'] as $resident) {
                            if (!empty($resident['first_name']) && !empty($resident['last_name'])) {
                                $additional_residents[] = [
                                    'first_name' => $resident['first_name'],
                                    'last_name' => $resident['last_name'],
                                    'phone' => $resident['phone'] ?? '',
                                    'email' => $resident['email'] ?? '',
                                    'is_owner' => 0,
                                    'is_primary' => 0,
                                    'move_in_date' => $resident['move_in_date'] ?? date('Y-m-d')
                                ];
                            }
                        }
                    }
                    
                    if ($building_id > 0 && !empty($number) && $floor >= 0 && $area > 0) {
                        $pdo->beginTransaction();
                        try {
                            // Добавяне на апартамента
                            $stmt = $pdo->prepare("INSERT INTO apartments (building_id, number, floor, area, people_count) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$building_id, $number, $floor, $area, $people_count]);
                            $apartment_id = $pdo->lastInsertId();
                            
                            // Ако има данни за собственик, го добавяме като обитател
                            if (!empty($owner_first_name) && !empty($owner_last_name)) {
                                $stmt = $pdo->prepare("INSERT INTO residents (apartment_id, first_name, last_name, phone, email, is_owner, is_primary, move_in_date) VALUES (?, ?, ?, ?, ?, 1, 1, ?)");
                                $stmt->execute([$apartment_id, $owner_first_name, $owner_last_name, $owner_phone, $owner_email, $owner_move_in_date]);
                            }
                            
                            // Добавяне на допълнителни обитатели
                            if (!empty($additional_residents)) {
                                $stmt = $pdo->prepare("INSERT INTO residents (apartment_id, first_name, last_name, phone, email, is_owner, is_primary, move_in_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                foreach ($additional_residents as $resident) {
                                    $stmt->execute([
                                        $apartment_id,
                                        $resident['first_name'],
                                        $resident['last_name'],
                                        $resident['phone'],
                                        $resident['email'],
                                        $resident['is_owner'],
                                        $resident['is_primary'],
                                        $resident['move_in_date']
                                    ]);
                                }
                            }
                            
                            $pdo->commit();
                            $success = showSuccess('Апартаментът е добавен успешно.');
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            throw $e;
                        }
                    } else {
                        $error = showError('Моля, попълнете всички задължителни полета.');
                    }
                    break;
                    
                case 'edit_apartment':
                    $id = (int)($_POST['id'] ?? 0);
                    $building_id = (int)($_POST['building_id'] ?? 0);
                    $number = $_POST['number'] ?? '';
                    $floor = (int)($_POST['floor'] ?? 0);
                    $area = (float)($_POST['area'] ?? 0);
                    $people_count = (int)($_POST['people_count'] ?? 1);
                    $owner_name = $_POST['owner_name'] ?? '';
                    $owner_phone = $_POST['owner_phone'] ?? '';
                    $owner_email = $_POST['owner_email'] ?? '';
                    
                    if ($id > 0 && $building_id > 0 && !empty($number) && $floor >= 0 && $area > 0) {
                        $stmt = $pdo->prepare("UPDATE apartments SET building_id = ?, number = ?, floor = ?, area = ?, people_count = ?, owner_name = ?, owner_phone = ?, owner_email = ? WHERE id = ?");
                        $stmt->execute([$building_id, $number, $floor, $area, $people_count, $owner_name, $owner_phone, $owner_email, $id]);
                        $success = showSuccess('Апартаментът е редактиран успешно.');
                    } else {
                        $error = showError('Моля, попълнете всички задължителни полета.');
                    }
                    break;
                    
                case 'delete_apartment':
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id > 0) {
                        $stmt = $pdo->prepare("DELETE FROM apartments WHERE id = ?");
                        $stmt->execute([$id]);
                        $success = showSuccess('Апартаментът е изтрит успешно.');
                    }
                    break;
                    
                case 'add_resident':
                    $apartment_id = (int)($_POST['apartment_id'] ?? 0);
                    $first_name = $_POST['first_name'] ?? '';
                    $last_name = $_POST['last_name'] ?? '';
                    $phone = $_POST['phone'] ?? '';
                    $email = $_POST['email'] ?? '';
                    $is_owner = isset($_POST['is_owner']) ? 1 : 0;
                    $is_primary = isset($_POST['is_primary']) ? 1 : 0;
                    $move_in_date = $_POST['move_in_date'] ?? date('Y-m-d');
                    
                    if ($apartment_id > 0 && !empty($first_name) && !empty($last_name)) {
                        $stmt = $pdo->prepare("INSERT INTO residents (apartment_id, first_name, last_name, phone, email, is_owner, is_primary, move_in_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$apartment_id, $first_name, $last_name, $phone, $email, $is_owner, $is_primary, $move_in_date]);
                        $success = showSuccess('Обитателят е добавен успешно.');
                    } else {
                        $error = showError('Моля, попълнете всички задължителни полета.');
                    }
                    break;
                    
                case 'edit_resident':
                    $id = (int)($_POST['id'] ?? 0);
                    $apartment_id = (int)($_POST['apartment_id'] ?? 0);
                    $first_name = $_POST['first_name'] ?? '';
                    $last_name = $_POST['last_name'] ?? '';
                    $phone = $_POST['phone'] ?? '';
                    $email = $_POST['email'] ?? '';
                    $is_owner = isset($_POST['is_owner']) ? 1 : 0;
                    $is_primary = isset($_POST['is_primary']) ? 1 : 0;
                    $move_in_date = $_POST['move_in_date'] ?? '';
                    $move_out_date = $_POST['move_out_date'] ?? '';
                    
                    if ($id > 0 && $apartment_id > 0 && !empty($first_name) && !empty($last_name)) {
                        $stmt = $pdo->prepare("UPDATE residents SET apartment_id = ?, first_name = ?, last_name = ?, phone = ?, email = ?, is_owner = ?, is_primary = ?, move_in_date = ?, move_out_date = ? WHERE id = ?");
                        $stmt->execute([$apartment_id, $first_name, $last_name, $phone, $email, $is_owner, $is_primary, $move_in_date, $move_out_date ?: null, $id]);
                        $success = showSuccess('Обитателят е редактиран успешно.');
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
                    }
                    break;
            }
        }
    }
    
    // Вземане на всички сгради за падащото меню
    $stmt = $pdo->query("SELECT * FROM buildings ORDER BY name");
    $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Вземане на апартаментите според избраната сграда
    $query = "
        SELECT a.*, b.name as building_name 
        FROM apartments a 
        JOIN buildings b ON a.building_id = b.id 
    ";
    $params = [];
    
    if ($currentBuilding) {
        $query .= " WHERE a.building_id = ?";
        $params[] = $currentBuilding['id'];
    }
    
    $query .= " ORDER BY b.name, a.number";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $apartments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = handlePDOError($e);
} catch (Exception $e) {
    $error = handleError($e);
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Апартаменти | Електронен Домоуправител</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once 'includes/styles.php'; ?>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Апартаменти</h1>
            <?php echo renderNavigation('apartments'); ?>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Назад към таблото</a>
        
        <?php echo renderBuildingSelector(); ?>
        
        <?php if ($currentBuilding): ?>
        <div class="building-info">
            <h4><i class="fas fa-building"></i> Текуща сграда: <?php echo htmlspecialchars($currentBuilding['name']); ?></h4>
            <p><i class="fas fa-map-marker-alt"></i> Адрес: <?php echo htmlspecialchars($currentBuilding['address']); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <?php echo $error; ?>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <?php echo $success; ?>
        <?php endif; ?>
        
        <button class="btn btn-primary mb-3" onclick="showAddModal()">
            <i class="fas fa-plus"></i> Добави нов апартамент
        </button>
        
        <div class="grid">
            <?php foreach ($apartments as $apartment): ?>
            <div class="card">
                <h3><i class="fas fa-home"></i> Апартамент <?php echo htmlspecialchars($apartment['number']); ?></h3>
                <p><strong><i class="fas fa-building"></i> Сграда:</strong> <?php echo htmlspecialchars($apartment['building_name']); ?></p>
                <p><strong><i class="fas fa-layer-group"></i> Етаж:</strong> <?php echo $apartment['floor']; ?></p>
                <p><strong><i class="fas fa-ruler-combined"></i> Площ:</strong> <?php echo $apartment['area']; ?> м²</p>
                <?php if ($apartment['owner_name']): ?>
                <p><strong><i class="fas fa-user"></i> Собственик:</strong> <?php echo htmlspecialchars($apartment['owner_name']); ?></p>
                <?php endif; ?>
                <?php if ($apartment['owner_phone']): ?>
                <p><strong><i class="fas fa-phone"></i> Телефон:</strong> <?php echo htmlspecialchars($apartment['owner_phone']); ?></p>
                <?php endif; ?>
                <?php if ($apartment['owner_email']): ?>
                <p><strong><i class="fas fa-envelope"></i> Имейл:</strong> <?php echo htmlspecialchars($apartment['owner_email']); ?></p>
                <?php endif; ?>
                <div class="payment-actions">
                    <button class="btn btn-info" onclick="showResidentsModal(<?php echo $apartment['id']; ?>)">
                        <i class="fas fa-users"></i> Обитатели
                    </button>
                    <button class="btn btn-warning" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($apartment)); ?>)">
                        <i class="fas fa-edit"></i> Редактирай
                    </button>
                    <button class="btn btn-danger" onclick="deleteApartment(<?php echo $apartment['id']; ?>)">
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
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Добави нов апартамент</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_apartment">
                        <input type="hidden" name="building_id" value="<?php echo $currentBuilding ? $currentBuilding['id'] : ''; ?>">
                        <div class="form-group">
                            <label for="number" class="form-label">Номер:</label>
                            <input type="text" class="form-control" id="number" name="number" required>
                        </div>
                        <div class="form-group">
                            <label for="type" class="form-label">Тип на имота:</label>
                            <select class="form-control" id="type" name="type" required>
                                <?php foreach (PROPERTY_TYPES as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="floor" class="form-label">Етаж:</label>
                            <input type="number" class="form-control" id="floor" name="floor" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="area" class="form-label">Площ (м²):</label>
                            <input type="number" class="form-control" id="area" name="area" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="people_count" class="form-label">Брой хора:</label>
                            <input type="number" class="form-control" id="people_count" name="people_count" min="1" value="1" required>
                        </div>
                        
                        <hr>
                        <h5 class="mb-3">Данни за собственика</h5>
                        
                        <div class="form-group">
                            <label for="owner_first_name" class="form-label">Име на собственик:</label>
                            <input type="text" class="form-control" id="owner_first_name" name="owner_first_name">
                        </div>
                        <div class="form-group">
                            <label for="owner_last_name" class="form-label">Фамилия на собственик:</label>
                            <input type="text" class="form-control" id="owner_last_name" name="owner_last_name">
                        </div>
                        <div class="form-group">
                            <label for="owner_phone" class="form-label">Телефон:</label>
                            <input type="tel" class="form-control" id="owner_phone" name="owner_phone">
                        </div>
                        <div class="form-group">
                            <label for="owner_email" class="form-label">Имейл:</label>
                            <input type="email" class="form-control" id="owner_email" name="owner_email">
                        </div>
                        <div class="form-group">
                            <label for="owner_move_in_date" class="form-label">Дата на настаняване:</label>
                            <input type="date" class="form-control" id="owner_move_in_date" name="owner_move_in_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <hr>
                        <h5 class="mb-3">Допълнителни обитатели</h5>
                        <div id="additionalResidents">
                            <!-- Тук ще се добавят форми за допълнителни обитатели -->
                        </div>
                        
                        <button type="button" class="btn btn-info mb-3" onclick="addResidentForm()">
                            <i class="fas fa-plus"></i> Добави обитател
                        </button>
                        
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
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Редактирай апартамент</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_apartment">
                        <input type="hidden" name="id" id="edit_id">
                        <input type="hidden" name="building_id" id="edit_building_id" value="<?php echo $currentBuilding ? $currentBuilding['id'] : ''; ?>">
                        <div class="form-group">
                            <label for="edit_number" class="form-label">Номер:</label>
                            <input type="text" class="form-control" id="edit_number" name="number" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_floor" class="form-label">Етаж:</label>
                            <input type="number" class="form-control" id="edit_floor" name="floor" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_area" class="form-label">Площ (м²):</label>
                            <input type="number" class="form-control" id="edit_area" name="area" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_people_count" class="form-label">Брой хора:</label>
                            <input type="number" class="form-control" id="edit_people_count" name="people_count" min="1" value="1" required>
                        </div>
                        
                        <hr>
                        <h5 class="mb-3">Данни за собственика</h5>
                        
                        <div class="form-group">
                            <label for="edit_owner_first_name" class="form-label">Име на собственик:</label>
                            <input type="text" class="form-control" id="edit_owner_first_name" name="owner_first_name">
                        </div>
                        <div class="form-group">
                            <label for="edit_owner_last_name" class="form-label">Фамилия на собственик:</label>
                            <input type="text" class="form-control" id="edit_owner_last_name" name="owner_last_name">
                        </div>
                        <div class="form-group">
                            <label for="edit_owner_phone" class="form-label">Телефон:</label>
                            <input type="tel" class="form-control" id="edit_owner_phone" name="owner_phone">
                        </div>
                        <div class="form-group">
                            <label for="edit_owner_email" class="form-label">Имейл:</label>
                            <input type="email" class="form-control" id="edit_owner_email" name="owner_email">
                        </div>
                        <div class="form-group">
                            <label for="edit_owner_move_in_date" class="form-label">Дата на настаняване:</label>
                            <input type="date" class="form-control" id="edit_owner_move_in_date" name="owner_move_in_date">
                        </div>
                        
                        <hr>
                        <h5 class="mb-3">Допълнителни обитатели</h5>
                        <div id="editAdditionalResidents">
                            <!-- Тук ще се добавят форми за допълнителни обитатели -->
                        </div>
                        
                        <button type="button" class="btn btn-info mb-3" onclick="addEditResidentForm()">
                            <i class="fas fa-plus"></i> Добави обитател
                        </button>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                            <button type="submit" class="btn btn-primary">Запази</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Модален прозорец за обитатели -->
    <div id="residentsModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-users"></i> Управление на обитатели</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="residentsList" class="mb-4">
                        <!-- Списък с обитатели ще се зарежда динамично -->
                    </div>
                    
                    <button class="btn btn-primary mb-3" onclick="showAddResidentModal()">
                        <i class="fas fa-plus"></i> Добави нов обитател
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Модален прозорец за добавяне на обитател -->
    <div id="addResidentModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Добави нов обитател</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_resident">
                        <input type="hidden" name="apartment_id" id="resident_apartment_id">
                        <div class="form-group">
                            <label for="first_name" class="form-label">Име:</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
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
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="is_owner" name="is_owner">
                            <label class="form-check-label" for="is_owner">Собственик</label>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="is_primary" name="is_primary">
                            <label class="form-check-label" for="is_primary">Основен обитател</label>
                        </div>
                        <div class="form-group">
                            <label for="move_in_date" class="form-label">Дата на настаняване:</label>
                            <input type="date" class="form-control" id="move_in_date" name="move_in_date" value="<?php echo date('Y-m-d'); ?>" required>
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

    <!-- Модален прозорец за редактиране на обитател -->
    <div id="editResidentModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Редактирай обитател</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_resident">
                        <input type="hidden" name="id" id="edit_resident_id">
                        <input type="hidden" name="apartment_id" id="edit_resident_apartment_id">
                        <div class="form-group">
                            <label for="edit_first_name" class="form-label">Име:</label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
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
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="edit_is_owner" name="is_owner">
                            <label class="form-check-label" for="edit_is_owner">Собственик</label>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="edit_is_primary" name="is_primary">
                            <label class="form-check-label" for="edit_is_primary">Основен обитател</label>
                        </div>
                        <div class="form-group">
                            <label for="edit_move_in_date" class="form-label">Дата на настаняване:</label>
                            <input type="date" class="form-control" id="edit_move_in_date" name="move_in_date" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_move_out_date" class="form-label">Дата на напускане:</label>
                            <input type="date" class="form-control" id="edit_move_out_date" name="move_out_date">
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
            var modal = new bootstrap.Modal(document.getElementById('addModal'));
            modal.show();
        }

        function showEditModal(apartment) {
            document.getElementById('edit_id').value = apartment.id;
            document.getElementById('edit_building_id').value = apartment.building_id;
            document.getElementById('edit_number').value = apartment.number;
            document.getElementById('edit_floor').value = apartment.floor;
            document.getElementById('edit_area').value = apartment.area;
            document.getElementById('edit_people_count').value = apartment.people_count;
            
            // Изчистване на предишните допълнителни обитатели
            document.getElementById('editAdditionalResidents').innerHTML = '';
            
            // Зареждане на данни за собственика и допълнителните обитатели от базата
            fetch(`get_residents.php?apartment_id=${apartment.id}`)
                .then(response => response.json())
                .then(residents => {
                    const owner = residents.find(r => r.is_owner === 1);
                    if (owner) {
                        document.getElementById('edit_owner_first_name').value = owner.first_name;
                        document.getElementById('edit_owner_last_name').value = owner.last_name;
                        document.getElementById('edit_owner_phone').value = owner.phone || '';
                        document.getElementById('edit_owner_email').value = owner.email || '';
                        document.getElementById('edit_owner_move_in_date').value = owner.move_in_date;
                    }
                    
                    // Добавяне на форми за допълнителните обитатели
                    residents.filter(r => r.is_owner === 0).forEach((resident, index) => {
                        const container = document.getElementById('editAdditionalResidents');
                        const residentForm = document.createElement('div');
                        residentForm.className = 'resident-form mb-3 p-3 border rounded';
                        residentForm.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">Обитател ${index + 1}</h6>
                                <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Име:</label>
                                        <input type="text" class="form-control" name="additional_residents[${index}][first_name]" value="${resident.first_name}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Фамилия:</label>
                                        <input type="text" class="form-control" name="additional_residents[${index}][last_name]" value="${resident.last_name}" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Телефон:</label>
                                        <input type="tel" class="form-control" name="additional_residents[${index}][phone]" value="${resident.phone || ''}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Имейл:</label>
                                        <input type="email" class="form-control" name="additional_residents[${index}][email]" value="${resident.email || ''}">
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Дата на настаняване:</label>
                                        <input type="date" class="form-control" name="additional_residents[${index}][move_in_date]" value="${resident.move_in_date}">
                                    </div>
                                </div>
                            </div>
                        `;
                        container.appendChild(residentForm);
                    });
                })
                .catch(error => console.error('Error:', error));
            
            var modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        }

        function deleteApartment(id) {
            if (confirm('Сигурни ли сте, че искате да изтриете този апартамент?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_apartment">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function showResidentsModal(apartmentId) {
            document.getElementById('resident_apartment_id').value = apartmentId;
            loadResidents(apartmentId);
            new bootstrap.Modal(document.getElementById('residentsModal')).show();
        }

        function showAddResidentModal() {
            new bootstrap.Modal(document.getElementById('addResidentModal')).show();
        }

        function showEditResidentModal(resident) {
            document.getElementById('edit_resident_id').value = resident.id;
            document.getElementById('edit_resident_apartment_id').value = resident.apartment_id;
            document.getElementById('edit_first_name').value = resident.first_name;
            document.getElementById('edit_last_name').value = resident.last_name;
            document.getElementById('edit_phone').value = resident.phone;
            document.getElementById('edit_email').value = resident.email;
            document.getElementById('edit_is_owner').checked = resident.is_owner == 1;
            document.getElementById('edit_is_primary').checked = resident.is_primary == 1;
            document.getElementById('edit_move_in_date').value = resident.move_in_date;
            document.getElementById('edit_move_out_date').value = resident.move_out_date || '';
            new bootstrap.Modal(document.getElementById('editResidentModal')).show();
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

        function loadResidents(apartmentId) {
            fetch(`get_residents.php?apartment_id=${apartmentId}`)
                .then(response => response.json())
                .then(residents => {
                    const residentsList = document.getElementById('residentsList');
                    residentsList.innerHTML = '';
                    
                    if (residents.length === 0) {
                        residentsList.innerHTML = '<div class="alert alert-info">Няма обитатели за този апартамент.</div>';
                        return;
                    }
                    
                    const table = document.createElement('table');
                    table.className = 'table table-striped';
                    table.innerHTML = `
                        <thead>
                            <tr>
                                <th>Име</th>
                                <th>Телефон</th>
                                <th>Имейл</th>
                                <th>Статус</th>
                                <th>Настаняване</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    `;
                    
                    const tbody = table.querySelector('tbody');
                    residents.forEach(resident => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${resident.first_name} ${resident.last_name}</td>
                            <td>${resident.phone || '-'}</td>
                            <td>${resident.email || '-'}</td>
                            <td>
                                ${resident.is_owner ? '<span class="badge bg-primary">Собственик</span> ' : ''}
                                ${resident.is_primary ? '<span class="badge bg-success">Основен</span>' : ''}
                            </td>
                            <td>${resident.move_in_date}</td>
                            <td>
                                <button class="btn btn-warning btn-sm" onclick='showEditResidentModal(${JSON.stringify(resident)})'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteResident(${resident.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                    
                    residentsList.appendChild(table);
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('residentsList').innerHTML = '<div class="alert alert-danger">Възникна грешка при зареждането на обитателите.</div>';
                });
        }

        function addResidentForm() {
            const container = document.getElementById('additionalResidents');
            const index = container.children.length;
            
            const residentForm = document.createElement('div');
            residentForm.className = 'resident-form mb-3 p-3 border rounded';
            residentForm.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Обитател ${index + 1}</h6>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Име:</label>
                            <input type="text" class="form-control" name="additional_residents[${index}][first_name]" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Фамилия:</label>
                            <input type="text" class="form-control" name="additional_residents[${index}][last_name]" required>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Телефон:</label>
                            <input type="tel" class="form-control" name="additional_residents[${index}][phone]">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Имейл:</label>
                            <input type="email" class="form-control" name="additional_residents[${index}][email]">
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Дата на настаняване:</label>
                            <input type="date" class="form-control" name="additional_residents[${index}][move_in_date]" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(residentForm);
        }
        
        function addEditResidentForm() {
            const container = document.getElementById('editAdditionalResidents');
            const index = container.children.length;
            
            const residentForm = document.createElement('div');
            residentForm.className = 'resident-form mb-3 p-3 border rounded';
            residentForm.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Обитател ${index + 1}</h6>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Име:</label>
                            <input type="text" class="form-control" name="additional_residents[${index}][first_name]" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Фамилия:</label>
                            <input type="text" class="form-control" name="additional_residents[${index}][last_name]" required>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Телефон:</label>
                            <input type="tel" class="form-control" name="additional_residents[${index}][phone]">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Имейл:</label>
                            <input type="email" class="form-control" name="additional_residents[${index}][email]">
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Дата на настаняване:</label>
                            <input type="date" class="form-control" name="additional_residents[${index}][move_in_date]" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(residentForm);
        }
    </script>
</body>
</html>
