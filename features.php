<?php
require_once 'config.php';

// Aktive Features abrufen
$stmt = $pdo->query("SELECT * FROM features WHERE is_active = 1 ORDER BY order_num ASC");
$features = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Features - <?php echo htmlspecialchars(getSetting('server_name')); ?></title>
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
                    <a href="features.php" class="nav-link active">FEATURES</a>
                    <?php if (isLoggedIn()): ?>
                        <a href="whitelist.php" class="nav-link">WHITELIST</a>
                        <?php if (hasPermission('manage_news')): ?>
                            <a href="admin/dashboard.php" class="nav-link">ADMIN</a>
                        <?php endif; ?>
                        <a href="logout.php" class="nav-link">LOGOUT</a>
                    <?php else: ?>
                        <a href="login.php" class="nav-link">LOGIN</a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>

        <div style="max-width: 1400px; margin: 2rem auto; padding: 0 2rem;">
            <h1 class="section-title">SERVER FEATURES</h1>

            <?php if (empty($features)): ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <p style="color: var(--text-secondary); font-size: 1.1rem;">
                        Features werden in Kürze hier vorgestellt.
                    </p>
                </div>
            <?php else: ?>
                <div class="features-grid">
                    <?php foreach ($features as $feature): ?>
                        <div class="feature-card">
                            <?php if ($feature['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($feature['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($feature['title']); ?>" 
                                     class="feature-image">
                            <?php else: ?>
                                <div class="feature-image" style="display: flex; align-items: center; justify-content: center; font-size: 3rem;">
                                    ⭐
                                </div>
                            <?php endif; ?>
                            <h3 class="feature-title"><?php echo htmlspecialchars($feature['title']); ?></h3>
                            <p class="feature-description"><?php echo nl2br(htmlspecialchars($feature['description'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!isLoggedIn()): ?>
                    <div style="text-align: center; margin-top: 3rem;">
                        <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                            Bereit, Teil unserer Community zu werden?
                        </p>
                        <a href="login.php" class="cta-button">
                            JETZT BEWERBEN
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <footer class="footer">
            <p>&copy; 2024 <?php echo htmlspecialchars(getSetting('server_name')); ?>. Alle Rechte vorbehalten.</p>
        </footer>
    </div>
</body>
</html>