<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('db.php');

function getBuildings() {
    global $pdo;
    $sql = "SELECT id, name, address FROM buildings ORDER BY name";
    $stmt = $pdo->query($sql);
    $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $buildings;
}

function getCurrentBuilding() {
    global $pdo;
    if (isset($_SESSION['current_building_id'])) {
        $sql = "SELECT id, name, address FROM buildings WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['current_building_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return $result;
        }
    }
    return null;
}

function setCurrentBuilding($building_id) {
    $_SESSION['current_building_id'] = $building_id;
}

function renderBuildingSelector() {
    global $pdo;
    $buildings = $pdo->query("SELECT * FROM buildings ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $currentBuilding = getCurrentBuilding();
    
    $html = '<select name="building_id" id="building_id" class="form-select" onchange="this.form.submit()">';
    $html .= '<option value="">Всички сгради</option>';
    
    foreach ($buildings as $building) {
        $selected = ($currentBuilding && $currentBuilding['id'] == $building['id']) ? 'selected' : '';
        $html .= sprintf(
            '<option value="%d" %s>%s</option>',
            $building['id'],
            $selected,
            htmlspecialchars($building['name'])
        );
    }
    
    $html .= '</select>';
    
    return $html;
}
?> 