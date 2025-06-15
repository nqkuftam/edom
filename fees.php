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
            case 'add_fee':
                $apartment_id = (int)($_POST['apartment_id'] ?? 0);
                $month = $_POST['month'] ?? '';
                $year = (int)($_POST['year'] ?? 0);
                $amount = (float)($_POST['amount'] ?? 0);
                $description = $_POST['description'] ?? '';
                
                if ($apartment_id > 0 && !empty($month) && $year > 0 && $amount > 0) {
                    $stmt = $pdo->prepare("INSERT INTO fees (apartment_id, month, year, amount, description) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$apartment_id, $month, $year, $amount, $description]);
                }
                break;
                
            case 'edit_fee':
                $id = (int)($_POST['id'] ?? 0);
                $apartment_id = (int)($_POST['apartment_id'] ?? 0);
                $month = $_POST['month'] ?? '';
                $year = (int)($_POST['year'] ?? 0);
                $amount = (float)($_POST['amount'] ?? 0);
                $description = $_POST['description'] ?? '';
                
                if ($id > 0 && $apartment_id > 0 && !empty($month) && $year > 0 && $amount > 0) {
                    $stmt = $pdo->prepare("UPDATE fees SET apartment_id = ?, month = ?, year = ?, amount = ?, description = ? WHERE id = ?");
                    $stmt->execute([$apartment_id, $month, $year, $amount, $description, $id]);
                }
                break;
                
            case 'delete_fee':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare("DELETE FROM fees WHERE id = ?");
                    $stmt->execute([$id]);
                }
                break;
        }
    }
}

// Вземане на всички апартаменти за dropdown менюто
$stmt = $pdo->query("
    SELECT a.*, b.name as building_name 
    FROM apartments a 
    JOIN buildings b ON a.building_id = b.id 
    ORDER BY b.name, a.number
");
$apartments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Вземане на всички такси
$stmt = $pdo->query("
    SELECT f.*, a.number as apartment_number, b.name as building_name 
    FROM fees f 
    JOIN apartments a ON f.apartment_id = a.id 
    JOIN buildings b ON a.building_id = b.id 
    ORDER BY f.year DESC, f.month DESC, b.name, a.number
");
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Масив с месеците
$months = [
    'Януари', 'Февруари', 'Март', 'Април', 'Май', 'Юни',
    'Юли', 'Август', 'Септември', 'Октомври', 'Ноември', 'Декември'
];
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление на такси | Електронен Домоуправител</title>
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
        .fees-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .fee-card {
            background-color: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .fee-card h3 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
        .fee-card p {
            margin: 0.5rem 0;
            color: #666;
        }
        .fee-actions {
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
        <h1>Управление на такси</h1>
    </div>

    <div class="container">
        <a href="index.php" class="back-link">← Назад към таблото</a>
        
        <button class="btn btn-primary" onclick="showAddModal()">Добави нова такса</button>
        
        <div class="fees-grid">
            <?php foreach ($fees as $fee): ?>
            <div class="fee-card">
                <h3>Такса за <?php echo htmlspecialchars($fee['month']); ?> <?php echo $fee['year']; ?></h3>
                <p><strong>Сграда:</strong> <?php echo htmlspecialchars($fee['building_name']); ?></p>
                <p><strong>Апартамент:</strong> <?php echo htmlspecialchars($fee['apartment_number']); ?></p>
                <p><strong>Сума:</strong> <?php echo number_format($fee['amount'], 2); ?> лв.</p>
                <p><strong>Описание:</strong> <?php echo htmlspecialchars($fee['description']); ?></p>
                <div class="fee-actions">
                    <button class="btn btn-edit" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($fee)); ?>)">Редактирай</button>
                    <button class="btn btn-danger" onclick="deleteFee(<?php echo $fee['id']; ?>)">Изтрий</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Модален прозорец за добавяне -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h2>Добави нова такса</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_fee">
                <div class="form-group">
                    <label for="apartment_id">Апартамент:</label>
                    <select id="apartment_id" name="apartment_id" required>
                        <option value="">Изберете апартамент</option>
                        <?php foreach ($apartments as $apartment): ?>
                        <option value="<?php echo $apartment['id']; ?>">
                            <?php echo htmlspecialchars($apartment['building_name'] . ' - Апартамент ' . $apartment['number']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="month">Месец:</label>
                    <select id="month" name="month" required>
                        <option value="">Изберете месец</option>
                        <?php foreach ($months as $month): ?>
                        <option value="<?php echo $month; ?>"><?php echo $month; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="year">Година:</label>
                    <input type="number" id="year" name="year" min="2000" max="2100" value="<?php echo date('Y'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="amount">Сума (лв.):</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="description">Описание:</label>
                    <input type="text" id="description" name="description">
                </div>
                <button type="submit" class="btn btn-primary">Добави</button>
                <button type="button" class="btn btn-danger" onclick="hideModal('addModal')">Отказ</button>
            </form>
        </div>
    </div>

    <!-- Модален прозорец за редактиране -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2>Редактирай такса</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_fee">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label for="edit_apartment_id">Апартамент:</label>
                    <select id="edit_apartment_id" name="apartment_id" required>
                        <option value="">Изберете апартамент</option>
                        <?php foreach ($apartments as $apartment): ?>
                        <option value="<?php echo $apartment['id']; ?>">
                            <?php echo htmlspecialchars($apartment['building_name'] . ' - Апартамент ' . $apartment['number']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_month">Месец:</label>
                    <select id="edit_month" name="month" required>
                        <option value="">Изберете месец</option>
                        <?php foreach ($months as $month): ?>
                        <option value="<?php echo $month; ?>"><?php echo $month; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_year">Година:</label>
                    <input type="number" id="edit_year" name="year" min="2000" max="2100" required>
                </div>
                <div class="form-group">
                    <label for="edit_amount">Сума (лв.):</label>
                    <input type="number" id="edit_amount" name="amount" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit_description">Описание:</label>
                    <input type="text" id="edit_description" name="description">
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

        function showEditModal(fee) {
            document.getElementById('edit_id').value = fee.id;
            document.getElementById('edit_apartment_id').value = fee.apartment_id;
            document.getElementById('edit_month').value = fee.month;
            document.getElementById('edit_year').value = fee.year;
            document.getElementById('edit_amount').value = fee.amount;
            document.getElementById('edit_description').value = fee.description;
            document.getElementById('editModal').style.display = 'block';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function deleteFee(id) {
            if (confirm('Сигурни ли сте, че искате да изтриете тази такса?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_fee">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
