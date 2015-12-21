<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace airmoi\yii2fmconnector\api;

use Yii;
use yii\base\InvalidConfigException;
use airmoi\FileMaker\FileMaker;

/**
 * ActiveQuery represents a DB query associated with an Active Record class.
 *
 * An ActiveQuery can be a normal query or be used in a relational context.
 *
 * ActiveQuery instances are usually created by [[ActiveRecord::find()]].
 *
 * Normal Query
 * ------------
 *
 * ActiveQuery mainly provides the following methods to retrieve the query results:
 *
 * - [[one()]]: returns a single record populated with the first row of data.
 * - [[all()]]: returns all records based on the query results.
 * - [[count()]]: returns the number of records.
 * - [[sum()]]: returns the sum over the specified column.
 * - [[average()]]: returns the average over the specified column.
 * - [[min()]]: returns the min over the specified column.
 * - [[max()]]: returns the max over the specified column.
 * - [[scalar()]]: returns the value of the first column in the first row of the query result.
 * - [[column()]]: returns the value of the first column in the query result.
 * - [[exists()]]: returns a value indicating whether the query result has data or not.
 *
 * Because ActiveQuery extends from [[Find]], one can use query methods, such as [[where()]],
 * [[orderBy()]] to customize the query options.
 *
 * ActiveQuery also provides the following additional query options:
 *
 * - [[with()]]: list of relations that this query should be performed with.
 * - [[indexBy()]]: the name of the column by which the query result should be indexed.
 * - [[asArray()]]: whether to return each record as an array.
 *
 * These options can be configured using methods of the same name. For example:
 *
 * ```php
 * $customers = Customer::find()->with('orders')->asArray()->all();
 * ```
 *
 * Relational query
 * ----------------
 *
 * In relational context ActiveQuery represents a relation between two Active Record classes.
 *
 * Relational ActiveQuery instances are usually created by calling [[ActiveRecord::hasOne()]] and
 * [[ActiveRecord::hasMany()]]. An Active Record class declares a relation by defining
 * a getter method which calls one of the above methods and returns the created ActiveQuery object.
 *
 * A relation is specified by [[link]] which represents the association between columns
 * of different tables; and the multiplicity of the relation is indicated by [[multiple]].
 *
 * If a relation involves a junction table, it may be specified by [[via()]] or [[viaTable()]] method.
 * These methods may only be called in a relational context. Same is true for [[inverseOf()]], which
 * marks a relation as inverse of another relation and [[onCondition()]] which adds a condition that
 * is to be added to relational query join condition.
 *
 * @author airmoi <airmoi@gmail.com>
 * @since 2.0
 */
class ActiveFind extends \yii\base\Object implements \yii\db\QueryInterface
{
    public $modelClass;
    
    /**
     * Layout's name on which the query is based
     * @var string 
     */
    public $layout;
    
    /**
     *
     * @var Connection 
     */
    public $db;
    /**
     * @var array how to sort the query results. This is used to construct the ORDER BY clause in a SQL statement.
     * The array keys are the columns to be sorted by, and the array values are the corresponding sort directions which
     * can be either [SORT_ASC](http://php.net/manual/en/array.constants.php#constant.sort-asc)
     * or [SORT_DESC](http://php.net/manual/en/array.constants.php#constant.sort-desc).
     * The array may also contain [[Expression]] objects. If that is the case, the expressions
     * will be converted into strings without any change.
     */
    public $orderBy = [];
    /**
     * @var string|callable $column the name of the column by which the query results should be indexed by.
     * This can also be a callable (e.g. anonymous function) that returns the index value based on the given
     * row data. For more details, see [[indexBy()]]. This property is only used by [[QueryInterface::all()|all()]].
     */
    public $indexBy;
    /**
     * @var integer maximum number of records to be returned. If not set or less than 0, it means no limit.
     */
    public $limit;
    /**
     * @var integer zero-based offset from where the records are to be returned. If not set or
     * less than 0, it means starting from the beginning.
     */
    public $offset;
    /**
     * @var \airmoi\FileMaker\Command\CompoundFind
     */
    private $_cmd;
    /**
     * @var \airmoi\FileMaker\Command\FindRequest[]
     */
    private $_requests = [];
    /**
     * @var \airmoi\FileMaker\Command\FindRequest the current request being filled
     */
    private $_currentRequest;
    
