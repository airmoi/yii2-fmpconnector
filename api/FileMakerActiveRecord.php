<?php
namespace airmoi\yii2fmconnector\api;

use yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;


/**
 * Description of FileMakerModel
 *
 * @author romain
 */
class FileMakerActiveRecord extends \yii\db\BaseActiveRecord 
{
    /**
     *
     * @var array 
     */
    protected $_attributes; 
    
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
     *
     * @var \airmoi\FileMaker\Object\Record 
     */
    protected $_record;  
    
    /**
     *
     * @var \airmoi\FileMaker\Object\Layout 
     */
    protected static $_layout; 
    
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
    public static function primaryKey(){
        return ['_recid'];
    }
    
    /**
     * Returns the list of all attribute names of the model.
     * The default implementation will return all column names of the table associated with this AR class.
     * @return array list of attribute names.
     */
    public function attributes()
    {
        return array_keys($this->getDb()->getSchema()->getTableSchema($this->layoutName())->columns);
    }
    
    /**
     * @inheritdoc
     * @return ActiveFind the newly created [[ActiveFind]] instance.
     */
    public static function find()
    {
        return Yii::createObject(ActiveFind::className(), [get_called_class()]);
    }
    
    /**
     * @inheritdoc
     * @return static[]
     */
    public static function findAll($condition = [], callable $callback = null)
    {
        $query = static::find();
        $query->andWhere($condition);
        return $query->all();
    }
    
    /**
     * @inheritdoc
     * @return \airmoi\FileMaker\Command\Find the newly created [[Find]] instance.
     */
    public static function compoundFind()
    {
        return static::getDb()->newCompoundFindCommand(static::layoutName());
    }
    
    /**
     * 
     * @param mixed $condition primary key value or a set of column values
     * @return static ActiveRecord instance matching the condition, or null if nothing matches.
     * @throws \yii\web\HttpException
     */
    public static function findOne($condition){
        if(!ArrayHelper::isAssociative($condition)) {
            return self::find()->getRecordById($condition);
        }

        return self::find()->andWhere($condition)->one();
    }
    
    public static function layoutName() {
        throw new \yii\base\NotSupportedException('layoutName Method should be overidded');
    }
    
    /**
     * Return the layout name use by this model
     * @return string
     */
    public static function getLayout() {
        if(!isset(static::$_layout[static::layoutName()])) {
            $fm = static::getDb();
            static::$_layout[static::layoutName()] = $fm->getLayout(static::layoutName());
        }
        return static::$_layout[static::layoutName()];
    }
    
