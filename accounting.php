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
    // Създаване на таблица за баланси на апартаменти, ако не съществува
    $pdo->exec("CREATE TABLE IF NOT EXISTS apartment_balances (
        id INT AUTO_INCREMENT PRIMARY KEY,
        apartment_id INT NOT NULL,
        balance DECIMAL(10,2) DEFAULT 0.00,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE
    )");

    // Взимане на всички апартаменти с техните баланси и името на сградата
    $apartments = $pdo->query("
        SELECT a.*, b.name AS building_name, ab.balance
        FROM apartments a
        JOIN buildings b ON a.building_id = b.id
        LEFT JOIN apartment_balances ab ON a.id = ab.apartment_id
        ORDER BY b.name, a.number
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Ако някой апартамент няма запис в балансите, създаваме такъв
    foreach ($apartments as $apartment) {
        if (!isset($apartment['balance'])) {
            $stmt = $pdo->prepare("INSERT INTO apartment_balances (apartment_id, balance) VALUES (?, 0)");
            $stmt->execute([$apartment['id']]);
            $apartment['balance'] = 0;
        }
    }

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

    // --- ЗАРЕЖДАНЕ НА ПЛАЩАНИЯТА ЗА ТЕКУЩАТА СГРАДА ---
    if (isset($currentBuilding) && $currentBuilding) {
        $stmt = $pdo->prepare("
            SELECT p.*, a.number AS apartment_number, b.name AS building_name
            FROM payments p
            JOIN apartments a ON p.apartment_id = a.id
            JOIN buildings b ON a.building_id = b.id
            WHERE a.building_id = ?
            ORDER BY p.payment_date DESC
        ");
        $stmt->execute([$currentBuilding['id']]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("
            SELECT p.*, a.number AS apartment_number, b.name AS building_name
            FROM payments p
            JOIN apartments a ON p.apartment_id = a.id
            JOIN buildings b ON a.building_id = b.id
            ORDER BY p.payment_date DESC
        ");
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $payment_methods = ['В брой', 'Банков превод', 'Карта', 'Друг'];

    // --- ЗАРЕЖДАНЕ НА ТАКСИТЕ ЗА ТЕКУЩАТА СГРАДА ---
    if (isset($currentBuilding) && $currentBuilding) {
        $stmt = $pdo->prepare("
            SELECT f.*, c.name AS cashbox_name
            FROM fees f
            LEFT JOIN cashboxes c ON f.cashbox_id = c.id
            JOIN fee_apartments fa ON fa.fee_id = f.id
            JOIN apartments a ON fa.apartment_id = a.id
            WHERE a.building_id = ?
            GROUP BY f.id
            ORDER BY f.created_at DESC
        ");
        $stmt->execute([$currentBuilding['id']]);
        $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("
            SELECT f.*, c.name AS cashbox_name
            FROM fees f
            LEFT JOIN cashboxes c ON f.cashbox_id = c.id
            ORDER BY f.created_at DESC
        ");
        $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Вземи всички каси в асоциативен масив за лесен достъп по id
    $cashboxNames = [];
    foreach ($cashboxes as $cb) {
        $cashboxNames[$cb['id']] = $cb['name'];
    }

    // Изчисляване на общите задължения за всеки апартамент
    $apartmentDebts = [];
    foreach ($unpaid_fees as $fee) {
        $aid = $fee['apartment_id'];
        if (!isset($apartmentDebts[$aid])) $apartmentDebts[$aid] = 0;
        $apartmentDebts[$aid] += $fee['amount'];
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
        $charge = $_POST['charge'] ?? [];
        if (!empty($type) && $amount > 0 && !empty($distribution_method) && !empty($amounts) && $cashbox_id > 0) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO fees (cashbox_id, type, amount, description, distribution_method, months_count) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$cashbox_id, $type, $amount, $description, $distribution_method, $months_count]);
                $fee_id = $pdo->lastInsertId();
                $stmt2 = $pdo->prepare("INSERT INTO fee_apartments (fee_id, apartment_id, amount) VALUES (?, ?, ?)");
                $inserted = false;
                foreach ($amounts as $apartment_id => $a) {
                    if (isset($charge[$apartment_id]) && is_numeric($a) && $a !== '') {
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

    if (isset($_POST['action']) && $_POST['action'] === 'delete_fee') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM fees WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: accounting.php');
            exit();
        }
    }

    // Обработка на плащане на избрани такси за апартамент
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['action']) && $_POST['action'] === 'add_payment'
    ) {
        $apartment_id = (int)($_POST['apartment_id'] ?? 0);
        $selected_fees = $_POST['selected_fees'] ?? [];
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $payment_method = $_POST['payment_method'] ?? '';
        $notes = $_POST['notes'] ?? '';
        if ($apartment_id > 0 && is_array($selected_fees) && count($selected_fees) > 0) {
            // Вземи сумите за избраните такси
            $placeholders = implode(',', array_fill(0, count($selected_fees), '?'));
            $stmt = $pdo->prepare("SELECT id, amount, fee_id FROM fee_apartments WHERE id IN ($placeholders) AND apartment_id = ? AND is_paid = 0");
            $stmt->execute(array_merge($selected_fees, [$apartment_id]));
            $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total = 0;
            $fee_ids = [];
            foreach ($fees as $f) {
                $total += $f['amount'];
                $fee_ids[] = $f['fee_id'];
            }
            if ($payment_method === 'от баланс') {
                // Проверка за баланс
                $stmt = $pdo->prepare("SELECT balance FROM apartment_balances WHERE apartment_id = ?");
                $stmt->execute([$apartment_id]);
                $balance = $stmt->fetchColumn();
                if ($total > $balance) {
                    $error = showError('Недостатъчен баланс!');
                } else {
                    $pdo->beginTransaction();
                    try {
                        // Намали баланса
                        $stmt = $pdo->prepare("UPDATE apartment_balances SET balance = balance - ? WHERE apartment_id = ?");
                        $stmt->execute([$total, $apartment_id]);
                        // Маркирай таксите като платени
                        $stmt = $pdo->prepare("UPDATE fee_apartments SET is_paid = 1 WHERE id IN ($placeholders)");
                        $stmt->execute($selected_fees);
                        // Създай запис в payments за всяка такса
                        foreach ($fees as $f) {
                            $stmt = $pdo->prepare("INSERT INTO payments (apartment_id, fee_id, amount, payment_date, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$apartment_id, $f['fee_id'], $f['amount'], $payment_date, $payment_method, $notes]);
                        }
                        $pdo->commit();
                        $success = showSuccess('Плащането от баланс е успешно.');
                        header('Location: accounting.php?tab=debts');
                        exit();
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = showError('Грешка при плащане: ' . $e->getMessage());
                    }
                }
            } else {
                // Стандартно плащане
                $pdo->beginTransaction();
                try {
                    // Маркирай таксите като платени
                    $stmt = $pdo->prepare("UPDATE fee_apartments SET is_paid = 1 WHERE id IN ($placeholders)");
                    $stmt->execute($selected_fees);
                    // Създай запис в payments за всяка такса
                    foreach ($fees as $f) {
                        $stmt = $pdo->prepare("INSERT INTO payments (apartment_id, fee_id, amount, payment_date, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$apartment_id, $f['fee_id'], $f['amount'], $payment_date, $payment_method, $notes]);
                    }
                    $pdo->commit();
                    $success = showSuccess('Плащането е успешно.');
                    header('Location: accounting.php?tab=debts');
                    exit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = showError('Грешка при плащане: ' . $e->getMessage());
                }
            }
        } else {
            $error = showError('Моля, изберете поне едно задължение за плащане.');
        }
    }

    // Обработка на добавяне към баланс
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['action']) && $_POST['action'] === 'add_to_balance'
    ) {
        $apartment_id = (int)($_POST['apartment_id'] ?? 0);
        $fee_id = (int)($_POST['fee_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        if ($apartment_id > 0 && $fee_id > 0 && $amount > 0) {
            $pdo->beginTransaction();
            try {
                // Добави към баланса
                $stmt = $pdo->prepare("UPDATE apartment_balances SET balance = balance + ? WHERE apartment_id = ?");
                $stmt->execute([$amount, $apartment_id]);
                // Маркирай таксата като платена
                $stmt = $pdo->prepare("UPDATE fee_apartments SET is_paid = 1 WHERE fee_id = ? AND apartment_id = ?");
                $stmt->execute([$fee_id, $apartment_id]);
                $pdo->commit();
                $success = showSuccess('Сумата е добавена към баланса на апартамента.');
                header('Location: accounting.php?tab=debts');
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = showError('Грешка при добавяне към баланса: ' . $e->getMessage());
            }
        } else {
            $error = showError('Невалидни данни за добавяне към баланса.');
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
                        <th>Каса</th>
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
                        <td><?php echo isset($cashboxNames[$fee['cashbox_id']]) ? htmlspecialchars($cashboxNames[$fee['cashbox_id']]) : '<span class=\'text-danger\'>Няма</span>'; ?></td>
                        <td>
                          <button class="btn btn-danger btn-sm" onclick="deleteFee(<?php echo $fee['id']; ?>)"><i class="fas fa-trash"></i> Изтрий</button>
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
                                <th>Таксувай</th>
                                <th>Апартамент</th>
                                <th>Сума (лв.)</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($apartments as $apartment): ?>
                              <tr>
                                <td class="text-center">
                                  <input type="checkbox" class="charge-checkbox" name="charge[<?php echo $apartment['id']; ?>]" value="1" checked onchange="toggleChargeRow(this)">
                                </td>
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
                            <div class="form-group mb-2">
                              <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="add_to_balance_checkbox" name="add_to_balance" value="1">
                                <label class="form-check-label" for="add_to_balance_checkbox">
                                  Добави към баланс вместо плащане
                                </label>
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
      <div class="tab-pane fade" id="transactions" role="tabpanel">
        <!-- Тук може да се добави съдържание за Транзакции -->
      </div>
      <div class="tab-pane fade" id="debts" role="tabpanel">
        <div class="card mb-3 shadow-sm" style="font-size:0.95rem;">
          <div class="card-header bg-danger text-white"><i class="fas fa-exclamation-circle"></i> Задължения по апартаменти</div>
          <div class="card-body p-3">
            <div class="table-responsive">
              <table class="table table-striped table-bordered table-sm">
                <thead class="table-dark">
                  <tr>
                    <th>Апартамент</th>
                    <th>Баланс</th>
                    <th>Задължения</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($apartments as $apartment):
                    $aid = $apartment['id'];
                    $debt = $apartmentDebts[$aid] ?? 0;
                    if ($debt == 0) continue;
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($apartment['building_name'] . ' - ' . $apartment['number']); ?></td>
                    <td><?php echo number_format($apartment['balance'], 2); ?> лв.</td>
                    <td><?php echo number_format($debt, 2); ?> лв.</td>
                    <td><button class="btn btn-success btn-sm pay-apartment-btn" data-apartment='<?php echo json_encode($apartment); ?>'><i class="fas fa-credit-card"></i> Плати</button></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <!-- Модал за плащане на задължения на апартамент -->
        <div id="payApartmentModal" class="modal fade" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-credit-card"></i> Плащане на задължения</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <form method="POST" id="payApartmentForm">
                  <input type="hidden" name="action" value="add_payment">
                  <input type="hidden" name="apartment_id" id="pay_apartment_modal_id">
                  <div class="mb-3">
                    <label class="form-label">Апартамент:</label>
                    <input type="text" class="form-control" id="pay_apartment_modal_info" readonly>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Избери задължения за плащане:</label>
                    <div id="apartmentDebtsList" class="border p-2 rounded" style="max-height: 200px; overflow-y: auto;"></div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Обща сума за плащане: <span id="totalApartmentPayment">0.00</span> лв.</label>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Метод на плащане:</label>
                    <select class="form-control" name="payment_method" id="pay_apartment_payment_method" required>
                      <?php foreach ($payment_methods as $method): ?>
                      <option value="<?php echo $method; ?>"><?php echo $method; ?></option>
                      <?php endforeach; ?>
                      <option value="от баланс">От баланс</option>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Бележка:</label>
                    <textarea class="form-control" name="notes" rows="2"></textarea>
                  </div>
                  <div class="text-end">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                    <button type="submit" class="btn btn-success">Потвърди плащане</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
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
    var checkedRows = Array.from(rows).filter(function(row) {
        return row.querySelector('.charge-checkbox').checked;
    });
    var n = checkedRows.length;
    if (n === 0) {
        rows.forEach(function(row) {
            row.querySelector('.amount-input').value = '';
        });
        return;
    }
    if (method === 'equal') {
        var per = (total / n);
        checkedRows.forEach(function(row, i) {
            var val = (i === n - 1) ? (total - per * (n - 1)) : per;
            row.querySelector('.amount-input').value = val.toFixed(2);
        });
    } else if (method === 'by_people') {
        var totalPeople = 0;
        checkedRows.forEach(function(row) {
            totalPeople += parseInt(row.getAttribute('data-people')) || 1;
        });
        checkedRows.forEach(function(row, i) {
            var people = parseInt(row.getAttribute('data-people')) || 1;
            var val = totalPeople ? (total * people / totalPeople) : 0;
            if (i === n - 1) {
                // Корекция за последния ред
                var sum = 0;
                checkedRows.forEach(function(r, j) {
                    if (j !== n - 1) sum += parseFloat(r.querySelector('.amount-input').value) || 0;
                });
                val = total - sum;
            }
            row.querySelector('.amount-input').value = val.toFixed(2);
        });
    } else if (method === 'by_area') {
        var totalArea = 0;
        checkedRows.forEach(function(row) {
            totalArea += parseFloat(row.getAttribute('data-area')) || 1;
        });
        checkedRows.forEach(function(row, i) {
            var area = parseFloat(row.getAttribute('data-area')) || 1;
            var val = totalArea ? (total * area / totalArea) : 0;
            if (i === n - 1) {
                // Корекция за последния ред
                var sum = 0;
                checkedRows.forEach(function(r, j) {
                    if (j !== n - 1) sum += parseFloat(r.querySelector('.amount-input').value) || 0;
                });
                val = total - sum;
            }
            row.querySelector('.amount-input').value = val.toFixed(2);
        });
    }
    // Всички останали (без тикче) -> празно и disabled
    rows.forEach(function(row) {
        if (!row.querySelector('.charge-checkbox').checked) {
            row.querySelector('.amount-input').value = '';
        }
    });
}

// При промяна на чекбокс или метод автоматично преизчислявай
Array.from(document.querySelectorAll('.charge-checkbox')).forEach(function(cb) {
    cb.addEventListener('change', distributeAmounts);
});
document.getElementById('distribution_method').addEventListener('change', distributeAmounts);
document.getElementById('amount').addEventListener('input', distributeAmounts);

function toggleChargeRow(checkbox) {
  var amountInput = checkbox.closest('tr').querySelector('.amount-input');
  if (!checkbox.checked) {
    amountInput.value = '';
    amountInput.disabled = true;
  } else {
    amountInput.disabled = false;
  }
}

function showPayFeeModal(fee) {
  document.getElementById('pay_apartment_id').value = fee.apartment_id;
  document.getElementById('pay_fee_id').value = fee.id;
  document.getElementById('pay_apartment_info').value = (fee.building_name ? fee.building_name + ' - ' : '') + 'Апартамент ' + fee.apartment_number;
  document.getElementById('pay_amount').value = fee.amount;
  // Избери първия метод по подразбиране
  document.getElementById('pay_payment_method').selectedIndex = 0;
  var modal = new bootstrap.Modal(document.getElementById('payFeeModal'));
  modal.show();
}

// За всички бутони "Плати" в таблицата
Array.from(document.querySelectorAll('.pay-fee-btn')).forEach(function(btn) {
  btn.addEventListener('click', function() {
    var fee = JSON.parse(this.getAttribute('data-fee'));
    showPayFeeModal(fee);
  });
});

// При избор на "от баланс" проверка за достатъчен баланс
const payPaymentMethod = document.getElementById('pay_payment_method');
if (payPaymentMethod) {
  payPaymentMethod.addEventListener('change', function() {
    if (this.value === 'от баланс') {
      const apartmentId = document.getElementById('pay_apartment_id').value;
      const amount = parseFloat(document.getElementById('pay_amount').value);
      const balance = parseFloat(apartmentBalances[apartmentId] || 0);
      if (amount > balance) {
        alert('Недостатъчен баланс!');
        this.value = '';
      }
    }
  });
}

const apartmentBalances = <?php echo json_encode(array_column($apartments, 'balance', 'id')); ?>;
// Показване на модал за плащане на апартамент
Array.from(document.querySelectorAll('.pay-apartment-btn')).forEach(function(btn) {
  btn.addEventListener('click', function() {
    var apartment = JSON.parse(this.getAttribute('data-apartment'));
    var aid = apartment.id;
    document.getElementById('pay_apartment_modal_id').value = aid;
    document.getElementById('pay_apartment_modal_info').value = apartment.building_name + ' - ' + apartment.number;
    // Зареждане на задълженията
    var debts = unpaidFees.filter(fee => fee.apartment_id == aid);
    var list = document.getElementById('apartmentDebtsList');
    list.innerHTML = '';
    debts.forEach(function(fee, i) {
      var div = document.createElement('div');
      div.className = 'form-check mb-2';
      div.innerHTML = `
        <input class="form-check-input debt-checkbox" type="checkbox" name="selected_fees[]" value="${fee.id}" data-amount="${fee.amount}" checked>
        <label class="form-check-label">${fee.description} - ${fee.amount} лв.</label>
      `;
      list.appendChild(div);
    });
    updateTotalApartmentPayment();
    var modal = new bootstrap.Modal(document.getElementById('payApartmentModal'));
    modal.show();
  });
});
function updateTotalApartmentPayment() {
  const checkboxes = document.querySelectorAll('.debt-checkbox:checked');
  let total = 0;
  checkboxes.forEach(cb => {
    total += parseFloat(cb.dataset.amount);
  });
  document.getElementById('totalApartmentPayment').textContent = total.toFixed(2);
}
document.addEventListener('change', function(e) {
  if (e.target.classList.contains('debt-checkbox')) updateTotalApartmentPayment();
});
// При избор на "от баланс" проверка за достатъчен баланс
const payApartmentPaymentMethod = document.getElementById('pay_apartment_payment_method');
if (payApartmentPaymentMethod) {
  payApartmentPaymentMethod.addEventListener('change', function() {
    if (this.value === 'от баланс') {
      const aid = document.getElementById('pay_apartment_modal_id').value;
      const checkboxes = document.querySelectorAll('.debt-checkbox:checked');
      let total = 0;
      checkboxes.forEach(cb => { total += parseFloat(cb.dataset.amount); });
      const balance = parseFloat(apartmentBalances[aid] || 0);
      if (total > balance) {
        alert('Недостатъчен баланс!');
        this.value = '';
      }
    }
  });
}

// Активиране на таб 'Задължения' ако има ?tab=debts или ако има грешка
(function() {
  function activateDebtsTab() {
    var debtsTab = document.getElementById('debts-tab');
    if (debtsTab) debtsTab.click();
  }
  if (window.location.search.indexOf('tab=debts') !== -1) {
    activateDebtsTab();
  }
  <?php if ($error): ?>
    activateDebtsTab();
  <?php endif; ?>
})();
</script>
</body>
</html> 