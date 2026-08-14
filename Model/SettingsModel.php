<?php

namespace Kanboard\Plugin\NotionSync\Model;

use Kanboard\Core\Base;

/**
 * Configuración global del plugin (una sola fila).
 *
 * Guarda el token de integración de Notion y el database_id de la base "Tareas".
 * Ambos valores son únicos por instancia de Kanboard (regla de negocio 9).
 *
 * El token nunca se guarda en claro: se cifra con TokenCipher al escribir y se
 * descifra al leer, de modo que el resto del plugin sigue trabajando con el
 * token en claro sin enterarse.
 */
class SettingsModel extends Base
{
    const TABLE = 'notionsync_settings';

    /**
     * Devuelve la configuración global con el token ya descifrado, siempre con
     * las claves esperadas presentes.
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

        $settings['api_token'] = $this->notionTokenCipher->decrypt($settings['api_token']);

        return $settings;
    }

    /**
     * Indica si hay un token guardado todavía sin cifrar, para poder avisarlo en
     * la pantalla de configuración.
     *
     * @return boolean
     */
    public function hasPlainTextToken()
    {
        $stored = $this->db->table(self::TABLE)->findOneColumn('api_token');

        return ! empty($stored) && ! $this->notionTokenCipher->isEncrypted($stored);
    }

    /**
     * Guarda la configuración global.
     *
     * Si $values['api_token'] llega vacío se conserva el token existente: el
     * formulario nunca reenvía el token en claro, solo cuando el usuario escribe
     * uno nuevo. En cualquier caso se vuelve a cifrar al escribir, así que un
     * token heredado en claro queda cifrado al primer guardado.
     *
     * @param  array $values
     * @return boolean
     */
    public function save(array $values)
    {
        $current = $this->get();

        $token = isset($values['api_token']) ? trim($values['api_token']) : '';

        $data = array(
            'api_token' => $this->notionTokenCipher->encrypt($token !== '' ? $token : $current['api_token']),
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
