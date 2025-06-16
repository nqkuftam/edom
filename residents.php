<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Проверка за автентикация
checkAuth();

$db = getDBConnection();

// Обработка на формата за добавяне/редактиране на обитател
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $apartment_id = $_POST['apartment_id'];
            $first_name = $_POST['first_name'];
            $last_name = $_POST['last_name'];
            $phone = $_POST['phone'];
            $email = $_POST['email'];
            $is_owner = isset($_POST['is_owner']) ? 1 : 0;
            $is_primary = isset($_POST['is_primary']) ? 1 : 0;
            $move_in_date = $_POST['move_in_date'];
            $move_out_date = !empty($_POST['move_out_date']) ? $_POST['move_out_date'] : null;
            $notes = $_POST['notes'];

            if ($_POST['action'] === 'add') {
                $stmt = $db->prepare("INSERT INTO residents (apartment_id, first_name, last_name, phone, email, is_owner, is_primary, move_in_date, move_out_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$apartment_id, $first_name, $last_name, $phone, $email, $is_owner, $is_primary, $move_in_date, $move_out_date, $notes]);
            } else {
                $resident_id = $_POST['resident_id'];
                $stmt = $db->prepare("UPDATE residents SET apartment_id = ?, first_name = ?, last_name = ?, phone = ?, email = ?, is_owner = ?, is_primary = ?, move_in_date = ?, move_out_date = ?, notes = ? WHERE id = ?");
                $stmt->execute([$apartment_id, $first_name, $last_name, $phone, $email, $is_owner, $is_primary, $move_in_date, $move_out_date, $notes, $resident_id]);
            }
        } elseif ($_POST['action'] === 'delete') {
            $resident_id = $_POST['resident_id'];
            $stmt = $db->prepare("DELETE FROM residents WHERE id = ?");
            $stmt->execute([$resident_id]);
        }
    }
    header('Location: residents.php');
    exit;
}

// Вземане на всички сгради
$buildings = $db->query("SELECT * FROM buildings ORDER BY name")->fetchAll();

// Вземане на избраната сграда
$selected_building_id = isset($_GET['building_id']) ? $_GET['building_id'] : null;

// Вземане на апартаменти за избраната сграда
$apartments = [];
if ($selected_building_id) {
    $stmt = $db->prepare("SELECT * FROM apartments WHERE building_id = ? ORDER BY number");
    $stmt->execute([$selected_building_id]);
    $apartments = $stmt->fetchAll();
}

