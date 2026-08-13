<?php

namespace Kanboard\Plugin\NotionSync\Model;

use Kanboard\Event\TaskEvent;

/**
 * Override del TaskModel del core.
 *
 * Kanboard no dispara ningún evento al eliminar una tarea: TaskModel::remove()
 * borra la fila y devuelve el resultado sin notificar a nadie. Este override,
 * registrado desde Plugin::getClasses(), reemplaza la clave "taskModel" del
 * contenedor y añade el evento que falta, sin tocar el código del core.
 *
 * Se hace en el modelo y no en TaskSuppressionController para cubrir también la
 * eliminación vía API JSON-RPC, no solo la de la interfaz web.
 */
class TaskModel extends \Kanboard\Model\TaskModel
{
    const EVENT_DELETE = 'notionsync.task.delete';

    /**
     * Elimina la tarea y notifica el borrado.
     *
     * La tarea se captura antes del borrado porque después ya no es
     * recuperable, y el listener necesita al menos su id y su project_id.
     *
     * @param  integer $task_id
     * @return boolean
     */
    public function remove($task_id)
    {
        $task = $this->taskFinderModel->getById($task_id);
        $result = parent::remove($task_id);

        if ($result && ! empty($task)) {
            $this->dispatcher->dispatch(
                new TaskEvent(array('task_id' => $task_id, 'task' => $task)),
                self::EVENT_DELETE
            );
        }

        return $result;
    }
}
