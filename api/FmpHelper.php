<?php
/**
 * @link https://github.com/airmoi/yii2-fmpconnector
 * @copyright Copyright (c) 2014 Romain Dunand
 * @license  MIT
 */
 
namespace airmoi\yii2fmconnector\api;


//require_once(dirname(__FILE__).'/FileMaker.php');

use Yii;
use yii\base\Component;
use airmoi\FileMaker\FileMaker;
use airmoi\FileMaker\FileMakerException;

/**
 * This class provide access to FileMaker PHP-API
 * with some improvement and helpers for easier access
 *
 * @author Romain dunand <airmoi@gmail.com>
 * @since 1.0
 * 
 * 
 * @method string getAPIVersion() Returns the version of the FileMaker API for PHP.
 * @method string getContainerData($url) Returns the data for the specified container field.
 * @method string getContainerDataURL($url) Returns the fully qualified URL for the specified container field.
 * @method string getMinServerVersion() Returns the minimum version of FileMaker Server that this API works with.
 * @method array getProperties() Returns an associative array of property name => property value for all current properties and their current values. 
 * 
 * @method boolean isError($variable) Tests whether a variable is a FileMaker API Error.
 * 
 * @method array listDatabases() Returns an array of databases that are available
 * @method array listLayouts() Returns an array of layouts from the current database
 * @method array listScripts() Returns an array of scripts from the current database
 * 
 * @method \airmoi\FileMaker\Object\Layout getLayout($layout) Returns the layout object.
 * @method \airmoi\FileMaker\Object\Record createRecord($layout, $fieldValues = []) Creates a new FileMaker_Record object.
 * 
 * @method \airmoi\FileMaker\Command\Add newAddCommand($layout, $values = array()) Creates a new Add object.
 * @method \airmoi\FileMaker\Command\CompoundFind newCompoundFindCommand($layout) Creates a new CompoundFind object.
 * @method \airmoi\FileMaker\Command\Delete newDeleteCommand($layout, $recordId) Creates a new Delete object.
 * @method \airmoi\FileMaker\Command\Duplicate newDuplicateCommand($layout, $recordId) Creates a new Duplicate object.
 * @method \airmoi\FileMaker\Command\Edit newEditCommand($layout, $recordId, $updatedValues = array()) Creates a new Edit object.
 * @method \airmoi\FileMaker\Command\FindAll newFindAllCommand($layout) Creates a new FindAll object.
 * 
 * @method \airmoi\FileMaker\Command\FindAny newFindAnyCommand($layout) Creates a new FindAny object.
 * @method \airmoi\FileMaker\Command\Find newFindCommand($layout) Creates a new Find object.
 * @method \airmoi\FileMaker\Command\FindRequest newFindRequest($layout) Creates a new FindRequest object.
 * @method \airmoi\FileMaker\Command\PerformScript newPerformScriptCommand($layout, $scriptName, $scriptParameters = null) Creates a new PerformScript object.
 * 
 * 
 * @method null setProperty($prop, $value) Sets a property to a new value for all API calls.
 */
class FmpHelper extends Component {

    
    public $resultLayout = "PHP_scriptResult";
    public $resultField = "PHP_scriptResult";
    public $valueListLayout = "PHP_valueLists";
    public $errorTag = "SCRIPT_ERRORCODE";
    public $errorDescriptionTag = "SCRIPT_ERRORDESCRIPTION";
    public $scriptResultTag = "SCRIPT_RESULT";
    public $host = 'localhost';
    public $db = '';
    public $username = '';
    public $password = '';
    public $uniqueSession = true;
    
    /** @var FileMaker */
    private $_fm;
    private $_layout;
    private $_valueLists = [];
    private $_scriptResult;
    private $_cookie;
    
    public function __construct($config = []) {
        \Yii::configure($this, $config);

    }
    
    public function init(){
    }

    private function initConnection() { 
         if( $this->uniqueSession && file_exists(Yii::getAlias('@runtime').'/WPCSessionID')){
            $this->_cookie = file_get_contents (Yii::getAlias('@runtime').'/WPCSessionID');
            if ( @$_COOKIE["WPCSessionID"] != $this->_cookie)  {
            	setcookie ('WPCSessionID', $this->_cookie) ;
            	$_COOKIE["WPCSessionID"] = $this->_cookie;
       		}
        }
        if ( $this->_fm === null )
            $this->_fm = new FileMaker($this->db, $this->host, $this->username, $this->password);
    }

    private function endConnection() { 
        if( $this->uniqueSession && isset($_COOKIE["WPCSessionID"]) && $_COOKIE["WPCSessionID"] != $this->_cookie ){
            file_put_contents(Yii::getAlias('@runtime').'/WPCSessionID', $_COOKIE["WPCSessionID"]) ;
        }   
    }
    
