# NotionSync

Plugin de Kanboard que crea una página en una base de datos de Notion cada vez que se crea una tarea
en un proyecto configurado, y mantiene sincronizado su título ante ediciones posteriores.

## Endpoints usados

Se fija la versión `2022-06-28` de la API, que es la que usa `parent.database_id` para crear páginas.
Las versiones a partir de 2025 sustituyen ese concepto por *data sources* y cambian la forma del
`parent`, así que subir de versión no es un cambio transparente.

| Operación | Endpoint |
|---|---|
| Crear la página de una tarea | `POST /v1/pages` |
| Actualizar el título o la propiedad de borrado | `PATCH /v1/pages/{page_id}` |
| Descubrir la propiedad de tipo `title` de una base relacionada | `GET /v1/databases/{database_id}` |
| Buscar la página relacionada por título | `POST /v1/databases/{database_id}/query` |

## Comportamientos que impone la API de Notion

- **Opciones de `select` y `multi_select`**: Notion agrega al esquema cualquier nombre de opción que no
  exista, siempre que la integración tenga permiso de escritura sobre la base. El plugin no hace
  ninguna llamada extra para esto.
- **`status` es distinto**: sus opciones no se crean automáticamente. El valor configurado debe existir
  ya en Notion, o la sincronización falla. Esto afecta a la acción de borrado por defecto
  (Estado = "Cancelado"): esa opción tiene que existir en la base.
- **Límite de 2000 caracteres** en `title` y `rich_text`. El plugin **no trunca**: valida antes de
  enviar y deja la tarea pendiente con un mensaje que indica cuántos caracteres sobran, para que se
  decida qué recortar.
- **Límite de 100 caracteres** en nombres de opción de `select`, `multi_select` y `status`.
- **Las comas no son válidas dentro del nombre de una opción**, por eso la coma se puede usar sin
  ambigüedad como separador de valores en un `multi_select`.
- **Límite de peticiones**: unas 3 por segundo. De ahí los valores por defecto de `--limit` y `--delay`
  del comando de cron.

Un valor de plantilla que resuelve a texto vacío se envía como `null` (sin valor) en lugar de como
cadena vacía, porque Notion rechaza un nombre de opción o una fecha vacíos.

## Instalación

El plugin se carga solo con estar en `plugins/NotionSync/`. En el primer arranque se crean sus tablas
(`notionsync_*`) mediante el sistema de migraciones de plugins de Kanboard.

## Configuración

1. **Global** — *Configuración → NotionSync* (solo administradores): token de integración de Notion y
   `database_id` de la base "Tareas".
2. **Por proyecto** — *Proyecto → Sincronización con Notion* (manager del proyecto): las propiedades a
   sincronizar, una por una. Mientras un proyecto no tenga ninguna propiedad configurada, sus tareas
   no generan ninguna llamada a Notion.

Antes de usarlo, la base "Tareas" y cualquier base relacionada deben estar compartidas con la
integración de Notion.

Para que `{{task_url}}` genere enlaces correctos hay que tener definido **URL de la aplicación** en los
ajustes de Kanboard; sin ese valor, Kanboard no puede construir enlaces absolutos desde el cron y
apuntaría a `http://localhost/`.

## Tipos de propiedad soportados

`title`, `rich_text`, `select`, `multi_select`, `status`, `date`, `url`, `relation`.

En `multi_select` los valores se separan con comas. En `date` la plantilla debe resolver a `AAAA-MM-DD`,
que es lo que produce `{{created_at}}`.

Para un campo `relation` hay que indicar además el `database_id` de la base relacionada. El plugin
descubre solo cuál es su propiedad de título, busca la página cuyo título coincida **exactamente**
(sensible a mayúsculas y espacios) y falla la sincronización entera si no encuentra ninguna o si
encuentra más de una, sin dejar páginas a medio crear.

## Variables de plantilla

`{{title_task}}`, `{{task_id}}`, `{{task_url}}`, `{{project_name}}`, `{{created_at}}`, `{{assignee}}`,
`{{description}}`.

Se pueden combinar con texto libre: `Tarea {{task_id}} de {{project_name}}`.

## Idiomas

Los textos fuente están en inglés y la traducción al español vive en
`Locale/es_ES/translations.php`, siguiendo la convención de Kanboard. El idioma mostrado es el del
usuario, o `application_language` si el usuario no tiene uno propio.

Seis cadenas genéricas (`Add`, `Remove`, `Save`, `Update`, `This field is required`,
`Settings saved successfully.`) las traduce ya el core y no se repiten aquí.

Para añadir otro idioma basta con crear `Locale/<código>/translations.php` con las mismas claves; sin
ese archivo la interfaz cae en inglés, que es el texto fuente.

## Procesamiento en segundo plano

Los hooks solo encolan; las llamadas a Notion las hace el comando CLI:

```
./cli notionsync:process-queue
```

Ejemplo de cron cada cinco minutos:

```
*/5 * * * * cd /var/www/html/kanboard.hypernextplus.com && ./cli notionsync:process-queue >/dev/null 2>&1
```

Opciones: `--limit` (trabajos por ejecución, 50 por defecto) y `--delay` (pausa en milisegundos entre
llamadas, 350 por defecto), pensadas para no agotar el límite de peticiones de Notion.

## Nota sobre la eliminación de tareas

Kanboard no dispara ningún evento al eliminar una tarea: `TaskModel::remove()` borra la fila sin
notificar. El plugin registra su propio `TaskModel` en el contenedor (`Plugin::getClasses()`), que
extiende el del core y emite `notionsync.task.delete`. No se modifica ningún archivo de Kanboard, y la
cobertura incluye tanto el borrado desde la interfaz como el de la API JSON-RPC.
