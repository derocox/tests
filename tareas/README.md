# Gestor de tareas

Aplicación PHP sencilla para gestionar tareas y definir su estado.

## Funcionalidades

- Crear, editar y eliminar tareas (título, descripción y estado).
- Cambiar el estado de una tarea directamente desde el listado.
- Filtrar tareas por estado, con contador por estado.
- Definir estados propios (nombre y color). Vienen cuatro por defecto:
  *Pendiente*, *En progreso*, *Bloqueada* y *Completada*.
  Un estado solo puede eliminarse si no tiene tareas asignadas.

## Requisitos

- PHP 8.1 o superior con la extensión `pdo_sqlite`.

## Uso

```bash
php -S localhost:8000 -t tareas
```

Abrir <http://localhost:8000>. La base de datos SQLite se crea
automáticamente en `tareas/data/tareas.sqlite`.
