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

## Cifrado del token

El token de integración se guarda cifrado con **AES-256-GCM**, nunca en claro. El cifrado es
autenticado: si el valor almacenado se altera, el descifrado falla en lugar de devolver basura.

**Qué protege y qué no.** La clave vive en el servidor de aplicación, así que esto no defiende frente a
quien ya controla ese servidor. Sí evita que el token quede legible en un volcado de la base, en una
réplica, en un backup de MySQL o ante un acceso de solo lectura a la base — que es donde un secreto en
claro suele acabar filtrándose.

**De dónde sale la clave**, por orden de prioridad:

1. La constante o variable de entorno `NOTIONSYNC_ENCRYPTION_KEY`. Es lo recomendable: mantiene la
   clave fuera del disco de datos y permite compartirla entre varios servidores.

   ```php
   // config.php
   define('NOTIONSYNC_ENCRYPTION_KEY', 'una cadena larga y aleatoria');
   ```

2. Si no está definida, se genera una clave de 32 bytes en
   `data/files/notionsync/notionsync.key`, con el directorio en `0700` y el archivo en `0600`. La ruta
   se deriva de la constante `FILES_DIR`, así que acompaña a cualquier reubicación del directorio de
   datos. El `.htaccess` que Kanboard trae en `data/` la deja fuera del alcance del servidor web.

**Cuidado con el usuario del proceso.** La clave se crea con permisos `0600`, así que solo la lee su
propietario. Normalmente la genera el servidor web (`www-data`), de modo que el cron que ejecuta
`notionsync:process-queue` debe correr como ese mismo usuario o como `root`; si corre como un tercero,
no podrá descifrar el token y todas las sincronizaciones fallarán. Definir
`NOTIONSYNC_ENCRYPTION_KEY` evita el problema de raíz, porque entonces no hay archivo.

**Si se pierde la clave, el token es irrecuperable**: hay que volver a introducirlo desde la pantalla de
configuración. Conviene incluir la clave en la copia de seguridad, guardada aparte del volcado de la
base de datos — si viajan juntos, el cifrado deja de aportar nada.

Un token guardado antes de activarse el cifrado se sigue leyendo con normalidad, la pantalla de
configuración avisa de que está en claro, y queda cifrado la próxima vez que se guarde el formulario.

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

Los hooks solo encolan; las llamadas a Notion las hace el procesador de la cola, que hay que disparar
periódicamente. Se puede hacer por consola o por URL, según lo que permita el alojamiento.

### Desde la línea de comandos

```
./cli notionsync:process-queue
```

Ejemplo de cron cada cinco minutos:

```
*/5 * * * * cd /var/www/html/kanboard.hypernextplus.com && ./cli notionsync:process-queue >/dev/null 2>&1
```

Opciones: `--limit` (trabajos por ejecución, 50 por defecto) y `--delay` (pausa en milisegundos entre
llamadas, 350 por defecto), pensadas para no agotar el límite de peticiones de Notion.

### Desde una URL

Para alojamientos que no permiten ejecutar procesos por consola, la misma cola se procesa por HTTP.
Sigue el patrón del cron por URL del core (`CronjobController`): ruta pública autenticada con el
**token global de webhooks** de Kanboard, el mismo que aparece en *Configuración → Webhooks*.

```
https://domain.tld/notionsync/cron?token=TOKEN
https://domain.tld/?controller=NotionCronjobController&action=run&plugin=NotionSync&token=TOKEN
```

Las dos formas son equivalentes y llaman a la misma acción. La primera necesita que la reescritura de
URLs esté activa (`ENABLE_URL_REWRITE`); la segunda funciona siempre. Ambas aparecen ya montadas, con
el token real, en *Configuración → NotionSync*.

Se disparan con cualquier cliente HTTP, sea el cron del panel del alojamiento o un servicio externo:

```
*/5 * * * * wget -q -O - "https://domain.tld/notionsync/cron?token=TOKEN" >/dev/null 2>&1
```

Parámetros opcionales en la query: `limit` y `delay`, equivalentes a las opciones de la CLI. Por
defecto el límite es **20**, no 50: la consola no tiene tope de tiempo pero una petición HTTP sí, y
`max_execution_time` suele estar en 30 s justo en los alojamientos que obligan a usar esta ruta. Con
20 trabajos y 350 ms de pausa se acumulan 7 s, quedando margen para las llamadas a Notion. Si el
alojamiento permite `set_time_limit()`, el controlador lo levanta.

Que la petición se corte por tiempo no rompe nada: cada trabajo se confirma en la base en cuanto
termina, así que lo ya sincronizado queda hecho y el resto lo recoge la siguiente pasada.

La respuesta es `text/plain` con la salida del comando —incluida la línea
`Procesados | Sincronizados | Con error`— porque un cron por URL no tiene stdout donde mirar. El
código HTTP es `200` siempre que el endpoint haya podido ejecutarse, y `403` si el token no coincide;
un trabajo fallido **no** devuelve error HTTP, para que una incidencia pasajera de Notion no dispare
las alertas del monitor: el reintento ya lo cubre la siguiente ejecución.

Si la instancia no tiene `webhook_token`, la URL responde `403` en lugar de quedar abierta.

## Nota sobre la eliminación de tareas

Kanboard no dispara ningún evento al eliminar una tarea: `TaskModel::remove()` borra la fila sin
notificar. El plugin registra su propio `TaskModel` en el contenedor (`Plugin::getClasses()`), que
extiende el del core y emite `notionsync.task.delete`. No se modifica ningún archivo de Kanboard, y la
cobertura incluye tanto el borrado desde la interfaz como el de la API JSON-RPC.
