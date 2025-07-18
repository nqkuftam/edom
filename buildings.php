<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/navigation.php';
require_once 'includes/building_selector.php';
require_once 'includes/error_handler.php';

// Проверка дали потребителят е логнат
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Обработка на POST заявки
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_building':
                $name = $_POST['name'] ?? '';
                $address = $_POST['address'] ?? '';
                $floors = (int)($_POST['floors'] ?? 0);
                $total_properties = (int)($_POST['total_properties'] ?? 0);
                
                if (!empty($name) && !empty($address) && $floors > 0 && $total_properties > 0) {
                    $stmt = $pdo->prepare("INSERT INTO buildings (name, address, floors, total_properties) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $address, $floors, $total_properties]);
                    $success = showSuccess('Сградата е добавена успешно.');
                    header('Location: buildings.php');
                    exit();
                }
                break;
                
            case 'edit_building':
                $id = (int)($_POST['id'] ?? 0);
                $name = $_POST['name'] ?? '';
                $address = $_POST['address'] ?? '';
                $floors = (int)($_POST['floors'] ?? 0);
                $total_properties = (int)($_POST['total_properties'] ?? 0);
                
                if ($id > 0 && !empty($name) && !empty($address) && $floors > 0 && $total_properties > 0) {
                    $stmt = $pdo->prepare("UPDATE buildings SET name = ?, address = ?, floors = ?, total_properties = ? WHERE id = ?");
                    $stmt->execute([$name, $address, $floors, $total_properties, $id]);
                    $success = showSuccess('Сградата е редактирана успешно.');
                    header('Location: buildings.php');
                    exit();
                }
                break;
                
            case 'delete_building':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare("DELETE FROM buildings WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = showSuccess('Сградата е изтрита успешно.');
                    header('Location: buildings.php');
                    exit();
                }
                break;
        }
    }
}

// Вземане на всички сгради
$stmt = $pdo->query("SELECT * FROM buildings ORDER BY name");
$buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сгради | Електронен Домоуправител</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once 'includes/styles.php'; ?>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-city"></i> Сгради</h1>
            <?php echo renderNavigation('buildings'); ?>
        </div>
    </div>
    <div class="container">
        <a href="index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Назад към таблото</a>
        <?php echo renderBuildingSelector(); ?>
        <button class="btn btn-primary mb-3" onclick="showAddModal()"><i class="fas fa-plus"></i> Добави нова сграда</button>
        <div class="grid">
            <?php foreach ($buildings as $building): ?>
            <div class="card">
                <h3><i class="fas fa-building"></i> <?php echo htmlspecialchars($building['name']); ?></h3>
                <p><strong><i class="fas fa-map-marker-alt"></i> Адрес:</strong> <?php echo htmlspecialchars($building['address']); ?></p>
                <p><strong><i class="fas fa-layer-group"></i> Етажи:</strong> <?php echo $building['floors']; ?></p>
                <p><strong><i class="fas fa-door-open"></i> Имоти:</strong> <?php echo $building['total_properties']; ?></p>
                <div class="payment-actions">
                    <button class="btn btn-warning" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($building)); ?>)"><i class="fas fa-edit"></i> Редактирай</button>
                    <button class="btn btn-danger" onclick="deleteBuilding(<?php echo $building['id']; ?>)"><i class="fas fa-trash"></i> Изтрий</button>
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
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Добави нова сграда</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_building">
                        <div class="form-group">
                            <label for="name" class="form-label">Име на сграда:</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="address" class="form-label">Адрес:</label>
                            <input type="text" class="form-control" id="address" name="address" required>
                        </div>
                        <div class="form-group">
                            <label for="floors" class="form-label">Брой етажи:</label>
                            <input type="number" class="form-control" id="floors" name="floors" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="total_properties" class="form-label">Общ брой имоти:</label>
                            <input type="number" class="form-control" id="total_properties" name="total_properties" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="generate_day" class="form-label">Ден за генериране на такси (1-28):</label>
                            <input type="number" class="form-control" id="generate_day" name="generate_day" min="1" max="28" value="1" required>
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
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Редактирай сграда</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_building">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="form-group">
                            <label for="edit_name" class="form-label">Име на сграда:</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_address" class="form-label">Адрес:</label>
                            <input type="text" class="form-control" id="edit_address" name="address" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_floors" class="form-label">Брой етажи:</label>
                            <input type="number" class="form-control" id="edit_floors" name="floors" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_total_properties" class="form-label">Общ брой имоти:</label>
                            <input type="number" class="form-control" id="edit_total_properties" name="total_properties" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="generate_day" class="form-label">Ден за генериране на такси (1-28):</label>
                            <input type="number" class="form-control" id="generate_day" name="generate_day" min="1" max="28" value="1" required>
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
        function showEditModal(building) {
            document.getElementById('edit_id').value = building.id;
            document.getElementById('edit_name').value = building.name;
            document.getElementById('edit_address').value = building.address;
            document.getElementById('edit_floors').value = building.floors;
            document.getElementById('edit_total_properties').value = building.total_properties;
            var modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        }
        function deleteBuilding(id) {
            if (confirm('Сигурни ли сте, че искате да изтриете тази сграда?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_building">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
