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
    $buildings = getBuildings();
    $currentBuilding = getCurrentBuilding();
    
    if (empty($buildings)) {
        return '<div class="alert alert-warning">Няма налични сгради</div>';
    }

    // Ако няма избрана сграда, избери първата и я сетни в сесията
    if (!$currentBuilding && count($buildings) > 0) {
        setCurrentBuilding($buildings[0]['id']);
        $currentBuilding = getCurrentBuilding();
    }

    $html = '<div class="building-selector mb-4">';
    $html .= '<form method="POST" action="set_building.php" class="d-flex align-items-center">';
    $html .= '<label for="building_id" class="me-2">Изберете сграда:</label>';
    $html .= '<select name="building_id" id="building_id" class="form-select me-2" style="width: auto;" onchange="this.form.submit()">';
    
    foreach ($buildings as $building) {
        $selected = ($currentBuilding && $currentBuilding['id'] == $building['id']) ? 'selected' : '';
        $html .= sprintf(
            '<option value="%d" %s>%s - %s</option>',
            $building['id'],
            $selected,
            htmlspecialchars($building['name']),
            htmlspecialchars($building['address'])
        );
    }
    
    $html .= '</select>';
    // Премахнат бутон 'Избери'
    $html .= '</form>';
    $html .= '</div>';
    
    return $html;
}
?> 