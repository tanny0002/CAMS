<?php
/**
 * Seed 1000+ shuffled question banks per topic.
 * php -c .\.php.ini database/seed_question_banks.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/question_bank_generator.php';

$pdo = db();
echo "Seeding question banks (" . QUESTION_BANK_SIZE . "+ per topic)...\n";
$stats = cams_seed_question_banks($pdo, QUESTION_BANK_SIZE);
echo "Done. Modules: {$stats['modules']}, questions processed/added toward banks.\n";

$rows = $pdo->query(
    "SELECT m.slug, m.title, COUNT(qs.id) AS bank_questions
     FROM modules m
     JOIN quizzes q ON q.module_id = m.id AND q.quiz_type = 'bank'
     LEFT JOIN questions qs ON qs.quiz_id = q.id AND qs.is_active = 1
     GROUP BY m.id
     ORDER BY m.sort_order"
)->fetchAll(PDO::FETCH_ASSOC);

echo "\nBank sizes:\n";
foreach ($rows as $r) {
    echo "  {$r['slug']}: {$r['bank_questions']}\n";
}
