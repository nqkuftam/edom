<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Справки</h1>
        
        <div class="report-filters">
            <form method="POST" class="filter-form">
                <div class="form-group">
                    <label for="report_type">Тип справка:</label>
                    <select name="report_type" id="report_type" required>
                        <option value="">Изберете тип справка</option>
                        <option value="building_debt">Задължения по сграда</option>
                        <option value="period_payments">Плащания за период</option>
                        <option value="monthly_fees">Месечни такси</option>
                    </select>
                </div>

                <div class="form-group building-select" style="display: none;">
                    <label for="building_id">Сграда:</label>
                    <select name="building_id" id="building_id">
                        <option value="">Изберете сграда</option>
                        <?php foreach ($buildings as $building): ?>
                            <option value="<?php echo $building['id']; ?>">
                                <?php echo htmlspecialchars($building['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group date-range" style="display: none;">
                    <label for="start_date">От дата:</label>
                    <input type="date" name="start_date" id="start_date">
                    
                    <label for="end_date">До дата:</label>
                    <input type="date" name="end_date" id="end_date">
                </div>

                <button type="submit" class="btn">Генерирай справка</button>
            </form>
        </div>

        <?php if (!empty($reportData)): ?>
            <div class="report-results">
                <h2>Резултати от справката</h2>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
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
                <button onclick="window.print()" class="btn">Печат</button>
            </div>
        <?php endif; ?>
    </div>

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
