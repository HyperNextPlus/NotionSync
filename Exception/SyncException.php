<?php

namespace Kanboard\Plugin\NotionSync\Exception;

use Exception;

/**
 * Error de sincronización originado en el propio plugin y no en la API: campo
 * mal configurado, relación sin resolver, configuración global incompleta.
 */
class SyncException extends Exception
{
}
