<?php
require_once '../config.php';
requireLogin();
requirePermission('view_applications');

// Filter
$filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Anträge abrufen
$query = "
    SELECT wa.*, u.discord_username, u.discord_avatar
    FROM whitelist_applications wa
    JOIN users u ON wa.user_id = u.id
";

if ($filter !== 'all') {
    $query .= " WHERE wa.status = :status";
}

$query .= " ORDER BY wa.submitted_at DESC";

$stmt = $pdo->prepare($query);
if ($filter !== 'all') {
    $stmt->execute(['status' => $filter]);
} else {
    $stmt->execute();
}
$applications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Whitelist Anträge - Admin</title>
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
                <a href="applications.php" class="admin-nav-link active">📝 Whitelist Anträge</a>
                <a href="questions.php" class="admin-nav-link">❓ Fragen verwalten</a>
                <a href="../index.php" class="admin-nav-link">🏠 Zur Hauptseite</a>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header">
                <h1>Whitelist Anträge</h1>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    $action = $_GET['success'] === 'approved' ? 'angenommen' : 'abgelehnt';
                    echo "Antrag wurde erfolgreich $action!";
                    
                    if (isset($_GET['dm'])) {
                        if ($_GET['dm'] === 'sent') {
                            echo " 📩 Private Nachricht wurde an den Bewerber gesendet.";
                        } else {
                            echo " ⚠️ Private Nachricht konnte nicht gesendet werden (Bot-Berechtigung prüfen).";
                        }
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="admin-content">
                <!-- Filter -->
                <div class="card" style="margin-bottom: 2rem;">
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <a href="?status=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">
                            Alle
                        </a>
                        <a href="?status=pending" class="btn <?php echo $filter === 'pending' ? 'btn-primary' : 'btn-secondary'; ?>">
                            Ausstehend
                        </a>
                        <a href="?status=approved" class="btn <?php echo $filter === 'approved' ? 'btn-primary' : 'btn-secondary'; ?>">
                            Angenommen
                        </a>
                        <a href="?status=rejected" class="btn <?php echo $filter === 'rejected' ? 'btn-primary' : 'btn-secondary'; ?>">
                            Abgelehnt
                        </a>
                    </div>
                </div>

                <div class="card">
                    <?php if (empty($applications)): ?>
                        <p style="color: var(--text-secondary);">Keine Anträge vorhanden.</p>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Benutzer</th>
                                    <th>Status</th>
                                    <th>Eingereicht am</th>
                                    <th>Termin</th>
                                    <th>Aktion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
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
                                            <?php if ($app['appointment_date']): ?>
                                                <?php echo date('d.m.Y H:i', strtotime($app['appointment_date'])); ?>
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view_application.php?id=<?php echo $app['id']; ?>" class="btn btn-secondary btn-sm">
                                                Ansehen
                                            </a>
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