    private $_count;
    
    /**
     * @var boolean whether to return each record as an array. If false (default), an object
     * of [[modelClass]] will be created to represent each record.
     */
    public $asArray;
    /**
     *
     * @var \airmoi\FileMaker\Object\Result 
     */
    private $_result;
    
    public $_recordId;

    /**
     * Constructor.
     * @param string $modelClass the model class associated with this query
     * @param array $config configurations to be applied to the newly created query object
     */
    public function __construct($modelClass, $config = [])
    {
        $this->modelClass = $modelClass;
        $this->layout = $modelClass::layoutName();
        $this->db = $modelClass::getDb();
        
        parent::__construct($config);
        
        /* @var $class FileMakerActiveRecord */
        $this->_cmd =  $this->db->newCompoundFindCommand($this->layout);
        $this->_currentRequest = $this->db->newFindRequest($this->layout);
        $this->_requests[] = $this->_currentRequest;
    }

    /**
     * Initializes the object.
     * This method is called at the end of the constructor. The default implementation will trigger
     * an [[EVENT_INIT]] event. If you override this method, make sure you call the parent implementation at the end
     * to ensure triggering of the event.
     */
    public function init()
    {
        parent::init();
        //$this->trigger(self::EVENT_INIT);
    }

    /**
     * Executes query and returns all results as an array.
     * @param Connection $db the DB connection used to create the DB command.
     * If null, the DB connection returned by [[modelClass]] will be used.
     * @return array|ActiveRecord[] the query results. If the query results in nothing, an empty array will be returned.
     */
    public function all($db = null)
    {
        try {
            $result = $this->execute();
            $rows = $result->getRecords();
            return $this->populate($rows);
        }
        catch (\Exception $e){
            if( $e->getCode() == 401 ){
                return [];
            }
            throw new \Exception($e->getMessage(), $e->errorInfo, (int) $e->getCode(), $e);
        }
    }
    
    /**
     * Sets the [[asArray]] property.
     * @param boolean $value whether to return the query results in terms of arrays instead of Active Records.
     * @return static the query object itself
     */
    public function asArray($value = true)
    {
        $this->asArray = $value;
        return $this;
    }

    /**
     * Prepare the request for execution
     */
    public function prepare()
    {
        $emptyRequest = true;
        //Add requests
        foreach($this->_requests as $i => $findrequest){
            if( !$findrequest->isEmpty()) {
                $this->_cmd->add($i, $findrequest);
                $emptyRequest = false;
            }
        }
        
        //Tranform query to findall query if no find request set
        if($emptyRequest && $this->_cmd instanceof \airmoi\FileMaker\Command\CompoundFind){
            $this->_cmd = $this->db->newFindAllCommand($this->layout);
        }
        
        //Add sort rules
        foreach($this->orderBy as $fieldName => $precedence){
            $this->_cmd->addSortRule($fieldName, $precedence);
        }
        
        //Apply limits & offset
        $this->offset = $this->offset == -1 ? null: $this->offset;
        $this->limit = $this->limit == -1 ? null: $this->limit;
        $this->_cmd->setRange($this->offset, $this->limit);
    }
    
    /**
     * Prepare and execute the query
     * @return \airmoi\FileMaker\Object\Result
     */
    public function execute() {
        if( $this->_result === null){
            $this->prepare();
            
            Yii::beginProfile($this->serializeQuery(), 'yii\db\Command::query');
            
            try {
                $this->_result = $this->_cmd->execute();
                $this->_count = $this->_result->getTableRecordCount();
                Yii::info($this->db->getLastRequestedUrl(), __METHOD__);
                
            } catch (airmoi\FileMaker\FileMakerException $e){
                Yii::endProfile($this->serializeQuery(), 'yii\db\Command::query');
                throw $this->db->getSchema()->convertException($e, $this->serializeQuery());
            }
            
            Yii::endProfile($this->serializeQuery(), 'yii\db\Command::query');
        }
        return $this->_result;
    }

