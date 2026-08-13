<?php

namespace Kanboard\Plugin\NotionSync\Exception;

use Exception;

/**
 * Error al comunicarse con la API de Notion.
 *
 * El mensaje se guarda en la cola y se muestra al usuario en la vista de la
 * tarea, así que debe ser legible sin consultar los logs.
 */
class NotionApiException extends Exception
{
}
