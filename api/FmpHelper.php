<?php
/**
 * @link https://github.com/airmoi/yii2-fmpconnector
 * @copyright Copyright (c) 2014 Romain Dunand
 * @license  MIT
 */

namespace airmoi\yii2fmconnector\api;

use Yii;
use yii\base\Component;
use airmoi\FileMaker\FileMaker;
use airmoi\FileMaker\FileMakerException;
use yii\base\UnknownMethodException;

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
 * @method string getLastRequestedUrl() Last URL call to web publishing engine.
 *
 * @method null setProperty($prop, $value) Sets a property to a new value for all API calls.
 */
class FmpHelper extends Component
{
    public $resultLayout = "PHP_scriptResult";
    public $resultField = "PHP_scriptResult";
    public $valueListLayout = "PHP_valueLists";
    public $errorTag = "SCRIPT_ERRORCODE";
    public $errorDescriptionTag = "SCRIPT_ERRORDESCRIPTION";
    public $scriptResultTag = "SCRIPT_RESULT";
    public $host = '127.0.0.1';
    public $db = '';
    public $username = '';
    public $password = '';
    public $useCookieSession = false;
    public $dateFormat;
    public $charset = 'utf-8';
    public $locale = 'en';
    public $prevalidate = false;
    public $emptyAsNull = false;
    public $curlOptions = [CURLOPT_SSL_VERIFYPEER => false];
    public $cache = null;
    public $schemaCache = true;
    public $schemaCacheDuration = null;
    public $sessionHandler = null;
    public $enableProfiling = false;
    public $enableLogging = false;
    public $logLevel = FileMaker::LOG_ERR;
    public $useDataApi = false;
    public $useDateFormatInRequests = false;

    /** @var FileMaker */
    private $_fm;
    private $_layout;
    private $_valueLists = [];
    private $_scriptResult;

    public function __construct($config = [])
    {
        parent::__construct($config);
    }

    public function init()
    {
        parent::init();
    }

    /**
     * @throws FileMakerException
     * @throws \yii\base\InvalidConfigException
     */
    private function initConnection()
    {
        if ($this->_fm === null) {
            $this->_fm = new FileMaker($this->db, $this->host, $this->username, $this->password);
            $this->_fm->setProperty('charset', $this->charset);
            $this->_fm->setProperty('locale', $this->locale);
            $this->_fm->setProperty('prevalidate', $this->prevalidate);
            $this->_fm->setProperty('curlOptions', $this->curlOptions);
            $this->_fm->setProperty('dateFormat', $this->dateFormat);
            $this->_fm->setProperty('useCookieSession', $this->useCookieSession);
            $this->_fm->setProperty('emptyAsNull', $this->emptyAsNull);
            $this->_fm->setProperty('schemaCache', $this->schemaCache);
            $this->_fm->setProperty('schemaCacheDuration', $this->schemaCacheDuration);
            $this->_fm->setProperty('enableProfiling', $this->enableProfiling);
            $this->_fm->setProperty('logLevel', $this->logLevel);
            $this->_fm->setProperty('useDataApi', $this->useDataApi);
            $this->_fm->setProperty('useDateFormatInRequests', $this->useDateFormatInRequests);

            if ($this->cache) {
                $this->_fm->setCache(Yii::$app->get($this->cache));
            }

            if ($this->enableLogging) {
                $this->_fm->setLogger(new Logger());
            }
            if ($this->sessionHandler) {
                $this->_fm->setSessionHandler($this->sessionHandler);
            }
        }
    }

    /**
     * @param $property
     * @param $value
     * @return FileMakerException|null
     * @throws FileMakerException
     */
    public function setFmOption($property, $value)
    {
        return $this->_fm->setProperty($property, $value);
    }

    private function endConnection()
    {
    }

