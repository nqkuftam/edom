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

    <div class="container">
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
        
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-building fa-2x mb-3" style="color: var(--primary-color);"></i>
                <h3><?php echo $totalBuildings; ?></h3>
                <p>Общ брой сгради</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-home fa-2x mb-3" style="color: var(--primary-color);"></i>
                <h3><?php echo $totalApartments; ?></h3>
                <p>Общ брой апартаменти</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-money-bill-wave fa-2x mb-3" style="color: var(--primary-color);"></i>
                <h3><?php echo number_format($totalDebts, 2); ?> лв.</h3>
                <p>Общ дълг</p>
            </div>
        </div>
        
        <div class="card">
            <h2 class="mb-4"><i class="fas fa-history"></i> Последни плащания</h2>
            <?php if ($recentPayments): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Сграда</th>
                            <th>Апартамент</th>
                            <th>Сума</th>
                            <th>Метод</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPayments as $payment): ?>
                        <tr>
                            <td><?php echo date('d.m.Y', strtotime($payment['payment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($payment['building_name']); ?></td>
                            <td><?php echo htmlspecialchars($payment['apartment_number']); ?></td>
                            <td><?php echo number_format($payment['amount'], 2); ?> лв.</td>
                            <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted">Няма намерени плащания.</p>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
