<?php
namespace airmoi\yii2fmconnector\api;

use yii;
use yii\base\Model;


/**
 * Description of FileMakerModel
 *
 * @author romain
 */
class FileMakerModel extends \yii\db\BaseActiveRecord 
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
     * @return \airmoi\FileMaker\Command\Find the newly created [[Find]] instance.
     */
    public static function find()
    {
        return static::getDb()->newFindCommand(static::layoutName());
    }
    
    /**
     * @inheritdoc
     * @return static[]
     */
    public static function findAll($condition = [], callable $callback = null)
    {
        try {
            $query = static::getDb()->newFindCommand(static::layoutName());

            foreach($condition as $field => $value){
                $query->addFindCriterion($field, $value);
            }
            $result = $query->execute();
            $array = [];
            foreach ( $result->getRecords() as $record){
                $model = new static();
                static::populateRecordFromFm($model, $record);
                
                if(!is_null($callback)){
                    call_user_func($callback, $model);
                }
                $array[] = $model;
            }
            return $array;
        } catch( \airmoi\FileMaker\FileMakerException $ex){
            // catch "not found" error
            if ( $ex->getCode() == '101'){
                return [];
            }
            throw $ex;
        }
    }
    
    
    
    /**
     * @inheritdoc
     * @return static[]
     */
    public static function findAllAsArray($condition =null)
    {
        $query = static::getDb()->newFindAllCommand(static::layoutName());
        $result = $query->execute();
        $array = [];
        foreach ( $result->getRecords() as $record){
            $array[] = $record;
        }
        return $array;
    }
    
    
    /**
     * @inheritdoc
     * @return \airmoi\FileMaker\Command\Find the newly created [[Find]] instance.
     */
    public static function compoundFind()
    {
        return static::getDb()->newCompoundFindCommand(static::layoutName());
    }
            
    public static function findOne($id){
        try{
             $fm = static::getDb();
             if(is_array($id)){
                 $id = $id[static::primaryKey()[0]];
             }
            $request = $fm->newFindCommand(static::layoutName());
            $request->setRecordId($id);
            //$request->addFindCriterion(static::primaryKey()[0], $id);
            $result = $request->execute();
            
            $model = new static();
            static::populateRecordFromFm($model, $result->getFirstRecord());
            return $model;
            
        } catch (\airmoi\FileMaker\FileMakerException $ex) {
            Yii::error($ex->getMessage(), __METHOD__);
            if($ex->getCode() === 101){
                return null;
            }
            throw new \yii\web\HttpException($ex->getMessage());
        }   
    }
    
            
    public static function findOneByRecID($recid){
        
        try{
             $fm = static::getDb();
        
            $request = $fm->newFindCommand(static::layoutName());
            $request->recordId = $recid;
            $result = $request->execute();
            
            $record = $result->getFirstRecord();
            
            $model = new static();
            $model->populateRecordFromFm($model, $record);
            return $model;
            
        } catch (\airmoi\FileMaker\FileMakerException $ex) {
            Yii::error($ex->getMessage(), __METHOD__);
            throw new \yii\web\HttpException('Serveur introuvable');
        }
    }
    
    public static function layoutName() {
        throw new \yii\base\NotSupportedException('layoutName Method should be overidded');
    }
    
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
    protected static function populateRecordFromFm(FileMakerModel $record, \airmoi\FileMaker\Object\Record $fmRecord){
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
    
}
