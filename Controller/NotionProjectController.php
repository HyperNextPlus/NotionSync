<?php

namespace Kanboard\Plugin\NotionSync\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Plugin\NotionSync\Model\FieldModel;

/**
 * Configuración de sincronización por proyecto.
 *
 * El usuario agrega las propiedades de Notion una por una: nombre, tipo y
 * plantilla de valor. La pantalla arranca vacía, sin ningún campo precargado, de
 * modo que un proyecto recién creado no sincroniza nada (regla 1).
 *
 * Restringido al manager del proyecto desde Plugin::initialize().
 */
class NotionProjectController extends BaseController
{
    public function show(array $values = array(), array $errors = array())
    {
        $project = $this->getProject();

        $this->response->html($this->helper->layout->project('NotionSync:project/show', array(
            'project' => $project,
            'title' => t('Notion synchronization'),
            'fields' => $this->notionFieldModel->getAllByProject($project['id']),
            'types' => $this->notionFieldModel->getTypes(),
            'variables' => $this->notionTemplateResolver->getSupportedVariables(),
            'delete_action' => $this->notionDeleteActionModel->getByProject($project['id']),
            'is_configured' => $this->notionSettingsModel->isConfigured(),
            'values' => $values,
            'errors' => $errors,
        )));
    }

    public function saveField()
    {
        $project = $this->getProject();
        $this->checkCSRFForm();

        $values = $this->request->getValues();
        $errors = $this->validateField($project['id'], $values);

        if (! empty($errors)) {
            $this->show($values, $errors);

            return;
        }

        $this->notionFieldModel->create($project['id'], $values);
        $this->flash->success(t('Field added successfully.'));
        $this->redirectToProject($project);
    }

    public function updateField()
    {
        $project = $this->getProject();
        $this->checkCSRFForm();

        $values = $this->request->getValues();
        $field = $this->notionFieldModel->getById($this->request->getIntegerParam('field_id'));

        if (empty($field) || $field['project_id'] != $project['id']) {
            $this->flash->failure(t('Field not found.'));
            $this->redirectToProject($project);

            return;
        }

        $errors = $this->validateField($project['id'], $values, $field['id']);

        if (! empty($errors)) {
            $this->show($values, $errors);

            return;
        }

        $this->notionFieldModel->update($field['id'], $values);
        $this->flash->success(t('Field updated successfully.'));
        $this->redirectToProject($project);
    }

    public function removeField()
    {
        $project = $this->getProject();
        $this->checkCSRFParam();

        $field = $this->notionFieldModel->getById($this->request->getIntegerParam('field_id'));

        if (! empty($field) && $field['project_id'] == $project['id']) {
            $this->notionFieldModel->remove($field['id']);
            $this->flash->success(t('Field removed successfully.'));
        } else {
            $this->flash->failure(t('Field not found.'));
        }

        $this->redirectToProject($project);
    }

    public function saveDeleteAction()
    {
        $project = $this->getProject();
        $this->checkCSRFForm();

        $this->notionDeleteActionModel->save($project['id'], $this->request->getValues());
        $this->flash->success(t('Delete action saved successfully.'));
        $this->redirectToProject($project);
    }

    /**
     * @param  integer $project_id
     * @param  array   $values
     * @param  integer $ignore_field_id Campo que se está editando
     * @return array
     */
    private function validateField($project_id, array $values, $ignore_field_id = 0)
    {
        $errors = array();

        if (empty($values['property_name'])) {
            $errors['property_name'] = array(t('This field is required'));
        }

        if (empty($values['property_type']) || ! array_key_exists($values['property_type'], $this->notionFieldModel->getTypes())) {
            $errors['property_type'] = array(t('Invalid property type.'));
        }

        if (isset($values['property_type']) && $values['property_type'] === FieldModel::TYPE_RELATION && empty($values['relation_database_id'])) {
            $errors['relation_database_id'] = array(t('A relation property requires the identifier of the related database.'));
        }

        // Notion admite una sola propiedad de tipo title por base de datos, y la
        // sincronización de ediciones depende de que esa propiedad sea única.
        if (isset($values['property_type']) && $values['property_type'] === FieldModel::TYPE_TITLE) {
            $existing = $this->notionFieldModel->getTitleField($project_id);

            if (! empty($existing) && $existing['id'] != $ignore_field_id) {
                $errors['property_type'] = array(t('This project already has a property of type title.'));
            }
        }

        if (! empty($errors)) {
            $this->flash->failure(t('Check the submitted values.'));
        }

        return $errors;
    }

    private function redirectToProject(array $project)
    {
        $this->response->redirect($this->helper->url->to('NotionProjectController', 'show', array(
            'project_id' => $project['id'],
            'plugin' => 'NotionSync',
        )));
    }
}
