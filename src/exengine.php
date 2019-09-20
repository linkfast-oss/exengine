<?php
/* PHP Version Check */

namespace {
    if (version_compare(PHP_VERSION, '5.6.0', '<')) {
        print '<h1>ExEngine</h1><p>ExEngine requires PHP 5.6 or higher, please update your installation.</p>';
        exit();
    }
}
/**
 * ExEngine namespace.
 */

namespace ExEngine {

    use Throwable;

    class Rest
    {
        /**
         * @param array $argument_array
         * @return mixed
         * @throws ResponseException
         */
        final function executeRest(array $argument_array)
        {
            $request_method = 'get';
            if (isset($_SERVER['REQUEST_METHOD'])) {
                $request_method = strtolower($_SERVER['REQUEST_METHOD']);
            }
            if (method_exists($this, $request_method)) {
                return call_user_func_array([$this, $request_method], $argument_array);
            } else {
                throw new ResponseException("REST Method (" . $request_method . ") is not defined.", 404);
            }
        }
    }

    /**
     * Class DataClass
     * This class is supposed to be used as a parent of any object returning function.
     * ExEngine Core will automatically parse the properties as a json object.
     * Available modifiers:
     *  supressNulls: if true, all non-initialized or null properties will be stripped out.
     *      Can be set globally in `BaseConfig::supressNulls` or in `$this->dcConfiguration->supressNulls`.
     * @package ExEngine
     */
    abstract class DataClass
    {
        /**
         * Contains the DataClass configuration, should be set it in the child constructor.
         * @var null|\ExEngine\DataClassLocalConfig
         */
        protected $dcConfiguration = null;

        /**
         * Converts all properties into a serializable array.
         * @return array
         */
        final public function expose()
        {
            if ((\ee()->getConfig()->isSuppressNulls() && $this->dcConfiguration == null) ||
                ($this->dcConfiguration != null && $this->dcConfiguration->isSupressNulls())) {
                return array_filter(get_object_vars($this), function ($v) {
                    if ($v === $this->dcConfiguration) {
                        return false;
                    }
                    return $v !== null;
                });
            } else {
                return array_filter(get_object_vars($this), function ($v) {
                    if ($v === $this->dcConfiguration) {
                        return false;
                    }
                    return true;
                });
            }
        }
    }

    /**
     * Class ResponseException
     * Simple extension to PHP's Exception class.
     * @package ExEngine
     */
    class ResponseException extends \Exception
    {
        public function __construct($message = "", $code = 0, Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }
    }

    class DataClassLocalConfig
    {
        protected $supressNulls = false;

        /**
         * DataClassLocalConfig constructor.
         * @param bool|null $suppressNulls Activates `DataClass` automatic null suppression.
         */
        public final function __construct(
            $suppressNulls = null
        )
        {
            if ($suppressNulls === null) {
                $this->supressNulls = \ee()->getConfig()->isSuppressNulls();
            } else {
                $this->supressNulls = $suppressNulls;
            }
        }

        public final function isSupressNulls()
        {
            return $this->supressNulls;
        }
    }

    abstract class BaseConfig
    {
        /* config default values */
        protected $controllersLocation = "._";
        protected $usePrettyPrint = true;
        protected $showVersionInfo = "MINIMAL";
        protected $suppressNulls = true;
        protected $showStackTrace = true;
        protected $showHeaderBanner = true;
        protected $dbConnectionAuto = false;
        protected $launcherFolderPath = "";
        protected $filters = [];
        protected $production = false;
        protected $forceAutoDbInit = false;
        /* getters */
        /**
         * True if production optimizations are enabled. Please test your app in development mode first, production
         * mode will disable most of classes/methods/duplicates/etc checks to improve performance.
         * @return bool
         */
        public function isProduction()
        {
            return $this->production;
        }
        /**
         * @return bool
         */
        public function isForceAutoDbInit()
        {
            return $this->forceAutoDbInit;
        }
        /**
         * Returns an array with the filters to be chained.
         * @return Filter[]
         */
        public function getFilters()
        {
            return $this->filters;
        }
        /**
         * Returns the instance launcher folder path.
         * @return string
         */
        public function getLauncherFolderPath()
        {
            return $this->launcherFolderPath;
        }

