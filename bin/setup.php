#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI setup script for PHP CRUD API.
 * Creates system tables and initial admin user.
 */

// Find autoload
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

$autoloaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloaded = true;
        break;
    }
}

if (!$autoloaded) {
    fwrite(STDERR, colorize("Error: Could not find autoload.php. Run 'composer install' first.\n", 'red'));
    exit(1);
}

use Kunlare\PhpCrudApi\Config\Config;
use Kunlare\PhpCrudApi\Database\Connection;
use Kunlare\PhpCrudApi\Bootstrap;
use Kunlare\PhpCrudApi\Auth\JwtAuth;

// --- Helper functions ---

function colorize(string $text, string $color): string
{
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'cyan' => "\033[36m",
        'bold' => "\033[1m",
        'reset' => "\033[0m",
    ];

    $code = $colors[$color] ?? '';
    $reset = $colors['reset'];

    return "{$code}{$text}{$reset}";
}

function prompt(string $message, string $default = ''): string
{
    $defaultHint = $default !== '' ? " [{$default}]" : '';
    echo $message . $defaultHint . ': ';
    $input = trim((string) fgets(STDIN));
    return $input !== '' ? $input : $default;
}

function promptPassword(string $message): string
{
    echo $message . ': ';

    // Try to hide input on Unix systems
    if (strncasecmp(PHP_OS, 'WIN', 3) !== 0) {
        system('stty -echo 2>/dev/null');
        $password = trim((string) fgets(STDIN));
        system('stty echo 2>/dev/null');
        echo "\n";
    } else {
        $password = trim((string) fgets(STDIN));
    }

    return $password;
}

// --- Main setup ---

echo "\n";
echo colorize("  PHP CRUD API - Initial Setup", 'bold') . "\n";
echo str_repeat('=', 50) . "\n\n";

// Step 1: Check .env file
$envDir = getcwd() ?: __DIR__ . '/..';
$envFile = $envDir . '/.env';
$envExample = $envDir . '/.env.example';

if (!file_exists($envFile)) {
    if (file_exists($envExample)) {
        copy($envExample, $envFile);
        echo colorize("  [OK]", 'green') . " .env file created from .env.example\n";
    } else {
        // Check in package directory
        $packageExample = __DIR__ . '/../examples/.env.example';
        if (file_exists($packageExample)) {
            copy($packageExample, $envFile);
            echo colorize("  [OK]", 'green') . " .env file created from package example\n";
        } else {
            echo colorize("  [ERROR]", 'red') . " No .env or .env.example found.\n";
            echo "  Please create a .env file with database configuration.\n\n";
            exit(1);
        }
    }
} else {
    echo colorize("  [OK]", 'green') . " .env file found\n";
}

// Step 2: Load configuration
try {
    $config = new Config($envDir);
    echo colorize("  [OK]", 'green') . " Configuration loaded\n";
} catch (\Throwable $e) {
    echo colorize("  [ERROR]", 'red') . " Failed to load configuration: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 3: Test database connection
try {
    Connection::resetInstance();
    $db = Connection::getInstance($config);
    echo colorize("  [OK]", 'green') . " Database connection successful\n";
} catch (\Throwable $e) {
    echo colorize("  [ERROR]", 'red') . " Database connection failed: " . $e->getMessage() . "\n";
    echo "\n  Please check your .env database settings:\n";
    echo "    DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD\n\n";
    exit(1);
}

// Step 4: Create system tables
try {
    $bootstrap = new Bootstrap($db, $config);
    $bootstrap->run();
    echo colorize("  [OK]", 'green') . " System tables created\n";
} catch (\Throwable $e) {
    echo colorize("  [ERROR]", 'red') . " Failed to create tables: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 5: Check if admin already exists
$usersTable = $config->getString('USERS_TABLE', 'users');
$userCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM `{$usersTable}`");

if ($userCount > 0) {
    echo "\n" . colorize("  [INFO]", 'yellow') . " Users table already has {$userCount} user(s).\n";
    $continue = prompt('  Create another admin user? (y/n)', 'n');
    if (strtolower($continue) !== 'y') {
        echo "\n  Setup complete. No new users created.\n\n";
        exit(0);
    }
}

// Step 6: Create admin user
echo "\n" . colorize("  Create Administrator Account", 'bold') . "\n";
echo str_repeat('-', 50) . "\n\n";

$email = prompt('  Email');
while (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo colorize("  Invalid email format. Try again.\n", 'red');
    $email = prompt('  Email');
}

$username = prompt('  Username', strstr($email, '@', true) ?: 'admin');

$password = promptPassword('  Password');
$passwordConfirm = promptPassword('  Confirm password');

while ($password !== $passwordConfirm) {
    echo colorize("  Passwords do not match. Try again.\n", 'red');
    $password = promptPassword('  Password');
    $passwordConfirm = promptPassword('  Confirm password');
}

$minLength = $config->getInt('MIN_PASSWORD_LENGTH', 8);
while (strlen($password) < $minLength) {
    echo colorize("  Password must be at least {$minLength} characters.\n", 'red');
    $password = promptPassword('  Password');
    $passwordConfirm = promptPassword('  Confirm password');
    while ($password !== $passwordConfirm) {
        echo colorize("  Passwords do not match. Try again.\n", 'red');
        $password = promptPassword('  Password');
        $passwordConfirm = promptPassword('  Confirm password');
    }
}

// Insert admin
try {
    $db->execute(
        "INSERT INTO `{$usersTable}` (username, email, password_hash, role, is_active) VALUES (?, ?, ?, 'admin', 1)",
        [$username, $email, password_hash($password, PASSWORD_BCRYPT)]
    );
    $userId = (int) $db->lastInsertId();
    echo "\n" . colorize("  [OK]", 'green') . " Admin user created successfully!\n";
} catch (\Throwable $e) {
    echo "\n" . colorize("  [ERROR]", 'red') . " Failed to create admin: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 7: Generate JWT token
try {
    $jwt = new JwtAuth($db, $config);
    $token = $jwt->generateToken([
        'user_id' => $userId,
        'email' => $email,
        'role' => 'admin',
    ]);

    echo "\n" . colorize("  JWT Access Token:", 'cyan') . "\n";
    echo "  " . $token . "\n";
} catch (\Throwable $e) {
    echo "\n" . colorize("  [WARNING]", 'yellow') . " Could not generate JWT token: " . $e->getMessage() . "\n";
}

// Step 8: Summary
echo "\n" . str_repeat('=', 50) . "\n";
echo colorize("  Setup Complete!", 'bold') . "\n\n";
echo "  Next steps:\n";
echo "  1. Start the server:  php -S localhost:8000 examples/index.php\n";
echo "  2. Test login:\n";
echo "     curl -X POST http://localhost:8000/api/v1/auth/login \\\n";
echo "       -H 'Content-Type: application/json' \\\n";
echo "       -d '{\"email\":\"{$email}\",\"password\":\"YOUR_PASSWORD\"}'\n";
echo "  3. Read the docs:     README.md\n";
echo "\n" . str_repeat('=', 50) . "\n\n";
