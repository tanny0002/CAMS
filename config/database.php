<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db_driver(): string
{
    return defined('DB_DRIVER') ? DB_DRIVER : 'mysql';
}

function db_is_pgsql(): bool
{
    return db_driver() === 'pgsql';
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (db_is_pgsql()) {
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );
        if (DB_SSLMODE !== '') {
            $dsn .= ';sslmode=' . DB_SSLMODE;
        }
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

/** Check whether a table has a given column (MySQL + Postgres). */
function db_has_column(string $table, string $column): bool
{
    if (db_is_pgsql()) {
        $stmt = db()->prepare(
            'SELECT 1 FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?
             LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
    $stmt = db()->query("SHOW COLUMNS FROM `{$table}` LIKE " . db()->quote($column));
    return (bool) $stmt->fetch();
}

/** Upsert helper: ON CONFLICT for Postgres, ON DUPLICATE KEY for MySQL. */
function db_upsert_sql(string $insertSql, string $conflictTarget, string $updateSql): string
{
    if (db_is_pgsql()) {
        return $insertSql . ' ON CONFLICT (' . $conflictTarget . ') DO UPDATE SET ' . $updateSql;
    }
    return $insertSql . ' ON DUPLICATE KEY UPDATE ' . $updateSql;
}
