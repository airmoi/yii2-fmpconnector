<?php
/**
 * @link https://github.com/airmoi/yii2-fmpconnector
 * @copyright Copyright (c) 2014 Romain Dunand
 * @license  MIT
 */

namespace airmoi\yii2fmconnector\db;

/**
 * TableSchema represents the metadata of a database table.
 *
 * @author Romain Dunand <airmoi@gmail.com>
 * @since 1.0
 */
class TableSchema extends \yii\db\TableSchema
{
    public $baseTableName;
    
    public function isForeignKey(ColumnSchema $column){
        foreach($this->foreignKeys as $fk){
            if(array_key_exists($column->name, $fk))
                return true;
        }
    }
}
