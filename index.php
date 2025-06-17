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
// Проверка дали потребителят е логнат
if (!isset($_SESSION['user_id']) || !isLoggedIn()) {
    $_SESSION['error'] = 'Моля, влезте в системата за да продължите.';
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

try {
    // Вземане на текущата сграда
    $currentBuilding = getCurrentBuilding();
    
    // Вземане на статистики
    $totalBuildings = 0;
    $totalApartments = 0;
    $totalDebts = 0;
    $recentPayments = [];
    
    // Общ брой сгради
    $stmt = $pdo->query("SELECT COUNT(*) FROM buildings");
    $totalBuildings = $stmt->fetchColumn();
    
    // Общ брой апартаменти
    $stmt = $pdo->query("SELECT COUNT(*) FROM apartments");
    $totalApartments = $stmt->fetchColumn();
    
    // Общ дълг
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(f.amount), 0) - COALESCE(SUM(p.amount), 0) as total_debt
        FROM fees f
        LEFT JOIN payments p ON f.id = p.fee_id
    ");
    $totalDebts = $stmt->fetchColumn();
    
    // Последни плащания
    $stmt = $pdo->query("
        SELECT p.*, a.number as apartment_number, b.name as building_name
        FROM payments p
        JOIN apartments a ON p.apartment_id = a.id
        JOIN buildings b ON a.building_id = b.id
        ORDER BY p.payment_date DESC
        LIMIT 5
    ");
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Зареждам бележките от базата за текущата сграда:
    $notes = [];
    if ($currentBuilding) {
        $stmt = $pdo->prepare("SELECT n.*, u.username FROM building_notes n LEFT JOIN users u ON n.user_id = u.id WHERE n.building_id = ? ORDER BY n.created_at DESC");
        $stmt->execute([$currentBuilding['id']]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Вземане на касите за текущата сграда
    $cashboxes = [];
    if ($currentBuilding) {
        $stmt = $pdo->prepare("SELECT * FROM cashboxes WHERE building_id = ?");
        $stmt->execute([$currentBuilding['id']]);
        $cashboxes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error = handlePDOError($e);
} catch (Exception $e) {
    $error = handleError($e);
}

// При обработка на POST заявка с action=add_note, добавям нова бележка в базата
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) && $_POST['action'] === 'add_note'
) {
    $note = trim($_POST['note'] ?? '');
    if ($note && $currentBuilding) {
        $user_id = $_SESSION['user_id'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO building_notes (building_id, user_id, note) VALUES (?, ?, ?)");
        $stmt->execute([$currentBuilding['id'], $user_id, $note]);
        header('Location: index.php');
        exit();
    }
}

// При обработка на POST заявка с action=delete_note, изтривам бележка от базата
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) && $_POST['action'] === 'delete_note'
) {
    $note_id = (int)($_POST['note_id'] ?? 0);
    if ($note_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM building_notes WHERE id = ?");
        $stmt->execute([$note_id]);
        header('Location: index.php');
        exit();
    }
}

require_once 'includes/styles.php';
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Табло | Електронен Домоуправител</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once 'includes/styles.php'; ?>
</head>
<body>
    <div class="header mb-4">
        <div class="header-content container">
            <h1 class="display-5 fw-bold mb-0"><i class="fas fa-home"></i> Електронен Домоуправител</h1>
            <?php echo renderNavigation('index'); ?>
        </div>
    </div>

    <div class="container">
        <?php if ($currentBuilding): ?>
        <div class="building-info">
            <h4 class="d-flex align-items-center">
                <i class="fas fa-building me-2"></i> Текуща сграда:
                <?php echo renderBuildingSelector(); ?>
            </h4>
            <p><i class="fas fa-map-marker-alt"></i> Адрес: <?php echo htmlspecialchars($currentBuilding['address']); ?></p>
        </div>
        <?php endif; ?>
        <div class="row">
            <!-- Лява колона -->
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3"><i class="fas fa-cog"></i> Управление</h5>
                        <hr>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2"><i class="fas fa-building"></i> <a href="properties.php">Имоти</a></li>
                            <li><i class="fas fa-euro-sign"></i> <a href="accounting.php">Счетоводство</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3"><i class="fas fa-sticky-note"></i> Бележки</h5>
                        <ul class="list-group mb-3">
                            <?php foreach ($notes as $n): ?>
                            <li class="list-group-item small d-flex align-items-center justify-content-between">
                                <span>
                                    <span class="text-muted"><?php echo date('Y-m-d', strtotime($n['created_at'])); ?></span>
                                    - <?php echo htmlspecialchars($n['note']); ?>
                                </span>
                                <button class="btn btn-outline-danger btn-sm ms-2" onclick="deleteNote(<?php echo $n['id']; ?>)"><i class="fas fa-trash"></i></button>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <form method="POST" action="index.php">
                            <input type="hidden" name="action" value="add_note">
                            <div class="mb-2">
                                <textarea class="form-control" rows="2" name="note" placeholder="Нова бележка..." required></textarea>
                            </div>
                            <button class="btn btn-primary btn-sm w-100"><i class="fas fa-plus"></i> Добави</button>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Дясна колона -->
            <div class="col-md-8 col-lg-9">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-4"><i class="fas fa-cash-register"></i> Каси</h4>
                        <?php if ($cashboxes): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-sm align-middle">
                                <thead class="table-dark">
                                    <tr><th>Име</th><th>Салдо</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cashboxes as $cb): ?>
                                    <tr>
                                        <td><i class="fas fa-wallet me-1"></i> <?php echo htmlspecialchars($cb['name']); ?></td>
                                        <td class="text-end text-primary fw-bold"><?php echo number_format($cb['balance'], 2); ?> лв.</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-muted">Няма каси за тази сграда.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function deleteNote(id) {
        if (confirm('Сигурни ли сте, че искате да изтриете тази бележка?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="delete_note"><input type="hidden" name="note_id" value="' + id + '">';
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>
