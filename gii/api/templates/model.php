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
?>

namespace <?= $generator->ns ?>;

use Yii;
use airmoi\yii2fmconnector\api\FileMakerActiveRecord; 
use airmoi\yii2fmconnector\api\FileMakerRelatedRecord; 


/**
 * This is the model class for table "<?= $generator->generateTableName($tableName) ?>".
 *
<?php foreach ($tableSchema->columns as $column): ?>
 * @property <?= "{$column->phpType} \${$column->name}\n" ?>
<?php endforeach; ?>
<?php if (!empty($relations)): ?>
 *
<?php foreach ($relations as $name => $relation): ?>
 * @property <?= ucfirst($name) .($relation->isPortal ? '[]' : '' ) . ' $' . lcfirst($name) . "\n" ?>
<?php endforeach; ?>
<?php endif; ?>
 */
class <?= $className ?> extends FileMakerActiveRecord
{
    private static $_vList = [
        <?php foreach ($valueLists as $valueList): ?>
         '<?= $valueList ?>',
        <?php endforeach; ?>
    ];
    /**
     * @inheritdoc
     */
    public static function layoutName()
    {
        return '<?= $generator->generateTableName($tableName) ?>';
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

    /**
     * @return <?= ucfirst($name) ?>
     */
     public function get<?= ucfirst($name) ?>()
    {
        //TODO retrievex related records ?
    }
<?php endforeach; ?>
}

<?php if (!empty($relations)): ?>
<?php foreach ($relations as $name => $relation): ?>
<?php $rules = $generator->generateRules($relation) ?>
/*
 * This is the model class for related records "<?= $generator->generateTableName($name) ?>".
<?php foreach ($relation->columns as $column): ?>
 * @property <?= "{$column->phpType} \${$column->name}\n" ?>
<?php endforeach; ?>
 */
 class <?= ucfirst($name) ?> extends FileMakerRelatedRecord
{
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
<?php foreach ($relation->columns as $column): ?>
            <?= "'$column->name' => " . $generator->generateString($name . ' ' . $column->name) . ",\n" ?>
<?php endforeach; ?>
        ];
    }
}
<?php endforeach; ?>
<?php endif; ?>

