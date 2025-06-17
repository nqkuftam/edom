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
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

try {
    // Вземане на текущата сграда
    $currentBuilding = getCurrentBuilding();
    
    // Вземане на историята на обитателите
    $stmt = $pdo->prepare("
        SELECT 
            rh.*,
            a.number as apartment_number,
            a.type as apartment_type,
            CONCAT(rh.first_name, ' ', rh.last_name) as resident_name
        FROM resident_history rh
        JOIN apartments a ON rh.apartment_id = a.id
        WHERE a.building_id = ?
        ORDER BY rh.move_in_date DESC, rh.apartment_id
    ");
    $stmt->execute([$currentBuilding['id']]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    <title>Домова книга | Електронен Домоуправител</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once 'includes/styles.php'; ?>
    <style>
        .history-table th {
            position: sticky;
            top: 0;
            background: white;
            z-index: 1;
        }
        .resident-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        .status-current {
            background-color: #d4edda;
            color: #155724;
        }
        .status-past {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Домова книга</h1>
            <?php echo renderNavigation('resident_history'); ?>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Назад към таблото</a>
        
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

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped history-table">
                        <thead>
                            <tr>
                                <th>Апартамент</th>
                                <th>Обитател</th>
                                <th>Статус</th>
                                <th>Телефон</th>
                                <th>Имейл</th>
                                <th>Настаняване</th>
                                <th>Напускане</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $record): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $typeIcon = $record['apartment_type'] === 'apartment' ? 'home' : 
                                              ($record['apartment_type'] === 'garage' ? 'car' : 'building');
                                    ?>
                                    <i class="fas fa-<?php echo $typeIcon; ?>"></i>
                                    <?php echo htmlspecialchars($record['apartment_number']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($record['resident_name']); ?>
                                    <?php if ($record['is_owner']): ?>
                                        <span class="badge bg-primary">Собственик</span>
                                    <?php endif; ?>
                                    <?php if ($record['is_primary']): ?>
                                        <span class="badge bg-success">Основен</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['move_out_date'] === null): ?>
                                        <span class="resident-status status-current">Текущ</span>
                                    <?php else: ?>
                                        <span class="resident-status status-past">Бивш</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($record['phone'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($record['email'] ?? '-'); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($record['move_in_date'])); ?></td>
                                <td>
                                    <?php 
                                    echo $record['move_out_date'] 
                                        ? date('d.m.Y', strtotime($record['move_out_date']))
                                        : '-';
                                    ?>
                                </td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick="showResidentDetails(<?php echo $record['id']; ?>)">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Модален прозорец за детайли -->
    <div class="modal fade" id="residentDetailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Детайли за обитател</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="residentDetailsContent">
                    <!-- Тук ще се зареждат детайлите динамично -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showResidentDetails(id) {
            fetch(`get_resident_history.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    const content = document.getElementById('residentDetailsContent');
                    content.innerHTML = `
                        <div class="mb-3">
                            <h6>Основна информация</h6>
                            <p><strong>Име:</strong> ${data.first_name} ${data.last_name}</p>
                            <p><strong>Телефон:</strong> ${data.phone || '-'}</p>
                            <p><strong>Имейл:</strong> ${data.email || '-'}</p>
                        </div>
                        <div class="mb-3">
                            <h6>Статус</h6>
                            <p>
                                ${data.is_owner ? '<span class="badge bg-primary">Собственик</span> ' : ''}
                                ${data.is_primary ? '<span class="badge bg-success">Основен обитател</span>' : ''}
                            </p>
                        </div>
                        <div class="mb-3">
                            <h6>Период на обитаване</h6>
                            <p><strong>Настаняване:</strong> ${new Date(data.move_in_date).toLocaleDateString('bg-BG')}</p>
                            <p><strong>Напускане:</strong> ${data.move_out_date ? new Date(data.move_out_date).toLocaleDateString('bg-BG') : 'Текущ обитател'}</p>
                        </div>
                    `;
                    
                    new bootstrap.Modal(document.getElementById('residentDetailsModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Възникна грешка при зареждане на детайлите.');
                });
        }
    </script>
</body>
</html> 