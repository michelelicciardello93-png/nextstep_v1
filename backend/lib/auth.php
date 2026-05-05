<?php

declare(strict_types=1);

const SESSION_COOKIE = 'nextstep_session';
const SESSION_DAYS = 14;
const ROLES = ['superadmin', 'admin', 'supervisor', 'agent'];
const INVITE_ROLES = ['admin', 'supervisor', 'agent'];
const STATUSES = ['active', 'pending', 'disabled'];

function slugify(string $name): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
    return $slug !== '' ? $slug : 'workspace';
}

function unique_workspace_slug(PDO $pdo, string $name): string
{
    $base = slugify($name);
    $slug = $base;
    $suffix = 2;

    $stmt = $pdo->prepare('SELECT id FROM workspaces WHERE slug = ? LIMIT 1');
    while (true) {
        $stmt->execute([$slug]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $suffix;
        $suffix++;
    }
}

function token_hash(string $token): string
{
    return hash('sha256', $token);
}

function code_hash(string $code): string
{
    return hash('sha256', strtoupper(trim($code)));
}

function make_public_user(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'workspace_id' => (int) $user['workspace_id'],
        'workspace_name' => $user['workspace_name'] ?? null,
        'email' => $user['email'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
        'status' => $user['status'],
        'default_locale' => $user['default_locale'],
        'created_at' => $user['created_at'] ?? null,
        'updated_at' => $user['updated_at'] ?? null,
    ];
}

function create_session_for_user(PDO $pdo, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $expires = (new DateTimeImmutable('+' . SESSION_DAYS . ' days'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare('INSERT INTO sessions (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$userId, token_hash($token), $expires]);

    setcookie(SESSION_COOKIE, $token, [
        'expires' => time() + (SESSION_DAYS * 24 * 60 * 60),
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return $token;
}

function clear_session_cookie(): void
{
    setcookie(SESSION_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function current_user(PDO $pdo): ?array
{
    $token = $_COOKIE[SESSION_COOKIE] ?? '';
    if ($token === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT users.*, workspaces.name AS workspace_name, sessions.id AS session_id
         FROM sessions
         INNER JOIN users ON users.id = sessions.user_id
         INNER JOIN workspaces ON workspaces.id = users.workspace_id
         WHERE sessions.token_hash = ? AND sessions.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([token_hash($token)]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        return null;
    }

    return $user;
}

function require_user(PDO $pdo): array
{
    $user = current_user($pdo);
    if (!$user) {
        json_response(['error' => 'Authentication required.'], 401);
    }

    return $user;
}

function require_admin_user(PDO $pdo): array
{
    $user = require_user($pdo);
    if (!in_array($user['role'], ['superadmin', 'admin'], true)) {
        json_response(['error' => 'Admin access required.'], 403);
    }

    return $user;
}

function write_audit(PDO $pdo, ?int $workspaceId, ?int $userId, string $action, array $details = []): void
{
    $stmt = $pdo->prepare('INSERT INTO audit_logs (workspace_id, user_id, action, details_json) VALUES (?, ?, ?, ?)');
    $stmt->execute([$workspaceId, $userId, $action, json_encode($details, JSON_UNESCAPED_SLASHES)]);
}
