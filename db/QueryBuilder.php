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
}
