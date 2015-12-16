<?php
/**
 * @link https://github.com/airmoi/yii2-fmpconnector
 * @copyright Copyright (c) 2014 Romain Dunand
 * @license  MIT
 */

namespace airmoi\yii2fmconnector\api;
use yii;
use airmoi\yii2fmconnector\api\ColumnSchema;
use airmoi\FileMaker\FileMaker;
use airmoi\FileMaker\Object\Field;
use airmoi\FileMaker\Object\Layout;

/**
 * Schema is the class for retrieving metadata from a FileMaker ODBC databases (version 13 and above).
 *
 * @author Romain Dunand <airmoi@gmail.com>
 * @since 1.0
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
     * @var \airmoi\FileMaker\Object\Layout
     */
    private $_layout;

    /**
     * Loads the metadata for the specified table.
     * @param string $name table name
     * @return TableSchema|null driver dependent table metadata. Null if the table does not exist.
     */
    public function loadTableSchema($name)
    {
        $table = new TableSchema();
        $this->resolveTableNames($table, $name);
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
     */
    public function getlayout($layoutName) {  
        if($this->_layout->getName() != $layoutName){
            $this->_layout = $this->db->getLayout($layoutName);
        }
        return $this->_layout;
    }

    /**
     * Resolves the table name and schema name (if any).
     * @param TableSchema $table the table metadata object
     * @param string $name the table name
     */
    protected function resolveTableNames( TableSchema $table, $name)
    {
        //Check if Layout exists
        $this->_layout = $this->db->getLayout($name);
        
        $table->name = $name;
        $table->fullName = $name;
        
        return $name;

    }

    /**
     * Loads the column information into a [[ColumnSchema]] object.
     * @param Field $field a FileMaker Field Object
     * @return ColumnSchema the column schema object
     */
    protected function loadColumnSchema(Field $field)
    {
        $column = $this->createColumnSchema();

        /**
         * @todo gerer les multivaluées
         */
        $column->isRelated = strpos($field->name, '::') !== false ;
        //Remove OT prefix for fields from related sets
        if ( $column->isRelated ) {
            $fieldParts = explode('::', $field->name);
            $column->relationName = $fieldParts[0];
            $column->name = $fieldParts[1];
        } 
        else {
            $column->name = $field->name;
        }
        
        
        $column->allowNull = !$field->hasValidationRule(FileMaker::RULE_NOTEMPTY);
        $column->dbType = $field->result;
        //$column->isPrimaryKey = substr($column->name, 0, 3)=="zkp"; 
        $column->isPrimaryKey = preg_match($this->primaryKeyPattern, $column->name);
        
        $column->fmType = $field->type;
        $column->global = $field->isGlobal();
        
        /**
         * Dirty hack to prevent field edition on calculated / conatiners fields (will be ignored in generated rules)
         */
        $column->autoIncrement = $field->isAutoEntered();
        
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
     */
    protected function findColumns(TableSchema $table)
    {
        
        $fields = $this->getLayout($table->name)->getFields();
        
        if ( sizeof( $fields ) == 0)
                return false;
        
        foreach ($fields as $field) {
            $column = $this->loadColumnSchema($field);
            if($column->isRelated){
                $table->relations[$column->relationName] = [0 , [$column]];
            }
            else {
                $table->columns[$column->name] = $column;
            }
            /**if ( $column->isPrimaryKey ) {
                $table->primaryKey[] = $column->name;
            }*/
        }
        $table->columns['_recid'] = $this->createColumnSchema();
        $table->columns['_recid']->name = '_recid';
        $table->columns['_recid']->allowNull = false;
        $table->columns['_recid']->isPrimaryKey = true;
        $table->columns['_recid']->autoIncrement = true;
        $table->columns['_recid']->phpType = 'integer';
        $table->primaryKey[] = '_recid';
        return true;
    }

    /**
     * Returns all table names in the database.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     * @return array all table names in the database. The names have NO schema name prefix.
     */
    protected function findTableNames($schema = '')
    {
        return $this->db->listLayouts();
    }

    /**
     * Collects the value lists names for the given layout.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     * @return array all table names in the database. The names have NO schema name prefix.
     */
    protected function findValueLists(TableSchema $table)
    {
        $table->valueLists = $this->getLayout($table->name)->listValueLists();
        return $table->valueLists;
    }
    
    /**
     * Collects the foreign key column details for the given table.
     * @param TableSchema $table the table metadata
     */
    protected function findConstraints($table)
    {  
        foreach( $this->getLayout($table->name)->getRelatedSets() as $relation){
            $relationName = $relation->name;
            $relationColumns = [];
            foreach( $relation->getFields() as $field ){
                $column = $this->loadColumnSchema($field);
                $relationColumns[$column->name] = $column;
            }
            $table->relations[$relationName] = [1, $relationColumns] ;
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
     * @throws InvalidCallException if the DB connection is not active
     * @see http://www.php.net/manual/en/function.PDO-lastInsertId.php
     */
    public function getLastInsertID($sequenceName = '')
    {
        throw new InvalidCallException('getLastInsertID is not supported by FileMaker PHP-API.');
    }
    
    /**
     * Executes the INSERT command, returning primary key values.
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column data (name => value) to be inserted into the table.
     * @return integer The FileMaker (internal) Record Id or false if the command fails
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
        foreach ($tableSchema->primaryKey as $name) {  
                $result[$name] = $record->getRecordId();
        }
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