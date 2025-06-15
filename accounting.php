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

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

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
    <ul class="nav nav-tabs mb-4" id="accountingTabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#cashboxes"><i class="fas fa-cash-register"></i> Каси</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#model"><i class="fas fa-list"></i> Модел на таксуване</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#budget"><i class="fas fa-table"></i> Бюджет</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#payments"><i class="fas fa-credit-card"></i> Плащания</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#debts"><i class="fas fa-exclamation-triangle"></i> Задължения</a></li>
    </ul>
    <div class="tab-content bg-white p-4 rounded shadow-sm">
        <!-- Каси -->
        <div class="tab-pane fade show active" id="cashboxes">
            <div class="row">
                <div class="col-lg-7 col-md-10 mx-auto">
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                            <span><i class="fas fa-cash-register"></i> Каси</span>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addCashboxModal"><i class="fas fa-plus"></i> Нова каса</button>
                        </div>
                        <div class="card-body p-4">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light"><tr><th>Име</th><th>Салдо</th><th class="text-center">Операции</th></tr></thead>
                                <tbody>
                                <?php foreach ($cashboxes as $cb): ?>
                                    <tr>
                                        <td class="fw-bold"><i class="fas fa-wallet me-1"></i> <?php echo htmlspecialchars($cb['name']); ?></td>
                                        <td class="text-end text-primary fw-bold"><?php echo number_format($cb['balance'], 2); ?> лв.</td>
                                        <td class="text-center">
                                            <button class="btn btn-outline-success btn-sm me-1" data-bs-toggle="modal" data-bs-target="#cashInModal<?php echo $cb['id']; ?>"><i class="fas fa-arrow-down"></i> Внасяне</button>
                                            <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cashOutModal<?php echo $cb['id']; ?>"><i class="fas fa-arrow-up"></i> Изваждане</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Модали за въвеждане/извеждане на пари -->
            <?php foreach ($cashboxes as $cb): ?>
            <div class="modal fade" id="cashInModal<?php echo $cb['id']; ?>" tabindex="-1">
                <div class="modal-dialog"><div class="modal-content">
                    <form method="POST">
                        <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="fas fa-arrow-down"></i> Внасяне в каса: <?php echo htmlspecialchars($cb['name']); ?></h5></div>
                        <div class="modal-body">
                            <input type="hidden" name="cashbox_id" value="<?php echo $cb['id']; ?>">
                            <input type="hidden" name="cash_action" value="in">
                            <div class="mb-3">
                                <label class="form-label">Сума</label>
                                <input type="number" class="form-control" name="amount" min="0.01" step="0.01" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Затвори</button>
                            <button type="submit" class="btn btn-success">Внеси</button>
                        </div>
                    </form>
                </div></div>
            </div>
            <div class="modal fade" id="cashOutModal<?php echo $cb['id']; ?>" tabindex="-1">
                <div class="modal-dialog"><div class="modal-content">
                    <form method="POST">
                        <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="fas fa-arrow-up"></i> Изваждане от каса: <?php echo htmlspecialchars($cb['name']); ?></h5></div>
                        <div class="modal-body">
                            <input type="hidden" name="cashbox_id" value="<?php echo $cb['id']; ?>">
                            <input type="hidden" name="cash_action" value="out">
                            <div class="mb-3">
                                <label class="form-label">Сума</label>
                                <input type="number" class="form-control" name="amount" min="0.01" step="0.01" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Затвори</button>
                            <button type="submit" class="btn btn-danger">Извади</button>
                        </div>
                    </form>
                </div></div>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- Модел на таксуване -->
        <div class="tab-pane fade" id="model">
            <div class="card p-4 shadow-sm">Тук ще бъде моделът на таксуване...</div>
        </div>
        <!-- Бюджет -->
        <div class="tab-pane fade" id="budget">
            <div class="card p-4 shadow-sm">Тук ще бъде бюджетът...</div>
        </div>
        <!-- Плащания -->
        <div class="tab-pane fade" id="payments">
            <div class="card p-4 shadow-sm">Тук ще бъдат плащанията...</div>
        </div>
        <!-- Задължения -->
        <div class="tab-pane fade" id="debts">
            <div class="card p-4 shadow-sm">Тук ще бъдат задълженията...</div>
        </div>
    </div>
</div>
<!-- Модал за добавяне на нова каса -->
<div class="modal fade" id="addCashboxModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="accounting.php">
            <input type="hidden" name="action" value="add_cashbox">
            <div class="modal-header"><h5 class="modal-title">Добави нова каса</h5></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Име на касата</label>
                    <input type="text" class="form-control" name="cashbox_name" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Затвори</button>
                <button type="submit" class="btn btn-primary">Добави</button>
            </div>
        </form>
    </div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 