<?php
/**
 * This is the template for generating the model class of a specified table.
 */

/* @var $this yii\web\View */
/* @var $generator yii\gii\generators\model\Generator */
/* @var $tableName string full table name */
/* @var $className string class name */
/* @var $tableSchema airmoi\yii2fmconnector\api\TableSchema */
/* @var $labels string[] list of attribute labels (name => label) */
/* @var $rules string[] list of validation rules */
/* @var $relations array list of relations (name => relation declaration) */

echo "<?php\n";

$relations = $tableSchema->relations;
$valueLists = $tableSchema->valueLists;
$layoutList = $tableSchema->layouts;
?>

namespace <?= $generator->ns ?>;

use Yii;
use airmoi\yii2fmconnector\api\FileMakerActiveRecord; 
use airmoi\yii2fmconnector\api\FileMakerRelatedRecord; 


/**
 * This is the model class for table "<?= $generator->generateTableName($tableName) ?>".
 *
<?php foreach ($tableSchema->columns as $column): ?>
 * @property <?= "{$column->phpType}".($column->maxRepeat > 1 ? '[]' : '' )." \${$column->name} ".($column->maxRepeat > 1 ? 'Multivalued, '.$column->maxRepeat. ' repetitions' : '' )."\n" ?>
<?php endforeach; ?>
 *
<?php foreach ($relations as $name => $relation): ?>
 * @property <?= ucfirst($name) .($relation->isPortal ? '[]' : '' ) . ' $' . lcfirst($name) . "\n" ?>
<?php endforeach; ?>
 */
class <?= $className ?> extends FileMakerActiveRecord
{
    /**
    * @var string the default layout used to retrieve records
    */
    public static $defaultLayout = '<?= $generator->generateTableName($tableName) ?>';
    
    /**
    * @var string the default layout to use for find requests
    */
    public static $defaultSearchLayout = '<?= $generator->generateTableName($tableName) ?>';
    
    private static $_vList = [
        <?php foreach ($valueLists as $valueList): ?>
         '<?= $valueList ?>',
        <?php endforeach; ?>
    ];
    
    /**
     * @return array all available FileMaker layouts for this model
     */
    public static function listLayouts()
    {
        return [
    <?php foreach ($layoutList as $layoutName): ?>
        '<?= $layoutName ?>',
    <?php endforeach; ?>
        ];
    }
    
    /**
     * @return array An array of [ attributeName => valueListName ]
     */
    public function attributeValueLists()
    {
        return [
<?php foreach ($tableSchema->columns as $column): 
        if ($column->valueList !== null): ?>
            '<?= $column->name ?>' => '<?= $column->valueList ?>',
<?php   endif;   
    endforeach; ?>
        ];
    }
    
    /**
     * @return string default FileMaker layout used by this model
     */
    public static function layoutName()
    {
        return self::$defaultLayout;
    }
    
    /**
     * @return string default FileMaker layout to be used for search queries
     */
    public static function searchLayoutName()
    {
        return self::$defaultSearchLayout;
    }
<?php if ($generator->db !== 'db'): ?>

    /**
     * @return \yii\db\Connection the database connection used by this AR class.
     */
    public static function getDb()
    {
        return Yii::$app->get('<?= $generator->db ?>');
    }
<?php endif; ?>

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [<?= "\n            " . implode(",\n            ", $rules) . "\n        " ?>];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
<?php foreach ($labels as $name => $label): ?>
            <?= "'$name' => " . $generator->generateString($label) . ",\n" ?>
<?php endforeach; ?>
        ];
    }
<?php foreach ($relations as $name => $fields): ?>
<?php
    //Disable this part until "findFor" is not implemented
    /**
     * @return <?= ucfirst($name) ?>
     */
     /*public function get<?= ucfirst($name) ?>()
    {
        //TODO retrievex related records ?
    } */
    ?>
<?php endforeach; ?>
<?php if ($queryClassName): ?>
<?php
    $queryClassFullName = ($generator->ns === $generator->queryNs) ? $queryClassName : '\\' . $generator->queryNs . '\\' . $queryClassName;
    echo "\n";
?>
    /**
     * @inheritdoc
     * @return <?= $queryClassFullName ?> the active query used by this AR class.
     */
    public static function find($layout = null)
    {
        $query = new <?= $queryClassFullName ?>(get_called_class());
        if ($layout !== null){
            $query->resultLayout = $layout;
        }
        return $query;
    }
<?php endif; ?>
}

<?php foreach ($relations as $name => $relation): ?>
<?= $generator->render('_related_model.php', ['generator' => $generator, 'relationName' => $name, 'tableSchema' => $relation]) ?>
<?php endforeach; ?>