    /**
     * @inheritdoc
     */
    public function populate($rows)
    {
        if (empty($rows)) {
            return [];
        }

        $models = $this->createModels($rows);
        if ($this->indexBy === null) {
            $models = $this->removeDuplicatedModels($models);
        }
        
        if (!$this->asArray) {
            foreach ($models as $model) {
                $model->afterFind();
            }
        }

        return $models;
    }
    
    /**
     * Converts found rows into model instances
     * @param \airmoi\FileMaker\Object\Record[] $records
     * @return array|FileMakerActiveRecord[]
     */
    private function createModels($records)
    {
        $models = [];
        if ($this->asArray) {
            /*if ($this->indexBy === null) {
                return $rows;
            }*/
            foreach ($records as $key => $record) {
                $row = [];
                if (is_string($this->indexBy)) {
                    $key = $record->getField($this->indexBy);
                } elseif(is_callable($this->indexBy)) {
                    $key = call_user_func($this->indexBy, $record);
                }
                $row['_recid'] = $record->getRecordId();
                foreach($record->getFields() as $field){
                    $row[$field] = $record->getField($field);
                }
                
                //Store related sets
                foreach($record->getLayout()->getRelatedSets() as $relatedSetName => $relatedset) { 
                    foreach ( $relatedSet as $i => $record) {
                        $row[$relatedSetName][$i] = ['_recid' => $record->getRecordId()];
                        foreach($record->getFields() as $field){
                            $row[$relatedSetName][$i][$field] = $relatedset->getField($field);
                        }
                    }
                }
                $models[$key] = $row;
            }
        } else {
            /* @var $class FileMakerActiveRecord */
            $class = $this->modelClass;
            if ($this->indexBy === null) {
                foreach ($records as $record) {
                    $model = $class::instantiate($record);
                    $class::populateRecordFromFm($model, $record);
                    $models[] = $model;
                }
            } else {
                foreach ($records as $record) {
                    $model = $class::instantiate($record);
                    $class::populateRecordFromFm($model, $record);
                    if (is_string($this->indexBy)) {
                        $key = $model->{$this->indexBy};
                    } else {
                        $key = call_user_func($this->indexBy, $model);
                    }
                    $models[$key] = $model;
                }
            }
        }

        return $models;
    }

    /**
     * Removes duplicated models by checking their primary key values.
     * This method is mainly called when a join query is performed, which may cause duplicated rows being returned.
     * @param array $models the models to be checked
     * @throws InvalidConfigException if model primary key is empty
     * @return array the distinctive models
     */
    private function removeDuplicatedModels($models)
    {
        $hash = [];
        /* @var $class ActiveRecord */
        $class = $this->modelClass;
        $pks = $class::primaryKey();

        if (count($pks) > 1) {
            // composite primary key
            foreach ($models as $i => $model) {
                $key = [];
                foreach ($pks as $pk) {
                    if (!isset($model[$pk])) {
                        // do not continue if the primary key is not part of the result set
                        break 2;
                    }
                    $key[] = $model[$pk];
                }
                $key = serialize($key);
                if (isset($hash[$key])) {
                    unset($models[$i]);
                } else {
                    $hash[$key] = true;
                }
            }
        } elseif (empty($pks)) {
            throw new InvalidConfigException("Primary key of '{$class}' can not be empty.");
        } else {
            // single column primary key
            $pk = reset($pks);
            foreach ($models as $i => $model) {
                if (!isset($model[$pk])) {
                    // do not continue if the primary key is not part of the result set
                    break;
                }
                $key = $model[$pk];
                if (isset($hash[$key])) {
                    unset($models[$i]);
                } elseif ($key !== null) {
                    $hash[$key] = true;
                }
            }
        }

        return array_values($models);
    }

