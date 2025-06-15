<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Проверка дали потребителят е логнат
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Вземане на статистика за dashboard
$totalBuildings = 0; // TODO: Имплементирай функция за броя сгради
$totalApartments = 0; // TODO: Имплементирай функция за броя апартаменти
$totalDebt = 0; // TODO: Имплементирай функция за общата сума на задълженията
$recentPayments = []; // TODO: Имплементирай функция за последните плащания
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Табло | Електронен Домоуправител</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #fff;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background-color: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            margin: 0 0 0.5rem 0;
            color: #666;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .menu-item {
            background-color: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #333;
            text-align: center;
            transition: transform 0.2s;
        }
        .menu-item:hover {
            transform: translateY(-2px);
        }
        .menu-item h3 {
            margin: 0 0 0.5rem 0;
        }
        .menu-item p {
            margin: 0;
            color: #666;
        }
        .logout {
            float: right;
            color: #dc3545;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Електронен Домоуправител</h1>
        <a href="logout.php" class="logout">Изход</a>
    </div>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Общо сгради</h3>
                <div class="value"><?php echo $totalBuildings; ?></div>
            </div>
            <div class="stat-card">
                <h3>Общо апартаменти</h3>
                <div class="value"><?php echo $totalApartments; ?></div>
            </div>
            <div class="stat-card">
                <h3>Общо задължения</h3>
                <div class="value"><?php echo number_format($totalDebt, 2); ?> лв.</div>
            </div>
        </div>

        <div class="menu-grid">
            <a href="buildings.php" class="menu-item">
                <h3>Сгради</h3>
                <p>Управление на сграи и входове</p>
            </a>
            <a href="apartments.php" class="menu-item">
                <h3>Апартаменти</h3>
                <p>Управление на апартаменти и собственици</p>
            </a>
            <a href="fees.php" class="menu-item">
                <h3>Такси</h3>
                <p>Създаване и управление на месечни такси</p>
            </a>
            <a href="payments.php" class="menu-item">
                <h3>Плащания</h3>
                <p>Отбелязване и проследяване на плащания</p>
            </a>
            <a href="reports.php" class="menu-item">
                <h3>Справки</h3>
                <p>Генериране на отчети и статистики</p>
            </a>
        </div>
    </div>
</body>
</html>
