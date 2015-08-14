<?php
/**
 * @link https://github.com/airmoi/yii2-fmpconnector
 * @copyright Copyright (c) 2014 Romain Dunand
 * @license  MIT
 */

namespace airmoi\yii2fmconnector\db;



/**
 * QueryBuilder is the query builder for FileMaker databases.
 *
 * @author Romain Dunand <airmoi@gmail.com>
 * @since 1.0
 */
class QueryBuilder extends \yii\db\QueryBuilder {
    

    /**
     * @var Connection the database connection.
     */
    public $db;
    /**
     * @param integer $limit
     * @param integer $offset
     * @return string the LIMIT and OFFSET clauses
     */
    public function buildLimit($limit, $offset)
    {
        $sql = '';
        if($offset>0)
            $sql.=' OFFSET '.(int)$offset . ' ROWS';

        if($limit>0)
            $sql.=' FETCH FIRST  '.(int)$limit . ' ROWS ONLY';

        return ltrim($sql);
    }
    
    /**
     * Generates a SELECT SQL statement from a [[Query]] object.
     * @param Query $query the [[Query]] object from which the SQL statement will be generated.
     * @param array $params the parameters to be bound to the generated SQL statement. These parameters will
     * be included in the result with the additional parameters generated during the query building process.
     * @return array the generated SQL statement (the first array element) and the corresponding
     * parameters to be bound to the SQL statement (the second array element). The parameters returned
     * include those provided in `$params`.
     */
    public function build($query, $params = [])
    {
        $query = $query->prepare($this);
        
        
        if(empty($query->select)){
            $schema = $this->db->getSchema();
            //$tables = $this->qu
            foreach ( $query->from as $i => $table){
                foreach ( $schema->listFields($table) as $fieldName => $column ){
                    if ( $column['FieldType'] == 'binary'){ //Cast containers as text to retrieve names
                        $query->select[] = "CAST($fieldName AS VARCHAR) as $fieldName";
                    } else {
                        $query->select[] = $fieldName;
                    }
                }
            }
        } 

        $params = empty($params) ? $query->params : array_merge($params, $query->params);

        $clauses = [
            $this->buildSelect($query->select, $params, $query->distinct, $query->selectOption),
            $this->buildFrom($query->from, $params),
            $this->buildJoin($query->join, $params),
            $this->buildWhere($query->where, $params),
            $this->buildGroupBy($query->groupBy),
            $this->buildHaving($query->having, $params),
        ];

        $sql = implode($this->separator, array_filter($clauses));
        $sql = $this->buildOrderByAndLimit($sql, $query->orderBy, $query->limit, $query->offset);

        $union = $this->buildUnion($query->union, $params);
        if ($union !== '') {
            $sql = "($sql){$this->separator}$union";
        }

        return [$sql, $params];
    }
}
