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
                    $owner_name = $_POST['owner_name'] ?? '';
                    $owner_phone = $_POST['owner_phone'] ?? '';
                    $owner_email = $_POST['owner_email'] ?? '';
                    
                    if ($building_id > 0 && !empty($number) && $floor >= 0 && $area > 0) {
                        $stmt = $pdo->prepare("INSERT INTO apartments (building_id, number, floor, area, people_count, owner_name, owner_phone, owner_email) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$building_id, $number, $floor, $area, $people_count, $owner_name, $owner_phone, $owner_email]);
                        $apartment_id = $pdo->lastInsertId();
                        
                        // Добавяне на обитатели
                        if (isset($_POST['residents']) && is_array($_POST['residents'])) {
                            $stmt = $pdo->prepare("INSERT INTO residents (apartment_id, first_name, last_name, phone, email, is_owner, is_primary, move_in_date, move_out_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            foreach ($_POST['residents'] as $resident) {
                                if (!empty($resident['first_name']) && !empty($resident['last_name']) && !empty($resident['move_in_date'])) {
                                    $is_owner = isset($resident['is_owner']) ? 1 : 0;
                                    $is_primary = isset($resident['is_primary']) ? 1 : 0;
                                    $move_out_date = !empty($resident['move_out_date']) ? $resident['move_out_date'] : null;
                                    $stmt->execute([
                                        $apartment_id,
                                        $resident['first_name'],
                                        $resident['last_name'],
                                        $resident['phone'] ?? null,
                                        $resident['email'] ?? null,
                                        $is_owner,
                                        $is_primary,
                                        $resident['move_in_date'],
                                        $move_out_date,
                                        $resident['notes'] ?? null
                                    ]);
                                }
                            }
                        }
                        
                        $success = showSuccess('Апартаментът е добавен успешно.');
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
                        
                        // Изтриване на старите обитатели
                        $stmt = $pdo->prepare("DELETE FROM residents WHERE apartment_id = ?");
                        $stmt->execute([$id]);
                        
                        // Добавяне на новите обитатели
                        if (isset($_POST['residents']) && is_array($_POST['residents'])) {
                            $stmt = $pdo->prepare("INSERT INTO residents (apartment_id, first_name, last_name, phone, email, is_owner, is_primary, move_in_date, move_out_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            foreach ($_POST['residents'] as $resident) {
                                if (!empty($resident['first_name']) && !empty($resident['last_name']) && !empty($resident['move_in_date'])) {
                                    $is_owner = isset($resident['is_owner']) ? 1 : 0;
                                    $is_primary = isset($resident['is_primary']) ? 1 : 0;
                                    $move_out_date = !empty($resident['move_out_date']) ? $resident['move_out_date'] : null;
                                    $stmt->execute([
                                        $id,
                                        $resident['first_name'],
                                        $resident['last_name'],
                                        $resident['phone'] ?? null,
                                        $resident['email'] ?? null,
                                        $is_owner,
                                        $is_primary,
                                        $resident['move_in_date'],
                                        $move_out_date,
                                        $resident['notes'] ?? null
                                    ]);
                                }
                            }
                        }
                        
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
            }
        }
    }
    
    // Вземане на всички сгради за падащото меню
    $stmt = $pdo->query("SELECT * FROM buildings ORDER BY name");
    $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Вземане на апартаментите според избраната сграда
    $query = "
        SELECT a.*, b.name as building_name,
        (SELECT COUNT(*) FROM residents r WHERE r.apartment_id = a.id) as residents_count,
        (SELECT JSON_ARRAYAGG(
            JSON_OBJECT(
                'id', r.id,
                'first_name', r.first_name,
                'last_name', r.last_name,
                'phone', r.phone,
                'email', r.email,
                'is_owner', r.is_owner,
                'is_primary', r.is_primary,
                'move_in_date', r.move_in_date,
                'move_out_date', r.move_out_date,
                'notes', r.notes
            )
        ) FROM residents r WHERE r.apartment_id = a.id) as residents
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
                <p><strong><i class="fas fa-users"></i> Брой обитатели:</strong> <?php echo $apartment['residents_count']; ?></p>
                <div class="payment-actions">
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
                        
                        <hr>
                        <h5 class="mb-3"><i class="fas fa-users"></i> Обитатели</h5>
                        <div id="residents-container">
                            <div class="resident-entry mb-3 p-3 border rounded">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Име:</label>
                                            <input type="text" class="form-control" name="residents[0][first_name]" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Фамилия:</label>
                                            <input type="text" class="form-control" name="residents[0][last_name]" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Телефон:</label>
                                            <input type="tel" class="form-control" name="residents[0][phone]">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Имейл:</label>
                                            <input type="email" class="form-control" name="residents[0][email]">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="residents[0][is_owner]" id="resident0_is_owner">
                                            <label class="form-check-label" for="resident0_is_owner">Собственик</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="residents[0][is_primary]" id="resident0_is_primary">
                                            <label class="form-check-label" for="resident0_is_primary">Основен обитател</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Дата на настаняване:</label>
                                            <input type="date" class="form-control" name="residents[0][move_in_date]" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Дата на напускане:</label>
                                            <input type="date" class="form-control" name="residents[0][move_out_date]">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group mt-2">
                                    <label class="form-label">Бележки:</label>
                                    <textarea class="form-control" name="residents[0][notes]" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="addResidentEntry()">
                            <i class="fas fa-plus"></i> Добави още обитател
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
                        <div class="form-group">
                            <label for="edit_owner_name" class="form-label">Име на собственик:</label>
                            <input type="text" class="form-control" id="edit_owner_name" name="owner_name">
                        </div>
                        <div class="form-group">
                            <label for="edit_owner_phone" class="form-label">Телефон:</label>
                            <input type="tel" class="form-control" id="edit_owner_phone" name="owner_phone">
                        </div>
                        <div class="form-group">
                            <label for="edit_owner_email" class="form-label">Имейл:</label>
                            <input type="email" class="form-control" id="edit_owner_email" name="owner_email">
                        </div>
                        
                        <hr>
                        <h5 class="mb-3"><i class="fas fa-users"></i> Обитатели</h5>
                        <div id="edit-residents-container">
                            <div class="resident-entry mb-3 p-3 border rounded">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Име:</label>
                                            <input type="text" class="form-control" name="residents[0][first_name]" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Фамилия:</label>
                                            <input type="text" class="form-control" name="residents[0][last_name]" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Телефон:</label>
                                            <input type="tel" class="form-control" name="residents[0][phone]">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Имейл:</label>
                                            <input type="email" class="form-control" name="residents[0][email]">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="residents[0][is_owner]" id="edit_resident0_is_owner">
                                            <label class="form-check-label" for="edit_resident0_is_owner">Собственик</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="residents[0][is_primary]" id="edit_resident0_is_primary">
                                            <label class="form-check-label" for="edit_resident0_is_primary">Основен обитател</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Дата на настаняване:</label>
                                            <input type="date" class="form-control" name="residents[0][move_in_date]" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Дата на напускане:</label>
                                            <input type="date" class="form-control" name="residents[0][move_out_date]">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group mt-2">
                                    <label class="form-label">Бележки:</label>
                                    <textarea class="form-control" name="residents[0][notes]" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="addEditResidentEntry()">
                            <i class="fas fa-plus"></i> Добави още обитател
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
            document.getElementById('edit_owner_name').value = apartment.owner_name;
            document.getElementById('edit_owner_phone').value = apartment.owner_phone;
            document.getElementById('edit_owner_email').value = apartment.owner_email;
            
            // Изчистване на контейнера за обитатели
            const container = document.getElementById('edit-residents-container');
            container.innerHTML = '';
            editResidentCount = 0;
            
            // Добавяне на съществуващите обитатели
            if (apartment.residents) {
                const residents = JSON.parse(apartment.residents);
                residents.forEach(resident => {
                    const newEntry = document.createElement('div');
                    newEntry.className = 'resident-entry mb-3 p-3 border rounded';
                    newEntry.innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Име:</label>
                                    <input type="text" class="form-control" name="residents[${editResidentCount}][first_name]" value="${resident.first_name}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Фамилия:</label>
                                    <input type="text" class="form-control" name="residents[${editResidentCount}][last_name]" value="${resident.last_name}" required>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Телефон:</label>
                                    <input type="tel" class="form-control" name="residents[${editResidentCount}][phone]" value="${resident.phone || ''}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Имейл:</label>
                                    <input type="email" class="form-control" name="residents[${editResidentCount}][email]" value="${resident.email || ''}">
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="residents[${editResidentCount}][is_owner]" id="edit_resident${editResidentCount}_is_owner" ${resident.is_owner == 1 ? 'checked' : ''}>
                                    <label class="form-check-label" for="edit_resident${editResidentCount}_is_owner">Собственик</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="residents[${editResidentCount}][is_primary]" id="edit_resident${editResidentCount}_is_primary" ${resident.is_primary == 1 ? 'checked' : ''}>
                                    <label class="form-check-label" for="edit_resident${editResidentCount}_is_primary">Основен обитател</label>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Дата на настаняване:</label>
                                    <input type="date" class="form-control" name="residents[${editResidentCount}][move_in_date]" value="${resident.move_in_date}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Дата на напускане:</label>
                                    <input type="date" class="form-control" name="residents[${editResidentCount}][move_out_date]" value="${resident.move_out_date || ''}">
                                </div>
                            </div>
                        </div>
                        <div class="form-group mt-2">
                            <label class="form-label">Бележки:</label>
                            <textarea class="form-control" name="residents[${editResidentCount}][notes]" rows="2">${resident.notes || ''}</textarea>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm mt-2" onclick="this.parentElement.remove()">
                            <i class="fas fa-trash"></i> Премахни обитател
                        </button>
                    `;
                    container.appendChild(newEntry);
                    editResidentCount++;
                });
            }
            
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

        let residentCount = 1;
        let editResidentCount = 1;

        function addResidentEntry() {
            const container = document.getElementById('residents-container');
            const newEntry = document.createElement('div');
            newEntry.className = 'resident-entry mb-3 p-3 border rounded';
            newEntry.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Име:</label>
                            <input type="text" class="form-control" name="residents[${residentCount}][first_name]" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Фамилия:</label>
                            <input type="text" class="form-control" name="residents[${residentCount}][last_name]" required>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Телефон:</label>
                            <input type="tel" class="form-control" name="residents[${residentCount}][phone]">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Имейл:</label>
                            <input type="email" class="form-control" name="residents[${residentCount}][email]">
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="residents[${residentCount}][is_owner]" id="resident${residentCount}_is_owner">
                            <label class="form-check-label" for="resident${residentCount}_is_owner">Собственик</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="residents[${residentCount}][is_primary]" id="resident${residentCount}_is_primary">
                            <label class="form-check-label" for="resident${residentCount}_is_primary">Основен обитател</label>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Дата на настаняване:</label>
                            <input type="date" class="form-control" name="residents[${residentCount}][move_in_date]" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Дата на напускане:</label>
                            <input type="date" class="form-control" name="residents[${residentCount}][move_out_date]">
                        </div>
                    </div>
                </div>
                <div class="form-group mt-2">
                    <label class="form-label">Бележки:</label>
                    <textarea class="form-control" name="residents[${residentCount}][notes]" rows="2"></textarea>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm mt-2" onclick="this.parentElement.remove()">
                    <i class="fas fa-trash"></i> Премахни обитател
                </button>
            `;
            container.appendChild(newEntry);
            residentCount++;
        }

        function addEditResidentEntry() {
            const container = document.getElementById('edit-residents-container');
            const newEntry = document.createElement('div');
            newEntry.className = 'resident-entry mb-3 p-3 border rounded';
            newEntry.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Име:</label>
                            <input type="text" class="form-control" name="residents[${editResidentCount}][first_name]" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Фамилия:</label>
                            <input type="text" class="form-control" name="residents[${editResidentCount}][last_name]" required>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Телефон:</label>
                            <input type="tel" class="form-control" name="residents[${editResidentCount}][phone]">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Имейл:</label>
                            <input type="email" class="form-control" name="residents[${editResidentCount}][email]">
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="residents[${editResidentCount}][is_owner]" id="edit_resident${editResidentCount}_is_owner">
                            <label class="form-check-label" for="edit_resident${editResidentCount}_is_owner">Собственик</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="residents[${editResidentCount}][is_primary]" id="edit_resident${editResidentCount}_is_primary">
                            <label class="form-check-label" for="edit_resident${editResidentCount}_is_primary">Основен обитател</label>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Дата на настаняване:</label>
                            <input type="date" class="form-control" name="residents[${editResidentCount}][move_in_date]" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Дата на напускане:</label>
                            <input type="date" class="form-control" name="residents[${editResidentCount}][move_out_date]">
                        </div>
                    </div>
                </div>
                <div class="form-group mt-2">
                    <label class="form-label">Бележки:</label>
                    <textarea class="form-control" name="residents[${editResidentCount}][notes]" rows="2"></textarea>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm mt-2" onclick="this.parentElement.remove()">
                    <i class="fas fa-trash"></i> Премахни обитател
                </button>
            `;
            container.appendChild(newEntry);
            editResidentCount++;
        }
    </script>
</body>
</html>
