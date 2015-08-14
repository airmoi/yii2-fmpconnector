<?php
/**
 * This is the template for generating the model class of a specified table.
 */

/* @var $this yii\web\View */
/* @var $generator yii\gii\generators\model\Generator */
/* @var $tableName string full table name */
/* @var $className string class name */
/* @var $tableSchema yii\db\TableSchema */
/* @var $labels string[] list of attribute labels (name => label) */
/* @var $rules string[] list of validation rules */
/* @var $relations array list of relations (name => relation declaration) */

echo "<?php\n";
?>

namespace <?= $generator->ns ?>;

use Yii;

/**
 * This is the model class for table "<?= $generator->generateTableName($tableName) ?>".
 *
<?php foreach ($tableSchema->columns as $column): ?>
 * @property <?= "{$column->phpType} \${$column->name}\n" ?>
<?php endforeach; ?>
<?php if (!empty($relations)): ?>
 *
<?php foreach ($relations as $name => $relation): ?>
 * @property <?= $relation[1] . ($relation[2] ? '[]' : '') . ' $' . lcfirst($name) . "\n" ?>
<?php endforeach; ?>
<?php endif; ?>
 */
class <?= $className ?> extends <?= '\\' . ltrim($generator->baseClass, '\\') . "\n" ?>
{
    private static $_vList = null;
    /**
     * @inheritdoc
     */
    public static function tableName()
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
<?php foreach ($relations as $name => $relation): ?>

    /**
     * @return \yii\db\ActiveQuery
     */
    public function get<?= $name ?>()
    {
        <?= $relation[0] . "\n" ?>
    }
<?php endforeach; ?>
    
    /**
     * Generate array that can be used for dropdown list or checkbox set
     * @param string $key field to be used as index
     * @param string $label field to be used as label
     * @param string|array $condition condition to be used in where clause (@see \yii\db\ActiveQuery::where()
     * @param array $params params to be used in where clause (@see \yii\db\ActiveQuery::where()
     * @return array Array of records index by key 
     */
    public static function valueList($key = '<?= $tableSchema->primaryKey[0] ?>' , $label = 'label' , $condition = null, $params = null ) {
        
        $q = self::find()->select([$key . ' AS index', $label . ' AS label']);
        if( $condition !== null) {
            $q->andWhere($condition, $params);
        }
        
        $records = $q->createCommand()->cache()->queryAll();
        $result = [];
        foreach ( $records as $record) {
           $result[$record['index']] = $record['label'];
        }
        
        return $result;
    }
    
    /**
     * Generate query search params from model values
     * @param \yii\db\ActiveQuery $query
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function buildSearch(\yii\db\ActiveQuery $query)
    {
        $a = key($query->from);
        
        $query->andFilterWhere([<?php foreach ($tableSchema->columns as $column) {
            if( in_array ($column->phpType, ['integer', 'boolean', 'double', 'timestamp', 'time']) 
                    or $column->isPrimaryKey 
                    or $tableSchema->isForeignKey($column)  
                    ) { ?> 
            $a.'.<?= $column->name ?>' => $this-><?= $column->name ?>,<?php
         } }?>
        ]);
        
        $query<?php foreach ($tableSchema->columns as $column) {
            if( in_array ($column->phpType, ['string']) 
                    and !$column->isPrimaryKey 
                    and !$tableSchema->isForeignKey($column)  
                    ) {
            ?>->andFilterWhere(['like', 'LOWER(' . $a . '.<?= $column->name?>)', strtolower($this-><?= $column->name?>)])
            <?php } }?>;
            
            
        /*
         * build related join query if needed (use model's related records
         */
        foreach($this->relatedRecords as $relationName => $relatedRecord){
            if( !$relatedRecord instanceof \yii\db\ActiveRecord ){
                continue;
            }
            /**
             * @var ActiveRecord $relatedRecord
             */
            $isempty = true;
            foreach($relatedRecord->attributes() as $field){
                if(!empty($relatedRecord->$field)){
                    $isempty = false;
                    break;
                }
            }
            
            if($isempty)
                continue;
            
            $query->joinWith([$relationName => function($query) use ($relatedRecord){
                $relatedRecord->buildSearch($query);
            }]);
        } 
        return $query;
    }
}