        /**
         * True if JSON output null suppression is enabled.
         * @return bool
         */
        public function isSuppressNulls()
        {
            return $this->suppressNulls;
        }

        /**
         * Returns the controller's folder.
         * @return string
         */
        public function getControllersLocation()
        {
            return $this->controllersLocation;
        }

        /**
         * Returns true if pretty JSON printing is enabled.
         * @return bool
         */
        public function isUsePrettyPrint()
        {
            return $this->usePrettyPrint;
        }

        /**
         * @return string
         */
        public function getShowVersionInfo()
        {
            return $this->showVersionInfo;
        }

        /**
         * @return mixed
         */
        public function getSessionConfig()
        {
            return $this->sessionConfig;
        }
        /**
         * @return bool
         */
        public function isShowStackTrace()
        {
            return $this->showStackTrace;
        }
        /**
         * @return bool
         */
        public function isShowHeaderBanner()
        {
            return $this->showHeaderBanner;
        }
        /**
         * @return bool
         */
        public function isDbConnectionAuto()
        {
            return $this->dbConnectionAuto;
        }
        /* non overridable methods */
        final public function registerFilter(Filter $filter) {
            if (!$this->isProduction()) {
                if (!$filter instanceof Filter) {
                    throw new ResponseException("Invalid filter is trying to be registered in chain. Filters must
                    be an instance of Filter interface.", 500);
                }
                $class = new ReflectionClass($filter);
                $method = $class->getMethod("doFilter");
                if ($method->class != get_class($filter)) {
                    throw new ResponseException("Invalid filter is trying to be registered in chain. Filters must
                    implement the 'doFilter' method.", 500);
                }
                foreach ($this->filters as $registeredFilter) {
                    if (get_class($registeredFilter) == get_class($filter)) {
                        CoreX::addDevelopmentMessage(['WARNING' => 'Filter class '.get_class($registeredFilter).' is 
                            registered twice, not an error, but maybe a typo or intentional?.']);
                    }
                }
            }
            $this->filters[] = $filter;
        }
        /* default overridables */
        /**
         * Default overridable method for defining a database connection. Do not call parent::dbInit();
         *
         * Important: Automatic detection and initialization of supported database managers is disabled by default
         * in production mode. You can force the execution in production mode setting $this->forceAutoDbInit to true.
         */
        public function dbInit()
        {
            if (!$this->isProduction() && !$this->forceAutoDbInit) {
                // RedBeanPHP Classic version
                if (class_exists("\\R")) {
                    \R::setup();
                    // RedBeanPHP Composer version uses PSR-4
                } else if (class_exists("\\RedBeanPHP\\R")) {
                    \RedBeanPHP\R::setup();
                    // POMM
                } else if (class_exists('\PommProject\Foundation\Pomm')) {
                    if (file_exists($this->launcherFolderPath . '/.pomm_cli_bootstrap.php')) {
                        $pomm = require $this->launcherFolderPath . '/.pomm_cli_bootstrap.php';
                        if (sizeof($pomm->getSessionBuilders()) == 0) {
                            throw new ResponseException("POMM configuration file found, add a connection or override config::dbInit() or uninstall.", 500);
                        }
                        return $pomm;
                    } else {
                        throw new ResponseException("POMM found, please configure or override config::dbInit() or uninstall.", 500);
                    }
                };
            }
        }

        /**
         * BaseConfig constructor, if you override, you must call parent constructor.
         * @param $launcherFolderPath You must pass the full folder path of your instance launcher, ex. new CoreX(new Config(__DIR__);.
         */
        public function __construct($launcherFolderPath)
        {
            $this->launcherFolderPath = $launcherFolderPath;
        }
    }

    class DefaultConfig extends BaseConfig
    {
    }

    class ErrorDetail extends DataClass
    {
        protected $stackTrace = [];
        protected $message = "";

        function __construct(
            array $stackTrace = null,
            $message
        )
        {
            $this->stackTrace = $stackTrace;
            $this->message = $message;
        }
    }

    /**
     * Class StandardResponse
     * This class is a generic serialization ready response data structure, part of the ExEngine Request Lifecycle
     * @package ExEngine
     */
    class StandardResponse extends DataClass
    {
        protected $took = 0;
        protected $code = 200;
        protected $data = null;
        protected $error = false;
        protected $errorDetails = null;
        protected $developmentMessages = null;

        /**
         * StandardResponse constructor.
         * @param int $took
         * @param int $code
         * @param array|NULL $data
         * @param bool $error
         * @param ErrorDetail|NULL|false $errorDetails
         * @param array|NULL|false $developmentMessages When set to an array, displays the development messages to the
         *                                              exposed http response body.
         */
        function __construct(
            $took,
            $code,
            array $data = NULL,
            $error = false,
            ErrorDetail $errorDetails = NULL,
            $developmentMessages = NULL
        )
        {
            $this->code = $code;
            $this->took = $took;
            $this->data = $data;
            $this->error = $error;
            if (!\ee()->getConfig()->isProduction())
                if ($developmentMessages != NULL || $developmentMessages != false) {
                    $this->developmentMessages = $developmentMessages;
                }
            if ($error != NULL || $error != false) {
                $this->errorDetails = $errorDetails->expose();
            }
        }
    }

    /**
     * Class MethodMeta
     * This class represents the metadata of a controller method just before being executed.
     * Specially created for using with filters, this allows to have a good structure for using a framework level
     * filter system, just as for Filter and RESTController classes.
     * @package ExEngine
     */
    class MethodMeta {
        private $className = '';
        private $methodName = '';
        private $arguments = [];

        /**
         * MethodMeta constructor.
         * @param string $className
         * @param string $methodName
         * @param array $arguments
         */
        public function __construct($className, $methodName, array $arguments)
        {
            $this->className = $className;
            $this->methodName = $methodName;
            $this->arguments = $arguments;
        }

        /**
         * @return string
         */
        public function getClassName()
        {
            return $this->className;
        }

        /**
         * @return string
         */
        public function getMethodName()
        {
            return $this->methodName;
        }

        /**
         * @return array
         */
        public function getArguments()
        {
            return $this->arguments;
        }



    }

    abstract class Filter {
        abstract function doFilter(MethodMeta $controllerMeta, $filtersData = null);
        final public function __construct() {}
    }

    class CoreX
    {
        /**
         * This static variable contains the Core X instance that will be accessed globally.
         * @var CoreX
         */
        private static $instance = null;
        private static $developmentMessages = [];
        /**
         * Adds a message (can be any serializable object) to the DevelopmentMessages chain, only available
         * in development mode and with Standard responses.
         * @param string|mixed $message
         */
        public static function addDevelopmentMessage($message) {
            CoreX::$developmentMessages[] = $message;
        }

        /**
         * @return CoreX
         */
        public static function getInstance()
        {
            return self::$instance;
        }

        private $config = null;

        /**
         * @return BaseConfig
         */
        public function getConfig()
        {
            return $this->config;
        }

        private function usePrettyPrint()
        {
            if ($this->getConfig()->isUsePrettyPrint()) {
                return JSON_PRETTY_PRINT;
            }
            return null;
        }

        /**
         * @param string $ControllerFilePath
         * @return string
         */
        private function getController($ControllerFilePath)
        {
            return $this->getConfig()->getControllersLocation() . '/' . $ControllerFilePath . '.php';
        }

        /**
         * @param string $ControllerFolder
         * @return string
         */
        private function getControllerFolder($ControllerFolder)
        {
            return $this->getConfig()->getControllersLocation() . '/' . $ControllerFolder;
        }

        private $filterData = [];

        public function getFilterData() {
            return $this->filterData;
        }

        private function processFilters(MethodMeta $controllerMeta) {
            foreach ($this->getConfig()->getFilters() as $filter) {
                $this->filterData[get_class($filter)] = $filter->doFilter($controllerMeta, $this->filterData);
            }
        }

        /**
         * Url query parser and executor.
         * @return string
         * @throws ResponseException
         * @throws Throwable
         */
        private function processArguments()
        {

            $start = time();
            $reqUri = $_SERVER['REQUEST_URI'];
            $httpCode = 200;
            $method = $_SERVER['REQUEST_METHOD'];
            //error_log('method: ' . $method);

            preg_match("/(?:\.php\/)(.*?)(?:\?|$)/", $reqUri, $matches, PREG_OFFSET_CAPTURE);
            //print_r($matches);
            if (count($matches) > 1) {
                $access = explode('/', $matches[1][0]);

                if (strlen($access[0]) == 0) {
                    // if the controller/folder name is empty
                    throw new ResponseException("Not found.", 404);
                }

                $className = "";
                $method = "";
                $arguments = [];

                // Find and instantiate controller.
                if (count($access) > 0) {
                    $fpart = $access[0];
                    $uc_fpart = ucfirst($fpart);
                    // Check if controller exists and load it.
                    if (file_exists($this->getController($fpart))) {
                        include_once($this->getController($fpart));
                        $classObj = new $uc_fpart();
                        $className = $uc_fpart;
                        // check if method is defined
                        if (count($access) > 1) {
                            $method = $access[1];
                            $arguments = array_slice($access, 2);
                        }
                    } else {
                        if (count($access) > 1) {
                            $spart = $access[1];
                            $uc_spart = ucfirst($spart);
                            // Check if is folder, and load if controller found.
                            if (is_dir($this->getControllerFolder($fpart))) {
                                if (file_exists($this->getControllerFolder($fpart) . '/' . $spart . '.php')) {
                                    include_once($this->getControllerFolder($fpart) . '/' . $spart . '.php');
                                    $classObj = new $uc_spart();
                                    $className = $uc_spart;
                                    // check if method is defined
                                    if (count($access) > 2) {
                                        $method = $access[2];
                                        $arguments = array_slice($access, 3);
                                    }
                                } else {
                                    // 404
                                    throw new ResponseException("Not found.", 404);
                                }
                            }
                        } else {
                            // 404
                            throw new ResponseException("Not found.", 404);
                        }
                    }
                } else {
                    // if the controller/folder name is not defined correctly
                    throw new ResponseException("Not found.", 404);
                }

                $isRestController = false;
                if (isset($classObj) && $classObj instanceof Rest) {
                    // connect to database if autoconnection is enabled
                    if ($this->getConfig()->isDbConnectionAuto()) {
                        $this->getConfig()->dbInit();
                    }
                    // if controller is Rest, execute directly depending on the method.
                    try {
                        // Extract Controller Meta
                        $controllerMeta = new MethodMeta($className,
                            strtolower($_SERVER['REQUEST_METHOD']), $arguments);
                        // Process Filters
                        $this->processFilters($controllerMeta);
                        // Execute Method
                        $data = $classObj->executeRest(array_slice($access, 1));
                    } catch (\Throwable $restException) {
                        if ($restException instanceof ResponseException) {
                            throw $restException;
                        }
                        throw new ResponseException($restException->getMessage(), 500, $restException);
                    }
                    $isRestController = true;
                } else {
                    // if not, check if method is defined
                    if (isset($classObj) && method_exists($classObj, $method)) {
                        try {
                            // Extract Controller Meta
                            $controllerMeta = new MethodMeta($className,$method, $arguments);
                            // Process Filters
                            $this->processFilters($controllerMeta);
                            // Execute Method
                            $data = call_user_func_array([$classObj, $method], $arguments);
                        } catch (\Throwable $methodException) {
                            if ($methodException instanceof ResponseException) {
                                throw $methodException;
                            }
                            throw new ResponseException($methodException->getMessage(), 500, $methodException);
                        }
                    } else {
                        // if method does not exist, return not found
                        throw new ResponseException("Not found.", 404);
                    }
                }

                if (isset($data) && $data instanceof DataClass) {
                    $data = $data->expose();
                }
                $end = time();



                if (isset($data)) {
                    if (is_array($data)) {
                        if (!isset($data['_useStandardResponse'])) {
                            // Not defined. For REST controllers is disabled but in standard controllers is enabled
                            // by default.
                            if ($isRestController) {
                                $data['_useStandardResponse'] = false;
                            } else {
                                $data['_useStandardResponse'] = true;
                            }
                        }
                        header('Content-type: application/json');
                        if ($data["_useStandardResponse"]) {
                            return json_encode(
                                (new StandardResponse($end - $start,
                                    $httpCode,
                                    $data,
                                    false,
                                    NULL,
                                    CoreX::$developmentMessages)
                                )->expose());
                        } else {
                            return json_encode($data);
                        }
                    } else {
                        // Return RAW if it is not safely serializable.
                        return $data;
                    }
                }

            } else {
                throw new ResponseException("Not found.", 404);
            }
        }

        /**
         * CoreX constructor.
         * @param BaseConfig|string|null $baseConfigChildInstanceOrLauncherFolderPath
         * @throws Exception
         */
        function __construct($baseConfigChildInstanceOrLauncherFolderPath = null)
        {
            if (CoreX::$instance != null) {
                throw new \Exception("CoreX is already instantiated, cannot instantiate twice. Please check.");
            }
            CoreX::$instance = $this;
            if ($baseConfigChildInstanceOrLauncherFolderPath == null) {
                throw new \Exception('CoreX first parameter must be either a string containing the launcher ' .
                    'folder path or an instantiated BaseConfig child class. Example: new \ExEngine\CoreX(__DIR__);');
            }
            if ($baseConfigChildInstanceOrLauncherFolderPath instanceof BaseConfig) {
                $this->config = &$baseConfigChildInstanceOrLauncherFolderPath;
            } else {
                if (is_string($baseConfigChildInstanceOrLauncherFolderPath) && file_exists($baseConfigChildInstanceOrLauncherFolderPath) && is_dir($baseConfigChildInstanceOrLauncherFolderPath)) {
                    $this->config = new DefaultConfig($baseConfigChildInstanceOrLauncherFolderPath);
                } else {
                    throw new \Exception("If default config is being used, you must pass the launcher folder path. Example: new \ExEngine\CoreX(__DIR__);");
                }
            }
            if ($this->config->isShowHeaderBanner()) {
                if (!$this->config->isProduction()) {
                    header("Y-Powered-By: ExEngine - Development Mode");
                } else {
                    header("Y-Powered-By: ExEngine");
                }
            }
            if (strlen($this->config->getLauncherFolderPath()) == 0) {
                throw new \Exception("Launcher folder path must be passed in the Config constructor. If overriden, parent constructor must be called.");
            } else {
                if (!file_exists($this->config->getLauncherFolderPath()) || !is_dir($this->config->getLauncherFolderPath())) {
                    throw new \Exception("Launcher folder path is invalid or does not exists. Please use PHP's constant `__DIR__` from the instance launcher.");
                }
            }

            try {
                print $this->processArguments();
            } catch (\Throwable $exception) {
                $trace = $this->getConfig()->isShowStackTrace() ? $exception->getTrace() : null;
                $resp = new StandardResponse(0, $exception->getCode(), null, true, new ErrorDetail($trace, $exception->getMessage()));
                http_response_code($exception->getCode());
                header('Content-type: application/json');
                print json_encode($resp->expose(), $this->usePrettyPrint());
            }
        }
    }
}

namespace {
    /**
     * Global shortcut for \ExEngine\CoreX::getInstance();
     *
     * @return \ExEngine\CoreX
     */
    function ee()
    {
        return \ExEngine\CoreX::getInstance();
    }
}