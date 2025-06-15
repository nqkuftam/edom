<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

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
                $total_apartments = (int)($_POST['total_apartments'] ?? 0);
                
                if (!empty($name) && !empty($address) && $floors > 0 && $total_apartments > 0) {
                    $stmt = $pdo->prepare("INSERT INTO buildings (name, address, floors, total_apartments) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $address, $floors, $total_apartments]);
                }
                break;
                
            case 'edit_building':
                $id = (int)($_POST['id'] ?? 0);
                $name = $_POST['name'] ?? '';
                $address = $_POST['address'] ?? '';
                $floors = (int)($_POST['floors'] ?? 0);
                $total_apartments = (int)($_POST['total_apartments'] ?? 0);
                
                if ($id > 0 && !empty($name) && !empty($address) && $floors > 0 && $total_apartments > 0) {
                    $stmt = $pdo->prepare("UPDATE buildings SET name = ?, address = ?, floors = ?, total_apartments = ? WHERE id = ?");
                    $stmt->execute([$name, $address, $floors, $total_apartments, $id]);
                }
                break;
                
            case 'delete_building':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare("DELETE FROM buildings WHERE id = ?");
                    $stmt->execute([$id]);
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
    <title>Управление на сгради | Електронен Домоуправител</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #fff;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        .buildings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .building-card {
            background-color: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .building-card h3 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
        .building-card p {
            margin: 0.5rem 0;
            color: #666;
        }
        .building-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-edit {
            background-color: #28a745;
            color: white;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .back-link {
            color: #666;
            text-decoration: none;
            margin-bottom: 1rem;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Управление на сгради</h1>
    </div>

    <div class="container">
        <a href="index.php" class="back-link">← Назад към таблото</a>
        
        <button class="btn btn-primary" onclick="showAddModal()">Добави нова сграда</button>
        
        <div class="buildings-grid">
            <?php foreach ($buildings as $building): ?>
            <div class="building-card">
                <h3><?php echo htmlspecialchars($building['name']); ?></h3>
                <p><strong>Адрес:</strong> <?php echo htmlspecialchars($building['address']); ?></p>
                <p><strong>Етажи:</strong> <?php echo $building['floors']; ?></p>
                <div class="building-actions">
                    <button class="btn btn-edit" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($building)); ?>)">Редактирай</button>
                    <button class="btn btn-danger" onclick="deleteBuilding(<?php echo $building['id']; ?>)">Изтрий</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Модален прозорец за добавяне -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h2>Добави нова сграда</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_building">
                <div class="form-group">
                    <label for="name">Име на集团有限公司:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="address">Адрес:</label>
                    <input type="text" id="address" name="address" required>
                </div>
                <div class="form-group">
                    <label for="floors">Брой етажи:</label>
                    <input type="number" id="floors" name="floors" min="1" required>
                </div>
                <div class="form-group">
                    <label for="total_apartments">Общ брой апартаменти:</label>
                    <input type="number" id="total_apartments" name="total_apartments" min="1" required>
                </div>
                <button type="submit" class="btn btn-primary">Добави</button>
                <button type="button" class="btn btn-danger" onclick="hideModal('addModal')">Отказ</button>
            </form>
        </div>
    </div>

    <!-- Модален прозорец за редактиране -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2>Редактирай сграда</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_building">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label for="edit_name">Име на集团有限公司:</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit_address">Адрес:</label>
                    <input type="text" id="edit_address" name="address" required>
                </div>
                <div class="form-group">
                    <label for="edit_floors">Брой етажи:</label>
                    <input type="number" id="edit_floors" name="floors" min="1" required>
                </div>
                <div class="form-group">
                    <label for="edit_total_apartments">Общ брой апартаменти:</label>
                    <input type="number" id="edit_total_apartments" name="total_apartments" min="1" required>
                </div>
                <button type="submit" class="btn btn-primary">Запази</button>
                <button type="button" class="btn btn-danger" onclick="hideModal('editModal')">Отказ</button>
            </form>
        </div>
    </div>

    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function showEditModal(building) {
            document.getElementById('edit_id').value = building.id;
            document.getElementById('edit_name').value = building.name;
            document.getElementById('edit_address').value = building.address;
            document.getElementById('edit_floors').value = building.floors;
            document.getElementById('edit_total_apartments').value = building.total_apartments;
            document.getElementById('editModal').style.display = 'block';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function deleteBuilding(id) {
            if (confirm('Сигурни ли сте, че искате да изтриете тази сграда?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_building">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Затваряне на модалните прозорци при клик извън тях
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
