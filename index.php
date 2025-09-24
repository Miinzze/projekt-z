<?php
require_once 'config.php';

// News abrufen
$stmt = $pdo->query("SELECT * FROM news WHERE is_published = 1 ORDER BY created_at DESC LIMIT 5");
$news = $stmt->fetchAll();

// Features abrufen
$stmt = $pdo->query("SELECT * FROM features WHERE is_active = 1 ORDER BY order_num ASC");
$features = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getSetting('server_name', 'Zombie Survival RP')); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="logo">
                    <img src="images/skull-icon.png" alt="Logo" class="logo-img">
                    <h1><?php echo htmlspecialchars(getSetting('server_name', 'ZOMBIE SURVIVAL RP')); ?></h1>
                </div>
                <nav class="nav">
                    <a href="index.php" class="nav-link active">HOME</a>
                    <a href="rules.php" class="nav-link">REGELWERK</a>
                    <a href="features.php" class="nav-link">FEATURES</a>
                    <?php if (isLoggedIn()): ?>
                        <a href="whitelist.php" class="nav-link">WHITELIST</a>
                        <?php if (hasPermission('view_dashboard')): ?>
                            <a href="admin/dashboard.php" class="nav-link">ADMIN</a>
                        <?php endif; ?>
                        <a href="logout.php" class="nav-link">LOGOUT</a>
                    <?php else: ?>
                        <a href="login.php" class="nav-link">LOGIN</a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-overlay">
                <h2 class="hero-title">ÜBERLEBE DIE APOKALYPSE</h2>
                <p class="hero-subtitle">Trete der ultimativen Zombie-Survival-Community bei</p>
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-icon">👥</span>
                        <span class="stat-value" id="player-count">
                            <span class="loading-dots">...</span>
                        </span>
                        <span class="stat-label">Spieler Online</span>
                    </div>
                    <div class="stat-item">
                        <a href="<?php echo htmlspecialchars(getSetting('discord_server_invite')); ?>" target="_blank" class="discord-btn">
                            <span class="discord-icon">💬</span> DISCORD BEITRETEN
                        </a>
                    </div>
                </div>
                <?php if (!isLoggedIn()): ?>
                    <a href="login.php" class="cta-button">JETZT BEWERBEN</a>
                <?php endif; ?>
            </div>
        </section>

        <!-- News Section -->
        <section class="news-section">
            <h2 class="section-title">NEUIGKEITEN</h2>
            <div class="news-grid">
                <?php foreach ($news as $item): ?>
                    <div class="news-card">
                        <?php if ($item['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="News" class="news-image">
                        <?php endif; ?>
                        <div class="news-content">
                            <h3 class="news-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                            <p class="news-excerpt"><?php echo nl2br(htmlspecialchars(substr($item['content'], 0, 150))); ?>...</p>
                            <span class="news-date"><?php echo date('d.m.Y', strtotime($item['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features-section">
            <h2 class="section-title">FEATURES</h2>
            <div class="features-grid">
                <?php foreach ($features as $feature): ?>
                    <div class="feature-card">
                        <?php if ($feature['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($feature['image_url']); ?>" alt="Feature" class="feature-image">
                        <?php endif; ?>
                        <h3 class="feature-title"><?php echo htmlspecialchars($feature['title']); ?></h3>
                        <p class="feature-description"><?php echo nl2br(htmlspecialchars($feature['description'])); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer">
            <p>&copy; 2024 <?php echo htmlspecialchars(getSetting('server_name')); ?>. Alle Rechte vorbehalten.</p>
        </footer>
    </div>

    <script>
        // Spielerzahl laden
        async function updatePlayerCount() {
            try {
                const response = await fetch('api/player_count.php');
                const data = await response.json();
                
                const countElement = document.getElementById('player-count');
                
                if (data.success) {
                    countElement.innerHTML = `${data.online}/${data.max}`;
                    countElement.style.color = 'var(--accent-tan)';
                } else {
                    countElement.innerHTML = 'Offline';
                    countElement.style.color = 'var(--text-secondary)';
                }
            } catch (error) {
                const countElement = document.getElementById('player-count');
                countElement.innerHTML = 'N/A';
                countElement.style.color = 'var(--text-secondary)';
            }
        }

        // Initial laden
        updatePlayerCount();
        
        // Alle 30 Sekunden aktualisieren
        setInterval(updatePlayerCount, 30000);
    </script>

    <style>
        .loading-dots {
            animation: blink 1.5s infinite;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
    </style>
</body>
</html>