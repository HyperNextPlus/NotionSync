<?php

// Traducción al español de los textos del plugin.
//
// Los textos fuente están en inglés, siguiendo la convención de Kanboard: t()
// devuelve la cadena original cuando no encuentra traducción, así que la interfaz
// en inglés funciona sin este archivo.
//
// No se repiten aquí las cadenas que el core ya traduce (Add, Remove, Save,
// Update, This field is required, Settings saved successfully.): el plugin carga
// su locale después del core, de modo que redefinirlas solo duplicaría trabajo.
//
// Para añadir otro idioma, crear Locale/<código>/translations.php con las mismas
// claves.

return array(
    // Configuración global
    'NotionSync settings' => 'Configuración de NotionSync',
    'Notion integration token' => 'Token de integración de Notion',
    'A token is already saved. Leave this field empty to keep it.' => 'Ya hay un token guardado. Deja este campo vacío para conservarlo.',
    'Secret token of the internal integration created in Notion.' => 'Token secreto de la integración interna creada en Notion.',
    'Identifier of the "Tasks" database' => 'Identificador de la base de datos "Tareas"',
    'The database_id of the database where task pages will be created.' => 'El database_id de la base donde se crearán las páginas de las tareas.',
    'The plugin is not configured yet: no task will be synchronized with Notion until you complete these values.' => 'El plugin todavía no está configurado: ninguna tarea se sincronizará con Notion hasta que completes estos datos.',
    'Remember to share the "Tasks" database (and any related database used by relation fields) with the Notion integration. Without that permission the API replies that the database cannot be found.' => 'Recuerda compartir la base de datos "Tareas" (y cualquier base relacionada que usen los campos de tipo relación) con la integración de Notion. Sin ese permiso la API responde que no encuentra la base de datos.',
    'Check the submitted values.' => 'Revisa los datos ingresados.',

    // Configuración por proyecto
    'Notion synchronization' => 'Sincronización con Notion',
    'The global plugin settings are missing (token and database). An administrator must complete them for synchronization to work.' => 'Falta la configuración global del plugin (token y base de datos). Un administrador debe completarla para que la sincronización funcione.',
    'This project has no property configured, so its tasks are not synchronized with Notion. Add at least one property to enable synchronization.' => 'Este proyecto no tiene ninguna propiedad configurada, así que sus tareas no se sincronizan con Notion. Agrega al menos una propiedad para activar la sincronización.',
    'Variables available in templates' => 'Variables disponibles en las plantillas',
    'You can combine free text and variables, for example: Task {{task_id}} of {{project_name}}' => 'Puedes combinar texto libre y variables, por ejemplo: Tarea {{task_id}} de {{project_name}}',
    'Synchronized properties' => 'Propiedades sincronizadas',
    'Add a property' => 'Agregar una propiedad',
    'Notion property name' => 'Nombre de la propiedad en Notion',
    'It must match exactly the property name in the Notion database.' => 'Debe coincidir exactamente con el nombre de la propiedad en la base de datos de Notion.',
    'Property type' => 'Tipo de propiedad',
    'Value template' => 'Plantilla del valor',
    'For a multi-select property, separate the values with commas.' => 'Para una propiedad de selección múltiple, separa los valores con comas.',
    'Related database (relation type only)' => 'Base de datos relacionada (solo para tipo relación)',
    'The database_id of the database where the page whose title exactly matches the resolved value will be searched.' => 'El database_id de la base donde se buscará la página cuyo título coincida exactamente con el valor resuelto.',
    'Field added successfully.' => 'Campo agregado correctamente.',
    'Field updated successfully.' => 'Campo actualizado correctamente.',
    'Field removed successfully.' => 'Campo eliminado correctamente.',
    'Field not found.' => 'Campo no encontrado.',
    'Invalid property type.' => 'Tipo de propiedad no válido.',
    'A relation property requires the identifier of the related database.' => 'Una propiedad de tipo relación necesita el identificador de la base de datos relacionada.',
    'This project already has a property of type title.' => 'El proyecto ya tiene una propiedad de tipo título.',

    // Acción al eliminar una tarea
    'When a task is removed' => 'Al eliminar una tarea',
    'When a task is removed in Kanboard, its Notion page is neither deleted nor archived: only the property set here is updated.' => 'Cuando una tarea se elimina en Kanboard, su página en Notion no se borra ni se archiva: solo se actualiza la propiedad indicada aquí.',
    'Property to update' => 'Propiedad a actualizar',
    'Value to assign' => 'Valor a asignar',
    'Delete action saved successfully.' => 'Acción al eliminar tarea guardada correctamente.',

    // Tipos de propiedad de Notion
    'Title (title)' => 'Título (title)',
    'Rich text (rich_text)' => 'Texto enriquecido (rich_text)',
    'Select (select)' => 'Selección (select)',
    'Multi-select (multi_select)' => 'Selección múltiple (multi_select)',
    'Status (status)' => 'Estado (status)',
    'Date (date)' => 'Fecha (date)',
    'URL (url)' => 'URL (url)',
    'Relation (relation)' => 'Relación (relation)',

    // Variables de plantilla
    'Task title' => 'Título de la tarea',
    'Task identifier in Kanboard' => 'Identificador de la tarea en Kanboard',
    'Direct link to the task in Kanboard' => 'Enlace directo a la tarea en Kanboard',
    'Name of the Kanboard project' => 'Nombre del proyecto de Kanboard',
    'Task creation date (YYYY-MM-DD)' => 'Fecha de creación de la tarea (AAAA-MM-DD)',
    'Name of the assigned user' => 'Nombre del usuario asignado',
    'Task description' => 'Descripción de la tarea',

    // Vista de la tarea y reintento manual
    'Notion synchronization pending' => 'Sincronización con Notion pendiente',
    'This task has not been sent to Notion yet.' => 'Esta tarea todavía no se envió a Notion.',
    'Retry synchronization' => 'Reintentar sincronización',
    'This task has no pending synchronization.' => 'Esta tarea no tiene sincronizaciones pendientes.',
    'Synchronization with Notion completed.' => 'Sincronización con Notion completada.',
    'Synchronization with Notion failed: %s' => 'La sincronización con Notion falló: %s',

    // Errores de sincronización
    'The plugin is not configured: the Notion token or the database identifier is missing.' => 'El plugin no está configurado: faltan el token de Notion o el identificador de la base de datos.',
    'The Notion integration token is missing from the plugin settings.' => 'Falta el token de integración de Notion en la configuración del plugin.',
    'Unknown operation: %s' => 'Operación desconocida: %s',
    'Task #%d no longer exists in Kanboard.' => 'La tarea n.º %d ya no existe en Kanboard.',
    'The project no longer has any field configured for synchronization.' => 'El proyecto ya no tiene campos configurados para sincronizar.',
    'The field "%s" is a relation but has no related database configured.' => 'El campo "%s" es de tipo relación pero no tiene base de datos configurada.',
    'The task does not have a page created in Notion yet.' => 'La tarea todavía no tiene una página creada en Notion.',
    'The project does not have any property of type title configured.' => 'El proyecto no tiene ninguna propiedad de tipo título configurada.',
    'The task has no associated page in Notion.' => 'La tarea no tiene una página asociada en Notion.',
    'The project does not have the delete action configured.' => 'El proyecto no tiene configurada la acción al eliminar tarea.',
    'Notion did not return the identifier of the created page.' => 'Notion no devolvió el identificador de la página creada.',
    'Unsupported property type: %s' => 'Tipo de propiedad no soportado: %s',

    // Resolución de relaciones
    'The template of a relation field resolved to an empty value.' => 'La plantilla de un campo de tipo relación resolvió a un valor vacío.',
    'No page titled "%s" was found in the related database.' => 'No se encontró ninguna página titulada "%s" en la base de datos relacionada.',
    'The title "%s" matches %d pages in the related database, so the relation is ambiguous.' => 'El título "%s" coincide con %d páginas en la base de datos relacionada, así que la relación es ambigua.',
    'The related database has no property of type title.' => 'La base de datos relacionada no tiene ninguna propiedad de tipo título.',

    // Límites de la API de Notion
    'Notion limits text to %d characters and the resolved value has %d. Shorten the template or the task title.' => 'Notion limita el texto a %d caracteres y el valor resuelto tiene %d. Acorta la plantilla o el título de la tarea.',
    'Notion limits option names to %d characters and the resolved value has %d.' => 'Notion limita los nombres de opción a %d caracteres y el valor resuelto tiene %d.',
    'A multi-select property accepts at most %d options per request.' => 'Una propiedad de selección múltiple admite como máximo %d opciones por petición.',

    // Errores de red
    'The PHP cURL extension is required to connect to Notion.' => 'La extensión cURL de PHP es necesaria para conectarse con Notion.',
    'Unable to reach the Notion API: %s' => 'No se pudo contactar con la API de Notion: %s',
    'no details provided' => 'sin detalle',

    // Comando CLI
    'NotionSync is not configured: the token or the database identifier is missing.' => 'NotionSync no está configurado: falta el token o el identificador de la base de datos.',
    'No pending synchronizations.' => 'No hay sincronizaciones pendientes.',
    'Processed: %d | Synced: %d | Failed: %d' => 'Procesados: %d | Sincronizados: %d | Con error: %d',

    // Descripción del plugin
    'Creates and keeps Kanboard tasks synchronized as pages in a Notion database.' => 'Crea y mantiene sincronizadas las tareas de Kanboard como páginas en una base de datos de Notion.',
);
