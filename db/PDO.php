<?php
/**
 * @link https://github.com/airmoi/yii2-fmpconnector
 * @copyright Copyright (c) 2014 Romain Dunand
 * @license  MIT
 */

namespace airmoi\yii2fmconnector\db;
/**
 * This is an emulation of the default PDO class of ODBC for FileMaker.
 * It provides workarounds with buggy functionalities of PDO ODBC driver caused by FileMaker ODBC driver.
 *
 * @author Romain Dunand <airmoi@gmail.com>
 * @since 1.0
 */
class PDO extends \PDO
{
    private $_db;
    private $_query;
    /**
     * 
     */
    public function __construct ( $dsn , $username = "" ,  $password = "",  $options = array()) {
        
        /* remove driverName if predent on connection string) */
        if(($pos=strpos($dsn, ':'))!==false)
            $dsn =  strtolower(substr($dsn, $pos+1, strlen($dsn)-$pos));
        
        if ( !$this->_db = @odbc_connect($dsn, utf8_decode($username), $password))
            $this->throwErrors();
        
        //odbc_longreadlen($this->_db, 1000000) ;//1024*1024*30);
    }
    
    /**
     * 
     * @return bool
     */
    public function  beginTransaction ( ){
        if (!odbc_autocommit($this->_db, FALSE))
            $this->throwErrors();
        return true;
    }
    
    /**
     * 
     * @return bool
     */
    public function commit () {
        if (!odbc_commit($this->_db))
            $this->throwErrors();
        return true;
    }
   /**
    * 
    * @return string
    */
    public function errorCode ( ) {
        return odbc_error($this->_db);
    }
    /**
     * 
     * @return array
     */
    public function  errorInfo (  ){
        return array(odbc_error($this->_db), odbc_error($this->_db), odbc_errormsg($this->_db));
    }
    /**
     * 
     * @param string $statement the SQL query
     * @return int number of affected rows
     */
    public function  exec (  $statement ) {
        
        if ( ! $res = odbc_exec($this->_db, $statement))
                $this->throwErrors ();
        
         return odbc_num_rows($this->_db);
    }
    
    /**
     * NOT Supported
     * @param type $attribute
     * @return type
     */
    public function  getAttribute (  $attribute ) {
        return null;
    }
    /**
     * 
     * @return bool
     */
    public function  inTransaction ( ) {
        return !odbc_autocommit($this->_db);
    }
    
    /**
     * NOT Supported
     */
    public function  lastInsertId ($name= null ) {
       return null ;
    }
    
    /**
     * 
     * @param string $statement
     * @param array $driver_options 
     * @return pdoODBCStatement
     */
    public function prepare (  $statement , $driver_options = array() ) {
        $this->_query = utf8_decode($statement);
        if (!$stmt = @odbc_prepare($this->_db, $this->_query))
            $this->throwErrors();
       return new PDOStatement($stmt, $this->_db, $this->_query); 
    }
    
    public function  query (  $statement ) {
        if (!$stmt = @odbc_exec($this->_db, $statement))
             $this->throwErrors();
       return new PDOStatement($stmt, $this->_db, $statement); 
    }
    
    /**
     * @return bool
     */
    public function   rollBack (  ) {
        if (!$result = odbc_rollback($this->_db))
            $this->throwErrors();
       return new pdoODBCStatement($stmt, $this->_db); 
    }
    /**
     * @return bool
     */
    public function  setAttribute (  $attribute ,  $value ) {
        return false;
    }
            
            
    private function throwErrors() {
        if ( !$this->_db)
            throw new \yii\db\Exception(odbc_errormsg(). ' ('. odbc_error(). ')');
        if ( odbc_errormsg($this->_db))
            throw new \yii\db\Exception(odbc_errormsg($this->_db). ' ('. odbc_error($this->_db). ')');
    }
}

class PDOStatement extends \PDOStatement {
    
    private $_statement;
    private $_db;
    private $_fetchMode = PDO::FETCH_ASSOC;
    private $_query;
    private $_lastExec;
    private $_lastError;
    private $_lastErrorMessage;
    private $_rowCount = 0;
    /* Méthodes */

