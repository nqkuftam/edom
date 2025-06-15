<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/building_selector.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['building_id'])) {
    $building_id = (int)$_POST['building_id'];
    setCurrentBuilding($building_id);
}

// Пренасочване към предишната страница
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
header("Location: $redirect");
exit;
?> 