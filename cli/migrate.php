<?php

declare(strict_types=1);

/**
 * Applies database/schema.sql via mysqli_multi_query.
 * Usage: php cli/migrate.php [--force]
 * Without --force: refuses if `categories` already exists (protects data).
 */

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($base);
$dotenv->safeLoad();

$migrateOpts = getopt('', ['force']);
$force = array_key_exists('force', $migrateOpts);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('DB_PORT') ?: 3306);
$name = getenv('DB_NAME') ?: 'products_api';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$schemaFile = $base . '/database/schema.sql';
if (!is_readable($schemaFile)) {
    fwrite(STDERR, "Missing schema file\n");
    exit(1);
}

$sql = file_get_contents($schemaFile);
if ($sql === false || $sql === '') {
    fwrite(STDERR, "Cannot read schema\n");
    exit(1);
}

$mysqli = mysqli_init();
if (!$mysqli->real_connect($host, $user, $pass, $name, $port)) {
    fwrite(STDERR, 'Connect failed: ' . mysqli_connect_error() . PHP_EOL);
    exit(1);
}

$mysqli->set_charset('utf8mb4');

if (!$force) {
    $chk = $mysqli->query("SHOW TABLES LIKE 'categories'");
    if ($chk && $chk->num_rows > 0) {
        fwrite(STDERR, "Skip: database already initialized (`categories` exists). Use --force to DROP and reapply.\n");
        $mysqli->close();
        exit(0);
    }
}

if (!$mysqli->multi_query($sql)) {
    fwrite(STDERR, 'Schema failed: ' . $mysqli->error . PHP_EOL);
    exit(1);
}

do {
    if ($result = $mysqli->store_result()) {
        $result->free();
    }
} while ($mysqli->next_result());

if ($mysqli->errno) {
    fwrite(STDERR, 'Schema failed: ' . $mysqli->error . PHP_EOL);
    exit(1);
}

$mysqli->close();

echo "OK: schema applied to {$name}\n";
