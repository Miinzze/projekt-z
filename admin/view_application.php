<?php
require_once '../config.php';
requireLogin();
requirePermission('review_applications');

if (!isset($_GET['id'])) {
    header('Location: applications.php');
    exit;
}

$applicationId = $_GET['id'];

// Antrag mit Antworten abrufen
$stmt = $pdo->prepare("
    SELECT wa.*, u.discord_id, u.discord_username, u.discord_avatar
    FROM whitelist_applications wa
    JOIN users u ON wa.user_id = u.id
    WHERE wa.id = ?
");
$stmt->execute([$applicationId]);
$application = $stmt->fetch();

if (!$application) {
    die("Antrag nicht gefunden!");
}

// Antworten abrufen
$stmt = $pdo->prepare("
    SELECT wans.*, wq.question, wq.question_type, wq.correct_answer
    FROM whitelist_answers wans
    JOIN whitelist_questions wq ON wans.question_id = wq.id
    WHERE wans.application_id = ?
    ORDER BY wq.order_num ASC
");
$stmt->execute([$applicationId]);
$answers = $stmt->fetchAll();

// Für Multiple-Choice-Antworten die Optionen laden
foreach ($answers as &$answer) {
    if ($answer['question_type'] === 'multiple_choice') {
        // Alle Optionen für diese Frage laden
        $stmt = $pdo->prepare("SELECT * FROM whitelist_question_options WHERE question_id = ? ORDER BY option_order ASC");
        $stmt->execute([$answer['question_id']]);
        $answer['all_options'] = $stmt->fetchAll();
        
        // Die gewählte Option laden
        $stmt = $pdo->prepare("SELECT * FROM whitelist_question_options WHERE id = ?");
        $stmt->execute([$answer['answer']]);
        $answer['selected_option'] = $stmt->fetch();
    }
}

// Antrag verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        $appointmentDate = $_POST['appointment_date'] . ' ' . $_POST['appointment_time'];
        
        $stmt = $pdo->prepare("
            UPDATE whitelist_applications 
            SET status = 'approved', 
                appointment_date = ?, 
                reviewed_at = NOW(),
                reviewed_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$appointmentDate, $_SESSION['user_id'], $applicationId]);

        // Discord Nachricht senden
        $message = getSetting('whitelist_approved_message', 'Deine Bewerbung wurde angenommen!');
        $message = str_replace('{date}', date('d.m.Y H:i', strtotime($appointmentDate)), $message);
        
        // Private Nachricht an Benutzer senden
        $dmSent = sendDiscordDM($application['discord_id'], $message);
        
        if ($dmSent) {
            header('Location: applications.php?success=approved&dm=sent');
        } else {
            header('Location: applications.php?success=approved&dm=failed');
        }
        exit;
    } elseif (isset($_POST['reject'])) {
        $stmt = $pdo->prepare("
            UPDATE whitelist_applications 
            SET status = 'rejected', 
                reviewed_at = NOW(),
                reviewed_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $applicationId]);

        // Discord Nachricht senden
        $message = getSetting('whitelist_rejected_message', 'Deine Bewerbung wurde abgelehnt.');
        
        // Private Nachricht an Benutzer senden
        $dmSent = sendDiscordDM($application['discord_id'], $message);
        
        if ($dmSent) {
            header('Location: applications.php?success=rejected&dm=sent');
        } else {
            header('Location: applications.php?success=rejected&dm=failed');
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrag von <?php echo htmlspecialchars($application['discord_username']); ?></title>
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
                <a href="../index.php" class="admin-nav-link">🏠 Zur Hauptseite</a>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header">
                <h1>Whitelist Antrag</h1>
                <a href="applications.php" class="btn btn-secondary">← Zurück</a>
            </div>

            <div class="admin-content">
                <div class="card">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
                        <?php if ($application['discord_avatar']): ?>
                            <img src="https://cdn.discordapp.com/avatars/<?php echo $application['discord_id']; ?>/<?php echo $application['discord_avatar']; ?>.png" 
                                 style="width: 64px; height: 64px; border-radius: 50%;">
                        <?php endif; ?>
                        <div>
                            <h2><?php echo htmlspecialchars($application['discord_username']); ?></h2>
                            <p style="color: var(--text-secondary);">
                                Eingereicht am: <?php echo date('d.m.Y H:i', strtotime($application['submitted_at'])); ?> Uhr
                            </p>
                            <span class="status-badge status-<?php echo $application['status']; ?>">
                                <?php 
                                echo [
                                    'pending' => 'Ausstehend',
                                    'approved' => 'Angenommen',
                                    'rejected' => 'Abgelehnt'
                                ][$application['status']]; 
                                ?>
                            </span>
                        </div>
                    </div>

                    <h3 style="color: var(--accent-green); margin-bottom: 1rem;">ANTWORTEN</h3>
                    
                    <?php foreach ($answers as $index => $answer): ?>
                        <div class="answer-review <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                            <h4><?php echo ($index + 1); ?>. <?php echo htmlspecialchars($answer['question']); ?></h4>
                            
                            <?php if ($answer['question_type'] === 'text'): ?>
                                <p><strong>Antwort:</strong> <span class="user-answer"><?php echo nl2br(htmlspecialchars($answer['answer'])); ?></span></p>
                                <?php if ($answer['correct_answer']): ?>
                                    <p class="correct-answer">
                                        ✓ Erwartete Antwort: <?php echo htmlspecialchars($answer['correct_answer']); ?>
                                    </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="margin-top: 0.5rem;">
                                    <?php foreach ($answer['all_options'] as $option): ?>
                                        <div style="padding: 0.5rem; margin: 0.3rem 0; border-radius: 2px; 
                                            <?php if ($answer['selected_option'] && $option['id'] == $answer['selected_option']['id']): ?>
                                                background: rgba(168, 153, 104, 0.1); border: 1px solid var(--accent-tan);
                                            <?php else: ?>
                                                background: #1c1c1c; border: 1px solid #3d3d3d;
                                            <?php endif; ?>">
                                            
                                            <?php if ($answer['selected_option'] && $option['id'] == $answer['selected_option']['id']): ?>
                                                <span style="color: var(--accent-tan);">➤</span>
                                            <?php else: ?>
                                                <span style="color: #5a5a5a;">○</span>
                                            <?php endif; ?>
                                            
                                            <span style="color: var(--text-primary); margin-left: 0.5rem;">
                                                <?php echo htmlspecialchars($option['option_text']); ?>
                                            </span>
                                            
                                            <?php if ($option['is_correct']): ?>
                                                <span style="color: var(--accent-tan); margin-left: 0.5rem;">✓ (Richtig)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($application['status'] === 'pending'): ?>
                        <form method="POST" style="margin-top: 2rem;">
                            <div class="action-buttons">
                                <button type="button" onclick="showApproveModal()" class="btn btn-primary">
                                    ✓ Annehmen
                                </button>
                                <button type="submit" name="reject" class="btn btn-danger" 
                                        onclick="return confirm('Möchtest du diesen Antrag wirklich ablehnen?')">
                                    ✗ Ablehnen
                                </button>
                            </div>

                            <!-- Modal für Termin -->
                            <div id="approveModal" class="modal">
                                <div class="modal-content">
                                    <span class="modal-close" onclick="closeApproveModal()">&times;</span>
                                    <h3 style="color: var(--accent-green); margin-bottom: 1rem;">Termin festlegen</h3>
                                    
                                    <div class="form-group">
                                        <label>Datum</label>
                                        <input type="date" name="appointment_date" class="form-control" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Uhrzeit</label>
                                        <input type="time" name="appointment_time" class="form-control" required>
                                    </div>

                                    <button type="submit" name="approve" class="btn btn-primary">
                                        Annehmen & Termin senden
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function showApproveModal() {
            document.getElementById('approveModal').classList.add('active');
        }

        function closeApproveModal() {
            document.getElementById('approveModal').classList.remove('active');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('approveModal');
            if (event.target === modal) {
                closeApproveModal();
            }
        }
    </script>
</body>
</html>