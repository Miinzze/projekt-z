<?php
require_once '../config.php';
requireLogin();
requirePermission('manage_questions');

$message = '';
$messageType = '';

// Frage erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_question'])) {
    $questionType = $_POST['question_type'];
    
    $stmt = $pdo->prepare("
        INSERT INTO whitelist_questions (question, question_type, correct_answer, order_num) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $_POST['question'],
        $questionType,
        $questionType === 'text' ? $_POST['correct_answer'] : null,
        $_POST['order_num']
    ]);
    $questionId = $pdo->lastInsertId();
    
    // Multiple-Choice-Optionen speichern
    if ($questionType === 'multiple_choice' && isset($_POST['options'])) {
        foreach ($_POST['options'] as $index => $optionText) {
            if (!empty(trim($optionText))) {
                $isCorrect = (isset($_POST['correct_option']) && $_POST['correct_option'] == $index);
                
                $stmt = $pdo->prepare("
                    INSERT INTO whitelist_question_options (question_id, option_text, is_correct, option_order) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$questionId, $optionText, $isCorrect ? 1 : 0, $index]);
            }
        }
    }
    
    $message = 'Frage erfolgreich erstellt!';
    $messageType = 'success';
}

// Frage bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_question'])) {
    $questionType = $_POST['question_type'];
    
    $stmt = $pdo->prepare("
        UPDATE whitelist_questions 
        SET question = ?, question_type = ?, correct_answer = ?, order_num = ?, is_active = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['question'],
        $questionType,
        $questionType === 'text' ? $_POST['correct_answer'] : null,
        $_POST['order_num'],
        isset($_POST['is_active']) ? 1 : 0,
        $_POST['question_id']
    ]);
    
    // Multiple-Choice-Optionen aktualisieren
    if ($questionType === 'multiple_choice') {
        // Alte Optionen löschen
        $stmt = $pdo->prepare("DELETE FROM whitelist_question_options WHERE question_id = ?");
        $stmt->execute([$_POST['question_id']]);
        
        // Neue Optionen einfügen
        if (isset($_POST['options'])) {
            foreach ($_POST['options'] as $index => $optionText) {
                if (!empty(trim($optionText))) {
                    $isCorrect = (isset($_POST['correct_option']) && $_POST['correct_option'] == $index);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO whitelist_question_options (question_id, option_text, is_correct, option_order) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$_POST['question_id'], $optionText, $isCorrect ? 1 : 0, $index]);
                }
            }
        }
    }
    
    $message = 'Frage erfolgreich aktualisiert!';
    $messageType = 'success';
}

// Frage löschen
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM whitelist_questions WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $message = 'Frage erfolgreich gelöscht!';
    $messageType = 'success';
}

// Alle Fragen mit Optionen abrufen
$stmt = $pdo->query("SELECT * FROM whitelist_questions ORDER BY order_num ASC, id ASC");
$questions = $stmt->fetchAll();

