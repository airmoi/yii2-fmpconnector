<?php

use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $generator airmoi\yii2fmconnector\gii\api\crud\Generator */

$urlParams = $generator->generateUrlParams();

echo "<?php\n";
?>
use yii\helpers\Html;
use yii\widgets\DetailView;
use <?= $generator->indexWidgetType === 'grid' ? "yii\\grid\\GridView" : "yii\\widgets\\ListView" ?>;
use yii\data\ArrayDataProvider;

/* @var $this yii\web\View */
/* @var $model <?= ltrim($generator->modelClass, '\\') ?> */

$this->title = $model-><?= $generator->getNameAttribute() ?>;
$this->params['breadcrumbs'][] = ['label' => <?= $generator->generateString(Inflector::pluralize(Inflector::camel2words(StringHelper::basename($generator->modelClass)))) ?>, 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="<?= Inflector::camel2id(StringHelper::basename($generator->modelClass)) ?>-view">

    <h1><?= "<?= " ?>Html::encode($this->title) ?></h1>

    <p>
        <?= "<?= " ?>Html::a(<?= $generator->generateString('Update') ?>, ['update', <?= $urlParams ?>], ['class' => 'btn btn-primary']) ?>
        <?= "<?= " ?>Html::a(<?= $generator->generateString('Delete') ?>, ['delete', <?= $urlParams ?>], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => <?= $generator->generateString('Are you sure you want to delete this item?') ?>,
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= "<?= " ?>DetailView::widget([
        'model' => $model,
        'attributes' => [
<?php
if (($tableSchema = $generator->getTableSchema()) === false) {
    foreach ($generator->getColumnNames() as $name) {
        echo "            '" . $name . "',\n";
    }
} else {
    
    foreach ($generator->getTableSchema()->columns as $column) {
        echo $generator->generateListViewColumn($column);
        
    }
    
    //Single related fields
    foreach ($generator->getTableSchema()->relations as $relatedTableSchema){
        if(!$relatedTableSchema->isPortal) {
            foreach( $relatedTableSchema->columns as $column) {  
                $format = $generator->generateColumnFormat($column);
                echo $generator->generateListViewColumn($column, false, $relatedTableSchema->fullName);
                //echo "            '" . $relatedTableSchema->fullName . '.' . $column->name . ($format === 'text' ? "" : ":" . $format) . "',\n";
            }
        }
    }
}
?>
        ],
    ]) ?>
    
    
<?=  '<?php'  ?> $parentId = $model->_recid; ?>
<?php
//Build portals
if($tableSchema !== false){
    //Single related fields
    foreach ($tableSchema->relations as $relatedTableSchema){
        if($relatedTableSchema->isPortal) {
            ?>
    <h1><?= Html::encode($relatedTableSchema->name) ?></h1>
    <p>
        <?= '<?=' ?>Html::a(Yii::t('app', 'Create <?= Html::encode($relatedTableSchema->name) ?>'), ['createrelated', 'id' => $model->getRecId(), 'relation' => '<?= $relatedTableSchema->fullName ?>'], ['class' => 'btn btn-success']) ?>
    </p>
<?=  '<?='  ?>  
GridView::widget([
    'dataProvider' => new ArrayDataProvider([
        'id' => '<?= Inflector::camel2id($relatedTableSchema->fullName) ?>',
        'allModels' => $model-><?= $relatedTableSchema->fullName ?>,      
        'pagination' => [
            'pageSize' => 10,
            'pageParam' => 'page-<?= $relatedTableSchema->fullName ?>',
        ],
    ]),
    'columns' => [
        ['class' => 'yii\grid\SerialColumn'],
        <?php 
            $count = 0;
            foreach( $relatedTableSchema->columns as $column) {
                echo $generator->generateGridViewColumn($column, ++$count > 6);
            }
            
            //Single related fields
            foreach ($relatedTableSchema->relations as $relatedTableSchema2){
                    foreach( $relatedTableSchema2->columns as $column) {  
                        $format = $generator->generateColumnFormat($column);
                        echo $generator->generateGridViewColumn($column, ++$count > 6, $relatedTableSchema2->fullName);
                        //echo "            '" . $relatedTableSchema->fullName . '.' . $column->name . ($format === 'text' ? "" : ":" . $format) . "',\n";
                    }
            }
        ?>
            [
                'class' => 'yii\grid\ActionColumn', 
                'template' => '{update}',
                'buttons' => [
                    'update' => function ($url, airmoi\yii2fmconnector\api\FileMakerRelatedRecord $model, $key) use ($parentId) {
                        return Html::a('<span class="glyphicon glyphicon-pencil"></span>', ['updaterelated', 'relation' => $model->relationName,'id' => $parentId, 'relatedId' => $model->_recid]);
                    },
                ],
            ],
        ],
    ]
); ?>
<?php 
        }
    }  
} 
?>
</div>
