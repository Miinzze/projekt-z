<?php
require_once '../config.php';
requireLogin();
requirePermission('roles_manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

try {
    // User Rolle (ID 1)
    $userPermissions = [
        'view_news' => true,
        'features_view' => true,
        'rules_view' => true
    ];
    
    $stmt = $pdo->prepare("UPDATE roles SET permissions = ? WHERE id = 1");
    $stmt->execute([json_encode($userPermissions)]);
    
    // Moderator Rolle (ID 2)
    $moderatorPermissions = [
        'view_news' => true,
        'features_view' => true,
        'rules_view' => true,
        'whitelist_view_applications' => true,
        'whitelist_manage_applications' => true,
        'dashboard_access' => true,
        'statistics_view' => true
    ];
    
    $stmt = $pdo->prepare("UPDATE roles SET permissions = ? WHERE id = 2");
    $stmt->execute([json_encode($moderatorPermissions)]);
    
    // Admin Rolle (ID 3) - Alle Berechtigungen
    $adminPermissions = [
        // News
        'view_news' => true,
        'news_create' => true,
        'news_edit' => true,
        'news_delete' => true,
        'news_publish' => true,
        
        // Whitelist
        'whitelist_view_applications' => true,
        'whitelist_manage_applications' => true,
        'whitelist_questions_manage' => true,
        'whitelist_toggle' => true,
        'whitelist_settings' => true,
        
        // Features
        'features_view' => true,
        'features_create' => true,
        'features_edit' => true,
        'features_delete' => true,
        
        // Rules
        'rules_view' => true,
        'rules_create' => true,
        'rules_edit' => true,
        'rules_delete' => true,
        
        // System
        'dashboard_access' => true,
        'users_view' => true,
        'users_manage' => true,
        'roles_manage' => true,
        'settings_general' => true,
        'settings_discord' => true,
        'settings_advanced' => true,
        'statistics_view' => true
    ];
    
    $stmt = $pdo->prepare("UPDATE roles SET permissions = ? WHERE id = 3");
    $stmt->execute([json_encode($adminPermissions)]);
    
    echo 'success';
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Fehler: ' . $e->getMessage();
}