    /**
     * 
     * @param ressource $Result_id result of odbc_prepare();
     */
    public function __construct($Result_id, $db, $query=null) {
        $this->_statement = $Result_id;
        $this->_db = $db;
        $this->_query = $query;
    }
    /**
     * NOT SUPPORTED
     * @param mixed $column
     * @param mixed $param
     * @param int $type
     * @param int $maxlen
     * @param mixed $driverdata
     * @return boolean
     */
    public function bindColumn (  $column ,  &$param ,  $type = null ,  $maxlen = null ,  $driverdata = null ) {
      return false;  
    }
    /**
     * NOT SUPPORTED
     * @param type $parameter
     * @param type $variable
     * @param type $data_type
     * @param type $length
     * @param type $driver_options
     * @return boolean
     */
    public function bindParam (  $parameter ,  &$variable ,  $data_type = PDO::PARAM_STR ,  $length = null,  $driver_options= null ) {
        return false;
    }
    /**
     * NOT SUPPORTED
     * @param mixed $parameter
     * @param mixed $value
     * @param int $data_type
     * @return boolean
     */
    public function  bindValue (  $parameter ,  $value , $data_type = PDO::PARAM_STR  ) {
        return false;
    }
    /**
     * 
     * @return boolean
     */
    public function  closeCursor (  ) {
        return odbc_free_result($this->_statement);
    }
    /**
     * 
     * @return int
     */
    public function  columnCount (  ){
        return odbc_num_fields($this->_statement);
    }
    /**
     * Not implemented
     * @return null
     */
    public function debugDumpParams ( ){
        return null;
    }
    /**
     * 
     * @return string
     */
    public function  errorCode ( ){
        return odbc_error($this->_db);
    }
    
    /**
     * 
     * @return array
     */
    public function  errorInfo (){
        return array(odbc_error($this->_db), odbc_error($this->_db), odbc_errormsg($this->_db));
    }
    
    /**
     * 
     * @param type $input_parameters
     * @throws pdoODBCException
     * @return boolean
     */
    public function execute ( $input_parameters = array() ){
        
        /*if (!odbc_execute($this->_statement, $input_parameters))
            $this->throwErrors();*/
        if (!$this->_lastExec = @odbc_execute($this->_statement, $input_parameters) ) {
            $this->_lastError = odbc_error();
            $this->_lastErrorMessage = odbc_errormsg();
            return false;
        }

        $this->rowCount();
        odbc_longreadlen($this->_statement, 1024*1024*30);
        return true;
        
    }
    
    public function fetch ($fetch_style = null , $cursor_orientation = PDO::FETCH_ORI_NEXT ,  $cursor_offset = 0){
        
        if ($fetch_style == null){
            $fetch_style = $this->_fetchMode;
        }
        
        if ($cursor_offset == 0 ) {
            $cursor_offset = null;
        }
        //$numrows = $this->rowCount();
        if ( $fetch_style == PDO::FETCH_ASSOC){
            $row = @odbc_fetch_array ($this->_statement );  
            return $row;
        }
        elseif ( $fetch_style == PDO::FETCH_COLUMN) {    
            if ( !$row = @odbc_fetch_array ($this->_statement )){
                    $this->_lastError = odbc_error ();
                    $this->_lastErrorMessage = odbc_errormsg();
                    return false;
            }
            $key = key($row);
            $result = $row[key($row)];
            $this->_lastError = odbc_error ();
            $this->_lastErrorMessage = odbc_errormsg();
            return $row[key($row)];
        }
        elseif ( $fetch_style == PDO::FETCH_BOTH or $fetch_style == PDO::FETCH_NUM){
            /*Bug avec driver FMP sur fetch row => emulation */
            if ( !$rowA = @odbc_fetch_array ($this->_statement, $cursor_offset ))
                    return false;
            
            $rowB = array();
            foreach ( $rowA as $key=>$value ){
                $rowB[] = $value;
            }
           
            if ( $fetch_style == PDO::FETCH_BOTH )
                return array_merge(
                    $rowA, $rowB );
            else
                return $rowB;
        }
        elseif ( $fetch_style == PDO::FETCH_NUM){
            /*Bug avec driver FMP sur fetch row => emulation */
            if ( !$rowA = @odbc_fetch_array ($this->_statement, $cursor_offset ))
                    return false;
            
            $rowB = array();
            foreach ( $rowA as $key=>$value ){
                $rowB[] = $value;
            }
            return $rowB;
        }
        elseif ( $fetch_style == PDO::FETCH_BOUND){
            return false;
        }
        elseif ( $fetch_style == PDO::FETCH_CLASS){
            return @odbc_fetch_object ($this->_statement, $cursor_offset );
        }
        elseif ( $fetch_style == PDO::FETCH_INTO){
            /* NOT SUPPORTED YET */
            return false;
        }
        elseif ( $fetch_style == PDO::FETCH_LAZY){
            /* NOT SUPPORTED YET */
            return false;
        }
        elseif ( $fetch_style == PDO::FETCH_NAMED){
            /* NOT SUPPORTED YET */
            return false;
        }
        elseif ( $fetch_style == PDO::FETCH_OBJ){
            /* NOT SUPPORTED YET */
            return false;
        }
    }
    
