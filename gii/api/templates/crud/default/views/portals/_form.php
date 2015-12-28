<?php

use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/* @var $this yii\web\View */
/* @var $generator airmoi\yii2fmconnector\gii\api\crud\Generator */
/* @var $tableSchema \airmoi\yii2fmconnector\api\TableSchema */
/* @var $modelClass string */


$parentModel = new $generator->modelClass();
/* @var $model \airmoi\yii2fmconnector\api\FileMakerRelatedRecord */
$model = new $modelClass();
$safeAttributes = $model->safeAttributes();
if (empty($safeAttributes)) {
    $safeAttributes = $model->attributes();
}

echo "<?php\n";
?>
//test
use yii\helpers\Html;
use yii\widgets\ActiveForm;
<?=  $generator->generateNamespaces(); ?>

/* @var $this yii\web\View */
/* @var $model <?= $modelClass ?> */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="<?= Inflector::camel2id(StringHelper::basename($modelClass)) ?>-form">

    <?= "<?php " ?>$form = ActiveForm::begin(); ?>

<?php foreach ($tableSchema->columns as $column) {
    if (in_array($column->name, $safeAttributes)) {
        echo "    <?= " . $generator->generateActiveField($column->name) . " ?>\n\n";
    }
} ?>
    <div class="form-group">
        <?= "<?= " ?>Html::submitButton($model->isNewRecord ? <?= $generator->generateString('Create') ?> : <?= $generator->generateString('Update') ?>, ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?= "<?php " ?>ActiveForm::end(); ?>

</div>
