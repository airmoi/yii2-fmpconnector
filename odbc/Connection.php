<?php
/**
 * @link https://github.com/airmoi/yii2-fmpconnector
 * @copyright Copyright (c) 2014 Romain Dunand
 * @license  MIT
 */

namespace airmoi\yii2fmconnector\db;

/**
 * Hook standard db connection to use airmoi\yii2fmconnector\db\FmpCommand
 *
 * @author Romain Dunand <airmoi@gmail.com>
 * @since 1.0
 */


class Connection extends \yii\db\Connection
{
    

     public $schemaMap = [
        'fmp' => [
            'class' => 'airmoi\yii2fmconnector\db\Schema',
            ]// FileMaker ODBC
       
    ];
     
     /**
     * Creates a command for execution.
     * @param string $sql the SQL statement to be executed
     * @param array $params the parameters to be bound to the SQL statement
     * @return Command the DB command
     */
    public function createCommand($sql = null, $params = [])
    {
        $command = new FmpCommand([
            'db' => $this,
            'sql' => $sql,
        ]);

        return $command->bindValues($params);
    }
}
