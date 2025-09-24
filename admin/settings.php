<?php
require_once '../config.php';
requireLogin();
requirePermission('manage_settings');

$message = '';
$messageType = '';

// Einstellungen speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'discord_webhook_url',
        'whitelist_approved_message',
        'whitelist_rejected_message',
        'discord_server_invite',
        'server_name',
        'player_count_api',
        'player_count_type',
        'require_discord_server_member',
        'whitelist_random_enabled',
        'whitelist_random_questions',
        'whitelist_enabled',
        'whitelist_cooldown_hours'
    ];

    foreach ($settings as $key) {
        if (isset($_POST[$key])) {
            $value = $_POST[$key];
            
            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
        }
    }

    $message = 'Einstellungen erfolgreich gespeichert!';
    $messageType = 'success';
}

// Aktuelle Einstellungen abrufen
$stmt = $pdo->query("SELECT * FROM settings");
$currentSettings = [];
while ($row = $stmt->fetch()) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen - Admin</title>
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
                <a href="roles.php" class="admin-nav-link">👥 Rollen & Rechte</a>
                <a href="settings.php" class="admin-nav-link active">⚙️ Einstellungen</a>
                <a href="../index.php" class="admin-nav-link">🏠 Zur Hauptseite</a>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header">
                <h1>Einstellungen</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="admin-content">
                <form method="POST">
                    <!-- Allgemeine Einstellungen -->
                    <div class="card">
                        <h2>Allgemeine Einstellungen</h2>
                        
                        <div class="form-group">
                            <label>Server Name</label>
                            <input type="text" name="server_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentSettings['server_name'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label>Discord Server Einladung</label>
                            <input type="url" name="discord_server_invite" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentSettings['discord_server_invite'] ?? ''); ?>"
                                   placeholder="https://discord.gg/...">
                        </div>

                        <div class="form-group">
                            <label>Spielerzahl API URL (optional)</label>
                            <input type="url" name="player_count_api" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentSettings['player_count_api'] ?? ''); ?>"
                                   placeholder="https://api.example.com/server/status">
                            <small style="color: var(--text-secondary); display: block; margin-top: 0.5rem;">
                                URL zur API die die aktuelle Spielerzahl zurückgibt
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Server-Typ</label>
                            <select name="player_count_type" class="form-control">
                                <option value="fivem" <?php echo ($currentSettings['player_count_type'] ?? '') === 'fivem' ? 'selected' : ''; ?>>
                                    FiveM (GTA V)
                                </option>
                                <option value="minecraft" <?php echo ($currentSettings['player_count_type'] ?? '') === 'minecraft' ? 'selected' : ''; ?>>
                                    Minecraft
                                </option>
                                <option value="cftools" <?php echo ($currentSettings['player_count_type'] ?? '') === 'cftools' ? 'selected' : ''; ?>>
                                    CFTools (DayZ)
                                </option>
                                <option value="battlemetrics" <?php echo ($currentSettings['player_count_type'] ?? '') === 'battlemetrics' ? 'selected' : ''; ?>>
                                    BattleMetrics
                                </option>
                                <option value="custom" <?php echo ($currentSettings['player_count_type'] ?? '') === 'custom' ? 'selected' : ''; ?>>
                                    Custom/Generic
                                </option>
                            </select>
                            <small style="color: var(--text-secondary); display: block; margin-top: 0.5rem;">
                                Wähle deinen Server-Typ für die richtige API-Verarbeitung
                            </small>
                        </div>
                    </div>

                    <!-- Discord Einstellungen -->
                    <div class="card">
                        <h2>Discord Einstellungen</h2>
                        
                        <div class="form-group">
                            <label>Discord Bot Token (config.php)</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo !empty(DISCORD_BOT_TOKEN) ? str_repeat('●', 20) : 'NICHT KONFIGURIERT'; ?>"
                                   disabled>
                            <small style="color: var(--text-secondary);">
                                Bot Token wird in der config.php konfiguriert. Benötigt für private Nachrichten.
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label>Discord Webhook URL (optional)</label>
                            <input type="url" name="discord_webhook_url" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentSettings['discord_webhook_url'] ?? ''); ?>"
                                   placeholder="https://discord.com/api/webhooks/...">
                            <small style="color: var(--text-secondary);">
                                ⚠️ <strong>NICHT MEHR VERWENDET:</strong> Bewerber erhalten jetzt private Nachrichten anstatt öffentlicher Webhook-Nachrichten
                            </small>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="require_discord_server_member" value="1" 
                                       <?php echo ($currentSettings['require_discord_server_member'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                Discord Server Mitgliedschaft erforderlich
                            </label>
                        </div>
                        
                        <div style="background: rgba(168, 153, 104, 0.1); border: 1px solid var(--accent-tan); padding: 1rem; border-radius: 4px; margin-top: 1rem;">
                            <h4 style="color: var(--accent-tan); margin: 0 0 0.5rem 0;">🤖 Bot Setup Info:</h4>
                            <ul style="color: var(--text-secondary); margin: 0.5rem 0 0 1.5rem;">
                                <li>Bot muss auf deinem Discord Server sein</li>
                                <li>Bot braucht Berechtigung "Nachrichten senden"</li>
                                <li>Bewerber erhalten private Nachrichten direkt vom Bot</li>
                                <li>Keine öffentlichen Channel-Nachrichten mehr!</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Whitelist Nachrichten -->
                    <div class="card">
                        <h2>Whitelist Kontrolle</h2>
                        
                        <div class="form-group">
                            <label style="font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="whitelist_enabled" value="1" 
                                       <?php echo ($currentSettings['whitelist_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>
                                       style="transform: scale(1.2);">
                                <span style="color: var(--accent-tan); font-weight: bold;">🔓 Whitelist geöffnet</span>
                            </label>
                            <div style="margin-left: 2rem; margin-top: 0.5rem;">
                                <small style="color: var(--text-secondary);">
                                    Wenn deaktiviert, können sich keine neuen Bewerber anmelden
                                </small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Bewerbungs-Cooldown (Stunden)</label>
                            <input type="number" name="whitelist_cooldown_hours" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentSettings['whitelist_cooldown_hours'] ?? '24'); ?>"
                                   min="1" max="168">
                            <small style="color: var(--text-secondary);">
                                Wartezeit zwischen Bewerbungen in Stunden (24 = 1 Tag, 168 = 1 Woche)
                            </small>
                        </div>
                        
                        <div style="background: rgba(168, 153, 104, 0.1); border: 1px solid var(--accent-tan); padding: 1rem; border-radius: 4px; margin-top: 1rem;">
                            <h4 style="color: var(--accent-tan); margin: 0 0 0.5rem 0;">💡 Quick-Einstellungen:</h4>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem;">
                                <button type="button" onclick="setCooldown(24)" class="btn btn-secondary btn-sm">24h (1 Tag)</button>
                                <button type="button" onclick="setCooldown(48)" class="btn btn-secondary btn-sm">48h (2 Tage)</button>
                                <button type="button" onclick="setCooldown(72)" class="btn btn-secondary btn-sm">72h (3 Tage)</button>
                                <button type="button" onclick="setCooldown(168)" class="btn btn-secondary btn-sm">168h (1 Woche)</button>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <h2>Whitelist Einstellungen</h2>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="whitelist_random_enabled" value="1" 
                                       <?php echo ($currentSettings['whitelist_random_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                Zufällige Fragen aktivieren
                            </label>
                            <small style="color: var(--text-secondary); display: block; margin-top: 0.5rem;">
                                Wenn aktiviert, werden bei jeder Bewerbung zufällige Fragen aus dem Fragenpool ausgewählt
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Anzahl zufälliger Fragen</label>
                            <input type="number" name="whitelist_random_questions" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentSettings['whitelist_random_questions'] ?? '5'); ?>"
                                   min="1" max="50">
                            <small style="color: var(--text-secondary);">
                                Anzahl der Fragen, die bei aktivierten zufälligen Fragen gestellt werden
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Nachricht bei Annahme</label>
                            <textarea name="whitelist_approved_message" class="form-control" rows="3"><?php echo htmlspecialchars($currentSettings['whitelist_approved_message'] ?? ''); ?></textarea>
                            <small style="color: var(--text-secondary);">
                                Verwende {date} als Platzhalter für Datum und Uhrzeit
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Nachricht bei Ablehnung</label>
                            <textarea name="whitelist_rejected_message" class="form-control" rows="3"><?php echo htmlspecialchars($currentSettings['whitelist_rejected_message'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Einstellungen speichern
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script>
        function setCooldown(hours) {
            document.querySelector('input[name="whitelist_cooldown_hours"]').value = hours;
        }
    </script>
</body>
</html>