    /**
     * Executes query and returns a single row of result.
     * @param Connection $db the DB connection used to create the DB command.
     * If null, the DB connection returned by [[modelClass]] will be used.
     * @return ActiveRecord|array|null a single row of query result. Depending on the setting of [[asArray]],
     * the query result may be either an array or an ActiveRecord object. Null will be returned
     * if the query results in nothing.
     */
    public function one($db = null)
    {
        try {
            $result = $this->execute();
            $rows = $result->getFirstRecord();
            return $this->populate([$rows])[0];
        }
        catch (\Exception $e){
            if( $e->getCode() == 401 ){
                return null;
            }
            throw new Exception($e->getMessage(), $e->errorInfo, (int) $e->getCode(), $e);
        }
    }
    
    /**
     * Sets the WHERE part of the current Request.
     * CAUTION : This will reset all existing requets / where conditions
     * To add condition to existing where query, use andWhere() instead
     *
     * The method requires a `$condition` parameter.
     *
     * The `$condition` parameter should be an array (field => value).
     *
     * @inheritdoc
     *
     * @param array $condition the conditions that should be put in the WHERE part.
     * @param string $layout the name of the layout the request should be based on (if different from the model one).
     * @return static the query object itself
     */
    public function where($condition, $layout = null)
    {
        if( $layout === null ) {
            $layout = $this->layout;
        }
        $this->_currentRequest = $this->db->newFindRequest($layout);
        $this->_requests = [$this->_currentRequest];
        foreach($condition as $fieldName => $testvalue) {
            if(!empty($testvalue)) {
                $this->_currentRequest->addFindCriterion($fieldName, $testvalue);
            }
        }
        return $this;
    }
    
    /**
     * Adds an additional WHERE condition to the existing one.
     * The new condition and the existing one will be joined using the 'AND' operator.
     * @param array $condition the new WHERE condition (name => value)
     * @return static the query object itself
     * @see where()
     * @see orWhere()
     */
    public function andWhere($condition)
    {
        foreach($condition as $fieldName => $testvalue) {
            if(!empty($testvalue)) {
                $this->_currentRequest->addFindCriterion($fieldName, $testvalue);
            }
        }
        return $this;
    }
    
    /**
     * Adds an additional WHERE condition to the existing one.
     * The new condition and the existing one will be joined using the 'AND' operator.
     * @param array $condition the new WHERE condition (name => value)
     * @return static the query object itself
     * @see where()
     * @see orWhere()
     */
    public function filterWhere(array $condition)
    {
        $this->where($condition);
        return $this;
    }
    
    /**
     * Adds an additional WHERE condition to the existing one.
     * The new condition and the existing one will be joined using the 'AND' operator.
     * @param array $condition the new WHERE condition (name => value)
     * @return static the query object itself
     * @see where()
     * @see orWhere()
     */
    public function andFilterWhere(array $condition)
    {
        $this->andWhere($condition);
        return $this;
    }

    /**
     * Adds an additional WHERE condition to the existing one.
     * The new condition and the existing one will be joined using the 'OR' operator.
     * @param string|array $condition the new WHERE condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @return static the query object itself
     * @see where()
     * @see andWhere()
     */
    public function orWhere($condition, $layout = null)
    {
        if( $layout === null ) {
            $layout = $this->layout;
        }
        $this->_currentRequest = $this->db->newFindRequest($layout);
        $this->_requests[] = $this->_currentRequest;
        $this->andWhere($condition);
        return $this;
    }

    /**
     * Adds an additional WHERE condition to the existing one.
     * The new condition and the existing one will be joined using the 'OR' operator.
     * @param string|array $condition the new WHERE condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @return static the query object itself
     * @see where()
     * @see andWhere()
     */
    public function orFilterWhere(array $condition, $layout = null)
    {
        if( $layout === null ) {
            $layout = $this->layout;
        }
        $this->_currentRequest = $this->db->newFindRequest($layout);
        $this->_requests[] = $this->_currentRequest;
        $this->andWhere($condition);
        return $this;
    }

