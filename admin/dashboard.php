<?php
require_once '../config.php';
requireLogin();

$user = getCurrentUser();

// Mindestens eine Admin-Berechtigung erforderlich
if (!hasPermission('view_dashboard')) {
    die("Keine Berechtigung zum Dashboard-Zugriff!");
}

// Statistiken
$stats = [];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM whitelist_applications WHERE status = 'pending'");
$stats['pending_applications'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM news WHERE is_published = 1");
$stats['published_news'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM features WHERE is_active = 1");
$stats['active_features'] = $stmt->fetch()['total'];

// Whitelist Status
$stats['whitelist_enabled'] = getSetting('whitelist_enabled', '1') == '1';
$stats['cooldown_hours'] = getSetting('whitelist_cooldown_hours', '24');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars(getSetting('server_name')); ?></title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin-style.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="admin-logo">
                <h2>ADMIN PANEL</h2>
                <p><?php echo htmlspecialchars($user['discord_username']); ?></p>
            </div>
            
            <nav class="admin-nav">
                <a href="dashboard.php" class="admin-nav-link active">
                    📊 Dashboard
                </a>
                
                <?php if (hasPermission('view_applications')): ?>
                <a href="applications.php" class="admin-nav-link">
                    📝 Whitelist Anträge
                    <?php if ($stats['pending_applications'] > 0): ?>
                        <span class="badge"><?php echo $stats['pending_applications']; ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                
                <?php if (hasPermission('manage_questions')): ?>
                <a href="questions.php" class="admin-nav-link">
                    ❓ Fragen verwalten
                </a>
                <?php endif; ?>
                
                <?php if (hasPermission('view_news') || hasPermission('create_news')): ?>
                <a href="news.php" class="admin-nav-link">
                    📰 News verwalten
                </a>
                <?php endif; ?>
                
                <?php if (hasPermission('view_features') || hasPermission('create_features')): ?>
                <a href="features.php" class="admin-nav-link">
                    ⭐ Features verwalten
                </a>
                <?php endif; ?>
                
                <?php if (hasPermission('view_rules') || hasPermission('create_rules')): ?>
                <a href="rules.php" class="admin-nav-link">
                    📋 Regelwerk verwalten
                </a>
                <?php endif; ?>
                
                <?php if (hasPermission('manage_roles')): ?>
                <a href="roles.php" class="admin-nav-link">
                    👥 Rollen & Rechte
                </a>
                <?php endif; ?>
                
                <?php if (hasPermission('manage_settings')): ?>
                <a href="settings.php" class="admin-nav-link">
                    ⚙️ Einstellungen
                </a>
                <?php endif; ?>
                
                <a href="../index.php" class="admin-nav-link" style="margin-top: auto;">
                    🏠 Zur Hauptseite
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-header">
                <h1>Dashboard</h1>
            </div>

            <!-- Feedback Nachrichten -->
            <?php if (isset($_GET['whitelist'])): ?>
                <?php if ($_GET['whitelist'] === 'opened'): ?>
                    <div class="alert alert-success">
                        🔓 <strong>Whitelist wurde geöffnet!</strong> Bewerber können sich jetzt anmelden.
                    </div>
                <?php elseif ($_GET['whitelist'] === 'closed'): ?>
                    <div class="alert" style="background: rgba(140, 74, 74, 0.2); border: 1px solid var(--accent-red); color: var(--accent-red);">
                        🔒 <strong>Whitelist wurde geschlossen!</strong> Keine neuen Bewerbungen möglich.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="admin-content">
                <!-- Statistiken -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_users']; ?></h3>
                            <p>Registrierte Benutzer</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">📝</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['pending_applications']; ?></h3>
                            <p>Offene Anträge</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">📰</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['published_news']; ?></h3>
                            <p>Veröffentlichte News</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">⭐</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['active_features']; ?></h3>
                            <p>Aktive Features</p>
                        </div>
                    </div>
                </div>

                <!-- Letzte Aktivitäten -->
                <div class="card">
                    <h2>Letzte Whitelist Anträge</h2>
                    <?php
                    $stmt = $pdo->query("
                        SELECT wa.*, u.discord_username, u.discord_avatar
                        FROM whitelist_applications wa
                        JOIN users u ON wa.user_id = u.id
                        ORDER BY wa.submitted_at DESC
                        LIMIT 5
                    ");
                    $recentApplications = $stmt->fetchAll();
                    ?>

                    <?php if (empty($recentApplications)): ?>
                        <p style="color: var(--text-secondary);">Keine Anträge vorhanden.</p>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Benutzer</th>
                                    <th>Status</th>
                                    <th>Eingereicht am</th>
                                    <th>Aktion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentApplications as $app): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <?php if ($app['discord_avatar']): ?>
                                                    <img src="https://cdn.discordapp.com/avatars/<?php echo $app['user_id']; ?>/<?php echo $app['discord_avatar']; ?>.png" 
                                                         style="width: 32px; height: 32px; border-radius: 50%;">
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($app['discord_username']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $app['status']; ?>">
                                                <?php 
                                                echo [
                                                    'pending' => 'Ausstehend',
                                                    'approved' => 'Angenommen',
                                                    'rejected' => 'Abgelehnt'
                                                ][$app['status']]; 
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($app['submitted_at'])); ?></td>
                                        <td>
                                            <?php if (hasPermission('view_applications')): ?>
                                                <a href="view_application.php?id=<?php echo $app['id']; ?>" class="btn btn-secondary btn-sm">
                                                    Ansehen
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>