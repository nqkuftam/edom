
// В секцията, където зареждате имотите, добавете occupants в заявката:
$stmt = $pdo->prepare("
    SELECT a.*, b.name AS building_name,
        (SELECT COUNT(*) FROM residents res WHERE res.property_id = a.id AND res.move_out_date IS NULL) as residents_count,
        a.occupants
    FROM properties a
    JOIN buildings b ON a.building_id = b.id
    WHERE a.building_id = ?
    ORDER BY a.number
");

// В секцията за добавяне на нова такса, добавете нова опция за разпределение:
<select class="form-control" id="distribution_method" name="distribution_method" required>
    <option value="equal">Поравно</option>
    <option value="by_area">По площ</option>
    <option value="by_ideal_parts">По идеални части</option>
    <option value="per_occupant">По брой обитатели</option>
</select>

// В PHP кода за обработка на добавяне на нова такса, добавете нова логика за разпределение по брой обитатели:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_fee') {
    // ... (съществуващ код)
    
    if ($distribution_method === 'per_occupant') {
        $total_occupants = array_sum(array_column($properties, 'occupants'));
        if ($total_occupants > 0) {
            foreach ($properties as $property) {
                $property_amount = ($amount * $property['occupants']) / $total_occupants;
                $amounts[$property['id']] = round($property_amount, 2);
            }
        } else {
            $error = showError('Няма регистрирани обитатели в имотите.');
        }
    }
    
    // ... (останалата част от кода)
}<?php
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

// Масив за превод на типове имоти
$PROPERTY_TYPES_BG = [
    'apartment' => 'Апартамент',
    'garage' => 'Гараж',
    'room' => 'Стая',
    'office' => 'Офис',
    'shop' => 'Магазин',
    'warehouse' => 'Склад',
];

// Масив за кратки типове имоти (BG)
$PROPERTY_TYPES_BG_SHORT = [
    'apartment' => 'Ап.',
    'garage' => 'Гараж',
    'room' => 'Стая',
    'office' => 'Офис',
    'shop' => 'Магазин',
    'warehouse' => 'Склад',
    'parking' => 'Паркомясто',
];

