<?php

namespace RecIdRegression;

use airmoi\FileMaker\Command\Add;
use airmoi\FileMaker\Command\CompoundFind;
use airmoi\FileMaker\Command\Delete;
use airmoi\FileMaker\Command\Edit;
use airmoi\FileMaker\Command\FindAll;
use airmoi\FileMaker\FileMaker;
use airmoi\FileMaker\Object\Field;
use airmoi\FileMaker\Object\Layout;
use airmoi\FileMaker\Object\Record;
use airmoi\FileMaker\Object\Result;
use airmoi\yii2fmconnector\api\ColumnSchema;
use airmoi\yii2fmconnector\api\FileMakerActiveRecord;
use airmoi\yii2fmconnector\api\FileMakerRelatedRecord;
use airmoi\yii2fmconnector\api\TableSchema;

$autoload = $argv[1] ?? dirname(__DIR__) . '/vendor/autoload.php';
require_once $autoload;
require_once dirname($autoload) . '/yiisoft/yii2/Yii.php';

spl_autoload_register(function ($class) {
    $prefix = 'airmoi\\yii2fmconnector\\api\\';
    if (strpos($class, $prefix) === 0) {
        require dirname(__DIR__) . '/api/' . substr($class, strlen($prefix)) . '.php';
    }
}, true, true);

new \yii\console\Application([
    'id' => 'recid-regression',
    'basePath' => __DIR__,
    'vendorPath' => dirname($autoload),
]);

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});

// Only command execution is replaced; hydration, Yii attributes and save() are real.
trait FixtureExecution
{
    public function execute($result = null)
    {
        return $this->fm->result;
    }
}

class FixtureFind extends CompoundFind
{
    use FixtureExecution;
}

class FixtureFindAll extends FindAll
{
    use FixtureExecution;
}

class FixtureAdd extends Add
{
    use FixtureExecution;
}

class FixtureEdit extends Edit
{
    use FixtureExecution;
}

class FixtureDelete extends Delete
{
    use FixtureExecution;
}

class FixtureConnection extends FileMaker
{
    public $unencodedData = true;
    public $schema;
    public $fixtureLayout;
    public $result;
    public $edits = [];
    public $deletes = [];
    public $adds = [];

    public function getTableSchema($layout)
    {
        return $this->schema;
    }

    public function getLayout($layoutName, $recid = null, $loadExtended = true)
    {
        return $this->fixtureLayout;
    }

    public function newCompoundFindCommand($layout)
    {
        return new FixtureFind($this, $layout);
    }

    public function newFindAllCommand($layout)
    {
        return new FixtureFindAll($this, $layout);
    }

    public function newAddCommand($layout, $values = [], $useRawData = false)
    {
        $this->adds[] = [$layout, $values];
        return new FixtureAdd($this, $layout, $values, $useRawData);
    }

    public function newEditCommand($layout, $recordId, $updatedValues = [], $useRawData = false, $relatedSetName = null)
    {
        if ($recordId === null) {
            throw new \InvalidArgumentException('Cannot update a record with a null record ID.');
        }
        $this->edits[] = [$layout, $recordId, $updatedValues];
        return new FixtureEdit($this, $layout, $recordId, $updatedValues, $useRawData, $relatedSetName);
    }

    public function newDeleteCommand($layout, $recordId)
    {
        $this->deletes[] = [$layout, $recordId];
        return new FixtureDelete($this, $layout, $recordId);
    }
}

class FixtureModel extends FileMakerActiveRecord
{
    public static $defaultLayout = 'Regression';

    public function internalRecordId()
    {
        return $this->_recid;
    }
}

class FixtureRelatedModel extends FileMakerRelatedRecord
{
    public static $defaultLayout = 'Regression';

    public function attributes()
    {
        return array_keys(static::getDb()->schema->relations['Children']->columns);
    }
}

function same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new \RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

function checkRecordId($model, $expected)
{
    same($expected, $model->attributes['_recid'], 'attributes[_recid]');
    same($expected, $model->_recid, 'external _recid access');
    same($expected, $model->getAttribute('_recid'), 'getAttribute');
    same($expected, $model->getRecId(), 'getRecId');
    same($expected, $model->getPrimaryKey(), 'primary key');
    if ($model instanceof FixtureModel) {
        same($expected, $model->internalRecordId(), 'internal _recid access');
    }
}