// Optionen für jede Frage laden
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
    <title>Fragen verwalten - Admin</title>
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
                <a href="applications.php" class="admin-nav-link">📝 Whitelist Anträge</a>
                <a href="questions.php" class="admin-nav-link active">❓ Fragen verwalten</a>
                <a href="../index.php" class="admin-nav-link">🏠 Zur Hauptseite</a>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header">
                <h1>Whitelist Fragen</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="admin-content">
                <!-- Neue Frage erstellen -->
                <div class="card">
                    <h2>Neue Frage erstellen</h2>
                    <form method="POST" id="createQuestionForm">
                        <div class="form-group">
                            <label>Fragentyp *</label>
                            <select name="question_type" class="form-control" onchange="toggleQuestionType(this.value, 'create')" required>
                                <option value="text">Text-Antwort</option>
                                <option value="multiple_choice">Multiple Choice</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Frage *</label>
                            <textarea name="question" class="form-control" required placeholder="z.B. Was ist Roleplay?"></textarea>
                        </div>

                        <!-- Text-Antwort Bereich -->
                        <div id="create-text-answer" class="question-type-section">
                            <div class="form-group">
                                <label>Korrekte Antwort (optional)</label>
                                <input type="text" name="correct_answer" class="form-control" placeholder="Falls es eine spezifische Antwort gibt">
                            </div>
                        </div>

                        <!-- Multiple-Choice Bereich -->
                        <div id="create-mc-options" class="question-type-section" style="display: none;">
                            <div class="form-group">
                                <label>Antwortmöglichkeiten *</label>
                                <div id="create-options-container">
                                    <div class="mc-option">
                                        <input type="radio" name="correct_option" value="0" required>
                                        <input type="text" name="options[]" class="form-control" placeholder="Option 1" style="flex: 1; margin-left: 0.5rem;">
                                    </div>
                                    <div class="mc-option">
                                        <input type="radio" name="correct_option" value="1">
                                        <input type="text" name="options[]" class="form-control" placeholder="Option 2" style="flex: 1; margin-left: 0.5rem;">
                                    </div>
                                    <div class="mc-option">
                                        <input type="radio" name="correct_option" value="2">
                                        <input type="text" name="options[]" class="form-control" placeholder="Option 3" style="flex: 1; margin-left: 0.5rem;">
                                    </div>
                                    <div class="mc-option">
                                        <input type="radio" name="correct_option" value="3">
                                        <input type="text" name="options[]" class="form-control" placeholder="Option 4" style="flex: 1; margin-left: 0.5rem;">
                                    </div>
                                </div>
                                <small style="color: var(--text-secondary);">Wähle die richtige Antwort aus</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Reihenfolge</label>
                            <input type="number" name="order_num" class="form-control" value="0">
                        </div>

                        <button type="submit" name="create_question" class="btn btn-primary">
                            Frage erstellen
                        </button>
                    </form>
                </div>

                <!-- Vorhandene Fragen -->
                <div class="card">
                    <h2>Vorhandene Fragen</h2>
                    
                    <?php if (empty($questions)): ?>
                        <p style="color: var(--text-secondary);">Keine Fragen vorhanden.</p>
                    <?php else: ?>
                        <?php foreach ($questions as $q): ?>
                            <div class="answer-review" style="margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: between; align-items: start; gap: 1rem;">
                                    <div style="flex: 1;">
                                        <h4><?php echo htmlspecialchars($q['question']); ?></h4>
                                        
                                        <?php if ($q['question_type'] === 'text'): ?>
                                            <?php if ($q['correct_answer']): ?>
                                                <p style="color: var(--accent-tan);">
                                                    ✓ Erwartete Antwort: <?php echo htmlspecialchars($q['correct_answer']); ?>
                                                </p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p style="color: var(--accent-tan); font-weight: 600;">Multiple Choice:</p>
                                            <ul style="margin-left: 1.5rem; color: var(--text-secondary);">
                                                <?php foreach ($q['options'] as $opt): ?>
                                                    <li style="margin: 0.3rem 0;">
                                                        <?php echo htmlspecialchars($opt['option_text']); ?>
                                                        <?php if ($opt['is_correct']): ?>
                                                            <span style="color: var(--accent-tan);">✓ (Richtig)</span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        
                                        <p style="color: var(--text-secondary); font-size: 0.9rem;">
                                            Typ: <?php echo $q['question_type'] === 'text' ? 'Text' : 'Multiple Choice'; ?> | 
                                            Reihenfolge: <?php echo $q['order_num']; ?> | 
                                            Status: <?php echo $q['is_active'] ? '<span style="color: var(--accent-tan);">Aktiv</span>' : '<span style="color: var(--accent-red);">Inaktiv</span>'; ?>
                                        </p>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button onclick="editQuestion(<?php echo htmlspecialchars(json_encode($q)); ?>)" class="btn btn-secondary btn-sm">
                                            Bearbeiten
                                        </button>
                                        <a href="?delete=<?php echo $q['id']; ?>" 
                                           onclick="return confirm('Wirklich löschen?')" 
                                           class="btn btn-danger btn-sm">
                                            Löschen
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
            <h3 style="color: var(--accent-tan); margin-bottom: 1rem;">Frage bearbeiten</h3>
            
            <form method="POST" id="editQuestionForm">
                <input type="hidden" name="question_id" id="edit_question_id">
                
                <div class="form-group">
                    <label>Fragentyp</label>
                    <select name="question_type" id="edit_question_type" class="form-control" onchange="toggleQuestionType(this.value, 'edit')" required>
                        <option value="text">Text-Antwort</option>
                        <option value="multiple_choice">Multiple Choice</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Frage</label>
                    <textarea name="question" id="edit_question" class="form-control" required></textarea>
                </div>

                <!-- Text-Antwort Bereich -->
                <div id="edit-text-answer" class="question-type-section">
                    <div class="form-group">
                        <label>Korrekte Antwort</label>
                        <input type="text" name="correct_answer" id="edit_correct_answer" class="form-control">
                    </div>
                </div>

                <!-- Multiple-Choice Bereich -->
                <div id="edit-mc-options" class="question-type-section" style="display: none;">
                    <div class="form-group">
                        <label>Antwortmöglichkeiten</label>
                        <div id="edit-options-container">
                            <div class="mc-option">
                                <input type="radio" name="correct_option" value="0">
                                <input type="text" name="options[]" class="form-control" placeholder="Option 1" style="flex: 1; margin-left: 0.5rem;">
                            </div>
                            <div class="mc-option">
                                <input type="radio" name="correct_option" value="1">
                                <input type="text" name="options[]" class="form-control" placeholder="Option 2" style="flex: 1; margin-left: 0.5rem;">
                            </div>
                            <div class="mc-option">
                                <input type="radio" name="correct_option" value="2">
                                <input type="text" name="options[]" class="form-control" placeholder="Option 3" style="flex: 1; margin-left: 0.5rem;">
                            </div>
                            <div class="mc-option">
                                <input type="radio" name="correct_option" value="3">
                                <input type="text" name="options[]" class="form-control" placeholder="Option 4" style="flex: 1; margin-left: 0.5rem;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Reihenfolge</label>
                    <input type="number" name="order_num" id="edit_order_num" class="form-control">
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                        Aktiv
                    </label>
                </div>

                <button type="submit" name="edit_question" class="btn btn-primary">
                    Speichern
                </button>
            </form>
        </div>
    </div>

    <style>
        .mc-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .question-type-section {
            margin-top: 1rem;
        }
    </style>

    <script>
        function toggleQuestionType(type, context) {
            const textSection = document.getElementById(context + '-text-answer');
            const mcSection = document.getElementById(context + '-mc-options');
            
            if (type === 'text') {
                textSection.style.display = 'block';
                mcSection.style.display = 'none';
            } else {
                textSection.style.display = 'none';
                mcSection.style.display = 'block';
            }
        }

        function editQuestion(question) {
            document.getElementById('edit_question_id').value = question.id;
            document.getElementById('edit_question').value = question.question;
            document.getElementById('edit_question_type').value = question.question_type;
            document.getElementById('edit_order_num').value = question.order_num;
            document.getElementById('edit_is_active').checked = question.is_active == 1;
            
            toggleQuestionType(question.question_type, 'edit');
            
            if (question.question_type === 'text') {
                document.getElementById('edit_correct_answer').value = question.correct_answer || '';
            } else {
                // Multiple-Choice-Optionen füllen
                const container = document.getElementById('edit-options-container');
                const inputs = container.querySelectorAll('input[type="text"]');
                const radios = container.querySelectorAll('input[type="radio"]');
                
                // Felder zurücksetzen
                inputs.forEach(input => input.value = '');
                radios.forEach(radio => radio.checked = false);
                
                // Optionen einfügen
                if (question.options) {
                    question.options.forEach((opt, index) => {
                        if (inputs[index]) {
                            inputs[index].value = opt.option_text;
                        }
                        if (opt.is_correct && radios[index]) {
                            radios[index].checked = true;
                        }
                    });
                }
            }
            
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