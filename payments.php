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
            case 'add_payment':
                $apartment_id = (int)($_POST['apartment_id'] ?? 0);
                $fee_id = (int)($_POST['fee_id'] ?? 0);
                $amount = (float)($_POST['amount'] ?? 0);
                $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
                $payment_method = $_POST['payment_method'] ?? '';
                $notes = $_POST['notes'] ?? '';
                
                if ($apartment_id > 0 && $fee_id > 0 && $amount > 0) {
                    $stmt = $pdo->prepare("INSERT INTO payments (apartment_id, fee_id, amount, payment_date, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$apartment_id, $fee_id, $amount, $payment_date, $payment_method, $notes]);
                }
                break;
                
            case 'edit_payment':
                $id = (int)($_POST['id'] ?? 0);
                $apartment_id = (int)($_POST['apartment_id'] ?? 0);
                $fee_id = (int)($_POST['fee_id'] ?? 0);
                $amount = (float)($_POST['amount'] ?? 0);
                $payment_date = $_POST['payment_date'] ?? '';
                $payment_method = $_POST['payment_method'] ?? '';
                $notes = $_POST['notes'] ?? '';
                
                if ($id > 0 && $apartment_id > 0 && $fee_id > 0 && $amount > 0) {
                    $stmt = $pdo->prepare("UPDATE payments SET apartment_id = ?, fee_id = ?, amount = ?, payment_date = ?, payment_method = ?, notes = ? WHERE id = ?");
                    $stmt->execute([$apartment_id, $fee_id, $amount, $payment_date, $payment_method, $notes, $id]);
                }
                break;
                
            case 'delete_payment':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
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

// Вземане на всички неплатени такси
$stmt = $pdo->query("
    SELECT f.*, a.number as apartment_number, b.name as building_name 
    FROM fees f 
    JOIN apartments a ON f.apartment_id = a.id 
    JOIN buildings b ON a.building_id = b.id 
    WHERE f.id NOT IN (SELECT fee_id FROM payments)
    ORDER BY f.year DESC, f.month DESC, b.name, a.number
");
$unpaid_fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Вземане на всички плащания
$stmt = $pdo->query("
    SELECT p.*, a.number as apartment_number, b.name as building_name, f.month, f.year 
    FROM payments p 
    JOIN apartments a ON p.apartment_id = a.id 
    JOIN buildings b ON a.building_id = b.id 
    JOIN fees f ON p.fee_id = f.id 
    ORDER BY p.payment_date DESC, b.name, a.number
");
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Масив с методи за плащане
$payment_methods = ['В брой', 'Банков превод', 'Карта', 'Друг'];
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Плащания | Електронен Домоуправител</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .payments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .payment-card {
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .payment-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: #fff;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Електронен Домоуправител</h1>
        <a href="logout.php" class="logout">Изход</a>
    </div>

    <div class="container">
        <a href="index.php" class="back-link">← Назад към таблото</a>
        
        <button class="btn btn-primary" onclick="showAddModal()">Добави ново плащане</button>
        
        <div class="payments-grid">
            <?php foreach ($payments as $payment): ?>
            <div class="payment-card">
                <h3>Плащане за <?php echo htmlspecialchars($payment['month']); ?> <?php echo $payment['year']; ?></h3>
                <p><strong>Сграда:</strong> <?php echo htmlspecialchars($payment['building_name']); ?></p>
                <p><strong>Апартамент:</strong> <?php echo htmlspecialchars($payment['apartment_number']); ?></p>
                <p><strong>Сума:</strong> <?php echo number_format($payment['amount'], 2); ?> лв.</p>
                <p><strong>Дата на плащане:</strong> <?php echo date('d.m.Y', strtotime($payment['payment_date'])); ?></p>
                <p><strong>Метод на плащане:</strong> <?php echo htmlspecialchars($payment['payment_method']); ?></p>
                <p><strong>Бележки:</strong> <?php echo htmlspecialchars($payment['notes']); ?></p>
                <div class="payment-actions">
                    <button class="btn btn-edit" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($payment)); ?>)">Редактирай</button>
                    <button class="btn btn-danger" onclick="deletePayment(<?php echo $payment['id']; ?>)">Изтрий</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Модален прозорец за добавяне -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h2>Добави ново плащане</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_payment">
                <div class="form-group">
                    <label for="apartment_id">Апартамент:</label>
                    <select id="apartment_id" name="apartment_id" required onchange="updateUnpaidFees()">
                        <option value="">Изберете апартамент</option>
                        <?php foreach ($apartments as $apartment): ?>
                        <option value="<?php echo $apartment['id']; ?>">
                            <?php echo htmlspecialchars($apartment['building_name'] . ' - Апартамент ' . $apartment['number']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="fee_id">Такса:</label>
                    <select id="fee_id" name="fee_id" required>
                        <option value="">Първо изберете апартамент</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="amount">Сума (лв.):</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="payment_date">Дата на плащане:</label>
                    <input type="date" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="payment_method">Метод на плащане:</label>
                    <select id="payment_method" name="payment_method" required>
                        <?php foreach ($payment_methods as $method): ?>
                        <option value="<?php echo $method; ?>"><?php echo $method; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="notes">Бележки:</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Добави</button>
                <button type="button" class="btn btn-danger" onclick="hideModal('addModal')">Отказ</button>
            </form>
        </div>
    </div>

    <!-- Модален прозорец за редактиране -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2>Редактирай плащане</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_payment">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label for="edit_apartment_id">Апартамент:</label>
                    <select id="edit_apartment_id" name="apartment_id" required>
                        <?php foreach ($apartments as $apartment): ?>
                        <option value="<?php echo $apartment['id']; ?>">
                            <?php echo htmlspecialchars($apartment['building_name'] . ' - Апартамент ' . $apartment['number']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_fee_id">Такса:</label>
                    <select id="edit_fee_id" name="fee_id" required>
                        <?php foreach ($unpaid_fees as $fee): ?>
                        <option value="<?php echo $fee['id']; ?>">
                            <?php echo htmlspecialchars($fee['building_name'] . ' - Апартамент ' . $fee['apartment_number'] . ' - ' . $fee['month'] . ' ' . $fee['year']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_amount">Сума (лв.):</label>
                    <input type="number" id="edit_amount" name="amount" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit_payment_date">Дата на плащане:</label>
                    <input type="date" id="edit_payment_date" name="payment_date" required>
                </div>
                <div class="form-group">
                    <label for="edit_payment_method">Метод на плащане:</label>
                    <select id="edit_payment_method" name="payment_method" required>
                        <?php foreach ($payment_methods as $method): ?>
                        <option value="<?php echo $method; ?>"><?php echo $method; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_notes">Бележки:</label>
                    <textarea id="edit_notes" name="notes" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Запази</button>
                <button type="button" class="btn btn-danger" onclick="hideModal('editModal')">Отказ</button>
            </form>
        </div>
    </div>

    <script>
        // Функция за показване на модален прозорец
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        // Функция за скриване на модален прозорец
        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Функция за показване на модален прозорец за добавяне
        function showAddModal() {
            showModal('addModal');
        }

        // Функция за показване на модален прозорец за редактиране
        function showEditModal(payment) {
            document.getElementById('edit_id').value = payment.id;
            document.getElementById('edit_apartment_id').value = payment.apartment_id;
            document.getElementById('edit_fee_id').value = payment.fee_id;
            document.getElementById('edit_amount').value = payment.amount;
            document.getElementById('edit_payment_date').value = payment.payment_date;
            document.getElementById('edit_payment_method').value = payment.payment_method;
            document.getElementById('edit_notes').value = payment.notes;
            showModal('editModal');
        }

        // Функция за изтриване на плащане
        function deletePayment(id) {
            if (confirm('Сигурни ли сте, че искате да изтриете това плащане?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_payment">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Функция за обновяване на неплатените такси при избор на апартамент
        function updateUnpaidFees() {
            const apartmentId = document.getElementById('apartment_id').value;
            const feeSelect = document.getElementById('fee_id');
            feeSelect.innerHTML = '<option value="">Зареждане...</option>';

            if (apartmentId) {
                fetch(`get_unpaid_fees.php?apartment_id=${apartmentId}`)
                    .then(response => response.json())
                    .then(fees => {
                        feeSelect.innerHTML = '<option value="">Изберете такса</option>';
                        fees.forEach(fee => {
                            feeSelect.innerHTML += `
                                <option value="${fee.id}">
                                    ${fee.month} ${fee.year} - ${fee.amount} лв.
                                </option>
                            `;
                        });
                    })
                    .catch(error => {
                        console.error('Грешка при зареждане на таксите:', error);
                        feeSelect.innerHTML = '<option value="">Грешка при зареждане</option>';
                    });
            } else {
                feeSelect.innerHTML = '<option value="">Първо изберете апартамент</option>';
            }
        }
    </script>
</body>
</html>