// --- ДОБАВЯМ ЛОГИКАТА ЗА ПЛАЩАНИЯ ---
try {
    // Вземане на апартаментите само за текущата сграда
    if ($currentBuilding) {
        $stmt = $pdo->prepare("
            SELECT a.*, b.name AS building_name,
                (SELECT COUNT(*) FROM residents res WHERE res.property_id = a.id AND res.move_out_date IS NULL) as residents_count
            FROM properties a
            JOIN buildings b ON a.building_id = b.id
            WHERE a.building_id = ?
            ORDER BY a.number
        ");
        $stmt->execute([$currentBuilding['id']]);
        $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Вземи баланса за всеки апартамент от ledger
        foreach ($properties as &$property) {
            $stmt2 = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) FROM property_ledger WHERE property_id = ?");
            $stmt2->execute([$property['id']]);
            $property['balance'] = $stmt2->fetchColumn() ?: 0;
        }
        unset($property);
    } else {
        $properties = [];
    }

    // Вземане на всички неплатени такси с информация за апартамент и сграда (включително индивидуалните с fee_id = NULL)
    $stmt = $pdo->query("
        SELECT fa.id as fa_id, 
               CASE 
                   WHEN fa.fee_id IS NULL THEN fa.description 
                   ELSE f.description 
               END as description,
               CASE 
                   WHEN fa.fee_id IS NULL THEN fa.created_at 
                   ELSE f.created_at 
               END as created_at,
               CASE 
                   WHEN fa.fee_id IS NULL THEN 'individual' 
                   ELSE f.type 
               END as type,
               f.id as fee_id,
               fa.property_id, 
               a.number AS property_number, 
               b.name AS building_name, 
               fa.amount,
               fa.cashbox_id
        FROM fee_properties fa
        LEFT JOIN fees f ON fa.fee_id = f.id
        JOIN properties a ON fa.property_id = a.id
        JOIN buildings b ON a.building_id = b.id 
        WHERE fa.is_paid = 0
        ORDER BY COALESCE(f.created_at, fa.created_at) DESC
    ");
    $unpaid_fees_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- ЗАРЕЖДАНЕ НА ПЛАЩАНИЯТА ЗА ТЕКУЩАТА СГРАДА ---
    if (isset($currentBuilding) && $currentBuilding) {
        $stmt = $pdo->prepare("
            SELECT p.*, a.number AS property_number, b.name AS building_name
            FROM payments p
            JOIN properties a ON p.property_id = a.id
            JOIN buildings b ON a.building_id = b.id
            WHERE a.building_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$currentBuilding['id']]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("
            SELECT p.*, a.number AS property_number, b.name AS building_name
            FROM payments p
            JOIN properties a ON p.property_id = a.id
            JOIN buildings b ON a.building_id = b.id
            ORDER BY p.created_at DESC
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
            JOIN fee_properties fa ON fa.fee_id = f.id
            JOIN properties a ON fa.property_id = a.id
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
    $propertyDebts = [];
    foreach ($unpaid_fees_list as $fee) {
        $aid = $fee['property_id'];
        if (!isset($propertyDebts[$aid])) $propertyDebts[$aid] = 0;
        $propertyDebts[$aid] += $fee['amount'];
    }

    // 1. Вземи всички тегления (withdrawals) за касите на текущата сграда
    $withdrawals = [];
    if ($currentBuilding) {
        $stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE cashbox_id IN (SELECT id FROM cashboxes WHERE building_id = ?)");
        $stmt->execute([$currentBuilding['id']]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $withdrawals[$row['cashbox_id']][] = $row;
        }
    }
    
    // 2. Вземи всички връщания на тегления (withdrawal_returns)
    $withdrawal_returns = [];
    if ($currentBuilding) {
        $stmt = $pdo->prepare("
            SELECT wr.*, w.cashbox_id 
            FROM withdrawal_returns wr 
            JOIN withdrawals w ON wr.withdrawal_id = w.id 
            WHERE w.cashbox_id IN (SELECT id FROM cashboxes WHERE building_id = ?)
        ");
        $stmt->execute([$currentBuilding['id']]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $withdrawal_returns[$row['withdrawal_id']][] = $row;
        }
    }
    // 2. Изчисли салдото на всяка каса
    foreach ($cashboxes as &$cb) {
        $cb_id = $cb['id'];
        // 1. Платени такси
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fee_properties WHERE cashbox_id = ? AND is_paid = 1");
        $stmt->execute([$cb_id]);
        $paid_fees = $stmt->fetchColumn();
        // 2. Тегления
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE cashbox_id = ?");
        $stmt->execute([$cb_id]);
        $withdrawn = $stmt->fetchColumn();
        // 3. Връщания на тегления
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(wr.amount),0) 
            FROM withdrawal_returns wr 
            JOIN withdrawals w ON wr.withdrawal_id = w.id 
            WHERE w.cashbox_id = ?
        ");
        $stmt->execute([$cb_id]);
        $returned = $stmt->fetchColumn();
        // 4. Салдо = платени такси - тегления + връщания
        $cb['balance'] = $paid_fees - $withdrawn + $returned;
    }
    unset($cb);

    // Зареждане на активните задължения по имоти за таблицата
    $active_debts_by_property = [];
    if ($currentBuilding) {
        // Вземи всички активни задължения за текущата сграда (включително индивидуалните с fee_id = NULL)
        $stmt = $pdo->prepare("
            SELECT 
                p.id as property_id,
                p.number as property_number,
                p.type as property_type,
                f.id as fee_id,
                CASE 
                    WHEN f.id IS NULL THEN 'individual' 
                    ELSE f.type 
                END as fee_type,
                CASE 
                    WHEN f.id IS NULL THEN fp.description 
                    ELSE f.description 
                END as fee_description,
                f.distribution_method,
                fp.amount as debt_amount,
                fp.is_paid
            FROM properties p
            JOIN fee_properties fp ON p.id = fp.property_id
            LEFT JOIN fees f ON fp.fee_id = f.id
            WHERE p.building_id = ? AND fp.is_paid = 0
            ORDER BY p.number, COALESCE(f.created_at, fp.created_at) DESC
        ");
        $stmt->execute([$currentBuilding['id']]);
        $active_debts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Групиране по имоти
        foreach ($active_debts as $debt) {
            $property_id = $debt['property_id'];
            if (!isset($active_debts_by_property[$property_id])) {
                $active_debts_by_property[$property_id] = [
                    'property' => [
                        'id' => $debt['property_id'],
                        'number' => $debt['property_number'],
                        'type' => $debt['property_type']
                    ],
                    'debts' => [],
                    'total_amount' => 0
                ];
            }
            $active_debts_by_property[$property_id]['debts'][] = $debt;
            $active_debts_by_property[$property_id]['total_amount'] += $debt['debt_amount'];
        }
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
                $stmt2 = $pdo->prepare("INSERT INTO fee_properties (fee_id, property_id, amount, cashbox_id) VALUES (?, ?, ?, ?)");
                $inserted = false;
                foreach ($amounts as $property_id => $a) {
                    if (isset($charge[$property_id]) && is_numeric($a) && $a !== '') {
                        $stmt2->execute([$fee_id, $property_id, $a, $cashbox_id]);
                        $inserted = true;
                    }
                }
                if (!$inserted) {
                    throw new Exception('Няма валидни суми за имотите!');
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
        error_log('POST add_payment: ' . print_r($_POST, true)); // Дебъг лог
        $property_id = (int)($_POST['property_id'] ?? 0);
        $selected_fees = $_POST['selected_fees'] ?? [];
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $payment_method = $_POST['payment_method'] ?? '';
        $notes = $_POST['notes'] ?? '';
        if ($property_id > 0 && is_array($selected_fees) && count($selected_fees) > 0) {
            // Вземи сумите за избраните такси
            $placeholders = implode(',', array_fill(0, count($selected_fees), '?'));
            $stmt = $pdo->prepare("SELECT id, amount, fee_id FROM fee_properties WHERE id IN ($placeholders) AND property_id = ? AND is_paid = 0");
            $stmt->execute(array_merge($selected_fees, [$property_id]));
            $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log('SELECTED FEES RESULT: ' . print_r($fees, true)); // Дебъг лог
            if (!$fees || count($fees) === 0) {
                $error = showError('Няма намерени задължения за плащане. Може би вече са платени.');
            } else {
                $total = 0;
                $fee_ids = [];
                foreach ($fees as $f) {
                    $total += $f['amount'];
                    $fee_ids[] = $f['fee_id'];
                }
                if ($payment_method === 'от баланс') {
                    // Проверка за баланс
                    $stmt = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) FROM property_ledger WHERE property_id = ?");
                    $stmt->execute([$property_id]);
                    $balance = $stmt->fetchColumn();
                    if ($total > $balance) {
                        $error = showError('Недостатъчен баланс!');
                    } else {
                        $pdo->beginTransaction();
                        try {
                            // Намали баланса (ledger)
                            $stmt = $pdo->prepare("INSERT INTO property_ledger (property_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
                            $stmt->execute([$property_id, $total, 'Плащане на такси']);
                            // Маркирай таксите като платени
                            $stmt = $pdo->prepare("UPDATE fee_properties SET is_paid = 1 WHERE id IN ($placeholders)");
                            $stmt->execute($selected_fees);
                            // Създай запис в payments за всяка такса
                            foreach ($fees as $f) {
                                $stmt = $pdo->prepare("INSERT INTO payments (property_id, fee_id, amount, payment_date, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)");
                                $stmt->execute([$property_id, $f['fee_id'], $f['amount'], $payment_date, $payment_method, $notes]);
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
                    // Стандартно плащане (без ledger)
                    $pdo->beginTransaction();
                    try {
                        $stmt = $pdo->prepare("UPDATE fee_properties SET is_paid = 1 WHERE id IN ($placeholders)");
                        $stmt->execute($selected_fees);
                        foreach ($fees as $f) {
                            $stmt = $pdo->prepare("INSERT INTO payments (property_id, fee_id, amount, payment_date, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$property_id, $f['fee_id'], $f['amount'], $payment_date, $payment_method, $notes]);
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
        $property_id = (int)($_POST['property_id'] ?? 0);
        $fee_id = (int)($_POST['fee_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        if ($property_id > 0 && $fee_id > 0 && $amount > 0) {
            $pdo->beginTransaction();
            try {
                // Добави към баланса
                $stmt = $pdo->prepare("UPDATE property_ledger SET amount = amount + ? WHERE property_id = ? AND fee_id = ?");
                $stmt->execute([$amount, $property_id, $fee_id]);
                // Маркирай таксата като платена
                $stmt = $pdo->prepare("UPDATE fee_properties SET is_paid = 1 WHERE fee_id = ? AND property_id = ?");
                $stmt->execute([$fee_id, $property_id]);
                $pdo->commit();
                $success = showSuccess('Сумата е добавена към баланса на имота.');
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

// Теглене на пари от каса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'withdraw') {
    $cashbox_id = (int)($_POST['cashbox_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    
    if ($cashbox_id > 0 && $amount > 0 && !empty($description)) {
        // Записване на тегленето без проверка за достатъчно средства
        $stmt = $pdo->prepare("INSERT INTO withdrawals (cashbox_id, amount, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$cashbox_id, $amount, $description]);
        $success = 'Тегленето е успешно!';
        header('Location: accounting.php?tab=budget');
        exit();
    } else {
        $error = 'Моля, попълнете всички полета правилно.';
    }
}

// Връщане на теглени пари
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return_withdrawal') {
    $withdrawal_id = (int)($_POST['withdrawal_id'] ?? 0);
    $return_amount = (float)($_POST['return_amount'] ?? 0);
    $return_description = trim($_POST['return_description'] ?? '');
    
    if ($withdrawal_id > 0 && $return_amount > 0 && !empty($return_description)) {
        // Вземи информация за тегленето
        $stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE id = ?");
        $stmt->execute([$withdrawal_id]);
        $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($withdrawal && $return_amount <= $withdrawal['amount']) {
            // Проверка дали вече не е върнато повече от тегленото
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawal_returns WHERE withdrawal_id = ?");
            $stmt->execute([$withdrawal_id]);
            $already_returned = $stmt->fetchColumn();
            
            if (($already_returned + $return_amount) <= $withdrawal['amount']) {
                $stmt = $pdo->prepare("INSERT INTO withdrawal_returns (withdrawal_id, amount, description, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$withdrawal_id, $return_amount, $return_description]);
                $success = 'Връщането е успешно!';
                header('Location: accounting.php?tab=budget');
                exit();
            } else {
                $error = 'Не можете да върнете повече от тегленото!';
            }
        } else {
            $error = 'Невалидно теглене или сума!';
        }
    } else {
        $error = 'Моля, попълнете всички полета правилно.';
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
    <div class="header">
        <div class="header-content">
            <h1>Счетоводство</h1>
            <?php echo renderNavigation('accounting'); ?>
        </div>
    </div>
    <div class="container mt-4">
        <a href="index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Назад към таблото</a>
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
            <button class="nav-link" id="budget-tab" data-bs-toggle="tab" data-bs-target="#budget" type="button" role="tab">Бюджет</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">Отчети</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">Плащания</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="debts-tab" data-bs-toggle="tab" data-bs-target="#debts" type="button" role="tab">Задължения</button>
          </li>
        </ul>
        <div class="tab-content" id="accountingTabsContent">
          <div class="tab-pane fade" id="budget" role="tabpanel">
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
                      <thead class="table-dark"><tr><th>Име</th><th>Баланс</th><th class="text-center">Операции</th></tr></thead>
                      <tbody>
                      <?php foreach ($cashboxes as $cb): ?>
                        <tr>
                          <td class="fw-bold"><i class="fas fa-wallet me-1"></i> <?php echo htmlspecialchars($cb['name']); ?></td>
                          <td class="text-end text-primary fw-bold"><?php echo number_format($cb['balance'], 2); ?> лв.</td>
                          <td class="text-center">
                            <button class="btn btn-outline-success btn-sm" onclick="showWithdrawModal(<?php echo $cb['id']; ?>, '<?php echo htmlspecialchars(addslashes($cb['name'])); ?>')"><i class="fas fa-arrow-down"></i> Тегли</button>
                            <button class="btn btn-outline-danger btn-sm" onclick="deleteCashbox(<?php echo $cb['id']; ?>)"><i class="fas fa-trash"></i> Изтрий</button>
                          </td>
                        </tr>
                        <tr>
                          <td colspan="3">
                            <div class="small text-muted">История на тегленията:</div>
                            <?php if (!empty($withdrawals[$cb['id']])): ?>
                              <ul class="list-group mb-2">
                                <?php foreach ($withdrawals[$cb['id']] as $w): ?>
                                  <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                                    <div class="flex-grow-1">
                                      <div class="d-flex justify-content-between align-items-center">
                                    <span><?php echo htmlspecialchars($w['description']); ?> <span class="text-secondary">(<?php echo date('d.m.Y H:i', strtotime($w['created_at'])); ?>)</span></span>
                                    <span class="fw-bold text-danger">-<?php echo number_format($w['amount'], 2); ?> лв.</span>
                                      </div>
                                      <?php 
                                      // Покажи връщанията за това теглене
                                      $returns = $withdrawal_returns[$w['id']] ?? [];
                                      $total_returned = 0;
                                      foreach ($returns as $r) {
                                          $total_returned += $r['amount'];
                                      }
                                      $remaining = $w['amount'] - $total_returned;
                                      ?>
                                      <?php if (!empty($returns)): ?>
                                        <div class="small text-success mt-1">
                                          <strong>Върнати суми:</strong>
                                          <?php foreach ($returns as $r): ?>
                                            <div class="ms-3">
                                              <i class="fas fa-arrow-up text-success"></i> 
                                              <?php echo number_format($r['amount'], 2); ?> лв. 
                                              (<?php echo htmlspecialchars($r['description']); ?> - <?php echo date('d.m.Y H:i', strtotime($r['created_at'])); ?>)
                                            </div>
                                          <?php endforeach; ?>
                                          <div class="ms-3 fw-bold">
                                            Общо върнато: <?php echo number_format($total_returned, 2); ?> лв.
                                            (Остава: <?php echo number_format($remaining, 2); ?> лв.)
                                          </div>
                                        </div>
                                      <?php endif; ?>
                                      <?php if ($remaining > 0): ?>
                                        <div class="mt-1">
                                          <button class="btn btn-outline-warning btn-sm" onclick="showReturnModal(<?php echo $w['id']; ?>, <?php echo $remaining; ?>, '<?php echo htmlspecialchars(addslashes($w['description'])); ?>')">
                                            <i class="fas fa-undo"></i> Върни (<?php echo number_format($remaining, 2); ?> лв.)
                                          </button>
                                        </div>
                                      <?php endif; ?>
                                    </div>
                                  </li>
                                <?php endforeach; ?>
                              </ul>
                            <?php else: ?>
                              <div class="text-muted">Няма тегления.</div>
                            <?php endif; ?>
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
                          <?php if (!is_array($fees)) $fees = []; ?>
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
                            <td><?php 
                              $cb_id = $fee['cashbox_id'] ?? null;
                              if (!$cb_id && isset($fee['id'])) {
                                // Ако няма cashbox_id във fee_properties, вземи от fees
                                $cb_id = $fee['cashbox_id'];
                              }
                              echo isset($cashboxNames[$cb_id]) ? htmlspecialchars($cashboxNames[$cb_id]) : '<span class=\'text-danger\'>Няма</span>'; 
                            ?></td>
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
                              <option value="by_ideal_parts">По идеални части (%)</option>
                            </select>
                          </div>
                          <div class="form-group">
                            <label for="description" class="form-label">Описание:</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                          </div>
                          <div class="form-group">
                            <label class="form-label">Разпределение по имоти:</label>
                            <div class="table-responsive">
                              <table class="table table-bordered table-sm" id="distribution_table">
                                <thead>
                                  <tr>
                                    <th>Таксувай</th>
                                    <th>Имот</th>
                                    <th>Сума (лв.)</th>
                                  </tr>
                                </thead>
                                <tbody>
                                  <?php foreach ($properties as $property): ?>
                                  <tr data-people="<?php echo $property['residents_count']; ?>" data-area="<?php echo $property['area']; ?>" data-ideal-parts="<?php echo $property['ideal_parts']; ?>">
                                    <td class="text-center">
                                      <input type="checkbox" class="charge-checkbox" name="charge[<?php echo $property['id']; ?>]" value="1" checked onchange="toggleChargeRow(this)">
                                    </td>
                                    <td><?php echo htmlspecialchars($property['building_name'] . ' - ' . $property['number']); ?></td>
                                    <td><input type="number" class="form-control amount-input" name="amounts[<?php echo $property['id']; ?>]" step="0.01" min="0" value="0"></td>
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
            
            <!-- Таблица с активни задължения по имоти -->
            <div class="row mt-4">
              <div class="col-12">
                <div class="card shadow-sm">
                  <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Активни задължения по имоти</h5>
                  </div>
                  <div class="card-body">
                    <?php if (!empty($active_debts_by_property)): ?>
                      <div class="table-responsive">
                        <table class="table table-striped table-bordered table-sm">
                          <thead class="table-dark">
                            <tr>
                              <th>Имот</th>
                              <?php 
                              // Вземи всички уникални задължения за заглавията на колоните
                              $unique_debts = [];
                              foreach ($active_debts_by_property as $property_data) {
                                  foreach ($property_data['debts'] as $debt) {
                                      $debt_key = $debt['fee_id'] . '_' . $debt['fee_description'];
                                      if (!isset($unique_debts[$debt_key])) {
                                          $unique_debts[$debt_key] = [
                                              'fee_id' => $debt['fee_id'],
                                              'description' => $debt['fee_description'],
                                              'type' => $debt['fee_type']
                                          ];
                                      }
                                  }
                              }
                              foreach ($unique_debts as $debt): ?>
                                <th class="text-center">
                                  <?php echo htmlspecialchars($debt['description']); ?>
                                  <br>
                                  <small class="text-muted">
                                    <?php echo $debt['type'] === 'monthly' ? 'Месечна' : 'Временна'; ?>
                                  </small>
                                </th>
                              <?php endforeach; ?>
                              <th class="text-center text-primary fw-bold">Обща сума</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($active_debts_by_property as $property_data): ?>
                              <tr>
                                <td class="fw-bold">
                                  <?php 
                                  $property = $property_data['property'];
                                  echo htmlspecialchars(($PROPERTY_TYPES_BG[$property['type']] ?? $property['type']) . ' - ' . $property['number']); 
                                  ?>
                                </td>
                                <?php foreach ($unique_debts as $debt_key => $debt): ?>
                                  <td class="text-center">
                                    <?php 
                                    $amount = 0;
                                    foreach ($property_data['debts'] as $property_debt) {
                                        if ($property_debt['fee_id'] == $debt['fee_id']) {
                                            $amount = $property_debt['debt_amount'];
                                            break;
                                        }
                                    }
                                    if ($amount > 0) {
                                        echo '<span class="text-danger fw-bold">' . number_format($amount, 2) . ' лв.</span>';
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                  </td>
                                <?php endforeach; ?>
                                <td class="text-center text-primary fw-bold">
                                  <?php echo number_format($property_data['total_amount'], 2); ?> лв.
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php else: ?>
                      <div class="text-center text-muted">
                        <i class="fas fa-check-circle fa-2x mb-3"></i>
                        <p>Няма активни задължения за тази сграда.</p>
                      </div>
                    <?php endif; ?>
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
                <!-- Добавяме филтри -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <input type="hidden" name="tab" value="payments">
                            <div class="col-md-3">
                                <label for="filter_property" class="form-label">Имот:</label>
                                <select name="filter_property" id="filter_property" class="form-select">
                                    <option value="">Всички имоти</option>
                                    <?php foreach ($properties as $property): ?>
                                        <option value="<?php echo $property['id']; ?>" <?php if (isset($_GET['filter_property']) && $_GET['filter_property'] == $property['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars(($PROPERTY_TYPES_BG[$property['type']] ?? $property['type']) . ' - ' . $property['number']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filter_method" class="form-label">Метод на плащане:</label>
                                <select name="filter_method" id="filter_method" class="form-select">
                                    <option value="">Всички</option>
                                    <?php foreach ($payment_methods as $method): ?>
                                        <option value="<?php echo $method; ?>" <?php if (isset($_GET['filter_method']) && $_GET['filter_method'] == $method) echo 'selected'; ?>><?php echo $method; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filter_date_from" class="form-label">От дата:</label>
                                <input type="date" name="filter_date_from" id="filter_date_from" class="form-control" value="<?php echo isset($_GET['filter_date_from']) ? htmlspecialchars($_GET['filter_date_from']) : ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="filter_date_to" class="form-label">До дата:</label>
                                <input type="date" name="filter_date_to" id="filter_date_to" class="form-control" value="<?php echo isset($_GET['filter_date_from']) ? htmlspecialchars($_GET['filter_date_to']) : ''; ?>">
                            </div>
                            <div class="col-md-12 text-end">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Филтрирай</button>
                            </div>
                        </form>
                    </div>
                </div>

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
                                        <th>Имот</th>
                                        <th>Сума (лв.)</th>
                                        <th>Дата</th>
                                        <th>Метод</th>
                                        <th>Описание</th>
                                        <th>Бележка</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Прилагаме филтрите към заявката за плащания
                                    $payments_query = "
                                        SELECT p.*, a.number AS property_number, a.type AS property_type, b.name AS building_name, f.description AS fee_description
                                        FROM payments p
                                        JOIN properties a ON p.property_id = a.id
                                        JOIN buildings b ON a.building_id = b.id
                                        LEFT JOIN fees f ON p.fee_id = f.id
                                        WHERE 1=1
                                    ";
                                    $payments_params = [];

                                    if ($currentBuilding) {
                                        $payments_query .= " AND a.building_id = ?";
                                        $payments_params[] = $currentBuilding['id'];
                                    }

                                    if (isset($_GET['filter_property']) && $_GET['filter_property']) {
                                        $payments_query .= " AND a.id = ?";
                                        $payments_params[] = $_GET['filter_property'];
                                    }

                                    if (isset($_GET['filter_method']) && $_GET['filter_method']) {
                                        $payments_query .= " AND p.payment_method = ?";
                                        $payments_params[] = $_GET['filter_method'];
                                    }

                                    if (isset($_GET['filter_date_from']) && $_GET['filter_date_from']) {
                                        $payments_query .= " AND p.payment_date >= ?";
                                        $payments_params[] = $_GET['filter_date_from'];
                                    }

                                    if (isset($_GET['filter_date_to']) && $_GET['filter_date_to']) {
                                        $payments_query .= " AND p.payment_date <= ?";
                                        $payments_params[] = $_GET['filter_date_to'];
                                    }

                                    $payments_query .= " ORDER BY p.created_at DESC, p.id DESC";
                                    
                                    $stmt = $pdo->prepare($payments_query);
                                    $stmt->execute($payments_params);
                                    $filtered_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    foreach ($filtered_payments as $payment): 
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(($PROPERTY_TYPES_BG[$payment['property_type']] ?? $payment['property_type']) . ' - ' . $payment['property_number']); ?></td>
                                        <td><?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['fee_description'] ?? $payment['description'] ?? ''); ?></td>
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
                                    <label for="property_id" class="form-label">Имот:</label>
                                    <select class="form-control" id="property_id" name="property_id" required onchange="updateUnpaidFees()">
                                        <option value="">Изберете имот</option>
                                        <?php foreach ($properties as $property): ?>
                                        <option value="<?php echo $property['id']; ?>">
                                            <?php echo htmlspecialchars(($PROPERTY_TYPES_BG[$property['type']] ?? $property['type']) . ' - ' . $property['number']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="fee_id" class="form-label">Такса:</label>
                                    <select class="form-control" id="fee_id" name="fee_id" required>
                                        <option value="">Първо изберете имот</option>
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
          <div class="tab-pane fade" id="debts" role="tabpanel">
            <!-- Добавям филтърна форма като при плащанията -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="tab" value="debts">
                        <div class="col-md-3">
                            <label for="filter_property_debt" class="form-label">Имот:</label>
                            <select name="filter_property_debt" id="filter_property_debt" class="form-select">
                                <option value="">Всички имоти</option>
                                <?php foreach (
                                    $properties as $property): ?>
                                    <option value="<?php echo $property['id']; ?>" <?php if (isset($_GET['filter_property_debt']) && $_GET['filter_property_debt'] == $property['id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars(($PROPERTY_TYPES_BG[$property['type']] ?? $property['type']) . ' - ' . $property['number']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filter_method_debt" class="form-label">Метод на разпределение:</label>
                            <select name="filter_method_debt" id="filter_method_debt" class="form-select">
                                <option value="">Всички</option>
                                <option value="equal" <?php if (isset($_GET['filter_method_debt']) && $_GET['filter_method_debt'] == 'equal') echo 'selected'; ?>>Равномерно</option>
                                <option value="by_people" <?php if (isset($_GET['filter_method_debt']) && $_GET['filter_method_debt'] == 'by_people') echo 'selected'; ?>>По хора</option>
                                <option value="by_area" <?php if (isset($_GET['filter_method_debt']) && $_GET['filter_method_debt'] == 'by_area') echo 'selected'; ?>>По площ</option>
                                <option value="by_ideal_parts" <?php if (isset($_GET['filter_method_debt']) && $_GET['filter_method_debt'] == 'by_ideal_parts') echo 'selected'; ?>>По идеални части</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filter_date_from_debt" class="form-label">От дата:</label>
                            <input type="date" name="filter_date_from_debt" id="filter_date_from_debt" class="form-control" value="<?php echo isset($_GET['filter_date_from_debt']) ? htmlspecialchars($_GET['filter_date_from_debt']) : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="filter_date_to_debt" class="form-label">До дата:</label>
                            <input type="date" name="filter_date_to_debt" id="filter_date_to_debt" class="form-control" value="<?php echo isset($_GET['filter_date_to_debt']) ? htmlspecialchars($_GET['filter_date_to_debt']) : ''; ?>">
                        </div>
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Филтрирай</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card mb-3 shadow-sm" style="font-size:0.95rem;">
              <div class="card-body p-3">
                <div class="table-responsive">
                  <table class="table table-striped table-bordered table-sm">
                    <thead class="table-dark">
                      <tr>
                        <th>Имот</th>
                        <th>Баланс</th>
                        <th>Задължения</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php 
                      // Филтриране на задълженията според избраните филтри
                      $filtered_properties = $properties;
                      if (isset($_GET['filter_property_debt']) && $_GET['filter_property_debt']) {
                          $filtered_properties = array_filter($filtered_properties, function($a) {
                              return $a['id'] == $_GET['filter_property_debt'];
                          });
                      }
                      // Филтриране по метод на разпределение и период
                      $filtered_debts = $propertyDebts;
                      $filtered_unpaid_fees = $unpaid_fees_list;
                      if (isset($_GET['filter_method_debt']) && $_GET['filter_method_debt']) {
                          $filtered_unpaid_fees = array_filter($filtered_unpaid_fees, function($fee) {
                              return $fee['distribution_method'] == $_GET['filter_method_debt'];
                          });
                      }
                      if (isset($_GET['filter_date_from_debt']) && $_GET['filter_date_from_debt']) {
                          $filtered_unpaid_fees = array_filter($filtered_unpaid_fees, function($fee) {
                              return $fee['created_at'] >= $_GET['filter_date_from_debt'];
                          });
                      }
                      if (isset($_GET['filter_date_to_debt']) && $_GET['filter_date_to_debt']) {
                          $filtered_unpaid_fees = array_filter($filtered_unpaid_fees, function($fee) {
                              return $fee['created_at'] <= $_GET['filter_date_to_debt'];
                          });
                      }
                      // ДЕБЪГ: Проверка на типа и съдържанието на $filtered_unpaid_fees и $unpaid_fees_list
                      if (!is_array($filtered_unpaid_fees)) {
                          echo '<pre style="color:red">';
                          echo 'ДЕБЪГ: $filtered_unpaid_fees не е масив!\n';
                          var_dump($filtered_unpaid_fees);
                          echo "\nДЕБЪГ: $unpaid_fees_list = ";
                          var_dump($unpaid_fees_list);
                          echo '</pre>';
                          die('Скриптът е прекратен за дебъг.');
                      }
                      // Пресмятаме задълженията само за филтрираните такси
                      $debts_by_property = [];
                      foreach ($filtered_unpaid_fees as $fee) {
                          $aid = $fee['property_id'];
                          if (!isset($debts_by_property[$aid])) $debts_by_property[$aid] = 0;
                          $debts_by_property[$aid] += $fee['amount'];
                      }
                      if (!is_array($filtered_properties)) $filtered_properties = [];
                      foreach ($filtered_properties as $property):
                        $aid = $property['id'];
                        $debt = $debts_by_property[$aid] ?? 0;
                        if ($debt == 0) continue;
                      ?>
                      <tr>
                        <td><?php echo htmlspecialchars(($PROPERTY_TYPES_BG[$property['type']] ?? $property['type']) . ' - ' . $property['number']); ?></td>
                        <td><?php echo number_format($property['balance'], 2); ?> лв.</td>
                        <td><?php echo number_format($debt, 2); ?> лв.</td>
                        <td><button class="btn btn-success btn-sm pay-property-btn" data-property='<?php echo json_encode($property); ?>'><i class="fas fa-credit-card"></i> Плати</button></td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            <!-- Модал за плащане на задължения на имот -->
            <div id="payPropertyModal" class="modal fade" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-credit-card"></i> Плащане на задължения</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <form method="POST" id="payPropertyForm">
                      <input type="hidden" name="action" value="add_payment">
                      <input type="hidden" name="property_id" id="pay_property_modal_id">
                      <div class="mb-3">
                        <label class="form-label">Имот:</label>
                        <input type="text" class="form-control" id="pay_property_modal_info" readonly>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Избери задължения за плащане:</label>
                        <div id="propertyDebtsList" class="border p-2 rounded" style="max-height: 200px; overflow-y: auto;"></div>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Обща сума за плащане: <span id="totalPropertyPayment">0.00</span> лв.</label>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Метод на плащане:</label>
                        <select class="form-control" name="payment_method" id="pay_property_payment_method" required>
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
    <div class="modal fade" id="withdrawModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="action" value="withdraw">
            <input type="hidden" name="cashbox_id" id="withdraw_cashbox_id">
            <div class="modal-header">
              <h5 class="modal-title"><i class="fas fa-arrow-down"></i> Теглене от каса</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-2"><strong>Каса:</strong> <span id="withdraw_cashbox_name"></span></div>
              <div class="form-group mb-2">
                <label for="withdraw_amount" class="form-label">Сума (лв.):</label>
                <input type="number" class="form-control" id="withdraw_amount" name="amount" step="0.01" min="0.01" required>
              </div>
              <div class="form-group mb-2">
                <label for="withdraw_description" class="form-label">Описание:</label>
                <input type="text" class="form-control" id="withdraw_description" name="description" required placeholder="Пример: Ремонт, ток и др.">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
              <button type="submit" class="btn btn-danger">Тегли</button>
            </div>
          </form>
          </div>
        </div>
    </div>
    
    <!-- Модал за връщане на теглени пари -->
    <div class="modal fade" id="returnModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="action" value="return_withdrawal">
            <input type="hidden" name="withdrawal_id" id="return_withdrawal_id">
            <div class="modal-header">
              <h5 class="modal-title"><i class="fas fa-undo"></i> Връщане на теглени пари</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-2"><strong>Теглене:</strong> <span id="return_withdrawal_description"></span></div>
              <div class="mb-2"><strong>Максимална сума за връщане:</strong> <span id="return_max_amount" class="text-primary fw-bold"></span> лв.</div>
              <div class="form-group mb-2">
                <label for="return_amount" class="form-label">Сума за връщане (лв.):</label>
                <input type="number" class="form-control" id="return_amount" name="return_amount" step="0.01" min="0.01" required>
              </div>
              <div class="form-group mb-2">
                <label for="return_description" class="form-label">Причина за връщане:</label>
                <input type="text" class="form-control" id="return_description" name="return_description" required placeholder="Пример: Не е използвано, грешка в сумата и др.">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
              <button type="submit" class="btn btn-warning">Върни</button>
            </div>
          </form>
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
    const unpaidFees = <?php echo json_encode($unpaid_fees_list); ?>;
    function showAddModal() {
        var modal = new bootstrap.Modal(document.getElementById('addModal'));
        modal.show();
    }
    function updateUnpaidFees() {
        const propertyId = document.getElementById('property_id').value;
        const feeSelect = document.getElementById('fee_id');
        feeSelect.innerHTML = '<option value="">Изберете такса</option>';
        if (propertyId) {
            const propertyFees = unpaidFees.filter(fee => fee.property_id == propertyId);
            propertyFees.forEach(fee => {
                const option = document.createElement('option');
                option.value = fee.fa_id;
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
        } else if (method === 'by_ideal_parts') {
            var totalIdealParts = 0;
            checkedRows.forEach(function(row) {
                totalIdealParts += parseFloat(row.getAttribute('data-ideal-parts')) || 0;
            });
            checkedRows.forEach(function(row, i) {
                var idealParts = parseFloat(row.getAttribute('data-ideal-parts')) || 0;
                var val = totalIdealParts ? (total * idealParts / totalIdealParts) : 0;
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
      document.getElementById('pay_property_id').value = fee.property_id;
      document.getElementById('pay_fee_id').value = fee.fa_id;
      document.getElementById('pay_property_info').value = (fee.building_name ? fee.building_name + ' - ' : '') + 'Имот ' + fee.property_number;
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
          const propertyId = document.getElementById('pay_property_id').value;
          const amount = parseFloat(document.getElementById('pay_amount').value);
          const balance = parseFloat(propertyBalances[propertyId] || 0);
          if (amount > balance) {
            alert('Недостатъчен баланс!');
            this.value = '';
          }
        }
      });
    }

    const propertyBalances = <?php echo json_encode(array_column($properties, 'balance', 'id')); ?>;
    // Добавяме масив с кратки типове имоти за JS
    const PROPERTY_TYPES_BG_SHORT = <?php echo json_encode($PROPERTY_TYPES_BG_SHORT); ?>;
    // Показване на модал за плащане на имот
    Array.from(document.querySelectorAll('.pay-property-btn')).forEach(function(btn) {
      btn.addEventListener('click', function() {
        var property = JSON.parse(this.getAttribute('data-property'));
        var aid = property.id;
        document.getElementById('pay_property_modal_id').value = aid;
        // Тук попълваме типа и номера
        var typeShort = PROPERTY_TYPES_BG_SHORT[property.type] || property.type;
        document.getElementById('pay_property_modal_info').value = typeShort + ' ' + property.number;
        // Зареждане на задълженията
        var debts = unpaidFees.filter(fee => fee.property_id == aid);
        var list = document.getElementById('propertyDebtsList');
        list.innerHTML = '';
        debts.forEach(function(fee, i) {
          var div = document.createElement('div');
          div.className = 'form-check mb-2';
          div.innerHTML = `
            <input class="form-check-input debt-checkbox" type="checkbox" name="selected_fees[]" value="${fee.fa_id}" data-amount="${fee.amount}" checked>
            <label class="form-check-label">${fee.description} - ${fee.amount} лв.</label>
          `;
          list.appendChild(div);
        });
        updateTotalPropertyPayment();
        var modal = new bootstrap.Modal(document.getElementById('payPropertyModal'));
        modal.show();
      });
    });
    function updateTotalPropertyPayment() {
      const checkboxes = document.querySelectorAll('.debt-checkbox:checked');
      let total = 0;
      checkboxes.forEach(cb => {
        total += parseFloat(cb.dataset.amount);
      });
      document.getElementById('totalPropertyPayment').textContent = total.toFixed(2);
    }
    document.addEventListener('change', function(e) {
      if (e.target.classList.contains('debt-checkbox')) updateTotalPropertyPayment();
    });
    // При избор на "от баланс" проверка за достатъчен баланс
    const payPropertyPaymentMethod = document.getElementById('pay_property_payment_method');
    if (payPropertyPaymentMethod) {
      payPropertyPaymentMethod.addEventListener('change', function() {
        if (this.value === 'от баланс') {
          const aid = document.getElementById('pay_property_modal_id').value;
          const checkboxes = document.querySelectorAll('.debt-checkbox:checked');
          let total = 0;
          checkboxes.forEach(cb => { total += parseFloat(cb.dataset.amount); });
          const balance = parseFloat(propertyBalances[aid] || 0);
          if (total > balance) {
            alert('Недостатъчен баланс!');
            this.value = '';
          }
        }
      });
    }

    // Активиране на таб 'Задължения' ако има ?tab=debts или ако има грешка
    (function() {
      function activateTab(tabName) {
        var tab = document.getElementById(tabName + '-tab');
        var tabContent = document.getElementById(tabName);
        if (tab && tabContent) {
          // Премахване на active класа от всички табове
          document.querySelectorAll('.nav-link').forEach(function(t) {
            t.classList.remove('active');
          });
          document.querySelectorAll('.tab-pane').forEach(function(t) {
            t.classList.remove('show', 'active');
          });
          
          // Активиране на избрания таб
          tab.classList.add('active');
          tabContent.classList.add('show', 'active');
          
          // Актуализиране на URL-а
          const url = new URL(window.location);
          url.searchParams.set('tab', tabName);
          window.history.replaceState({}, '', url);
        }
      }
      
      // Проверка за URL параметър tab
      const urlParams = new URLSearchParams(window.location.search);
      const activeTab = urlParams.get('tab');
      
      if (activeTab) {
        activateTab(activeTab);
      } else {
        // По подразбиране активирай budget таба
        activateTab('budget');
      }
      
      // Активиране на debts таба ако има грешка
      <?php if ($error): ?>
        activateTab('debts');
      <?php endif; ?>
      
      // Добавяне на event listeners за таб бутоните
      document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function(tabButton) {
        tabButton.addEventListener('click', function() {
          const target = this.getAttribute('data-bs-target');
          const tabName = target.replace('#', '');
          activateTab(tabName);
        });
      });
    })();

    // Добавям нова функция за обновяване на списъка с имоти
    function updatePropertiesList() {
        const buildingId = document.querySelector('select[name="building_id"]').value;
        if (!buildingId) {
            document.getElementById('distribution_table').querySelector('tbody').innerHTML = '';
            return;
        }

        fetch(`get_properties.php?building_id=${buildingId}`)
            .then(response => response.json())
            .then(properties => {
                const tbody = document.getElementById('distribution_table').querySelector('tbody');
                tbody.innerHTML = '';
                
                properties.forEach(property => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="text-center">
                            <input type="checkbox" class="charge-checkbox" name="charge[${property.id}]" value="1" checked onchange="toggleChargeRow(this)">
                        </td>
                        <td>${property.building_name} - ${property.number}</td>
                        <td><input type="number" class="form-control amount-input" name="amounts[${property.id}]" step="0.01" min="0" value="0"></td>
                    `;
                    tbody.appendChild(tr);
                });

                // Преизчисляване на сумите
                distributeAmounts();
            })
            .catch(error => console.error('Error:', error));
    }

    // Добавяме слушател за промяна на сградата
    document.querySelector('select[name="building_id"]').addEventListener('change', updatePropertiesList);

    function showWithdrawModal(id, name) {
      document.getElementById('withdraw_cashbox_id').value = id;
      document.getElementById('withdraw_cashbox_name').textContent = name;
      document.getElementById('withdraw_amount').value = '';
      document.getElementById('withdraw_description').value = '';
      var modal = new bootstrap.Modal(document.getElementById('withdrawModal'));
      modal.show();
    }
    
    function showReturnModal(withdrawalId, maxAmount, description) {
      document.getElementById('return_withdrawal_id').value = withdrawalId;
      document.getElementById('return_withdrawal_description').textContent = description;
      document.getElementById('return_max_amount').textContent = maxAmount.toFixed(2);
      document.getElementById('return_amount').value = '';
      document.getElementById('return_amount').max = maxAmount;
      document.getElementById('return_description').value = '';
      var modal = new bootstrap.Modal(document.getElementById('returnModal'));
      modal.show();
    }
    </script>
</body>
</html> 