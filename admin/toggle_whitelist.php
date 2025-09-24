<?php
require_once '../config.php';
requireLogin();
requirePermission('toggle_whitelist');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'open') {
        // Whitelist öffnen
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES ('whitelist_enabled', '1') 
            ON DUPLICATE KEY UPDATE setting_value = '1'
        ");
        $stmt->execute();
        
        header('Location: dashboard.php?whitelist=opened');
    } elseif ($action === 'close') {
        // Whitelist schließen
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES ('whitelist_enabled', '0') 
            ON DUPLICATE KEY UPDATE setting_value = '0'
        ");
        $stmt->execute();
        
        header('Location: dashboard.php?whitelist=closed');
    } else {
        header('Location: dashboard.php');
    }
} else {
    header('Location: dashboard.php');
}
exit;