    /**
     * Sets the ORDER BY part of the query.
     * @param string|array $columns the columns (and the directions) to be ordered by.
     * Columns can be specified in either a string (e.g. `"id ASC, name DESC"`) or an array
     * (e.g. `['id' => SORT_ASC, 'name' => SORT_DESC]`).
     * The method will automatically quote the column names unless a column contains some parenthesis
     * (which means the column contains a DB expression).
     * Note that if your order-by is an expression containing commas, you should always use an array
     * to represent the order-by information. Otherwise, the method will not be able to correctly determine
     * the order-by columns.
     * @return static the query object itself.
     * @see addOrderBy()
     */
    public function orderBy($columns)
    {
        $this->orderBy = $this->normalizeOrderBy($columns);
        return $this;
    }

    /**
     * Adds additional ORDER BY columns to the query.
     * @param string|array $columns the columns (and the directions) to be ordered by.
     * Columns can be specified in either a string (e.g. "id ASC, name DESC") or an array
     * (e.g. `['id' => FileMaker::SORT_ASC, 'name' => SORT_DESC]`).
     * The method will automatically quote the column names unless a column contains some parenthesis
     * (which means the column contains a DB expression).
     * @return static the query object itself.
     * @see orderBy()
     */
    public function addOrderBy($columns)
    {
        $columns = $this->normalizeOrderBy($columns);
        if ($this->orderBy === null) {
            $this->orderBy = $columns;
        } else {
            $this->orderBy = array_merge($this->orderBy, $columns);
        }
        return $this;
    }

    /**
     * Normalizes format of ORDER BY data
     *
     * @param array|string $columns
     * @return array
     */
    protected function normalizeOrderBy($columns)
    {
        if (is_array($columns)) {
            return $columns;
        } else {
            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
            $result = [];
            foreach ($columns as $column) {
                if (preg_match('/^(.*?)\s+(asc|desc)$/i', $column, $matches)) {
                    $result[$matches[1]] = strcasecmp($matches[2], 'desc') ? FileMaker::SORT_ASCENDC : FileMaker::SORT_DESCEND;
                } else {
                    $result[$column] = FileMaker::SORT_ASCENDC;
                }
            }
            return $result;
        }
    }

    /**
     * Sets the LIMIT part of the query.
     * @param integer $limit the limit. Use null or negative value to disable limit.
     * @return static the query object itself.
     */
    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Sets the OFFSET part of the query.
     * @param integer $offset the offset. Use null or negative value to disable offset.
     * @return static the query object itself.
     */
    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Returns the number of records.
     * @param string $q unused.
     * @param Connection $db unused.
     * If this parameter is not given, the `db` application component will be used.
     * @return integer number of records
     */
    public function count($q = '*', $db = NULL) {
        $this->execute();
        return $this->_count;
    }

    /**
     * Returns a value indicating whether the query result contains any row of data.
     * @param Connection $db unused.
     * If this parameter is not given, the `db` application component will be used.
     * @return boolean whether the query result contains any row of data.
     */
    public function exists($db = null) {
        $this->execute();
        return $this->_count > 0;
    }/**
     * Sets the [[indexBy]] property.
     * @param string|callable $column the name of the column by which the query results should be indexed by.
     * This can also be a callable (e.g. anonymous function) that returns the index value based on the given
     * row data. The signature of the callable should be:
     *
     * ~~~
     * function ($row)
     * {
     *     // return the index value corresponding to $row
     * }
     * ~~~
     *
     * @return static the query object itself.
     */
    public function indexBy($column)
    {
        $this->indexBy = $column;
        return $this;
    }
    
    private function serializeQuery() {
        $command = ['layout' => $this->layout];
        $command['method'] = get_class($this->_cmd);
        if($this->_cmd instanceof \airmoi\FileMaker\Command\CompoundFind){
            foreach( $this->_requests as $request ){
               $command['method']['requests'][] = $request->findCriteria;
            }
        } 
        $command['offset'] = $this->offset;
        $command['limit'] = $this->limit;
        $command['sort'] = $this->orderBy;
        
        return json_encode($command);
    }
    
    public function getRecordById($id){
        $this->_cmd = $this->db->newFindCommand($this->layout);
        $this->_cmd->setRecordId($id);
        return $this->one();
    }
}
