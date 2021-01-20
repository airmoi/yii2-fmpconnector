<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */


namespace airmoi\yii2fmconnector\gii\api\nuxt;

use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use airmoi\yii2fmconnector\api\Schema;
use yii\gii\CodeFile;
use yii\helpers\StringHelper;

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
     * @inheritdoc
     */
    public function generate()
    {
        $controllerFile = Yii::getAlias('@' . str_replace('\\', '/', ltrim($this->controllerClass, '\\')) . '.php');

        $files = [
            new CodeFile($controllerFile, $this->render('controller.php')),
        ];

        if (!empty($this->searchModelClass)) {
            $searchModel = Yii::getAlias('@' . str_replace('\\', '/', ltrim($this->searchModelClass, '\\') . '.php'));
            $files[] = new CodeFile($searchModel, $this->render('search.php'));
        }


        $folders = [
            'pages' => [$this->controllerID, 'vue'],
            'components' => [$this->controllerID, 'vue'],
            'store' => [Inflector::camel2id(StringHelper::basename($this->modelClass)), 'js']
        ];
        foreach ($folders as $folder => $ext) {
            $files = ArrayHelper::merge($files, $this->generateViews($folder, $ext));
        }

        return $files;
    }

    public function generateViews($folder, $opts)
    {
        list($folderPath, $ext) = $opts;
        $files = [];
        $viewPath = $this->getViewPath() . '/' . $folder . '/' . $folderPath;
        $templatePath = $this->getTemplatePath() . '/views/' . $folder;
        foreach (scandir($templatePath) as $file) {
            if (empty($this->searchModelClass) && $file === '_search.php') {
                continue;
            }
            if (is_file($templatePath . '/' . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $filename = pathinfo($file, PATHINFO_FILENAME) . '.' . $ext;
                $files[] = new CodeFile("$viewPath/$filename", $this->render("views/$folder/$file"));
            }
        }
        return $files;
    }

    /**
     * Generates code for active field
     * @param string $attribute
     * @return string
     */
    public function generateActiveField($attribute, $tableShema = null)
    {
        $tableSchema = $tableShema === null ? $this->getTableSchema() : $tableShema;
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
        if ($column->valueList) {
            return "\$form->field(\$model, '$attribute')->dropDownList(\$model->valueList('$attribute'), ['prompt' => 'Select a value' ])";
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

    public function generateGridViewColumn($column, $isComment = false, $relationName = null, $repetition = null){
        $commentString = $isComment ? '//' : '' ;
        $attributeName = ($relationName !== null ? $relationName . '.' : '' ) . $column->name . ($repetition !== null ? "[$repetition]" : '');
        if ($column->dbType == 'container') {
            return "            $commentString [\n"
               . "              $commentString'attribute' => '".$attributeName."',\n"
               . "              $commentString'format' => 'image',\n"
               . "              $commentString'value' => function(\$model) { return yii\helpers\Url::to(['container', 'token' => \$model->encryptContainerUrl(\$model->getAttribute('".$attributeName."'))]);},\n"
               ."            $commentString ],\n";
        }
        else {
            $format = $this->generateColumnFormat($column);
            return "            $commentString'" . $attributeName . ($format === 'text' ? "" : ":" . $format) . "',\n";
        }
    }

    public function generateListViewColumn($column, $isComment = false, $relationName = null, $repetition = null){
        $commentString = $isComment ? '//' : '' ;
        $attributeName = ($relationName !== null ? $relationName . '.' : '' ) . $column->name . ($repetition !== null ? "[$repetition]" : '');

        if ($column->dbType == 'container') {
            echo "           $commentString [\n"
               . "              $commentString 'attribute' => '".$attributeName."',\n"
               . "              $commentString 'format' => 'image',\n"
               . "              $commentString 'value' => yii\helpers\Url::to(['container', 'token' => \$model->encryptContainerUrl(\$model->getAttribute('".$attributeName."'))]),\n"
               ."            $commentString ],\n";
        }
        else {
            $format = $this->generateColumnFormat($column);
            echo "            $commentString '" . $attributeName . ($format === 'text' ? "" : ":" . $format) . "',\n";
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
                //Ignore _recid (not a real field)
                if($column->name == '_recid'){
                    continue;
                }
                $columns[$column->name] = $column->type;
            }
        }

        $likeConditions = [];
        $hashConditions = [];
        foreach ($columns as $column => $type) {
            $hashConditions[] = "'{$column}' => \$this->{$column},";
            /*switch ($type) {
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
            }*/
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
            //Ignore _recid (not a real field)
            if($column->name == '_recid'){
                continue;
            }
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
                /*
                 * Numbers may be treated as "safe" to allow fileMaker search operators
                 */
                //case Schema::TYPE_FLOAT:
                //case Schema::TYPE_DECIMAL:
                //case Schema::TYPE_MONEY:
                //   $types['number'][] = $column->name;
                //    break;

                case Schema::TYPE_FLOAT:
                case Schema::TYPE_DECIMAL:
                case Schema::TYPE_MONEY:

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
        /* @var $class \airmoi\yii2fmconnector\api\FileMakerActiveRecord */
        $class = $this->modelClass;
        if (is_subclass_of($class, 'airmoi\yii2fmconnector\api\FileMakerActiveRecord')) {
            return $class::getTableSchema();
        } else {
            return false;
        }
    }



    /**
     * @return array model column names
     */
    public function getColumnNames()
    {
        /* @var $class \airmoi\yii2fmconnector\api\FileMakerActiveRecord */
        $class = $this->modelClass;
        if (is_subclass_of($class, 'airmoi\yii2fmconnector\api\FileMakerActiveRecord')) {
            $colmunNames = $class::getTableSchema()->getColumnNames();
            $layoutColumns = $class::getDb()->getSchema()->getLayout($class::layoutName())->getFields();
            $columns = [];
            foreach ( $colmunNames as $columnName){
                if (array_key_exists($columnName, $layoutColumns)){
                    $columns[] = $columnName;
                }
            }
            return $columns;
        } else {
            /* @var $model \yii\base\Model */
            $model = new $class();

            return $model->attributes();
        }
    }
}
