<?php
/**
 * PDO database access. Every query uses prepared statements with bound values.
 * Table and column identifiers only ever come from code, never from user input.
 */
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = cfg('db');
    if (is_production() && strtolower((string) $c['user']) === 'root') {
        throw new RuntimeException('Refusing to connect: the application must not use the MySQL root account in production.');
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int) ($c['port'] ?? 3306), $c['name']);
    $pdo = new PDO($dsn, (string) $c['user'], (string) $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $offset = (new DateTime('now'))->format('P');
    $pdo->exec("SET time_zone = '" . $offset . "'");
    $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $i = 0;
    foreach ($params as $k => $v) {
        $key = is_int($k) ? ++$i : (strpos((string) $k, ':') === 0 ? $k : ':' . $k);
        if (is_int($v)) {
            $st->bindValue($key, $v, PDO::PARAM_INT);
        } elseif (is_bool($v)) {
            $st->bindValue($key, $v ? 1 : 0, PDO::PARAM_INT);
        } elseif ($v === null) {
            $st->bindValue($key, null, PDO::PARAM_NULL);
        } else {
            $st->bindValue($key, (string) $v, PDO::PARAM_STR);
        }
    }
    $st->execute();
    return $st;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function q_val(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function q_col(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
}

/** key => value pairs from a two-column query */
function q_pairs(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll(PDO::FETCH_KEY_PAIR);
}

function ident(string $name): string
{
    if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $name)) {
        throw new InvalidArgumentException('Invalid identifier');
    }
    return '`' . $name . '`';
}

function q_insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO ' . ident($table) . ' (' . implode(',', array_map('ident', $cols)) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int) db()->lastInsertId();
}

function q_update(string $table, array $data, int $id, string $pk = 'id'): int
{
    if (!$data) {
        return 0;
    }
    $sets = [];
    foreach (array_keys($data) as $c) {
        $sets[] = ident($c) . ' = ?';
    }
    $params = array_values($data);
    $params[] = $id;
    return q('UPDATE ' . ident($table) . ' SET ' . implode(', ', $sets) . ' WHERE ' . ident($pk) . ' = ?', $params)->rowCount();
}

/** Run a callback inside a transaction; nested calls join the outer one. */
function tx(callable $fn)
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn();
    }
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Build "col IN (?,?,?)" safely; empty list yields a false condition. */
function sql_in(string $col, array $values, array &$params): string
{
    $values = array_values(array_unique(array_map('intval', $values)));
    if (!$values) {
        return '0=1';
    }
    foreach ($values as $v) {
        $params[] = $v;
    }
    return $col . ' IN (' . implode(',', array_fill(0, count($values), '?')) . ')';
}

function like_escape(string $s): string
{
    return '%' . addcslashes($s, '%_\\') . '%';
}
