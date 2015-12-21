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
        foreach ( static::getDb()->getSchema()->getTableSchema(static::layoutName())->relations as $relationName => $relationConfig){
            if( !$relationConfig[0] ){
                $record->populateHasOneRelation($relationName, $relationConfig[1], $fmRecord);
            }
            else {
                $record->populateHasManyRelation($relationName, $relationConfig[1], $fmRecord);
            }
        }

        return $record;
    }
    
    /**
     * 
     * @param string $relationName
     * @param ColumnSchema[] $fields
     * @param \airmoi\FileMaker\Object\Record $record
     */
    protected function populateHasOneRelation( $relationName, $fields, \airmoi\FileMaker\Object\Record $record) {
        $modelClass = substr(get_called_class(), 0, strrpos(get_called_class(), '\\')) . '\\' . ucfirst($relationName);
        
        $model = new $modelClass();
        foreach ( $fields as $fieldName => $config){
            $model->$fieldName = $record->getField($relationName . '::' . $fieldName);
        }
        
        $this->populateRelation($relationName, $model);
    }
    
    /**
     * 
     * @param string $relationName
     * @param ColumnSchema[] $fields
     * @param \airmoi\FileMaker\Object\Record $record
     */
    protected function populateHasManyRelation( $relationName, $fields, \airmoi\FileMaker\Object\Record $record) {
        $modelClass = substr(get_called_class(), 0, strrpos(get_called_class(), '\\')) . '\\' . ucfirst($relationName);
        
        $records = $record->getRelatedSet($relationName);
        $models = [];
        
        foreach ( $records as $record ){ 
            $model = new $modelClass();
            foreach ( $fields as $fieldName => $config){
                $model->$fieldName = $record->getField($relationName . '::' . $fieldName);
            } 
            $model->$fieldName = $record->getRecordId();
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
            throw $e;
        }
    }
}

class FileMakerRelatedRecord extends Model 
{
    /**
     * @var array attribute values indexed by attribute names
     */
    protected $_attributes = [];
    /**
     * @var array|null old attribute values indexed by attribute names.
     * This is `null` if the record [[isNewRecord|is new]].
     */
    private $_oldAttributes;
    
    
    public function attributes()
    {
        return $this->_attributes;
    }

    /**
     * Returns a value indicating whether the model has an attribute with the specified name.
     * @param string $name the name of the attribute
     * @return boolean whether the model has an attribute with the specified name.
     */
    public function hasAttribute($name)
    {
        return isset($this->_attributes[$name]) || in_array($name, $this->attributes());
    }
    
    /**
     * Returns the attribute values that have been modified since they are loaded or saved most recently.
     * @param string[]|null $names the names of the attributes whose values may be returned if they are
     * changed recently. If null, [[attributes()]] will be used.
     * @return array the changed attribute values (name-value pairs)
     */
    public function getDirtyAttributes($names = null)
    {
        if ($names === null) {
            $names = $this->attributes();
        }
        $names = array_flip($names);
        $attributes = [];
        if ($this->_oldAttributes === null) {
            foreach ($this->_attributes as $name => $value) {
                if (isset($names[$name])) {
                    $attributes[$name] = $value;
                }
            }
        } else {
            foreach ($this->_attributes as $name => $value) {
                if (isset($names[$name]) && (!array_key_exists($name, $this->_oldAttributes) || $value !== $this->_oldAttributes[$name])) {
                    $attributes[$name] = $value;
                }
            }
        }
        return $attributes;
    }
    
    /**
     * PHP getter magic method.
     * This method is overridden so that attributes and related objects can be accessed like properties.
     *
     * @param string $name property name
     * @throws \yii\base\InvalidParamException if relation name is wrong
     * @return mixed property value
     * @see getAttribute()
     */
    public function __get($name)
    {
        if (isset($this->_attributes[$name]) || array_key_exists($name, $this->_attributes)) {
            return $this->_attributes[$name];
        } elseif ($this->hasAttribute($name)) {
            return null;
        } else {
            if (isset($this->_related[$name]) || array_key_exists($name, $this->_related)) {
                return $this->_related[$name];
            }
            $value = parent::__get($name);
            if ($value instanceof ActiveQueryInterface) {
                return $this->_related[$name] = $value->findFor($name, $this);
            } else {
                return $value;
            }
        }
    }

    /**
     * PHP setter magic method.
     * This method is overridden so that AR attributes can be accessed like properties.
     * @param string $name property name
     * @param mixed $value property value
     */
    public function __set($name, $value)
    {
        if ($this->hasAttribute($name)) {
            $this->_attributes[$name] = $value;
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * Checks if a property value is null.
     * This method overrides the parent implementation by checking if the named attribute is null or not.
     * @param string $name the property name or the event name
     * @return boolean whether the property value is null
     */
    public function __isset($name)
    {
        try {
            return $this->__get($name) !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Sets a component property to be null.
     * This method overrides the parent implementation by clearing
     * the specified attribute value.
     * @param string $name the property name or the event name
     */
    public function __unset($name)
    {
        if ($this->hasAttribute($name)) {
            unset($this->_attributes[$name]);
        } else {
            parent::__unset($name);
        }
    }
}
