<?php
require_once '../config.php';
requireLogin();
requirePermission('manage_roles');

$message = '';
$messageType = '';

// Verfügbare Berechtigungen (ERWEITERT UM STREAMER-VERWALTUNG)
$availablePermissions = [
    // Dashboard & Basis
    'view_dashboard' => 'Dashboard ansehen',
    'apply_whitelist' => 'Whitelist-Antrag stellen',
    
    // News-System
    'view_news' => 'News ansehen',
    'create_news' => 'News erstellen',
    'edit_news' => 'News bearbeiten', 
    'delete_news' => 'News löschen',
    
    // Whitelist-System
    'view_applications' => 'Whitelist-Anträge ansehen',
    'review_applications' => 'Anträge bearbeiten (Annehmen/Ablehnen)',
    'manage_questions' => 'Whitelist-Fragen verwalten',
    'toggle_whitelist' => 'Whitelist öffnen/schließen',
    
    // Features-System
    'view_features' => 'Features ansehen',
    'create_features' => 'Features erstellen',
    'edit_features' => 'Features bearbeiten',
    'delete_features' => 'Features löschen',
    
    // Regelwerk-System
    'view_rules' => 'Regelwerk ansehen',
    'create_rules' => 'Regeln erstellen',
    'edit_rules' => 'Regeln bearbeiten',
    'delete_rules' => 'Regeln löschen',
    
    // NEUE TWITCH/STREAMER VERWALTUNG
    'manage_streamers' => 'Twitch-Streamer verwalten',
    
    // Admin-Funktionen
    'manage_settings' => 'System-Einstellungen verwalten',
    'manage_roles' => 'Rollen & Berechtigungen verwalten',
    'manage_users' => 'Benutzer verwalten'
];

// Rolle erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_role'])) {
    $permissions = [];
    foreach ($availablePermissions as $key => $label) {
        $permissions[$key] = isset($_POST['permissions'][$key]);
    }

    $stmt = $pdo->prepare("
        INSERT INTO roles (name, discord_role_id, permissions) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $_POST['name'],
        $_POST['discord_role_id'] ?: null,
        json_encode($permissions)
    ]);
    $message = 'Rolle erfolgreich erstellt!';
    $messageType = 'success';
}

// Rolle bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_role'])) {
    $permissions = [];
    foreach ($availablePermissions as $key => $label) {
        $permissions[$key] = isset($_POST['permissions'][$key]);
    }

    $stmt = $pdo->prepare("
        UPDATE roles 
        SET name = ?, discord_role_id = ?, permissions = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['name'],
        $_POST['discord_role_id'] ?: null,
        json_encode($permissions),
        $_POST['role_id']
    ]);
    $message = 'Rolle erfolgreich aktualisiert!';
    $messageType = 'success';
}

// Rolle löschen
if (isset($_GET['delete']) && $_GET['delete'] > 3) { // Standard-Rollen nicht löschbar
    $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $message = 'Rolle erfolgreich gelöscht!';
    $messageType = 'success';
}

