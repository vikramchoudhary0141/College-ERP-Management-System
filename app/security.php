<?php
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(array $args = []): string {
    global $config;
    return rtrim($config['app']['base_url'] ?? '', '/') . '/index.php' . ($args ? '?' . http_build_query($args) : '');
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(419); exit('Your session expired. Reload the page and try again.');
    }
}
function flash(string $kind, string $message): void { $_SESSION['flash'] = [$kind, $message]; }
function redirect(array $args = []): void { header('Location: ' . url($args)); exit; }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): array {
    if (!current_user()) redirect(['action' => 'login']);
    $q=db()->prepare('SELECT active,role,university_id,college_id FROM users WHERE id=?');
    $q->execute([(int)current_user()['id']]);$fresh=$q->fetch();
    if(!$fresh || !(int)$fresh['active']) {
        unset($_SESSION['user']);flash('warning','Your account is inactive. Contact an administrator.');redirect(['action'=>'login']);
    }
    $_SESSION['user']['role']=$fresh['role'];
    $_SESSION['user']['university_id']=$fresh['university_id']?(int)$fresh['university_id']:null;
    $_SESSION['user']['college_id']=$fresh['college_id']?(int)$fresh['college_id']:null;
    return current_user();
}
function is_admin(): bool { return in_array(current_user()['role'] ?? '', ['super_admin','university_admin','college_admin'], true); }
function require_role(array $roles): void {
    if (!in_array(current_user()['role'] ?? '', $roles, true)) { http_response_code(403); exit('Access denied.'); }
}
function role_name(string $role): string {
    return ucwords(str_replace('_', ' ', $role));
}
function scope_sql(string $alias = ''): array {
    $u = require_login();
    $p = $alias !== '' ? $alias . '.' : '';
    if ($u['role'] === 'super_admin') return ['', []];
    if ($u['role'] === 'university_admin') return [" WHERE {$p}university_id = ?", [(int)$u['university_id']]];
    if ($u['role'] === 'parent') {
        return [" WHERE {$p}student_id IN (SELECT student_id FROM parent_students WHERE parent_user_id = ?)", [(int)$u['id']]];
    }
    if ($u['role'] === 'student') {
        return [" WHERE {$p}student_id = ?", [(int)$u['student_id']]];
    }
    return [" WHERE {$p}college_id = ?", [(int)$u['college_id']]];
}
function allowed_module(string $module): bool {
    $role = current_user()['role'] ?? '';
    $m = modules()[$module] ?? null;
    if (!$m) return false;
    return in_array($role, $m['roles'], true);
}
function user_college_id(): ?int { return isset(current_user()['college_id']) ? (int)current_user()['college_id'] : null; }
function user_university_id(): ?int { return isset(current_user()['university_id']) ? (int)current_user()['university_id'] : null; }
function require_module(string $module): array {
    require_login();
    if (!allowed_module($module)) { http_response_code(403); exit('You do not have access to this module.'); }
    return modules()[$module];
}
