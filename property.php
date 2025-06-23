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

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!defined('PROPERTY_TYPES')) {
    define('PROPERTY_TYPES', [
        'apartment' => 'Апартамент',
        'garage' => 'Гараж',
        'room' => 'Стая',
        'office' => 'Офис',
        'shop' => 'Магазин',
        'warehouse' => 'Склад'
    ]);
}

$error = '';
$success = '';
$property = null;
$residents = [];
$notes = [];
$currentBuilding = getCurrentBuilding();
$cashboxes = [];
if ($currentBuilding) {
    $stmt = $pdo->prepare("SELECT * FROM cashboxes WHERE building_id = ?");
    $stmt->execute([$currentBuilding['id']]);
    $cashboxes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        throw new Exception('Невалиден имот.');
    }
    // Вземи имота
    $stmt = $pdo->prepare('SELECT a.*, b.name as building_name FROM properties a LEFT JOIN buildings b ON a.building_id = b.id WHERE a.id = ?');
    $stmt->execute([$id]);
    $property = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$property) {
        throw new Exception('Имотът не е намерен.');
    }
    // Вземи обитателите (собственик първи)
    $stmt = $pdo->prepare('SELECT * FROM residents WHERE property_id = ? ORDER BY FIELD(status, "owner", "tenant", "user"), id ASC');
    $stmt->execute([$id]);
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Вземи бележките за имота
    $stmt = $pdo->prepare('SELECT n.*, u.username FROM property_notes n LEFT JOIN users u ON n.user_id = u.id WHERE n.property_id = ? ORDER BY n.created_at DESC');
    $stmt->execute([$id]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = handlePDOError($e);
} catch (Exception $e) {
    $error = handleError($e);
}

// Обработка на POST заявки за бележки
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_note') {
            $note = trim($_POST['note'] ?? '');
            if ($note && $property) {
                $user_id = $_SESSION['user_id'] ?? null;
                $stmt = $pdo->prepare('INSERT INTO property_notes (property_id, user_id, note) VALUES (?, ?, ?)');
                $stmt->execute([$property['id'], $user_id, $note]);
                header('Location: property.php?id=' . $property['id']);
                exit();
            }
        } elseif ($_POST['action'] === 'edit_note') {
            $note_id = (int)($_POST['note_id'] ?? 0);
            $note = trim($_POST['note'] ?? '');
            if ($note_id > 0 && $note) {
                $stmt = $pdo->prepare('UPDATE property_notes SET note = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$note, $note_id]);
                header('Location: property.php?id=' . $property['id']);
                exit();
            }
        } elseif ($_POST['action'] === 'delete_note') {
            $note_id = (int)($_POST['note_id'] ?? 0);
            if ($note_id > 0) {
                $stmt = $pdo->prepare('DELETE FROM property_notes WHERE id = ?');
                $stmt->execute([$note_id]);
                header('Location: property.php?id=' . $property['id']);
                exit();
            }
        } elseif ($_POST['action'] === 'restore_fee') {
            $fp_id = (int)$_POST['fee_property_id'];
            if ($fp_id > 0) {
                $stmt = $pdo->prepare('UPDATE fee_properties SET is_paid = 0 WHERE id = ?');
                $stmt->execute([$fp_id]);
                header('Location: property.php?id=' . $property['id']);
                exit();
            }
        } elseif ($_POST['action'] === 'add_fee') {
            $description = trim($_POST['fee_description'] ?? '');
            $amount = (float)($_POST['fee_amount'] ?? 0);
            $cashbox_id = (int)($_POST['cashbox_id'] ?? 0);
            if ($description && $amount > 0 && $cashbox_id > 0) {
                // Добави индивидуална такса директно във fee_properties с fee_id = NULL
                $stmt = $pdo->prepare("INSERT INTO fee_properties (fee_id, property_id, amount, description, is_paid, cashbox_id) VALUES (NULL, ?, ?, ?, 0, ?)");
                $stmt->execute([$property['id'], $amount, $description, $cashbox_id]);
                header('Location: property.php?id=' . $property['id']);
                exit();
            } else {
                $error = 'Моля, попълнете всички полета правилно.';
            }
        } elseif ($_POST['action'] === 'add_payment_to_balance') {
            $amount = (float)($_POST['payment_amount'] ?? 0);
            $payment_method = trim($_POST['payment_method'] ?? 'В брой');
            $description = trim($_POST['payment_description'] ?? 'Плащане към баланс');
            if ($amount > 0) {
                try {
                    // 1. Добави плащане (ledger)
                    $stmt = $pdo->prepare("INSERT INTO property_ledger (property_id, type, amount, description) VALUES (?, 'credit', ?, ?)");
                    $stmt->execute([$property['id'], $amount, $description]);
                    $success = 'Сумата е добавена успешно!';
                    header('Location: property.php?id=' . $property['id']);
                    exit();
                } catch (Exception $e) {
                    $error = 'Грешка при добавяне на баланс: ' . $e->getMessage();
                }
            }
        }
    }
}

