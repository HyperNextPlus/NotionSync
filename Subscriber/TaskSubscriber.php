<?php

namespace Kanboard\Plugin\NotionSync\Subscriber;

use Kanboard\Core\Base;
use Kanboard\Event\GenericEvent;
use Kanboard\Plugin\NotionSync\Model\QueueModel;

/**
 * Traduce los eventos de tarea de Kanboard en trabajos de la cola.
 *
 * Aquí nunca se llama a la API de Notion: encolar es una escritura local y
 * barata, de modo que crear, editar o eliminar una tarea sigue funcionando con
 * normalidad aunque Notion esté caído o mal configurado (regla de negocio 12).
 */
class TaskSubscriber extends Base
{
    /**
     * Tarea creada: se encola la creación de la página en Notion.
     *
     * @param GenericEvent $event
     */
    public function onTaskCreate(GenericEvent $event)
    {
        $task = $this->getTask($event);

        if (empty($task) || ! $this->notionFieldModel->hasFields($task['project_id'])) {
            return;
        }

        $this->notionQueueModel->enqueue(
            $task['id'],
            $task['project_id'],
            QueueModel::OPERATION_CREATE
        );
    }

    /**
     * Tarea editada: solo se reevalúa la propiedad de tipo title (regla 6).
     *
     * No se encola nada si la página todavía no existe, porque la creación
     * pendiente ya tomará el título actual cuando se ejecute.
     *
     * @param GenericEvent $event
     */
    public function onTaskUpdate(GenericEvent $event)
    {
        $task = $this->getTask($event);

        if (empty($task) || ! $this->notionPageModel->exists($task['id'])) {
            return;
        }

        if (! $this->notionFieldModel->getTitleField($task['project_id'])) {
            return;
        }

        if ($this->notionQueueModel->hasUnfinishedOperation($task['id'], QueueModel::OPERATION_UPDATE_TITLE)) {
            return;
        }

        $this->notionQueueModel->enqueue(
            $task['id'],
            $task['project_id'],
            QueueModel::OPERATION_UPDATE_TITLE
        );
    }

    /**
     * Tarea eliminada: se encola la actualización de la propiedad configurada en
     * la acción de borrado del proyecto (regla 7).
     *
     * El evento lo emite el TaskModel del plugin, porque el del core no notifica
     * las eliminaciones. La tarea ya no existe en la base de datos, así que el
     * snapshot que viaja en el evento es la única fuente de datos disponible.
     *
     * @param GenericEvent $event
     */
    public function onTaskDelete(GenericEvent $event)
    {
        $values = $event->getAll();
        $task = isset($values['task']) ? $values['task'] : array();

        if (empty($task) || ! $this->notionPageModel->exists($task['id'])) {
            return;
        }

        if (! $this->notionDeleteActionModel->isConfigured($task['project_id'])) {
            return;
        }

        $this->notionQueueModel->enqueue(
            $task['id'],
            $task['project_id'],
            QueueModel::OPERATION_DELETE,
            $task
        );
    }

    /**
     * @param  GenericEvent $event
     * @return array
     */
    private function getTask(GenericEvent $event)
    {
        $task_id = $event->getTaskId();

        if (empty($task_id)) {
            return array();
        }

        $task = $this->taskFinderModel->getDetails($task_id);

        return empty($task) ? array() : $task;
    }
}
