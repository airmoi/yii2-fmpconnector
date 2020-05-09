<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace airmoi\yii2fmconnector\api;

use airmoi\FileMaker\Command\CompoundFind;
use airmoi\FileMaker\Command\Find;
use airmoi\FileMaker\Command\PerformScript;
use airmoi\FileMaker\Object\Result;
use Yii;
use yii\base\InvalidConfigException;
use airmoi\FileMaker\FileMaker;
use yii\base\NotSupportedException;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveQueryTrait;
use yii\db\Exception;

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
class ActiveFind extends \yii\base\BaseObject implements ActiveQueryInterface
{
    use ActiveQueryTrait;
    use ActiveRelationTrait;

    /**
     * Layout's name on which the query is based
     * @var string
     */
    public $layout;

    /**
     * Layout's name on which the query is based
     * @var string
     */
    public $resultLayout;

    public $relatedSetFilter = 'none';
    public $relatedSetMax = null;

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
     * @var boolean whether to emulate the actual query execution, returning empty or false results.
     * @see emulateExecution()
     * @since 2.0.11
     */
    public $emulateExecution = false;

    public $_recordId;

    /**
     * Conditions that will be applied to all requets
     * @var array
     */
    public $filterAll = [];

    public $inClause = [];
    /**
     *
     * @var Result
     */
    private $_result;

    /**
     * @var array scripts to be executed before / after find and before sort
     *
     */
    private $_scripts = [];
    /**
     * @var \airmoi\FileMaker\Command\CompoundFind|\airmoi\FileMaker\Command\PerformScript
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
     * Constructor.
     * @param string $modelClass the model class associated with this query
     * @param array $config configurations to be applied to the newly created query object
     */
    public function __construct($modelClass, $config = [])
    {
        $this->modelClass = $modelClass;
        $this->layout = $modelClass::searchLayoutName();
        $this->resultLayout = $modelClass::layoutName();
        $this->db = $modelClass::getDb();

        parent::__construct($config);
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

        /* @var $class FileMakerActiveRecord */
        $this->_cmd = $this->db->newCompoundFindCommand($this->layout);
        $this->_currentRequest = $this->db->newFindRequest($this->layout);
        $this->_requests[] = $this->_currentRequest;
        //$this->trigger(self::EVENT_INIT);
    }

    /**
     * Executes query and returns all results as an array.
     * @param Connection $db the DB connection used to create the DB command.
     * If null, the DB connection returned by [[modelClass]] will be used.
     * @return FileMakerActiveRecord[]|array the query results. If the query results in nothing, an empty array will be returned.
     * @throws \Exception
     */
    public function all($db = null)
    {
        if ($this->emulateExecution) {
            return [];
        }
        try {
            $result = $this->execute();
            $rows = $result->getRecords();
        } catch (\Exception $e) {
            if ($e->getCode() == 401) {
                return [];
            }
            throw $e;
        }

        return $this->populate($rows);
    }

