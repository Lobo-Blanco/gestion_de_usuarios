<?php
/**
 * @see KumbiaActiveRecord
 */
require_once CORE_PATH.'libs/kumbia_active_record/kumbia_active_record.php';

/**
 * ActiveRecord
 *
 * Clase padre ActiveRecord para añadir tus métodos propios
 *
 * @category Kumbia
 * @package Db
 * @subpackage ActiveRecord
 */
abstract class ActiveRecord extends KumbiaActiveRecord
{
    /**
     * Verifica si el registro es nuevo
     * @return bool
     */
    public function is_new_record()
    {
        // Si no tiene ID o el ID es cero, es un nuevo registro
        return empty($this->id) || $this->id == 0;
    }
}