// Вземане на обитатели за избран апартамент
$residents = [];
$selected_apartment_id = isset($_GET['apartment_id']) ? $_GET['apartment_id'] : null;
if ($selected_apartment_id) {
    $stmt = $db->prepare("SELECT r.*, a.number as apartment_number, b.name as building_name 
                         FROM residents r 
                         JOIN apartments a ON r.apartment_id = a.id 
                         JOIN buildings b ON a.building_id = b.id 
                         WHERE r.apartment_id = ? 
                         ORDER BY r.is_primary DESC, r.move_in_date DESC");
    $stmt->execute([$selected_apartment_id]);
    $residents = $stmt->fetchAll();
}

$page_title = "Управление на обитатели";
include 'includes/header.php';
?>

<div class="container mt-4">
    <h1 class="mb-4"><?php echo $page_title; ?></h1>

    <!-- Форма за избор на сграда -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label for="building_id" class="form-label">Изберете сграда:</label>
                    <select name="building_id" id="building_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Изберете сграда...</option>
                        <?php foreach ($buildings as $building): ?>
                            <option value="<?php echo $building['id']; ?>" <?php echo $selected_building_id == $building['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($building['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selected_building_id): ?>
        <!-- Форма за избор на апартамент -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="building_id" value="<?php echo $selected_building_id; ?>">
                    <div class="col-md-6">
                        <label for="apartment_id" class="form-label">Изберете апартамент:</label>
                        <select name="apartment_id" id="apartment_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Изберете апартамент...</option>
                            <?php foreach ($apartments as $apartment): ?>
                                <option value="<?php echo $apartment['id']; ?>" <?php echo $selected_apartment_id == $apartment['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($apartment['number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected_apartment_id): ?>
            <!-- Форма за добавяне на обитател -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Добави нов обитател</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="apartment_id" value="<?php echo $selected_apartment_id; ?>">
                        
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">Име:</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Фамилия:</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Телефон:</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Имейл:</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="move_in_date" class="form-label">Дата на настаняване:</label>
                            <input type="date" class="form-control" id="move_in_date" name="move_in_date" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="move_out_date" class="form-label">Дата на напускане:</label>
                            <input type="date" class="form-control" id="move_out_date" name="move_out_date">
                        </div>
                        
                        <div class="col-md-12">
                            <label for="notes" class="form-label">Бележки:</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_owner" name="is_owner">
                                <label class="form-check-label" for="is_owner">Собственик</label>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_primary" name="is_primary">
                                <label class="form-check-label" for="is_primary">Основен обитател</label>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Добави обитател</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Списък с обитатели -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Списък с обитатели</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($residents)): ?>
                        <p class="text-muted">Няма добавени обитатели за този апартамент.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Име</th>
                                        <th>Телефон</th>
                                        <th>Имейл</th>
                                        <th>Статус</th>
                                        <th>Дата на настаняване</th>
                                        <th>Дата на напускане</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($residents as $resident): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($resident['phone']); ?></td>
                                            <td><?php echo htmlspecialchars($resident['email']); ?></td>
                                            <td>
                                                <?php
                                                $status = [];
                                                if ($resident['is_owner']) $status[] = 'Собственик';
                                                if ($resident['is_primary']) $status[] = 'Основен';
                                                echo implode(', ', $status);
                                                ?>
                                            </td>
                                            <td><?php echo date('d.m.Y', strtotime($resident['move_in_date'])); ?></td>
                                            <td><?php echo $resident['move_out_date'] ? date('d.m.Y', strtotime($resident['move_out_date'])) : '-'; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" onclick="editResident(<?php echo htmlspecialchars(json_encode($resident)); ?>)">
                                                    Редактирай
                                                </button>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="resident_id" value="<?php echo $resident['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Сигурни ли сте, че искате да изтриете този обитател?')">
                                                        Изтрий
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Модален прозорец за редактиране -->
<div class="modal fade" id="editResidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редактиране на обитател</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editResidentForm" class="row g-3">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="resident_id" id="edit_resident_id">
                    <input type="hidden" name="apartment_id" value="<?php echo $selected_apartment_id; ?>">
                    
                    <div class="col-md-6">
                        <label for="edit_first_name" class="form-label">Име:</label>
                        <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="edit_last_name" class="form-label">Фамилия:</label>
                        <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="edit_phone" class="form-label">Телефон:</label>
                        <input type="tel" class="form-control" id="edit_phone" name="phone">
                    </div>
                    
                    <div class="col-md-6">
                        <label for="edit_email" class="form-label">Имейл:</label>
                        <input type="email" class="form-control" id="edit_email" name="email">
                    </div>
                    
                    <div class="col-md-6">
                        <label for="edit_move_in_date" class="form-label">Дата на настаняване:</label>
                        <input type="date" class="form-control" id="edit_move_in_date" name="move_in_date" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="edit_move_out_date" class="form-label">Дата на напускане:</label>
                        <input type="date" class="form-control" id="edit_move_out_date" name="move_out_date">
                    </div>
                    
                    <div class="col-md-12">
                        <label for="edit_notes" class="form-label">Бележки:</label>
                        <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_owner" name="is_owner">
                            <label class="form-check-label" for="edit_is_owner">Собственик</label>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_primary" name="is_primary">
                            <label class="form-check-label" for="edit_is_primary">Основен обитател</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отказ</button>
                <button type="submit" form="editResidentForm" class="btn btn-primary">Запази промените</button>
            </div>
        </div>
    </div>
</div>

<script>
function editResident(resident) {
    document.getElementById('edit_resident_id').value = resident.id;
    document.getElementById('edit_first_name').value = resident.first_name;
    document.getElementById('edit_last_name').value = resident.last_name;
    document.getElementById('edit_phone').value = resident.phone;
    document.getElementById('edit_email').value = resident.email;
    document.getElementById('edit_move_in_date').value = resident.move_in_date;
    document.getElementById('edit_move_out_date').value = resident.move_out_date || '';
    document.getElementById('edit_notes').value = resident.notes;
    document.getElementById('edit_is_owner').checked = resident.is_owner == 1;
    document.getElementById('edit_is_primary').checked = resident.is_primary == 1;
    
    new bootstrap.Modal(document.getElementById('editResidentModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?> 