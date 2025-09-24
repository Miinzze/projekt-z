<?php
session_start();

// Datenbank Konfiguration
define('DB_HOST', 'localhost');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');

// Discord OAuth2 Konfiguration
define('DISCORD_CLIENT_ID', '');
define('DISCORD_CLIENT_SECRET', '');
define('DISCORD_REDIRECT_URI', 'http://projekt-z.eu/callback.php'); // Anpassen!
define('DISCORD_BOT_TOKEN', '');
define('DISCORD_GUILD_ID', '');

// Basis URL
define('BASE_URL', 'http://projekt-z.eu'); // Anpassen!

// Datenbank Verbindung
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}

// Funktionen
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function hasPermission($permission) {
    global $pdo;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $stmt = $pdo->prepare("
        SELECT r.permissions 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    
    if (!$result) {
        return false;
    }
    
    $permissions = json_decode($result['permissions'], true);
    return isset($permissions[$permission]) && $permissions[$permission] === true;
}

function requirePermission($permission) {
    if (!hasPermission($permission)) {
        die("Keine Berechtigung!");
    }
}

function getCurrentUser() {
    global $pdo;
    
    if (!isLoggedIn()) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function getSetting($key, $default = '') {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    
    return $result ? $result['setting_value'] : $default;
}

function sendDiscordDM($userId, $message) {
    if (empty(DISCORD_BOT_TOKEN)) {
        return false;
    }

    try {
        // Schritt 1: DM Channel erstellen/abrufen
        $dmData = json_encode(['recipient_id' => $userId]);
        
        $ch = curl_init('https://discord.com/api/v10/users/@me/channels');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bot ' . DISCORD_BOT_TOKEN,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dmData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $dmResponse = curl_exec($ch);
        $dmHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($dmHttpCode !== 200) {
            return false;
        }
        
        $dmChannel = json_decode($dmResponse, true);
        if (!isset($dmChannel['id'])) {
            return false;
        }
        
        // Schritt 2: Nachricht in DM Channel senden
        $messageData = json_encode(['content' => $message]);
        
        $ch = curl_init('https://discord.com/api/v10/channels/' . $dmChannel['id'] . '/messages');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bot ' . DISCORD_BOT_TOKEN,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $messageData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $messageResponse = curl_exec($ch);
        $messageHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $messageHttpCode === 200;
        
    } catch (Exception $e) {
        return false;
    }
}

function sendDiscordMessage($webhookUrl, $content) {
    $data = json_encode(['content' => $content]);
    
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

function checkDiscordServerMembership($userId) {
    $url = "https://discord.com/api/v10/guilds/" . DISCORD_GUILD_ID . "/members/" . $userId;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bot ' . DISCORD_BOT_TOKEN
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

function canUserApply($userId) {
    global $pdo;
    
    // Prüfen ob Whitelist aktiviert ist
    if (getSetting('whitelist_enabled', '1') !== '1') {
        return [
            'can_apply' => false,
            'reason' => 'disabled',
            'message' => 'Die Whitelist ist derzeit geschlossen.'
        ];
    }
    
    // Cooldown-Zeit abrufen
    $cooldownHours = (int)getSetting('whitelist_cooldown_hours', '24');
    
    // Letzte Bewerbung des Users finden
    $stmt = $pdo->prepare("
        SELECT submitted_at 
        FROM whitelist_applications 
        WHERE user_id = ? 
        ORDER BY submitted_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $lastApplication = $stmt->fetch();
    
    if ($lastApplication) {
        $lastSubmissionTime = strtotime($lastApplication['submitted_at']);
        $cooldownEndTime = $lastSubmissionTime + ($cooldownHours * 3600); // Stunden in Sekunden
        $currentTime = time();
        
        if ($currentTime < $cooldownEndTime) {
            $remainingHours = ceil(($cooldownEndTime - $currentTime) / 3600);
            
            return [
                'can_apply' => false,
                'reason' => 'cooldown',
                'message' => "Du kannst dich erst in {$remainingHours} Stunden erneut bewerben.",
                'remaining_hours' => $remainingHours,
                'next_application' => date('d.m.Y H:i', $cooldownEndTime)
            ];
        }
    }
    
    return [
        'can_apply' => true,
        'reason' => 'allowed',
        'message' => 'Du kannst dich bewerben.'
    ];
}