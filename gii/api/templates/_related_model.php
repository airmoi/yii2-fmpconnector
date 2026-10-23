<?php
/**
 * This is the template for generating the model class of a specified table.
 */

/* @var $this yii\web\View */
/* @var $generator yii\gii\generators\model\Generator */
/* @var $relationName string full table name */
/* @var $tableSchema airmoi\yii2fmconnector\api\TableSchema */

$relations = $tableSchema->relations;
$valueLists = $tableSchema->valueLists;
$layoutList = $tableSchema->layouts;
?>

<?php $rules = $generator->generateRules($tableSchema) ?>
/**
 * This is the model class for related records "<?= $generator->generateTableName($relationName) ?>".
<?php foreach ($tableSchema->columns as $column): ?>
 * @property <?= "{$column->phpType}".($column->maxRepeat > 1 ? '[]' : '' )." \${$column->name}".($column->maxRepeat > 1 ? ' Multivalue, '.$column->maxRepeat. ' repetitions' : '' )."\n" ?>
<?php endforeach; ?>
 *
<?php foreach ($relations as $name => $relation): ?>
 * @property <?= ucfirst($name) .($relation->isPortal ? '[]' : '' ) . ' $' . lcfirst($name) . "\n" ?>
<?php endforeach; ?>
 */
class <?= ucfirst($relationName) ?> extends FileMakerRelatedRecord
{
    /**
     * @var string name of the default layout the relation is accessible from
     */
    public static $defaultLayout = '<?=  $tableSchema->defaultLayout ?>';
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
<?php foreach ($tableSchema->columns as $column): ?>
            <?= "'$column->name' => " . $generator->generateString($column->name) . ",\n" ?>
<?php endforeach; ?>
        ];
    }
}

<?php foreach ($relations as $name => $relation): ?>
<?= $generator->render('_related_model.php', ['generator' => $generator, 'relationName' => $name, 'tableSchema' => $relation]) ?>
<?php endforeach; ?>