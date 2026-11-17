<?php
/**
 * Tamper-evident audit logging (hash chain), security events and
 * authentication events. Passwords, tokens and session secrets are never logged.
 */
declare(strict_types=1);

function audit_log(string $action, string $module, ?int $recordId = null, string $description = '', string $outcome = 'success', $changes = null): void
{
    try {
        $user = $GLOBALS['CNPL_USER'] ?? null;
        $row = [
            'user_id' => $user['id'] ?? null,
            'actor_name' => $user ? mb_substr($user['full_name'] . ' (' . $user['username'] . ')', 0, 150) : (IS_CLI ? 'system (cli)' : 'anonymous'),
            'action' => mb_substr($action, 0, 60),
            'module' => mb_substr($module, 0, 60),
            'record_id' => $recordId,
            'description' => mb_substr($description, 0, 500),
            'outcome' => $outcome,
            'ip_address' => IS_CLI ? null : client_ip(),
            'user_agent' => IS_CLI ? null : user_agent(),
            'changes' => $changes === null ? null : (is_string($changes) ? $changes : json_encode($changes, JSON_UNESCAPED_UNICODE)),
            'created_at' => now(),
        ];
        q("SELECT GET_LOCK('cnpl_audit_chain', 5)");
        try {
            $prev = (string) (q_val('SELECT entry_hash FROM audit_logs ORDER BY id DESC LIMIT 1') ?? str_repeat('0', 64));
            $row['prev_hash'] = $prev;
            $row['entry_hash'] = audit_hash($row);
            q_insert('audit_logs', $row);
        } finally {
            q("SELECT RELEASE_LOCK('cnpl_audit_chain')");
        }
    } catch (Throwable $e) {
        @file_put_contents(STORAGE_PATH . '/logs/audit-fallback.log', '[' . now() . "] {$action} {$module} {$recordId} {$description}: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
    }
}

function audit_hash(array $r): string
{
    $canon = implode('|', [
        $r['prev_hash'], (string) ($r['user_id'] ?? ''), $r['action'], $r['module'], (string) ($r['record_id'] ?? ''),
        (string) ($r['description'] ?? ''), $r['outcome'], (string) ($r['ip_address'] ?? ''), (string) ($r['changes'] ?? ''), $r['created_at'],
    ]);
    return hash('sha256', $canon);
}

/** Verify the hash chain. Returns [checked, firstBrokenId|null]. */
function audit_verify_chain(int $limit = 5000): array
{
    $rows = q_all('SELECT * FROM audit_logs ORDER BY id DESC LIMIT ' . (int) $limit);
    $rows = array_reverse($rows);
    $checked = 0;
    $prevHash = null;
    foreach ($rows as $r) {
        if ($prevHash !== null && $r['prev_hash'] !== $prevHash) {
            return [$checked, (int) $r['id']];
        }
        if (audit_hash($r) !== $r['entry_hash']) {
            return [$checked, (int) $r['id']];
        }
        $prevHash = $r['entry_hash'];
        $checked++;
    }
    return [$checked, null];
}

function security_event(string $type, string $severity, string $description, ?int $userId = null): void
{
    try {
        q_insert('security_events', [
            'event_type' => mb_substr($type, 0, 60), 'severity' => $severity,
            'user_id' => $userId ?? ($GLOBALS['CNPL_USER']['id'] ?? null),
            'ip_address' => IS_CLI ? null : client_ip(), 'description' => mb_substr($description, 0, 500),
            'event_status' => in_array($severity, ['info', 'low'], true) ? 'reviewed' : 'open', 'created_at' => now(),
        ]);
    } catch (Throwable $e) {
        // Logging must never break the request; keep a file copy instead.
        @file_put_contents(STORAGE_PATH . '/logs/security-fallback.log', sprintf("[%s] %s %s %s (db error: %s)\n", date('Y-m-d H:i:s'), $type, $severity,
            str_replace(["\r", "\n"], ' ', mb_substr($description, 0, 500)), get_class($e)), FILE_APPEND | LOCK_EX);
    }
}

function auth_event(string $type, ?int $userId, ?string $identifier = null, string $details = ''): void
{
    try {
        q_insert('authentication_events', [
            'user_id' => $userId, 'identifier' => $identifier !== null ? mb_substr($identifier, 0, 191) : null,
            'event_type' => $type, 'ip_address' => IS_CLI ? null : client_ip(), 'user_agent' => IS_CLI ? null : user_agent(),
            'details' => mb_substr($details, 0, 255), 'created_at' => now(),
        ]);
    } catch (Throwable $e) {
    }
}

/** Compute a field-level diff for revisions and audit entries. */
function diff_changes(array $old, array $new, array $redact = []): array
{
    $out = [];
    foreach ($new as $k => $v) {
        if (in_array($k, ['updated_at', 'updated_by', 'created_at', 'created_by'], true)) {
            continue;
        }
        $ov = $old[$k] ?? null;
        if ((string) $ov !== (string) $v) {
            $out[$k] = in_array($k, $redact, true) ? ['from' => $ov === null ? null : '[protected]', 'to' => $v === null ? null : '[protected]']
                : ['from' => $ov, 'to' => $v];
        }
    }
    return $out;
}

function revision_log(string $module, int $recordId, string $action, ?array $changes): void
{
    q_insert('record_revisions', [
        'module' => $module, 'record_id' => $recordId, 'action' => $action,
        'changes' => $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null,
        'user_id' => $GLOBALS['CNPL_USER']['id'] ?? null, 'created_at' => now(),
    ]);
}
