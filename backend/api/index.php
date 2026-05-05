<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

try {
    $pdo = db();
} catch (Throwable $error) {
    json_response(['error' => 'Database connection failed.', 'detail' => $error->getMessage()], 500);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$route = preg_replace('#^/api#', '', $uriPath);
$route = '/' . trim((string) $route, '/');
if ($route === '/') {
    $route = '/status';
}

if ($method === 'OPTIONS') {
    json_response(['ok' => true]);
}

function users_exist(PDO $pdo): bool
{
    return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
}

function ensure_passwords_match(array $data): void
{
    if (($data['password'] ?? '') !== ($data['confirm_password'] ?? '')) {
        json_response(['error' => 'Passwords do not match.'], 422);
    }

    if (strlen((string) $data['password']) < 8) {
        json_response(['error' => 'Password must be at least 8 characters.'], 422);
    }
}

try {
    if ($method === 'GET' && $route === '/setup/status') {
        $current = current_user($pdo);
        json_response([
            'requires_setup' => !users_exist($pdo),
            'user' => $current ? make_public_user($current) : null,
        ]);
    }

    if ($method === 'POST' && $route === '/setup/create-superadmin') {
        $data = read_json_body();
        require_fields($data, ['workspace_name', 'full_name', 'email', 'password', 'confirm_password']);
        ensure_passwords_match($data);

        $pdo->beginTransaction();
        if (users_exist($pdo)) {
            $pdo->rollBack();
            json_response(['error' => 'Initial setup is already complete.'], 409);
        }

        $workspaceName = trim((string) $data['workspace_name']);
        $email = strtolower(trim((string) $data['email']));
        $slug = unique_workspace_slug($pdo, $workspaceName);

        $stmt = $pdo->prepare('INSERT INTO workspaces (name, slug) VALUES (?, ?)');
        $stmt->execute([$workspaceName, $slug]);
        $workspaceId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO users (workspace_id, email, password_hash, full_name, role, status, default_locale)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $workspaceId,
            $email,
            password_hash((string) $data['password'], PASSWORD_DEFAULT),
            trim((string) $data['full_name']),
            'superadmin',
            'active',
            trim((string) ($data['default_locale'] ?? 'en')) ?: 'en',
        ]);
        $userId = (int) $pdo->lastInsertId();
        write_audit($pdo, $workspaceId, $userId, 'SUPERADMIN_CREATED', ['workspace' => $workspaceName]);
        create_session_for_user($pdo, $userId);
        $pdo->commit();

        $stmt = $pdo->prepare(
            'SELECT users.*, workspaces.name AS workspace_name
             FROM users INNER JOIN workspaces ON workspaces.id = users.workspace_id
             WHERE users.id = ?'
        );
        $stmt->execute([$userId]);
        json_response(['user' => make_public_user($stmt->fetch())], 201);
    }

    if ($method === 'POST' && $route === '/auth/login') {
        $data = read_json_body();
        require_fields($data, ['email', 'password']);
        $email = strtolower(trim((string) $data['email']));

        $stmt = $pdo->prepare(
            'SELECT users.*, workspaces.name AS workspace_name
             FROM users INNER JOIN workspaces ON workspaces.id = users.workspace_id
             WHERE users.email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify((string) $data['password'], $user['password_hash'])) {
            write_audit($pdo, $user ? (int) $user['workspace_id'] : null, $user ? (int) $user['id'] : null, 'LOGIN_FAILED', ['email' => $email]);
            json_response(['error' => 'Invalid email or password.'], 401);
        }

        if ($user['status'] !== 'active') {
            json_response(['error' => 'This account is not active.'], 403);
        }

        create_session_for_user($pdo, (int) $user['id']);
        write_audit($pdo, (int) $user['workspace_id'], (int) $user['id'], 'LOGIN_SUCCESS');
        json_response(['user' => make_public_user($user)]);
    }

    if ($method === 'POST' && $route === '/auth/register') {
        if (!users_exist($pdo)) {
            json_response(['error' => 'Create the first superadmin account before using invite registration.'], 409);
        }

        $data = read_json_body();
        require_fields($data, ['full_name', 'email', 'password', 'confirm_password', 'auth_code']);
        ensure_passwords_match($data);
        $email = strtolower(trim((string) $data['email']));
        $hash = code_hash((string) $data['auth_code']);

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT auth_codes.*, workspaces.status AS workspace_status
             FROM auth_codes INNER JOIN workspaces ON workspaces.id = auth_codes.workspace_id
             WHERE auth_codes.code_hash = ? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$hash]);
        $code = $stmt->fetch();

        if (!$code || $code['workspace_status'] !== 'active') {
            $pdo->rollBack();
            json_response(['error' => 'Invalid auth code.'], 422);
        }
        if ($code['expires_at'] !== null && strtotime($code['expires_at']) < time()) {
            $pdo->rollBack();
            json_response(['error' => 'Auth code has expired.'], 422);
        }
        if ((int) $code['used_count'] >= (int) $code['max_uses']) {
            $pdo->rollBack();
            json_response(['error' => 'Auth code has reached its maximum uses.'], 422);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (workspace_id, email, password_hash, full_name, role, status, default_locale)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $code['workspace_id'],
            $email,
            password_hash((string) $data['password'], PASSWORD_DEFAULT),
            trim((string) $data['full_name']),
            $code['role'],
            'active',
            trim((string) ($data['default_locale'] ?? 'en')) ?: 'en',
        ]);
        $userId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE auth_codes SET used_count = used_count + 1 WHERE id = ?')->execute([(int) $code['id']]);
        write_audit($pdo, (int) $code['workspace_id'], $userId, 'USER_CREATED', ['role' => $code['role'], 'source' => 'auth_code']);
        create_session_for_user($pdo, $userId);
        $pdo->commit();

        $stmt = $pdo->prepare(
            'SELECT users.*, workspaces.name AS workspace_name
             FROM users INNER JOIN workspaces ON workspaces.id = users.workspace_id
             WHERE users.id = ?'
        );
        $stmt->execute([$userId]);
        json_response(['user' => make_public_user($stmt->fetch())], 201);
    }

    if ($method === 'POST' && $route === '/auth/logout') {
        $token = $_COOKIE[SESSION_COOKIE] ?? '';
        if ($token !== '') {
            $pdo->prepare('DELETE FROM sessions WHERE token_hash = ?')->execute([token_hash($token)]);
        }
        clear_session_cookie();
        json_response(['ok' => true]);
    }

    if ($method === 'GET' && $route === '/auth/me') {
        $user = current_user($pdo);
        json_response(['user' => $user ? make_public_user($user) : null]);
    }

    if ($method === 'GET' && $route === '/users') {
        $user = require_admin_user($pdo);
        if ($user['role'] === 'superadmin') {
            $stmt = $pdo->query(
                'SELECT users.*, workspaces.name AS workspace_name
                 FROM users INNER JOIN workspaces ON workspaces.id = users.workspace_id
                 ORDER BY users.created_at DESC'
            );
        } else {
            $stmt = $pdo->prepare(
                'SELECT users.*, workspaces.name AS workspace_name
                 FROM users INNER JOIN workspaces ON workspaces.id = users.workspace_id
                 WHERE users.workspace_id = ? ORDER BY users.created_at DESC'
            );
            $stmt->execute([(int) $user['workspace_id']]);
        }
        json_response(['users' => array_map('make_public_user', $stmt->fetchAll())]);
    }

    if ($method === 'POST' && $route === '/users/invite-code') {
        $user = require_admin_user($pdo);
        $data = read_json_body();
        require_fields($data, ['role', 'max_uses']);
        $role = (string) $data['role'];
        if (!in_array($role, INVITE_ROLES, true)) {
            json_response(['error' => 'Invite role must be admin, supervisor, or agent.'], 422);
        }
        if ($user['role'] !== 'superadmin' && $role === 'admin') {
            json_response(['error' => 'Only superadmins can create admin invite codes.'], 403);
        }

        $workspaceId = (int) ($data['workspace_id'] ?? $user['workspace_id']);
        if ($user['role'] !== 'superadmin' && $workspaceId !== (int) $user['workspace_id']) {
            json_response(['error' => 'Admins can only create invite codes for their workspace.'], 403);
        }

        $plainCode = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($plainCode === '') {
            $plainCode = 'NS-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper($role);
        }
        $maxUses = max(1, (int) $data['max_uses']);
        $expiresAt = trim((string) ($data['expires_at'] ?? '')) ?: null;
        if ($expiresAt !== null) {
            $expiresAt = str_replace('T', ' ', $expiresAt);
            if (strlen($expiresAt) === 16) {
                $expiresAt .= ':00';
            }
        }
        $label = trim((string) ($data['label'] ?? $plainCode));

        $stmt = $pdo->prepare(
            'INSERT INTO auth_codes (workspace_id, code_hash, label, role, max_uses, expires_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$workspaceId, code_hash($plainCode), $label, $role, $maxUses, $expiresAt, (int) $user['id']]);
        write_audit($pdo, $workspaceId, (int) $user['id'], 'AUTH_CODE_CREATED', ['role' => $role, 'max_uses' => $maxUses]);

        json_response(['code' => $plainCode, 'role' => $role, 'max_uses' => $maxUses, 'expires_at' => $expiresAt], 201);
    }

    if ($method === 'PATCH' && preg_match('#^/users/(\d+)/(role|status)$#', $route, $matches)) {
        $actor = require_admin_user($pdo);
        $targetId = (int) $matches[1];
        $field = $matches[2];
        $data = read_json_body();
        require_fields($data, [$field]);
        $value = (string) $data[$field];

        if ($field === 'role' && !in_array($value, ROLES, true)) {
            json_response(['error' => 'Invalid role.'], 422);
        }
        if ($field === 'status' && !in_array($value, STATUSES, true)) {
            json_response(['error' => 'Invalid status.'], 422);
        }
        if ($actor['role'] !== 'superadmin' && ($field === 'role' || $targetId === (int) $actor['id'])) {
            json_response(['error' => 'Only superadmins can change roles or modify their own status.'], 403);
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if (!$target) {
            json_response(['error' => 'User not found.'], 404);
        }
        if ($actor['role'] !== 'superadmin' && (int) $target['workspace_id'] !== (int) $actor['workspace_id']) {
            json_response(['error' => 'Cannot manage users outside your workspace.'], 403);
        }

        $stmt = $pdo->prepare("UPDATE users SET {$field} = ? WHERE id = ?");
        $stmt->execute([$value, $targetId]);
        write_audit($pdo, (int) $target['workspace_id'], (int) $actor['id'], strtoupper("USER_{$field}_CHANGED"), ['target_user_id' => $targetId, 'value' => $value]);
        json_response(['ok' => true]);
    }

    json_response(['error' => 'Route not found.'], 404);
} catch (PDOException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $status = $error->getCode() === '23000' ? 409 : 500;
    json_response(['error' => $status === 409 ? 'A unique value already exists.' : 'Database error.', 'detail' => $error->getMessage()], $status);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['error' => 'Server error.', 'detail' => $error->getMessage()], 500);
}