    /**
     * Prepare the request for execution
     */
    public function prepare()
    {
        //No prepare when retrieving record from its ID
        if ($this->_cmd instanceof Find && $this->_cmd->recordId !== null) {
            return;
        }

        if (!$this->_cmd instanceof PerformScript) {

            if ($this->primaryModel !== null) {
                // lazy loading of a relation
                //$where = $this->where;

                if ($this->via instanceof self) {
                    // via junction table
                    $viaModels = $this->via->findJunctionRows([$this->primaryModel]);
                    $this->filterByModels($viaModels);
                } elseif (is_array($this->via)) {
                    // via relation
                    /* @var $viaQuery ActiveQuery */
                    list($viaName, $viaQuery) = $this->via;
                    if ($viaQuery->multiple) {
                        $viaModels = $viaQuery->all();
                        $this->primaryModel->populateRelation($viaName, $viaModels);
                    } else {
                        $model = $viaQuery->one();
                        $this->primaryModel->populateRelation($viaName, $model);
                        $viaModels = $model === null ? [] : [$model];
                    }
                    $this->filterByModels($viaModels);
                } else {
                    $this->filterByModels([$this->primaryModel]);
                }
            }

            $this->applyFilterAll();
            $this->applyInClause();

            //Add requests
            foreach ($this->_requests as $i => $findrequest) {
                if (!$findrequest->isEmpty()) {
                    $this->_cmd->add($i, $findrequest);
                } else {
                    unset($this->_requests[$i]);
                }
            }

            //Tranform query to findall query if no find request set (empty CompoundFind are to supported by cwp)
            if (!sizeof($this->_requests) && $this->_cmd instanceof CompoundFind) {
                $this->_cmd = $this->db->newFindAllCommand($this->layout);
                Yii::debug("Query transformed to findAll (no request defined)", 'yii\db\Command::query');
            }

            //Add sort rules
            $precedence = 0;
            foreach ($this->orderBy as $fieldName => $order) {
                $this->_cmd->addSortRule($fieldName, ++$precedence, $order);
            }

            foreach ($this->_scripts as $position => $scriptOptions) {
                if ($position == 'beforeFind') {
                    $this->_cmd->setPreCommandScript($scriptOptions[0], $scriptOptions[1]);
                } elseif ($position == 'beforeSort') {
                    $this->_cmd->setPreSortScript($scriptOptions[0], $scriptOptions[1]);
                } elseif ($position == 'afterFind') {
                    $this->_cmd->setScript($scriptOptions[0], $scriptOptions[1]);
                }
            }
            $this->_cmd->setRelatedSetsFilters($this->relatedSetFilter, $this->relatedSetMax);
        }
        //Apply limits & offset
        $this->offset = $this->offset == -1 ? null : $this->offset;
        $this->limit = $this->limit == -1 ? null : $this->limit;
        $this->_cmd->setRange($this->offset, $this->limit);
        $this->_cmd->setResultLayout($this->resultLayout);
    }

    public function findByScript($scriptName, $scriptParameters)
    {
        $parameters = "";
        foreach ($scriptParameters as $name => $value) {
            $parameters .= "<" . $name . ">" . $value . "</" . $name . ">";
        }
        if ($this->db->getProperty('useDataApi')) {
            $this->_cmd = $this->db->newFindAllCommand($this->resultLayout);
            $this->_cmd->setScript($scriptName, $parameters);
        } else {
            $this->_cmd = $this->db->newPerformScriptCommand($this->resultLayout, $scriptName, $parameters);
        }
    }

