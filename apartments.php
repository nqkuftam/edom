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
            case 'add_apartment':
                $building_id = (int)($_POST['building_id'] ?? 0);
                $number = $_POST['number'] ?? '';
                $floor = (int)($_POST['floor'] ?? 0);
                $owner_name = $_POST['owner_name'] ?? '';
                $owner_phone = $_POST['owner_phone'] ?? '';
                $owner_email = $_POST['owner_email'] ?? '';
                $area = (float)($_POST['area'] ?? 0);
                
                if ($building_id > 0 && !empty($number) && $floor >= 0) {
                    $stmt = $pdo->prepare("INSERT INTO apartments (building_id, number, floor, owner_name, owner_phone, owner_email, area) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$building_id, $number, $floor, $owner_name, $owner_phone, $owner_email, $area]);
                }
                break;
                
            case 'edit_apartment':
                $id = (int)($_POST['id'] ?? 0);
                $building_id = (int)($_POST['building_id'] ?? 0);
                $number = $_POST['number'] ?? '';
                $floor = (int)($_POST['floor'] ?? 0);
                $owner_name = $_POST['owner_name'] ?? '';
                $owner_phone = $_POST['owner_phone'] ?? '';
                $owner_email = $_POST['owner_email'] ?? '';
                $area = (float)($_POST['area'] ?? 0);
                
                if ($id > 0 && $building_id > 0 && !empty($number) && $floor >= 0) {
                    $stmt = $pdo->prepare("UPDATE apartments SET building_id = ?, number = ?, floor = ?, owner_name = ?, owner_phone = ?, owner_email = ?, area = ? WHERE id = ?");
                    $stmt->execute([$building_id, $number, $floor, $owner_name, $owner_phone, $owner_email, $area, $id]);
                }
                break;
                
            case 'delete_apartment':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare("DELETE FROM apartments WHERE id = ?");
                    $stmt->execute([$id]);
                }
                break;
        }
    }
}

// Вземане на всички сгради за dropdown менюто
$stmt = $pdo->query("SELECT * FROM buildings ORDER BY name");
$buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Вземане на всички апартаменти
$stmt = $pdo->query("
    SELECT a.*, b.name as building_name 
    FROM apartments a 
    JOIN buildings b ON a.building_id = b.id 
    ORDER BY b.name, a.floor, a.number
");
$apartments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление на апартаменти | Електронен Домоуправител</title>
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
        .apartments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .apartment-card {
            background-color: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .apartment-card h3 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
        .apartment-card p {
            margin: 0.5rem 0;
            color: #666;
        }
        .apartment-actions {
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
        .form-group input, .form-group select {
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
        <h1>Управление на апартаменти</h1>
    </div>

    <div class="container">
        <a href="index.php" class="back-link">← Назад към таблото</a>
        
        <button class="btn btn-primary" onclick="showAddModal()">Добави нов апартамент</button>
        
        <div class="apartments-grid">
            <?php foreach ($apartments as $apartment): ?>
            <div class="apartment-card">
                <h3>Апартамент <?php echo htmlspecialchars($apartment['number']); ?></h3>
                <p><strong>Сграда:</strong> <?php echo htmlspecialchars($apartment['building_name']); ?></p>
                <p><strong>Етаж:</strong> <?php echo $apartment['floor']; ?></p>
                <p><strong>Площ:</strong> <?php echo $apartment['area']; ?> м²</p>
                <p><strong>Собственик:</strong> <?php echo htmlspecialchars($apartment['owner_name']); ?></p>
                <p><strong>Телефон:</strong> <?php echo htmlspecialchars($apartment['owner_phone']); ?></p>
                <p><strong>Имейл:</strong> <?php echo htmlspecialchars($apartment['owner_email']); ?></p>
                <div class="apartment-actions">
                    <button class="btn btn-edit" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($apartment)); ?>)">Редактирай</button>
                    <button class="btn btn-danger" onclick="deleteApartment(<?php echo $apartment['id']; ?>)">Изтрий</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Модален прозорец за добавяне -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h2>Добави нов апартамент</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_apartment">
                <div class="form-group">
                    <label for="building_id">Сграда:</label>
                    <select id="building_id" name="building_id" required>
                        <option value="">Изберете сграда</option>
                        <?php foreach ($buildings as $building): ?>
                        <option value="<?php echo $building['id']; ?>"><?php echo htmlspecialchars($building['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="number">Номер на апартамента:</label>
                    <input type="text" id="number" name="number" required>
                </div>
                <div class="form-group">
                    <label for="floor">Етаж:</label>
                    <input type="number" id="floor" name="floor" min="0" required>
                </div>
                <div class="form-group">
                    <label for="area">Площ (м²):</label>
                    <input type="number" id="area" name="area" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="owner_name">Име на собственика:</label>
                    <input type="text" id="owner_name" name="owner_name">
                </div>
                <div class="form-group">
                    <label for="owner_phone">Телефон:</label>
                    <input type="tel" id="owner_phone" name="owner_phone">
                </div>
                <div class="form-group">
                    <label for="owner_email">Имейл:</label>
                    <input type="email" id="owner_email" name="owner_email">
                </div>
                <button type="submit" class="btn btn-primary">Добави</button>
                <button type="button" class="btn btn-danger" onclick="hideModal('addModal')">Отказ</button>
            </form>
        </div>
    </div>

    <!-- Модален прозорец за редактиране -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2>Редактирай апартамент</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_apartment">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label for="edit_building_id">Сграда:</label>
                    <select id="edit_building_id" name="building_id" required>
                        <?php foreach ($buildings as $building): ?>
                        <option value="<?php echo $building['id']; ?>"><?php echo htmlspecialchars($building['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_number">Номер на апартамента:</label>
                    <input type="text" id="edit_number" name="number" required>
                </div>
                <div class="form-group">
                    <label for="edit_floor">Етаж:</label>
                    <input type="number" id="edit_floor" name="floor" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit_area">Площ (м²):</label>
                    <input type="number" id="edit_area" name="area" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit_owner_name">Име на собственика:</label>
                    <input type="text" id="edit_owner_name" name="owner_name">
                </div>
                <div class="form-group">
                    <label for="edit_owner_phone">Телефон:</label>
                    <input type="tel" id="edit_owner_phone" name="owner_phone">
                </div>
                <div class="form-group">
                    <label for="edit_owner_email">Имейл:</label>
                    <input type="email" id="edit_owner_email" name="owner_email">
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

        function showEditModal(apartment) {
            document.getElementById('edit_id').value = apartment.id;
            document.getElementById('edit_building_id').value = apartment.building_id;
            document.getElementById('edit_number').value = apartment.number;
            document.getElementById('edit_floor').value = apartment.floor;
            document.getElementById('edit_area').value = apartment.area;
            document.getElementById('edit_owner_name').value = apartment.owner_name;
            document.getElementById('edit_owner_phone').value = apartment.owner_phone;
            document.getElementById('edit_owner_email').value = apartment.owner_email;
            document.getElementById('editModal').style.display = 'block';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function deleteApartment(id) {
            if (confirm('Сигурни ли сте, че искате да изтриете този апартамент?')) {
                const form = document.createElement('form');
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
