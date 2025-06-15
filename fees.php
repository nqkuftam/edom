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
                case 'add_fee':
                    $apartment_id = (int)($_POST['apartment_id'] ?? 0);
                    $month = $_POST['month'] ?? '';
                    $year = (int)($_POST['year'] ?? 0);
                    $amount = (float)($_POST['amount'] ?? 0);
                    $description = $_POST['description'] ?? '';
                    
                    if ($apartment_id > 0 && !empty($month) && $year > 0 && $amount > 0) {
                        $stmt = $pdo->prepare("INSERT INTO fees (apartment_id, month, year, amount, description) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$apartment_id, $month, $year, $amount, $description]);
                        $success = showSuccess('Таксата е добавена успешно.');
                    } else {
                        $error = showError('Моля, попълнете всички задължителни полета.');
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
                        $success = showSuccess('Таксата е редактирана успешно.');
                    } else {
                        $error = showError('Моля, попълнете всички задължителни полета.');
                    }
                    break;
                    
                case 'delete_fee':
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id > 0) {
                        $stmt = $pdo->prepare("DELETE FROM fees WHERE id = ?");
                        $stmt->execute([$id]);
                        $success = showSuccess('Таксата е изтрита успешно.');
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
    
    // Вземане на таксите според избраната сграда
    $query = "
        SELECT f.*, a.number as apartment_number, b.name as building_name 
        FROM fees f 
        JOIN apartments a ON f.apartment_id = a.id 
        JOIN buildings b ON a.building_id = b.id 
    ";
    $params = [];
    
    if ($currentBuilding) {
        $query .= " WHERE a.building_id = ?";
        $params[] = $currentBuilding['id'];
    }
    
    $query .= " ORDER BY f.year DESC, f.month DESC, b.name, a.number";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Масив с месеци
    $months = [
        'Януари', 'Февруари', 'Март', 'Април', 'Май', 'Юни',
        'Юли', 'Август', 'Септември', 'Октомври', 'Ноември', 'Декември'
    ];
    
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
    <title>Такси | Електронен Домоуправител</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Такси</h1>
            <?php echo renderNavigation('fees'); ?>
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
            <i class="fas fa-plus"></i> Добави нова такса
        </button>
        
        <div class="grid">
            <?php foreach ($fees as $fee): ?>
            <div class="card">
                <h3><i class="fas fa-file-invoice-dollar"></i> Такса за <?php echo htmlspecialchars($fee['month']); ?> <?php echo $fee['year']; ?></h3>
                <p><strong><i class="fas fa-building"></i> Сграда:</strong> <?php echo htmlspecialchars($fee['building_name']); ?></p>
                <p><strong><i class="fas fa-home"></i> Апартамент:</strong> <?php echo htmlspecialchars($fee['apartment_number']); ?></p>
                <p><strong><i class="fas fa-money-bill-wave"></i> Сума:</strong> <?php echo number_format($fee['amount'], 2); ?> лв.</p>
                <?php if ($fee['description']): ?>
                <p><strong><i class="fas fa-info-circle"></i> Описание:</strong> <?php echo htmlspecialchars($fee['description']); ?></p>
                <?php endif; ?>
                <div class="payment-actions">
                    <button class="btn btn-warning" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($fee)); ?>)">
                        <i class="fas fa-edit"></i> Редактирай
                    </button>
                    <button class="btn btn-danger" onclick="deleteFee(<?php echo $fee['id']; ?>)">
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
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Добави нова такса</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_fee">
                        <div class="form-group">
                            <label for="apartment_id" class="form-label">Апартамент:</label>
                            <select class="form-control" id="apartment_id" name="apartment_id" required>
                                <option value="">Изберете апартамент</option>
                                <?php foreach ($apartments as $apartment): ?>
                                <option value="<?php echo $apartment['id']; ?>">
                                    <?php echo htmlspecialchars($apartment['building_name'] . ' - Апартамент ' . $apartment['number']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="month" class="form-label">Месец:</label>
                            <select class="form-control" id="month" name="month" required>
                                <option value="">Изберете месец</option>
                                <?php foreach ($months as $month): ?>
                                <option value="<?php echo $month; ?>"><?php echo $month; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="year" class="form-label">Година:</label>
                            <input type="number" class="form-control" id="year" name="year" min="2000" max="2100" value="<?php echo date('Y'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="amount" class="form-label">Сума (лв.):</label>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="description" class="form-label">Описание:</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
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
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Редактирай такса</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_fee">
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
                            <label for="edit_month" class="form-label">Месец:</label>
                            <select class="form-control" id="edit_month" name="month" required>
                                <?php foreach ($months as $month): ?>
                                <option value="<?php echo $month; ?>"><?php echo $month; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_year" class="form-label">Година:</label>
                            <input type="number" class="form-control" id="edit_year" name="year" min="2000" max="2100" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_amount" class="form-label">Сума (лв.):</label>
                            <input type="number" class="form-control" id="edit_amount" name="amount" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_description" class="form-label">Описание:</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
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

        function showEditModal(fee) {
            document.getElementById('edit_id').value = fee.id;
            document.getElementById('edit_apartment_id').value = fee.apartment_id;
            document.getElementById('edit_month').value = fee.month;
            document.getElementById('edit_year').value = fee.year;
            document.getElementById('edit_amount').value = fee.amount;
            document.getElementById('edit_description').value = fee.description;
            
            var modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        }

        function deleteFee(id) {
            if (confirm('Сигурни ли сте, че искате да изтриете тази такса?')) {
                var form = document.createElement('form');
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