    public function performScript($scriptName, array $params){
        try {
            $this->initConnection();
            $scriptParameters = "";
            foreach ($params as $name => $value){
               $scriptParameters .= "<".$name.">".$value."</".$name.">";
            }
            Yii::trace("Performing script $scriptName with params $scriptParameters", __NAMESPACE__ . __CLASS__);
            $t0 = microtime(true);
            $cmd = $this->_fm->newPerformScriptCommand($this->resultLayout, $scriptName, $scriptParameters);        
            $result = $cmd->execute();
       
            Yii::trace("Script performed successfully in " . (microtime(true)-$t0), __NAMESPACE__ . __CLASS__);
            $record = $result->getFirstRecord();
            $this->_scriptResult = html_entity_decode($record->getField($this->resultField));
            $this->endConnection();
            return true;
        }
        catch ( FileMakerException $e ) {
            Yii::error("Script error : ". $e->getCode() . ' ' . $e->getMessage(), __NAMESPACE__ . __CLASS__);
            $this->_scriptResult = '<'.$this->errorTag.'>'.$e->getCode().'</'.$this->errorTag.'><'.$this->errorDescriptionTag.'>'.$e->getMessage().'</'.$this->errorDescriptionTag.'>';
            return false;
        }
    }
    
    /**
     * Renvoi le contenu de la balise 
     * 
     * @param mixed $data an xml string or SimpleXMLElement object
     * @param string $tag XML node name to return 
     * @param int $i node repetition n° 
     * @return \SimpleXMLElement|string|null If node contains subnodes, returns subnodes, node content if node contain a string, null if not dosn't exists 
     */
    public static function xmlget($data, $tag, $i = 0 ) {
        if ( $data instanceof \SimpleXMLElement)
            $xml = $data;
        else {
            if(substr($data, 0, 5 ) != '<?xml')
                    $data = "<?xml version='1.0' standalone='yes'?><body>".$data."</body>";
            //$old = libxml_use_internal_errors(true);
            if ( !$xml = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA)) {
                Yii::error('xmlget error : '.$data. '('.print_r(libxml_get_errors(), true).')', 'airmoi\yii2fmconnector\api\FmpHelper::getValueList');
                    return null;
            }
        }

        if ( $result = $xml->xpath($tag))
        {
            if ( sizeof( $result[$i]->children() ) > 0) {
                return $result[$i];
            }
            else {
                return (string) $result[$i];
            }
        }
        else
            return null;
    }
    
    /**
     * Returns executed script error code
     * 
     * @return string Error code
     */
    public function getScriptError() {
       return self::xmlget($this->_scriptResult, $this->errorTag);
    }
    
    /**
     * Returns executed script error description
     * 
     * @return string Error code
     */
    public function getScriptErrorDescription() {
       return self::xmlget($this->_scriptResult, $this->errorDescriptionTag);
    }
    
    /**
     * Returns executed script result
     * 
     * @return string The script result 
    */
    public function getScriptResult() {
       return self::xmlget($this->_scriptResult, $this->scriptResultTag);
    }
    
    public function getValueList($listName){ 
        try {
            $this->initConnection();
            if ( isset ( $this->_valueLists[$listName]))
                return $this->_valueLists[$listName];

            if ( $this->_layout === null) {
                $this->_layout = $this->_fm->getLayout($this->valueListLayout);
            }
            $result = $this->_layout->getValueListTwoFields($listName);
            Yii::info('Get value list : '.$listName, 'airmoi\yii2fmconnector\api\FmpHelper::getValueList');
            $this->_valueLists[$listName] = $result;
            $this->endConnection();
            return $this->_valueLists[$listName];
        }
        catch ( airmoi\FileMaker\FileMakerException $e ){
            Yii::error('Error getting value list "'.$listName. '" ('.$e->getMessage().')', __METHOD__);
            return [];
        }
    }
    
    /**
     * Calls the named method which is not a class method.
     *
     * This method will check if any attached behavior has
     * the named method and will execute it if available.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when an unknown method is being invoked.
     * @param string $name the method name
     * @param array $params method parameters
     * @return mixed the method return value
     * @throws UnknownMethodException when calling unknown method
     */
    public function __call($name, $params)
    {
        $this->initConnection();
        
        if ( method_exists($this, $name))
                return call_user_func_array([$this, $name], $params);
        elseif ( method_exists($this->_fm, $name))
                return call_user_func_array([$this->_fm, $name], $params);

        throw new UnknownMethodException('Calling unknown method: ' . get_class($this) . "::$name()");
    }
}

