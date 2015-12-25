<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */


namespace airmoi\yii2fmconnector\gii\api\crud;

use yii\helpers\Inflector;
use airmoi\yii2fmconnector\api\Schema;
/**
 * Generates CRUD
 *
 * @property array $columnNames Model column names. This property is read-only.
 * @property string $controllerID The controller ID (without the module ID prefix). This property is
 * read-only.
 * @property array $searchAttributes Searchable attributes. This property is read-only.
 * @property boolean|\airmoi\yii2fmconnector\api\TableSchema $tableSchema This property is read-only.
 * @property string $viewPath The controller view path. This property is read-only.
 *
 * @author Romain Dunand airmoi@gmail.com>
 * @since 2.0
 */
class Generator extends \yii\gii\generators\crud\Generator
{
    /**
     * Generates code for active field
     * @param string $attribute
     * @return string
     */
    public function generateActiveField($attribute)
    {
        $tableSchema = $this->getTableSchema();
        if($pos = strpos($attribute, '.')){
            $relationName = substr($attribute , 0 , $pos);
            $attribute = substr($attribute, $pos+1);
            $tableSchema = $tableSchema->relations[ $relationName ];
            $modelVar = '$model->'.$relationName;
        } else {
            $modelVar = '$model';
        }
        
        if ($tableSchema === false || !isset($tableSchema->columns[$attribute])) {
            if (preg_match('/^(password|pass|passwd|passcode)$/i', $attribute)) {
                return "\$form->field(\$model, '$attribute')->passwordInput()";
            } else {
                return "\$form->field(\$model, '$attribute')";
            }
        }
        $column = $tableSchema->columns[$attribute];
        if ($column->phpType === 'boolean') {
            return "\$form->field($modelVar, '$attribute')->checkbox()";
        } elseif ($column->type === 'text') {
            return "\$form->field($modelVar, '$attribute')->textarea(['rows' => 6])";
        } else {
            if (preg_match('/^(password|pass|passwd|passcode)$/i', $column->name)) {
                $input = 'passwordInput';
            } else {
                $input = 'textInput';
            }
            /* Search if column is a foreign key and generate dropDownList*/
            /*foreach ( $tableSchema->foreignKeys as $relation) {
                if ( array_key_exists($attribute, $relation)) {
                    return "\$form->field(\$model, '$attribute')->dropDownList("
                    . Inflector::id2camel($relation[0], '_')."::valueList()" .", ['prompt' => ''])";
                }
            }*/
            
            if ($column->phpType !== 'string' || $column->size === null) {
                return "\$form->field($modelVar, '$attribute')->$input()";
            } else {
                return "\$form->field($modelVar, '$attribute')->$input(['maxlength' => $column->size])";
            }
        }
    }
    
    /**
     * Generates code namespaces potentially used in views (related models)
     * @return string
     */
    public function generateNamespaces()
    {
        $tableSchema = $this->getTableSchema();
        if ($tableSchema === false) {
            return ;
        }
        
        $models = [];
        foreach ( $tableSchema->foreignKeys as $relation) {
            $models[] = "use app\models\\" . Inflector::id2camel($relation[0], '_').";";
        }
        return implode ( "\r\n", $models);
    }
    /**
     * Generates search conditions
     * @return array
     */
    public function generateSearchConditions()
    {
        $columns = [];
        if (($table = $this->getTableSchema()) === false) {
            $class = $this->modelClass;
            /* @var $model \yii\base\Model */
            $model = new $class();
            foreach ($model->attributes() as $attribute) {
                $columns[$attribute] = 'unknown';
            }
        } else {
            foreach ($table->columns as $column) {
                $columns[$column->name] = $column->type;
            }
        }

        $likeConditions = [];
        $hashConditions = [];
        foreach ($columns as $column => $type) {
            switch ($type) {
                case Schema::TYPE_SMALLINT:
                case Schema::TYPE_INTEGER:
                case Schema::TYPE_BIGINT:
                case Schema::TYPE_BOOLEAN:
                case Schema::TYPE_FLOAT:
                case Schema::TYPE_DECIMAL:
                case Schema::TYPE_MONEY:
                case Schema::TYPE_DATE:
                case Schema::TYPE_TIME:
                case Schema::TYPE_DATETIME:
                case Schema::TYPE_TIMESTAMP:
                    $hashConditions[] = "'{$column}' => \$this->{$column},";
                    break;
                default:
                    $hashConditions[] = "'{$column}' => \$this->{$column},";
                    break;
            }
        }

        $conditions = [];
        if (!empty($hashConditions)) {
            $conditions[] = "\$query->andFilterWhere([\n"
                . str_repeat(' ', 12) . implode("\n" . str_repeat(' ', 12), $hashConditions)
                . "\n" . str_repeat(' ', 8) . "]);\n";
        }
        if (!empty($likeConditions)) {
            $conditions[] = "\$query" . implode("\n" . str_repeat(' ', 12), $likeConditions) . ";\n";
        }

        return $conditions;
    }
    
    /**
     * Generates validation rules for the search model.
     * @return array the generated validation rules
     */
    public function generateSearchRules()
    {
        if (($table = $this->getTableSchema()) === false) {
            return ["[['" . implode("', '", $this->getColumnNames()) . "'], 'safe']"];
        }
        $types = [];
        foreach ($table->columns as $column) {
            switch ($column->type) {
                case Schema::TYPE_BINARY:
                    break;
                case Schema::TYPE_SMALLINT:
                case Schema::TYPE_INTEGER:
                case Schema::TYPE_BIGINT:
                    $types['integer'][] = $column->name;
                    break;
                case Schema::TYPE_BOOLEAN:
                    $types['boolean'][] = $column->name;
                    break;
                case Schema::TYPE_FLOAT:
                case Schema::TYPE_DECIMAL:
                case Schema::TYPE_MONEY:
                    $types['number'][] = $column->name;
                    break;
                case Schema::TYPE_DATE:
                case Schema::TYPE_TIME:
                case Schema::TYPE_DATETIME:
                case Schema::TYPE_TIMESTAMP:
                default:
                    $types['safe'][] = $column->name;
                    break;
            }
        }

        $rules = [];
        foreach ($types as $type => $columns) {
            $rules[] = "[['" . implode("', '", $columns) . "'], '$type']";
        }

        return $rules;
    }
    
    /**
     * Returns table schema for current model class or false if it is not an active record
     * @return boolean|\airmoi\yii2fmconnector\api\TableSchema
     */
    public function getTableSchema()
    {
        /* @var $class ActiveRecord */
        $class = $this->modelClass;
        if (is_subclass_of($class, 'airmoi\yii2fmconnector\api\FileMakerActiveRecord')) {
            return $class::getTableSchema();
        } else {
            return false;
        }
    }
}