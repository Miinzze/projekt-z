<?php
require_once '../config.php';
requireLogin();
requirePermission('manage_settings');

$message = '';
$messageType = '';

// Upload-Ordner erstellen falls nicht vorhanden
$uploadDir = '../uploads/backgrounds/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Bild hochladen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_background'])) {
    if (isset($_FILES['background_file']) && $_FILES['background_file']['error'] === 0) {
        $file = $_FILES['background_file'];
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $fileTmp = $file['tmp_name'];
        $fileType = $file['type'];
        
        // Validierung
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($fileType, $allowedTypes)) {
            $message = 'Nur JPEG, PNG und WebP Dateien erlaubt!';
            $messageType = 'error';
        } elseif ($fileSize > $maxSize) {
            $message = 'Datei zu groß! Maximum 10MB erlaubt.';
            $messageType = 'error';
        } else {
            // Dateiname sicher machen
            $safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
            $timestamp = time();
            $finalFileName = $timestamp . '_' . $safeFileName;
            $filePath = $uploadDir . $finalFileName;
            
            if (move_uploaded_file($fileTmp, $filePath)) {
                // Bildabmessungen ermitteln
                $imageInfo = getimagesize($filePath);
                $width = $imageInfo[0];
                $height = $imageInfo[1];
                
                // In Datenbank speichern
                $stmt = $pdo->prepare("
                    INSERT INTO background_images (name, file_path, file_size, image_width, image_height, uploaded_by) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['image_name'],
                    'uploads/backgrounds/' . $finalFileName,
                    $fileSize,
                    $width,
                    $height,
                    $_SESSION['user_id']
                ]);
                
                $message = 'Hintergrundbild erfolgreich hochgeladen!';
                $messageType = 'success';
            } else {
                $message = 'Fehler beim Upload!';
                $messageType = 'error';
            }
        }
    }
}

// Bild via URL hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_url_background'])) {
    $imageUrl = filter_var($_POST['image_url'], FILTER_VALIDATE_URL);
    
    if (!$imageUrl) {
        $message = 'Ungültige URL!';
        $messageType = 'error';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO background_images (name, file_path, uploaded_by) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $_POST['image_name'],
            $imageUrl,
            $_SESSION['user_id']
        ]);
        
        $message = 'Hintergrundbild-URL erfolgreich hinzugefügt!';
        $messageType = 'success';
    }
}

// Aktives Hintergrundbild setzen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_active'])) {
    $imageId = $_POST['image_id'];
    
    // Alle deaktivieren
    $pdo->exec("UPDATE background_images SET is_active = 0");
    
    // Gewähltes aktivieren
    $stmt = $pdo->prepare("UPDATE background_images SET is_active = 1 WHERE id = ?");
    $stmt->execute([$imageId]);
    
    // Setting aktualisieren
    $stmt = $pdo->prepare("SELECT file_path FROM background_images WHERE id = ?");
    $stmt->execute([$imageId]);
    $image = $stmt->fetch();
    
    if ($image) {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES ('hero_background_image', ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$image['file_path'], $image['file_path']]);
    }
    
    $message = 'Hintergrundbild aktiviert!';
    $messageType = 'success';
}

// Hintergrundbild-Einstellungen speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $settings = [
        'hero_background_overlay' => $_POST['overlay_opacity'],
        'hero_background_position' => $_POST['background_position'],
        'hero_background_size' => $_POST['background_size'],
        'hero_background_attachment' => $_POST['background_attachment'],
        'hero_background_enabled' => isset($_POST['background_enabled']) ? '1' : '0'
    ];
    
    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$key, $value, $value]);
    }
    
    $message = 'Hintergrund-Einstellungen gespeichert!';
    $messageType = 'success';
}

