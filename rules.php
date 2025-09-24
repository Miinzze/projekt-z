<?php
require_once 'config.php';

// Alle aktiven Regeln nach Kategorien abrufen
$stmt = $pdo->query("
    SELECT * FROM rules 
    WHERE is_active = 1 
    ORDER BY category ASC, order_num ASC, id ASC
");
$rules = $stmt->fetchAll();

// Nach Kategorien gruppieren
$rulesByCategory = [];
foreach ($rules as $rule) {
    $rulesByCategory[$rule['category']][] = $rule;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regelwerk - <?php echo htmlspecialchars(getSetting('server_name')); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .rules-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .rules-nav {
            background: rgba(42, 42, 42, 0.9);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            position: sticky;
            top: 80px;
            z-index: 50;
        }
        
        .rules-nav h3 {
            color: var(--accent-green);
            margin-bottom: 1rem;
        }
        
        .rules-nav-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .rules-nav-link {
            color: var(--text-primary);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .rules-nav-link:hover {
            border-color: var(--accent-green);
            color: var(--accent-green);
        }
        
        .rule-category {
            margin-bottom: 3rem;
        }
        
        .category-header {
            background: rgba(74, 140, 74, 0.1);
            border-left: 4px solid var(--accent-green);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .category-header h2 {
            color: var(--accent-green);
            font-size: 1.8rem;
            margin: 0;
        }
        
        .rule-item {
            background: rgba(42, 42, 42, 0.9);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        
        .rule-item:hover {
            border-color: var(--accent-green);
            transform: translateX(5px);
        }
        
        .rule-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .rule-number {
            background: var(--accent-green);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .rule-title {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .rule-content {
            color: var(--text-secondary);
            line-height: 1.8;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-content">
                <div class="logo">
                    <img src="images/skull-icon.png" alt="Logo" class="logo-img">
                    <h1><?php echo htmlspecialchars(getSetting('server_name')); ?></h1>
                </div>
                <nav class="nav">
                    <a href="index.php" class="nav-link">HOME</a>
                    <a href="rules.php" class="nav-link active">REGELWERK</a>
                    <a href="features.php" class="nav-link">FEATURES</a>
                    <?php if (isLoggedIn()): ?>
                        <a href="whitelist.php" class="nav-link">WHITELIST</a>
                        <?php if (hasPermission('manage_news')): ?>
                            <a href="admin/dashboard.php" class="nav-link">ADMIN</a>
                        <?php endif; ?>
                        <a href="logout.php" class="nav-link">LOGOUT</a>
                    <?php else: ?>
                        <a href="login.php" class="nav-link">LOGIN</a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>

        <div class="rules-container">
            <h1 class="section-title">REGELWERK</h1>

            <?php if (!empty($rulesByCategory)): ?>
                <!-- Navigation -->
                <div class="rules-nav">
                    <h3>Kategorien</h3>
                    <div class="rules-nav-list">
                        <?php foreach (array_keys($rulesByCategory) as $category): ?>
                            <a href="#<?php echo htmlspecialchars(str_replace(' ', '-', strtolower($category))); ?>" class="rules-nav-link">
                                <?php echo htmlspecialchars($category); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Regeln -->
                <?php foreach ($rulesByCategory as $category => $categoryRules): ?>
                    <div class="rule-category" id="<?php echo htmlspecialchars(str_replace(' ', '-', strtolower($category))); ?>">
                        <div class="category-header">
                            <h2><?php echo htmlspecialchars($category); ?></h2>
                        </div>

                        <?php foreach ($categoryRules as $rule): ?>
                            <div class="rule-item">
                                <div class="rule-header">
                                    <?php if ($rule['rule_number']): ?>
                                        <span class="rule-number"><?php echo htmlspecialchars($rule['rule_number']); ?></span>
                                    <?php endif; ?>
                                    <h3 class="rule-title"><?php echo htmlspecialchars($rule['title']); ?></h3>
                                </div>
                                <div class="rule-content">
                                    <?php echo nl2br(htmlspecialchars($rule['content'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card">
                    <p style="color: var(--text-secondary); text-align: center;">
                        Das Regelwerk wird derzeit erstellt. Bitte schaue später wieder vorbei.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <footer class="footer">
            <p>&copy; 2024 <?php echo htmlspecialchars(getSetting('server_name')); ?>. Alle Rechte vorbehalten.</p>
        </footer>
    </div>

    <script>
        // Smooth Scroll für Navigation
        document.querySelectorAll('.rules-nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>