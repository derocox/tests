<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

session_start();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function volver(string $mensaje = '', string $query = ''): never
{
    if ($mensaje !== '') {
        $_SESSION['flash'] = $mensaje;
    }
    header('Location: index.php' . ($query !== '' ? '?' . $query : ''));
    exit;
}

// ---------------------------------------------------------------------------
// Acciones (POST)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Token CSRF inválido.');
    }

    $accion   = $_POST['accion'] ?? '';
    $id       = (int) ($_POST['id'] ?? 0);
    $estadoId = (int) ($_POST['estado_id'] ?? 0);
    $titulo   = trim($_POST['titulo'] ?? '');
    $desc     = trim($_POST['descripcion'] ?? '');
    $filtro   = isset($_POST['filtro']) && $_POST['filtro'] !== '' ? 'estado=' . (int) $_POST['filtro'] : '';

    switch ($accion) {
        case 'crear_tarea':
            if ($titulo === '' || !estadoExiste($estadoId)) {
                volver('El título y un estado válido son obligatorios.', $filtro);
            }
            db()->prepare('INSERT INTO tareas (titulo, descripcion, estado_id) VALUES (?, ?, ?)')
                ->execute([$titulo, $desc, $estadoId]);
            volver('Tarea creada.', $filtro);

        case 'editar_tarea':
            if (!tarea($id) || $titulo === '' || !estadoExiste($estadoId)) {
                volver('No se pudo guardar la tarea.', $filtro);
            }
            db()->prepare('UPDATE tareas SET titulo = ?, descripcion = ?, estado_id = ?,
                           actualizada = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$titulo, $desc, $estadoId, $id]);
            volver('Tarea actualizada.', $filtro);

        case 'cambiar_estado':
            if (!tarea($id) || !estadoExiste($estadoId)) {
                volver('Estado no válido.', $filtro);
            }
            db()->prepare('UPDATE tareas SET estado_id = ?, actualizada = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$estadoId, $id]);
            volver('Estado actualizado.', $filtro);

        case 'eliminar_tarea':
            db()->prepare('DELETE FROM tareas WHERE id = ?')->execute([$id]);
            volver('Tarea eliminada.', $filtro);

        case 'crear_estado':
            $nombre = trim($_POST['nombre'] ?? '');
            $color  = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#6b7280';
            if ($nombre === '') {
                volver('El nombre del estado es obligatorio.', $filtro);
            }
            try {
                $orden = (int) db()->query('SELECT COALESCE(MAX(orden), -1) + 1 FROM estados')->fetchColumn();
                db()->prepare('INSERT INTO estados (nombre, color, orden) VALUES (?, ?, ?)')
                    ->execute([$nombre, $color, $orden]);
            } catch (PDOException $ex) {
                volver('Ya existe un estado con ese nombre.', $filtro);
            }
            volver('Estado creado.', $filtro);

        case 'eliminar_estado':
            $stmt = db()->prepare('SELECT COUNT(*) FROM tareas WHERE estado_id = ?');
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() > 0) {
                volver('No se puede eliminar un estado que tiene tareas asignadas.', $filtro);
            }
            db()->prepare('DELETE FROM estados WHERE id = ?')->execute([$id]);
            volver('Estado eliminado.');
    }

    volver('Acción desconocida.', $filtro);
}

// ---------------------------------------------------------------------------
// Datos para la vista (GET)
// ---------------------------------------------------------------------------
$estados  = estados();
$filtro   = isset($_GET['estado']) && $_GET['estado'] !== '' ? (int) $_GET['estado'] : null;
$tareas   = tareas($filtro);
$editando = isset($_GET['editar']) ? tarea((int) $_GET['editar']) : null;
$flash    = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$conteo = array_fill_keys(array_column($estados, 'id'), 0);
foreach (tareas() as $t) {
    $conteo[$t['estado_id']]++;
}

$csrf = '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">'
      . '<input type="hidden" name="filtro" value="' . e((string) $filtro) . '">';

