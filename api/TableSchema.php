<?php
/**
 * @link https://github.com/airmoi/yii2-fmpconnector
 * @copyright Copyright (c) 2014 Romain Dunand
 * @license  MIT
 */

namespace airmoi\yii2fmconnector\api;

/**
 * TableSchema represents the metadata of a database table.
 *
 * @author Romain Dunand <airmoi@gmail.com>
 * @since 1.0
 */
class TableSchema extends \yii\db\TableSchema
{
    /**
     *
     * @var bool 
     */
    public $isPortal = false;
    /**
     * 
     * @var TableSchema[] Array of ColumnSchema indexed by relation name (OT)
     */
    public $relations = [];

    /**
     *
     * @var array Value lists names
     */
    public $valueLists = [];
}
