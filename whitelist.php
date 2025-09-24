<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$message = '';
$messageType = '';

// Prüfen ob User sich bewerben kann (Whitelist offen + Cooldown)
$applicationCheck = canUserApply($_SESSION['user_id']);

// Prüfen ob bereits ein Antrag existiert
$stmt = $pdo->prepare("SELECT * FROM whitelist_applications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$existingApplication = $stmt->fetch();

// Fragebogen absenden
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    // Erneut prüfen ob Bewerbung möglich ist
    $applicationCheck = canUserApply($_SESSION['user_id']);
    
    if (!$applicationCheck['can_apply']) {
        $message = $applicationCheck['message'];
        $messageType = 'warning';
    } elseif ($existingApplication && $existingApplication['status'] === 'pending') {
        $message = 'Du hast bereits einen ausstehenden Antrag!';
        $messageType = 'warning';
    } else {
        // Neue Bewerbung erstellen
        $stmt = $pdo->prepare("INSERT INTO whitelist_applications (user_id) VALUES (?)");
        $stmt->execute([$_SESSION['user_id']]);
        $applicationId = $pdo->lastInsertId();

        // Zugewiesene Fragen für diese Bewerbung speichern
        if (isset($_POST['assigned_questions'])) {
            $assignedQuestions = explode(',', $_POST['assigned_questions']);
            foreach ($assignedQuestions as $qId) {
                $stmt = $pdo->prepare("INSERT INTO whitelist_application_questions (application_id, question_id) VALUES (?, ?)");
                $stmt->execute([$applicationId, $qId]);
            }
        }

        // Antworten speichern
        foreach ($_POST['answers'] as $questionId => $answer) {
            // Frage abrufen
            $stmt = $pdo->prepare("SELECT * FROM whitelist_questions WHERE id = ?");
            $stmt->execute([$questionId]);
            $question = $stmt->fetch();
            
            $isCorrect = false;
            
            if ($question['question_type'] === 'multiple_choice') {
                // Bei Multiple-Choice die richtige Option prüfen
                $stmt = $pdo->prepare("SELECT * FROM whitelist_question_options WHERE id = ? AND is_correct = 1");
                $stmt->execute([$answer]);
                $isCorrect = $stmt->fetch() ? true : false;
            } else {
                // Bei Text-Fragen die Antwort vergleichen
                if ($question['correct_answer']) {
                    $isCorrect = (strtolower(trim($answer)) === strtolower(trim($question['correct_answer'])));
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO whitelist_answers (application_id, question_id, answer, is_correct) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$applicationId, $questionId, $answer, $isCorrect ? 1 : 0]);
        }

        $message = 'Deine Bewerbung wurde erfolgreich eingereicht!';
        $messageType = 'success';
        
        // Aktualisierte Bewerbung laden
        $stmt = $pdo->prepare("SELECT * FROM whitelist_applications WHERE id = ?");
        $stmt->execute([$applicationId]);
        $existingApplication = $stmt->fetch();
    }
}

// Fragen ermitteln
$randomEnabled = getSetting('whitelist_random_enabled', '0') == '1';
$randomCount = (int)getSetting('whitelist_random_questions', '5');

