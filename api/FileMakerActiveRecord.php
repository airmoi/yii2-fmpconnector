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
    * @var string the default layout used to retrieve records
    */
    public static $defaultLayout;
    
    /**
     *
     * @var array 
     */
    protected $_attributes; 
    
    public $isPortal;
    
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
    
    public function __get($name) {
        if(preg_match('/^(\w+)\[(\d+)\]/', $name, $matches)){
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
        return array_keys($this->getDb()->getTableSchema($this->layoutName())->columns);
    }
    
    public function getAttributeLabel($attribute) {
        if(preg_match('/^(\w+)\[(\d+)\]/', $attribute, $matches)){
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
        if ($layout !== null){
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
    public static function findOne($condition, $layout = null){
        if(!ArrayHelper::isAssociative($condition)) {
            return static::find($layout)->getRecordById($condition);
        }

        return static::find($layout)->andWhere($condition)->one();
    }
    
    /**
     * @return string default FileMaker layout used by this model
     */
    public static function layoutName() {
        if(static::$defaultLayout === null) {
            throw new \yii\base\NotSupportedException('defaultLayout property must be set in model');
        }
        return static::$defaultLayout;
    }
    
    /**
     * Map value lists associated with fields
     * @return array associative array (field => valueListName)
     * @throws \yii\base\NotSupportedException
     */
    public function attributeValueLists() {
        throw new \yii\base\NotSupportedException('attributeValueLists Method should be overidded');
    }
    
    /**
     * @var string default FileMaker layout to be used for search queries
     */
    public static function searchLayoutName() {
        return static::layoutName();
    }
    
    /**
     * get filename of a container attribute
     * @param string $url the container url
     * @return string the filename
     */
    public static function getContainerFileName($url) {
        $name = basename($url);
        return substr($name, 0, strpos($name, "?"));
    }
    
    /**
     * Return the layout Object used by this model
     * @return Layout
     */
    public static function getLayout() {
        if(!isset(static::$_layout[static::layoutName()])) {
            $fm = static::getDb();
            static::$_layout[static::layoutName()] = $fm->getLayout(static::layoutName());
        }
        return static::$_layout[static::layoutName()];
    }

    /**
     * Returns the schema information of the DB table associated with this AR class.
     * @return TableSchema the schema information of the DB table associated with this AR class.
     * @throws InvalidConfigException if the table for the AR class does not exist.
     */
    public static function getTableSchema()
    {
        $schema = static::getDb()->getTableSchema(static::layoutName());
        if ($schema !== null) {
            return $schema;
        } else {
            throw new InvalidConfigException("The table does not exist: " . static::layoutName());
        }
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
        $tableSchema = $record->isPortal ? static::getDb()->getTableSchema(static::layoutName())->relations[$record->relationName] : static::getDb()->getTableSchema(static::layoutName());
        
        /* @var $tableSchema airmoi\yii2fmconnector\api\TableSchema */
        $row = [];
        $attributePrefix = $record->isPortal ? $record->tableOccurence . '::' : '';
        foreach ($record->attributes() as $attribute){
            if($tableSchema->columns[$attribute]->maxRepeat > 1){
                $row[$attribute] = [];
                for ($i = 0; $i <= $tableSchema->columns[$attribute]->maxRepeat; $i++) {
                    $row[$attribute][$i] = $fmRecord->getField($attributePrefix.$attribute, $i);
                }
            } else {
                $row[$attribute] = $fmRecord->getField($attributePrefix.$attribute);
            }
        }
        
        $row['_recid'] = $fmRecord->getRecordId();
        parent::populateRecord($record, $row);
        
        //Populate relations
        foreach ( $tableSchema->relations as $relationName => $tableSchema){
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
        
        if(!class_exists($modelClass)){
            \Yii::error($modelClass . ' does not exists' , 'FileMaker.fmConnector');
            return;
        }
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
     * @param \airmoi\FileMaker\Object\Record $record
     */
    
    /**
     * 
     * @param type $relationName
     * @param type $record
     * @return boolean
     */
    public function newRelatedRecord( $relationName, $record = null ) {
        $modelClass = substr(get_called_class(), 0, strrpos(get_called_class(), '\\')) . '\\' . ucfirst($relationName);
        
        
        if(!class_exists($modelClass)){
            \Yii::error($modelClass . ' does not exists' , 'FileMaker.fmConnector');
            throw new yii\base\InvalidParamException("relation's model class $modelClass is missing");
        }
        
        $tableSchema = static::getDb()->getTableSchema(static::layoutName())->relations[$relationName];
        $model = $modelClass::instantiate([]); 
        
        if($record === null && $this->_record !== null){
            $record = $this->_record->newRelatedRecord($tableSchema->name);
        }
        $model->_record = $record;
        $model->isPortal = true;
        $model->parent = $this;
        $model->relationName = $relationName;
        $model->tableOccurence = $tableSchema->name;
        //\Yii::configure($model, ['isPortal' => true, 'parent' => $this, 'relationName' => $relationName, '' => ]);
        
        return $model;
    }
    
    /**
     * 
     * @param string $relationName
     * @param ColumnSchema[] $fields
     * @param \airmoi\FileMaker\Object\Record $record
     */
    protected function populateHasManyRelation( $relationName, TableSchema $tableSchema, \airmoi\FileMaker\Object\Record $record) {
        $modelClass = substr(get_called_class(), 0, strrpos(get_called_class(), '\\')) . '\\' . ucfirst($relationName);
        
        if(!class_exists($modelClass)){
            \Yii::error($modelClass . ' does not exists' , 'FileMaker.fmConnector');
            return;
        }
        
        try {
            $records = $record->getRelatedSet($tableSchema->name);
        } catch (\Exception $e){
            return;
        }
        $models = [];
        
        foreach ( $records as $record ){
            $model = $this->newRelatedRecord($relationName, $record);
            self::populateRecordFromFm($model, $record);
            /*foreach ( $tableSchema->columns  as $fieldName => $config){
                $row[$fieldName] = $record->getField($tableSchema->name . '::' . $fieldName);
            }
            $row['_recid'] = $record->getRecordId();

            parent::populateRecord($model, $row);*/
            //$model->_recid = $record->getRecordId();
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
           $token = 'update '.__CLASS__ . ' '.$this->getRecId();
           Yii::beginProfile($token, 'yii\db\Command::query');
           $fm = static::getDb();
           $request = $fm->newEditCommand(static::layoutName(), $this->getRecId(), $values);
           $request->execute();
           
            Yii::info($this->db->getLastRequestedUrl(), __METHOD__);
            Yii::endProfile($token, 'yii\db\Command::query');
           return 1;
        } catch (\Exception $e) {
            Yii::info($this->db->getLastRequestedUrl(), __METHOD__);
            Yii::endProfile($token, 'yii\db\Command::query');
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
    
    public function valueList($attribute, $byRecId = false, $layoutName = null ) {
        if ( !array_key_exists($attribute, $this->attributeValueLists()) ){
            return [];
        }
        if($layoutName === null){
            if($this->isPortal) {
                $layoutName = $this->parentLayoutName(); 
            } else {
                $layoutName = $this->layoutName();
            }
        }
        $valueList = $this->attributeValueLists()[$attribute];
        $layout = static::getDb()->getSchema()->getlayout($layoutName);
        $recid = $this->isPortal ? $this->getParent()->getRecid() : $this->getRecId();
        
        return array_flip($layout->getValueListTwoFields($valueList, $byRecId ? $recid : null));
    }
    
    
    
    public static function encryptContainerUrl($url) {
        $user = Yii::$app->user;
        $request = Yii::$app->request;
        return base64_encode(Yii::$app->security->encryptByKey($url, get_called_class()));
    }
    
    public static function decryptContainerUrl($encryptedUrl) {
        return Yii::$app->security->decryptByKey(base64_decode($encryptedUrl), get_called_class());
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
     * Name of the relation
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
    
    public function getTableSchemaFromParent($layout = null) {
        if($this->getParent()->isPortal){
            return $this->getParent()->getTableSchemaFromParent($layout)->relations[$this->relationName];
        }
        else {
            return $this->getParent()->getTableSchema($layout)->relations[$this->relationName];
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
     * 
     * @return FileMakerActiveRecord
     */
    public function getParent() {
        return $this->parent;
    }
    
    public function insert($runValidation = true, $attributes = null) { 
        if ($runValidation && !$this->validate($attributes)) {
            return false;
        }
        
        if(!$this->isPortal){
            return $this->getParent()->insert($runValidation, $attributeNames);
        }  else {
            $values = $this->getDirtyAttributes();
            foreach ( $values as $field => $value ){
                $this->_record->setField($field, $value);
            }
            
           $token = 'insert '.__CLASS__ . ' '.$this->getRecId();
           Yii::beginProfile($token, 'yii\db\Command::query');
           try {
                $this->_record->commit();
                Yii::info($this->getParent()->getDb()->getLastRequestedUrl(), __METHOD__);
                Yii::endProfile($token, 'yii\db\Command::query');
                return 1;
           }
           catch ( \Exception $e) {
                $this->addError('general', $e->getMessage());
                Yii::info($this->getParent()->getDb()->getLastRequestedUrl(), __METHOD__);
                Yii::endProfile($token, 'yii\db\Command::query');
                return false;
            }
        }
    }
    
    public function update($runValidation = true, $attributeNames = null)
    {
        if ($runValidation && !$this->validate($attributeNames)) {
            return false;
        }
        
        if(!$this->isPortal){
            return $this->getParent()->update($runValidation, $attributeNames);
        } else {
            $values = $this->getDirtyAttributes();
            foreach ( $values as $field => $value ){
                $this->_record->setField($field, $value);
            }
            
           $token = 'update '.__CLASS__ . ' '.$this->getRecId();
           Yii::beginProfile($token, 'yii\db\Command::query');
           try {
                $this->_record->commit();
                Yii::info($this->getDb()->getLastRequestedUrl(), __METHOD__);
                Yii::endProfile($token, 'yii\db\Command::query');
                return 1;
           }
           catch ( \Exception $e) {
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
        foreach ( $values as $field => $value ){
            $prefixedValues[$this->tableOccurence.'::'.$field] = $value;
        }
        
        return $prefixedValues;
    }
    
}
