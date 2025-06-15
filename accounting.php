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

$error = '';
$success = '';

// Вземане на текущата сграда
$currentBuilding = getCurrentBuilding();

// Каси: зареждане и обработка
$cashboxes = [];
if ($currentBuilding) {
    $stmt = $pdo->prepare("SELECT * FROM cashboxes WHERE building_id = ?");
    $stmt->execute([$currentBuilding['id']]);
    $cashboxes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// Добавяне/изваждане на пари
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cash_action'])) {
    $cashbox_id = (int)($_POST['cashbox_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $type = $_POST['cash_action'] === 'in' ? 'in' : 'out';
    if ($cashbox_id && $amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO cashbox_transactions (cashbox_id, amount, type, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$cashbox_id, $amount, $type]);
        $success = 'Операцията е успешна!';
        header('Location: accounting.php');
        exit();
    }
}
// Добавяне на нова каса
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) && $_POST['action'] === 'add_cashbox' &&
    $currentBuilding
) {
    $name = trim($_POST['cashbox_name'] ?? '');
    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO cashboxes (building_id, name, balance) VALUES (?, ?, 0)");
        $stmt->execute([$currentBuilding['id'], $name]);
        header('Location: accounting.php');
        exit();
    }
}
// Изтриване на каса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_cashbox') {
    $cashbox_id = (int)($_POST['cashbox_id'] ?? 0);
    if ($cashbox_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM cashboxes WHERE id = ?");
        $stmt->execute([$cashbox_id]);
        header('Location: accounting.php');
        exit();
    }
}
// Проверка дали потребителят е логнат
if (!isset($_SESSION['user_id']) || !isLoggedIn()) {
    $_SESSION['error'] = 'Моля, влезте в системата за да продължите.';
    header('Location: login.php');
    exit();
}

