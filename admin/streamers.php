<?php
require_once '../config.php';
requireLogin();
requirePermission('manage_streamers'); // Neue Berechtigung

$message = '';
$messageType = '';

// Streamer hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_streamer'])) {
    $twitchUsername = trim($_POST['twitch_username']);
    $displayName = trim($_POST['display_name']);
    
    // Validierung
    if (empty($twitchUsername) || empty($displayName)) {
        $message = 'Bitte alle Felder ausfüllen!';
        $messageType = 'error';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{4,25}$/', $twitchUsername)) {
        $message = 'Ungültiger Twitch-Benutzername! (4-25 Zeichen, nur Buchstaben, Zahlen und _)';
        $messageType = 'error';
    } else {
        // Prüfen ob Username bereits existiert
        $stmt = $pdo->prepare("SELECT id FROM streamers WHERE twitch_username = ?");
        $stmt->execute([$twitchUsername]);
        
        if ($stmt->fetch()) {
            $message = 'Dieser Streamer ist bereits in der Liste!';
            $messageType = 'error';
        } else {
            // Höchste Ordnungsnummer ermitteln
            $stmt = $pdo->query("SELECT COALESCE(MAX(order_num), 0) + 1 as next_order FROM streamers");
            $nextOrder = $stmt->fetch()['next_order'];
            
            $stmt = $pdo->prepare("
                INSERT INTO streamers (twitch_username, display_name, order_num, added_by) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$twitchUsername, $displayName, $nextOrder, $_SESSION['user_id']]);
            
            $message = 'Streamer erfolgreich hinzugefügt!';
            $messageType = 'success';
        }
    }
}

// Streamer bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_streamer'])) {
    $streamerId = $_POST['streamer_id'];
    $displayName = trim($_POST['display_name']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    $stmt = $pdo->prepare("
        UPDATE streamers 
        SET display_name = ?, is_active = ? 
        WHERE id = ?
    ");
    $stmt->execute([$displayName, $isActive, $streamerId]);
    
    $message = 'Streamer erfolgreich aktualisiert!';
    $messageType = 'success';
}

// Streamer löschen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM streamers WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    
    $message = 'Streamer erfolgreich gelöscht!';
    $messageType = 'success';
}

// Reihenfolge ändern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder'])) {
    $orders = $_POST['order'];
    foreach ($orders as $id => $order) {
        $stmt = $pdo->prepare("UPDATE streamers SET order_num = ? WHERE id = ?");
        $stmt->execute([intval($order), intval($id)]);
    }
    
    $message = 'Reihenfolge erfolgreich aktualisiert!';
    $messageType = 'success';
}

