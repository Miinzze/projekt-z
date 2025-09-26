<?php
require_once '../config.php';
requireLogin();

// Mindestens News-Berechtigung erforderlich
if (!hasPermission('view_news') && !hasPermission('create_news')) {
    die("Keine Berechtigung zum News-Zugriff!");
}

$message = '';
$messageType = '';

// Funktion zum Senden von Discord-Benachrichtigungen für News
function sendNewsDiscordNotification($newsId, $title, $content, $author, $imageUrl = '', $isNewNews = true) {
    global $pdo;
    
    // News-Webhook Einstellungen prüfen
    $webhookEnabled = getSetting('news_webhook_enabled', '1') == '1';
    $webhookUrl = getSetting('news_webhook_url', '');
    
    if (!$webhookEnabled || empty($webhookUrl)) {
        return false; // Webhook nicht konfiguriert oder deaktiviert
    }
    
    // URL zur News erstellen
    $newsUrl = BASE_URL . "/index.php#news-" . $newsId;
    
    // Verbesserte Discord-Nachricht mit Embeds senden
    return sendNewsDiscordMessage($webhookUrl, $title, $content, $newsUrl, $author, $imageUrl);
}

// News erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_news'])) {
    if (!hasPermission('create_news')) {
        die("Keine Berechtigung zum Erstellen von News!");
    }
    
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    
    $stmt = $pdo->prepare("
        INSERT INTO news (title, content, author_id, image_url, is_published) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_POST['title'],
        $_POST['content'],
        $_SESSION['user_id'],
        $_POST['image_url'],
        $isPublished
    ]);
    
    $newsId = $pdo->lastInsertId();
    $message = 'News erfolgreich erstellt!';
    $messageType = 'success';
    
    // Discord-Benachrichtigung senden wenn News direkt veröffentlicht wird
    if ($isPublished) {
        $currentUser = getCurrentUser();
        $discordSent = sendNewsDiscordNotification(
            $newsId, 
            $_POST['title'], 
            $_POST['content'], 
            $currentUser['discord_username'],
            $_POST['image_url'], // Bild-URL hinzufügen
            true
        );
        
        if ($discordSent) {
            $message .= ' 📩 Discord-Benachrichtigung wurde gesendet!';
        } elseif (getSetting('news_webhook_enabled', '1') == '1') {
            $message .= ' ⚠️ Discord-Benachrichtigung konnte nicht gesendet werden.';
        }
    }
}

// News bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_news'])) {
    if (!hasPermission('edit_news')) {
        die("Keine Berechtigung zum Bearbeiten von News!");
    }
    
    $newsId = $_POST['news_id'];
    $newIsPublished = isset($_POST['is_published']) ? 1 : 0;
    
    // Vorherigen Status abrufen
    $stmt = $pdo->prepare("SELECT is_published, title FROM news WHERE id = ?");
    $stmt->execute([$newsId]);
    $oldNews = $stmt->fetch();
    $wasPublished = $oldNews['is_published'];
    
    $stmt = $pdo->prepare("
        UPDATE news 
        SET title = ?, content = ?, image_url = ?, is_published = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['title'],
        $_POST['content'],
        $_POST['image_url'],
        $newIsPublished,
        $newsId
    ]);
    
    $message = 'News erfolgreich aktualisiert!';
    $messageType = 'success';
    
    // Discord-Benachrichtigung senden wenn News neu veröffentlicht wird (von Entwurf auf veröffentlicht)
    if (!$wasPublished && $newIsPublished) {
        $currentUser = getCurrentUser();
        $discordSent = sendNewsDiscordNotification(
            $newsId, 
            $_POST['title'], 
            $_POST['content'], 
            $currentUser['discord_username'],
            $_POST['image_url'], // Bild-URL hinzufügen
            true
        );
        
        if ($discordSent) {
            $message .= ' 📩 Discord-Benachrichtigung wurde gesendet!';
        } elseif (getSetting('news_webhook_enabled', '1') == '1') {
            $message .= ' ⚠️ Discord-Benachrichtigung konnte nicht gesendet werden.';
        }
    }
}

// News löschen
if (isset($_GET['delete'])) {
    if (!hasPermission('delete_news')) {
        die("Keine Berechtigung zum Löschen von News!");
    }
    
    $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $message = 'News erfolgreich gelöscht!';
    $messageType = 'success';
}

