<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/building_selector.php';
require_once 'includes/error_handler.php';
require_once 'includes/navigation.php';
require_once 'includes/styles.php';

// Проверка дали потребителят е логнат
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

try {
    // Вземане на текущата сграда
    $currentBuilding = getCurrentBuilding();
    
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
                        $success = showSuccess('Плащането е добавено успешно.');
                    } else {
                        $error = showError('Моля, попълнете всички задължителни полета.');
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
                        $success = showSuccess('Плащането е редактирано успешно.');
                    } else {
                        $error = showError('Моля, попълнете всички задължителни полета.');
                    }
                    break;
                    
                case 'delete_payment':
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id > 0) {
                        $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
                        $stmt->execute([$id]);
                        $success = showSuccess('Плащането е изтрито успешно.');
                    }
                    break;
            }
        }
    }
    
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
    
    // Вземане на неплатените такси според избраната сграда
    $query = "
        SELECT f.*, a.number as apartment_number, b.name as building_name 
        FROM fees f 
        JOIN apartments a ON f.apartment_id = a.id 
        JOIN buildings b ON a.building_id = b.id 
        WHERE f.id NOT IN (SELECT fee_id FROM payments)
    ";
    $params = [];
    
    if ($currentBuilding) {
        $query .= " AND a.building_id = ?";
        $params[] = $currentBuilding['id'];
    }
    
    $query .= " ORDER BY f.year DESC, f.month DESC, b.name, a.number";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $unpaid_fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Вземане на плащанията според избраната сграда
    $query = "
        SELECT p.*, a.number as apartment_number, b.name as building_name, f.month, f.year 
        FROM payments p 
        JOIN apartments a ON p.apartment_id = a.id 
        JOIN buildings b ON a.building_id = b.id 
        JOIN fees f ON p.fee_id = f.id 
    ";
    $params = [];
    
    if ($currentBuilding) {
        $query .= " WHERE a.building_id = ?";
        $params[] = $currentBuilding['id'];
    }
    
    $query .= " ORDER BY p.payment_date DESC, b.name, a.number";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Масив с методи за плащане
    $payment_methods = ['В брой', 'Банков превод', 'Карта', 'Друг'];
    
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
    <title>Плащания | Електронен Домоуправител</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Плащания</h1>
            <?php echo renderNavigation('payments'); ?>
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
            <i class="fas fa-plus"></i> Добави ново плащане
        </button>
        
        <div class="grid">
            <?php foreach ($payments as $payment): ?>
            <div class="card">
                <h3><i class="fas fa-money-check-alt"></i> Плащане за <?php echo htmlspecialchars($payment['month']); ?> <?php echo $payment['year']; ?></h3>
                <p><strong><i class="fas fa-building"></i> Сграда:</strong> <?php echo htmlspecialchars($payment['building_name']); ?></p>
                <p><strong><i class="fas fa-home"></i> Апартамент:</strong> <?php echo htmlspecialchars($payment['apartment_number']); ?></p>
                <p><strong><i class="fas fa-money-bill-wave"></i> Сума:</strong> <?php echo number_format($payment['amount'], 2); ?> лв.</p>
                <p><strong><i class="fas fa-calendar"></i> Дата на плащане:</strong> <?php echo date('d.m.Y', strtotime($payment['payment_date'])); ?></p>
                <p><strong><i class="fas fa-credit-card"></i> Метод на плащане:</strong> <?php echo htmlspecialchars($payment['payment_method']); ?></p>
                <?php if ($payment['notes']): ?>
                <p><strong><i class="fas fa-info-circle"></i> Бележки:</strong> <?php echo htmlspecialchars($payment['notes']); ?></p>
                <?php endif; ?>
                <div class="payment-actions">
                    <button class="btn btn-warning" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($payment)); ?>)">
                        <i class="fas fa-edit"></i> Редактирай
                    </button>
                    <button class="btn btn-danger" onclick="deletePayment(<?php echo $payment['id']; ?>)">
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
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Добави ново плащане</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_payment">
                        <div class="form-group">
                            <label for="apartment_id" class="form-label">Апартамент:</label>
                            <select class="form-control" id="apartment_id" name="apartment_id" required onchange="updateUnpaidFees()">
                                <option value="">Изберете апартамент</option>
                                <?php foreach ($apartments as $apartment): ?>
                                <option value="<?php echo $apartment['id']; ?>">
                                    <?php echo htmlspecialchars($apartment['building_name'] . ' - Апартамент ' . $apartment['number']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fee_id" class="form-label">Такса:</label>
                            <select class="form-control" id="fee_id" name="fee_id" required>
                                <option value="">Първо изберете апартамент</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="amount" class="form-label">Сума (лв.):</label>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="payment_date" class="form-label">Дата на плащане:</label>
                            <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="payment_method" class="form-label">Метод на плащане:</label>
                            <select class="form-control" id="payment_method" name="payment_method" required>
                                <?php foreach ($payment_methods as $method): ?>
                                <option value="<?php echo $method; ?>"><?php echo $method; ?></option>
                                <?php endforeach; ?>
                            </select>
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
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Редактирай плащане</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_payment">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="form-group">
                            <label for="edit_apartment_id" class="form-label">Апартамент:</label>
                            <select class="form-control" id="edit_apartment_id" name="apartment_id" required>
                                <?php foreach ($apartments as $apartment): ?>
                                <option value="<?php echo $apartment['id']; ?>">
                                    <?php echo htmlspecialchars($apartment['building_name'] . ' - Апартамент ' . $apartment['number']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_fee_id" class="form-label">Такса:</label>
                            <select class="form-control" id="edit_fee_id" name="fee_id" required>
                                <?php foreach ($unpaid_fees as $fee): ?>
                                <option value="<?php echo $fee['id']; ?>">
                                    <?php echo htmlspecialchars($fee['building_name'] . ' - Апартамент ' . $fee['apartment_number'] . ' - ' . $fee['month'] . ' ' . $fee['year']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_amount" class="form-label">Сума (лв.):</label>
                            <input type="number" class="form-control" id="edit_amount" name="amount" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_payment_date" class="form-label">Дата на плащане:</label>
                            <input type="date" class="form-control" id="edit_payment_date" name="payment_date" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_payment_method" class="form-label">Метод на плащане:</label>
                            <select class="form-control" id="edit_payment_method" name="payment_method" required>
                                <?php foreach ($payment_methods as $method): ?>
                                <option value="<?php echo $method; ?>"><?php echo $method; ?></option>
                                <?php endforeach; ?>
                            </select>
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
        // Масив с неплатените такси
        const unpaidFees = <?php echo json_encode($unpaid_fees); ?>;

        function showAddModal() {
            var modal = new bootstrap.Modal(document.getElementById('addModal'));
            modal.show();
        }

        function showEditModal(payment) {
            document.getElementById('edit_id').value = payment.id;
            document.getElementById('edit_apartment_id').value = payment.apartment_id;
            document.getElementById('edit_fee_id').value = payment.fee_id;
            document.getElementById('edit_amount').value = payment.amount;
            document.getElementById('edit_payment_date').value = payment.payment_date;
            document.getElementById('edit_payment_method').value = payment.payment_method;
            document.getElementById('edit_notes').value = payment.notes;
            
            var modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        }

        function updateUnpaidFees() {
            const apartmentId = document.getElementById('apartment_id').value;
            const feeSelect = document.getElementById('fee_id');
            feeSelect.innerHTML = '<option value="">Изберете такса</option>';
            
            if (apartmentId) {
                const apartmentFees = unpaidFees.filter(fee => fee.apartment_id == apartmentId);
                apartmentFees.forEach(fee => {
                    const option = document.createElement('option');
                    option.value = fee.id;
                    option.textContent = `${fee.month} ${fee.year} - ${fee.amount} лв.`;
                    feeSelect.appendChild(option);
                });
            }
        }

        function deletePayment(id) {
            if (confirm('Сигурни ли сте, че искате да изтриете това плащане?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_payment">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
