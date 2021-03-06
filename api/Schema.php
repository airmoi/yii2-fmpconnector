<?php
/**
 * @link https://github.com/airmoi/yii2-fmpconnector
 * @copyright Copyright (c) 2014 Romain Dunand
 * @license  MIT
 */

namespace airmoi\yii2fmconnector\api;

use airmoi\FileMaker\FileMakerException;
use yii;
use airmoi\FileMaker\FileMaker;
use airmoi\FileMaker\Object\Field;
use airmoi\FileMaker\Object\Layout;
use yii\helpers\ArrayHelper;

/**
 * Schema is the class for retrieving metadata from a FileMaker ODBC databases (version 13 and above).
 *
 * @author Romain Dunand <airmoi@gmail.com>
 * @since 1.0
 *
 * @method TableSchema getTableSchema($name, $refresh = false) Obtains the metadata for the named table.
 *
 * @property Connection $db Description
 */
class Schema extends \yii\db\Schema
{

    public $defaultPrimaryKey = 'zkp';
    public $primaryKeyPattern = '/^zkp[_]?/';

    /**
     * Pattern used to detect if a field is a foreign keys field
     * second match pattern must return a table trigram (XXX)
     *
     * @var string
     */
    public $foreignKeyPattern = '/^(zkf|zkp)_([^_]*).*/';

    /**
     * Pattern used to detect if a field is a foreign keys field
     * second match pattern must return a table trigram (XXX)
     *
     * @var string
     */
    public $layoutFilterPattern = '/^PHP_.*/';

    /**
     * @var array mapping from physical column types (keys) to abstract column types (values)
     */
    public $typeMap = [
        'number' => self::TYPE_DECIMAL,
        'text' => self::TYPE_STRING,
        'date' => self::TYPE_DATE,
        'time' => self::TYPE_TIME,
        'timestamp' => self::TYPE_DATETIME,
        'container' => self::TYPE_BINARY,

    ];

    /**
     *
     * @var \airmoi\FileMaker\Object\Layout[]
     */
    private $_layout = [];

    /**
     *
     * @var string[]
     */
    private $_layoutList = [];

    /**
     * Loads the metadata for the specified table.
     * @param string $name table name
     * @return TableSchema|null driver dependent table metadata. Null if the table does not exist.
     * @throws \Exception
     * @throws yii\base\InvalidConfigException
     */
    public function loadTableSchema($name)
    {
        $table = new TableSchema();
        $this->resolveTableNames($table, $name);
        $this->findLayoutsFromSameTable($table);
        if ($this->findColumns($table)) {
            $this->findConstraints($table);
            $this->findValueLists($table);
            return $table;
        } else {
            return null;
        }
    }

    /**
     *
     * @param string $layoutName
     * @return Layout
     * @throws \Exception
     */
    public function getlayout($layoutName)
    {
        if (!isset($this->_layout[$layoutName])) {
            $token = 'Getting layout "' . $layoutName . '"';
            try {
                Yii::info($token, __METHOD__);
                Yii::beginProfile($token, __METHOD__);

                $this->_layout[$layoutName] = $this->db->getLayout($layoutName);
                $this->_layout[$layoutName]->loadExtendedInfo();

                Yii::info($this->db->getLastRequestedUrl(), __METHOD__);
            } catch (FileMakerException $e) {
                Yii::endProfile($token, __METHOD__);
                throw new \Exception($e->getMessage(), (int)$e->getCode(), $e);
            }
        }
        return $this->_layout[$layoutName];
    }

    /**
     * Resolves the table name and schema name (if any).
     * @param TableSchema $table the table metadata object
     * @param string $name the table name
     * @return string
     * @throws \Exception
     */
    protected function resolveTableNames(TableSchema $table, $name)
    {
        //Check if Layout exists
        $table->baseTable = $this->getlayout($name)->table;
        $table->layouts[] = $table->name = $table->fullName = $table->defaultLayout = $name;

        return $name;
    }

    /**
     * @param TableSchema $table
     * @throws \Exception
     */
    public function findLayoutsFromSameTable(TableSchema $table)
    {
        $layouts = $this->findTableNames();
        foreach ($layouts as $layoutName) {
            if ($layoutName == $table->name || $layoutName == '') {
                continue;
            }
            if ($this->getlayout($layoutName)->table == $table->baseTable) {
                $table->layouts[] = $layoutName;
            }
        }
    }

