<?php
declare(strict_types=1);

define('DB_HOST', getenv('MYSQL_ADDON_HOST') ?: (getenv('DB_HOST') ?: 'boyq44fnxfe8elvqtivg-mysql.services.clever-cloud.com'));
define('DB_PORT', (int)(getenv('MYSQL_ADDON_PORT') ?: (getenv('DB_PORT') ?: 3306)));
define('DB_NAME', getenv('MYSQL_ADDON_DB') ?: (getenv('DB_NAME') ?: 'boyq44fnxfe8elvqtivg'));
define('DB_USER', getenv('MYSQL_ADDON_USER') ?: (getenv('DB_USER') ?: 'uxhd9vzgcujluir2'));
define('DB_PASS', getenv('MYSQL_ADDON_PASSWORD') ?: (getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: 'STBVNBSvvCXg5pgd6ags')));
const SUPER_ADMIN_PASSWORD_HASH = '$2y$10$BB3YCCj7kXsC0PhEzfJ/JOQ94Yiz07Qaw0uS23hSCA06.sBth58ey';

function startAppSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function requireArmateurSession(): array
{
    if (empty($_SESSION['armateur'])) {
        jsonResponse(['error' => 'Authentification bateau requise.'], 401);
    }

    return $_SESSION['armateur'];
}

function requireSuperAdminSession(): void
{
    if (empty($_SESSION['super_admin'])) {
        jsonResponse(['error' => 'Accès super-admin réservé à l’Administrateur.'], 403);
    }
}