function fixture($id = 42)
{
    $db = new FixtureConnection();
    $db->schema = new TableSchema();
    foreach (['_recid', 'name'] as $name) {
        $column = new ColumnSchema();
        $column->name = $name;
        $column->maxRepeat = 1;
        $db->schema->columns[$name] = $column;
    }
    $db->schema->primaryKey = ['_recid'];
    $db->schema->relations['Children'] = clone $db->schema;
    $db->schema->relations['Children']->name = 'Children';
    $db->schema->relations['Children']->isPortal = true;

    $db->fixtureLayout = new Layout($db);
    $field = new Field($db->fixtureLayout);
    $field->name = 'name';
    $field->result = 'text';
    $db->fixtureLayout->fields['name'] = $field;
    $record = new Record($db->fixtureLayout);
    $record->recordId = $id;
    $record->fields = ['name' => ['Before']];
    $db->result = new Result($db);
    $db->result->layout = $db->fixtureLayout;
    $db->result->records = [$record];
    $db->result->foundSetCount = 1;
    $db->result->fetchCount = 1;
    \Yii::$app->set('db', $db);
    return $db;
}

$tests = [
    'new model has no record ID' => function () {
        fixture();
        checkRecordId(new FixtureModel(), null);
    },
    'hydration exposes the stored attribute through getRecId()' => function () {
        $db = fixture();
        $model = new FixtureModel();
        FixtureModel::populateRecordFromFm($model, $db->result->getFirstRecord());
        same(42, $model->getAttribute('_recid'), 'stored record ID');
        same(42, $model->getRecId(), 'record ID used by updates');
    },
    'find()->one() hydrates all record ID accessors' => function () {
        fixture();
        $model = FixtureModel::find()->where(['name' => 'Before'])->one();
        checkRecordId($model, 42);
        same(false, $model->isNewRecord, 'loaded model state');
        same([], $model->getDirtyAttributes(), 'loaded model dirty attributes');
    },
    'find()->all() hydrates all record ID accessors' => function () {
        fixture();
        $models = FixtureModel::find()->all();
        same(1, count($models), 'result count');
        checkRecordId($models[0], 42);
    },
    'string record IDs are preserved without casting' => function () {
        $db = fixture('42');
        $model = FixtureModel::find()->one();
        checkRecordId($model, '42');
        $model->name = 'After';
        same(true, $model->save(false), 'save with a string record ID');
        same([['Regression', '42', ['name' => 'After']]], $db->edits, 'string edit ID');
    },
    'asArray results retain the record ID' => function () {
        fixture();
        same(['_recid' => 42, 'name' => 'Before'], FixtureModel::find()->asArray()->one(), 'array result');
    },
    'find followed by save sends the correct update ID and fields' => function () {
        $db = fixture();
        $model = FixtureModel::find()->one();
        $model->name = 'After';
        same(true, $model->save(false), 'save after find');
        same([['Regression', 42, ['name' => 'After']]], $db->edits, 'edit command arguments');
    },
    'insert followed by save updates the inserted record' => function () {
        $db = fixture(73);
        $model = new FixtureModel();
        $model->name = 'Before';
        same(true, $model->save(false), 'initial insert');
        same([['Regression', ['name' => 'Before']]], $db->adds, 'add command arguments');
        checkRecordId($model, 73);
        same(false, $model->isNewRecord, 'inserted model state');
        same([], $model->getDirtyAttributes(), 'inserted model dirty attributes');
        $model->name = 'After';
        same(true, $model->save(false), 'save after insert');
        same([['Regression', 73, ['name' => 'After']]], $db->edits, 'edit after insert');
    },
    'delete sends the loaded record ID' => function () {
        $db = fixture();
        $model = FixtureModel::find()->one();
        same(1, $model->delete(), 'delete result');
        same([['Regression', 42]], $db->deletes, 'delete command arguments');
    },
    'attribute writes remain consistent, including zero' => function () {
        fixture();
        $model = new FixtureModel();
        $model->_recid = 0;
        checkRecordId($model, 0);
        $model->setAttribute('_recid', 99);
        checkRecordId($model, 99);
    },
    'portal hydration uses the related record ID' => function () {
        $db = fixture(84);
        $model = new FixtureRelatedModel();
        $model->isPortal = true;
        $model->relationName = 'Children';
        $model->tableOccurence = 'Children';
        FixtureRelatedModel::populateRecordFromFm($model, $db->result->getFirstRecord());
        checkRecordId($model, 84);
    },
];

$failures = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS: $name\n";
    } catch (\Throwable $error) {
        $failures++;
        fwrite(STDERR, "FAIL: $name\n" . $error . "\n");
    }
}

echo count($tests) . " tests, $failures failures (PHP " . PHP_VERSION . ', Yii ' . \Yii::getVersion() . ")\n";
exit($failures === 0 ? 0 : 1);
