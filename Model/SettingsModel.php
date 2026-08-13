<?php

namespace Kanboard\Plugin\NotionSync\Model;

use Kanboard\Core\Base;

/**
 * Configuración global del plugin (una sola fila).
 *
 * Guarda el token de integración de Notion y el database_id de la base "Tareas".
 * Ambos valores son únicos por instancia de Kanboard (regla de negocio 9).
 */
class SettingsModel extends Base
{
    const TABLE = 'notionsync_settings';

    /**
     * Devuelve la configuración global, siempre con las dos claves presentes.
     *
     * @return array
     */
    public function get()
    {
        $settings = $this->db->table(self::TABLE)->findOne();

        if (empty($settings)) {
            return array(
                'api_token' => '',
                'tasks_database_id' => '',
                'updated_at' => 0,
            );
        }

        return $settings;
    }

    /**
     * Guarda la configuración global.
     *
     * Si $values['api_token'] llega vacío se conserva el token existente: el
     * formulario nunca reenvía el token en claro, solo cuando el usuario escribe
     * uno nuevo.
     *
     * @param  array $values
     * @return boolean
     */
    public function save(array $values)
    {
        $current = $this->get();

        $token = isset($values['api_token']) ? trim($values['api_token']) : '';

        $data = array(
            'api_token' => $token !== '' ? $token : $current['api_token'],
            'tasks_database_id' => isset($values['tasks_database_id']) ? trim($values['tasks_database_id']) : '',
            'updated_at' => time(),
        );

        if ($this->db->table(self::TABLE)->exists()) {
            return $this->db->table(self::TABLE)->update($data);
        }

        return $this->db->table(self::TABLE)->insert($data);
    }

    /**
     * Indica si el plugin tiene lo mínimo para hablar con la API de Notion.
     *
     * @return boolean
     */
    public function isConfigured()
    {
        $settings = $this->get();

        return $settings['api_token'] !== '' && $settings['tasks_database_id'] !== '';
    }
}
