<?php
/**
 * @link https://github.com/airmoi/yii2-fmpconnector
 * @copyright Copyright (c) 2014 Romain Dunand
 * @license  MIT
 */

namespace airmoi\yii2fmconnector\api;

use Yii;
use airmoi\FileMaker\FileMaker;


/**
 * Class Logger
 * @package airmoi\yii2fmconnector\api
 *
 * Logger interface to handle airmoi/FileMaker logs in yii logs
 */
class Logger
{
    public function log($message, $level)
    {
        switch ($level) {
            case FileMaker::LOG_INFO:
                Yii::info($message, __NAMESPACE__);
                break;
            case FileMaker::LOG_ERR:
                Yii::error($message, __NAMESPACE__);
                break;
            case FileMaker::LOG_DEBUG:
                Yii::debug($message, __NAMESPACE__);
                break;
            case FileMaker::LOG_NOTICE:
                Yii::warning($message, __NAMESPACE__);
                break;
            default:
                Yii::warning($message, __NAMESPACE__);
        }
    }

    public function profileBegin($token)
    {
        Yii::beginProfile($token, __NAMESPACE__);

        //Add custom query log to yii\db\Command::query category so it appears in Yii debug databases debug panel
        Yii::info($token, 'yii\db\Command::query');
    }

    public function profileEnd($token)
    {
        Yii::endProfile($token, __NAMESPACE__);
    }
}