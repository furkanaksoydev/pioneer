<?php
declare(strict_types=1);

/**
 * Production credentials live in config.local.php (ignored by Git) or in environment variables.
 * This file is safe to publish with the application source.
 */
function pioneerDatabaseConfig(): array {
    $localFile = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
    if (is_file($localFile)) {
        $config = require $localFile;
        if (is_array($config)) return $config;
    }
    $config = [
        'host' => getenv('PIONEER_DB_HOST') ?: 'localhost',
        'database' => getenv('PIONEER_DB_NAME') ?: '',
        'username' => getenv('PIONEER_DB_USER') ?: '',
        'password' => getenv('PIONEER_DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ];
    if ($config['database'] === '' || $config['username'] === '') throw new RuntimeException('MySQL bağlantı ayarları bulunamadı.');
    return $config;
}

function pioneerMysql(): PDO {
    $config = pioneerDatabaseConfig();
    $charset = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($config['charset'] ?? 'utf8mb4')) ?: 'utf8mb4';
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['database']};charset={$charset}", (string) $config['username'], (string) $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET NAMES {$charset} COLLATE utf8mb4_unicode_ci");
    return $pdo;
}
