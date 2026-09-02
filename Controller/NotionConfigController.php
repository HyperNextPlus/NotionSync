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

        $webhookToken = $this->configModel->get('webhook_token');

        $this->response->html($this->helper->layout->config('NotionSync:config/show', array(
            'title' => t('NotionSync settings'),
            'values' => $values,
            'errors' => $errors,
            'has_token' => $settings['api_token'] !== '',
            'has_plaintext_token' => $this->notionSettingsModel->hasPlainTextToken(),
            'is_configured' => $this->notionSettingsModel->isConfigured(),
            'has_webhook_token' => ! empty($webhookToken),
            'cron_pretty_url' => $this->buildCronUrl($webhookToken, true),
            'cron_query_url' => $this->buildCronUrl($webhookToken, false),
        )));
    }

    /**
     * URL del cron por URL, en sus dos formas.
     *
     * No se usa el helper de URLs porque este decide por su cuenta entre la ruta
     * amigable y la cadena de consulta según esté activada la reescritura, y aquí
     * hacen falta las dos a la vez: la amigable es más limpia pero depende de la
     * reescritura, y la de consulta funciona siempre.
     *
     * @param  string  $webhookToken
     * @param  bool    $pretty
     * @return string
     */
    private function buildCronUrl($webhookToken, $pretty)
    {
        $base = $this->helper->url->base();

        if ($pretty) {
            return $base.'notionsync/cron?'.http_build_query(array('token' => $webhookToken));
        }

        return $base.'?'.http_build_query(array(
            'controller' => 'NotionCronjobController',
            'action' => 'run',
            'plugin' => 'NotionSync',
            'token' => $webhookToken,
        ));
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