// Alle News abrufen
$stmt = $pdo->query("
    SELECT n.*, u.discord_username 
    FROM news n
    JOIN users u ON n.author_id = u.id
    ORDER BY n.created_at DESC
");
$newsList = $stmt->fetchAll();

// News-Webhook Status für Info-Box
$newsWebhookEnabled = getSetting('news_webhook_enabled', '1') == '1';
$newsWebhookUrl = getSetting('news_webhook_url', '');
$newsWebhookConfigured = !empty($newsWebhookUrl);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News verwalten - Admin</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin-style.css">
</head>
<body>
    <div class="admin-container">
        <aside class="admin-sidebar">
            <div class="admin-logo">
                <h2>ADMIN PANEL</h2>
            </div>
            <nav class="admin-nav">
                <a href="dashboard.php" class="admin-nav-link">📊 Dashboard</a>
                <a href="news.php" class="admin-nav-link active">📰 News verwalten</a>
                <a href="features.php" class="admin-nav-link">⭐ Features verwalten</a>
                <a href="rules.php" class="admin-nav-link">📋 Regelwerk verwalten</a>
                <a href="settings.php" class="admin-nav-link">⚙️ Einstellungen</a>
                <a href="../index.php" class="admin-nav-link">🏠 Zur Hauptseite</a>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header">
                <h1>News verwalten</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Discord Integration Status -->
            <?php if (!$newsWebhookEnabled || !$newsWebhookConfigured): ?>
                <div class="alert" style="background: rgba(217, 119, 6, 0.2); border: 1px solid var(--accent-orange);">
                    <h4 style="color: var(--accent-orange); margin: 0 0 0.5rem 0;">⚠️ Discord News-Integration</h4>
                    <p style="color: var(--text-secondary); margin: 0;">
                        <?php if (!$newsWebhookEnabled): ?>
                            News Discord-Benachrichtigungen sind deaktiviert.
                        <?php elseif (!$newsWebhookConfigured): ?>
                            News-Webhook URL ist nicht konfiguriert.
                        <?php endif; ?>
                        <a href="settings.php" style="color: var(--accent-orange);">→ Zu den Einstellungen</a>
                    </p>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <h4 style="color: var(--accent-tan); margin: 0 0 0.5rem 0;">✅ Discord Integration aktiv</h4>
                    <p style="color: var(--text-secondary); margin: 0;">
                        📩 News werden automatisch an Discord gesendet wenn sie veröffentlicht werden.
                    </p>
                </div>
            <?php endif; ?>

            <div class="admin-content">
                <!-- Neue News erstellen -->
                <?php if (hasPermission('create_news')): ?>
                <div class="card">
                    <h2>Neue News erstellen</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label>Titel *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Inhalt *</label>
                            <textarea name="content" class="form-control" rows="6" required></textarea>
                            <small style="color: var(--text-secondary);">
                                💡 Tipp: Bei Discord-Benachrichtigungen werden nur die ersten 300 Zeichen angezeigt
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Bild URL (optional)</label>
                            <input type="url" name="image_url" class="form-control" placeholder="https://...">
                        </div>

                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="is_published" value="1" checked>
                                <span>Veröffentlicht</span>
                                <?php if ($newsWebhookEnabled && $newsWebhookConfigured): ?>
                                    <span style="color: var(--accent-tan); font-size: 0.8rem;">📩 (sendet Discord-Benachrichtigung)</span>
                                <?php endif; ?>
                            </label>
                            <small style="color: var(--text-secondary); margin-left: 1.5rem;">
                                Nur veröffentlichte News erscheinen auf der Homepage
                            </small>
                        </div>

                        <button type="submit" name="create_news" class="btn btn-primary">
                            📰 News erstellen
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Vorhandene News -->
                <div class="card">
                    <h2>Vorhandene News</h2>
                    
                    <?php if (empty($newsList)): ?>
                        <p style="color: var(--text-secondary);">Keine News vorhanden.</p>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Titel</th>
                                    <th>Autor</th>
                                    <th>Status</th>
                                    <th>Erstellt am</th>
                                    <th>Aktion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($newsList as $news): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($news['title']); ?>
                                            <?php if (strlen($news['content']) > 100): ?>
                                                <br><small style="color: var(--text-secondary);">
                                                    <?php echo htmlspecialchars(substr($news['content'], 0, 100)) . '...'; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($news['discord_username']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $news['is_published'] ? 'approved' : 'pending'; ?>">
                                                <?php if ($news['is_published']): ?>
                                                    📰 Veröffentlicht
                                                <?php else: ?>
                                                    📝 Entwurf
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($news['created_at'])); ?></td>
                                        <td>
                                            <?php if (hasPermission('edit_news')): ?>
                                            <button onclick="editNews(<?php echo htmlspecialchars(json_encode($news)); ?>)" class="btn btn-secondary btn-sm">
                                                Bearbeiten
                                            </button>
                                            <?php endif; ?>
                                            <?php if (hasPermission('delete_news')): ?>
                                            <a href="?delete=<?php echo $news['id']; ?>" 
                                               onclick="return confirm('Wirklich löschen?')" 
                                               class="btn btn-danger btn-sm">
                                                Löschen
                                            </a>
                                            <?php endif; ?>
                                            <?php if (!hasPermission('edit_news') && !hasPermission('delete_news')): ?>
                                            <span style="color: var(--text-secondary);">Nur anzeigen</span>
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

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
            <h3 style="color: var(--accent-tan); margin-bottom: 1rem;">News bearbeiten</h3>
            
            <form method="POST">
                <input type="hidden" name="news_id" id="edit_news_id">
                
                <div class="form-group">
                    <label>Titel</label>
                    <input type="text" name="title" id="edit_title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Inhalt</label>
                    <textarea name="content" id="edit_content" class="form-control" rows="6" required></textarea>
                </div>

                <div class="form-group">
                    <label>Bild URL</label>
                    <input type="url" name="image_url" id="edit_image_url" class="form-control">
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="is_published" id="edit_is_published" value="1">
                        <span>Veröffentlicht</span>
                        <?php if ($newsWebhookEnabled && $newsWebhookConfigured): ?>
                            <span style="color: var(--accent-tan); font-size: 0.8rem;" id="publish-discord-hint">📩 (sendet Discord-Benachrichtigung bei erstmaliger Veröffentlichung)</span>
                        <?php endif; ?>
                    </label>
                </div>

                <button type="submit" name="edit_news" class="btn btn-primary">
                    💾 Speichern
                </button>
            </form>
        </div>
    </div>

    <script>
        function editNews(news) {
            document.getElementById('edit_news_id').value = news.id;
            document.getElementById('edit_title').value = news.title;
            document.getElementById('edit_content').value = news.content;
            document.getElementById('edit_image_url').value = news.image_url || '';
            document.getElementById('edit_is_published').checked = news.is_published == 1;
            
            // Discord-Hinweis nur anzeigen wenn News noch nicht veröffentlicht wurde
            const discordHint = document.getElementById('publish-discord-hint');
            if (discordHint) {
                discordHint.style.display = news.is_published == 1 ? 'none' : 'inline';
            }
            
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>