// Bild löschen
if (isset($_GET['delete'])) {
    $imageId = $_GET['delete'];
    
    $stmt = $pdo->prepare("SELECT file_path FROM background_images WHERE id = ?");
    $stmt->execute([$imageId]);
    $image = $stmt->fetch();
    
    if ($image && strpos($image['file_path'], 'uploads/') === 0) {
        // Lokale Datei löschen
        $fullPath = '../' . $image['file_path'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
    
    // Aus Datenbank löschen
    $stmt = $pdo->prepare("DELETE FROM background_images WHERE id = ?");
    $stmt->execute([$imageId]);
    
    $message = 'Hintergrundbild gelöscht!';
    $messageType = 'success';
}

// Alle Hintergrundbilder abrufen
$stmt = $pdo->query("
    SELECT bg.*, u.discord_username as uploaded_by_name 
    FROM background_images bg 
    LEFT JOIN users u ON bg.uploaded_by = u.id 
    ORDER BY bg.is_active DESC, bg.created_at DESC
");
$backgrounds = $stmt->fetchAll();

// Aktuelle Einstellungen
$currentSettings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'hero_background_%'");
while ($row = $stmt->fetch()) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hintergrundbilder - Admin</title>
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
                <a href="backgrounds.php" class="admin-nav-link active">🖼️ Hintergrundbilder</a>
                <a href="settings.php" class="admin-nav-link">⚙️ Einstellungen</a>
                <a href="../index.php" class="admin-nav-link">🏠 Zur Hauptseite</a>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header">
                <h1>🖼️ Hintergrundbilder</h1>
                <p style="color: var(--text-secondary);">Hero-Section Hintergrund verwalten</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="admin-content">
                <!-- Live-Vorschau -->
                <div class="card">
                    <h2>🔍 Live-Vorschau</h2>
                    <div class="hero-preview" style="
                        height: 300px;
                        background-image: 
                            linear-gradient(rgba(0, 0, 0, <?php echo $currentSettings['hero_background_overlay'] ?? 0.7; ?>), 
                                          rgba(0, 0, 0, <?php echo $currentSettings['hero_background_overlay'] ?? 0.7; ?>)),
                            <?php if (!empty($currentSettings['hero_background_image'])): ?>
                                url('<?php echo htmlspecialchars($currentSettings['hero_background_image']); ?>');
                            <?php else: ?>
                                linear-gradient(180deg, #1c1c1c 0%, #0d0d0d 100%);
                            <?php endif; ?>
                        background-size: <?php echo $currentSettings['hero_background_size'] ?? 'cover'; ?>;
                        background-position: <?php echo $currentSettings['hero_background_position'] ?? 'center center'; ?>;
                        background-attachment: scroll;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: var(--text-primary);
                        border: 1px solid var(--border-color);
                        border-radius: 4px;
                        position: relative;
                    ">
                        <div style="text-align: center;">
                            <h2 style="font-size: 2rem; color: var(--accent-tan); margin-bottom: 0.5rem;">
                                ÜBERLEBE DIE APOKALYPSE
                            </h2>
                            <p style="color: var(--text-secondary);">
                                Trete der ultimativen Zombie-Survival-Community bei
                            </p>
                        </div>
                        
                        <div style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); padding: 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                            <?php if ($currentSettings['hero_background_enabled'] == '1'): ?>
                                ✅ Aktiviert
                            <?php else: ?>
                                ❌ Deaktiviert
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Hintergrund-Einstellungen -->
                <div class="card">
                    <h2>⚙️ Hintergrund-Einstellungen</h2>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="background_enabled" value="1" 
                                           <?php echo ($currentSettings['hero_background_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    Hintergrundbild aktiviert
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label>Overlay-Transparenz</label>
                                <input type="range" name="overlay_opacity" class="form-control" 
                                       min="0" max="1" step="0.1" 
                                       value="<?php echo $currentSettings['hero_background_overlay'] ?? '0.7'; ?>"
                                       oninput="updatePreview()">
                                <small style="color: var(--text-secondary);">
                                    Dunkles Overlay für bessere Textlesbarkeit (0 = transparent, 1 = schwarz)
                                </small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Hintergrund-Position</label>
                                <select name="background_position" class="form-control">
                                    <option value="center center" <?php echo ($currentSettings['hero_background_position'] ?? '') === 'center center' ? 'selected' : ''; ?>>
                                        Zentriert
                                    </option>
                                    <option value="top center" <?php echo ($currentSettings['hero_background_position'] ?? '') === 'top center' ? 'selected' : ''; ?>>
                                        Oben Zentriert
                                    </option>
                                    <option value="bottom center" <?php echo ($currentSettings['hero_background_position'] ?? '') === 'bottom center' ? 'selected' : ''; ?>>
                                        Unten Zentriert
                                    </option>
                                    <option value="left center" <?php echo ($currentSettings['hero_background_position'] ?? '') === 'left center' ? 'selected' : ''; ?>>
                                        Links Zentriert
                                    </option>
                                    <option value="right center" <?php echo ($currentSettings['hero_background_position'] ?? '') === 'right center' ? 'selected' : ''; ?>>
                                        Rechts Zentriert
                                    </option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Hintergrund-Größe</label>
                                <select name="background_size" class="form-control">
                                    <option value="cover" <?php echo ($currentSettings['hero_background_size'] ?? '') === 'cover' ? 'selected' : ''; ?>>
                                        Cover (Bildschirm ausfüllen)
                                    </option>
                                    <option value="contain" <?php echo ($currentSettings['hero_background_size'] ?? '') === 'contain' ? 'selected' : ''; ?>>
                                        Contain (Komplettes Bild zeigen)
                                    </option>
                                    <option value="100% 100%" <?php echo ($currentSettings['hero_background_size'] ?? '') === '100% 100%' ? 'selected' : ''; ?>>
                                        Stretch (Gestreckt)
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Parallax-Effekt</label>
                            <select name="background_attachment" class="form-control">
                                <option value="scroll" <?php echo ($currentSettings['hero_background_attachment'] ?? '') === 'scroll' ? 'selected' : ''; ?>>
                                    Normal (scrollt mit)
                                </option>
                                <option value="fixed" <?php echo ($currentSettings['hero_background_attachment'] ?? '') === 'fixed' ? 'selected' : ''; ?>>
                                    Fixed (Parallax-Effekt)
                                </option>
                            </select>
                        </div>
                        
                        <button type="submit" name="save_settings" class="btn btn-primary">
                            💾 Einstellungen speichern
                        </button>
                    </form>
                </div>

                <!-- Bilder hochladen -->
                <div class="form-row">
                    <!-- Upload -->
                    <div class="card">
                        <h2>📤 Bild hochladen</h2>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label>Bild-Name</label>
                                <input type="text" name="image_name" class="form-control" required 
                                       placeholder="z.B. Zombie Apokalypse Stadtbild">
                            </div>
                            
                            <div class="form-group">
                                <label>Bild-Datei</label>
                                <input type="file" name="background_file" class="form-control" required 
                                       accept="image/jpeg,image/png,image/webp">
                                <small style="color: var(--text-secondary);">
                                    JPEG, PNG oder WebP • Max. 10MB • Empfohlen: 1920x1080px
                                </small>
                            </div>
                            
                            <button type="submit" name="upload_background" class="btn btn-primary">
                                📤 Hochladen
                            </button>
                        </form>
                    </div>
                    
                    <!-- URL hinzufügen -->
                    <div class="card">
                        <h2>🔗 Bild-URL hinzufügen</h2>
                        <form method="POST">
                            <div class="form-group">
                                <label>Bild-Name</label>
                                <input type="text" name="image_name" class="form-control" required 
                                       placeholder="z.B. Unsplash Zombie City">
                            </div>
                            
                            <div class="form-group">
                                <label>Bild-URL</label>
                                <input type="url" name="image_url" class="form-control" required 
                                       placeholder="https://example.com/image.jpg">
                                <small style="color: var(--text-secondary);">
                                    Direktlink zu einem Bild (HTTPS empfohlen)
                                </small>
                            </div>
                            
                            <button type="submit" name="add_url_background" class="btn btn-secondary">
                                🔗 URL hinzufügen
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Bildergalerie -->
                <div class="card">
                    <h2>🖼️ Hintergrundbild-Galerie (<?php echo count($backgrounds); ?>)</h2>
                    
                    <?php if (empty($backgrounds)): ?>
                        <p style="color: var(--text-secondary);">Noch keine Hintergrundbilder vorhanden.</p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem;">
                            <?php foreach ($backgrounds as $bg): ?>
                                <div class="background-card" style="
                                    border: 2px solid <?php echo $bg['is_active'] ? 'var(--accent-tan)' : 'var(--border-color)'; ?>;
                                    border-radius: 8px;
                                    overflow: hidden;
                                    background: #252525;
                                ">
                                    <div style="
                                        height: 150px;
                                        background-image: url('<?php echo htmlspecialchars($bg['file_path']); ?>');
                                        background-size: cover;
                                        background-position: center;
                                        position: relative;
                                    ">
                                        <?php if ($bg['is_active']): ?>
                                            <div style="position: absolute; top: 5px; left: 5px; background: var(--accent-tan); color: #000; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: bold; font-size: 0.8rem;">
                                                ✅ AKTIV
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($bg['image_width'] && $bg['image_height']): ?>
                                            <div style="position: absolute; top: 5px; right: 5px; background: rgba(0,0,0,0.7); color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.7rem;">
                                                <?php echo $bg['image_width']; ?>x<?php echo $bg['image_height']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="padding: 1rem;">
                                        <h4 style="margin: 0 0 0.5rem 0; color: var(--text-primary);">
                                            <?php echo htmlspecialchars($bg['name']); ?>
                                        </h4>
                                        
                                        <div style="color: var(--text-secondary); font-size: 0.8rem; margin-bottom: 1rem;">
                                            <?php if ($bg['uploaded_by_name']): ?>
                                                👤 <?php echo htmlspecialchars($bg['uploaded_by_name']); ?> • 
                                            <?php endif; ?>
                                            📅 <?php echo date('d.m.Y', strtotime($bg['created_at'])); ?>
                                            <?php if ($bg['file_size']): ?>
                                                <br>📦 <?php echo round($bg['file_size'] / 1024, 1); ?> KB
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div style="display: flex; gap: 0.5rem;">
                                            <form method="POST" style="flex: 1;">
                                                <input type="hidden" name="image_id" value="<?php echo $bg['id']; ?>">
                                                <button type="submit" name="set_active" class="btn <?php echo $bg['is_active'] ? 'btn-secondary' : 'btn-primary'; ?> btn-sm" style="width: 100%;">
                                                    <?php echo $bg['is_active'] ? '✅ Aktiv' : '🎯 Aktivieren'; ?>
                                                </button>
                                            </form>
                                            
                                            <a href="?delete=<?php echo $bg['id']; ?>" 
                                               onclick="return confirm('Hintergrundbild wirklich löschen?')" 
                                               class="btn btn-danger btn-sm">
                                                🗑️
                                            </a>
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

    <script>
        function updatePreview() {
            const opacity = document.querySelector('input[name="overlay_opacity"]').value;
            const preview = document.querySelector('.hero-preview');
            const currentBg = preview.style.backgroundImage;
            
            preview.style.backgroundImage = `linear-gradient(rgba(0, 0, 0, ${opacity}), rgba(0, 0, 0, ${opacity})), ${currentBg.split('), ')[1] || 'linear-gradient(180deg, #1c1c1c 0%, #0d0d0d 100%)'}`;
        }
    </script>
</body>
</html>