<?php

// Force SCRIPT_NAME and SCRIPT_FILENAME so Laravel generates URLs correctly without /api prefix
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';

// Prepare writable paths in /tmp for Vercel Serverless environment
if (isset($_ENV['VERCEL']) || getenv('VERCEL')) {
    $dirs = [
        '/tmp/storage',
        '/tmp/storage/app',
        '/tmp/storage/app/public',
        '/tmp/storage/framework',
        '/tmp/storage/framework/cache',
        '/tmp/storage/framework/cache/data',
        '/tmp/storage/framework/sessions',
        '/tmp/storage/framework/views',
        '/tmp/storage/logs',
        '/tmp/bootstrap',
        '/tmp/bootstrap/cache',
    ];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    // Handle SQLite database in /tmp if sqlite driver is used
    $dbConnection = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?? 'sqlite';
    if ($dbConnection === 'sqlite') {
        $tmpDb = '/tmp/database.sqlite';
        $bundledDb = __DIR__ . '/../database/database.sqlite';

        if (!file_exists($tmpDb)) {
            if (file_exists($bundledDb)) {
                copy($bundledDb, $tmpDb);
            } else {
                touch($tmpDb);
            }
        }

        if (!isset($_ENV['DB_DATABASE']) && !getenv('DB_DATABASE')) {
            $_ENV['DB_DATABASE'] = $tmpDb;
            putenv("DB_DATABASE={$tmpDb}");
        }
    }
}

// Forward to public/index.php
require __DIR__ . '/../public/index.php';