    /**
     * Prepare and execute the query
     * @return Result
     * @throws Exception
     * @throws NotSupportedException
     */
    public function execute()
    {
        if ($this->_result === null) {
            $this->prepare();

            //Yii::beginProfile($this->serializeQuery(), 'yii\db\Command::query');

            try {
                $this->_result = $this->_cmd->execute();
                $this->_count = $this->_result->getFoundSetCount();
            } catch (\Exception $e) {
                throw $this->db->getSchema()->convertException($e, $this->serializeQuery());
            }

            //Yii::endProfile($this->serializeQuery(), 'yii\db\Command::query');
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

        if (!empty($this->with)) {
            $this->findWith($this->with, $models);
        }

        if ($this->inverseOf !== null) {
            $this->addInverseRelations($models);
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
     * @throws \airmoi\FileMaker\FileMakerException
     */
    protected function createModels($records)
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
                } elseif (is_callable($this->indexBy)) {
                    $key = call_user_func($this->indexBy, $record);
                }
                $row['_recid'] = $record->getRecordId();
                foreach ($record->getFields() as $field) {
                    $row[$field] = $record->getField($field);
                }

                //Store related sets
                foreach ($record->getLayout()->getRelatedSets() as $relatedSetName => $relatedset) {
                    foreach ($record->getRelatedSet($relatedSetName) as $i => $record) {
                        $row[$relatedSetName][$i] = ['_recid' => $record->getRecordId()];
                        foreach ($record->getFields() as $field) {
                            $row[$relatedSetName][$i][$field] = $record->getField($field);
                        }
                    }
                }
                $models[$key] = $row;
            }
        } else {
            /* @var $model FileMakerActiveRecord */
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
        /* @var $class FileMakerActiveRecord */
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
     * @return array|null|FileMakerActiveRecord a single row of query result. Depending on the setting of [[asArray]],
     * the query result may be either an array or an ActiveRecord object. Null will be returned
     * if the query results in nothing.
     * @throws \Exception
     */
    public function one($db = null)
    {
        if ($this->emulateExecution) {
            return [];
        }
        try {
            $result = $this->execute();
            if ($result->getFetchCount() == 0) {
                return null;
            }
            $rows = $result->getFirstRecord();
        } catch (\Exception $e) {
            if ($e->getCode() == 401) {
                return null;
            }
            throw $e;
        }

        return $this->populate([$rows])[0];
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
        if ($layout === null) {
            $layout = $this->layout;
        }
        $this->_currentRequest = $this->db->newFindRequest($layout);
        $this->_requests = [$this->_currentRequest];
        foreach ($condition as $fieldName => $testvalue) {
            //if(!$testvalue == '' ) {
            $this->_currentRequest->addFindCriterion($fieldName, '==' . $testvalue . '');
            //}
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
        if(is_array($condition) && isset($condition[0]) && strcasecmp($condition[0], 'and') === 0) {
            $condition = $condition[1];
        }
        elseif(is_array($condition) && isset($condition[0]) && strcasecmp($condition[0], 'or') === 0) {
            $this->orWhere($condition);
        }
        foreach ($condition as $fieldName => $testvalue) {
            //if(!$testvalue=='') {
            $this->_currentRequest->addFindCriterion($fieldName, '==' . $testvalue . '');
            //}
        }
        return $this;
    }

    /**
     * Add a WHERE condition that will be applied to all requests that are not omit queries
     * when performing the query
     * @param array $condition the new WHERE condition (name => value)
     * @return static the query object itself
     * @see where()
     * @see orWhere()
     */
    public function filterAll($condition)
    {
        $this->filterAll = [['and', $condition]];
        /*foreach($condition as $fieldName => $testvalue) {
            if(!empty($testvalue)) {
                $this->filterAll[$fieldName] = $testvalue;
            }
        }*/
        return $this;
    }

    /**
     * Add a additionnal WHERE condition that will be applied to all requests that are not omit queries
     * when performing the query
     * @param array $condition the new WHERE condition (name => value)
     * @return static the query object itself
     * @see where()
     * @see orWhere()
     */
    public function andFilterAll($condition)
    {
        $this->filterAll[] = ['and', $condition];
        /*foreach($condition as $fieldName => $testvalue) {
            if(!empty($testvalue)) {
                $this->filterAll[$fieldName] = $testvalue;
            }
        }*/
        return $this;
    }

    /**
     * Add a additionnal WHERE condition that will be applied to all requests that are not omit queries
     * when performing the query
     * @param array $condition the new WHERE condition (name => value)
     * @return static the query object itself
     * @see where()
     * @see orWhere()
     */
    public function orFilterAll($condition)
    {
        $this->filterAll[] = ['or', $condition];
        return $this;
    }

    /**
     * Add a additionnal WHERE condition that will be applied to all requests that are not omit queries
     * when performing the query
     * @param array $condition the new WHERE condition (name => value)
     * @return static the query object itself
     * @see where()
     * @see orWhere()
     */
    public function andIn($condition)
    {
        $this->inClause[] = $condition;
        return $this;
    }

    /**
     * Adds an additional WHERE condition to the existing one.
     * The new condition and the existing one will be joined using the 'AND' operator.
     * @param array $condition the new WHERE condition (name => value)
     * @param null|string $layout
     * @return static the query object itself
     * @see where()
     * @see orWhere()
     */
    public function filterWhere(array $condition, $layout = null)
    {
        if ($layout === null) {
            $layout = $this->layout;
        }
        $this->_currentRequest = $this->db->newFindRequest($layout);
        $this->_requests = [$this->_currentRequest];
        foreach ($condition as $fieldName => $testvalue) {
            if (!(strcmp($testvalue, '') === 0)) {
                if ($testvalue == null) {
                    $testvalue = '=';
                }
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
    public function andFilterWhere(array $condition)
    {
        foreach ($condition as $fieldName => $testvalue) {
            if (!(strcmp($testvalue, '') === 0)) {
                if ($testvalue === null) {
                    $testvalue = '=';
                }
                $this->_currentRequest->addFindCriterion($fieldName, $testvalue);
            }
        }
        return $this;
    }

    /**
     * Adds an additional WHERE condition to the existing one.
     * The new condition and the existing one will be joined using the 'OR' operator.
     * @param string|array $condition the new WHERE condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @param null|string $layout
     * @return static the query object itself
     * @see where()
     * @see andWhere()
     */
    public function orWhere($condition, $layout = null)
    {
        if(is_array($condition) && isset($condition[0]) && strcasecmp($condition[0], 'or') === 0) {
            $condition = $condition[1];
        }
        elseif(is_array($condition) && isset($condition[0]) && strcasecmp($condition[0], 'and') === 0) {
            $this->andWhere($condition);
        }

        if ($layout === null) {
            $layout = $this->layout;
        }
        $this->_currentRequest = $this->db->newFindRequest($layout);
        $this->_requests[] = $this->_currentRequest;
        $this->andWhere($condition);
        return $this;
    }

    /**
     * Adds an additional WHERE condition to the existing one.
     * This will create a new find request that will act as a 'OR' request
     * @param string|array $condition the new WHERE condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @param null|string $layout
     * @return static the query object itself
     * @see where()
     * @see andWhere()
     */
    public function orFilterWhere(array $condition, $layout = null)
    {
        if ($layout === null) {
            $layout = $this->layout;
        }
        $this->_currentRequest = $this->db->newFindRequest($layout);
        $this->_requests[] = $this->_currentRequest;
        $this->andFilterWhere($condition);
        return $this;
    }

    /**
     * Adds a 'ommit' request .
     * @param string|array $condition the new WHERE condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @param null|string $layout
     * @param bool $keepCurrentRequest
     * @return static the query object itself
     * @see where()
     * @see andWhere()
     */
    public function exceptWhere($condition, $layout = null, $keepCurrentRequest = true)
    {
        if ($layout === null) {
            $layout = $this->layout;
        }

        $previousRequest = $this->_currentRequest;
        $this->_currentRequest = $this->db->newFindRequest($layout);
        $this->_currentRequest->setOmit(true);
        $this->_requests[] = $this->_currentRequest;
        $this->andWhere($condition);

        if ($keepCurrentRequest) {
            $this->_currentRequest = $previousRequest;
        }

        return $this;
    }

    /**
     * Adds a 'ommit' request .
     * @param string|array $condition the new WHERE condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @param string $layout the layout name to use with the created request
     * @param bool $keepCurrentRequest wether to keep the current request active
     *
     *
     * @return static the query object itself
     * @see where()
     * @see andWhere()
     */
    public function exceptFilterWhere($condition, $layout = null, $keepCurrentRequest = true)
    {
        if ($layout === null) {
            $layout = $this->layout;
        }

        $previousRequest = $this->_currentRequest;
        $this->_currentRequest = $this->db->newFindRequest($layout);
        $this->_currentRequest->setOmit(true);
        $this->_requests[] = $this->_currentRequest;
        $this->andFilterWhere($condition);

        if ($keepCurrentRequest) {
            $this->_currentRequest = $previousRequest;
        }

        return $this;
    }

    /**
     * Sets a ScriptMaker script to be run before performing the query.
     *
     * @param string $scriptname
     * @param string $scriptParams
     * @return \airmoi\yii2fmconnector\api\ActiveFind
     */
    public function addPreFindScript($scriptname, $scriptParams = null)
    {
        $this->_scripts['beforeFind'] = [$scriptname, $scriptParams];
        return $this;
    }

    /**
     * Sets a ScriptMaker script to be run after performing a the query,
     * but before sorting the result set.
     *
     * @param string $scriptname
     * @param string $scriptParams
     * @return \airmoi\yii2fmconnector\api\ActiveFind
     */
    public function addPreSortScript($scriptname, $scriptParams = null)
    {
        $this->_scripts['beforeSort'] = [$scriptname, $scriptParams];
        return $this;
    }

    /**
     * Sets a ScriptMaker script to be run after the query result set is
     * generated and sorted.
     *
     * @param string $scriptname
     * @param string $scriptParams
     * @return \airmoi\yii2fmconnector\api\ActiveFind
     */
    public function addAfterFindScript($scriptname, $scriptParams = null)
    {
        $this->_scripts['afterFind'] = [$scriptname, $scriptParams];
        return $this;
    }

    /**
     * Add a new request to the query stack and allow direct access to the object
     * @param array $condition @see where()
     * @param string $layout specifi layout to use for the request
     * @return \airmoi\FileMaker\Command\FindRequest
     */
    public function addRequest($condition, $layout = null)
    {
        if ($layout === null) {
            $layout = $this->layout;
        }
        $this->_currentRequest = $this->db->newFindRequest($layout);
        $this->_requests[] = $this->_currentRequest;
        $this->andWhere($condition);
        return $this->_currentRequest;
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
        $result = [];
        if (is_array($columns)) {
            foreach ($columns as $column => $order) {
                if ($order == SORT_ASC) {
                    $result[$column] = FileMaker::SORT_ASCEND;
                } elseif ($order == SORT_DESC) {
                    $result[$column] = FileMaker::SORT_DESCEND;
                } else {
                    $result[$column] = $order;
                }
            }
        } else {
            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);

            foreach ($columns as $column) {
                if (preg_match('/^(.*?)\s+(asc|desc)$/i', $column, $matches)) {
                    $result[$matches[1]] = strcasecmp($matches[2], 'desc') ? FileMaker::SORT_ASCEND : FileMaker::SORT_DESCEND;
                } else {
                    $result[$column] = FileMaker::SORT_ASCEND;
                }
            }
        }

        return $result;
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
     * @throws NotSupportedException
     * @throws Exception
     */
    public function count($q = '*', $db = null)
    {
        if ($this->emulateExecution) {
            return 0;
        }
        if ($this->_count === null) {
            //Perform the query and get the only first record to retrieve the foundcount
            //If $countLayout is defined in the model, will use this layout to get results instead (prevent unnecessary data flow)
            $countQuery = clone $this;
            //Data API does not support limit 0
            $countQuery->limit = $countQuery->db->getProperty('useDataApi') ? 1 : 0;
            //No need to sort for a count
            $countQuery->orderBy = [];
            $class = $this->modelClass;
            if ($class::$countLayout) {
                $countQuery->resultLayout =  $class::$countLayout;
            }

            $countQuery->execute();
            $this->_count = $countQuery->_count;
        }
        return $this->_count;
    }

    /**
     * Returns a value indicating whether the query result contains any row of data.
     * @param Connection $db unused.
     * If this parameter is not given, the `db` application component will be used.
     * @return boolean whether the query result contains any row of data.
     */
    public function exists($db = null)
    {
        if ($this->emulateExecution) {
            return false;
        }
        $this->execute();
        return $this->_count > 0;
    }

    /**
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

    private function serializeQuery()
    {
        return $this->db->getLastRequestedUrl();
        /*$command = ['layout' => $this->layout];
        $command['resultLayout'] = $this->resultLayout;
        $command['method'] = get_class($this->_cmd);
        if ($this->_cmd instanceof CompoundFind) {
            $command['requests'] = [];
            foreach ($this->_requests as $request) {
                $command['requests'][] = $request->findCriteria;
            }
        } elseif ($this->_cmd instanceof Find) {
            $command['requests'] = ["_recId" => $this->_cmd->recordId];
        } elseif ($this->_cmd instanceof PerformScript) {
           //Not implemented yet : missing access to PerformFind Params
        }
        if (sizeof($this->_scripts)) {
            $command['scripts'] = [];
            foreach ($this->_scripts as $position => $scriptOptions) {
                $command['scripts'][$position] = $scriptOptions;
            }
        }
        $command['offset'] = $this->offset;
        $command['limit'] = $this->limit;
        $command['sort'] = $this->orderBy;
        $command['globals'] = $this->_cmd->getGlobals();
        $command['scripts'] = $this->_scripts;

        return json_encode($command);*/
    }

    /**
     *
     * @param int $id
     * @return FileMakerActiveRecord
     * @throws \Exception
     */
    public function getRecordById($id)
    {
        $this->_cmd = $this->db->newFindCommand($this->resultLayout);
        $this->_cmd->setRecordId($id);
        return $record = $this->one();
    }

    /**
     * Sets a filter to restrict the number of related records to return from
     * a portal.
     *
     * The filter limits the number of related records returned by respecting
     * the settings specified in the FileMaker Pro Portal Setup dialog box.
     *
     * @param string $relatedsetsfilter Specify one of these values to
     *        control filtering:
     *        - 'layout': Apply the settings specified in the FileMaker Pro
     *                    Portal Setup dialog box. The records are sorted based
     *                    on the sort  defined in the Portal Setup dialog box,
     *                    with the record set filtered to start with the
     *                    specified "Initial row."
     *        - 'none': Return all related records in the portal without
     *                  filtering or presorting them.
     *
     * @param string $relatedsetsmax If the "Show vertical scroll bar" setting
     *        is enabled in the Portal Setup dialog box, specify one of these
     *        values:
     *        - an integer value: Return this maximum number of related records
     *                            after the initial record.
     *        - 'all': Return all of the related records in the portal.
     *                 If "Show vertical scroll bar" is disabled, the Portal
     *                 Setup dialog box's "Number of rows" setting determines
     *                 the maximum number of related records to return.
     *
     * @return static the query object itself.
     */
    public function setRelatedSetsFilters($relatedsetsfilter, $relatedsetsmax = null)
    {
        $this->relatedSetFilter = $relatedsetsfilter;
        $this->relatedSetMax = $relatedsetsmax;
        return $this;
    }

    /**
     * Set a global field to be define before performing the command.
     *
     * @param string $fieldName the global field name.
     * @param string $fieldValue value to be set.
     *
     * @return static the query object itself.
     */
    public function setGlobal($fieldName, $fieldValue)
    {
        $this->_cmd->setGlobal($fieldName, $fieldValue);
        return $this;
    }

    /**
     * Apply inClause to all requests
     * WARNING, this may produce too many requests for FileMaker CWP
     */
    private function applyInClause()
    {
        if (empty($this->inClause)) {
            return;
        }
        $requests = [];
        foreach ($this->inClause as $condition) {
            $attribute = key($condition);
            $values = array_values($condition);

            foreach ($this->_requests as $request) {
                foreach ($values as $value) {
                    $newRequest = clone $request;
                    $requests[] = $newRequest;
                    $newRequest->addFindCriterion($attribute, '==' . $value);
                }
            }
        }
        $this->_requests = $requests;
    }

    /**
     * Apply filterAll to all requests
     */
    private function applyFilterAll()
    {
        foreach ($this->filterAll as $condition) {
            if ($condition[0] == 'and') {
                foreach ($this->_requests as $request) {
                    if (!$request->omit) {
                        $this->_currentRequest = $request;
                        $this->andFilterWhere($condition[1]);
                    }
                }
            } else {
                $this->applyOrFilterAll($condition[1]);
            }
        }
    }

    /**
     *
     * @param array $condition the WHERE condition (name => value) to apply
     */
    private function applyOrFilterAll($condition)
    {
        $newRequests = [];
        foreach ($this->_requests as $request) {
            $newRequests[] = $request;
            if (!$request->omit) {
                $this->_currentRequest = clone $request;
                $this->andFilterWhere($condition);
                $newRequests[] = $this->_currentRequest;
            }
        }
        $this->_requests = $newRequests;
    }

    /**
     * Sets whether to emulate query execution, preventing any interaction with data storage.
     * After this mode is enabled, methods, returning query results like [[one()]], [[all()]], [[exists()]]
     * and so on, will return empty or false values.
     * You should use this method in case your program logic indicates query should not return any results, like
     * in case you set false where condition like `0=1`.
     * @param boolean $value whether to prevent query execution.
     * @return $this the query object itself.
     * @since 2.0.11
     */
    public function emulateExecution($value = true)
    {
        $this->emulateExecution = $value;
        return $this;
    }

    /**
     * @return int
     * @throws Exception
     */
    public function getTotalCount()
    {
        if (!$this->_result) {
            throw new Exception('You must perform a query first to get total count');
        }
        return $this->_result->getFoundSetCount();
    }
}

