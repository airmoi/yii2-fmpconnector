<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */


namespace airmoi\yii2fmconnector\gii\crud;

use yii\helpers\Inflector;
/**
 * Generates CRUD
 *
 * @property array $columnNames Model column names. This property is read-only.
 * @property string $controllerID The controller ID (without the module ID prefix). This property is
 * read-only.
 * @property array $searchAttributes Searchable attributes. This property is read-only.
 * @property boolean|\yii\db\TableSchema $tableSchema This property is read-only.
 * @property string $viewPath The controller view path. This property is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
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
        if ($tableSchema === false || !isset($tableSchema->columns[$attribute])) {
            if (preg_match('/^(password|pass|passwd|passcode)$/i', $attribute)) {
                return "\$form->field(\$model, '$attribute')->passwordInput()";
            } else {
                return "\$form->field(\$model, '$attribute')";
            }
        }
        $column = $tableSchema->columns[$attribute];
        if ($column->phpType === 'boolean') {
            return "\$form->field(\$model, '$attribute')->checkbox()";
        } elseif ($column->type === 'text') {
            return "\$form->field(\$model, '$attribute')->textarea(['rows' => 6])";
        } else {
            if (preg_match('/^(password|pass|passwd|passcode)$/i', $column->name)) {
                $input = 'passwordInput';
            } else {
                $input = 'textInput';
            }
            /* Search if column is a foreign key and generate dropDownList*/
            foreach ( $tableSchema->foreignKeys as $relation) {
                if ( array_key_exists($attribute, $relation)) {
                    return "\$form->field(\$model, '$attribute')->dropDownList("
                    . Inflector::id2camel($relation[0], '_')."::valueList()" .", ['prompt' => ''])";
                }
            }
            
            if ($column->phpType !== 'string' || $column->size === null) {
                return "\$form->field(\$model, '$attribute')->$input()";
            } else {
                return "\$form->field(\$model, '$attribute')->$input(['maxlength' => $column->size])";
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
            $models[] = "use app\models\\" . Inflector::id2camel($relation[0], '_');
        }
        return implode ( "\r\n", $models);
    }
}
