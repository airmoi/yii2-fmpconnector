<?php
/**
 * @copyright 2016 Romain Dunand
 * @license MIT https://github.com/airmoi/yii2-fmpconnector/blob/master/LICENSE
 * @link https://github.com/airmoi/yii2-fmpconnector
 */

namespace airmoi\yii2fmconnector\api;

use airmoi\FileMaker\FileMakerException;
use airmoi\FileMaker\Object\Layout;
use airmoi\FileMaker\Object\Record;
use yii;
use yii\db\Exception;
use yii\db\BaseActiveRecord;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\base\NotSupportedException;

/**
 * Description of FileMakerModel
 */
class FileMakerActiveRecord extends BaseActiveRecord
{
    /**
     * @var string the default layout used to retrieve records
     */
    public static $defaultLayout;

    /**
     *
     * @var \airmoi\FileMaker\Object\Layout
     */
    protected static $_layout;

    /**
     *
     * @var array
     */
    protected static $_attributesList;

    /**
     * @var \airmoi\FileMaker\Object\Field[]
     */
    protected static $_attributesProperties;

    /**
     * Wether this model represent records from a portal view
     * @var bool
     */
    public $isPortal = false;

    /**
     *
     * @var array
     */
    protected $_attributes;


    /**
     *
     * @var \airmoi\FileMaker\Object\Record
     */
    protected $_record;

    /**
     * Globals to be set on update/save queries
     * @var array array of fieldName => value
     */
    private $_globals = [];

    /**
     * the parent record instance when Model is a related record
     * @var FileMakerActiveRecord
     */
    private $_parent;


    public function __get($name)
    {
        if (preg_match('/^(\w+)\[(\d+)\]/', $name, $matches)) {
            return parent::__get($matches[1])[$matches[2]];
        } else {
            return parent::__get($name);
        }
    }

    /**
     * @return Connection the database connection used by this AR class.
     */
    public static function getDb()
    {
        return Yii::$app->db;
    }

    /**
     *
     * @return string[]
     */
    public static function primaryKey()
    {
        return ['_recid'];
    }

    /**
     * Returns the list of all attribute names of the model.
     * The default implementation will return all column names of the table associated with this AR class.
     * @return array list of attribute names.
     * @throws NotSupportedException
     */
    public function attributes()
    {
        return array_keys($this->getDb()->getTableSchema($this->layoutName())->columns);
    }

    /**
     * Extend afterFind to trigger afterFind in related Records
     *
     * @inheritdoc
     */
    public function afterFind()
    {
        parent::afterFind();
        foreach ($this->getRelatedRecords() as $relation) {
            if (is_array($relation)) {
                foreach ($relation as $record) {
                    $record->afterFind();
                }
            } else {
                $relation->afterFind();
            }
        }
    }


    public function getAttributeLabel($attribute)
    {
        if (preg_match('/^(\w+)\[(\d+)\]/', $attribute, $matches)) {
            $attribute = $matches[1];
        }
        return parent::getAttributeLabel($attribute);
    }

    /**
     * @inheritdoc
     * @return ActiveFind the newly created [[ActiveFind]] instance.
     */
    public static function find($layout = null)
    {
        $query = Yii::createObject(ActiveFind::className(), [get_called_class()]);
        if ($layout !== null) {
            $query->resultLayout = $layout;
        }
        return $query;
    }

    /**
     * @inheritdoc
     * @return static[]
     */
    public static function findAll($condition = [], callable $callback = null, $layout = null)
    {
        $query = static::find($layout);
        $query->andWhere($condition);
        return $query->all();
    }

    /**
     * @inheritdoc
     * @return \airmoi\FileMaker\Command\CompoundFind the newly created [[Find]] instance.
     */
    public static function compoundFind()
    {
        return static::getDb()->newCompoundFindCommand(static::layoutName());
    }

    /**
     *
     * @param mixed $condition primary key value or a set of column values
     * @param string|null $layout
     * @return static ActiveRecord instance matching the condition, or null if nothing matches.
     * @throws \Exception
     */
    public static function findOne($condition, $layout = null)
    {
        if (!ArrayHelper::isAssociative($condition)) {
            return static::find($layout)->getRecordById($condition);
        }

        return static::find($layout)->andWhere($condition)->one();
    }

