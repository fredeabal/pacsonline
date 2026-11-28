#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Migra los datos existentes de CodeIgniter 4 (writable/pacs.sqlite) hacia PostgreSQL.
 * Ejecutar DESPUÉS de correr `php spark migrate --all` contra PostgreSQL.
 */

$sqlitePath = $argv[1] ?? '/var/www/html/writable/pacs.sqlite';
if (! is_file($sqlitePath)) {
    fwrite(STDERR, "[ERROR] No se encontró SQLite en: {$sqlitePath}\n");
    exit(1);
}

$host = getenv('APP_DB_HOST') ?: 'postgres';
$port = getenv('APP_DB_PORT') ?: '5432';
$db   = getenv('APP_DB_NAME') ?: 'pacs_app';
$user = getenv('APP_DB_USER') ?: 'orthanc';
$pass = getenv('APP_DB_PASSWORD') ?: '';

$sqlite = new PDO('sqlite:' . $sqlitePath, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pg = new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Tablas existentes en PostgreSQL (excluyendo migrations)
$pgTables = $pg->query("
    SELECT tablename
    FROM pg_tables
    WHERE schemaname = 'public' AND tablename <> 'migrations'
    ORDER BY tablename
")->fetchAll(PDO::FETCH_COLUMN);

// Tablas existentes en SQLite
$sqliteTables = $sqlite->query("
    SELECT name
    FROM sqlite_master
    WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name <> 'migrations'
")->fetchAll(PDO::FETCH_COLUMN);

$tablesToCopy = array_values(array_intersect($pgTables, $sqliteTables));

if ($tablesToCopy === []) {
    fwrite(STDERR, "[ERROR] No hay tablas en común entre SQLite y PostgreSQL.\n");
    exit(1);
}

echo '[INFO] Tablas a migrar (' . count($tablesToCopy) . '): ' . implode(', ', $tablesToCopy) . "\n";

$pg->beginTransaction();

try {
    // Desactiva triggers/FK temporalmente durante la carga inicial (requiere superuser)
    $pg->exec("SET session_replication_role = 'replica'");

    // Limpiar todas las tablas destino ANTES de insertar para que CASCADE no borre tablas ya copiadas
    $quotedAll = implode(', ', array_map(static fn ($t) => "\"{$t}\"", $tablesToCopy));
    $pg->exec("TRUNCATE TABLE {$quotedAll} RESTART IDENTITY CASCADE");

    foreach ($tablesToCopy as $table) {
        // Obtener columnas destino en PostgreSQL con sus tipos
        $colStmt = $pg->prepare("
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = :t
            ORDER BY ordinal_position
        ");
        $colStmt->execute([':t' => $table]);
        $pgCols = [];
        foreach ($colStmt->fetchAll() as $c) {
            $pgCols[$c['column_name']] = $c['data_type'];
        }

        // Obtener columnas origen en SQLite
        $sqCols = [];
        foreach ($sqlite->query("PRAGMA table_info(\"{$table}\")")->fetchAll() as $c) {
            $sqCols[] = $c['name'];
        }

        $commonCols = array_values(array_intersect(array_keys($pgCols), $sqCols));
        if ($commonCols === []) {
            continue;
        }

        $rows = $sqlite->query('SELECT ' . implode(', ', array_map(static fn ($c) => "\"{$c}\"", $commonCols)) . " FROM \"{$table}\"")->fetchAll();
        if ($rows === []) {
            echo "  - {$table}: 0 filas\n";
            continue;
        }

        $colList      = implode(', ', array_map(static fn ($c) => "\"{$c}\"", $commonCols));
        $placeholders = implode(', ', array_fill(0, count($commonCols), '?'));
        $insertStmt   = $pg->prepare("INSERT INTO \"{$table}\" ({$colList}) VALUES ({$placeholders})");

        foreach ($rows as $row) {
            $values = [];
            foreach ($commonCols as $col) {
                $val  = $row[$col];
                $type = $pgCols[$col] ?? '';
                if ($val === '' && in_array($type, ['integer', 'bigint', 'smallint', 'numeric', 'real', 'double precision', 'timestamp without time zone', 'timestamp with time zone', 'date'], true)) {
                    $val = null;
                } elseif ($type === 'boolean' && $val !== null) {
                    $val = (bool) $val ? 'true' : 'false';
                }
                $values[] = $val;
            }
            $insertStmt->execute($values);
        }

        // Sincronizar secuencia si existe columna id serial/identity
        if (isset($pgCols['id'])) {
            $seq = $pg->query("SELECT pg_get_serial_sequence('public.{$table}', 'id')")->fetchColumn();
            if (is_string($seq) && $seq !== '') {
                $maxId = (int) $pg->query("SELECT COALESCE(MAX(\"id\"), 0) FROM \"{$table}\"")->fetchColumn();
                if ($maxId > 0) {
                    $pg->exec("SELECT setval('{$seq}', {$maxId}, true)");
                }
            }
        }

        echo "  - {$table}: " . count($rows) . " filas copiadas\n";
    }

    $pg->exec("SET session_replication_role = 'origin'");
    $pg->commit();
    echo "[OK] Migración SQLite -> PostgreSQL completada correctamente.\n";
} catch (Throwable $e) {
    $pg->rollBack();
    fwrite(STDERR, '[FATAL] Error durante la migración: ' . $e->getMessage() . "\n");
    exit(1);
}
