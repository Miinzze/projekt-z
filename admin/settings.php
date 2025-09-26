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
        'whitelist_webhook_url',
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
        'whitelist_cooldown_hours',
        // NEUE TWITCH EINSTELLUNGEN
        'twitch_client_id',
        'twitch_client_secret',
        'twitch_enabled',
        // NEUE NEWS WEBHOOK EINSTELLUNGEN
        'news_webhook_url',
        'news_webhook_enabled',
        'news_webhook_mention_role',
        'news_webhook_template',
        // HINTERGRUNDBILD EINSTELLUNGEN
        'hero_background_enabled',
        'hero_background_overlay'
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

// Webhook Test Funktionen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_webhook'])) {
    $webhookUrl = $_POST['whitelist_webhook_url'];
    if (!empty($webhookUrl)) {
        $testMessage = "🔧 **TEST-NACHRICHT**\n\nDies ist eine Testnachricht für deine Whitelist-Benachrichtigungen!\n\n✅ Webhook funktioniert korrekt!";
        $success = sendDiscordMessage($webhookUrl, $testMessage);
        
        if ($success) {
            $message = '✅ Whitelist-Webhook Test erfolgreich gesendet!';
            $messageType = 'success';
        } else {
            $message = '❌ Whitelist-Webhook Test fehlgeschlagen.';
            $messageType = 'error';
        }
    } else {
        $message = '⚠️ Bitte gib erst eine gültige Whitelist Webhook-URL ein.';
        $messageType = 'warning';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_news_webhook'])) {
    $webhookUrl = $_POST['news_webhook_url'];
    if (!empty($webhookUrl)) {
        $mentionRole = $_POST['news_webhook_mention_role'];
        $testMessage = "🔧 **NEWS-WEBHOOK TEST**\n\n📰 **Test News Titel**\n\nDas ist eine Test-Nachricht für den News-Channel!\n\n✅ Webhook funktioniert korrekt!";
        
        if (!empty($mentionRole)) {
            $testMessage = "<@&{$mentionRole}> " . $testMessage;
        }
        
        $success = sendDiscordMessage($webhookUrl, $testMessage);
        
        if ($success) {
            $message = '✅ News-Webhook Test erfolgreich gesendet!';
            $messageType = 'success';
        } else {
            $message = '❌ News-Webhook Test fehlgeschlagen.';
            $messageType = 'error';
        }
    } else {
        $message = '⚠️ Bitte gib erst eine gültige News Webhook-URL ein.';
        $messageType = 'warning';
    }
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
                <a href="streamers.php" class="admin-nav-link">📺 Streamer verwalten</a>
                <a href="news.php" class="admin-nav-link">📰 News verwalten</a>
                <a href="backgrounds.php" class="admin-nav-link">🖼️ Hintergrundbilder</a>
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

                    <!-- NEUE TWITCH EINSTELLUNGEN -->
                    <div class="card">
                        <h2>📺 Twitch Integration</h2>
                        
                        <div class="form-group">
                            <label style="font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="twitch_enabled" value="1" 
                                       <?php echo ($currentSettings['twitch_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>
                                       style="transform: scale(1.2);">
                                <span style="color: var(--accent-purple); font-weight: bold;">📺 Twitch Integration aktiviert</span>
                            </label>
                            <div style="margin-left: 2rem; margin-top: 0.5rem;">
                                <small style="color: var(--text-secondary);">
                                    Zeigt live Streamer auf der Hauptseite an
                                </small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Twitch Client ID</label>
                            <input type="text" name="twitch_client_id" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentSettings['twitch_client_id'] ?? ''); ?>"
                                   placeholder="Deine Twitch App Client ID">
                            <small style="color: var(--text-secondary);">
                                Benötigt für Twitch API Zugriff
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Twitch Client Secret</label>
                            <input type="password" name="twitch_client_secret" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentSettings['twitch_client_secret'] ?? ''); ?>"
                                   placeholder="Dein Twitch App Client Secret">
                            <small style="color: var(--text-secondary);">
                                Wird sicher gespeichert und für API-Authentifizierung verwendet
                            </small>
                        </div>

                        <!-- Anleitung -->
                        <div style="background: rgba(138, 43, 226, 0.1); border: 1px solid var(--accent-purple); border-radius: 4px; padding: 1rem; margin-top: 1rem;">
                            <h4 style="color: var(--accent-purple); margin: 0 0 0.5rem 0;">📋 Twitch App erstellen:</h4>
                            <ol style="color: var(--text-secondary); font-size: 0.9rem; margin: 0; padding-left: 1.2rem;">
                                <li>Gehe zu <a href="https://dev.twitch.tv/console/apps" target="_blank" style="color: var(--accent-purple);">https://dev.twitch.tv/console/apps</a></li>
                                <li>Klicke auf "Register Your Application"</li>
                                <li>Name: "<?php echo htmlspecialchars(getSetting('server_name')); ?> Website"</li>
                                <li>OAuth Redirect URLs: "<?php echo BASE_URL; ?>" (optional)</li>
                                <li>Category: "Website Integration"</li>
                                <li>Kopiere Client ID und Client Secret hierher</li>
                            </ol>
                            <div style="margin-top: 0.8rem; padding-top: 0.8rem; border-top: 1px solid rgba(138, 43, 226, 0.3);">
                                <strong style="color: var(--accent-purple);">🔗 Quick-Links:</strong><br>
                                <a href="streamers.php" style="color: var(--accent-purple);">→ Streamer verwalten</a> | 
                                <a href="../api/twitch_streams.php" target="_blank" style="color: var(--accent-purple);">→ API testen</a>
                            </div>
                        </div>
                    </div>

                    <!-- NEUE NEWS WEBHOOK EINSTELLUNGEN -->
                    <div class="card">
                        <h2>📰 News Discord-Integration</h2>
                        
                        <div class="form-group">
                            <label style="font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="news_webhook_enabled" value="1" 
                                       <?php echo ($currentSettings['news_webhook_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>
                                       style="transform: scale(1.2);">
                                <span style="color: var(--accent-tan); font-weight: bold;">📰 News Discord-Benachrichtigungen aktiviert</span>
                            </label>
                            <div style="margin-left: 2rem; margin-top: 0.5rem;">
                                <small style="color: var(--text-secondary);">
                                    Sendet automatisch eine Nachricht in Discord wenn neue News veröffentlicht werden
                                </small>
                            </div>
                        </div>

                        <div class="form-group" style="border: 2px solid var(--accent-tan); border-radius: 6px; padding: 1rem; background: rgba(168, 153, 104, 0.05);">
                            <label style="color: var(--accent-tan); font-weight: bold; font-size: 1.1rem;">
                                📢 News-Channel Webhook URL
                            </label>
                            <input type="url" name="news_webhook_url" id="news_webhook_url" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentSettings['news_webhook_url'] ?? ''); ?>"
                                   placeholder="https://discord.com/api/webhooks/...">
                            <small style="color: var(--text-secondary); display: block; margin-top: 0.5rem;">
                                📩 Webhook für den News/Ankündigungen-Channel
                            </small>
                            
                            <div style="margin-top: 0.8rem;">
                                <button type="button" onclick="testNewsWebhook()" class="btn btn-secondary btn-sm">
                                    🧪 News-Webhook testen
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>@Rolle erwähnen (optional)</label>
                            <input type="text" name="news_webhook_mention_role" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentSettings['news_webhook_mention_role'] ?? ''); ?>"
                                   placeholder="z.B. 123456789012345678">
                            <small style="color: var(--text-secondary);">
                                Rollen-ID die bei News-Posts erwähnt werden soll (z.B. @everyone oder @News)
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Nachricht Template</label>
                            <textarea name="news_webhook_template" class="form-control" rows="4"><?php echo htmlspecialchars($currentSettings['news_webhook_template'] ?? '📰 **NEUE NEWS VERÖFFENTLICHT**\n\n**{title}**\n\n{content}\n\n🔗 **Mehr lesen:** {url}'); ?></textarea>
                            <small style="color: var(--text-secondary);">
                                Platzhalter: <code>{title}</code>, <code>{content}</code>, <code>{url}</code>, <code>{author}</code>
                            </small>
                        </div>

                        <!-- Anleitung für News-Webhook -->
                        <div style="background: rgba(168, 153, 104, 0.1); border: 1px solid var(--accent-tan); border-radius: 4px; padding: 1rem; margin-top: 1rem;">
                            <h4 style="color: var(--accent-tan); margin: 0 0 0.5rem 0;">📋 News-Webhook Setup:</h4>
                            <ol style="color: var(--text-secondary); font-size: 0.9rem; margin: 0; padding-left: 1.2rem;">
                                <li>Gehe zu deinem News/Ankündigungen Discord-Channel</li>
                                <li>Channel-Einstellungen → Integrationen → Webhooks</li>
                                <li>Erstelle einen neuen Webhook namens "News Bot"</li>
                                <li>Kopiere die Webhook-URL und füge sie hier ein</li>
                                <li>Optional: Erstelle eine @News Rolle für Benachrichtigungen</li>
                            </ol>
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

                        <!-- Whitelist Webhook -->
                        <div class="form-group" style="border: 2px solid var(--accent-tan); border-radius: 6px; padding: 1rem; background: rgba(168, 153, 104, 0.05);">
                            <label style="color: var(--accent-tan); font-weight: bold; font-size: 1.1rem;">
                                🔔 Whitelist Benachrichtigungs-Webhook URL
                            </label>
                            <input type="url" name="whitelist_webhook_url" id="whitelist_webhook_url" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentSettings['whitelist_webhook_url'] ?? ''); ?>"
                                   placeholder="https://discord.com/api/webhooks/...">
                            <small style="color: var(--text-secondary); display: block; margin-top: 0.5rem;">
                                📩 Webhook für Admin-Benachrichtigungen wenn neue Whitelist-Anträge eingehen.
                            </small>
                            
                            <div style="margin-top: 0.8rem;">
                                <button type="button" onclick="testWebhook()" class="btn btn-secondary btn-sm">
                                    🧪 Webhook testen
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="require_discord_server_member" value="1" 
                                       <?php echo ($currentSettings['require_discord_server_member'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                Discord Server Mitgliedschaft erforderlich
                            </label>
                        </div>
                    </div>

                    <!-- HINTERGRUND-EINSTELLUNGEN -->
                    <div class="card">
                        <h2>🖼️ Hero-Section Hintergrund</h2>
                        
                        <div class="form-group">
                            <label style="font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="hero_background_enabled" value="1" 
                                       <?php echo ($currentSettings['hero_background_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>
                                       style="transform: scale(1.2);">
                                <span style="color: var(--accent-tan); font-weight: bold;">🎨 Benutzerdefinierte Hintergrundbilder aktiviert</span>
                            </label>
                            <div style="margin-left: 2rem; margin-top: 0.5rem;">
                                <small style="color: var(--text-secondary);">
                                    Ermöglicht das Hochladen und Verwalten von Hintergrundbildern
                                </small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Overlay-Transparenz</label>
                            <input type="range" name="hero_background_overlay" class="form-control" 
                                   min="0" max="1" step="0.1" 
                                   value="<?php echo $currentSettings['hero_background_overlay'] ?? '0.7'; ?>"
                                   style="width: 100%;">
                            <small style="color: var(--text-secondary);">
                                Dunkles Overlay für bessere Textlesbarkeit (0 = transparent, 1 = komplett schwarz)
                            </small>
                        </div>

                        <!-- Hintergrundbild-Manager Link -->
                        <div style="background: rgba(168, 153, 104, 0.1); border: 1px solid var(--accent-tan); border-radius: 4px; padding: 1rem; margin-top: 1rem;">
                            <h4 style="color: var(--accent-tan); margin: 0 0 0.5rem 0;">🖼️ Hintergrundbild-Manager:</h4>
                            <p style="color: var(--text-secondary); margin: 0 0 0.8rem 0; font-size: 0.9rem;">
                                Upload und Verwaltung von Hintergrundbildern für die Hero-Section
                            </p>
                            <a href="backgrounds.php" class="btn btn-secondary">
                                📤 Hintergrundbilder verwalten
                            </a>
                        </div>
                    </div>

                    <!-- Whitelist Kontrolle -->
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
                        💾 Einstellungen speichern
                    </button>
                </form>
                
                <!-- Test-Formulare -->
                <form method="POST" id="webhookTestForm" style="display: none;">
                    <input type="hidden" name="test_webhook" value="1">
                    <input type="hidden" name="whitelist_webhook_url" id="test_webhook_url">
                </form>
                
                <form method="POST" id="newsWebhookTestForm" style="display: none;">
                    <input type="hidden" name="test_news_webhook" value="1">
                    <input type="hidden" name="news_webhook_url" id="test_news_webhook_url">
                    <input type="hidden" name="news_webhook_mention_role" id="test_news_mention_role">
                </form>
            </div>
        </main>
    </div>

    <script>
        function testWebhook() {
            const webhookUrl = document.getElementById('whitelist_webhook_url').value;
            
            if (!webhookUrl) {
                alert('Bitte gib erst eine Webhook-URL ein!');
                return;
            }

            if (!webhookUrl.includes('discord.com/api/webhooks/')) {
                alert('Das sieht nicht nach einer gültigen Discord Webhook-URL aus!');
                return;
            }

            document.getElementById('test_webhook_url').value = webhookUrl;
            document.getElementById('webhookTestForm').submit();
        }

        function testNewsWebhook() {
            const webhookUrl = document.getElementById('news_webhook_url').value;
            const mentionRole = document.querySelector('input[name="news_webhook_mention_role"]').value;
            
            if (!webhookUrl) {
                alert('Bitte gib erst eine News-Webhook-URL ein!');
                return;
            }

            if (!webhookUrl.includes('discord.com/api/webhooks/')) {
                alert('Das sieht nicht nach einer gültigen Discord Webhook-URL aus!');
                return;
            }

            document.getElementById('test_news_webhook_url').value = webhookUrl;
            document.getElementById('test_news_mention_role').value = mentionRole;
            document.getElementById('newsWebhookTestForm').submit();
        }
    </script>

    <style>
        .accent-purple {
            --accent-purple: #8a2be2;
        }
    </style>
</body>
</html>