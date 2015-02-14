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
    protected function resolveTableNames($table, $name)
    {
        /*
        * get first available TO name
        */
       $sql="SELECT TableName FROM FileMaker_Tables WHERE BaseTableName='$name' or TableName='$name'";
       $tablename = $this->db->createCommand($sql)->queryColumn();
            $table->name = $name;
            $table->fullName = $tablename[0];
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
        $column->isPrimaryKey = substr($column->name, 0, 3)=="zkp"; 
        //$column->autoIncrement = $info['is_identity'] == 1;
        $column->unsigned = false;
        $column->comment = "";
        
        if(preg_match('/varchar\((\d+)\)/', $column->dbType, $matches)){
            $column->type = $this->typeMap['varchar'];
            $column->size = $matches[1];
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
    protected function findColumns($table)
    {
        /*
        * Ignore Global and summary fields
        */
        $sql="SELECT * FROM FileMaker_Fields WHERE TableName = '".$table->fullName."' "
                . "AND FieldType NOT LIKE 'global%' "
                . "AND FieldClass NOT LIKE 'Summary' " 
                . "AND FieldName NOT LIKE 'zkk_%' "
                . "AND FieldName NOT LIKE 'zgi_%' "
                . "AND FieldName NOT LIKE 'zzz_%' "
                . "AND FieldName NOT LIKE 'z_foundCount_cU' "
                . "AND FieldName NOT LIKE 'z_listOf_eval_cU'";

        try {
            $columns = $this->db->createCommand($sql)->queryAll();
            if (empty($columns)) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
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
        $sql="SELECT (BaseTableName) FROM FileMaker_Tables";
        $tempResult = $this->db->createCommand($sql)->queryColumn();
        $result = array_unique($tempResult);
        return $result;
    }
    
    /**
     * Collects the foreign key column details for the given table.
     * @param TableSchema $table the table metadata
     */
    protected function findConstraints($table)
    {
        foreach ( $table->columns as $c) {
            if ( substr($c->name, 0, 3)=="zkf" || substr($c->name, 0, 4)=="zkp_") { 
                $XXX = $this->getTableNameFromXXX(preg_replace('/(zkf|zkp)_([^_]*).*/', "$2", $c->name));
                if ( sizeof ($XXX) )
                    $table->foreignKeys[] = [$XXX[0], "zkp" => $c->name];
            }
        }
    }
    
     protected function getTableNameFromXXX($XXX) {
         $sql="SELECT DISTINCT(BaseTableName) FROM FileMaker_Tables WHERE BaseTableName LIKE '$XXX\_%'";
         return $this->db->createCommand($sql)->queryColumn();
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
    
}