    public function load($data, $formName = NULL ) {
        $loaded = (int) parent::load($data);
        
        foreach ($this->getRelatedRecords() as $relationName => $records) {
            if($records instanceof FileMakerRelatedRecord && !$records->isPortal){
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
        
        try {
           $fm = static::getDb();
           $request = $fm->newAddCommand(static::layoutName(), $this->getDirtyAttributes());
           $result = $request->execute();
           $this->_recid = $result->getFirstRecord()->getRecordId();
           return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }
    
    /**
     * 
     * @param \airmoi\yii2fmconnector\api\FileMakerModel $record
     * @param \airmoi\FileMaker\Object\Record $fmRecord
     * @return self
     */
    public static function populateRecordFromFm(FileMakerActiveRecord $record, \airmoi\FileMaker\Object\Record $fmRecord){
        $record->_record = $fmRecord;
        $row = [];
        foreach ($record->attributes() as $attribute){
            $row[$attribute] = $fmRecord->getField($attribute);
        }
        $row['_recid'] = $fmRecord->getRecordId();
        parent::populateRecord($record, $row);
        
        //Populate relations
        foreach ( static::getDb()->getSchema()->getTableSchema(static::layoutName())->relations as $relationName => $tableSchema){
            if( !$tableSchema->isPortal ){
                $record->populateHasOneRelation($relationName, $tableSchema, $fmRecord);
            }
            else {
                $record->populateHasManyRelation($relationName, $tableSchema, $fmRecord);
            }
        }

        return $record;
    }
    
    /**
     * 
     * @param string $relationName
     * @param TableSchema $tableSchema
     * @param \airmoi\FileMaker\Object\Record $record
     */
    protected function populateHasOneRelation( $relationName, TableSchema $tableSchema, \airmoi\FileMaker\Object\Record $record) {
        $modelClass = substr(get_called_class(), 0, strrpos(get_called_class(), '\\')) . '\\' . ucfirst($relationName);
        
        $model = $modelClass::instantiate([]);
        \Yii::configure($model, ['isPortal' => false, 'parent' => $this, 'relationName' =>$relationName, 'tableOccurence' => $tableSchema->name]);
        
        $row = [];
        foreach ( $tableSchema->columns as $fieldName => $config){
            $row[$fieldName] = $record->getField($tableSchema->name . '::' . $fieldName);
        }
        
        parent::populateRecord($model, $row);
        
        $this->populateRelation($relationName, $model);
    }
    
    /**
     * 
     * @param string $relationName
     * @param ColumnSchema[] $fields
     * @param \airmoi\FileMaker\Object\Record $record
     */
    protected function populateHasManyRelation( $relationName, TableSchema $tableSchema, \airmoi\FileMaker\Object\Record $record) {
        $modelClass = substr(get_called_class(), 0, strrpos(get_called_class(), '\\')) . '\\' . ucfirst($relationName);
        
        $records = $record->getRelatedSet($tableSchema->name);
        $models = [];
        
        foreach ( $records as $record ){
            $model = $modelClass::instantiate([]); 
            \Yii::configure($model, ['isPortal' => true, 'parent' => $this, 'relationName' => $relationName, 'tableOccurence' => $tableSchema->name]);
            $row = [];
            foreach ( $tableSchema->columns  as $fieldName => $config){
                $row[$fieldName] = $record->getField($tableSchema->name . '::' . $fieldName);
            }

            parent::populateRecord($model, $row);
            $model->_recid = $record->getRecordId();
            $models[$record->getRecordId()] = $model;
        }
        
        $this->populateRelation($relationName, $models);
    }
    
    public function getRecId(){
        return $this->_recid;
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
     * @throws StaleObjectException if [[optimisticLock|optimistic locking]] is enabled and the data
     * being updated is outdated.
     * @throws Exception in case update failed.
     */
    public function update($runValidation = true, $attributeNames = null)
    {
        if ($runValidation && !$this->validate($attributeNames)) {
            return false;
        }
        
        $values = $this->getDirtyAttributes($attributeNames);
        if( empty($values) ){
            $this->addError('general', \Yii::t('app', 'Nothing to update'));
            return false;
        }
        try {
           $fm = static::getDb();
           $request = $fm->newEditCommand(static::layoutName(), $this->getRecId(), $values);
           $request->execute();
           return 1;
        } catch (\Exception $e) {
            throw new \yii\db\Exception($e->getMessage() . '(' . $e->getCode() . ')', $e->getCode());
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
        foreach ( $this->getRelatedRecords() as $relationName => $records ){
            if ( $records instanceof FileMakerRelatedRecord){
                $values = ArrayHelper::merge($values, $records->getDirtyAttributes($names));
            }
        }
        
        return $values;
    }
}

class FileMakerRelatedRecord extends FileMakerActiveRecord 
{
    /**
     * Whether the related record is part of a portal
     * @var bool 
     */
    public $isPortal;
    
    /**
     *
     * @var FileMakerActiveRecord
     */
    public $parent;
    
    /**
     * Name of the relation)
     * @var string
     */
    public $relationName;
    
    /**
     * Name of the FileMaker table occurrence the related record is based on
     * @return string
     */
    public $tableOccurence;
    
    public function parentLayoutName() {
        return $this->getParent()->layoutName();
    }
    
    /**
     * Returns the list of all attribute names of the model.
     * The default implementation will return all column names of the table associated with this AR class.
     * @return array list of attribute names.
     */
    public function attributes()
    {
        $relationSchema = $this->getParent()->getDb()->getSchema()->getTableSchema($this->parentLayoutName())->relations[$this->relationName];
        $keys = array_keys($relationSchema->columns);
        return $keys;
    }
    
    /**
     * 
     * @return FileMakerActiveRecord
     */
    public function getParent() {
        return $this->parent;
    }
    
    public function update($runValidation = true, $attributeNames = null)
    {
        if(!$this->isPortal()){
            return $this->getParent()->update($runValidation, $attributeNames);
        } else {
            throw new \yii\base\NotSupportedException('You cannot edit a related records in portal views');
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
        foreach ( $values as $field => $value ){
            $prefixedValues[$this->tableOccurence.'::'.$field] = $value;
        }
        
        return $prefixedValues;
    }
    
}
