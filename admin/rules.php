<?php
require_once '../config.php';
requireLogin();
requirePermission('view_rules');

$message = '';
$messageType = '';

// Regel erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_rule'])) {
    $stmt = $pdo->prepare("
        INSERT INTO rules (category, rule_number, title, content, order_num, is_active) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_POST['category'],
        $_POST['rule_number'],
        $_POST['title'],
        $_POST['content'],
        $_POST['order_num'],
        isset($_POST['is_active']) ? 1 : 0
    ]);
    $message = 'Regel erfolgreich erstellt!';
    $messageType = 'success';
}

// Regel bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_rule'])) {
    $stmt = $pdo->prepare("
        UPDATE rules 
        SET category = ?, rule_number = ?, title = ?, content = ?, order_num = ?, is_active = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['category'],
        $_POST['rule_number'],
        $_POST['title'],
        $_POST['content'],
        $_POST['order_num'],
        isset($_POST['is_active']) ? 1 : 0,
        $_POST['rule_id']
    ]);
    $message = 'Regel erfolgreich aktualisiert!';
    $messageType = 'success';
}

// Regel löschen
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM rules WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $message = 'Regel erfolgreich gelöscht!';
    $messageType = 'success';
}

// Alle Regeln abrufen
$stmt = $pdo->query("SELECT * FROM rules ORDER BY category ASC, order_num ASC, id ASC");
$rules = $stmt->fetchAll();

// Kategorien ermitteln
$categories = array_unique(array_column($rules, 'category'));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regelwerk verwalten - Admin</title>
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
                <a href="features.php" class="admin-nav-link">⭐ Features verwalten</a>
                <a href="rules.php" class="admin-nav-link active">📋 Regelwerk verwalten</a>
                <a href="../index.php" class="admin-nav-link">🏠 Zur Hauptseite</a>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header">
                <h1>Regelwerk verwalten</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="admin-content">
                <!-- Neue Regel erstellen -->
                <div class="card">
                    <h2>Neue Regel erstellen</h2>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Kategorie *</label>
                                <input type="text" name="category" class="form-control" required 
                                       list="categories" placeholder="z.B. Allgemeine Regeln">
                                <datalist id="categories">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div class="form-group">
                                <label>Regel-Nummer</label>
                                <input type="text" name="rule_number" class="form-control" placeholder="z.B. §1">
                            </div>

                            <div class="form-group">
                                <label>Reihenfolge</label>
                                <input type="number" name="order_num" class="form-control" value="0">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Titel *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Inhalt *</label>
                            <textarea name="content" class="form-control" rows="6" required></textarea>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" value="1" checked>
                                Aktiv
                            </label>
                        </div>

                        <button type="submit" name="create_rule" class="btn btn-primary">
                            Regel erstellen
                        </button>
                    </form>
                </div>

                <!-- Vorhandene Regeln -->
                <div class="card">
                    <h2>Vorhandene Regeln</h2>
                    
                    <?php if (empty($rules)): ?>
                        <p style="color: var(--text-secondary);">Keine Regeln vorhanden.</p>
                    <?php else: ?>
                        <?php
                        $currentCategory = '';
                        foreach ($rules as $rule):
                            if ($currentCategory !== $rule['category']):
                                if ($currentCategory !== '') echo '</div>';
                                $currentCategory = $rule['category'];
                                echo '<h3 style="color: var(--accent-green); margin-top: 2rem; margin-bottom: 1rem;">' . htmlspecialchars($currentCategory) . '</h3>';
                                echo '<div style="display: grid; gap: 1rem;">';
                            endif;
                        ?>
                            <div class="answer-review">
                                <div style="display: flex; justify-content: between; align-items: start; gap: 1rem;">
                                    <div style="flex: 1;">
                                        <h4>
                                            <?php if ($rule['rule_number']): ?>
                                                <span style="color: var(--accent-green);"><?php echo htmlspecialchars($rule['rule_number']); ?></span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($rule['title']); ?>
                                        </h4>
                                        <p><?php echo nl2br(htmlspecialchars($rule['content'])); ?></p>
                                        <p style="color: var(--text-secondary); font-size: 0.9rem;">
                                            Reihenfolge: <?php echo $rule['order_num']; ?> | 
                                            <?php echo $rule['is_active'] ? '<span style="color: var(--accent-green);">Aktiv</span>' : '<span style="color: var(--accent-red);">Inaktiv</span>'; ?>
                                        </p>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem; flex-shrink: 0;">
                                        <button onclick="editRule(<?php echo htmlspecialchars(json_encode($rule)); ?>)" class="btn btn-secondary btn-sm">
                                            Bearbeiten
                                        </button>
                                        <a href="?delete=<?php echo $rule['id']; ?>" 
                                           onclick="return confirm('Wirklich löschen?')" 
                                           class="btn btn-danger btn-sm">
                                            Löschen
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($currentCategory !== '') echo '</div>'; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
            <h3 style="color: var(--accent-green); margin-bottom: 1rem;">Regel bearbeiten</h3>
            
            <form method="POST">
                <input type="hidden" name="rule_id" id="edit_rule_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Kategorie</label>
                        <input type="text" name="category" id="edit_category" class="form-control" required list="categories">
                    </div>

                    <div class="form-group">
                        <label>Regel-Nummer</label>
                        <input type="text" name="rule_number" id="edit_rule_number" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Reihenfolge</label>
                        <input type="number" name="order_num" id="edit_order_num" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label>Titel</label>
                    <input type="text" name="title" id="edit_title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Inhalt</label>
                    <textarea name="content" id="edit_content" class="form-control" rows="6" required></textarea>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                        Aktiv
                    </label>
                </div>

                <button type="submit" name="edit_rule" class="btn btn-primary">
                    Speichern
                </button>
            </form>
        </div>
    </div>

    <script>
        function editRule(rule) {
            document.getElementById('edit_rule_id').value = rule.id;
            document.getElementById('edit_category').value = rule.category;
            document.getElementById('edit_rule_number').value = rule.rule_number || '';
            document.getElementById('edit_title').value = rule.title;
            document.getElementById('edit_content').value = rule.content;
            document.getElementById('edit_order_num').value = rule.order_num;
            document.getElementById('edit_is_active').checked = rule.is_active == 1;
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