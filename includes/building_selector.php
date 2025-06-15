<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('db.php');

function getBuildings() {
    global $conn;
    $sql = "SELECT id, name, address FROM buildings ORDER BY name";
    $result = $conn->query($sql);
    $buildings = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $buildings[] = $row;
        }
    }
    return $buildings;
}

function getCurrentBuilding() {
    if (isset($_SESSION['current_building_id'])) {
        global $conn;
        $sql = "SELECT id, name, address FROM buildings WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['current_building_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
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
    
    $html = '<div class="building-selector mb-4">';
    $html .= '<form method="POST" action="set_building.php" class="d-flex align-items-center">';
    $html .= '<label for="building_id" class="me-2">Изберете сграда:</label>';
    $html .= '<select name="building_id" id="building_id" class="form-select me-2" style="width: auto;">';
    
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
    $html .= '<button type="submit" class="btn btn-primary">Избери</button>';
    $html .= '</form>';
    $html .= '</div>';
    
    return $html;
}
?> 