// Alle Rollen abrufen
$stmt = $pdo->query("SELECT * FROM roles ORDER BY id ASC");
$roles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rollen & Rechte - Admin</title>
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
                <a href="roles.php" class="admin-nav-link active">👥 Rollen & Rechte</a>
                <a href="streamers.php" class="admin-nav-link">📺 Streamer verwalten</a>
                <a href="settings.php" class="admin-nav-link">⚙️ Einstellungen</a>
                <a href="../index.php" class="admin-nav-link">🏠 Zur Hauptseite</a>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header">
                <h1>Rollen & Rechte</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="admin-content">
                <!-- Neue Rolle erstellen -->
                <div class="card">
                    <h2>Neue Rolle erstellen</h2>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Rollenname *</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label>Discord Rollen-ID (optional)</label>
                                <input type="text" name="discord_role_id" class="form-control" 
                                       placeholder="z.B. 123456789012345678">
                                <small style="color: var(--text-secondary);">
                                    Nutzer mit dieser Discord-Rolle erhalten automatisch diese Rechte
                                </small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Berechtigungen</label>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.5rem; margin-top: 0.5rem;">
                                <?php 
                                // Berechtigungen nach Kategorien gruppiert anzeigen
                                $categories = [
                                    'Dashboard & Basis' => ['view_dashboard', 'apply_whitelist'],
                                    'News-System' => ['view_news', 'create_news', 'edit_news', 'delete_news'],
                                    'Whitelist-System' => ['view_applications', 'review_applications', 'manage_questions', 'toggle_whitelist'],
                                    'Features-System' => ['view_features', 'create_features', 'edit_features', 'delete_features'],
                                    'Regelwerk-System' => ['view_rules', 'create_rules', 'edit_rules', 'delete_rules'],
                                    'Twitch-Integration' => ['manage_streamers'],
                                    'Admin-Funktionen' => ['manage_settings', 'manage_roles', 'manage_users']
                                ];
                                
                                foreach ($categories as $categoryName => $permissions):
                                ?>
                                    <div style="border: 1px solid var(--border-color); border-radius: 4px; padding: 0.8rem; background: rgba(42, 42, 42, 0.3);">
                                        <h4 style="color: var(--accent-tan); margin: 0 0 0.8rem 0; font-size: 0.9rem;">
                                            <?php echo $categoryName; ?>
                                        </h4>
                                        <?php foreach ($permissions as $key): ?>
                                            <?php if (isset($availablePermissions[$key])): ?>
                                                <label style="display: block; color: var(--text-primary); font-size: 0.85rem; margin-bottom: 0.3rem;">
                                                    <input type="checkbox" name="permissions[<?php echo $key; ?>]" value="1" style="margin-right: 0.5rem;">
                                                    <?php echo $availablePermissions[$key]; ?>
                                                    <?php if ($key === 'manage_streamers'): ?>
                                                        <span style="color: var(--accent-purple); font-size: 0.8rem;">✨ NEU</span>
                                                    <?php endif; ?>
                                                </label>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button type="submit" name="create_role" class="btn btn-primary">
                            Rolle erstellen
                        </button>
                    </form>
                </div>

                <!-- Vorhandene Rollen -->
                <div class="card">
                    <h2>Vorhandene Rollen</h2>
                    
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Discord Rolle</th>
                                <th>Berechtigungen</th>
                                <th>Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $role): ?>
                                <?php $perms = json_decode($role['permissions'], true); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($role['name']); ?></td>
                                    <td>
                                        <?php if ($role['discord_role_id']): ?>
                                            <code style="color: var(--accent-green);"><?php echo htmlspecialchars($role['discord_role_id']); ?></code>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $activePerms = array_filter($perms);
                                        if (empty($activePerms)) {
                                            echo '<span style="color: var(--text-secondary);">Keine</span>';
                                        } else {
                                            echo count($activePerms) . ' Berechtigungen';
                                            // Twitch-Berechtigung hervorheben
                                            if (isset($perms['manage_streamers']) && $perms['manage_streamers']) {
                                                echo ' <span style="color: var(--accent-purple); font-size: 0.8rem;">📺</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button onclick="editRole(<?php echo htmlspecialchars(json_encode($role)); ?>)" class="btn btn-secondary btn-sm">
                                            Bearbeiten
                                        </button>
                                        <?php if ($role['id'] > 3): ?>
                                            <a href="?delete=<?php echo $role['id']; ?>" 
                                               onclick="return confirm('Wirklich löschen?')" 
                                               class="btn btn-danger btn-sm">
                                                Löschen
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
            <h3 style="color: var(--accent-green); margin-bottom: 1rem;">Rolle bearbeiten</h3>
            
            <form method="POST">
                <input type="hidden" name="role_id" id="edit_role_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Rollenname</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Discord Rollen-ID</label>
                        <input type="text" name="discord_role_id" id="edit_discord_role_id" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label>Berechtigungen</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.5rem; margin-top: 0.5rem;">
                        <?php 
                        foreach ($categories as $categoryName => $permissions):
                        ?>
                            <div style="border: 1px solid var(--border-color); border-radius: 4px; padding: 0.8rem; background: rgba(42, 42, 42, 0.3);">
                                <h4 style="color: var(--accent-tan); margin: 0 0 0.8rem 0; font-size: 0.9rem;">
                                    <?php echo $categoryName; ?>
                                </h4>
                                <?php foreach ($permissions as $key): ?>
                                    <?php if (isset($availablePermissions[$key])): ?>
                                        <label style="display: block; color: var(--text-primary); font-size: 0.85rem; margin-bottom: 0.3rem;">
                                            <input type="checkbox" name="permissions[<?php echo $key; ?>]" value="1" id="edit_perm_<?php echo $key; ?>" style="margin-right: 0.5rem;">
                                            <?php echo $availablePermissions[$key]; ?>
                                            <?php if ($key === 'manage_streamers'): ?>
                                                <span style="color: var(--accent-purple); font-size: 0.8rem;">✨ NEU</span>
                                            <?php endif; ?>
                                        </label>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" name="edit_role" class="btn btn-primary">
                    Speichern
                </button>
            </form>
        </div>
    </div>

    <script>
        function editRole(role) {
            document.getElementById('edit_role_id').value = role.id;
            document.getElementById('edit_name').value = role.name;
            document.getElementById('edit_discord_role_id').value = role.discord_role_id || '';
            
            const permissions = JSON.parse(role.permissions);
            <?php foreach ($availablePermissions as $key => $label): ?>
                document.getElementById('edit_perm_<?php echo $key; ?>').checked = permissions['<?php echo $key; ?>'] || false;
            <?php endforeach; ?>
            
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

    <style>
        .accent-purple {
            --accent-purple: #8a2be2;
        }
    </style>
</body>
</html>