    /**
     * Perform the named script and store the result
     * you may get result using getScriptResult method
     * @param string $scriptName
     * @param array $params
     * @return boolean
     * @throws \yii\base\InvalidConfigException
     */
    public function performScript($scriptName, array $params)
    {
        try {
            $scriptParameters = "";
            $this->initConnection();
            foreach ($params as $name => $value) {
                $scriptParameters .= "<" . $name . ">" . $value . "</" . $name . ">";
            }

            Yii::beginProfile("Perform script '$scriptName' with params '$scriptParameters'", 'yii\db\Command::query');
            $cmd = $this->_fm->newPerformScriptCommand($this->resultLayout, $scriptName, $scriptParameters);
            $result = $cmd->execute();

            Yii::endProfile("Perform script '$scriptName' with params '$scriptParameters'", 'yii\db\Command::query');
            if (!$this->useDataApi) {
                $record = $result->getFirstRecord();
                $this->_scriptResult = html_entity_decode($record->getField($this->resultField));
            } else {
                $this->_scriptResult = @$result['scriptResult'];
            }
            $this->endConnection();
            return true;
        }
        catch ( FileMakerException $e ) {
            Yii::endProfile("Perform script '$scriptName' with params '$scriptParameters'", 'yii\db\Command::query');
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
    public static function xmlget($data, $tag, $i = 0)
    {
        if (isempty($data)) {
            return "";
        } elseif ($data instanceof \SimpleXMLElement) {
            $xml = $data;
        } else {
            if (substr((string)$data, 0, 5) != '<?xml') {
                $data = "<?xml version='1.0' standalone='yes'?><body>" . $data . "</body>";
            }
            if (!$xml = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA)) {
                Yii::error(
                    'xmlget error : ' . $data . '(' . print_r(libxml_get_errors(), true) . ')',
                    __METHOD__
                );
                return null;
            }
        }

        if ($result = $xml->xpath($tag)) {
            if (sizeof($result[$i]->children()) > 0) {
                return $result[$i];
            } else {
                return (string)$result[$i];
            }
        } else {
            return null;
        }
    }

    /**
     * Returns executed script error code
     *
     * @return string Error code
     */
    public function getScriptError()
    {
        return self::xmlget($this->_scriptResult, $this->errorTag);
    }

    /**
     * Returns executed script error description
     *
     * @return string Error code
     */
    public function getScriptErrorDescription()
    {
        return self::xmlget($this->_scriptResult, $this->errorDescriptionTag);
    }

    /**
     * Returns executed script result
     *
     * @return string The script result
     */
    public function getScriptResult()
    {
        return self::xmlget($this->_scriptResult, $this->scriptResultTag);
    }

    /**
     * @param $listName
     * @return array|mixed
     * @throws \yii\base\InvalidConfigException
     */
    public function getValueList($listName)
    {
        try {
            $this->initConnection();
            if (isset($this->_valueLists[$listName])) {
                return $this->_valueLists[$listName];
            }

            if ($this->_layout === null) {
                $this->_layout = $this->_fm->getLayout($this->valueListLayout);
            }
            $result = $this->_layout->getValueListTwoFields($listName);
            Yii::info('Get value list : ' . $listName, 'airmoi\yii2fmconnector\api\FmpHelper::getValueList');
            $this->_valueLists[$listName] = array_flip($result);
            $this->endConnection();
            return $this->_valueLists[$listName];
        } catch (FileMakerException $e) {
            Yii::error('Error getting value list "' . $listName . '" (' . $e->getMessage() . ')', __METHOD__);
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
     * @throws FileMakerException
     * @throws \yii\base\InvalidConfigException
     */
    public function __call($name, $params)
    {
        $this->initConnection();

        if (method_exists($this, $name)) {
            return call_user_func_array([$this, $name], $params);
        } elseif (method_exists($this->_fm, $name)) {
            return call_user_func_array([$this->_fm, $name], $params);
        }

        throw new UnknownMethodException('Calling unknown method: ' . get_class($this) . "::$name()");
    }

    public static function mime_content_type($filename)
    {

        $mime_types = array(

            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',

            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );
        $exp = explode('.', $filename);
        $ext = strtolower(array_pop($exp));
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype;
        } else {
            return 'application/octet-stream';
        }
    }
}
