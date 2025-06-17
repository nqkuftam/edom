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
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Дефиниране на типовете имоти
if (!defined('PROPERTY_TYPES')) {
    define('PROPERTY_TYPES', [
        'apartment' => 'Апартамент',
        'garage' => 'Гараж',
        'room' => 'Стая',
        'office' => 'Офис',
        'shop' => 'Магазин',
        'warehouse' => 'Склад'
    ]);
}

$error = '';
$success = '';

try {
    // Вземане на текущата сграда
    $currentBuilding = getCurrentBuilding();
    
    // Обработка на POST заявките
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_apartment':
                    $building_id = (int)($_POST['building_id'] ?? 0);
                    $type = $_POST['type'] ?? 'apartment';
                    $number = $_POST['number'] ?? '';
                    $floor = (int)($_POST['floor'] ?? 0);
                    $area = (float)($_POST['area'] ?? 0);
                    $people_count = (int)($_POST['people_count'] ?? 1);
                    
                    // Данни за собственика
                    $owner_name = $_POST['owner_name'] ?? '';
                    $owner_phone = $_POST['owner_phone'] ?? '';
                    $owner_email = $_POST['owner_email'] ?? '';
                    $move_in_date = $_POST['move_in_date'] ?? date('Y-m-d');
                    
                    // Валидация според типа на имота
                    $isValid = true;
                    if ($type === 'apartment') {
                        $isValid = !empty($number) && $floor >= 0 && $area > 0;
                    } else {
                        $isValid = $area > 0;
                    }
                    
                    if ($building_id > 0 && $isValid) {
                        $pdo->beginTransaction();
                        try {
                            // Добавяне на имота
                            $stmt = $pdo->prepare("INSERT INTO apartments (building_id, type, number, floor, area, people_count) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$building_id, $type, $number, $floor, $area, $people_count]);
                            $apartment_id = $pdo->lastInsertId();
                            
                            // Ако има данни за собственик, го добавяме като обитател
                            if (!empty($owner_name)) {
                                $owner_parts = explode(' ', $owner_name, 2);
                                $owner_first_name = $owner_parts[0];
                                $owner_last_name = isset($owner_parts[1]) ? $owner_parts[1] : '';
                                
                                $stmt = $pdo->prepare("INSERT INTO residents (apartment_id, first_name, last_name, phone, email, is_owner, is_primary, move_in_date) VALUES (?, ?, ?, ?, ?, 1, 1, ?)");
                                $stmt->execute([$apartment_id, $owner_first_name, $owner_last_name, $owner_phone, $owner_email, $move_in_date]);
                            }
                            
                            $pdo->commit();
                            $success = showSuccess('Имотът е добавен успешно.');
                            header('Location: properties.php');
                            exit();
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $error = showError('Грешка при добавяне на имота: ' . $e->getMessage());
                        }
                    } else {
                        $error = showError('Моля, попълнете всички задължителни полета.');
                    }
                    break;
                    
                case 'edit_apartment':
                    $id = (int)($_POST['id'] ?? 0);
                    $building_id = (int)($_POST['building_id'] ?? 0);
                    $type = $_POST['type'] ?? 'apartment';
                    $number = $_POST['number'] ?? '';
                    $floor = (int)($_POST['floor'] ?? 0);
                    $area = (float)($_POST['area'] ?? 0);
                    $people_count = (int)($_POST['people_count'] ?? 1);
                    $owner_name = $_POST['owner_name'] ?? '';
                    $owner_phone = $_POST['owner_phone'] ?? '';
                    $owner_email = $_POST['owner_email'] ?? '';
                    $move_in_date = $_POST['move_in_date'] ?? date('Y-m-d');
                    
                    // Валидация според типа на имота
                    $isValid = true;
                    if ($type === 'apartment') {
                        $isValid = !empty($number) && $floor >= 0 && $area > 0;
                    } else {
                        $isValid = $area > 0;
                    }
                    
                    if ($id > 0 && $building_id > 0 && $isValid) {
                        $pdo->beginTransaction();
                        try {
                            // Редактиране на имота
                            $stmt = $pdo->prepare("UPDATE apartments SET building_id = ?, type = ?, number = ?, floor = ?, area = ?, people_count = ? WHERE id = ?");
                            $stmt->execute([$building_id, $type, $number, $floor, $area, $people_count, $id]);
                            
                            // Ако има данни за собственик, го редактираме или добавяме
                            if (!empty($owner_name)) {
                                $owner_parts = explode(' ', $owner_name, 2);
                                $owner_first_name = $owner_parts[0];
                                $owner_last_name = isset($owner_parts[1]) ? $owner_parts[1] : '';
                                
                                // Проверяваме дали вече има собственик
                                $stmt = $pdo->prepare("SELECT id FROM residents WHERE apartment_id = ? AND is_owner = 1 AND is_primary = 1");
                                $stmt->execute([$id]);
                                $owner = $stmt->fetch();
                                
                                if ($owner) {
                                    // Редактиране на съществуващ собственик
                                    $stmt = $pdo->prepare("UPDATE residents SET first_name = ?, last_name = ?, phone = ?, email = ? WHERE id = ?");
                                    $stmt->execute([$owner_first_name, $owner_last_name, $owner_phone, $owner_email, $owner['id']]);
                                } else {
                                    // Добавяне на нов собственик
                                    $stmt = $pdo->prepare("INSERT INTO residents (apartment_id, first_name, last_name, phone, email, is_owner, is_primary, move_in_date) VALUES (?, ?, ?, ?, ?, 1, 1, ?)");
                                    $stmt->execute([$id, $owner_first_name, $owner_last_name, $owner_phone, $owner_email, $move_in_date]);
                                }
                            }
                            
                            $pdo->commit();
                            $success = showSuccess('Имотът е редактиран успешно.');
                            header('Location: properties.php');
                            exit();
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $error = showError('Грешка при редактиране на имота: ' . $e->getMessage());
                        }
                    } else {
                        $error = showError('Моля, попълнете всички задължителни полета.');
                    }
                    break;
                    
                case 'delete_apartment':
                    try {
                        $id = (int)($_POST['id'] ?? 0);
                        if ($id <= 0) {
                            throw new Exception('Невалиден ID на имота.');
                        }

                        $pdo->beginTransaction();
                        
                        // Първо проверяваме дали има свързани плащания
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE apartment_id = ?");
                        $stmt->execute([$id]);
                        if ($stmt->fetchColumn() > 0) {
                            throw new Exception('Не можете да изтриете имота, защото има свързани плащания.');
                        }
                        
                        // Проверяваме за свързани такси в fee_apartments
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM fee_apartments WHERE apartment_id = ?");
                        $stmt->execute([$id]);
                        if ($stmt->fetchColumn() > 0) {
                            throw new Exception('Не можете да изтриете имота, защото има свързани такси.');
                        }
                        
                        // Изтриване на обитателите
                        $stmt = $pdo->prepare("DELETE FROM residents WHERE apartment_id = ?");
                        $stmt->execute([$id]);
                        
                        // Изтриване на имота
                        $stmt = $pdo->prepare("DELETE FROM apartments WHERE id = ?");
                        $stmt->execute([$id]);
                        
                        $pdo->commit();
                        echo 'OK';
                        header('Location: properties.php');
                        exit();
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        echo '<div class="alert alert-danger">Грешка при изтриване на имота: ' . $e->getMessage() . '</div>';
                    }
                    exit;
                    
                case 'add_resident':
                    $apartment_id = (int)($_POST['apartment_id'] ?? 0);
                    $first_name = $_POST['first_name'] ?? '';
                    $last_name = $_POST['last_name'] ?? '';
                    $phone = $_POST['phone'] ?? '';
                    $email = $_POST['email'] ?? '';
                    $is_owner = isset($_POST['is_owner']) ? 1 : 0;
                    $is_primary = isset($_POST['is_primary']) ? 1 : 0;
                    $move_in_date = $_POST['move_in_date'] ?? date('Y-m-d');
                    $move_out_date = $_POST['move_out_date'] ?? null;
                    
                    if ($apartment_id > 0 && !empty($first_name) && !empty($last_name)) {
                        $stmt = $pdo->prepare("INSERT INTO residents (apartment_id, first_name, last_name, phone, email, is_owner, is_primary, move_in_date, move_out_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$apartment_id, $first_name, $last_name, $phone, $email, $is_owner, $is_primary, $move_in_date, $move_out_date]);
                        $success = showSuccess('Обитателят е добавен успешно.');
                        header('Location: properties.php');
                        exit();
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
                    $move_out_date = $_POST['move_out_date'] ?? null;
                    
                    if ($id > 0 && $apartment_id > 0 && !empty($first_name) && !empty($last_name)) {
                        $stmt = $pdo->prepare("UPDATE residents SET apartment_id = ?, first_name = ?, last_name = ?, phone = ?, email = ?, is_owner = ?, is_primary = ?, move_in_date = ?, move_out_date = ? WHERE id = ?");
                        $stmt->execute([$apartment_id, $first_name, $last_name, $phone, $email, $is_owner, $is_primary, $move_in_date, $move_out_date, $id]);
                        $success = showSuccess('Обитателят е редактиран успешно.');
                        header('Location: properties.php');
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
                        header('Location: properties.php');
                        exit();
                    }
                    break;
            }
        }
    }
    
    // Вземане на всички сгради за падащото меню
    $stmt = $pdo->query("SELECT * FROM buildings ORDER BY name");
    $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Вземане на апартаментите според избраната сграда
    $stmt = $pdo->prepare("
        SELECT a.*, b.name as building_name,
               CONCAT(r.first_name, ' ', r.last_name) as owner_name,
               r.phone as owner_phone,
               r.email as owner_email
        FROM apartments a
        LEFT JOIN buildings b ON a.building_id = b.id
        LEFT JOIN residents r ON a.id = r.apartment_id AND r.is_owner = 1 AND r.is_primary = 1
        WHERE a.building_id = ?
        ORDER BY 
            CASE 
                WHEN a.type = 'apartment' THEN 1
                WHEN a.type = 'garage' THEN 2
                ELSE 3
            END,
            a.number
    ");
    $stmt->execute([$currentBuilding['id']]);
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
    <title>Имоти | Електронен Домоуправител</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once 'includes/styles.php'; ?>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Имоти</h1>
            <?php echo renderNavigation('properties'); ?>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Назад към таблото</a>
        
        <?php if ($currentBuilding): ?>
        <div class="building-info">
            <h4><i class="fas fa-building"></i> Текуща сграда: <?php echo htmlspecialchars($currentBuilding['name']); ?></h4>
            <?php echo renderBuildingSelector(); ?>
            <p><i class="fas fa-map-marker-alt"></i> Адрес: <?php echo htmlspecialchars($currentBuilding['address']); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <?php echo $error; ?>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <?php echo $success; ?>
        <?php endif; ?>
        
        <button type="button" class="btn btn-primary mb-3" onclick="showAddModal()">
            <i class="fas fa-plus"></i> Добави нов имот
        </button>
        
        <div class="grid">
            <?php foreach ($apartments as $apartment): ?>
            <div class="card">
                <h3>
                    <i class="fas fa-<?php echo $apartment['type'] === 'apartment' ? 'home' : ($apartment['type'] === 'garage' ? 'car' : 'building'); ?>"></i>
                    <?php echo htmlspecialchars(PROPERTY_TYPES[$apartment['type']] ?? $apartment['type']); ?>
                    <?php if ($apartment['type'] === 'apartment'): ?>
                        <?php echo htmlspecialchars($apartment['number']); ?>
                    <?php endif; ?>
                </h3>
                <p><strong><i class="fas fa-building"></i> Сграда:</strong> <?php echo htmlspecialchars($apartment['building_name']); ?></p>
                <?php if ($apartment['type'] === 'apartment'): ?>
                    <p><strong><i class="fas fa-layer-group"></i> Етаж:</strong> <?php echo $apartment['floor']; ?></p>
                <?php endif; ?>
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
                <div class="card-actions">
                    <button class="btn btn-primary" onclick="showEditForm(<?php echo $apartment['id']; ?>)">
                        <i class="fas fa-edit"></i> Редактирай
                    </button>
                    <button type="button" class="btn btn-danger" onclick="deleteApartment(<?php echo $apartment['id']; ?>)">
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
                            <label for="type">Тип на имота:</label>
                            <select class="form-control" id="type" name="type" required>
                                <?php foreach (PROPERTY_TYPES as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="number_group">
                            <label for="number">Номер:</label>
                            <input type="text" class="form-control" id="number" name="number" required>
                        </div>
                        <div class="form-group" id="floor_group">
                            <label for="floor">Етаж:</label>
                            <input type="number" class="form-control" id="floor" name="floor" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="area">Площ (м²):</label>
                            <input type="number" class="form-control" id="area" name="area" step="0.01" min="0" required>
                        </div>
                        <div class="form-group" id="people_count_group">
                            <label for="people_count">Брой хора:</label>
                            <input type="number" class="form-control" id="people_count" name="people_count" min="1" value="1" required>
                        </div>
                        
                        <hr>
                        <h5 class="mb-3">Данни за собственика</h5>
                        
                        <div class="form-group">
                            <label for="owner_name" class="form-label">Име на собственик:</label>
                            <input type="text" class="form-control" id="owner_name" name="owner_name">
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
                            <label for="move_in_date" class="form-label">Дата на настаняване:</label>
                            <input type="date" class="form-control" id="move_in_date" name="move_in_date" required>
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
                            <button type="submit" class="btn btn-primary" id="addApartmentBtn">Добави</button>
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
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Редактирай имот</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" id="edit_id" name="id">
                        <input type="hidden" id="edit_building_id" name="building_id">
                        <div class="form-group">
                            <label for="edit_type">Тип на имота:</label>
                            <select class="form-control" id="edit_type" name="type" required>
                                <?php foreach (PROPERTY_TYPES as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="edit_number_group">
                            <label for="edit_number">Номер:</label>
                            <input type="text" class="form-control" id="edit_number" name="number">
                        </div>
                        <div class="form-group" id="edit_floor_group">
                            <label for="edit_floor">Етаж:</label>
                            <input type="number" class="form-control" id="edit_floor" name="floor" min="0">
                        </div>
                        <div class="form-group">
                            <label for="edit_area">Площ (м²):</label>
                            <input type="number" class="form-control" id="edit_area" name="area" step="0.01" min="0" required>
                        </div>
                        <div class="form-group" id="edit_people_count_group">
                            <label for="edit_people_count">Брой хора:</label>
                            <input type="number" class="form-control" id="edit_people_count" name="people_count" min="1" value="1">
                        </div>
                        <hr>
                        <h5 class="mb-3">Данни за собственика</h5>
                        <div class="form-group">
                            <label for="edit_owner_name">Име на собственик:</label>
                            <input type="text" class="form-control" id="edit_owner_name" name="owner_name">
                        </div>
                        <div class="form-group">
                            <label for="edit_owner_phone">Телефон на собственик:</label>
                            <input type="text" class="form-control" id="edit_owner_phone" name="owner_phone">
                        </div>
                        <div class="form-group">
                            <label for="edit_owner_email">Имейл на собственик:</label>
                            <input type="email" class="form-control" id="edit_owner_email" name="owner_email">
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
                    
                    <button type="button" class="btn btn-primary mb-3" onclick="showAddResidentModal()">
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
                        <div class="form-group">
                            <label for="move_out_date" class="form-label">Дата на напускане:</label>
                            <input type="date" class="form-control" id="move_out_date" name="move_out_date">
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
            // Проверяваме дали модалният прозорец за добавяне е отворен
            const addModal = document.getElementById('addModal');
            if (addModal && addModal.classList.contains('show')) {
                alert('Моля, затворете първо формата за добавяне на имот преди да изтриете друг имот.');
                return;
            }

            if (confirm('Сигурни ли сте, че искате да изтриете този имот?')) {
                const formData = new FormData();
                formData.append('action', 'delete_apartment');
                formData.append('id', id);

                fetch('properties.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                    } else {
                        return response.text();
                    }
                })
                .then(html => {
                    if (html) {
                        // Показваме грешката
                        const div = document.createElement('div');
                        div.innerHTML = html;
                        const alertDiv = div.querySelector('.alert-danger');
                        if (alertDiv) {
                            alert(alertDiv.textContent.trim());
                        } else {
                            alert('Възникна грешка при изтриване на имота.');
                        }
                    }
                })
                .catch(error => {
                    console.error('Грешка при fetch:', error);
                    alert('Възникна грешка при изтриване на имота: ' + error.message);
                });
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
                                <th>Напускане</th>
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
                            <td>${resident.move_out_date || '-'}</td>
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

        // Функция за показване/скриване на полетата според типа на имота
        function togglePropertyFields() {
            const type = document.getElementById('type').value;
            const floorGroup = document.getElementById('floor_group');
            const peopleCountGroup = document.getElementById('people_count_group');
            const numberGroup = document.getElementById('number_group');
            const numberInput = document.getElementById('number');
            const floorInput = document.getElementById('floor');
            const peopleCountInput = document.getElementById('people_count');

            if (type === 'apartment') {
                floorGroup.style.display = 'block';
                peopleCountGroup.style.display = 'block';
                numberGroup.style.display = 'block';
                numberInput.required = true;
                floorInput.required = true;
                peopleCountInput.required = true;
            } else {
                floorGroup.style.display = 'none';
                peopleCountGroup.style.display = 'none';
                numberGroup.style.display = 'none';
                numberInput.required = false;
                floorInput.required = false;
                peopleCountInput.required = false;
            }
        }

        // Функция за показване/скриване на полетата в редактиращата форма
        function toggleEditPropertyFields() {
            const type = document.getElementById('edit_type').value;
            const floorGroup = document.getElementById('edit_floor_group');
            const peopleCountGroup = document.getElementById('edit_people_count_group');
            const numberGroup = document.getElementById('edit_number_group');
            const numberInput = document.getElementById('edit_number');
            const floorInput = document.getElementById('edit_floor');
            const peopleCountInput = document.getElementById('edit_people_count');

            if (type === 'apartment') {
                floorGroup.style.display = 'block';
                peopleCountGroup.style.display = 'block';
                numberGroup.style.display = 'block';
                numberInput.required = true;
                floorInput.required = true;
                peopleCountInput.required = true;
            } else {
                floorGroup.style.display = 'none';
                peopleCountGroup.style.display = 'none';
                numberGroup.style.display = 'none';
                numberInput.required = false;
                floorInput.required = false;
                peopleCountInput.required = false;
            }
        }

        // Добавяне на event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // За формата за добавяне
            const typeSelect = document.getElementById('type');
            if (typeSelect) {
                typeSelect.addEventListener('change', togglePropertyFields);
                togglePropertyFields(); // Извикване при зареждане
            }
            
            // За формата за редактиране
            const editTypeSelect = document.getElementById('edit_type');
            if (editTypeSelect) {
                editTypeSelect.addEventListener('change', toggleEditPropertyFields);
            }

            // Добавяне на event listener за формата за добавяне
            const addForm = document.querySelector('#addModal form');
            if (addForm) {
                addForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Проверяваме дали формата е отворена
                    const addModal = document.getElementById('addModal');
                    if (!addModal || !addModal.classList.contains('show')) {
                        return false;
                    }
                    
                    // Проверяваме дали бутонът "Добави" е натиснат
                    if (document.activeElement && document.activeElement.id !== 'addApartmentBtn') {
                        return false;
                    }

                    const formData = new FormData(this);
                    
                    fetch('properties.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (response.redirected) {
                            window.location.href = response.url;
                        } else {
                            return response.text();
                        }
                    })
                    .then(html => {
                        if (html) {
                            // Показваме грешката
                            const div = document.createElement('div');
                            div.innerHTML = html;
                            const alertDiv = div.querySelector('.alert-danger');
                            if (alertDiv) {
                                alert(alertDiv.textContent.trim());
                            } else {
                                alert('Възникна грешка при добавяне на имота.');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Възникна грешка при добавяне на имота: ' + error.message);
                    });
                });
            }

            // Обработка на формата за редактиране
            const editForm = document.getElementById('editForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(editForm);
                    formData.append('action', 'edit_apartment');
                    
                    fetch('properties.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (response.redirected) {
                            window.location.href = response.url;
                        } else {
                            return response.text();
                        }
                    })
                    .then(html => {
                        if (html) {
                            // Показваме грешката
                            const div = document.createElement('div');
                            div.innerHTML = html;
                            const alertDiv = div.querySelector('.alert-danger');
                            if (alertDiv) {
                                alert(alertDiv.textContent.trim());
                            } else {
                                alert('Възникна грешка при редактиране на имота.');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Възникна грешка при редактиране на имота: ' + error.message);
                    });
                });
            }

            // Изчистване на формата при затваряне на модала
            const addModal = document.getElementById('addModal');
            if (addModal) {
                addModal.addEventListener('hidden.bs.modal', function () {
                    const form = this.querySelector('form');
                    if (form) {
                        form.reset();
                        // Изчистваме всички допълнителни обитатели
                        const additionalResidents = document.getElementById('additionalResidents');
                        if (additionalResidents) {
                            additionalResidents.innerHTML = '';
                        }
                    }
                });
            }
        });

        // Функция за показване на формата за редактиране
        function showEditForm(id) {
            fetch(`get_apartment.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_building_id').value = data.building_id;
                    document.getElementById('edit_type').value = data.type;
                    document.getElementById('edit_number').value = data.number || '';
                    document.getElementById('edit_floor').value = data.floor || '';
                    document.getElementById('edit_area').value = data.area || '';
                    document.getElementById('edit_people_count').value = data.people_count || '';
                    document.getElementById('edit_owner_name').value = data.owner_name || '';
                    document.getElementById('edit_owner_phone').value = data.owner_phone || '';
                    document.getElementById('edit_owner_email').value = data.owner_email || '';
                    
                    // Показване/скриване на полетата според типа
                    toggleEditPropertyFields();
                    
                    // Показване на модалния прозорец
                    var modal = new bootstrap.Modal(document.getElementById('editModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Възникна грешка при зареждане на данните за имота.');
                });
        }
    </script>
</body>
</html>