// Wenn bereits eine abgelehnte Bewerbung existiert, deren Fragen laden
if ($existingApplication && $existingApplication['status'] === 'rejected') {
    $stmt = $pdo->prepare("
        SELECT wq.* 
        FROM whitelist_questions wq
        JOIN whitelist_application_questions waq ON wq.id = waq.question_id
        WHERE waq.application_id = ?
        ORDER BY wq.order_num ASC
    ");
    $stmt->execute([$existingApplication['id']]);
    $questions = $stmt->fetchAll();
} else {
    // Neue Fragen laden
    if ($randomEnabled && $randomCount > 0) {
        // Zufällige Fragen auswählen
        $stmt = $pdo->prepare("SELECT * FROM whitelist_questions WHERE is_active = 1 ORDER BY RAND() LIMIT ?");
        $stmt->execute([$randomCount]);
        $questions = $stmt->fetchAll();
    } else {
        // Alle aktiven Fragen
        $stmt = $pdo->query("SELECT * FROM whitelist_questions WHERE is_active = 1 ORDER BY order_num ASC");
        $questions = $stmt->fetchAll();
    }
}

// Optionen für Multiple-Choice-Fragen laden
foreach ($questions as &$question) {
    if ($question['question_type'] === 'multiple_choice') {
        $stmt = $pdo->prepare("SELECT * FROM whitelist_question_options WHERE question_id = ? ORDER BY option_order ASC");
        $stmt->execute([$question['id']]);
        $question['options'] = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Whitelist - <?php echo htmlspecialchars(getSetting('server_name')); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-content">
                <div class="logo">
                    <div class="logo-img" style="background: var(--accent-tan); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">☠️</div>
                    <h1><?php echo htmlspecialchars(getSetting('server_name')); ?></h1>
                </div>
                <nav class="nav">
                    <a href="index.php" class="nav-link">HOME</a>
                    <a href="rules.php" class="nav-link">REGELWERK</a>
                    <a href="features.php" class="nav-link">FEATURES</a>
                    <a href="whitelist.php" class="nav-link active">WHITELIST</a>
                    <?php if (hasPermission('manage_news')): ?>
                        <a href="admin/dashboard.php" class="nav-link">ADMIN</a>
                    <?php endif; ?>
                    <a href="logout.php" class="nav-link">LOGOUT</a>
                </nav>
            </div>
        </header>

        <div style="max-width: 800px; margin: 2rem auto; padding: 0 2rem;">
            <h2 class="section-title">WHITELIST ANTRAG</h2>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Whitelist Status Anzeige -->
            <?php if (!$applicationCheck['can_apply']): ?>
                <div class="form-container" style="margin-bottom: 2rem;">
                    <?php if ($applicationCheck['reason'] === 'disabled'): ?>
                        <h3 style="color: var(--accent-red); margin-bottom: 1rem;">🔒 WHITELIST GESCHLOSSEN</h3>
                        <div style="background: rgba(140, 74, 74, 0.2); padding: 1.5rem; border-radius: 4px; border: 1px solid var(--accent-red);">
                            <p style="color: var(--accent-red); font-size: 1.1rem; font-weight: bold; margin-bottom: 0.5rem;">
                                Die Whitelist ist derzeit geschlossen.
                            </p>
                            <p style="color: var(--text-secondary);">
                                Bewerbungen sind momentan nicht möglich. Schau später wieder vorbei oder folge unserem Discord für Updates.
                            </p>
                        </div>
                    <?php elseif ($applicationCheck['reason'] === 'cooldown'): ?>
                        <h3 style="color: var(--accent-orange); margin-bottom: 1rem;">⏰ BEWERBUNGS-COOLDOWN</h3>
                        <div style="background: rgba(217, 119, 6, 0.2); padding: 1.5rem; border-radius: 4px; border: 1px solid var(--accent-orange);">
                            <p style="color: var(--accent-orange); font-size: 1.1rem; font-weight: bold; margin-bottom: 0.5rem;">
                                <?php echo $applicationCheck['message']; ?>
                            </p>
                            <p style="color: var(--text-secondary); margin-bottom: 0.5rem;">
                                Nächste Bewerbung möglich: <strong style="color: var(--accent-orange);"><?php echo $applicationCheck['next_application']; ?> Uhr</strong>
                            </p>
                            <p style="color: var(--text-secondary);">
                                Diese Wartezeit verhindert Spam und gibt jedem eine faire Chance.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($existingApplication): ?>
                <div class="form-container" style="margin-bottom: 2rem;">
                    <h3 style="color: var(--accent-tan); margin-bottom: 1rem;">DEIN AKTUELLER STATUS</h3>
                    
                    <?php
                    $statusText = [
                        'pending' => 'Ausstehend - Deine Bewerbung wird geprüft',
                        'approved' => 'Angenommen - Willkommen!',
                        'rejected' => 'Abgelehnt - Du kannst dich erneut bewerben'
                    ];
                    $statusColor = [
                        'pending' => 'var(--accent-orange)',
                        'approved' => 'var(--accent-tan)',
                        'rejected' => 'var(--accent-red)'
                    ];
                    ?>
                    
                    <div style="background: rgba(42, 42, 42, 0.5); padding: 1.5rem; border-radius: 4px; border: 1px solid var(--border-color);">
                        <p style="color: <?php echo $statusColor[$existingApplication['status']]; ?>; font-size: 1.2rem; font-weight: bold;">
                            <?php echo $statusText[$existingApplication['status']]; ?>
                        </p>
                        <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                            Eingereicht am: <?php echo date('d.m.Y H:i', strtotime($existingApplication['submitted_at'])); ?>
                        </p>
                        
                        <?php if ($existingApplication['appointment_date']): ?>
                            <p style="color: var(--accent-tan); margin-top: 1rem; font-weight: bold;">
                                📅 Termin: <?php echo date('d.m.Y H:i', strtotime($existingApplication['appointment_date'])); ?> Uhr
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($applicationCheck['can_apply'] && (!$existingApplication || $existingApplication['status'] === 'rejected')): ?>
                <div class="form-container">
                    <?php if ($randomEnabled && $randomCount > 0): ?>
                        <div style="background: rgba(168, 153, 104, 0.1); border: 1px solid var(--accent-tan); padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px;">
                            <p style="color: var(--accent-tan); font-weight: 600; margin-bottom: 0.5rem;">
                                ℹ️ Zufällige Fragen
                            </p>
                            <p style="color: var(--text-secondary); font-size: 0.9rem;">
                                Du bekommst <?php echo $randomCount; ?> zufällig ausgewählte Fragen aus unserem Fragenpool. 
                                Beantworte alle Fragen sorgfältig.
                            </p>
                        </div>
                    <?php endif; ?>

                    <p style="color: var(--text-secondary); margin-bottom: 2rem;">
                        Beantworte die folgenden Fragen, um dich für die Whitelist zu bewerben.
                    </p>

                    <form method="POST">
                        <?php 
                        // Zugewiesene Fragen-IDs als verstecktes Feld speichern
                        $questionIds = array_column($questions, 'id');
                        ?>
                        <input type="hidden" name="assigned_questions" value="<?php echo implode(',', $questionIds); ?>">
                        
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="form-group">
                                <label style="font-weight: 600; color: var(--accent-tan);">
                                    <?php echo ($index + 1); ?>. <?php echo htmlspecialchars($question['question']); ?>
                                </label>
                                
                                <?php if ($question['question_type'] === 'text'): ?>
                                    <textarea 
                                        name="answers[<?php echo $question['id']; ?>]" 
                                        class="form-control" 
                                        required
                                        placeholder="Deine Antwort..."
                                    ></textarea>
                                <?php else: ?>
                                    <div style="margin-top: 0.5rem;">
                                        <?php foreach ($question['options'] as $option): ?>
                                            <label style="display: block; padding: 0.8rem; background: #252525; border: 1px solid #3d3d3d; margin-bottom: 0.5rem; cursor: pointer; transition: all 0.3s; border-radius: 2px;">
                                                <input 
                                                    type="radio" 
                                                    name="answers[<?php echo $question['id']; ?>]" 
                                                    value="<?php echo $option['id']; ?>" 
                                                    required
                                                    style="margin-right: 0.5rem;"
                                                >
                                                <span style="color: var(--text-primary);"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($questions)): ?>
                            <p style="color: var(--accent-orange);">
                                Derzeit sind keine Fragen verfügbar. Bitte versuche es später erneut.
                            </p>
                        <?php else: ?>
                            <button type="submit" name="submit_application" class="btn btn-primary">
                                BEWERBUNG ABSENDEN
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <footer class="footer">
            <p>&copy; 2024 <?php echo htmlspecialchars(getSetting('server_name')); ?>. Alle Rechte vorbehalten.</p>
        </footer>
    </div>

    <style>
        label:has(input[type="radio"]):hover {
            border-color: var(--accent-tan) !important;
            background: #2a2a2a !important;
        }
    </style>
</body>
</html>