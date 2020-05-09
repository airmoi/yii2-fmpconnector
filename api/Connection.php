<?php
/**
 * @link https://github.com/airmoi/yii2-fmpconnector
 * @copyright Copyright (c) 2014 Romain Dunand
 * @license  MIT
 */

namespace airmoi\yii2fmconnector\api;

use yii;
use yii\base\UnknownMethodException;

/**
 * Hook standard db connection to use airmoi\yii2fmconnector\db\FmpCommand
 *
 * @author Romain Dunand <airmoi@gmail.com>
 * @since 1.0
 *
 * @method Schema getSchema()
 * @method TableSchema getTableSchema($name, $refresh = false)
 *
 * @method string getAPIVersion() Returns the version of the FileMaker API for PHP.
 * @method string getContainerData($url) Returns the data for the specified container field.
 * @method string getContainerDataURL($url) Returns the fully qualified URL for the specified container field.
 * @method string getMinServerVersion() Returns the minimum version of FileMaker Server that this API works with.
 * @method array getProperties() Returns an associative array of property name => property value for all current
 * properties and their current values.
 *
 * @method boolean isError($variable) Tests whether a variable is a FileMaker API Error.
 *
 * @method array listDatabases() Returns an array of databases that are available
 * @method array listLayouts() Returns an array of layouts from the current database
 * @method array listScripts() Returns an array of scripts from the current database
 *
 * @method \airmoi\FileMaker\Object\Layout getLayout($layout) Returns the layout object.
 * @method \airmoi\FileMaker\Object\Record createRecord($layout, $fieldValues = []) Creates a new FileMaker_Record object.
 * @method \airmoi\FileMaker\Object\Record getRecordById($layout, $id) Returns a single Object\Record object matching
 * the given layout and record ID, or throws a FileMakerException object, if this operation fails.
 *
 * @method \airmoi\FileMaker\Command\Add newAddCommand($layout, $values = array()) Creates a new Add object.
 * @method \airmoi\FileMaker\Command\CompoundFind newCompoundFindCommand($layout) Creates a new CompoundFind object.
 * @method \airmoi\FileMaker\Command\Delete newDeleteCommand($layout, $recordId) Creates a new Delete object.
 * @method \airmoi\FileMaker\Command\Duplicate newDuplicateCommand($layout, $recordId) Creates a new Duplicate object.
 * @method \airmoi\FileMaker\Command\Edit newEditCommand($layout, $recordId, $updatedValues = array())
 * Creates a new Edit object.
 * @method \airmoi\FileMaker\Command\FindAll newFindAllCommand($layout) Creates a new FindAll object.
 *
 * @method \airmoi\FileMaker\Command\FindAny newFindAnyCommand($layout) Creates a new FindAny object.
 * @method \airmoi\FileMaker\Command\Find newFindCommand($layout) Creates a new Find object.
 * @method \airmoi\FileMaker\Command\FindRequest newFindRequest($layout) Creates a new FindRequest object.
 * @method \airmoi\FileMaker\Command\PerformScript newPerformScriptCommand($layout, $scriptName, $scriptParameters = null) Creates a new PerformScript object.
 *
 *
 * @method string getLastRequestedUrl() Last URL call to xml engine.
 *
 * @method null setProperty($prop, $value) Sets a property to a new value for all API calls.
 * @method null getProperty($prop) get FileMaker API property Value.
 */
class Connection extends \yii\db\Connection
{
    /**
     *
     * @var FmpHelper
     */
    private $_fm;

    public $host;
    public $dbName;

    public $schemaMap = [
        'fmpapi' => [
            'class' => 'airmoi\yii2fmconnector\api\Schema',
        ]
    ];

    public $options = [];

    /**
     * Connection constructor.
     * @param array $config
     * @throws \Exception
     */
    public function __construct($config = array())
    {
        parent::__construct($config);
        $this->open();
    }

    /**
     * @throws \Exception
     */
    public function reset()
    {
        $this->_fm = null;
        $this->open();
    }

    /**
     * Establishes a DB connection.
     * It does nothing if a DB connection has already been established.
     * @throws \Exception if connection fails
     */
    public function open()
    {
        if ($this->_fm !== null) {
            return;
        }

        try {
            $this->_fm = $this->createFmInstance();
            $this->initConnection();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Creates the PDO instance.
     * This method is called by [[open]] to establish a DB connection.
     * The default implementation will create a PHP PDO instance.
     * You may override this method if the default PDO needs to be adapted for certain DBMS.
     * @return FmpHelper the FileMaker Helper instance
     * @throws \Exception
     */
    protected function createFmInstance()
    {
        $this->parseDsn();
        $config = yii\helpers\ArrayHelper::merge(
            $this->options,
            [
                'db' => $this->dbName,
                'host' => $this->host,
                'username' => $this->username,
                'password' => $this->password,
                'schemaCache' => $this->enableSchemaCache,
                'schemaCacheDuration' => $this->schemaCacheDuration,
                'cache' => $this->schemaCache,
                'enableProfiling' => $this->enableProfiling,
                'enableLogging' => $this->enableLogging,
                'sessionHandler' => $this->getSessionHandler(),
            ]
        );
        return new FmpHelper($config);
    }

    /**
     * DSN pattern : fmpapi:host=<host ip or dns>;dbname=<db name>
     * @throws \Exception
     */
    public function parseDsn()
    {
        if (($pos = strpos($this->dsn, ':')) !== false) {
            $connectionString = substr($this->dsn, $pos + 1, strlen($this->dsn));
        } else {
            $connectionString = $this->dsn;
        }
        $connectionArray = explode(';', $connectionString);
        $this->host = explode('=', $connectionArray[0])[1];
        $this->dbName = explode('=', $connectionArray[1])[1];

        if (empty($this->dbName)) {
            throw new \Exception("Please provide a DB Name");
        }
    }

    public function getSessionHandler()
    {
        if (Yii::$app instanceof yii\web\Application) {
            return Yii::$app->session;
        }
    }

    /**
     * Initializes the DB connection.
     * This method is invoked right after the DB connection is established.
     * The default implementation turns on `PDO::ATTR_EMULATE_PREPARES`
     * if [[emulatePrepare]] is true, and sets the database [[charset]] if it is not empty.
     * It then triggers an [[EVENT_AFTER_OPEN]] event.
     */
    protected function initConnection()
    {
        $this->trigger(self::EVENT_AFTER_OPEN);
    }

    /**
     * Creates a command for execution.
     * @param string $sql the SQL statement to be executed
     * @param array $params the parameters to be bound to the SQL statement
     * @return FmpHelper the FileMaker Helper command
     */
    public function createCommand($sql = null, $params = [])
    {
        return new FmpHelper();
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
        if (method_exists($this, $name)) {
            return call_user_func_array([$this, $name], $params);
        } elseif (is_callable([$this->_fm, $name])) {
            return call_user_func_array([$this->_fm, $name], $params);
        }

        throw new UnknownMethodException('Calling unknown method: ' . get_class($this) . "::$name()");
    }
}
