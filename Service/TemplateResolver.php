<?php

namespace Kanboard\Plugin\NotionSync\Service;

use Kanboard\Core\Base;

/**
 * Resuelve las plantillas de texto configuradas por el usuario.
 *
 * Una plantilla combina texto libre con variables entre dobles llaves. Solo se
 * soportan las siete variables listadas en las reglas de negocio; cualquier otra
 * secuencia {{...}} se deja intacta para que el usuario vea el error en Notion en
 * lugar de perder el texto silenciosamente.
 */
class TemplateResolver extends Base
{
    /**
     * Variables soportadas y su descripción, usadas también por la vista de
     * configuración para mostrar la ayuda al usuario.
     *
     * @return array
     */
    public function getSupportedVariables()
    {
        return array(
            '{{title_task}}'   => t('Task title'),
            '{{task_id}}'      => t('Task identifier in Kanboard'),
            '{{task_url}}'     => t('Direct link to the task in Kanboard'),
            '{{project_name}}' => t('Name of the Kanboard project'),
            '{{created_at}}'   => t('Task creation date (YYYY-MM-DD)'),
            '{{assignee}}'     => t('Name of the assigned user'),
            '{{description}}'  => t('Task description'),
        );
    }

    /**
     * Sustituye las variables de una plantilla con los datos de la tarea.
     *
     * @param  string $template
     * @param  array  $task      Tarea obtenida con TaskFinderModel::getDetails()
     * @return string
     */
    public function resolve($template, array $task)
    {
        if ($template === '' || $template === null) {
            return '';
        }

        return str_replace(
            array_keys($this->getValues($task)),
            array_values($this->getValues($task)),
            $template
        );
    }

    /**
     * Construye el diccionario variable => valor para una tarea.
     *
     * @param  array $task
     * @return array
     */
    public function getValues(array $task)
    {
        return array(
            '{{title_task}}'   => $this->value($task, 'title'),
            '{{task_id}}'      => $this->value($task, 'id'),
            '{{task_url}}'     => $this->getTaskUrl($task),
            '{{project_name}}' => $this->value($task, 'project_name'),
            '{{created_at}}'   => $this->getCreationDate($task),
            '{{assignee}}'     => $this->getAssignee($task),
            '{{description}}'  => $this->value($task, 'description'),
        );
    }

    /**
     * Enlace absoluto a la tarea.
     *
     * Depende de "application_url" en los ajustes de Kanboard: sin ese valor
     * configurado, Kanboard no puede construir enlaces absolutos fuera de una
     * petición web y el enlace apuntaría a localhost.
     *
     * @param  array $task
     * @return string
     */
    public function getTaskUrl(array $task)
    {
        if (empty($task['id'])) {
            return '';
        }

        return $this->helper->url->to(
            'TaskViewController',
            'show',
            array('task_id' => $task['id'], 'project_id' => $this->value($task, 'project_id')),
            '',
            true
        );
    }

    /**
     * Fecha de creación en formato AAAA-MM-DD, que es el que acepta una
     * propiedad de tipo date en Notion.
     *
     * @param  array $task
     * @return string
     */
    private function getCreationDate(array $task)
    {
        if (empty($task['date_creation'])) {
            return '';
        }

        return date('Y-m-d', $task['date_creation']);
    }

    /**
     * Nombre del asignado, con el nombre de usuario como alternativa cuando el
     * usuario no tiene nombre completo cargado.
     *
     * @param  array $task
     * @return string
     */
    private function getAssignee(array $task)
    {
        $name = $this->value($task, 'assignee_name');

        return $name !== '' ? $name : $this->value($task, 'assignee_username');
    }

    /**
     * @param  array  $task
     * @param  string $key
     * @return string
     */
    private function value(array $task, $key)
    {
        return isset($task[$key]) && $task[$key] !== null ? (string) $task[$key] : '';
    }
}
