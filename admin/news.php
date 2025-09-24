<?php
require_once '../config.php';
requireLogin();

// Mindestens News-Berechtigung erforderlich
if (!hasPermission('view_news') && !hasPermission('create_news')) {
    die("Keine Berechtigung zum News-Zugriff!");
}

$message = '';
$messageType = '';

// News erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_news'])) {
    if (!hasPermission('create_news')) {
        die("Keine Berechtigung zum Erstellen von News!");
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO news (title, content, author_id, image_url, is_published) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_POST['title'],
        $_POST['content'],
        $_SESSION['user_id'],
        $_POST['image_url'],
        isset($_POST['is_published']) ? 1 : 0
    ]);
    $message = 'News erfolgreich erstellt!';
    $messageType = 'success';
}

// News bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_news'])) {
    if (!hasPermission('edit_news')) {
        die("Keine Berechtigung zum Bearbeiten von News!");
    }
    
    $stmt = $pdo->prepare("
        UPDATE news 
        SET title = ?, content = ?, image_url = ?, is_published = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['title'],
        $_POST['content'],
        $_POST['image_url'],
        isset($_POST['is_published']) ? 1 : 0,
        $_POST['news_id']
    ]);
    $message = 'News erfolgreich aktualisiert!';
    $messageType = 'success';
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
                        </div>

                        <div class="form-group">
                            <label>Bild URL (optional)</label>
                            <input type="url" name="image_url" class="form-control" placeholder="https://...">
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_published" value="1" checked>
                                Veröffentlicht
                            </label>
                        </div>

                        <button type="submit" name="create_news" class="btn btn-primary">
                            News erstellen
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
                                        <td><?php echo htmlspecialchars($news['title']); ?></td>
                                        <td><?php echo htmlspecialchars($news['discord_username']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $news['is_published'] ? 'approved' : 'rejected'; ?>">
                                                <?php echo $news['is_published'] ? 'Veröffentlicht' : 'Entwurf'; ?>
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
            <h3 style="color: var(--accent-green); margin-bottom: 1rem;">News bearbeiten</h3>
            
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
                    <label>
                        <input type="checkbox" name="is_published" id="edit_is_published" value="1">
                        Veröffentlicht
                    </label>
                </div>

                <button type="submit" name="edit_news" class="btn btn-primary">
                    Speichern
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