    /**
     * Deletes the table row corresponding to this active record.
     *
     * This method performs the following steps in order:
     *
     * 1. call [[beforeDelete()]]. If the method returns false, it will skip the
     *    rest of the steps;
     * 2. delete the record from the database;
     * 3. call [[afterDelete()]].
     *
     * In the above step 1 and 3, events named [[EVENT_BEFORE_DELETE]] and [[EVENT_AFTER_DELETE]]
     * will be raised by the corresponding methods.
     *
     * @return integer|false the number of rows deleted, or false if the deletion is unsuccessful for some reason.
     * Note that it is possible the number of rows deleted is 0, even though the deletion execution is successful.
     * @throws \Exception in case delete failed.
     */
    public function delete()
    {
        $recordId = $this->getRecId();
        $command = static::getDb()->newDeleteCommand(static::$defaultLayout, $recordId);
        try {
            $command->execute();
            return 1;
        } catch (FileMakerException $e) {
            $this->addError('delete', $e->getMessage());
            return 0;
        }
    }

    /**
     * @return string default FileMaker layout used by this model
     * @throws NotSupportedException
     */
    public static function layoutName()
    {
        if (static::$defaultLayout === null) {
            throw new NotSupportedException('defaultLayout property must be set in model');
        }
        return static::$defaultLayout;
    }

    /**
     * Map value lists associated with fields
     * @return array associative array (field => valueListName)
     * @throws \yii\base\NotSupportedException
     */
    public function attributeValueLists()
    {
        throw new NotSupportedException('attributeValueLists Method should be overidded');
    }

    /**
     * @var string default FileMaker layout to be used for search queries
     * @return string
     * @throws NotSupportedException
     */
    public static function searchLayoutName()
    {
        return static::layoutName();
    }

    /**
     * get filename of a container attribute
     * @param string $url the container url
     * @return string the filename
     */
    public static function getContainerFileName($url)
    {
        $name = basename($url);
        return substr($name, 0, strpos($name, "?"));
    }

    /**
     * Return the layout Object used by this model
     * @return Layout
     * @throws NotSupportedException
     */
    public static function getLayout()
    {
        if (!isset(static::$_layout[static::layoutName()])) {
            $connection = static::getDb();
            static::$_layout[static::layoutName()] = $connection->getLayout(static::layoutName());
        }
        return static::$_layout[static::layoutName()];
    }

    /**
     * Returns the schema information of the DB table associated with this AR class.
     * @param string $layout
     * @return TableSchema the schema information of the DB table associated with this AR class.
     * @throws InvalidConfigException if the table for the AR class does not exist.
     * @throws NotSupportedException
     */
    public static function getTableSchema($layout = null)
    {
        $schema = static::getDb()->getTableSchema($layout == null ? static::layoutName() : $layout);
        if ($schema !== null) {
            return $schema;
        } else {
            throw new InvalidConfigException("The table does not exist: " . static::layoutName());
        }
    }


    public function load($data, $formName = null)
    {
        $loaded = (int)parent::load($data, $formName);

        foreach ($this->getRelatedRecords() as $records) {
            if ($records instanceof FileMakerRelatedRecord && !$records->isPortal) {
                $loaded += (int)$records->load($data);
            }
        }
        return $loaded > 0;
    }

