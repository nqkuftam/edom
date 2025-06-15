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
    
} catch (PDOException $e) {
    $error = handlePDOError($e);
} catch (Exception $e) {
    $error = handleError($e);
}

// При обработка на POST заявка с action=add_note, добавям нова бележка в базата
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_note') {
    $note = trim($_POST['note'] ?? '');
    if ($note && $currentBuilding) {
        $user_id = $_SESSION['user_id'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO building_notes (building_id, user_id, note) VALUES (?, ?, ?)");
        $stmt->execute([$currentBuilding['id'], $user_id, $note]);
        header('Location: index.php');
        exit();
    }
}
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
    <div class="header">
        <div class="header-content">
            <h1>Електронен Домоуправител</h1>
            <?php echo renderNavigation('index'); ?>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Лява колона -->
            <div class="col-md-3">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Управление</h5>
                        <p><strong>Силвия Великова</strong><br><a href="mailto:es.silistra@gmail.com">es.silistra@gmail.com</a></p>
                        <hr>
                        <ul class="list-unstyled mb-3">
                            <li><i class="fas fa-building"></i> <a href="apartments.php">Имотии</a> <span class="badge bg-secondary">24</span></li>
                            <li><i class="fas fa-euro-sign"></i> <a href="accounting.php">Счетоводство</a></li>
                        </ul>
                        <div class="d-flex justify-content-between mb-3">
                            <button class="btn btn-outline-success btn-sm"><i class="fas fa-home"></i></button>
                            <button class="btn btn-outline-info btn-sm"><i class="fas fa-users"></i></button>
                            <button class="btn btn-outline-primary btn-sm"><i class="fas fa-euro-sign"></i></button>
                            <button class="btn btn-outline-danger btn-sm"><i class="fas fa-bell"></i></button>
                        </div>
                        <button class="btn btn-success w-100">Сигнализирай</button>
                    </div>
                </div>
                <!-- Бележки -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Бележки</h5>
                        <ul class="list-group mb-3">
                            <?php foreach ($notes as $n): ?>
                            <li class="list-group-item small">
                                <span class="text-muted"><?php echo date('Y-m-d', strtotime($n['created_at'])); ?></span>
                                <?php if ($n['username']): ?> - <b><?php echo htmlspecialchars($n['username']); ?></b><?php endif; ?>
                                - <?php echo htmlspecialchars($n['note']); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <form method="POST" action="index.php">
                            <input type="hidden" name="action" value="add_note">
                            <div class="mb-2">
                                <textarea class="form-control" rows="2" name="note" placeholder="Нова бележка..." required></textarea>
                            </div>
                            <button class="btn btn-primary btn-sm w-100">Добави</button>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Дясна колона -->
            <div class="col-md-9">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Съобщения</h5>
                                <p class="text-muted">Няма нови съобщения</p>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Информация</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Каси</h5>
                                <div class="row">
                                    <div class="col-6 text-center">
                                        <div class="h4">0.00 ЛВ</div>
                                        <div class="text-muted">САЛДО</div>
                                    </div>
                                    <div class="col-6 text-center">
                                        <div class="h4">264.16 ЛВ</div>
                                        <div class="text-muted">НА РАЗПОЛОЖЕНИЕ</div>
                                    </div>
                                </div>
                                <table class="table table-sm mt-3">
                                    <thead>
                                        <tr><th>Текущи задължения</th><th>Просрочени</th><th>Салдо</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>ОК</td><td>393.33 лв.</td><td>-223.55 лв.</td></tr>
                                        <tr><td>ФРО</td><td>105.25 лв.</td><td>388.75 лв.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Документи</h5>
                                <form>
                                    <div class="mb-2">
                                        <input type="file" class="form-control" multiple>
                                    </div>
                                    <div class="mb-2">
                                        <select class="form-select">
                                            <option>Директория</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-primary w-100">Прикачи</button>
                                </form>
                                <div class="mt-2 text-muted">Няма прикачени документи</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
