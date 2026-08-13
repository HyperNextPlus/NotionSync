<?php

namespace Kanboard\Plugin\NotionSync\Model;

use Kanboard\Core\Base;

/**
 * Relación task_id (Kanboard) <-> page_id (Notion).
 *
 * Permite ubicar la página correspondiente en ediciones, eliminaciones y
 * reintentos. Deliberadamente sin clave foránea a la tabla tasks: el vínculo
 * debe sobrevivir a la eliminación de la tarea en Kanboard para poder marcarla
 * como cancelada en Notion.
 */
class PageModel extends Base
{
    const TABLE = 'notionsync_pages';

    /**
     * Devuelve el page_id de Notion asociado a una tarea.
     *
     * @param  integer $task_id
     * @return string
     */
    public function getPageId($task_id)
    {
        $page_id = $this->db->table(self::TABLE)->eq('task_id', $task_id)->findOneColumn('page_id');

        return $page_id ?: '';
    }

    /**
     * @param  integer $task_id
     * @return boolean
     */
    public function exists($task_id)
    {
        return $this->db->table(self::TABLE)->eq('task_id', $task_id)->exists();
    }

    /**
     * Registra (o actualiza) la relación entre la tarea y la página de Notion.
     *
     * @param  integer $task_id
     * @param  integer $project_id
     * @param  string  $page_id
     * @return boolean
     */
    public function save($task_id, $project_id, $page_id)
    {
        if ($this->exists($task_id)) {
            return $this->db->table(self::TABLE)->eq('task_id', $task_id)->update(array(
                'page_id' => $page_id,
                'project_id' => $project_id,
            ));
        }

        return $this->db->table(self::TABLE)->insert(array(
            'task_id' => $task_id,
            'project_id' => $project_id,
            'page_id' => $page_id,
            'created_at' => time(),
        ));
    }
}