    /**
     * Inserts a row into the associated database table using the attribute values of this record.
     *
     * This method performs the following steps in order:
     *
     * 1. call [[beforeValidate()]] when `$runValidation` is true. If [[beforeValidate()]]
     *    returns `false`, the rest of the steps will be skipped;
     * 2. call [[afterValidate()]] when `$runValidation` is true. If validation
     *    failed, the rest of the steps will be skipped;
     * 3. call [[beforeSave()]]. If [[beforeSave()]] returns `false`,
     *    the rest of the steps will be skipped;
     * 4. insert the record into database. If this fails, it will skip the rest of the steps;
     * 5. call [[afterSave()]];
     *
     * In the above step 1, 2, 3 and 5, events [[EVENT_BEFORE_VALIDATE]],
     * [[EVENT_AFTER_VALIDATE]], [[EVENT_BEFORE_INSERT]], and [[EVENT_AFTER_INSERT]]
     * will be raised by the corresponding methods.
     *
     * Only the [[dirtyAttributes|changed attribute values]] will be inserted into database.
     *
     * If the table's primary key is auto-incremental and is null during insertion,
     * it will be populated with the actual value after insertion.
     *
     * For example, to insert a customer record:
     *
     * ```php
     * $customer = new Customer;
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->insert();
     * ```
     *
     * @param boolean $runValidation whether to perform validation (calling [[validate()]])
     * before saving the record. Defaults to `true`. If the validation fails, the record
     * will not be saved to the database and this method will return `false`.
     * @param array $attributes list of attributes that need to be saved. Defaults to null,
     * meaning all attributes that are loaded from DB will be saved.
     * @return boolean whether the attributes are valid and the record is inserted successfully.
     * @throws \Exception in case insert failed.
     */
    public function insert($runValidation = true, $attributes = null)
    {
        if ($runValidation && !$this->validate($attributes)) {
            Yii::info('Model not inserted due to validation error.', __METHOD__);
            return false;
        }

        if (!$this->beforeSave(true)) {
            return false;
        }

        try {
            $values = $this->getDirtyAttributes();
            $connection = static::getDb();
            $request = $connection->newAddCommand(static::layoutName(), $values);

            foreach ($this->_globals as $fieldName => $fieldValue) {
                $request->setGlobal($fieldName, $fieldValue);
            }
            $result = $request->execute();
            $this->_recid = $result->getFirstRecord()->getRecordId();
            self::populateRecordFromFm($this, $result->getFirstRecord());

            $this->afterSave(true, $values);
            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     *
     * @param FileMakerActiveRecord $record
     * @param Record $fmRecord
     * @return self
     * @throws FileMakerException
     * @throws NotSupportedException
     */
    public static function populateRecordFromFm(FileMakerActiveRecord $record, Record $fmRecord)
    {
        $record->_record = $fmRecord;
        if ($record->isPortal) {
            $tableSchema =  static::getDb()->getTableSchema(static::layoutName())->relations[$record->relationName];
        } else {
            $tableSchema = static::getDb()->getTableSchema(static::layoutName());
        }

        /* @var $tableSchema TableSchema */
        $row = [];
        $attributePrefix = $record->isPortal ? $record->tableOccurence . '::' : '';
        $fmFields = $fmRecord->getFields();
        foreach ($record->attributes() as $attribute) {
            //Ugly fix : bypass _recid as it is manually defined later
            if ($attribute == '_recid') {
                continue;
            }
            if ($tableSchema->columns[$attribute]->maxRepeat > 1) {
                $row[$attribute] = [];
                for ($i = 0; $i <= $tableSchema->columns[$attribute]->maxRepeat; $i++) {
                    $row[$attribute][$i] = $fmRecord->getField($attributePrefix . $attribute, $i);
                }
            } elseif (in_array($attribute, $fmFields)) {
                $row[$attribute] = $fmRecord->getField($attributePrefix . $attribute);
            }
        }

        $row['_recid'] = $fmRecord->getRecordId();
        parent::populateRecord($record, $row);

        //Populate relations
        foreach ($tableSchema->relations as $relationName => $schema) {
            $fields = $fmRecord->getLayout()->listFields();
            $filter = array_filter($fields, function ($field) use ($relationName) {
                return strpos($field, $relationName) !== false;
            });
            if (!$schema->isPortal && sizeof($filter)) {
                $record->populateHasOneRelation($relationName, $schema, $fmRecord);
            } elseif ($fmRecord->getLayout()->hasRelatedSet($schema->name)) {
                $record->populateHasManyRelation($relationName, $schema, $fmRecord);
            }
        }

        return $record;
    }

    /**
     *
     * @param string $relationName
     * @param TableSchema $tableSchema
     * @param Record $record
     * @throws FileMakerException
     */
    protected function populateHasOneRelation($relationName, TableSchema $tableSchema, Record $record)
    {
        $modelClass = substr(get_called_class(), 0, strrpos(get_called_class(), '\\')) . '\\' . ucfirst($relationName);

        if (!class_exists($modelClass)) {
            \Yii::error("relation's model class $modelClass is missing", 'FileMaker.fmConnector');
            return;
        }
        $model = $modelClass::instantiate([]);
        $model->_parent = $this;
        \Yii::configure(
            $model,
            [
                'isPortal' => false,
                'relationName' => $relationName,
                'tableOccurence' => $tableSchema->name
            ]
        );


        $row = [];
        $fmFields = $record->getFields();
        foreach (array_keys($tableSchema->columns) as $fieldName) {
            if (in_array($tableSchema->name . '::' . $fieldName,$fmFields )) {
                $row[$fieldName] = $record->getField($tableSchema->name . '::' . $fieldName);
            }
        }

        parent::populateRecord($model, $row);

        $this->populateRelation($relationName, $model);
    }

    /**
     *
     * @param string $relationName
     * @param Record|null $record
     * @return null|FileMakerActiveRecord
     * @throws FileMakerException
     * @throws NotSupportedException
     */
    public function newRelatedRecord($relationName, $record = null)
    {
        $modelClass = substr(get_called_class(), 0, strrpos(get_called_class(), '\\')) . '\\' . ucfirst($relationName);


        if (!class_exists($modelClass)) {
            \Yii::error("relation's model class $modelClass is missing", 'FileMaker.fmConnector');
            return null;
        }

        $tableSchema = static::getDb()->getTableSchema(self::layoutName())->relations[$relationName];
        $model = $modelClass::instantiate([]);

        if ($record === null && $this->_record !== null) {
            $record = $this->_record->newRelatedRecord($tableSchema->name);
        }
        $model->_record = $record;
        $model->isPortal = true;
        $model->_parent = $this;
        $model->relationName = $relationName;
        $model->tableOccurence = $tableSchema->name;
        //\Yii::configure($model, ['isPortal' => true, 'parent' => $this, 'relationName' => $relationName, '' => ]);

        return $model;
    }

    /**
     *
     * @param string $relationName
     * @param TableSchema $tableSchema
     * @param Record $record
     * @throws FileMakerException
     * @throws NotSupportedException
     * @internal param ColumnSchema[] $fields
     */
    protected function populateHasManyRelation($relationName, TableSchema $tableSchema, Record $record)
    {
        $modelClass = substr(get_called_class(), 0, strrpos(get_called_class(), '\\')) . '\\' . ucfirst($relationName);

        if (!class_exists($modelClass)) {
            \Yii::error($modelClass . ' does not exists', 'FileMaker.fmConnector');
            return;
        }

        try {
            $records = $record->getRelatedSet($tableSchema->name);
        } catch (\Exception $e) {
            return;
        }
        $models = [];

        foreach ($records as $record) {
            $model = $this->newRelatedRecord($relationName, $record);
            self::populateRecordFromFm($model, $record);
            $models[$record->getRecordId()] = $model;
        }

        $this->populateRelation($relationName, $models);
    }

    /**
     * Return the parent record if model represents a related record
     * @return FileMakerActiveRecord|FileMakerRelatedRecord
     */
    public function parentRecord()
    {
        return $this->_parent;
    }

    /**
     * return the FileMaker RecordID of the model
     * @return int
     */
    public function getRecId()
    {
        return $this->_recid;
    }

    /**
     * Add a global to be defined on update/create queries
     * @param string $fieldName
     * @param string $fieldValue
     * @return static
     */
    public function addGlobal($fieldName, $fieldValue)
    {
        $this->_globals[$fieldName] = $fieldValue;
        return $this;
    }

    /**
     * Delete à defined global
     * @param string $fieldName
     * @return static
     */
    public function deleteGlobal($fieldName)
    {
        unset($this->_globals[$fieldName]);
        return $this;
    }


    /**
     * Delete all globals sets
     * @return static
     */
    public function resetGlobals()
    {
        $this->_globals = [];
        return $this;
    }

    /**
     *
     * @param boolean $runValidation whether to perform validation (calling [[validate()]])
     * before saving the record. Defaults to `true`. If the validation fails, the record
     * will not be saved to the database and this method will return `false`.
     * @param array $attributeNames list of attribute names that need to be saved. Defaults to null,
     * meaning all attributes that are loaded from DB will be saved.
     * @return integer|boolean the number of rows affected, or false if validation fails
     * or [[beforeSave()]] stops the updating process.
     * @throws yii\db\StaleObjectException if [[optimisticLock|optimistic locking]] is enabled and the data
     * being updated is outdated.
     * @throws Exception in case update failed.
     */
    public function update($runValidation = true, $attributeNames = null)
    {
        if (!$this->beforeSave(false)) {
            return false;
        }
        if ($runValidation && !$this->validate($attributeNames)) {
            return false;
        }

        $values = $this->getDirtyAttributes($attributeNames);
        if (empty($values)) {
            //$this->addError('general', \Yii::t('app', 'Nothing to update'));
            $this->afterSave(false, $values);
            return true;
        }
        try {
            $token = 'update ' . __CLASS__ . ' ' . $this->getRecId();
            Yii::beginProfile($token, 'yii\db\Command::query');
            $connection = static::getDb();
            $request = $connection->newEditCommand(static::layoutName(), $this->getRecId(), $values);

            foreach ($this->_globals as $fieldName => $fieldValue) {
                $request->setGlobal($fieldName, $fieldValue);
            }

            $request->execute();

            $this->afterSave(false, $values);

            Yii::info($this->getDb()->getLastRequestedUrl(), __METHOD__);
            Yii::endProfile($token, 'yii\db\Command::query');
            return 1;
        } catch (\Exception $e) {
            Yii::info($this->getDb()->getLastRequestedUrl(), __METHOD__);
            Yii::endProfile($token, 'yii\db\Command::query');
            $this->addError('', $e->getMessage());
            return false;
            //throw new Exception($e->getMessage() . '(' . $e->getCode() . ')', $e->getCode());
        }
    }

    /**
     * Returns the attribute values that have been modified since they are loaded or saved most recently.
     * @param string[]|null $names the names of the attributes whose values may be returned if they are
     * changed recently. If null, [[attributes()]] will be used.
     * @return array the changed attribute values (name-value pairs)
     */
    public function getDirtyAttributes($names = null)
    {
        $values = parent::getDirtyAttributes($names);

        //Get updated related records values that are not in portals
        foreach ($this->getRelatedRecords() as $records) {
            if ($records instanceof FileMakerRelatedRecord) {
                $values = ArrayHelper::merge($values, $records->getDirtyAttributes($names));
            }
        }

        return $values;
    }

    /**
     * @param $attribute
     * @param bool $byRecId
     * @param null $layoutName
     * @return array|mixed|null
     * @throws FileMakerException
     * @throws NotSupportedException
     * @throws \Exception
     */
    public function valueList($attribute, $byRecId = false, $layoutName = null)
    {
        if (!array_key_exists($attribute, $this->attributeValueLists())) {
            return [];
        }
        if ($layoutName === null) {
            if ($this->parentRecord() !== null) {
                $layoutName = $this->parentRecord()->layoutName();
            } else {
                $layoutName = $this->layoutName();
            }
        }
        $valueList = $this->attributeValueLists()[$attribute];
        if (is_array($valueList)) {
            return $valueList;
        }
        $layout = static::getDb()->getSchema()->getlayout($layoutName);
        $recid = $this->parentRecord() !== null ? $this->parentRecord()->getRecId() : $this->getRecId();

        return array_flip($layout->getValueListTwoFields($valueList, $byRecId ? $recid : null));
    }

    /**
     * Repopulates this active record with the latest data.
     *
     * If the refresh is successful, an [[EVENT_AFTER_REFRESH]] event will be triggered.
     * This event is available since version 2.0.8.
     *
     * @return bool whether the row still exists in the database. If `true`, the latest data
     * will be populated to this active record. Otherwise, this record will remain unchanged.
     * @throws \Exception
     */
    public function refresh()
    {
        /* @var $record BaseActiveRecord */
        $record = static::findOne($this->getPrimaryKey(false));
        if ($record === null) {
            return false;
        }
        foreach ($this->attributes() as $name) {
            $this->_attributes[$name] = $record->hasAttribute($name) ? $record->getAttribute($name) : null;
        }
        $this->setOldAttributes($record->getOldAttributes());


        foreach ($record->getRelatedRecords() as $relationName => $records) {
            $this->populateRelation($relationName, $records);
        }
        $this->afterRefresh();

        return true;
    }

    /**
     * Return an attribute's "Friendly Value" according to its associated value list
     *
     * @param $attribute
     * @return mixed
     * @throws FileMakerException
     * @throws NotSupportedException
     * @throws \Exception
     */
    public function getFriendlyAttributeValue($attribute)
    {
        if (!array_key_exists($attribute, $this->attributeValueLists())) {
            return $this->$attribute;
        }
        $list = $this->valueList($attribute);
        if (!isset($list[$this->$attribute])) {
            return $this->$attribute;
        }

        return $list[$this->$attribute];
    }


    public static function encryptContainerUrl($url)
    {
        return base64_encode(Yii::$app->security->encryptByKey($url, get_called_class()));
    }

    public static function decryptContainerUrl($encryptedUrl)
    {
        return Yii::$app->security->decryptByKey(base64_decode($encryptedUrl), get_called_class());
    }
}

class FileMakerRelatedRecord extends FileMakerActiveRecord
{

    /**
     * Name of the relation
     * @var string
     */
    public $relationName;

    /**
     * Name of the FileMaker table occurrence the related record is based on
     * @return string
     */
    public $tableOccurence;

    /**
     * @return string
     * @throws NotSupportedException
     * @throws InvalidConfigException if the table for the AR class does not exist.
     */
    public function parentLayoutName()
    {
        return $this->parentRecord()->layoutName();
    }

    public function getTableSchemaFromParent($layout = null)
    {
        if ($this->parentRecord()->isPortal) {
            return $this->parentRecord()->getTableSchemaFromParent($layout)->relations[$this->relationName];
        } else {
            return $this->parentRecord()->getTableSchema($layout)->relations[$this->relationName];
        }
    }

    /**
     * Returns the list of all attribute names of the model.
     * The default implementation will return all column names of the table associated with this AR class.
     * @return array list of attribute names.
     */
    public function attributes()
    {
        $relationSchema = $this->getTableSchemaFromParent();
        $keys = array_keys($relationSchema->columns);
        return $keys;
    }


    /**
     * @param bool $runValidation
     * @param null $attributes
     * @return bool|int
     * @throws FileMakerException
     * @throws \Exception
     */
    public function insert($runValidation = true, $attributes = null)
    {
        if ($runValidation && !$this->validate($attributes)) {
            return false;
        }

        if (!$this->isPortal) {
            return $this->parentRecord()->insert($runValidation, $attributes);
        } else {
            $values = $this->getDirtyAttributes();
            foreach ($values as $field => $value) {
                $this->_record->setField($field, $value);
            }

            $token = 'insert ' . __CLASS__ . ' ' . $this->getRecId();
            Yii::beginProfile($token, 'yii\db\Command::query');
            try {
                $this->_record->commit();
                Yii::info($this->parentRecord()->getDb()->getLastRequestedUrl(), __METHOD__);
                Yii::endProfile($token, 'yii\db\Command::query');
                return 1;
            } catch (\Exception $e) {
                $this->addError('general', $e->getMessage());
                Yii::info($this->parentRecord()->getDb()->getLastRequestedUrl(), __METHOD__);
                Yii::endProfile($token, 'yii\db\Command::query');
                return false;
            }
        }
    }

    /**
     * @param bool $runValidation
     * @param null $attributeNames
     * @return bool|int
     * @throws \Exception
     * @throws FileMakerException
     */
    public function update($runValidation = true, $attributeNames = null)
    {
        if ($runValidation && !$this->validate($attributeNames)) {
            return false;
        }

        if (!$this->isPortal) {
            return $this->parentRecord()->update($runValidation, $attributeNames);
        } else {
            $values = $this->getDirtyAttributes();
            foreach ($values as $field => $value) {
                $this->_record->setField($field, $value);
            }

            $token = 'update ' . __CLASS__ . ' ' . $this->getRecId();
            Yii::beginProfile($token, 'yii\db\Command::query');
            try {
                $this->_record->commit();
                Yii::info($this->getDb()->getLastRequestedUrl(), __METHOD__);
                Yii::endProfile($token, 'yii\db\Command::query');
                return 1;
            } catch (\Exception $e) {
                $this->addError('general', $e->getMessage());
                Yii::info($this->getDb()->getLastRequestedUrl(), __METHOD__);
                Yii::endProfile($token, 'yii\db\Command::query');
                return false;
            }
        }
    }

    /**
     * Returns the attribute values that have been modified since they are loaded or saved most recently.
     * Prefix the relation tableName to field names
     * @param string[]|null $names the names of the attributes whose values may be returned if they are
     * changed recently. If null, [[attributes()]] will be used.
     * @return array the changed attribute values (name-value pairs)
     */
    public function getDirtyAttributes($names = null)
    {
        $values = parent::getDirtyAttributes($names);

        $prefixedValues = [];
        foreach ($values as $field => $value) {
            $prefixedValues[$this->tableOccurence . '::' . $field] = $value;
        }

        return $prefixedValues;
    }
}
