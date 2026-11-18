<?php
declare(strict_types=1);

function startAppSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    // Keep session files inside the project workspace. This also works when
    // XAMPP was installed with elevated permissions and its global tmp folder
    // is not writable by the current Windows account.
    $sessionDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($sessionDirectory)) mkdir($sessionDirectory, 0700, true);
    if (is_dir($sessionDirectory) && is_writable($sessionDirectory)) session_save_path($sessionDirectory);

    session_name('classmanager_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function signedInUser(): ?array
{
    startAppSession();
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function requireSignedInUser(): array
{
    $user = signedInUser();
    if ($user === null) jsonResponse(['error' => 'Entre no sistema para continuar.'], 401);
    return $user;
}

function requireUserRole(array $allowedRoles): array
{
    $user = requireSignedInUser();
    if (!in_array($user['perfil'] ?? '', $allowedRoles, true)) {
        jsonResponse(['error' => 'Seu perfil não tem permissão para essa ação.'], 403);
    }
    return $user;
}

function csrfToken(): string
{
    startAppSession();
    if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(): void
{
    $expected = csrfToken();
    $received = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($received === '' || !hash_equals($expected, $received)) {
        jsonResponse(['error' => 'Sessão expirada. Atualize a página e tente novamente.'], 419);
    }
}
