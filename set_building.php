<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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