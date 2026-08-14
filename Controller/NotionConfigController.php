<?php

namespace Kanboard\Plugin\NotionSync\Controller;

use Kanboard\Controller\BaseController;

/**
 * Configuración global del plugin: token de integración y base "Tareas".
 *
 * Restringido a administradores de la instancia desde Plugin::initialize().
 */
class NotionConfigController extends BaseController
{
    public function show(array $values = array(), array $errors = array())
    {
        $settings = $this->notionSettingsModel->get();

        if (empty($values)) {
            $values = array(
                'api_token' => '',
                'tasks_database_id' => $settings['tasks_database_id'],
            );
        }

        $this->response->html($this->helper->layout->config('NotionSync:config/show', array(
            'title' => t('NotionSync settings'),
            'values' => $values,
            'errors' => $errors,
            'has_token' => $settings['api_token'] !== '',
            'has_plaintext_token' => $this->notionSettingsModel->hasPlainTextToken(),
            'is_configured' => $this->notionSettingsModel->isConfigured(),
        )));
    }

    public function save()
    {
        // El token viaja en el cuerpo del formulario, no en la query: por eso
        // checkCSRFForm() y no checkCSRFParam(), que lee de $_GET.
        $this->checkCSRFForm();
        $values = $this->request->getValues();
        $errors = $this->validate($values);

        if (! empty($errors)) {
            $this->flash->failure(t('Check the submitted values.'));
            $this->show($values, $errors);

            return;
        }

        $this->notionSettingsModel->save($values);
        $this->flash->success(t('Settings saved successfully.'));
        $this->response->redirect($this->helper->url->to('NotionConfigController', 'show', array('plugin' => 'NotionSync')));
    }

    /**
     * El token solo es obligatorio la primera vez: después, dejar el campo vacío
     * significa "conservar el token actual".
     *
     * @param  array $values
     * @return array
     */
    private function validate(array $values)
    {
        $errors = array();
        $settings = $this->notionSettingsModel->get();

        if (empty($values['tasks_database_id'])) {
            $errors['tasks_database_id'] = array(t('This field is required'));
        }

        if (empty($values['api_token']) && $settings['api_token'] === '') {
            $errors['api_token'] = array(t('This field is required'));
        }

        return $errors;
    }
}
