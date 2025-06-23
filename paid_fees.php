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
require_once 'includes/styles.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$currentBuilding = getCurrentBuilding();

// Вземане на имотите
$query = "SELECT a.*, b.name as building_name FROM properties a JOIN buildings b ON a.building_id = b.id";
$params = [];
if ($currentBuilding) {
    $query .= " WHERE a.building_id = ?";
    $params[] = $currentBuilding['id'];
}
$query .= " ORDER BY b.name, a.number";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ако се показва баланс на имот, вземи го от ledger
foreach ($properties as &$property) {
    $stmt = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) FROM property_ledger WHERE property_id = ?");
    $stmt->execute([$property['id']]);
    $property['balance'] = $stmt->fetchColumn() ?: 0;
}
unset($property);

// Филтри
$filter_property = isset($_GET['filter_property']) ? (int)$_GET['filter_property'] : 0;
$filter_method = $_GET['filter_method'] ?? '';
$filter_date_from = $_GET['filter_date_from'] ?? '';
$filter_date_to = $_GET['filter_date_to'] ?? '';

// Вземане на платените такси
$query = "
    SELECT p.*, a.number as apartment_number, b.name as building_name, f.description as fee_description
    FROM payments p
    JOIN properties a ON p.property_id = a.id
    JOIN buildings b ON a.building_id = b.id
    JOIN fees f ON p.fee_id = f.id
    WHERE 1=1
";
$params = [];
if ($currentBuilding) {
    $query .= " AND a.building_id = ?";
    $params[] = $currentBuilding['id'];
}
if ($filter_property) {
    $query .= " AND a.id = ?";
    $params[] = $filter_property;
}
if ($filter_method) {
    $query .= " AND p.payment_method = ?";
    $params[] = $filter_method;
}
if ($filter_date_from) {
    $query .= " AND p.payment_date >= ?";
    $params[] = $filter_date_from;
}
if ($filter_date_to) {
    $query .= " AND p.payment_date <= ?";
    $params[] = $filter_date_to;
}
$query .= " ORDER BY p.payment_date DESC, p.id DESC, b.name, a.number";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$paid_fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$payment_methods = ['В брой', 'Банков превод', 'Карта', 'Друг'];
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Платени такси | Електронен Домоуправител</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once 'includes/styles.php'; ?>
</head>
<body>
    <div class="container mt-4">
        <a href="payments.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Назад към плащанията</a>
        <h2><i class="fas fa-list"></i> Платени такси</h2>
        <form method="GET" class="row g-3 align-items-end mb-4">
            <div class="col-md-3">
                <label for="property_id" class="form-label">Имот:</label>
                <select class="form-control" id="property_id" name="apartment_id" required>
                    <option value="">Изберете имот</option>
                    <?php foreach ($properties as $property): ?>
                        <option value="<?php echo $property['id']; ?>">
                            <?php echo htmlspecialchars($property['building_name'] . ' - Имот ' . $property['number']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filter_method" class="form-label">Метод на плащане:</label>
                <select name="filter_method" id="filter_method" class="form-select">
                    <option value="">Всички</option>
                    <?php foreach ($payment_methods as $method): ?>
                        <option value="<?php echo $method; ?>" <?php if ($filter_method == $method) echo 'selected'; ?>><?php echo $method; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filter_date_from" class="form-label">От дата:</label>
                <input type="date" name="filter_date_from" id="filter_date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
            </div>
            <div class="col-md-3">
                <label for="filter_date_to" class="form-label">До дата:</label>
                <input type="date" name="filter_date_to" id="filter_date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
            </div>
            <div class="col-md-12 text-end">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Филтрирай</button>
            </div>
        </form>
        <div class="card p-3">
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-sm">
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
                        <?php foreach ($paid_fees as $fee): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fee['building_name'] . ' - ' . $fee['apartment_number']); ?></td>
                            <td><?php echo number_format($fee['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($fee['payment_date']); ?></td>
                            <td><?php echo htmlspecialchars($fee['payment_method']); ?></td>
                            <td><?php echo htmlspecialchars($fee['fee_description']); ?></td>
                            <td><?php echo htmlspecialchars($fee['notes']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html> 