// POST обработка за обитатели
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) && in_array($_POST['action'], ['add_resident', 'edit_resident', 'delete_resident'])
) {
    if ($_POST['action'] === 'add_resident') {
        $property_id = $property['id'];
        $first_name = $_POST['first_name'] ?? '';
        $middle_name = $_POST['middle_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $status = $_POST['status'] ?? 'user';
        $move_in_date = $_POST['move_in_date'] ?? '';
        $move_out_date = $_POST['move_out_date'] ?? '';
        $notes = $_POST['notes'] ?? '';
        if (!empty($first_name) && !empty($last_name) && !empty($move_in_date)) {
            $stmt = $pdo->prepare("INSERT INTO residents (property_id, first_name, middle_name, last_name, phone, email, status, move_in_date, move_out_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$property_id, $first_name, $middle_name, $last_name, $phone, $email, $status, $move_in_date, $move_out_date ?: null, $notes]);
            header('Location: property.php?id=' . $property['id']);
            exit();
        }
    } elseif ($_POST['action'] === 'edit_resident') {
        $id = (int)($_POST['id'] ?? 0);
        $property_id = $property['id'];
        $first_name = $_POST['first_name'] ?? '';
        $middle_name = $_POST['middle_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $status = $_POST['status'] ?? 'user';
        $move_in_date = $_POST['move_in_date'] ?? '';
        $move_out_date = $_POST['move_out_date'] ?? '';
        $notes = $_POST['notes'] ?? '';
        if ($id > 0 && !empty($first_name) && !empty($last_name) && !empty($move_in_date)) {
            $stmt = $pdo->prepare("UPDATE residents SET property_id = ?, first_name = ?, middle_name = ?, last_name = ?, phone = ?, email = ?, status = ?, move_in_date = ?, move_out_date = ?, notes = ? WHERE id = ?");
            $stmt->execute([$property_id, $first_name, $middle_name, $last_name, $phone, $email, $status, $move_in_date, $move_out_date ?: null, $notes, $id]);
            header('Location: property.php?id=' . $property['id']);
            exit();
        }
    } elseif ($_POST['action'] === 'delete_resident') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM residents WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: property.php?id=' . $property['id']);
            exit();
        }
    }
}

// Четвърто табло: Суми, задължения, баланс
$monthly_fees_sum = 0;
$current_debt = 0;
$balance = 0;
$monthly_fees = [];
// Шесто табло: минали задължения
$paid_fees = [];

try {
    // Сума на всички такси за този имот (включително индивидуалните с fee_id = NULL)
    $stmt = $pdo->prepare("
        SELECT SUM(fa.amount) as total 
        FROM fee_properties fa 
        LEFT JOIN fees f ON fa.fee_id = f.id 
        WHERE fa.property_id = ? AND (f.type = 'monthly' OR fa.fee_id IS NULL)
    ");
    $stmt->execute([$property['id']]);
    $monthly_fees_sum = $stmt->fetchColumn() ?: 0;
    
    // Текущи неплатени такси (задължения) - включително индивидуалните
    $stmt = $pdo->prepare("
        SELECT SUM(fa.amount) as debt 
        FROM fee_properties fa 
        WHERE fa.property_id = ? AND fa.is_paid = 0
    ");
    $stmt->execute([$property['id']]);
    $current_debt = $stmt->fetchColumn() ?: 0;
    
    // Баланс
    $stmt = $pdo->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) FROM property_ledger WHERE property_id = ?");
    $stmt->execute([$property['id']]);
    $balance = $stmt->fetchColumn() ?: 0;
    
    // Всички такси за този имот (включително индивидуалните с fee_id = NULL)
    $stmt = $pdo->prepare("
        SELECT fa.*, 
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
               END as fee_type
        FROM fee_properties fa 
        LEFT JOIN fees f ON fa.fee_id = f.id 
        WHERE fa.property_id = ? 
        ORDER BY COALESCE(f.created_at, fa.created_at) DESC
    ");
    $stmt->execute([$property['id']]);
    $monthly_fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Минали задължения (включително индивидуалните)
    $stmt = $pdo->prepare("
        SELECT fa.id as fa_id, 
               CASE 
                   WHEN fa.fee_id IS NULL THEN fa.description 
                   ELSE f.description 
               END as description,
               CASE 
                   WHEN fa.fee_id IS NULL THEN fa.created_at 
                   ELSE f.created_at 
               END as created_at,
               c.name as cashbox_name, 
               fa.amount, 
               CASE 
                   WHEN fa.fee_id IS NULL THEN 'individual' 
                   ELSE f.type 
               END as type
        FROM fee_properties fa
        LEFT JOIN fees f ON fa.fee_id = f.id
        LEFT JOIN cashboxes c ON COALESCE(f.cashbox_id, fa.cashbox_id) = c.id
        WHERE fa.property_id = ? AND fa.is_paid = 1
        ORDER BY COALESCE(f.created_at, fa.created_at) DESC
    ");
    $stmt->execute([$property['id']]);
    $paid_fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Може да се добави обработка на грешки
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Детайли за имот | Електронен Домоуправител</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php require_once 'includes/styles.php'; ?>
    <style>
        .dashboard-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .dashboard-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Детайли за имот</h1>
            <?php echo renderNavigation('properties'); ?>
        </div>
    </div>
    <div class="container mt-4">
        <a href="properties.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Назад към имотите</a>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php elseif (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php else: ?>
        <div class="row">
            <div class="col-md-4">
                <!-- Първо табло: Тип и номер -->
                <div class="dashboard-box mb-3">
                    <div class="dashboard-title">Информация за имота</div>
                    <div><strong>Тип:</strong> <?php echo htmlspecialchars(PROPERTY_TYPES[$property['type']] ?? $property['type']); ?></div>
                    <div><strong>Сграда:</strong> <?php echo htmlspecialchars($property['building_name']); ?></div>
                    <?php if ($property['number']): ?>
                        <div><strong>Номер:</strong> <?php echo htmlspecialchars($property['number']); ?></div>
                    <?php endif; ?>
                    <div><strong>Етаж:</strong> <?php echo htmlspecialchars($property['floor']); ?></div>
                    <div><strong>Площ:</strong> <?php echo htmlspecialchars($property['area']); ?> м²</div>
                    <div><strong>Идеални части:</strong> <?php echo htmlspecialchars($property['ideal_parts']); ?>%</div>
                </div>
                <!-- Четвърто табло: Финансово табло -->
                <div class="dashboard-box mb-3">
                    <div class="dashboard-title">Финансово табло</div>
                    <div><strong>Общо месечни такси:</strong> <?php echo number_format($monthly_fees_sum, 2); ?> лв.</div>
                    <div><strong>Текущи задължения:</strong> <span class="text-danger"><?php echo number_format($current_debt, 2); ?> лв.</span></div>
                    <div><strong>Баланс:</strong> <span class="text-success"><?php echo number_format($balance, 2); ?> лв.</span></div>
                    <div class="mt-3 d-flex gap-2">
                        <button class="btn btn-success btn-sm" onclick="showAddFeeModal()"><i class="fas fa-plus"></i> Добави такса</button>
                        <button class="btn btn-info btn-sm" onclick="showAddPaymentModal()"><i class="fas fa-wallet"></i> Добави сума към баланс</button>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <!-- Второ табло: Обитатели -->
                <div class="dashboard-box mb-3">
                    <div class="dashboard-title d-flex justify-content-between align-items-center">
                        <span>Обитатели</span>
                        <button class="btn btn-primary btn-sm" onclick="showAddResidentModal()"><i class="fas fa-plus"></i> Добави обитател</button>
                    </div>
                    <?php if (count($residents) === 0): ?>
                        <div class="alert alert-info">Няма добавени обитатели.</div>
                    <?php else: ?>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Име</th>
                                    <th>Телефон</th>
                                    <th>Имейл</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($residents as $r): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($r['phone'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($r['email'] ?: '-'); ?></td>
                                        <td>
                                            <p><strong><i class="fas fa-key"></i> Статус:</strong> 
                                                <?php 
                                                switch ($r['status']) {
                                                    case 'owner':
                                                        echo '<span class="badge bg-primary">Собственик</span>';
                                                        break;
                                                    case 'tenant':
                                                        echo '<span class="badge bg-success">Наемател</span>';
                                                        break;
                                                    case 'resident':
                                                        echo '<span class="badge bg-info">Обитател</span>';
                                                        break;
                                                    case 'user':
                                                        echo '<span class="badge bg-secondary">Ползвател</span>';
                                                        break;
                                                    default:
                                                        echo '<span class="badge bg-light text-dark">' . htmlspecialchars($r['status']) . '</span>';
                                                }
                                                ?>
                                            </p>
                                        </td>
                                        <td>
                                            <button class="btn btn-warning btn-sm" onclick='showEditResidentModal(<?php echo json_encode($r); ?>)'><i class="fas fa-edit"></i></button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Сигурни ли сте, че искате да изтриете този обитател?');">
                                                <input type="hidden" name="action" value="delete_resident">
                                                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <!-- Трето табло: Бележки -->
                <div class="dashboard-box mb-3">
                    <div class="dashboard-title">Бележки за имота</div>
                    <ul class="list-group mb-3">
                        <?php foreach ($notes as $n): ?>
                        <li class="list-group-item d-flex align-items-center justify-content-between">
                            <span>
                                <span class="text-muted"><?php echo date('Y-m-d', strtotime($n['created_at'])); ?></span>
                                <?php if ($n['username']): ?>
                                    <span class="text-secondary">(<?php echo htmlspecialchars($n['username']); ?>)</span>
                                <?php endif; ?>
                                - <?php echo htmlspecialchars($n['note']); ?>
                            </span>
                            <span>
                                <button class="btn btn-outline-primary btn-sm me-1" onclick="showEditNoteModal(<?php echo $n['id']; ?>, <?php echo htmlspecialchars(json_encode($n['note'])); ?>)"><i class="fas fa-edit"></i></button>
                                <form method="POST" action="property.php?id=<?php echo $property['id']; ?>" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_note">
                                    <input type="hidden" name="note_id" value="<?php echo $n['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <form method="POST" action="property.php?id=<?php echo $property['id']; ?>">
                        <input type="hidden" name="action" value="add_note">
                        <div class="mb-2">
                            <textarea class="form-control" rows="2" name="note" placeholder="Нова бележка..." required></textarea>
                        </div>
                        <button class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Добави</button>
                    </form>
                </div>
                <!-- Модал за редакция на бележка -->
                <div class="modal fade" id="editNoteModal" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <form method="POST" action="property.php?id=<?php echo $property['id']; ?>">
                        <input type="hidden" name="action" value="edit_note">
                        <input type="hidden" name="note_id" id="edit_note_id">
                        <div class="modal-header">
                          <h5 class="modal-title"><i class="fas fa-edit"></i> Редакция на бележка</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                          <textarea class="form-control" name="note" id="edit_note_text" rows="3" required></textarea>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                          <button type="submit" class="btn btn-primary">Запази</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
                <script>
                function showEditNoteModal(id, note) {
                  document.getElementById('edit_note_id').value = id;
                  document.getElementById('edit_note_text').value = note;
                  var modal = new bootstrap.Modal(document.getElementById('editNoteModal'));
                  modal.show();
                }
                </script>
                <!-- Модал за добавяне на обитател -->
                <div class="modal fade" id="addResidentModal" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <form method="POST">
                        <input type="hidden" name="action" value="add_resident">
                        <div class="modal-header">
                          <h5 class="modal-title"><i class="fas fa-plus"></i> Добави обитател</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                          <div class="form-group mb-2">
                            <label for="first_name" class="form-label">Име:</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                          </div>
                          <div class="form-group mb-2">
                            <label for="middle_name" class="form-label">Презиме:</label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name">
                          </div>
                          <div class="form-group mb-2">
                            <label for="last_name" class="form-label">Фамилия:</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                          </div>
                          <div class="form-group mb-2">
                            <label for="phone" class="form-label">Телефон:</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                          </div>
                          <div class="form-group mb-2">
                            <label for="email" class="form-label">Имейл:</label>
                            <input type="email" class="form-control" id="email" name="email">
                          </div>
                          <div class="form-group">
                            <label for="status" class="form-label">Статус:</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="user">Ползвател</option>
                                <option value="resident">Обитател</option>
                                <option value="tenant">Наемател</option>
                                <option value="owner">Собственик</option>
                            </select>
                          </div>
                          <div class="form-group mb-2">
                            <label for="move_in_date" class="form-label">Дата на настаняване:</label>
                            <input type="date" class="form-control" id="move_in_date" name="move_in_date" required>
                          </div>
                          <div class="form-group mb-2">
                            <label for="move_out_date" class="form-label">Дата на напускане:</label>
                            <input type="date" class="form-control" id="move_out_date" name="move_out_date">
                          </div>
                          <div class="form-group mb-2">
                            <label for="notes" class="form-label">Бележки:</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                          <button type="submit" class="btn btn-primary">Добави</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
                <!-- Модал за редакция на обитател -->
                <div class="modal fade" id="editResidentModal" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <form method="POST">
                        <input type="hidden" name="action" value="edit_resident">
                        <input type="hidden" name="id" id="edit_resident_id">
                        <div class="modal-header">
                          <h5 class="modal-title"><i class="fas fa-edit"></i> Редактирай обитател</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                          <div class="form-group mb-2">
                            <label for="edit_first_name" class="form-label">Име:</label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                          </div>
                          <div class="form-group mb-2">
                            <label for="edit_middle_name" class="form-label">Презиме:</label>
                            <input type="text" class="form-control" id="edit_middle_name" name="middle_name">
                          </div>
                          <div class="form-group mb-2">
                            <label for="edit_last_name" class="form-label">Фамилия:</label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                          </div>
                          <div class="form-group mb-2">
                            <label for="edit_phone" class="form-label">Телефон:</label>
                            <input type="tel" class="form-control" id="edit_phone" name="phone">
                          </div>
                          <div class="form-group mb-2">
                            <label for="edit_email" class="form-label">Имейл:</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                          </div>
                          <div class="form-group">
                            <label for="edit_status" class="form-label">Статус:</label>
                            <select class="form-control" id="edit_status" name="status" required>
                                <option value="user">Ползвател</option>
                                <option value="resident">Обитател</option>
                                <option value="tenant">Наемател</option>
                                <option value="owner">Собственик</option>
                            </select>
                          </div>
                          <div class="form-group mb-2">
                            <label for="edit_move_in_date" class="form-label">Дата на настаняване:</label>
                            <input type="date" class="form-control" id="edit_move_in_date" name="move_in_date" required>
                          </div>
                          <div class="form-group mb-2">
                            <label for="edit_move_out_date" class="form-label">Дата на напускане:</label>
                            <input type="date" class="form-control" id="edit_move_out_date" name="move_out_date">
                          </div>
                          <div class="form-group mb-2">
                            <label for="edit_notes" class="form-label">Бележки:</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="2"></textarea>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                          <button type="submit" class="btn btn-primary">Запази</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
                <script>
                function showAddResidentModal() {
                  var modal = new bootstrap.Modal(document.getElementById('addResidentModal'));
                  document.getElementById('addResidentModal').querySelector('form').reset();
                  modal.show();
                }
                function showEditResidentModal(resident) {
                  document.getElementById('edit_resident_id').value = resident.id;
                  document.getElementById('edit_first_name').value = resident.first_name;
                  document.getElementById('edit_middle_name').value = resident.middle_name || '';
                  document.getElementById('edit_last_name').value = resident.last_name;
                  document.getElementById('edit_phone').value = resident.phone;
                  document.getElementById('edit_email').value = resident.email;
                  document.getElementById('edit_status').value = resident.status;
                  document.getElementById('edit_move_in_date').value = resident.move_in_date;
                  document.getElementById('edit_move_out_date').value = resident.move_out_date || '';
                  document.getElementById('edit_notes').value = resident.notes || '';
                  var modal = new bootstrap.Modal(document.getElementById('editResidentModal'));
                  modal.show();
                }
                </script>
                <!-- Пето табло: Всички месечни такси -->
                <div class="dashboard-box mb-3">
                    <div class="dashboard-title">Такси за имота</div>
                    <?php if (count($monthly_fees) === 0): ?>
                        <div class="alert alert-info">Няма такси за този имот.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-sm">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Тип</th>
                                        <th>Описание</th>
                                        <th>Сума (лв.)</th>
                                        <th>Дата</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthly_fees as $fee): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            switch($fee['fee_type']) {
                                                case 'monthly': echo '<span class="badge bg-primary">Месечна</span>'; break;
                                                case 'temporary': echo '<span class="badge bg-warning">Временна</span>'; break;
                                                case 'individual': echo '<span class="badge bg-info">Индивидуална</span>'; break;
                                                default: echo '<span class="badge bg-secondary">' . htmlspecialchars($fee['fee_type']) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($fee['description']); ?></td>
                                        <td><?php echo number_format($fee['amount'], 2); ?></td>
                                        <td><?php echo date('d.m.Y', strtotime($fee['created_at'])); ?></td>
                                        <td>
                                            <?php if ($fee['is_paid']): ?>
                                                <span class="badge bg-success">Платена</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Неплатена</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Шесто табло: Минали задължения -->
                <div class="dashboard-box mb-3">
                    <div class="dashboard-title">Минали задължения</div>
                    <?php if (count($paid_fees) === 0): ?>
                        <div class="alert alert-info">Няма минали задължения за този имот.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-sm">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Месец</th>
                                        <th>Каса</th>
                                        <th>Сума (лв.)</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paid_fees as $fee): ?>
                                    <tr>
                                        <td><?php echo date('m.Y', strtotime($fee['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($fee['cashbox_name'] ?: '-'); ?></td>
                                        <td><?php echo number_format($fee['amount'], 2); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Сигурни ли сте, че искате да върнете това задължение към активно?');">
                                                <input type="hidden" name="action" value="restore_fee">
                                                <input type="hidden" name="fee_property_id" value="<?php echo $fee['fa_id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-undo"></i> Върни</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- История на движенията по баланса (ledger) -->
                <div class="dashboard-box mb-3">
                    <div class="dashboard-title">История на баланса</div>
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM property_ledger WHERE property_id = ? ORDER BY created_at DESC");
                    $stmt->execute([$property['id']]);
                    $ledger = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Тип</th>
                                    <th>Сума</th>
                                    <th>Описание</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ledger as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($row['created_at']))); ?></td>
                                    <td><?php echo $row['type'] === 'credit' ? 'Приход' : 'Разход'; ?></td>
                                    <td class="text-<?php echo $row['type'] === 'credit' ? 'success' : 'danger'; ?>">
                                        <?php echo number_format($row['amount'], 2); ?> лв.
                                    </td>
                                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showAddModal() {
            var modal = new bootstrap.Modal(document.getElementById('addModal'));
            modal.show();
        }
        function showAddFeeModal() {
            var modalEl = document.getElementById('addFeeModal');
            if (!modalEl) return;
            var modal = new bootstrap.Modal(modalEl);
            modalEl.querySelector('form').reset();
            modal.show();
        }
        function showAddPaymentModal() {
            var modalEl = document.getElementById('addPaymentModal');
            if (!modalEl) return;
            var modal = new bootstrap.Modal(modalEl);
            modalEl.querySelector('form').reset();
            modal.show();
        }
    </script>
    <!-- Модал за добавяне на такса -->
    <div class="modal fade" id="addFeeModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="action" value="add_fee">
            <div class="modal-header">
              <h5 class="modal-title"><i class="fas fa-plus"></i> Добави такса</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="form-group mb-2">
                <label for="fee_description" class="form-label">Описание:</label>
                <input type="text" class="form-control" id="fee_description" name="fee_description" required>
              </div>
              <div class="form-group mb-2">
                <label for="fee_amount" class="form-label">Сума (лв.):</label>
                <input type="number" class="form-control" id="fee_amount" name="fee_amount" step="0.01" min="0.01" required>
              </div>
              <div class="form-group mb-2">
                <label for="fee_cashbox_id" class="form-label">Каса:</label>
                <select class="form-control" id="fee_cashbox_id" name="cashbox_id" required>
                    <option value="">Изберете каса</option>
                    <?php foreach ($cashboxes as $cb): ?>
                        <option value="<?php echo $cb['id']; ?>"><?php echo htmlspecialchars($cb['name']); ?></option>
                    <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
              <button type="submit" class="btn btn-success">Добави</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <!-- Модал за добавяне на сума към баланс -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="action" value="add_payment_to_balance">
            <div class="modal-header">
              <h5 class="modal-title"><i class="fas fa-wallet"></i> Добави сума към баланс</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="form-group mb-2">
                <label for="payment_amount" class="form-label">Сума (лв.):</label>
                <input type="number" class="form-control" id="payment_amount" name="payment_amount" step="0.01" min="0.01" required>
              </div>
              <div class="form-group mb-2">
                <label for="payment_method" class="form-label">Метод на плащане:</label>
                <select class="form-control" id="payment_method" name="payment_method">
                  <option value="В брой">В брой</option>
                  <option value="Банков превод">Банков превод</option>
                  <option value="Карта">Карта</option>
                  <option value="Друг">Друг</option>
                </select>
              </div>
              <div class="form-group mb-2">
                <label for="payment_description" class="form-label">Описание:</label>
                <input type="text" class="form-control" id="payment_description" name="payment_description" value="Плащане към баланс">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
              <button type="submit" class="btn btn-info">Добави</button>
            </div>
          </form>
        </div>
      </div>
    </div>
</body>
</html> 