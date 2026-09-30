<?php
/**
 * Apply expanded curriculum to the live database (idempotent).
 * Run: php -c .\.php.ini database/expand_content.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/content_pack.php';

$pdo = db();
$counts = cams_apply_content_pack($pdo);

echo "Content pack applied.\n";
echo "Modules touched: {$counts['modules']}\n";
echo "Lessons touched: {$counts['lessons']}\n";
echo "Quizzes touched: {$counts['quizzes']}\n";
echo "Questions written: {$counts['questions']}\n";
echo "Tips touched: {$counts['tips']}\n";

$stats = $pdo->query(
    'SELECT
        (SELECT COUNT(*) FROM modules WHERE is_active = 1) AS modules,
        (SELECT COUNT(*) FROM lessons WHERE is_active = 1) AS lessons,
        (SELECT COUNT(*) FROM quizzes WHERE is_active = 1) AS quizzes,
        (SELECT COUNT(*) FROM questions WHERE is_active = 1) AS questions,
        (SELECT COUNT(*) FROM security_tips WHERE is_active = 1) AS tips'
)->fetch(PDO::FETCH_ASSOC);

echo "\nDatabase totals (active):\n";
foreach ($stats as $k => $v) {
    echo "  {$k}: {$v}\n";
}
