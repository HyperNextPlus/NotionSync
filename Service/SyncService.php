<?php

namespace Kanboard\Plugin\NotionSync\Service;

use Kanboard\Core\Base;
use Kanboard\Plugin\NotionSync\Exception\SyncException;
use Kanboard\Plugin\NotionSync\Model\FieldModel;
use Kanboard\Plugin\NotionSync\Model\QueueModel;

/**
 * Ejecuta un trabajo de la cola contra Notion.
 *
 * Es el único punto donde ocurre la sincronización real, y lo usan por igual el
 * comando de cron y el botón de reintento manual, de modo que ambos caminos se
 * comportan exactamente igual.
 */
class SyncService extends Base
{
    /**
     * Procesa un trabajo de la cola y actualiza su estado.
     *
     * @param  array $job
     * @return boolean true si quedó sincronizado
     */
    public function processJob(array $job)
    {
        try {
            $this->execute($job);
            $this->notionQueueModel->markAsSynced($job['id']);

            return true;
        } catch (\Exception $e) {
            $this->notionQueueModel->markAsError($job['id'], $job['attempts'], $e->getMessage());
            $this->logger->error('NotionSync: trabajo '.$job['id'].' falló => '.$e->getMessage());

            return false;
        }
    }

    /**
     * Despacha el trabajo según su operación.
     *
     * @param  array $job
     * @throws SyncException
     */
    private function execute(array $job)
    {
        if (! $this->notionSettingsModel->isConfigured()) {
            throw new SyncException(t('The plugin is not configured: the Notion token or the database identifier is missing.'));
        }

        switch ($job['operation']) {
            case QueueModel::OPERATION_CREATE:
                $this->createPage($job);
                break;

            case QueueModel::OPERATION_UPDATE_TITLE:
                $this->updateTitle($job);
                break;

            case QueueModel::OPERATION_DELETE:
                $this->applyDeleteAction($job);
                break;

            default:
                throw new SyncException(t('Unknown operation: %s', $job['operation']));
        }
    }

    /**
     * Crea la página en la base "Tareas" con todas las propiedades configuradas.
     *
     * Las propiedades se resuelven por completo antes de la primera llamada de
     * creación: si un campo relation no se puede resolver, la sincronización
     * falla entera y no se crea ninguna página (regla 5).
     *
     * @param  array $job
     * @throws SyncException
     */
    private function createPage(array $job)
    {
        if ($this->notionPageModel->exists($job['task_id'])) {
            return;
        }

        $task = $this->taskFinderModel->getDetails($job['task_id']);

        if (empty($task)) {
            throw new SyncException(t('Task #%d no longer exists in Kanboard.', $job['task_id']));
        }

        $fields = $this->notionFieldModel->getAllByProject($job['project_id']);

        if (empty($fields)) {
            throw new SyncException(t('The project no longer has any field configured for synchronization.'));
        }

        $settings = $this->notionSettingsModel->get();
        $properties = array();

        foreach ($fields as $field) {
            $properties[$field['property_name']] = $this->buildFieldValue($field, $task);
        }

        $page_id = $this->notionApiClient->createPage($settings['tasks_database_id'], $properties);
        $this->notionPageModel->save($job['task_id'], $job['project_id'], $page_id);
    }

    /**
     * Construye el valor de un campo.
     *
     * Las opciones de select y multi_select no necesitan tratamiento: Notion
     * agrega al esquema cualquier nombre de opción que todavía no exista,
     * siempre que la integración tenga permiso de escritura sobre la base. Un
     * status, en cambio, exige que la opción ya exista.
     *
     * @param  array $field
     * @param  array $task
     * @return array
     * @throws SyncException
     */
    private function buildFieldValue(array $field, array $task)
    {
        $value = $this->notionTemplateResolver->resolve($field['value_template'], $task);

        if ($field['property_type'] === FieldModel::TYPE_RELATION) {
            if (empty($field['relation_database_id'])) {
                throw new SyncException(t('The field "%s" is a relation but has no related database configured.', $field['property_name']));
            }

            // Se sustituye el texto por el page_id de la página relacionada: si
            // no se resuelve, la excepción aborta la creación entera y no queda
            // ninguna página a medio completar (regla 5).
            $value = $this->notionApiClient->findPageIdByExactTitle($field['relation_database_id'], $value);
        }

        return $this->notionApiClient->buildProperty($field['property_type'], $value);
    }

    /**
     * Reevalúa la plantilla del campo title y actualiza esa única propiedad.
     *
     * @param  array $job
     * @throws SyncException
     */
    private function updateTitle(array $job)
    {
        $page_id = $this->notionPageModel->getPageId($job['task_id']);

        if ($page_id === '') {
            throw new SyncException(t('The task does not have a page created in Notion yet.'));
        }

        $task = $this->taskFinderModel->getDetails($job['task_id']);

        if (empty($task)) {
            throw new SyncException(t('Task #%d no longer exists in Kanboard.', $job['task_id']));
        }

        $field = $this->notionFieldModel->getTitleField($job['project_id']);

        if (empty($field)) {
            throw new SyncException(t('The project does not have any property of type title configured.'));
        }

        $value = $this->notionTemplateResolver->resolve($field['value_template'], $task);

        $this->notionApiClient->updatePageProperty(
            $page_id,
            $field['property_name'],
            $this->notionApiClient->buildProperty(FieldModel::TYPE_TITLE, $value)
        );
    }

    /**
     * Aplica la acción configurada para la eliminación de la tarea.
     *
     * La página no se elimina ni se archiva: solo se le asigna el valor fijo
     * definido en la configuración del proyecto.
     *
     * @param  array $job
     * @throws SyncException
     */
    private function applyDeleteAction(array $job)
    {
        $page_id = $this->notionPageModel->getPageId($job['task_id']);

        if ($page_id === '') {
            throw new SyncException(t('The task has no associated page in Notion.'));
        }

        $action = $this->notionDeleteActionModel->getByProject($job['project_id']);

        if (trim($action['property_name']) === '' || trim($action['property_value']) === '') {
            throw new SyncException(t('The project does not have the delete action configured.'));
        }

        $this->notionApiClient->updatePageProperty(
            $page_id,
            $action['property_name'],
            $this->notionApiClient->buildProperty($action['property_type'], $action['property_value'])
        );
    }
}
