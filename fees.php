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
                    $type = $_POST['type'] ?? '';
                    $months_count = (int)($_POST['months_count'] ?? 1);
                    $amount = (float)($_POST['amount'] ?? 0);
                    $distribution_method = $_POST['distribution_method'] ?? '';
                    $description = $_POST['description'] ?? '';
                    $amounts = $_POST['amounts'] ?? [];
                    
                    if (!empty($type) && $amount > 0 && !empty($distribution_method) && !empty($amounts)) {
                        $pdo->beginTransaction();
                        try {
                            // 1. Създаване на обща такса
                            $stmt = $pdo->prepare("INSERT INTO fees (type, amount, description, distribution_method, months_count) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$type, $amount, $description, $distribution_method, $months_count]);
                            $fee_id = $pdo->lastInsertId();
                            // 2. Създаване на разпределение по апартаменти
                            $stmt2 = $pdo->prepare("INSERT INTO fee_apartments (fee_id, apartment_id, amount) VALUES (?, ?, ?)");
                            // Дебъгване на получените суми
                            error_log('POSTED AMOUNTS: ' . print_r($amounts, true));
                            $inserted = false;
                            foreach ($amounts as $apartment_id => $amount) {
                                if (is_numeric($amount) && $amount !== '') {
                                    $stmt2->execute([$fee_id, $apartment_id, $amount]);
                                    $inserted = true;
                                }
                            }
                            if (!$inserted) {
                                throw new Exception('Няма валидни суми за апартаментите!');
                            }
                            $pdo->commit();
                            $success = showSuccess('Таксата и разпределението са добавени успешно.');
                            header('Location: fees.php');
                            exit();
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $error = showError('Възникна грешка при добавянето: ' . $e->getMessage());
                        }
                    } else {
                        $error = showError('Моля, попълнете всички задължителни полета.');
                    }
                    break;
                    
                case 'edit_fee':
                    $id = (int)($_POST['id'] ?? 0);
                    $apartment_id = (int)($_POST['apartment_id'] ?? 0);
                    $month = $_POST['month'] ?? '';
                    $amount = (float)($_POST['amount'] ?? 0);
                    $description = $_POST['description'] ?? '';
                    
                    if ($id > 0 && $apartment_id > 0 && !empty($month) && $amount > 0) {
                        $stmt = $pdo->prepare("UPDATE fees SET apartment_id = ?, month = ?, amount = ?, description = ? WHERE id = ?");
                        $stmt->execute([$apartment_id, $month, $amount, $description, $id]);
                        $success = showSuccess('Таксата е редактирана успешно.');
                        header('Location: fees.php');
                        exit();
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
                        header('Location: fees.php');
                        exit();
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
    
    // Вземане на всички такси
    if ($currentBuilding) {
        $stmt = $pdo->prepare("SELECT f.* FROM fees f JOIN fee_apartments fa ON fa.fee_id = f.id JOIN apartments a ON fa.apartment_id = a.id WHERE a.building_id = ? GROUP BY f.id ORDER BY f.created_at DESC");
        $stmt->execute([$currentBuilding['id']]);
        $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("SELECT * FROM fees ORDER BY created_at DESC");
        $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Масив с месеци
    $months = [
        'Януари', 'Февруари', 'Март', 'Април', 'Май', 'Юни',
        'Юли', 'Август', 'Септември', 'Октомври', 'Ноември', 'Декември'
    ];
    
    // --- АВТОМАТИЧНО ГЕНЕРИРАНЕ НА ТАКСИ ---
    if (
        $currentBuilding &&
        isset($currentBuilding['generate_day']) &&
        (int)$currentBuilding['generate_day'] === (int)date('j')
    ) {
        // Проверка дали вече има генерирана такса за този месец (за да не се дублира)
        $monthKey = date('Y-m');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM fees WHERE type = 'monthly' AND description LIKE ? AND created_at >= ?");
        $stmt->execute(['%Автоматично генерирана%', $monthKey.'-01']);
        $alreadyGenerated = $stmt->fetchColumn();
        if (!$alreadyGenerated) {
            // Вземи всички апартаменти в сградата
            $stmt = $pdo->prepare("SELECT * FROM apartments WHERE building_id = ?");
            $stmt->execute([$currentBuilding['id']]);
            $apartments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Вземи всички месечни такси за тази сграда
            $stmt = $pdo->prepare("SELECT * FROM fees WHERE type = 'monthly' AND description NOT LIKE '%Автоматично генерирана%' AND distribution_method IS NOT NULL");
            $stmt->execute();
            $monthly_fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($monthly_fees as $fee) {
                // Създай нова такса за този месец
                $stmt = $pdo->prepare("INSERT INTO fees (type, amount, description, distribution_method, months_count) VALUES (?, ?, ?, ?, ?)");
                $desc = ($fee['description'] ? $fee['description'].' ' : '').'(Автоматично генерирана '.date('m.Y').')';
                $stmt->execute(['monthly', $fee['amount'], $desc, $fee['distribution_method'], 1]);
                $fee_id = $pdo->lastInsertId();
                // Разпредели по апартаменти
                $stmt2 = $pdo->prepare("INSERT INTO fee_apartments (fee_id, apartment_id, amount) VALUES (?, ?, ?)");
                // ... тук можеш да добавиш логика за разпределение според метода ...
                $per = $fee['amount'] / count($apartments);
                foreach ($apartments as $apartment) {
                    $stmt2->execute([$fee_id, $apartment['id'], $per]);
                }
            }
        }
    }
    
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
        
        <!-- Таблица с всички такси -->
        <div class="card p-3 mb-4">
            <h5><i class="fas fa-file-invoice-dollar"></i> Всички такси</h5>
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Тип</th>
                            <th>Метод</th>
                            <th>Обща сума</th>
                            <th>Описание</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fees as $fee): ?>
                        <?php if ($fee['type'] === 'monthly' || $fee['type'] === 'temporary'): ?>
                        <tr>
                            <td><?php echo $fee['type'] === 'monthly' ? 'Месечна' : 'Временна'; ?></td>
                            <td>
                                <?php
                                switch($fee['distribution_method']) {
                                    case 'equal': echo 'Равномерно'; break;
                                    case 'by_people': echo 'По хора'; break;
                                    case 'by_area': echo 'По площ'; break;
                                }
                                ?>
                            </td>
                            <td><?php echo number_format($fee['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($fee['description']); ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm" onclick='showEditModal(<?php echo htmlspecialchars(json_encode($fee)); ?>)'><i class="fas fa-edit"></i></button>
                                <button class="btn btn-danger btn-sm" onclick="deleteFee(<?php echo $fee['id']; ?>)"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
                            <label for="type" class="form-label">Тип такса:</label>
                            <select class="form-control" id="type" name="type" required onchange="toggleMonthsCount()">
                                <option value="monthly">Месечна</option>
                                <option value="temporary">Временна</option>
                            </select>
                        </div>
                        <div class="form-group" id="months_count_group" style="display:none;">
                            <label for="months_count" class="form-label">Брой месеци (за временна такса):</label>
                            <input type="number" class="form-control" id="months_count" name="months_count" min="1" value="1">
                        </div>
                        <div class="form-group">
                            <label for="amount" class="form-label">Обща сума за разпределение (лв.):</label>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" value="0" oninput="distributeAmounts()">
                        </div>
                        <div class="form-group">
                            <label for="distribution_method" class="form-label">Метод на разпределение:</label>
                            <select class="form-control" id="distribution_method" name="distribution_method" required onchange="distributeAmounts()">
                                <option value="equal">Равномерно</option>
                                <option value="by_people">По брой хора</option>
                                <option value="by_area">По площ (м²)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="description" class="form-label">Описание:</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Разпределение по апартаменти:</label>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="distribution_table">
                                    <thead>
                                        <tr>
                                            <th>Апартамент</th>
                                            <th>Сума (лв.)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($apartments as $apartment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($apartment['building_name'] . ' - ' . $apartment['number']); ?></td>
                                            <td><input type="number" class="form-control amount-input" name="amounts[<?php echo $apartment['id']; ?>]" step="0.01" min="0" value="0"></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
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
                            <label for="edit_type" class="form-label">Тип такса:</label>
                            <select class="form-control" id="edit_type" name="type" required onchange="toggleMonthsCount()">
                                <option value="monthly">Месечна</option>
                                <option value="temporary">Временна</option>
                            </select>
                        </div>
                        <div class="form-group" id="edit_months_count_group" style="display:none;">
                            <label for="edit_months_count" class="form-label">Брой месеци (за временна такса):</label>
                            <input type="number" class="form-control" id="edit_months_count" name="months_count" min="1" value="1">
                        </div>
                        <div class="form-group">
                            <label for="edit_distribution_method" class="form-label">Метод на разпределение:</label>
                            <select class="form-control" id="edit_distribution_method" name="distribution_method" required>
                                <option value="equal">Равномерно</option>
                                <option value="by_people">По брой хора</option>
                                <option value="by_area">По площ (м²)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_amount" class="form-label">Обща сума за разпределение (лв.):</label>
                            <input type="number" class="form-control" id="edit_amount" name="amount" step="0.01" min="0" value="0">
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
            document.getElementById('edit_type').value = fee.type;
            document.getElementById('edit_months_count').value = fee.months_count;
            document.getElementById('edit_distribution_method').value = fee.distribution_method;
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

        function toggleMonthsCount() {
            var type = document.getElementById('type').value;
            document.getElementById('months_count_group').style.display = (type === 'temporary') ? 'block' : 'none';
        }
        document.getElementById('type').addEventListener('change', toggleMonthsCount);

        // Функция за преизчисляване на сумите при промяна на конкретна сума
        function recalculateAmounts(changedInput) {
            var total = parseFloat(document.getElementById('amount').value) || 0;
            var method = document.getElementById('distribution_method').value;
            var rows = document.querySelectorAll('#distribution_table tbody tr');
            var changedValue = parseFloat(changedInput.value) || 0;
            var changedIndex = Array.from(rows).indexOf(changedInput.closest('tr'));
            
            // Ако методът е равномерно разпределение
            if (method === 'equal') {
                var remainingTotal = total - changedValue;
                var remainingApartments = rows.length - 1;
                var perApartment = remainingTotal / remainingApartments;
                
                rows.forEach(function(row, index) {
                    if (index !== changedIndex) {
                        row.querySelector('.amount-input').value = perApartment.toFixed(2);
                    }
                });
            }
            // Ако методът е по брой хора
            else if (method === 'by_people') {
                var people = <?php echo json_encode(array_column($apartments, 'people_count', 'id')); ?>;
                var changedPeople = parseInt(people[Object.keys(people)[changedIndex]]) || 1;
                var remainingTotal = total - changedValue;
                var remainingPeople = 0;
                
                rows.forEach(function(row, index) {
                    if (index !== changedIndex) {
                        var id = Object.keys(people)[index];
                        remainingPeople += parseInt(people[id]) || 1;
                    }
                });
                
                rows.forEach(function(row, index) {
                    if (index !== changedIndex) {
                        var id = Object.keys(people)[index];
                        var peopleCount = parseInt(people[id]) || 1;
                        var newValue = remainingTotal * (peopleCount / remainingPeople);
                        row.querySelector('.amount-input').value = newValue.toFixed(2);
                    }
                });
            }
            // Ако методът е по площ
            else if (method === 'by_area') {
                var areas = <?php echo json_encode(array_column($apartments, 'area', 'id')); ?>;
                var changedArea = parseFloat(areas[Object.keys(areas)[changedIndex]]) || 0;
                var remainingTotal = total - changedValue;
                var remainingArea = 0;
                
                rows.forEach(function(row, index) {
                    if (index !== changedIndex) {
                        var id = Object.keys(areas)[index];
                        remainingArea += parseFloat(areas[id]) || 0;
                    }
                });
                
                rows.forEach(function(row, index) {
                    if (index !== changedIndex) {
                        var id = Object.keys(areas)[index];
                        var area = parseFloat(areas[id]) || 0;
                        var newValue = remainingTotal * (area / remainingArea);
                        row.querySelector('.amount-input').value = newValue.toFixed(2);
                    }
                });
            }
        }

        function distributeAmounts() {
            var total = parseFloat(document.getElementById('amount').value) || 0;
            var method = document.getElementById('distribution_method').value;
            var rows = document.querySelectorAll('#distribution_table tbody tr');
            
            if (method === 'equal') {
                var per = total / rows.length;
                rows.forEach(function(row) {
                    row.querySelector('.amount-input').value = per.toFixed(2);
                });
            } else if (method === 'by_people') {
                var people = <?php echo json_encode(array_column($apartments, 'people_count', 'id')); ?>;
                var totalPeople = 0;
                rows.forEach(function(row, i) {
                    var id = Object.keys(people)[i];
                    totalPeople += parseInt(people[id]) || 1;
                });
                rows.forEach(function(row, i) {
                    var id = Object.keys(people)[i];
                    var val = totalPeople ? total * (parseInt(people[id]) || 1) / totalPeople : 0;
                    row.querySelector('.amount-input').value = val.toFixed(2);
                });
            } else if (method === 'by_area') {
                var areas = <?php echo json_encode(array_column($apartments, 'area', 'id')); ?>;
                var totalArea = 0;
                rows.forEach(function(row, i) {
                    var id = Object.keys(areas)[i];
                    totalArea += parseFloat(areas[id]) || 0;
                });
                rows.forEach(function(row, i) {
                    var id = Object.keys(areas)[i];
                    var val = totalArea ? total * (parseFloat(areas[id]) || 0) / totalArea : 0;
                    row.querySelector('.amount-input').value = val.toFixed(2);
                });
            }
        }

        // Добавяме event listener за промяна на сумите
        document.addEventListener('DOMContentLoaded', function() {
            var amountInputs = document.querySelectorAll('.amount-input');
            amountInputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    recalculateAmounts(this);
                });
            });
        });
    </script>
</body>
</html>
