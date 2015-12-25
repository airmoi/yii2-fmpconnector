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
        if ($column->dbType == 'container') {
            echo "            [\n"
               . "              'attribute' => '".$column->name."',\n"
               . "              'format' => 'image',\n"
               . "              'value' => yii\helpers\Url::to(['container', 'id' => \$model->".$tableSchema->primaryKey[0].", 'field' => '".$column->name."']),\n"
               ."            ],\n";
        }
        else {
            $format = $generator->generateColumnFormat($column);
            echo "            '" . $column->name . ($format === 'text' ? "" : ":" . $format) . "',\n";
        }
    }
    
    //Single related fields
    foreach ($generator->getTableSchema()->relations as $relatedTableSchema){
        if(!$relatedTableSchema->isPortal) {
            foreach( $relatedTableSchema->columns as $column) {  
                $format = $generator->generateColumnFormat($column);
                echo "            '" . $relatedTableSchema->fullName . '.' . $column->name . ($format === 'text' ? "" : ":" . $format) . "',\n";
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
            ?><h1><?= Html::encode($relatedTableSchema->name) ?></h1>
<?=  '<?='  ?>  
GridView::widget([
    'dataProvider' => new ArrayDataProvider(['id' => '<?= Inflector::camel2id($relatedTableSchema->fullName) ?>', 'allModels' => $model-><?= $relatedTableSchema->fullName ?>]),
    'columns' => [
        ['class' => 'yii\grid\SerialColumn'],
        <?php 
            $count = 0;
            foreach( $relatedTableSchema->columns as $column) { 
                if (++$count < 6) {
                    if ($column->dbType == 'container') {
                        echo "            [\n"
                           . "              'attribute' => '".$column->name."',\n"
                           . "              'format' => 'image',\n"
                           . "              'value' => function(\$model) use (\$parentId) { return yii\helpers\Url::to(['container', 'id' => \$parentId, 'field' => '".$relatedTableSchema->fullName . "::" . $column->name."::'.\$model->".$tableSchema->primaryKey[0]."]);},\n"
                           ."            ],\n";
                    }
                    else {
                        $format = $generator->generateColumnFormat($column);
                        echo "            '" . $column->name . ($format === 'text' ? "" : ":" . $format) . "',\n";
                    }
                } else {
                    if ($column->dbType == 'container') {
                        echo "            //[\n"
                           . "            //    'attribute' => '".$column->name."',\n"
                           . "            //    'format' => 'image',\n"
                           . "            //    'value' => function(\$model) use (\$parentId) { return yii\helpers\Url::to(['container', 'id' => \$parentId, 'field' => '".$relatedTableSchema->fullName . "::" . $column->name."::'.\$model->".$tableSchema->primaryKey[0]."]);},\n"
                           . "            //],\n";
                    }
                    else {
                        $format = $generator->generateColumnFormat($column);
                        echo "            //'" . $column->name . ($format === 'text' ? "" : ":" . $format) . "',\n";
                    }
                }
            }
        }
    }
        ?>
        ],
    ]
);<?php   
} 
?>?>
</div>
