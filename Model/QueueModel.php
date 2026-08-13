<?php

namespace Kanboard\Plugin\NotionSync\Model;

use Kanboard\Core\Base;

/**
 * Cola interna de sincronizaciones pendientes.
 *
 * Los hooks de Kanboard solo encolan: nunca llaman a la API de Notion durante el
 * request (regla de negocio 12, la integración jamás bloquea la operación en
 * Kanboard). El trabajo real lo hace el comando CLI vía cron, o el botón de
 * reintento manual desde la vista de la tarea.
 */
class QueueModel extends Base
{
    const TABLE = 'notionsync_queue';

    const OPERATION_CREATE       = 'create';
    const OPERATION_UPDATE_TITLE = 'update_title';
    const OPERATION_DELETE       = 'delete';

    const STATUS_PENDING = 'pending';
    const STATUS_ERROR   = 'error';
    const STATUS_SYNCED  = 'synced';

    /**
     * Encola una operación de sincronización.
     *
     * @param  integer $task_id
     * @param  integer $project_id
     * @param  string  $operation
     * @param  array   $snapshot   Copia de la tarea, imprescindible para delete
     * @return boolean
     */
    public function enqueue($task_id, $project_id, $operation, array $snapshot = array())
    {
        return $this->db->table(self::TABLE)->insert(array(
            'task_id' => $task_id,
            'project_id' => $project_id,
            'operation' => $operation,
            'status' => self::STATUS_PENDING,
            'snapshot' => json_encode($snapshot),
            'attempts' => 0,
            'last_error' => '',
            'created_at' => time(),
            'updated_at' => time(),
        ));
    }

    /**
     * Devuelve los trabajos aún no completados, más antiguos primero.
     *
     * @param  integer $limit
     * @return array
     */
    public function getUnfinished($limit = 50)
    {
        return $this->db
            ->table(self::TABLE)
            ->in('status', array(self::STATUS_PENDING, self::STATUS_ERROR))
            ->asc('id')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Devuelve los trabajos no completados de una tarea concreta.
     *
     * @param  integer $task_id
     * @return array
     */
    public function getUnfinishedByTask($task_id)
    {
        return $this->db
            ->table(self::TABLE)
            ->eq('task_id', $task_id)
            ->in('status', array(self::STATUS_PENDING, self::STATUS_ERROR))
            ->asc('id')
            ->findAll();
    }

    /**
     * Último trabajo registrado para una tarea, para mostrar su estado en la
     * vista de la tarea.
     *
     * @param  integer $task_id
     * @return array|null
     */
    public function getLastByTask($task_id)
    {
        return $this->db->table(self::TABLE)->eq('task_id', $task_id)->desc('id')->findOne();
    }

    /**
     * Indica si una tarea tiene sincronización pendiente o fallida, que es
     * cuando debe aparecer el botón de reintento.
     *
     * @param  integer $task_id
     * @return boolean
     */
    public function hasUnfinished($task_id)
    {
        return $this->db
            ->table(self::TABLE)
            ->eq('task_id', $task_id)
            ->in('status', array(self::STATUS_PENDING, self::STATUS_ERROR))
            ->exists();
    }

    /**
     * Indica si ya hay una creación no completada encolada para la tarea.
     *
     * @param  integer $task_id
     * @return boolean
     */
    public function hasUnfinishedOperation($task_id, $operation)
    {
        return $this->db
            ->table(self::TABLE)
            ->eq('task_id', $task_id)
            ->eq('operation', $operation)
            ->in('status', array(self::STATUS_PENDING, self::STATUS_ERROR))
            ->exists();
    }

    /**
     * Marca un trabajo como sincronizado.
     *
     * @param  integer $id
     * @return boolean
     */
    public function markAsSynced($id)
    {
        return $this->db->table(self::TABLE)->eq('id', $id)->update(array(
            'status' => self::STATUS_SYNCED,
            'last_error' => '',
            'updated_at' => time(),
        ));
    }

    /**
     * Marca un trabajo como fallido y guarda el motivo.
     *
     * No hay reintento automático (regla de negocio 11): el trabajo queda a la
     * espera del botón manual o de la siguiente pasada del cron.
     *
     * @param  integer $id
     * @param  integer $attempts
     * @param  string  $message
     * @return boolean
     */
    public function markAsError($id, $attempts, $message)
    {
        return $this->db->table(self::TABLE)->eq('id', $id)->update(array(
            'status' => self::STATUS_ERROR,
            'attempts' => $attempts + 1,
            'last_error' => substr($message, 0, 2000),
            'updated_at' => time(),
        ));
    }

    /**
     * Decodifica el snapshot almacenado.
     *
     * @param  array $job
     * @return array
     */
    public function decodeSnapshot(array $job)
    {
        if (empty($job['snapshot'])) {
            return array();
        }

        $snapshot = json_decode($job['snapshot'], true);

        return is_array($snapshot) ? $snapshot : array();
    }
}
