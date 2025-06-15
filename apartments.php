<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Проверка дали потребителят е логнат
if (!isset($_SESSION['user_id']) || !isLoggedIn()) {
    $_SESSION['error'] = 'Моля, влезте в системата за да продължите.';
    header('Location: login.php');
    exit();
}
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/building_selector.php';
require_once 'includes/error_handler.php';
require_once 'includes/navigation.php';
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
    </script>
</body>
</html>