    /**
     * Loads the column information into a [[ColumnSchema]] object.
     * @param Field $field a FileMaker Field Object
     * @return ColumnSchema the column schema object
     * @throws yii\base\InvalidConfigException
     */
    protected function loadColumnSchema(Field $field)
    {
        $column = $this->createColumnSchema();

        /**
         * @todo gerer les multivaluées
         */
        $column->isRelated = strpos($field->name, '::') !== false;
        //Remove OT prefix for fields from related sets
        if ($column->isRelated) {
            $fieldParts = explode('::', $field->name);
            $column->relationName = $fieldParts[0];
            $column->name = $fieldParts[1];
        } else {
            $column->name = $field->name;
        }


        $column->allowNull = !$field->hasValidationRule(FileMaker::RULE_NOTEMPTY);
        $column->dbType = $field->result;
        //$column->isPrimaryKey = substr($column->name, 0, 3)=="zkp";
        $column->isPrimaryKey = preg_match($this->primaryKeyPattern, $column->name);

        $column->fmType = $field->type;
        $column->global = $field->isGlobal();
        $column->valueList = $field->valueList;
        $column->maxRepeat = $field->maxRepeat;

        /**
         * Dirty hack to prevent field edition on calculated / conatiners fields (will be ignored in generated rules)
         */
        $column->autoIncrement = ($field->type == 'calculation' or $column->isPrimaryKey);

        $column->unsigned = false;
        $column->comment = "";

        $column->type = isset($this->typeMap[$field->result]) ? $this->typeMap[$field->result] : self::TYPE_STRING;
        $column->size = $field->maxCharacters;

        $column->phpType = $this->getColumnPhpType($field->result);

        return $column;
    }

    /**
     * Collects the metadata of table columns.
     * @param TableSchema $table the table metadata
     * @return boolean whether the table exists in the database
     * @throws \Exception
     * @throws yii\base\InvalidConfigException
     */
    protected function findColumns(TableSchema $table)
    {
        $fields = [];
        foreach ($table->layouts as $layoutName) {
            $fields = ArrayHelper::merge($this->getLayout($layoutName)->getFields(), $fields);
        }

        if (sizeof($fields) == 0) {
            return false;
        }

        $table->columns['_recid'] = $this->createColumnSchema();
        $table->columns['_recid']->name = '_recid';
        $table->columns['_recid']->allowNull = false;
        $table->columns['_recid']->isPrimaryKey = true;
        $table->columns['_recid']->autoIncrement = true;
        $table->columns['_recid']->phpType = 'integer';
        $table->primaryKey[] = '_recid';

        foreach ($fields as $field) {
            if ($field->name == '') {
                continue;
            }
            $column = $this->loadColumnSchema($field);

            //handle related Fields outside portals
            if ($column->isRelated) {
                if (!isset($table->relations[$column->relationName])) {
                    $tableSchema = new TableSchema();
                    $tableSchema->name = $tableSchema->fullName = $column->relationName;
                    $tableSchema->defaultLayout = $table->defaultLayout;
                    $table->relations[$column->relationName] = $tableSchema;
                } else {
                    $tableSchema = $table->relations[$column->relationName];
                }

                if (!isset($tableSchema->columns[$column->name])) {
                    $tableSchema->columns[$column->name] = $column;
                }
                //$table->relations[$column->relationName][1][$column->name] = $column;
            } elseif (!isset($table->columns[$column->name])) {
                $table->columns[$column->name] = $column;
            }
        }

        asort($table->columns);
        return true;
    }

    /**
     * Returns all table names in the database.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     * @return array all table names in the database. The names have NO schema name prefix.
     * @throws \Exception
     */
    protected function findTableNames($schema = '')
    {
        $token = 'Getting layout\'s list';
        if (sizeof($this->_layoutList) > 0) {
            return $this->_layoutList;
        }
        try {
            Yii::info($token, __METHOD__);
            Yii::beginProfile($token, __METHOD__);

            $filter = $this->layoutFilterPattern;
            $layouts = array_filter($this->db->listLayouts(), function ($value) use ($filter) {
                return preg_match($filter, $value);
            });
            $this->_layoutList = array_values($layouts);
            Yii::info($this->db->getLastRequestedUrl(), __METHOD__);
        } catch (FileMakerException $e) {
            Yii::endProfile($token, __METHOD__);
            throw new \Exception($e->getMessage(), (int)$e->getCode(), $e);
        }
        return $this->_layoutList;
    }

    /**
     * Collects the value lists names for the given layout.
     * @param TableSchema $table
     * @return array all table names in the database. The names have NO schema name prefix.
     * @throws \Exception
     */
    protected function findValueLists(TableSchema $table)
    {
        $table->valueLists = $this->getLayout($table->name)->listValueLists();
        return $table->valueLists;
    }

