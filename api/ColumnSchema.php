<?php
/**
 * @link https://github.com/airmoi/yii2-fmpconnector
 * @copyright Copyright (c) 2014 Romain Dunand
 * @license  MIT
 */

namespace airmoi\yii2fmconnector\api;
use yii;
use yii\base\Object;


/**
 * ColumnSchema class describes the metadata of a column in a database table.
 * This extension hooks typecast to DB to handle correct dataType casting has 
 * PDO binding functions are buggy with FileMaker ODBC connector
 *
 * @author Romain Dunand <airmoi@gmail.com>
 * @since 1.0
 */
class ColumnSchema extends \yii\db\ColumnSchema
{
    public $fmType;
    public $global;
    
    public $isRelated;
    public $relationName;
    
    public $valueList;
    /**
     * Converts the input value according to [[phpType]] after retrieval from the database.
     * If the value is null or an [[Expression]], it will not be converted.
     * @param mixed $value input value
     * @return mixed converted value
     */
    public function phpTypecast($value)
    {
        if ($value === '' && $this->type !== Schema::TYPE_TEXT && $this->type !== Schema::TYPE_STRING && $this->type !== Schema::TYPE_BINARY) {
            return null;
        }
        if ($value === null || gettype($value) === $this->phpType || $value instanceof Expression) {
            return $value;
        }
        switch ($this->phpType) {
            case 'resource':
            case 'string':
                return is_resource($value) ? $value : (string) $value;
            case 'integer':
                return (integer) $value;
            case 'boolean':
                return (boolean) $value;
            case 'double':
                return (double) $value;
            case 'date':
                return (string) $value;
            case 'timestamp':
                return (string) $value;
        }

        return $value;
    }

    /**
     * Converts the input value according to [[type]] and [[dbType]] for use in a db query.
     * If the value is null or an [[Expression]], it will not be converted.
     * @param mixed $value input value
     * @return mixed converted value. This may also be an array containing the value as the first element
     * and the PDO type as the second element.
     */
    public function dbTypecast($value)
    {
       if( (  $value==='' || $value===null ) && $this->allowNull) {
            return '';
       }
    }
}
