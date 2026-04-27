<?php
// config/app.php

define('APP_NAME',  'LakBai Salakay Merch');
define('APP_URL',   'http://localhost/pos-system');
define('BASE_PATH', dirname(__DIR__));

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/classes/Auth.php';
require_once BASE_PATH . '/classes/User.php';
require_once BASE_PATH . '/classes/Product.php';
require_once BASE_PATH . '/classes/Sale.php';
require_once BASE_PATH . '/classes/Supplier.php';
require_once BASE_PATH . '/classes/StockIn.php';
require_once BASE_PATH . '/classes/Customer.php';
require_once BASE_PATH . '/classes/OnlineOrder.php';

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        redirect(APP_URL . '/login.php');
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        redirect(APP_URL . '/modules/dashboard/index.php');
    }
}

function flash(string $key, string $msg = ''): string {
    if ($msg) { $_SESSION['flash'][$key] = $msg; return ''; }
    $out = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $out;
}

// Helper: get logged-in staff full name
function staffFullName(): string {
    return trim(($_SESSION['SFN'] ?? '') . ' ' . ($_SESSION['SLN'] ?? '')) ?: 'User';
}