    /**
     * 
     * @param type $fetch_style
     * @param type $fetch_argument
     * @param type $ctor_args
     * @return array
     */
    public function fetchAll ($fetch_style= null ,  $fetch_argument = null ,  $ctor_args = array()){
        
        if ( $fetch_style == null)
            $fetch_style = $this->_fetchMode;
        
        $this->_fetchArgs = $fetch_argument;
        $this->_ctor_args = $ctor_args;
        
        //reset cursor
        $result = array();
        while ( $row = $this->fetch($fetch_style) ) {
            $result[] = $row; 
        }
        return $result;
    }
    public function fetchColumn ( $column_number = 0  ){
        if (!$row = $this->fetch(PDO::FETCH_NUM))
            $this->throwErrors();
        return @$row[$column_number];
    }
    
    public function fetchObject ( $class_name = "stdClass" ,  $ctor_args = array() ){
        
        if (!$row = $this->fetch())
            $this->throwErrors();
        $obj = new $class_name();
        foreach ( $row as $key => $value){
            $obj->$key = $value;
        }
        return $obj;
    }
    /**
     * NOT IMPLEMENTED YET
     * @param type $attribute
     * @return mixed
     */
    public function getAttribute ( $attribute ){
        return false;
    }
    public function getColumnMeta ( $column ){
        if (!$result = array('Type' =>odbc_field_type($this->_statement, $column)))
            $this->throwErrors();
        return $result;
    }
    public function nextRowset (  ){
        if (!$result =  odbc_next_result($this->_statement))
            $this->throwErrors();
        return $result;
    }
    /**
     * 
     * @return int
     */
    public function  rowCount (  ){
        $this->_rowCount = odbc_num_rows($this->_statement);
            //$this->throwErrors();
        return $this->_rowCount;
    }
    public function  setAttribute (  $attribute ,  $value ){
        return false;
    }
    /**
     * 
     * @param int $mode
     * @return boolean
     */
    public function  setFetchMode ( $mode, $params = NULL ){
        $this->_fetchMode = $mode;
        return true;
    }
          
            
    private function throwErrors() {
        if ( odbc_error($this->_db))
            throw new \yii\db\Exception(odbc_errormsg($this->_db). ' ('. odbc_error($this->_db). ')');
    }
}


class pdoODBCException extends \yii\base\Exception { 

    public function __construct( $message, $code = null) { 
        //echo $message,$code;
        if(strstr($message, 'SQLSTATE[')) { 
            preg_match('/SQLSTATE\[(\w+)\] \[(\w+)\] (.*)/', $message, $matches); 
            $this->code = ($matches[1] == 'HT000' ? $matches[2] : $matches[1]); 
            $this->message = $matches[3]; 
        } 
        else {
            
            $this->code = $code; 
            $this->message = $message; 
        }
    } 
} 
