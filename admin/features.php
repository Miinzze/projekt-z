<?php
require_once '../config.php';
requireLogin();

// Mindestens Features-Berechtigung erforderlich
if (!hasPermission('view_features') && !hasPermission('create_features')) {
    die("Keine Berechtigung zum Features-Zugriff!");
}

$message = '';
$messageType = '';

// Feature erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_feature'])) {
    if (!hasPermission('create_features')) {
        die("Keine Berechtigung zum Erstellen von Features!");
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO features (title, description, image_url, order_num, is_active) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_POST['title'],
        $_POST['description'],
        $_POST['image_url'],
        $_POST['order_num'],
        isset($_POST['is_active']) ? 1 : 0
    ]);
    $message = 'Feature erfolgreich erstellt!';
    $messageType = 'success';
}

// Feature bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_feature'])) {
    if (!hasPermission('edit_features')) {
        die("Keine Berechtigung zum Bearbeiten von Features!");
    }
    
    $stmt = $pdo->prepare("
        UPDATE features 
        SET title = ?, description = ?, image_url = ?, order_num = ?, is_active = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['title'],
        $_POST['description'],
        $_POST['image_url'],
        $_POST['order_num'],
        isset($_POST['is_active']) ? 1 : 0,
        $_POST['feature_id']
    ]);
    $message = 'Feature erfolgreich aktualisiert!';
    $messageType = 'success';
}

// Feature löschen
if (isset($_GET['delete'])) {
    if (!hasPermission('delete_features')) {
        die("Keine Berechtigung zum Löschen von Features!");
    }
    
    $stmt = $pdo->prepare("DELETE FROM features WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $message = 'Feature erfolgreich gelöscht!';
    $messageType = 'success';
}

// Alle Features abrufen
$stmt = $pdo->query("SELECT * FROM features ORDER BY order_num ASC, id ASC");
$features = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Features verwalten - Admin</title>
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
                <a href="news.php" class="admin-nav-link">📰 News verwalten</a>
                <a href="features.php" class="admin-nav-link active">⭐ Features verwalten</a>
                <a href="rules.php" class="admin-nav-link">📋 Regelwerk verwalten</a>
                <a href="../index.php" class="admin-nav-link">🏠 Zur Hauptseite</a>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header">
                <h1>Features verwalten</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="admin-content">
                <!-- Neues Feature erstellen -->
                <?php if (hasPermission('create_features')): ?>
                <div class="card">
                    <h2>Neues Feature erstellen</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label>Titel *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Beschreibung *</label>
                            <textarea name="description" class="form-control" rows="4" required></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Bild URL (optional)</label>
                                <input type="url" name="image_url" class="form-control" placeholder="https://...">
                            </div>

                            <div class="form-group">
                                <label>Reihenfolge</label>
                                <input type="number" name="order_num" class="form-control" value="0">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" value="1" checked>
                                Aktiv
                            </label>
                        </div>

                        <button type="submit" name="create_feature" class="btn btn-primary">
                            Feature erstellen
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Vorhandene Features -->
                <div class="card">
                    <h2>Vorhandene Features</h2>
                    
                    <?php if (empty($features)): ?>
                        <p style="color: var(--text-secondary);">Keine Features vorhanden.</p>
                    <?php else: ?>
                        <div style="display: grid; gap: 1rem;">
                            <?php foreach ($features as $feature): ?>
                                <div class="answer-review">
                                    <div style="display: flex; gap: 1rem; align-items: start;">
                                        <?php if ($feature['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($feature['image_url']); ?>" 
                                                 style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                                        <?php endif; ?>
                                        <div style="flex: 1;">
                                            <h4><?php echo htmlspecialchars($feature['title']); ?></h4>
                                            <p><?php echo nl2br(htmlspecialchars($feature['description'])); ?></p>
                                            <p style="color: var(--text-secondary); font-size: 0.9rem;">
                                                Reihenfolge: <?php echo $feature['order_num']; ?> | 
                                                <?php echo $feature['is_active'] ? '<span style="color: var(--accent-green);">Aktiv</span>' : '<span style="color: var(--accent-red);">Inaktiv</span>'; ?>
                                            </p>
                                        </div>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <?php if (hasPermission('edit_features')): ?>
                                            <button onclick="editFeature(<?php echo htmlspecialchars(json_encode($feature)); ?>)" class="btn btn-secondary btn-sm">
                                                Bearbeiten
                                            </button>
                                            <?php endif; ?>
                                            <?php if (hasPermission('delete_features')): ?>
                                            <a href="?delete=<?php echo $feature['id']; ?>" 
                                               onclick="return confirm('Wirklich löschen?')" 
                                               class="btn btn-danger btn-sm">
                                                Löschen
                                            </a>
                                            <?php endif; ?>
                                            <?php if (!hasPermission('edit_features') && !hasPermission('delete_features')): ?>
                                            <span style="color: var(--text-secondary); font-size: 0.8rem;">Nur anzeigen</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
            <h3 style="color: var(--accent-green); margin-bottom: 1rem;">Feature bearbeiten</h3>
            
            <form method="POST">
                <input type="hidden" name="feature_id" id="edit_feature_id">
                
                <div class="form-group">
                    <label>Titel</label>
                    <input type="text" name="title" id="edit_title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Beschreibung</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="4" required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Bild URL</label>
                        <input type="url" name="image_url" id="edit_image_url" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Reihenfolge</label>
                        <input type="number" name="order_num" id="edit_order_num" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                        Aktiv
                    </label>
                </div>

                <button type="submit" name="edit_feature" class="btn btn-primary">
                    Speichern
                </button>
            </form>
        </div>
    </div>

    <script>
        function editFeature(feature) {
            document.getElementById('edit_feature_id').value = feature.id;
            document.getElementById('edit_title').value = feature.title;
            document.getElementById('edit_description').value = feature.description;
            document.getElementById('edit_image_url').value = feature.image_url || '';
            document.getElementById('edit_order_num').value = feature.order_num;
            document.getElementById('edit_is_active').checked = feature.is_active == 1;
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