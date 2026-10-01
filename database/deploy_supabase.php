<?php
/**
 * Deploy CAMS schema + curriculum to the configured database (Supabase Postgres).
 * php -c .\.php.ini database/deploy_supabase.php
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';

echo "CAMS Supabase deploy\n";
echo 'Driver: ' . DB_DRIVER . ' host=' . DB_HOST . ':' . DB_PORT . " db=" . DB_NAME . "\n";

if (!db_is_pgsql()) {
    fwrite(STDERR, "DB_DRIVER must be pgsql for this deploy script.\n");
    exit(1);
}

$pdo = db();
echo "Connected as " . $pdo->query('select current_user')->fetchColumn() . "\n";

$sql = file_get_contents(__DIR__ . '/schema_postgres.sql');
if ($sql === false) {
    fwrite(STDERR, "Cannot read schema_postgres.sql\n");
    exit(1);
}

// Strip comments and run statement by statement
$sql = preg_replace('#/\*.*?\*/#s', '', $sql) ?? $sql;
$sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
$statements = array_filter(array_map('trim', explode(';', $sql)));
foreach ($statements as $i => $statement) {
    if ($statement === '') {
        continue;
    }
    try {
        $pdo->exec($statement);
    } catch (Throwable $e) {
        fwrite(STDERR, 'SQL failed at #' . ($i + 1) . ': ' . $e->getMessage() . "\n");
        fwrite(STDERR, substr($statement, 0, 180) . "\n");
        exit(1);
    }
}
echo "Schema applied.\n";

require_once __DIR__ . '/content_pack.php';
$pack = cams_apply_content_pack($pdo);
echo sprintf(
    "Content pack: %d lessons, %d quizzes, %d questions\n",
    $pack['lessons'],
    $pack['quizzes'],
    $pack['questions']
);

// Deep lessons if available
if (is_file(__DIR__ . '/expand_deep_lessons.php')) {
    // expand_deep_lessons is a CLI script; call pack functions if exported
    echo "Deep lessons: run expand_deep_lessons.php separately if needed.\n";
}

require_once __DIR__ . '/question_bank_generator.php';
$target = defined('QUESTION_BANK_SIZE') ? QUESTION_BANK_SIZE : 1001;
echo "Seeding question banks (target {$target}/module) — this may take several minutes...\n";
$bank = cams_seed_question_banks($pdo, $target);
echo sprintf("Question banks: %d modules seeded\n", $bank['modules']);

$mods = (int) $pdo->query('SELECT COUNT(*) FROM modules')->fetchColumn();
$lessons = (int) $pdo->query('SELECT COUNT(*) FROM lessons')->fetchColumn();
$qs = (int) $pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
echo "Totals: modules={$mods} lessons={$lessons} questions={$qs}\n";
echo "Done.\n";
