<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Discord OAuth2 URL
$discordAuthUrl = 'https://discord.com/api/oauth2/authorize?' . http_build_query([
    'client_id' => DISCORD_CLIENT_ID,
    'redirect_uri' => DISCORD_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'identify guilds guilds.members.read'
]);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars(getSetting('server_name')); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-content">
                <div class="logo">
                    <img src="images/skull-icon.png" alt="Logo" class="logo-img">
                    <h1><?php echo htmlspecialchars(getSetting('server_name')); ?></h1>
                </div>
                <nav class="nav">
                    <a href="index.php" class="nav-link">HOME</a>
                    <a href="rules.php" class="nav-link">REGELWERK</a>
                    <a href="features.php" class="nav-link">FEATURES</a>
                </nav>
            </div>
        </header>

        <div class="login-container">
            <div class="form-container">
                <h2 class="section-title">LOGIN</h2>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger">
                        <?php
                        $error = $_GET['error'];
                        if ($error === 'not_member') {
                            echo 'Du musst auf unserem Discord-Server sein, um dich anzumelden!';
                        } else {
                            echo 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.';
                        }
                        ?>
                    </div>
                <?php endif; ?>

                <div style="text-align: center;">
                    <p style="margin-bottom: 2rem; color: var(--text-secondary);">
                        Melde dich mit deinem Discord-Account an, um Zugang zur Whitelist zu erhalten.
                    </p>
                    
                    <a href="<?php echo htmlspecialchars($discordAuthUrl); ?>" class="discord-btn" style="display: inline-flex; font-size: 1.1rem; padding: 1rem 2.5rem;">
                        <span class="discord-icon">💬</span> MIT DISCORD ANMELDEN
                    </a>

                    <div style="margin-top: 2rem; padding: 1rem; background: rgba(74, 140, 74, 0.1); border: 1px solid var(--accent-green); border-radius: 4px;">
                        <p style="color: var(--accent-green); font-weight: 600;">
                            ⚠️ WICHTIG
                        </p>
                        <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                            Du musst Mitglied unseres Discord-Servers sein, um dich anzumelden.
                        </p>
                        <a href="<?php echo htmlspecialchars(getSetting('discord_server_invite')); ?>" target="_blank" style="color: var(--accent-green); text-decoration: underline; display: inline-block; margin-top: 0.5rem;">
                            Discord-Server beitreten
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <footer class="footer">
            <p>&copy; 2024 <?php echo htmlspecialchars(getSetting('server_name')); ?>. Alle Rechte vorbehalten.</p>
        </footer>
    </div>
</body>
</html>

<style>
.login-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 200px);
    padding: 2rem;
}
</style>