function selectorEstados(array $estados, ?int $seleccionado, string $extra = ''): string
{
    $html = '<select name="estado_id" ' . $extra . '>';
    foreach ($estados as $est) {
        $sel = (int) $est['id'] === $seleccionado ? ' selected' : '';
        $html .= '<option value="' . (int) $est['id'] . '"' . $sel . '>' . e($est['nombre']) . '</option>';
    }
    return $html . '</select>';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestor de tareas</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<main>
    <h1>Gestor de tareas</h1>

    <?php if ($flash !== ''): ?>
        <p class="flash"><?= e($flash) ?></p>
    <?php endif; ?>

    <!-- Formulario de creación / edición -->
    <section class="tarjeta">
        <h2><?= $editando ? 'Editar tarea' : 'Nueva tarea' ?></h2>
        <form method="post" class="form-tarea">
            <?= $csrf ?>
            <input type="hidden" name="accion" value="<?= $editando ? 'editar_tarea' : 'crear_tarea' ?>">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
            <?php endif; ?>
            <label>Título
                <input type="text" name="titulo" required maxlength="200" value="<?= e($editando['titulo'] ?? '') ?>">
            </label>
            <label>Descripción
                <textarea name="descripcion" rows="2"><?= e($editando['descripcion'] ?? '') ?></textarea>
            </label>
            <label>Estado
                <?= selectorEstados($estados, $editando ? (int) $editando['estado_id'] : ($filtro ?? (int) ($estados[0]['id'] ?? 0))) ?>
            </label>
            <div class="acciones">
                <button type="submit"><?= $editando ? 'Guardar cambios' : 'Agregar tarea' ?></button>
                <?php if ($editando): ?>
                    <a href="index.php<?= $filtro !== null ? '?estado=' . $filtro : '' ?>">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- Filtro por estado -->
    <nav class="filtros">
        <a href="index.php" class="<?= $filtro === null ? 'activo' : '' ?>">Todas (<?= array_sum($conteo) ?>)</a>
        <?php foreach ($estados as $est): ?>
            <a href="?estado=<?= (int) $est['id'] ?>"
               class="<?= $filtro === (int) $est['id'] ? 'activo' : '' ?>"
               style="--color: <?= e($est['color']) ?>">
                <?= e($est['nombre']) ?> (<?= $conteo[$est['id']] ?>)
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Listado de tareas -->
    <section>
        <?php if (!$tareas): ?>
            <p class="vacio">No hay tareas<?= $filtro !== null ? ' en este estado' : '' ?>.</p>
        <?php endif; ?>
        <ul class="tareas">
            <?php foreach ($tareas as $t): ?>
                <li class="tarea" style="--color: <?= e($t['color']) ?>">
                    <div class="info">
                        <span class="etiqueta"><?= e($t['estado']) ?></span>
                        <strong><?= e($t['titulo']) ?></strong>
                        <?php if ($t['descripcion'] !== ''): ?>
                            <p><?= nl2br(e($t['descripcion'])) ?></p>
                        <?php endif; ?>
                        <small>Actualizada: <?= e($t['actualizada']) ?></small>
                    </div>
                    <div class="controles">
                        <form method="post">
                            <?= $csrf ?>
                            <input type="hidden" name="accion" value="cambiar_estado">
                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <?= selectorEstados($estados, (int) $t['estado_id'], 'onchange="this.form.submit()" aria-label="Cambiar estado"') ?>
                            <noscript><button type="submit">Cambiar</button></noscript>
                        </form>
                        <a href="?editar=<?= (int) $t['id'] ?><?= $filtro !== null ? '&estado=' . $filtro : '' ?>">Editar</a>
                        <form method="post" onsubmit="return confirm('¿Eliminar esta tarea?')">
                            <?= $csrf ?>
                            <input type="hidden" name="accion" value="eliminar_tarea">
                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <button type="submit" class="peligro">Eliminar</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <!-- Gestión de estados -->
    <section class="tarjeta">
        <h2>Estados</h2>
        <ul class="estados">
            <?php foreach ($estados as $est): ?>
                <li style="--color: <?= e($est['color']) ?>">
                    <span class="etiqueta"><?= e($est['nombre']) ?></span>
                    <form method="post">
                        <?= $csrf ?>
                        <input type="hidden" name="accion" value="eliminar_estado">
                        <input type="hidden" name="id" value="<?= (int) $est['id'] ?>">
                        <button type="submit" class="enlace" <?= $conteo[$est['id']] > 0 ? 'disabled title="Tiene tareas asignadas"' : '' ?>>Eliminar</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" class="form-estado">
            <?= $csrf ?>
            <input type="hidden" name="accion" value="crear_estado">
            <input type="text" name="nombre" placeholder="Nuevo estado (p. ej. En revisión)" required maxlength="50">
            <input type="color" name="color" value="#9333ea" aria-label="Color">
            <button type="submit">Agregar estado</button>
        </form>
    </section>
</main>
</body>
</html>