// --- ДОБАВЯМ ЛОГИКАТА ЗА ПЛАЩАНИЯ ---
try {
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

    // Вземане на всички неплатени такси с информация за апартамент и сграда
    $stmt = $pdo->query("
        SELECT f.*, fa.apartment_id, a.number AS apartment_number, b.name AS building_name
        FROM fees f 
        JOIN fee_apartments fa ON fa.fee_id = f.id
        JOIN apartments a ON fa.apartment_id = a.id
        JOIN buildings b ON a.building_id = b.id 
        LEFT JOIN payments p ON p.fee_id = f.id AND p.apartment_id = fa.apartment_id
        WHERE p.id IS NULL
        ORDER BY f.created_at DESC
    ");
    $unpaid_fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Вземане на плащанията според избраната сграда
    $query = "
        SELECT p.*, a.number as apartment_number, b.name as building_name, f.created_at 
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
    $query .= " ORDER BY p.created_at DESC, b.name, a.number";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $payment_methods = ['В брой', 'Банков превод', 'Карта', 'Друг'];

    // --- ЗАРЕЖДАНЕ НА ТАКСИТЕ ЗА ТЕКУЩАТА СГРАДА ---
    if ($currentBuilding) {
        $stmt = $pdo->prepare("SELECT f.* FROM fees f JOIN fee_apartments fa ON fa.fee_id = f.id JOIN apartments a ON fa.apartment_id = a.id WHERE a.building_id = ? GROUP BY f.id ORDER BY f.created_at DESC");
        $stmt->execute([$currentBuilding['id']]);
        $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("SELECT * FROM fees ORDER BY created_at DESC");
        $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = handlePDOError($e);
} catch (Exception $e) {
    $error = handleError($e);
}

// В обработката на POST заявката за add_fee:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_fee') {
        $cashbox_id = (int)($_POST['cashbox_id'] ?? 0);
        $type = $_POST['type'] ?? '';
        $months_count = (int)($_POST['months_count'] ?? 1);
        $amount = (float)($_POST['amount'] ?? 0);
        $distribution_method = $_POST['distribution_method'] ?? '';
        $description = $_POST['description'] ?? '';
        $amounts = $_POST['amounts'] ?? [];
        if (!empty($type) && $amount > 0 && !empty($distribution_method) && !empty($amounts) && $cashbox_id > 0) {
            $pdo->beginTransaction();
            try {
                // 1. Създаване на обща такса с cashbox_id
                $stmt = $pdo->prepare("INSERT INTO fees (cashbox_id, type, amount, description, distribution_method, months_count) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$cashbox_id, $type, $amount, $description, $distribution_method, $months_count]);
                $fee_id = $pdo->lastInsertId();
                // 2. Създаване на разпределение по апартаменти
                $stmt2 = $pdo->prepare("INSERT INTO fee_apartments (fee_id, apartment_id, amount) VALUES (?, ?, ?)");
                $inserted = false;
                foreach ($amounts as $apartment_id => $a) {
                    if (is_numeric($a) && $a !== '') {
                        $stmt2->execute([$fee_id, $apartment_id, $a]);
                        $inserted = true;
                    }
                }
                if (!$inserted) {
                    throw new Exception('Няма валидни суми за апартаментите!');
                }
                $pdo->commit();
                $success = showSuccess('Таксата и разпределението са добавени успешно.');
                header('Location: accounting.php');
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = showError('Възникна грешка при добавянето: ' . $e->getMessage());
            }
        } else {
            $error = showError('Моля, попълнете всички задължителни полета.');
        }
    }
}

require_once 'includes/styles.php';
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Счетоводство | Електронен Домоуправител</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid mt-4">
    <h2 class="mb-4"><i class="fas fa-coins"></i> Счетоводство</h2>
    <?php echo renderBuildingSelector(); ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- НАВИГАЦИОННО МЕНЮ (TABS) -->
    <ul class="nav nav-tabs mb-3" id="accountingTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="budget-tab" data-bs-toggle="tab" data-bs-target="#budget" type="button" role="tab">Бюджет</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">Отчети</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">Плащания</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab">Транзакции</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="debts-tab" data-bs-toggle="tab" data-bs-target="#debts" type="button" role="tab">Задължения</button>
      </li>
    </ul>
    <div class="tab-content" id="accountingTabsContent">
      <div class="tab-pane fade show active" id="budget" role="tabpanel">
        <div class="row">
          <div class="col-lg-6 col-md-12">
            <!-- Каси -->
            <div class="card mb-3 shadow-sm" style="font-size:0.95rem;">
              <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                <span><i class="fas fa-cash-register"></i> Каси</span>
                <button class="btn btn-primary btn-sm" onclick="showAddCashboxModal()"><i class="fas fa-plus"></i> Добави нова каса</button>
              </div>
              <div class="card-body p-3">
                <table class="table table-bordered table-sm mb-0" style="font-size:0.95rem;">
                  <thead class="table-light"><tr><th>Име</th><th>Баланс</th><th class="text-center">Операции</th></tr></thead>
                  <tbody>
                  <?php foreach ($cashboxes as $cb): ?>
                    <tr>
                      <td class="fw-bold"><i class="fas fa-wallet me-1"></i> <?php echo htmlspecialchars($cb['name']); ?></td>
                      <td class="text-end text-primary fw-bold"><?php echo number_format($cb['balance'], 2); ?> лв.</td>
                      <td class="text-center">
                        <button class="btn btn-outline-danger btn-sm" onclick="deleteCashbox(<?php echo $cb['id']; ?>)"><i class="fas fa-trash"></i> Изтрий</button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <!-- Модал за добавяне на нова каса -->
            <div id="addCashboxModal" class="modal fade" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Добави нова каса</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <form method="POST">
                      <input type="hidden" name="action" value="add_cashbox">
                      <div class="form-group mb-3">
                        <label for="cashbox_name" class="form-label">Име на касата:</label>
                        <input type="text" class="form-control" id="cashbox_name" name="cashbox_name" required>
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
          </div>
          <div class="col-lg-6 col-md-12">
            <!-- Таблица с всички такси (като тази за касите) -->
            <div class="card mb-3 shadow-sm" style="font-size:0.95rem;">
              <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                <span><i class="fas fa-file-invoice-dollar"></i> Такси</span>
                <button class="btn btn-primary btn-sm" onclick="showAddFeeModal()"><i class="fas fa-plus"></i> Добави нова такса</button>
              </div>
              <div class="card-body p-3">
                <div class="table-responsive">
                  <table class="table table-bordered table-sm mb-0" style="font-size:0.95rem;">
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
                          <button class="btn btn-warning btn-sm" onclick='showEditFeeModal(<?php echo htmlspecialchars(json_encode($fee)); ?>)'><i class="fas fa-edit"></i></button>
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
            <!-- Модал за добавяне на такса -->
            <div id="addFeeModal" class="modal fade" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Добави нова такса</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <form method="POST">
                      <input type="hidden" name="action" value="add_fee">
                      <div class="form-group mb-2">
                        <label for="fee_cashbox_id" class="form-label">Каса:</label>
                        <select class="form-control" id="fee_cashbox_id" name="cashbox_id" required>
                          <option value="">Изберете каса</option>
                          <?php foreach ($cashboxes as $cb): ?>
                            <option value="<?php echo $cb['id']; ?>"><?php echo htmlspecialchars($cb['name']); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
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
          </div>
        </div>
      </div>
      <div class="tab-pane fade" id="reports" role="tabpanel">
        <!-- Тук може да се добави съдържание за Отчети -->
      </div>
      <div class="tab-pane fade" id="payments" role="tabpanel">
        <!-- Плащанията се местят тук -->
        <div class="col-lg-12 col-md-12">
            <div class="card mb-3 shadow-sm" style="font-size:0.95rem;">
                <div class="card-header d-flex justify-content-between align-items-center bg-success text-white">
                    <span><i class="fas fa-credit-card"></i> Плащания</span>
                    <button class="btn btn-success btn-sm" onclick="showAddModal()"><i class="fas fa-plus"></i> Добави ново плащане</button>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-sm mb-0" style="font-size:0.95rem;">
                            <thead class="table-dark">
                                <tr>
                                    <th>Апартамент</th>
                                    <th>Сума (лв.)</th>
                                    <th>Дата</th>
                                    <th>Метод</th>
                                    <th>Описание</th>
                                    <th>Бележка</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['building_name'] . ' - ' . $payment['apartment_number']); ?></td>
                                    <td><?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['description'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Модал за добавяне на плащане и JS остават тук -->
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
      </div>
      <div class="tab-pane fade" id="transactions" role="tabpanel">
        <!-- Тук може да се добави съдържание за Транзакции -->
      </div>
      <div class="tab-pane fade" id="debts" role="tabpanel">
        <!-- Тук може да се добави съдържание за Задължения -->
      </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function deleteCashbox(id) {
    if (confirm('Сигурни ли сте, че искате да изтриете тази каса?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_cashbox"><input type="hidden" name="cashbox_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}
const unpaidFees = <?php echo json_encode($unpaid_fees); ?>;
function showAddModal() {
    var modal = new bootstrap.Modal(document.getElementById('addModal'));
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
            option.textContent = `${fee.amount} лв.`;
            feeSelect.appendChild(option);
        });
    }
}
function showAddCashboxModal() {
    var modal = new bootstrap.Modal(document.getElementById('addCashboxModal'));
    modal.show();
}
function showAddFeeModal() {
    var modal = new bootstrap.Modal(document.getElementById('addFeeModal'));
    modal.show();
}
function showEditFeeModal(fee) {
    // Тук можеш да добавиш логика за редакция на такса
    // (примерно попълване на модал с данните на таксата)
}
function deleteFee(id) {
    if (confirm('Сигурни ли сте, че искате да изтриете тази такса?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_fee"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}
function toggleMonthsCount() {
    var type = document.getElementById('type').value;
    document.getElementById('months_count_group').style.display = (type === 'temporary') ? 'block' : 'none';
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
</script>
</body>
</html> 