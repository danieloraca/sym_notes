<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$_SERVER['KERNEL_CLASS'] ??= 'App\\Kernel';
$_ENV['KERNEL_CLASS'] = $_SERVER['KERNEL_CLASS'];

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

$_SERVER['MCP_TOKEN'] ??= str_repeat('a', 64);
$_ENV['MCP_TOKEN'] = $_SERVER['MCP_TOKEN'];

$_SERVER['MCP_USER_EMAIL'] ??= 'mcp@example.com';
$_ENV['MCP_USER_EMAIL'] = $_SERVER['MCP_USER_EMAIL'];

$_SERVER['MCP_ALLOWED_HOSTS'] ??= 'localhost,127.0.0.1';
$_ENV['MCP_ALLOWED_HOSTS'] = $_SERVER['MCP_ALLOWED_HOSTS'];

$_SERVER['APP_RUNTIME_OPTIONS'] ??= '{"disable_dotenv":true}';
$_ENV['APP_RUNTIME_OPTIONS'] = $_SERVER['APP_RUNTIME_OPTIONS'];

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
