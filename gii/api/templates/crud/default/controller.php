<?php
/**
 * This is the template for generating a CRUD controller class file.
 */

use yii\db\ActiveRecordInterface;
use yii\helpers\StringHelper;


/* @var $this yii\web\View */
/* @var $generator airmoi\yii2fmconnector\gii\api\crud\Generator */

$controllerClass = StringHelper::basename($generator->controllerClass);
$modelClass = StringHelper::basename($generator->modelClass);
$searchModelClass = StringHelper::basename($generator->searchModelClass);
if ($modelClass === $searchModelClass) {
    $searchModelAlias = $searchModelClass . 'Search';
}

/* @var $class ActiveRecordInterface */
$class = $generator->modelClass;
$pks = $class::primaryKey();
$urlParams = $generator->generateUrlParams();
$actionParams = $generator->generateActionParams();
$actionParamComments = $generator->generateActionParamComments();

echo "<?php\n";
?>

namespace <?= StringHelper::dirname(ltrim($generator->controllerClass, '\\')) ?>;

use Yii;
use <?= ltrim($generator->modelClass, '\\') ?>;
<?php if (!empty($generator->searchModelClass)): ?>
use <?= ltrim($generator->searchModelClass, '\\') . (isset($searchModelAlias) ? " as $searchModelAlias" : "") ?>;
<?php else: ?>
use airmoi\yii2fmconnector\api\ActiveDataProvider;
<?php endif; ?>
use <?= ltrim($generator->baseControllerClass, '\\') ?>;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * <?= $controllerClass ?> implements the CRUD actions for <?= $modelClass ?> model.
 */
class <?= $controllerClass ?> extends <?= StringHelper::basename($generator->baseControllerClass) . "\n" ?>
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Lists all <?= $modelClass ?> models.
     * @return mixed
     */
    public function actionIndex()
    {
<?php if (!empty($generator->searchModelClass)): ?>
        $searchModel = new <?= isset($searchModelAlias) ? $searchModelAlias : $searchModelClass ?>();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
<?php else: ?>
        $dataProvider = new ActiveDataProvider([
            'query' => <?= $modelClass ?>::find(),
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
<?php endif; ?>
    }

    /**
     * Displays a single <?= $modelClass ?> model.
     * <?= implode("\n     * ", $actionParamComments) . "\n" ?>
     * @return mixed
     */
    public function actionView(<?= $actionParams ?>)
    {
        return $this->render('view', [
            'model' => $this->findModel(<?= $actionParams ?>),
        ]);
    }

    /**
     * Creates a new <?= $modelClass ?> model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new <?= $modelClass ?>();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', <?= $urlParams ?>]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Creates a new Sample model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreaterelated($id, $relation)
    {
        //Load Main model class to resolve namespace
        <?= $modelClass ?>::layoutName();
        $relationClass = '\\app\\models\\'.ucfirst($relation);
        $layout = $relationClass::layoutName();
        
        $model = $this->findModel($id, $layout);
        $relatedRecord = $model->newRelatedRecord($relation);
        
        if ($relatedRecord->load(Yii::$app->request->post()) && $relatedRecord->save()) {
            return $this->redirect(['view', 'id' => $model->_recid]);
        } else {
            $view = '_' . $relation . '_form';
            return $this->render('portals/' . $view, [
                'model' => $relatedRecord,
            ]);
        }
    }

    /**
     * Updates an existing <?= $modelClass ?> model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * <?= implode("\n     * ", $actionParamComments) . "\n" ?>
     * @return mixed
     */
    public function actionUpdate(<?= $actionParams ?>)
    {
        $model = $this->findModel(<?= $actionParams ?>);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', <?= $urlParams ?>]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates a portal row.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdaterelated($id, $relation, $relatedId)
    {
        //Load Main model class to resolve namespace
        <?= $modelClass ?>::layoutName();
        $relationClass = '\\app\\models\\'.ucfirst($relation);
        $layout = $relationClass::layoutName();
        
        $model = $this->findModel($id, $layout);
        $relatedRecords = $model->$relation;
        $relatedRecord = $relatedRecords[$relatedId];
        
        if ($relatedRecord->load(Yii::$app->request->post()) && $relatedRecord->save()) {
            return $this->redirect(['view', 'id' => $model->_recid]);
        } else {
            $view = '_' . $relation . '_form';
            return $this->render('portals/' . $view, [
                'model' => $relatedRecord,
            ]);
        }
    }

    /**
     * Deletes an existing <?= $modelClass ?> model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * <?= implode("\n     * ", $actionParamComments) . "\n" ?>
     * @return mixed
     */
    public function actionDelete(<?= $actionParams ?>)
    {
        $this->findModel(<?= $actionParams ?>)->delete();

        return $this->redirect(['index']);
    }
    
     /**
     * Return Container file according to its mime type.
     * @param string $id the FileMaker Internal Record ID
     * @param string $field The fieldName to retrieve as container
     * @return mixed
     */
    public function actionContainer($token)
    {
        /*$model = $this->findModel($id);
        
        if ( $pos = strpos($field, '::') ){
            list ( $relation, $field, $recid) = explode ( "::", $field );
            $url = $model->$relation[$recid]->$field;
        } else {
            $url = $model->$field;
        }*/
        $url = <?= $modelClass ?>::decryptContainerUrl($token);
        
        $fileName = <?= $modelClass ?>::getContainerFileName($url);

        \Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        
        if ( empty($url) ) {
            throw new NotFoundHttpException('The requested file does not exist.');
        }
        
        header('Content-Type: '.\airmoi\yii2fmconnector\api\FmpHelper::mime_content_type($fileName));
        return <?= $modelClass ?>::getDb()->getContainerData($url);
        Yii::$app->end();
    }

    /**
     * Finds the <?= $modelClass ?> model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * <?= implode("\n     * ", $actionParamComments) . "\n" ?>
     * @return <?=                   $modelClass ?> the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(<?= $actionParams ?>, $layout = null)
    {
<?php
if (count($pks) === 1) {
    $condition = '$id';
} else {
    $condition = [];
    foreach ($pks as $pk) {
        $condition[] = "'$pk' => \$$pk";
    }
    $condition = '[' . implode(', ', $condition) . ']';
}
?>
        if (($model = <?= $modelClass ?>::findOne(<?= $condition ?>, $layout)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
