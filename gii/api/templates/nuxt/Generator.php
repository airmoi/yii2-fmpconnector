<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */


namespace airmoi\yii2fmconnector\gii\api\nuxt;

use Yii;
use \airmoi\yii2fmconnector\api\ColumnSchema;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use airmoi\yii2fmconnector\api\Schema;
use yii\gii\CodeFile;
use yii\helpers\StringHelper;
use yii\rest\ActiveController;
use yii\validators\DefaultValueValidator;
use yii\validators\Validator;

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
    public $modelClass = 'app\models\MyModel';
    public $baseControllerClass = ActiveController::class;
    public $controllerClass = 'app\modules\api\modules\v1\controllers\SampleController';
    public $viewPath = '@app/front';

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
            'store' => [Inflector::camel2id(StringHelper::basename($this->modelClass), '_'), 'js']
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

    public function generateForm()
    {
        $model = new $this->modelClass();
        $safeAttributes = $model->safeAttributes();

        $form = '';
        foreach ($this->getColumnNames() as $attribute) {
            if (in_array($attribute, $safeAttributes)) {
                $form .= $this->generateFormField($attribute) . "\n";
            }
        }
        return $form;
    }
    /**
     * Generates code for active field
     * @param string $attribute
     * @return string
     */
    public function generateFormField($attribute, $tableShema = null)
    {
        $tableSchema = $tableShema === null ? $this->getTableSchema() : $tableShema;
        if($pos = strpos($attribute, '.')){
            $relationName = substr($attribute , 0 , $pos);
            $attribute = substr($attribute, $pos+1);
            $tableSchema = $tableSchema->relations[ $relationName ];
            $modelVar = 'form.'.$relationName;
        } else {
            $modelVar = 'form';
        }

        $label = Inflector::camel2words($attribute);
        $column = $tableSchema->columns[$attribute];

        //Todo : handle file inputs
        if($column->dbType == 'container') {
            return;
        }
        if ($column->valueList) {
            $input = <<<JS
      <v-select
        v-model="$modelVar.$attribute"
        clearable
        :items="[]"
        label="$label"
      ></v-select>
JS;
        } else {
            if (preg_match('/^(password|pass|passwd|passcode)$/i', $column->name)) {
                $type ="password";
            } elseif (preg_match('/^(mail|email|e-mail|courriel)$/i', $column->name)) {
                $type ="email";
            } elseif ($column->type === 'number') {
                $type ="number";
            } else {
                $type = 'text';
            }
            $input = <<<JS
      <v-text-field
        v-model="$modelVar.$attribute"
        label="$label"
        type="$type"
      />
 JS;
        }
        return $input;
    }

    /**
     * @return false|string
     */
    public function generateDataTableHeaders()
    {
        /** @var $model ActiveRecord */
        $model = new $this->modelClass;
        $attributes = $this->getColumnNames();

        foreach ($attributes as $attribute) {
            $column = $model::getTableSchema()->getColumn($attribute);
            $headers[] = [
                'text' => $model->getAttributeLabel($attribute),
                'value' => $attribute,
                'sortable' => $this->isSortableColumn($column),
            ];
        }
        $headers[] = [
            'text' => 'Actions',
            'value' => 'actions',
            'sortable' => false
        ];

        return json_encode($headers, JSON_PRETTY_PRINT);
    }

    /**
     * @return false|string
     */
    public function getDefaultRecord()
    {
        /** @var $model ActiveRecord */
        $model = new $this->modelClass;
        $attributes = $this->getColumnNames();
        $allValidators = $model->getValidators();
        $defaultValues = [];
        foreach ($allValidators as $validator) {
            if ($validator instanceof DefaultValueValidator && !is_callable($validator->value)) {
                foreach ($validator->getAttributeNames() as $attribute) {
                    $defaultValues[$attribute] = $validator->value;
                }
            }
        }

        $colums = [];
        foreach ($attributes as $attribute) {
            $colums[$attribute] = @$defaultValues[$attribute]?:null;
        }

        return json_encode($colums, JSON_PRETTY_PRINT);
    }

    /**
     *
     * @param ColumnSchema $column
     */
    public function isSortableColumn(ColumnSchema $column)
    {
        return $column->fmType === 'normal'
            && $column->dbType !== 'container'
            && !$column->global
            && !$column->isRelated;
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
        if (is_subclass_of($class, BaseActiveRecord::class)) {
            return $class::getTableSchema();
        } else {
            throw new InvalidConfigException("This code generator expect model class to be an ActiveRecord");
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
            return $class::getTableSchema()->getColumnNames();
        } else {
            /* @var $model \yii\base\Model */
            $model = new $class();

            return $model->attributes();
        }
    }
}
