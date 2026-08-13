<?php

namespace Kanboard\Plugin\NotionSync\Model;

use Kanboard\Core\Base;

/**
 * Mapeo de propiedades de Notion configurado por proyecto.
 *
 * Cada fila es una propiedad de la base "Tareas" que el usuario decidió
 * sincronizar, con su tipo y la plantilla de texto que produce su valor.
 */
class FieldModel extends Base
{
    const TABLE = 'notionsync_fields';

    const TYPE_TITLE        = 'title';
    const TYPE_RICH_TEXT    = 'rich_text';
    const TYPE_SELECT       = 'select';
    const TYPE_MULTI_SELECT = 'multi_select';
    const TYPE_STATUS       = 'status';
    const TYPE_DATE         = 'date';
    const TYPE_URL          = 'url';
    const TYPE_RELATION     = 'relation';

    /**
     * Tipos de propiedad soportados por esta versión del plugin.
     *
     * @return array
     */
    public function getTypes()
    {
        return array(
            self::TYPE_TITLE        => t('Title (title)'),
            self::TYPE_RICH_TEXT    => t('Rich text (rich_text)'),
            self::TYPE_SELECT       => t('Select (select)'),
            self::TYPE_MULTI_SELECT => t('Multi-select (multi_select)'),
            self::TYPE_STATUS       => t('Status (status)'),
            self::TYPE_DATE         => t('Date (date)'),
            self::TYPE_URL          => t('URL (url)'),
            self::TYPE_RELATION     => t('Relation (relation)'),
        );
    }

    /**
     * Devuelve todos los campos configurados para un proyecto.
     *
     * @param  integer $project_id
     * @return array
     */
    public function getAllByProject($project_id)
    {
        return $this->db
            ->table(self::TABLE)
            ->eq('project_id', $project_id)
            ->asc('position')
            ->asc('id')
            ->findAll();
    }

    /**
     * Devuelve un campo por su identificador.
     *
     * @param  integer $field_id
     * @return array|null
     */
    public function getById($field_id)
    {
        return $this->db->table(self::TABLE)->eq('id', $field_id)->findOne();
    }

    /**
     * Devuelve el campo de tipo title del proyecto, si existe.
     *
     * @param  integer $project_id
     * @return array|null
     */
    public function getTitleField($project_id)
    {
        return $this->db
            ->table(self::TABLE)
            ->eq('project_id', $project_id)
            ->eq('property_type', self::TYPE_TITLE)
            ->findOne();
    }

    /**
     * Un proyecto está sincronizado cuando tiene al menos un campo configurado
     * (regla de negocio 1).
     *
     * @param  integer $project_id
     * @return boolean
     */
    public function hasFields($project_id)
    {
        return $this->db->table(self::TABLE)->eq('project_id', $project_id)->exists();
    }

    /**
     * Agrega un campo al proyecto.
     *
     * @param  integer $project_id
     * @param  array   $values
     * @return boolean
     */
    public function create($project_id, array $values)
    {
        return $this->db->table(self::TABLE)->insert(array(
            'project_id' => $project_id,
            'property_name' => trim($values['property_name']),
            'property_type' => $values['property_type'],
            'value_template' => isset($values['value_template']) ? $values['value_template'] : '',
            'relation_database_id' => isset($values['relation_database_id']) ? trim($values['relation_database_id']) : '',
            'position' => $this->getNextPosition($project_id),
        ));
    }

    /**
     * Actualiza un campo existente.
     *
     * @param  integer $field_id
     * @param  array   $values
     * @return boolean
     */
    public function update($field_id, array $values)
    {
        return $this->db->table(self::TABLE)->eq('id', $field_id)->update(array(
            'property_name' => trim($values['property_name']),
            'property_type' => $values['property_type'],
            'value_template' => isset($values['value_template']) ? $values['value_template'] : '',
            'relation_database_id' => isset($values['relation_database_id']) ? trim($values['relation_database_id']) : '',
        ));
    }

    /**
     * Elimina un campo del proyecto.
     *
     * @param  integer $field_id
     * @return boolean
     */
    public function remove($field_id)
    {
        return $this->db->table(self::TABLE)->eq('id', $field_id)->remove();
    }

    /**
     * @param  integer $project_id
     * @return integer
     */
    private function getNextPosition($project_id)
    {
        return (int) $this->db->table(self::TABLE)->eq('project_id', $project_id)->count() + 1;
    }
}
