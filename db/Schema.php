<?php
/**
 * @link https://github.com/airmoi/yii2-fmpconnector
 * @copyright Copyright (c) 2014 Romain Dunand
 * @license  MIT
 */

namespace airmoi\yii2fmconnector\db;

use airmoi\yii2fmconnector\db\ColumnSchema;
use yii;

/**
 * Schema is the class for retrieving metadata from a FileMaker ODBC databases (version 13 and above).
 *
 * @author Romain Dunand <airmoi@gmail.com>
 * @since 1.0
 */
class Schema extends \yii\db\Schema
{
    public $ignoreFields = [];
    
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
        'decimal' => self::TYPE_DECIMAL,
        'varchar' => self::TYPE_STRING,
        'date' => self::TYPE_DATE,
        'time' => self::TYPE_TIME,
        'timestamp' => self::TYPE_DATETIME,
        'binary' => self::TYPE_BINARY,

    ];
    
    private $_tables = [];
    
    /**
     * Store BaseTableName for each table occurrence ton improve parsing speed
     * @var array 
     */
    private $_tableMap = [];
    
    
     /**
     * Quotes a string value for use in a query.
     * Note that if the parameter is not a string, it will be returned without change.
     * @param string $str string to be quoted
     * @return string the properly quoted string
     * @see http://www.php.net/manual/en/function.PDO-quote.php
     */
    public function quoteValue($str)
    {
        /*if ( $str instanceof \DateTime) {
            return  "'{d " .$str->format('m-d-Y')."}'";
        }*/
        if (!is_string($str)) {
            return $str;
        }
       
        if ( preg_match('/^\d{4}-\d{2}-\d{2}$/', $str))
            return "{d '$str'}";
        if ( preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $str))
            return "{ts '$str'}";
        if ( preg_match('/^\d{2}:\d{2}:\d{2}$/', $str))
            return "{t '$str'}";
         if ( preg_match('/^••varchar••(.*)/', $str, $match))
                $str = $match[1];
        if ( preg_match('/^••decimal••(.*)/', $str, $match))
                return $match[1];
        
        // the driver doesn't support quote (e.g. oci)
        return "'" . addslashes(str_replace("'", "\'", $str)) . "'";
 
    }

    /**
     * Quotes a table name for use in a query.
     * A simple table name has no schema prefix.
     * @param string $name table name.
     * @return string the properly quoted table name.
     */
    public function quoteSimpleTableName($name)
    {
        return '"'.$name.'"';
    }

    /**
     * Quotes a column name for use in a query.
     * A simple column name has no prefix.
     * @param string $name column name.
     * @return string the properly quoted column name.
     */
    public function quoteSimpleColumnName($name)
    {
        if ( $name === '*' )
            return $name;
        return '"'.$name.'"';
    }

    /**
     * Creates a query builder for FileMaker ODBC database.
     * @return QueryBuilder query builder interface.
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder($this->db);
    }

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
            return $table;
        } else {
            return null;
        }
    }

    /**
     * Resolves the table name and schema name (if any).
     * @param TableSchema $table the table metadata object
     * @param string $name the table name
     */
    protected function resolveTableNames( TableSchema $table, $name)
    {
        /*
        * get first available TO name
        */
        if ( sizeof( $this->_tables ) == 0 )
             $this->parseSchema();
        
        foreach ( $this->_tables as $tableName => $infos){
            if ( $tableName == $name or array_search($name, $infos['tables']) !== false){
                $table->name = $name;
                $table->fullName = $infos['tables'][0];
                $table->baseTableName = $tableName;
                return $infos['tables'][0];
            }
        }
        return [];
    }

    /**
     * Loads the column information into a [[ColumnSchema]] object.
     * @param array $info column information
     * @return ColumnSchema the column schema object
     */
    protected function loadColumnSchema($info)
    {
        $column = $this->createColumnSchema();

        $column->name = $info['FieldName'];
        $column->allowNull = true;
        $column->dbType = $info['FieldType'];
        //$column->isPrimaryKey = substr($column->name, 0, 3)=="zkp"; 
        $column->isPrimaryKey = preg_match($this->primaryKeyPattern, $column->name); 
        //$column->autoIncrement = $info['is_identity'] == 1;
        $column->unsigned = false;
        $column->comment = "";
        
        if(preg_match('/varchar\((\d+)\)/', $column->dbType, $matches)){
            $column->type = $this->typeMap['varchar'];
            $column->size = $matches[1];
        }
        /* handle multivalued field (ignore multivalues) */
        elseif(preg_match('/([^\[]*)\[(\d+)\]/', $column->dbType, $matches)){
             $column->type = $this->typeMap[$matches[1]];
        }
        else {
        $column->type = $this->typeMap[$column->dbType];
        }
        $column->phpType = $this->getColumnPhpType($column);

        return $column;
    }

    /**
     * Collects the metadata of table columns.
     * @param TableSchema $table the table metadata
     * @return boolean whether the table exists in the database
     */
    protected function findColumns(TableSchema $table)
    {
        /*
        * Ignore Global and summary fields
        */
        
        $columns = $this->_tables[$table->baseTableName]['fields'];
        if ( sizeof( $columns ) == 0)
                return false;
        foreach ($columns as $column) {
            $column = $this->loadColumnSchema($column);
            $table->columns[$column->name] = $column;
            if ( $column->isPrimaryKey ) {
                $table->primaryKey[] = $column->name;
            }
        }
        return true;
    }

    /**
     * Returns all table names in the database.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     * @return array all table names in the database. The names have NO schema name prefix.
     */
    protected function findTableNames($schema = '')
    {
        if( sizeof( $this->_tables ) == 0) {
            $this->parseSchema();
        }
        return array_keys($this->_tables);
    }
    
    /**
     * Collects the foreign key column details for the given table.
     * @param TableSchema $table the table metadata
     */
    protected function findConstraints($table)
    {
        foreach ( $table->columns as $c) {
            //if ( substr($c->name, 0, 3)=="zkf" || substr($c->name, 0, 4)=="zkp_") { 
            if ( preg_match($this->foreignKeyPattern, $c->name, $matches)) { 
                $XXX = $this->getTableNameFromXXX($matches[2]);
                if ( sizeof ($XXX) )
                    $table->foreignKeys[] = [$XXX[0],   $c->name => $this->defaultPrimaryKey];
            }
        }
    }
    
     protected function getTableNameFromXXX($XXX) {
         if ( sizeof( $this->_tables ) == 0 )
             $this->parseSchema();
         
         foreach ( $this->_tables as $tableName => $infos ){
             if ( preg_match('/^'.$XXX.'_/', $tableName))
                 return [$infos['tables'][0]];
         }
         return [];
     }
     
     /**
     * Extracts the PHP type from abstract DB type.
     * @param ColumnSchema $column the column schema information
     * @return string PHP type name
     */
    protected function getColumnPhpType($column)
    {
        static $typeMap = [
            // abstract type => php type
            'smallint' => 'integer',
            'decimal' => 'integer',
            'integer' => 'integer',
            'bigint' => 'integer',
            'boolean' => 'boolean',
            'float' => 'double',
            'binary' => 'resource',
            'date' => 'date',
            'time' => 'time',
            'timestamp' => 'timestamp',
        ];
        if (isset($typeMap[$column->type])) {
            if ($column->type === 'bigint') {
                return PHP_INT_SIZE == 8 && !$column->unsigned ? 'integer' : 'string';
            } elseif ($column->type === 'integer') {
                return PHP_INT_SIZE == 4 && $column->unsigned ? 'string' : 'integer';
            } else {
                return $typeMap[$column->type];
            }
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
        return Yii::createObject('airmoi\yii2fmconnector\db\ColumnSchema');
    }
    
    /**
     * @return boolean TRUE on success
     * @throws \yii\base\InvalidConfigException
     */
    protected function parseSchema()
    {
        \Yii::trace('Caching DB schema', __METHOD__);
        $this->_tables = [];
        
        /* Store Tables */
        $sql="SELECT BaseTableName, TableName FROM FileMaker_Tables WHERE BaseTableName IS NOT NULL";
        $tables = $this->db->createCommand($sql)->cache($this->db->schemaCacheDuration)->queryAll();
        foreach ( $tables as $table ) {
            $this->_tableMap[$table['TableName']] = $table['BaseTableName'];
            if ( !isset( $this->_tables [$table['BaseTableName']]))
                $this->_tables [$table['BaseTableName']] = ['tables'=>[], 'fields'=>[]];
            $this->_tables [$table['BaseTableName']]['tables'][] = $table['TableName'];
            //asort($this->_tables [$table['BaseTableName']]['tables']);
        }
        $TOs = [];
        foreach ( $this->_tables as $baseTableName => $infos ){
            asort($this->_tables [$baseTableName]['tables']);
            $TOs[] = $this->_tables [$baseTableName]['tables'][0];
        }
        
        /* Store Fields */
        $sql="SELECT * FROM FileMaker_Fields";
        $conditions = [];
        foreach ( $this->ignoreFields as $type => $patterns ){
            foreach ( $patterns as $pattern ) {
                $conditions[] = "$type NOT LIKE '$pattern' ";
            }
        };
        
        if ( sizeof($conditions)>0)
            $sql .= ' WHERE '.implode (' AND ', $conditions );
        
        /* Limit to each Table's main Occurrences */
        $sql .= " AND TableName IN('".implode("', '", $TOs)."')";
        
        try {
            $columns = $this->db->createCommand($sql)->cache($this->db->schemaCacheDuration)->queryAll();
            if (empty($columns)) {
            \Yii::error('Schema cache fail : No columns found', __METHOD__ );
                return false;
            }
        } catch (\Exception $e) {
            \Yii::error('Schema cache fail : ' . $e->getMessage(), __METHOD__ );
            return false;
        }
        foreach ($columns as $column) {
            /* Find BaseTable Name */
            /*foreach ( $this->_tables as $baseTableName => $infos )
            {
                if ( array_search($column['TableName'], $infos['tables']) !== false){
                    break; 
                }
            }*/
            $baseTableName = $this->_tableMap[$column['TableName']];
            if( array_key_exists($column['FieldName'],  $this->_tables[$baseTableName]['fields']))
                continue;
            
            $this->_tables[$baseTableName]['fields'][$column['FieldName']] = $column;
        }
        
        \Yii::trace('Db schema cache done', __METHOD__ );
        return true;
    }
    
}
