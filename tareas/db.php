<?php

/**
 * Conexión a SQLite y creación del esquema.
 *
 * La base de datos se guarda en tareas/data/tareas.sqlite y se crea
 * automáticamente la primera vez, junto con los estados por defecto.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $dir . '/tareas.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec('CREATE TABLE IF NOT EXISTS estados (
        id     INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL UNIQUE,
        color  TEXT NOT NULL DEFAULT "#6b7280",
        orden  INTEGER NOT NULL DEFAULT 0
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS tareas (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        titulo      TEXT NOT NULL,
        descripcion TEXT NOT NULL DEFAULT "",
        estado_id   INTEGER NOT NULL REFERENCES estados(id),
        creada      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        actualizada TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $total = (int) $pdo->query('SELECT COUNT(*) FROM estados')->fetchColumn();
    if ($total === 0) {
        $porDefecto = [
            ['Pendiente', '#6b7280'],
            ['En progreso', '#2563eb'],
            ['Bloqueada', '#dc2626'],
            ['Completada', '#16a34a'],
        ];
        $stmt = $pdo->prepare('INSERT INTO estados (nombre, color, orden) VALUES (?, ?, ?)');
        foreach ($porDefecto as $i => [$nombre, $color]) {
            $stmt->execute([$nombre, $color, $i]);
        }
    }

    return $pdo;
}

function estados(): array
{
    return db()->query('SELECT * FROM estados ORDER BY orden, id')->fetchAll();
}

function tareas(?int $estadoId = null): array
{
    $sql = 'SELECT t.*, e.nombre AS estado, e.color
            FROM tareas t JOIN estados e ON e.id = t.estado_id';
    if ($estadoId !== null) {
        $stmt = db()->prepare($sql . ' WHERE t.estado_id = ? ORDER BY t.actualizada DESC, t.id DESC');
        $stmt->execute([$estadoId]);
        return $stmt->fetchAll();
    }
    return db()->query($sql . ' ORDER BY e.orden, t.actualizada DESC, t.id DESC')->fetchAll();
}

function tarea(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM tareas WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function estadoExiste(int $id): bool
{
    $stmt = db()->prepare('SELECT 1 FROM estados WHERE id = ?');
    $stmt->execute([$id]);
    return (bool) $stmt->fetchColumn();
}