    /**
     * Collects the foreign key column details for the given table.
     * @param TableSchema $table the table metadata
     * @throws \Exception
     * @throws yii\base\InvalidConfigException
     */
    protected function findConstraints(TableSchema $table)
    {
        foreach ($table->layouts as $layoutName) {
            foreach ($this->getLayout($layoutName)->getRelatedSets() as $relation) {
                // Check if portal of the same related table was already declared
                $relationName = $relation->name . '_portal';
                if (isset($table->relations[$relationName])) {
                    $tableSchema = $table->relations[$relationName];
                } else {
                    $tableSchema = new TableSchema();
                    $tableSchema->name = $relation->name;
                    $tableSchema->fullName = $relationName;
                    $tableSchema->isPortal = true;
                    $tableSchema->defaultLayout = $layoutName;
                    $tableSchema->baseTable = $relation->name;

                    //Store _recid PK as field
                    $pk = $this->createColumnSchema();
                    $pk->name = '_recid';
                    $pk->allowNull = false;
                    $pk->isPrimaryKey = true;
                    $pk->autoIncrement = true;
                    $pk->phpType = 'integer';

                    $tableSchema->columns['_recid'] = $pk;
                }


                $tableSchema->layouts[] = $layoutName;

                foreach ($relation->getFields() as $field) {
                    $column = $this->loadColumnSchema($field);
                    //handle related Fields from different OT
                    if ($column->isRelated && $column->relationName != $tableSchema->name) {
                        if (!isset($table->relations[$column->relationName])) {
                            $relatedSchema = new TableSchema();
                            $relatedSchema->name = $relatedSchema->fullName = $column->relationName;
                            $relatedSchema->defaultLayout = $table->defaultLayout;
                            $table->relations[$column->relationName] = $relatedSchema;
                        } else {
                            $relatedSchema = $table->relations[$column->relationName];
                        }

                        $tableSchema->relations[$column->relationName] = $relatedSchema;
                        if (!isset($relatedSchema->columns[$column->name])) {
                            $relatedSchema->columns[$column->name] = $column;
                        }
                        //$table->relations[$column->relationName][1][$column->name] = $column;
                    } elseif (!isset($tableSchema->columns[$column->name])) {
                        $tableSchema->columns[$column->name] = $column;
                    }
                }

                $table->relations[$relationName] = $tableSchema;
            }
        }
    }

    /**
     * Extracts the PHP type from abstract DB type.
     * @param string $type the column schema information
     * @return string PHP type name
     */
    protected function getColumnPhpType($type)
    {
        static $typeMap = [
            // abstract type => php type
            'text' => 'string',
            'number' => 'integer',
            'container' => 'resource',
            'date' => 'date',
            'time' => 'time',
            'timestamp' => 'timestamp',
        ];
        if (isset($typeMap[$type])) {
            return $typeMap[$type];
        } else {
            return 'string';
        }
    }

    /**
     * @return ColumnSchema
     * @throws \yii\base\InvalidConfigException
     */
    protected function createColumnSchema()
    {
        return Yii::createObject('airmoi\yii2fmconnector\api\ColumnSchema');
    }

    /**
     * Returns the ID of the last inserted row or sequence value.
     * @param string $sequenceName name of the sequence object (required by some DBMS)
     * @return string the row ID of the last row inserted, or the last value retrieved from the sequence object
     * @throws yii\base\InvalidCallException if the DB connection is not active
     * @see http://www.php.net/manual/en/function.PDO-lastInsertId.php
     */
    public function getLastInsertID($sequenceName = '')
    {
        throw new yii\base\InvalidCallException('getLastInsertID is not supported by FileMaker PHP-API.');
    }

    /**
     * Executes the INSERT command, returning primary key values.
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column data (name => value) to be inserted into the table.
     * @return integer The FileMaker (internal) Record Id or false if the command fails
     * @throws FileMakerException
     * @throws \airmoi\FileMaker\FileMakerValidationException
     * @since 2.0.4
     */
    public function insert($table, $columns)
    {
        $command = $this->db->newAddCommand($table, $columns);

        if (!$result = $command->execute()) {
            return false;
        }

        $record = $result->getFirstRecord();

        $result = [];
        /* ($tableSchema->primaryKey as $name) {
            $result[$name] = $record->getRecordId();
        }*/
        return $record->getRecordId();

        /*$command = $this->db->createCommand()->insert($table, $columns);
        $tableSchema = $this->getTableSchema($table);
        $result = [];
        foreach ($tableSchema->primaryKey as $name) {
                $result[$name] = $this->getLastInsertID($table);
        }
        return $result;*/
    }
}
