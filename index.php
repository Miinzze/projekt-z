<?php
require_once 'config.php';

// News abrufen
$stmt = $pdo->query("SELECT * FROM news WHERE is_published = 1 ORDER BY created_at DESC LIMIT 5");
$news = $stmt->fetchAll();

// Features abrufen
$stmt = $pdo->query("SELECT * FROM features WHERE is_active = 1 ORDER BY order_num ASC");
$features = $stmt->fetchAll();

// Twitch-Integration Status prüfen
$twitchEnabled = getSetting('twitch_enabled', '1') == '1';

// Hintergrundbild-Einstellungen abrufen
$heroBackgroundEnabled = getSetting('hero_background_enabled', '1') == '1';
$heroBackgroundImage = getSetting('hero_background_image', '');
$heroBackgroundOverlay = getSetting('hero_background_overlay', '0.7');
$heroBackgroundPosition = getSetting('hero_background_position', 'center center');
$heroBackgroundSize = getSetting('hero_background_size', 'cover');
$heroBackgroundAttachment = getSetting('hero_background_attachment', 'fixed');
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
        <section class="hero" style="
            <?php if ($heroBackgroundEnabled && !empty($heroBackgroundImage)): ?>
                background-image: 
                    linear-gradient(rgba(0, 0, 0, <?php echo $heroBackgroundOverlay; ?>), rgba(0, 0, 0, <?php echo $heroBackgroundOverlay; ?>)),
                    url('<?php echo htmlspecialchars($heroBackgroundImage); ?>');
                background-size: <?php echo $heroBackgroundSize; ?>;
                background-position: <?php echo $heroBackgroundPosition; ?>;
                background-attachment: <?php echo $heroBackgroundAttachment; ?>;
                background-repeat: no-repeat;
            <?php else: ?>
                background: linear-gradient(180deg, #1c1c1c 0%, #0d0d0d 100%);
            <?php endif; ?>
        ">
            <div class="hero-overlay">
                <h2 class="hero-title">ÜBERLEBE DIE APOKALYPSE</h2>
                <p class="hero-subtitle">Trete der ultimativen Zombie-Survival-Community bei</p>
                <!-- Obere Reihe: Spieler Online + Discord -->
                <div class="hero-stats">
                    <!-- Spieler Online -->
                    <div class="stat-item">
                        <span class="stat-icon">👥</span>
                        <span class="stat-value" id="player-count">
                            <span class="loading-dots">...</span>
                        </span>
                        <span class="stat-label">Spieler Online</span>
                    </div>
                    
                    <!-- Discord -->
                    <div class="stat-item">
                        <a href="<?php echo htmlspecialchars(getSetting('discord_server_invite')); ?>" target="_blank" class="discord-btn">
                            <span class="discord-icon">💬</span> DISCORD BEITRETEN
                        </a>
                    </div>
                </div>

                <!-- Untere Reihe: Twitch-Streams (großer Kasten) -->
                <?php if ($twitchEnabled): ?>
                <div class="hero-twitch-section">
                    <div class="twitch-main-card">
                        <div class="twitch-carousel">
                            <button class="carousel-nav carousel-prev" id="twitch-prev" onclick="previousStreamer()">
                                <span>◀</span>
                            </button>
                            
                            <div class="twitch-content" id="twitch-content">
                                <div class="twitch-loading">
                                    <span class="stat-icon">📺</span>
                                    <span class="stat-value">
                                        <span class="loading-dots">...</span>
                                    </span>
                                    <span class="stat-label">Live Streams</span>
                                </div>
                            </div>
                            
                            <button class="carousel-nav carousel-next" id="twitch-next" onclick="nextStreamer()">
                                <span>▶</span>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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
        // Globale Variablen für Twitch
        let currentStreamers = [];
        let currentStreamerIndex = 0;
        let twitchUpdateInterval;

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

        // Twitch Streams laden
        async function updateTwitchStreams() {
            <?php if (!$twitchEnabled): ?>
            return; // Twitch deaktiviert
            <?php endif; ?>
            
            try {
                const response = await fetch('api/twitch_streams.php');
                const data = await response.json();
                
                const contentElement = document.getElementById('twitch-content');
                const prevButton = document.getElementById('twitch-prev');
                const nextButton = document.getElementById('twitch-next');
                
                if (data.success && data.live_streamers.length > 0) {
                    currentStreamers = data.live_streamers;
                    currentStreamerIndex = 0;
                    showCurrentStreamer();
                    
                    // Navigation nur anzeigen wenn mehrere Streamer
                    const showNav = currentStreamers.length > 1;
                    prevButton.style.display = showNav ? 'block' : 'none';
                    nextButton.style.display = showNav ? 'block' : 'none';
                    
                } else {
                    // Keine Live-Streamer
                    currentStreamers = [];
                    showNoStreamersOnline();
                    prevButton.style.display = 'none';
                    nextButton.style.display = 'none';
                }
            } catch (error) {
                console.error('Twitch API Fehler:', error);
                showTwitchError();
            }
        }

        function showCurrentStreamer() {
            if (currentStreamers.length === 0) return;
            
            const streamer = currentStreamers[currentStreamerIndex];
            const contentElement = document.getElementById('twitch-content');
            
            // Viewer-Anzahl formatieren
            const viewerCount = streamer.viewer_count;
            let viewerText = viewerCount.toString();
            if (viewerCount >= 1000) {
                viewerText = (viewerCount / 1000).toFixed(1) + 'k';
            }
            
            contentElement.innerHTML = `
                <a href="${streamer.twitch_url}" target="_blank" class="twitch-streamer-link">
                    <div class="twitch-streamer">
                        <div class="live-indicator">🔴 LIVE</div>
                        <span class="stat-icon">📺</span>
                        <div class="streamer-info">
                            <span class="streamer-name">${escapeHtml(streamer.display_name)}</span>
                            <span class="viewer-count">${viewerText} Zuschauer</span>
                            <span class="game-name">${escapeHtml(streamer.game_name)}</span>
                        </div>
                        ${currentStreamers.length > 1 ? `<div class="stream-counter">${currentStreamerIndex + 1}/${currentStreamers.length}</div>` : ''}
                    </div>
                </a>
            `;
        }

        function showNoStreamersOnline() {
            const contentElement = document.getElementById('twitch-content');
            contentElement.innerHTML = `
                <div class="twitch-offline">
                    <span class="stat-icon">📺</span>
                    <span class="stat-value">Offline</span>
                    <span class="stat-label">Aktuell keiner Online</span>
                </div>
            `;
        }

        function showTwitchError() {
            const contentElement = document.getElementById('twitch-content');
            contentElement.innerHTML = `
                <div class="twitch-error">
                    <span class="stat-icon">📺</span>
                    <span class="stat-value">Fehler</span>
                    <span class="stat-label">Twitch nicht verfügbar</span>
                </div>
            `;
        }

        function nextStreamer() {
            if (currentStreamers.length <= 1) return;
            currentStreamerIndex = (currentStreamerIndex + 1) % currentStreamers.length;
            showCurrentStreamer();
        }

        function previousStreamer() {
            if (currentStreamers.length <= 1) return;
            currentStreamerIndex = (currentStreamerIndex - 1 + currentStreamers.length) % currentStreamers.length;
            showCurrentStreamer();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Initial laden
        updatePlayerCount();
        <?php if ($twitchEnabled): ?>
        updateTwitchStreams();
        <?php endif; ?>
        
        // Regelmäßig aktualisieren
        setInterval(updatePlayerCount, 30000); // Spielerzahl alle 30s
        <?php if ($twitchEnabled): ?>
        twitchUpdateInterval = setInterval(updateTwitchStreams, 60000); // Twitch alle 60s
        <?php endif; ?>
    </script>
</body>
</html>