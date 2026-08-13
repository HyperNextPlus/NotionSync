<?php

namespace Kanboard\Plugin\NotionSync\Model;

use Kanboard\Core\Base;

/**
 * Acción "al eliminar tarea", configurada por proyecto.
 *
 * Define qué propiedad de la página de Notion se actualiza y con qué valor fijo
 * cuando la tarea se elimina en Kanboard (regla de negocio 7). La página nunca
 * se elimina ni se archiva.
 */
class DeleteActionModel extends Base
{
    const TABLE = 'notionsync_delete_actions';

    /**
     * Devuelve la acción configurada para el proyecto.
     *
     * Cuando no hay nada guardado devuelve la sugerencia por defecto del brief
     * (Estado = "Cancelado"), que el usuario puede editar libremente.
     *
     * @param  integer $project_id
     * @return array
     */
    public function getByProject($project_id)
    {
        $action = $this->db->table(self::TABLE)->eq('project_id', $project_id)->findOne();

        if (empty($action)) {
            return array(
                'project_id' => $project_id,
                'property_name' => 'Estado',
                'property_type' => FieldModel::TYPE_STATUS,
                'property_value' => 'Cancelado',
            );
        }

        return $action;
    }

    /**
     * Indica si el proyecto tiene una acción de eliminación utilizable.
     *
     * @param  integer $project_id
     * @return boolean
     */
    public function isConfigured($project_id)
    {
        $action = $this->db->table(self::TABLE)->eq('project_id', $project_id)->findOne();

        return ! empty($action)
            && trim($action['property_name']) !== ''
            && trim($action['property_value']) !== '';
    }

    /**
     * Guarda la acción de eliminación del proyecto.
     *
     * @param  integer $project_id
     * @param  array   $values
     * @return boolean
     */
    public function save($project_id, array $values)
    {
        $data = array(
            'property_name' => isset($values['property_name']) ? trim($values['property_name']) : '',
            'property_type' => isset($values['property_type']) ? $values['property_type'] : FieldModel::TYPE_STATUS,
            'property_value' => isset($values['property_value']) ? trim($values['property_value']) : '',
        );

        if ($this->db->table(self::TABLE)->eq('project_id', $project_id)->exists()) {
            return $this->db->table(self::TABLE)->eq('project_id', $project_id)->update($data);
        }

        $data['project_id'] = $project_id;

        return $this->db->table(self::TABLE)->insert($data);
    }
}
