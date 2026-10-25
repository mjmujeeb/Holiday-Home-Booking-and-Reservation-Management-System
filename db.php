<?php
/* Tiny prepared-statement wrapper. Every query in the project goes through here,
 * so user input is only ever bound as a parameter and never pasted into SQL. */

function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }
    $c = config('db');
    $conn = new mysqli($c['host'], $c['user'], $c['pass'], $c['name'], (int) ($c['port'] ?? 3306));
    $conn->set_charset('utf8mb4');
    return $conn;
}

function db_exec(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

/** @return array<int, array<string, mixed>> */
function db_all(string $sql, string $types = '', array $params = []): array
{
    $stmt = db_exec($sql, $types, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** @return array<string, mixed>|null */
function db_one(string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_all($sql, $types, $params);
    return $rows[0] ?? null;
}

function db_value(string $sql, string $types = '', array $params = []): mixed
{
    $row = db_one($sql, $types, $params);
    return $row ? reset($row) : null;
}

/** Run INSERT / UPDATE / DELETE. Returns affected rows. */
function db_run(string $sql, string $types = '', array $params = []): int
{
    $stmt = db_exec($sql, $types, $params);
    $n = $stmt->affected_rows;
    $stmt->close();
    return $n;
}

function db_last_id(): int
{
    return (int) db()->insert_id;
}

/** Run a callback inside a transaction. Rolls back and rethrows on any failure. */
function db_transaction(callable $fn): mixed
{
    $conn = db();
    $conn->begin_transaction();
    try {
        $result = $fn();
        $conn->commit();
        return $result;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

/** Escape % and _ so a visitor's search text cannot act as a wildcard. */
function like_escape(string $s): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}
