<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$_SERVER['APP_ENV'] ??= 'test';
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'];

$_SERVER['APP_DEBUG'] ??= '1';
$_ENV['APP_DEBUG'] = $_SERVER['APP_DEBUG'];

$_SERVER['APP_SECRET'] ??= 'test-secret';
$_ENV['APP_SECRET'] = $_SERVER['APP_SECRET'];

$_SERVER['APP_SHARE_DIR'] ??= 'var/share';
$_ENV['APP_SHARE_DIR'] = $_SERVER['APP_SHARE_DIR'];

$_SERVER['DEFAULT_URI'] ??= 'http://localhost';
$_ENV['DEFAULT_URI'] = $_SERVER['DEFAULT_URI'];

$_SERVER['APP_RUNTIME_OPTIONS'] ??= '{"disable_dotenv":true}';
$_ENV['APP_RUNTIME_OPTIONS'] = $_SERVER['APP_RUNTIME_OPTIONS'];

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