// Alle Streamer abrufen
$stmt = $pdo->query("
    SELECT s.*, u.discord_username as added_by_username
    FROM streamers s 
    LEFT JOIN users u ON s.added_by = u.id
    ORDER BY s.order_num ASC
");
$streamers = $stmt->fetchAll();

// Twitch-Settings prüfen
$twitchClientId = getSetting('twitch_client_id', '');
$twitchEnabled = getSetting('twitch_enabled', '1') == '1';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Streamer verwalten - Admin</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin-style.css">
</head>
<body>
    <div class="admin-container">
        <aside class="admin-sidebar">
            <div class="admin-logo">
                <h2>ADMIN PANEL</h2>
                <p><?php echo htmlspecialchars(getCurrentUser()['discord_username']); ?></p>
            </div>
            <nav class="admin-nav">
                <a href="dashboard.php" class="admin-nav-link">📊 Dashboard</a>
                
                <?php if (hasPermission('view_applications')): ?>
                <a href="applications.php" class="admin-nav-link">📝 Whitelist Anträge</a>
                <?php endif; ?>
                
                <?php if (hasPermission('manage_streamers')): ?>
                <a href="streamers.php" class="admin-nav-link active">📺 Streamer verwalten</a>
                <?php endif; ?>
                
                <?php if (hasPermission('manage_settings')): ?>
                <a href="settings.php" class="admin-nav-link">⚙️ Einstellungen</a>
                <?php endif; ?>
                
                <a href="../index.php" class="admin-nav-link">🏠 Zur Hauptseite</a>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header">
                <h1>📺 Streamer verwalten</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Twitch Status -->
            <?php if (!$twitchEnabled || empty($twitchClientId)): ?>
                <div class="alert" style="background: rgba(217, 119, 6, 0.2); border: 1px solid var(--accent-orange);">
                    <h4 style="color: var(--accent-orange); margin: 0 0 0.5rem 0;">⚠️ Twitch Integration nicht konfiguriert</h4>
                    <p style="color: var(--text-secondary); margin: 0;">
                        Twitch API-Einstellungen sind nicht vollständig konfiguriert. 
                        <a href="settings.php" style="color: var(--accent-orange);">→ Zu den Einstellungen</a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="admin-content">
                <!-- Neuen Streamer hinzufügen -->
                <div class="card">
                    <h2>Neuen Streamer hinzufügen</h2>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Twitch-Benutzername *</label>
                                <input type="text" name="twitch_username" class="form-control" required 
                                       placeholder="z.B. beispielstreamer" pattern="[a-zA-Z0-9_]{4,25}">
                                <small style="color: var(--text-secondary);">
                                    Nur der Username, ohne https://twitch.tv/
                                </small>
                            </div>

                            <div class="form-group">
                                <label>Anzeigename *</label>
                                <input type="text" name="display_name" class="form-control" required 
                                       placeholder="z.B. Beispiel Streamer">
                                <small style="color: var(--text-secondary);">
                                    Name wie er auf der Website angezeigt wird
                                </small>
                            </div>
                        </div>

                        <button type="submit" name="add_streamer" class="btn btn-primary">
                            📺 Streamer hinzufügen
                        </button>
                    </form>
                </div>

                <!-- Live-Test -->
                <?php if ($twitchEnabled && !empty($twitchClientId)): ?>
                <div class="card">
                    <h2>🔴 Live-Status testen</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                        Teste die Twitch-Integration und sieh welche Streamer gerade live sind.
                    </p>
                    
                    <button onclick="testTwitchAPI()" class="btn btn-secondary" id="test-btn">
                        🧪 Live-Status prüfen
                    </button>
                    
                    <div id="twitch-test-results" style="margin-top: 1rem;"></div>
                </div>
                <?php endif; ?>

                <!-- Vorhandene Streamer -->
                <div class="card">
                    <h2>Vorhandene Streamer (<?php echo count($streamers); ?>)</h2>
                    
                    <?php if (empty($streamers)): ?>
                        <p style="color: var(--text-secondary);">Keine Streamer vorhanden.</p>
                    <?php else: ?>
                        <form method="POST" id="reorder-form">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">Reihenfolge</th>
                                        <th>Twitch-Username</th>
                                        <th>Anzeigename</th>
                                        <th>Status</th>
                                        <th>Hinzugefügt</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($streamers as $streamer): ?>
                                        <tr class="<?php echo $streamer['is_active'] ? '' : 'inactive-row'; ?>">
                                            <td>
                                                <input type="number" name="order[<?php echo $streamer['id']; ?>]" 
                                                       value="<?php echo $streamer['order_num']; ?>" 
                                                       class="form-control" style="width: 60px;" min="1">
                                            </td>
                                            <td>
                                                <a href="https://twitch.tv/<?php echo htmlspecialchars($streamer['twitch_username']); ?>" 
                                                   target="_blank" style="color: var(--accent-purple); text-decoration: none;">
                                                    <?php echo htmlspecialchars($streamer['twitch_username']); ?> 🔗
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($streamer['display_name']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $streamer['is_active'] ? 'status-approved' : 'status-rejected'; ?>">
                                                    <?php echo $streamer['is_active'] ? 'Aktiv' : 'Inaktiv'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('d.m.Y', strtotime($streamer['created_at'])); ?>
                                                <?php if ($streamer['added_by_username']): ?>
                                                    <br><small style="color: var(--text-secondary);">
                                                        von <?php echo htmlspecialchars($streamer['added_by_username']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button onclick="editStreamer(<?php echo htmlspecialchars(json_encode($streamer)); ?>)" 
                                                        class="btn btn-secondary btn-sm">
                                                    Bearbeiten
                                                </button>
                                                <a href="?delete=<?php echo $streamer['id']; ?>" 
                                                   onclick="return confirm('Streamer wirklich löschen?')" 
                                                   class="btn btn-danger btn-sm">
                                                    Löschen
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <button type="submit" name="reorder" class="btn btn-primary" style="margin-top: 1rem;">
                                💾 Reihenfolge speichern
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
            <h3 style="color: var(--accent-tan); margin-bottom: 1rem;">Streamer bearbeiten</h3>
            
            <form method="POST">
                <input type="hidden" name="streamer_id" id="edit_streamer_id">
                
                <div class="form-group">
                    <label>Twitch-Benutzername</label>
                    <input type="text" id="edit_twitch_username" class="form-control" disabled 
                           style="background: #1a1a1a; color: var(--text-secondary);">
                    <small style="color: var(--text-secondary);">
                        Username kann nicht geändert werden
                    </small>
                </div>

                <div class="form-group">
                    <label>Anzeigename</label>
                    <input type="text" name="display_name" id="edit_display_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                        Aktiv (auf Website anzeigen)
                    </label>
                </div>

                <button type="submit" name="edit_streamer" class="btn btn-primary">
                    Speichern
                </button>
            </form>
        </div>
    </div>

    <script>
        function editStreamer(streamer) {
            document.getElementById('edit_streamer_id').value = streamer.id;
            document.getElementById('edit_twitch_username').value = streamer.twitch_username;
            document.getElementById('edit_display_name').value = streamer.display_name;
            document.getElementById('edit_is_active').checked = streamer.is_active == 1;
            
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        async function testTwitchAPI() {
            const btn = document.getElementById('test-btn');
            const results = document.getElementById('twitch-test-results');
            
            btn.textContent = '⏳ Teste...';
            btn.disabled = true;
            
            try {
                const response = await fetch('../api/twitch_streams.php');
                const data = await response.json();
                
                if (data.success) {
                    if (data.live_streamers.length > 0) {
                        let html = '<h4 style="color: var(--accent-tan);">🔴 Live Streamer (' + data.live_streamers.length + '):</h4>';
                        html += '<div style="display: grid; gap: 1rem; margin-top: 1rem;">';
                        
                        data.live_streamers.forEach(streamer => {
                            html += `
                                <div style="background: #252525; padding: 1rem; border-radius: 4px; border: 1px solid var(--border-color);">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                        <span style="background: #ff0000; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8rem;">LIVE</span>
                                        <strong style="color: var(--accent-purple);">${streamer.display_name}</strong>
                                        <span style="color: var(--text-secondary);">(${streamer.twitch_username})</span>
                                    </div>
                                    <p style="color: var(--text-primary); margin: 0.5rem 0;">${streamer.stream_title}</p>
                                    <div style="color: var(--text-secondary); font-size: 0.9rem;">
                                        🎮 ${streamer.game_name} | 👁️ ${streamer.viewer_count} Zuschauer
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        results.innerHTML = html;
                    } else {
                        results.innerHTML = '<div style="color: var(--text-secondary);">📴 Aktuell ist keiner deiner Streamer live.</div>';
                    }
                } else {
                    results.innerHTML = '<div style="color: var(--accent-red);">❌ Fehler: ' + (data.error || 'Unbekannter Fehler') + '</div>';
                }
            } catch (error) {
                results.innerHTML = '<div style="color: var(--accent-red);">❌ Netzwerkfehler: ' + error.message + '</div>';
            }
            
            btn.textContent = '🧪 Live-Status prüfen';
            btn.disabled = false;
        }

        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }
    </script>

    <style>
        .inactive-row {
            opacity: 0.6;
        }
        
        .status-approved {
            background: rgba(168, 153, 104, 0.2);
            color: var(--accent-tan);
        }
        
        .status-rejected {
            background: rgba(140, 74, 74, 0.2);
            color: var(--accent-red);
        }
    </style>
</body>
</html>