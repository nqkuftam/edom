<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/navigation.php';
require_once 'includes/building_selector.php';

// Проверка дали потребителят е логнат
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Вземане на всички сгради за филтриране
$stmt = $pdo->query("SELECT * FROM buildings ORDER BY name");
$buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Обработка на заявката за справка
$reportData = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $building_id = isset($_POST['building_id']) ? (int)$_POST['building_id'] : 0;
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $report_type = $_POST['report_type'] ?? '';

    switch ($report_type) {
        case 'building_debt':
            // Справка за задължения по сграда
            $stmt = $pdo->prepare("
                SELECT 
                    a.number as apartment_number,
                    a.owner_name,
                    SUM(f.amount) as total_fees,
                    COALESCE(SUM(p.amount), 0) as total_payments,
                    (SUM(f.amount) - COALESCE(SUM(p.amount), 0)) as debt
                FROM apartments a
                LEFT JOIN fees f ON a.id = f.apartment_id
                LEFT JOIN payments p ON a.id = p.apartment_id
                WHERE a.building_id = ?
                GROUP BY a.id, a.number, a.owner_name
                ORDER BY a.number
            ");
            $stmt->execute([$building_id]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'period_payments':
            // Справка за плащания за период
            $stmt = $pdo->prepare("
                SELECT 
                    b.name as building_name,
                    a.number as apartment_number,
                    a.owner_name,
                    p.amount,
                    p.payment_date,
                    p.description
                FROM payments p
                JOIN apartments a ON p.apartment_id = a.id
                JOIN buildings b ON a.building_id = b.id
                WHERE p.payment_date BETWEEN ? AND ?
                ORDER BY p.payment_date DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'monthly_fees':
            // Справка за месечни такси
            $stmt = $pdo->prepare("
                SELECT 
                    b.name as building_name,
                    a.number as apartment_number,
                    a.owner_name,
                    f.amount,
                    f.month,
                    f.year
                FROM fees f
                JOIN apartments a ON f.apartment_id = a.id
                JOIN buildings b ON a.building_id = b.id
                WHERE f.year = YEAR(?) AND f.month = MONTH(?)
                ORDER BY b.name, a.number
            ");
            $stmt->execute([$start_date, $start_date]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Справки | Електронен Домоуправител</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once 'includes/styles.php'; ?>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-chart-bar"></i> Справки</h1>
            <?php echo renderNavigation('reports'); ?>
        </div>
    </div>
    <div class="container">
        <?php echo renderBuildingSelector(); ?>
        <?php $currentBuilding = getCurrentBuilding(); if ($currentBuilding): ?>
        <div class="building-info">
            <h4><i class="fas fa-building"></i> Текуща сграда: <?php echo htmlspecialchars($currentBuilding['name']); ?></h4>
            <p><i class="fas fa-map-marker-alt"></i> Адрес: <?php echo htmlspecialchars($currentBuilding['address']); ?></p>
        </div>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Назад към таблото</a>
        <div class="card mb-4 p-4">
            <form method="POST" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="report_type" class="form-label"><i class="fas fa-list"></i> Тип справка:</label>
                    <select name="report_type" id="report_type" class="form-select" required>
                        <option value="">Изберете тип справка</option>
                        <option value="building_debt">Задължения по сграда</option>
                        <option value="period_payments">Плащания за период</option>
                        <option value="monthly_fees">Месечни такси</option>
                    </select>
                </div>
                <div class="col-md-4 building-select" style="display: none;">
                    <label for="building_id" class="form-label"><i class="fas fa-building"></i> Сграда:</label>
                    <select name="building_id" id="building_id" class="form-select">
                        <option value="">Изберете сграда</option>
                        <?php foreach ($buildings as $building): ?>
                            <option value="<?php echo $building['id']; ?>">
                                <?php echo htmlspecialchars($building['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 date-range" style="display: none;">
                    <label for="start_date" class="form-label"><i class="fas fa-calendar-alt"></i> От дата:</label>
                    <input type="date" name="start_date" id="start_date" class="form-control">
                    <label for="end_date" class="form-label mt-2"><i class="fas fa-calendar-alt"></i> До дата:</label>
                    <input type="date" name="end_date" id="end_date" class="form-control">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Генерирай справка</button>
                </div>
            </form>
        </div>
        <?php if (!empty($reportData)): ?>
            <div class="card p-4">
                <h2><i class="fas fa-table"></i> Резултати от справката</h2>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <?php
                                if (!empty($reportData)) {
                                    foreach (array_keys($reportData[0]) as $header) {
                                        echo "<th>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $header))) . "</th>";
                                    }
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                        <td><?php echo htmlspecialchars($value); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button onclick="window.print()" class="btn btn-success mt-3"><i class="fas fa-print"></i> Печат</button>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('report_type').addEventListener('change', function() {
            const buildingSelect = document.querySelector('.building-select');
            const dateRange = document.querySelector('.date-range');
            switch(this.value) {
                case 'building_debt':
                    buildingSelect.style.display = 'block';
                    dateRange.style.display = 'none';
                    break;
                case 'period_payments':
                    buildingSelect.style.display = 'none';
                    dateRange.style.display = 'block';
                    break;
                case 'monthly_fees':
                    buildingSelect.style.display = 'none';
                    dateRange.style.display = 'block';
                    break;
                default:
                    buildingSelect.style.display = 'none';
                    dateRange.style.display = 'none';
            }
        });
    </script>
</body>
</html>
