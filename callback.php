<?php
require_once 'config.php';

if (!isset($_GET['code'])) {
    header('Location: login.php?error=no_code');
    exit;
}

$code = $_GET['code'];

// Token von Discord abrufen
$tokenUrl = 'https://discord.com/api/oauth2/token';
$tokenData = [
    'client_id' => DISCORD_CLIENT_ID,
    'client_secret' => DISCORD_CLIENT_SECRET,
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => DISCORD_REDIRECT_URI
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$tokenResponse = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($tokenResponse, true);

if (!isset($tokenData['access_token'])) {
    header('Location: login.php?error=token_error');
    exit;
}

$accessToken = $tokenData['access_token'];

// Benutzerinformationen abrufen
$userUrl = 'https://discord.com/api/v10/users/@me';
$ch = curl_init($userUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$userResponse = curl_exec($ch);
curl_close($ch);

$userData = json_decode($userResponse, true);

if (!isset($userData['id'])) {
    header('Location: login.php?error=user_error');
    exit;
}

// Prüfen ob Benutzer auf Discord-Server ist (wenn aktiviert)
if (getSetting('require_discord_server_member', '1') === '1') {
    if (!checkDiscordServerMembership($userData['id'])) {
        header('Location: login.php?error=not_member');
        exit;
    }
}

// Discord-Rollen abrufen
$guildsUrl = 'https://discord.com/api/v10/users/@me/guilds/' . DISCORD_GUILD_ID . '/member';
$ch = curl_init($guildsUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$guildResponse = curl_exec($ch);
curl_close($ch);

$guildData = json_decode($guildResponse, true);
$discordRoles = isset($guildData['roles']) ? $guildData['roles'] : [];

// Benutzer in Datenbank suchen oder erstellen
$stmt = $pdo->prepare("SELECT * FROM users WHERE discord_id = ?");
$stmt->execute([$userData['id']]);
$user = $stmt->fetch();

if ($user) {
    // Benutzer existiert - aktualisieren
    $stmt = $pdo->prepare("
        UPDATE users 
        SET discord_username = ?, 
            discord_discriminator = ?, 
            discord_avatar = ?,
            last_login = NOW()
        WHERE discord_id = ?
    ");
    $stmt->execute([
        $userData['username'],
        $userData['discriminator'] ?? '0',
        $userData['avatar'],
        $userData['id']
    ]);
    $userId = $user['id'];
} else {
    // Neuer Benutzer - erstellen
    $stmt = $pdo->prepare("
        INSERT INTO users (discord_id, discord_username, discord_discriminator, discord_avatar) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $userData['id'],
        $userData['username'],
        $userData['discriminator'] ?? '0',
        $userData['avatar']
    ]);
    $userId = $pdo->lastInsertId();
}

// Rolle basierend auf Discord-Rollen zuweisen
$stmt = $pdo->query("SELECT * FROM roles WHERE discord_role_id IS NOT NULL");
$roles = $stmt->fetchAll();

$highestRole = 1; // Standard User-Rolle
foreach ($roles as $role) {
    if (in_array($role['discord_role_id'], $discordRoles)) {
        if ($role['id'] > $highestRole) {
            $highestRole = $role['id'];
        }
    }
}

$stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ?");
$stmt->execute([$highestRole, $userId]);

// Session setzen
$_SESSION['user_id'] = $userId;
$_SESSION['discord_id'] = $userData['id'];
$_SESSION['discord_username'] = $userData['username'];

header('Location: index.php');
exit;