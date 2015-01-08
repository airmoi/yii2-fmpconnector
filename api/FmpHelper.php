<?php
/**
 * @link https://github.com/airmoi/yii2-fmpconnector
 * @copyright Copyright (c) 2014 Romain Dunand
 * @license  MIT
 */
 
namespace airmoi\yii2fmconnector\api;


//require_once(dirname(__FILE__).'/FileMaker.php');

use Yii;
require_once dirname(__FILE__).'/FileMaker.php';

class FmpHelper extends \FileMaker {

    
    public $resultLayout = "PHP_scriptResult";
    public $resultField = "PHP_scriptResult";
    public $valueListLayout = "PHP_valueLists";
    public $host = 'localhost';
    
	/** @var */
    private $_layout;
    private $_valueLists = [];
    
    public function __construct() {
        parent::FileMaker('Logistics', $this->host, \Yii::$app->user->getIdentity()->username, \Yii::$app->user->getIdentity()->Password);
        return $this;
    }
    
    
    public function performScript($scriptName, array $params){
        $scriptParameters = "";
        foreach ($params as $name => $value){
           $scriptParameters .= "<".$name.">".$value."</".$name.">";
        }
        $cmd = $this->newPerformScriptCommand($this->resultLayout, $scriptName, $scriptParameters);
        
        $result = $cmd->execute();
        if (parent::isError($result))
        {
            /**
             *  @var $result FileMaker_Error
             */
            return '<error><SCRIPT_ERRORCODE>'.$result->getCode().'</SCRIPT_ERRORCODE><SCRIPT_ERRORDESCRIPTION>'.$result->getMessage().'</SCRIPT_ERRORDESCRIPTION></error>';
        }
        $record = $result->getFirstRecord();
        return html_entity_decode($record->getField($this->resultField));
    }
    
    public static function xmlget($data, $tag) {
        if ( preg_match('#<'.$tag.'>(.*)</'.$tag.'>#i', $data, $matches) ){
            return $matches[1];
        }
        else {
            return null;
        }
    }
    
    public function getValueList($listName){
        /*$cmd = $this->newFindAllCommand($this->valueListLayout);
        $cmd->setRange(0,1);*/
        if ( isset ( $this->_valueLists[$listName]))
            return $this->_valueLists[$listName];
        
        if ( $this->_layout === null) {
            $this->_layout = $this->getLayout($this->valueListLayout);
            if ( self::isError($this->_layout)) {
                Yii::error('Error getting layout : '.$this->valueListLayout. '('.$this->_layout->getMessage().')', 'airmoi\yii2fmconnector\api\FmpHelper::getValueList');
                return [];
            }
        }
        $result = $this->_layout->getValueListTwoFields($listName);
        if ( self::isError($result)) {
            Yii::error('Error getting value list : '.$listName. '('.$result->getMessage().')', 'airmoi\yii2fmconnector\api\FmpHelper::getValueList');
            return [];
        }
        Yii::info('Get value list : '.$listName, 'airmoi\yii2fmconnector\api\FmpHelper::getValueList');
        $this->_valueLists[$listName] = $result;
        return $this->_valueLists[$listName];
    }
}

