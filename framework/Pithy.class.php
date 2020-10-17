<?php
// +----------------------------------------------------------------------
// | PithyPHP [ 精练PHP ]
// +----------------------------------------------------------------------
// | Copyright (c) 2010 http://pithy.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: jenvan <jenvan@pithy.cn>
// +----------------------------------------------------------------------

// 如果 Pithy 已经运行，则返回
if (defined("PITHY")) return;

define("PITHY", true);
define("PITHY_VERSION", "0.50");

// 定义执行起始时间    
define("PITHY_TIME", microtime(true));

// 定义随机值
define("PITHY_RANDOM", "R_".PITHY_TIME."_".mt_rand()); 
    
// 定义运行模式： lite | extend | custom | cli | mvc
defined("PITHY_MODE") || define("PITHY_MODE", "lite");

// 定义系统相关常量
define("IS_WIN", stristr(PHP_OS, "WIN") ? 1 : 0);
define("IS_CGI", substr(PHP_SAPI, 0, 3) == "cgi" ? 1 : 0);
define("IS_CLI", PHP_SAPI == "cli"  ? 1 : 0); 


// 精简模式只需包含此文件即可执行
PITHY_MODE == "lite" && Pithy::run();


// 核心类
class Pithy{
   
    static public $terminator = null;
    static public $inputer = null;
    static public $outputer = null; 

    static private $_alias = array();
    static private $_object = array();
    
    
    /**
     * 运行
     * 
     * @param mixed $config 配置数组或配置文件路径
     */
    static public function run($config=array()){

        self::benchmark("start");

        // 初始化
        self::init($config);
        
        // lite 模式
        if (PITHY_MODE == "lite"){
              
        }        
        
        // extend 模式
        if (PITHY_MODE == "extend"){
            // 导入扩展库目录
            self::import("#.libs.*");     
        }
        else{
            // 设置异常和错误接口
            set_exception_handler(array(__CLASS__, "exception"));
            set_error_handler(array(__CLASS__, "error"));   
        }

        // cli mvc rest 模式
        if (in_array(PITHY_MODE, array("custom", "cli", "mvc"))){ 
            
            if (PITHY_MODE == "mvc"){
                // 使用 ob 控制                
                ob_start();
                
                // Session 初始化
                if (!headers_sent())
                    session_start();
            } 
            
            // 执行后续操作
            self::exec();     
        }
        
        self::benchmark("end");
    }
     
    /**
     * 初始化
     * 
     * @param mixed $config 配置数组或配置文件路径
     */
    static protected function init($config=array()){ 
        
        // 框架目录
        define("PITHY_SYSTEM", dirname(__FILE__).DIRECTORY_SEPARATOR);
        
        // 网站目录
        define("PITHY_HOME", $_SERVER["DOCUMENT_ROOT"].DIRECTORY_SEPARATOR);
        
        // 应用目录        
        !defined("PITHY_APPLICATION") && PITHY_MODE == "lite" && define("PITHY_APPLICATION", PITHY_HOME);
        !defined("PITHY_APPLICATION") && trigger_error("PITHY_APPLICATION undefined !", E_USER_ERROR);

        // 加载配置
        if (false === ($default = @include(PITHY_SYSTEM."Pithy.config.php"))){
            trigger_error("Config file not found! [default]", E_USER_ERROR);
        }
        if (is_string($config) && !empty($config) && false === ($config = @include($config))){
            trigger_error("Config file not found! [defined]", E_USER_ERROR);    
        }         
        self::config(self::merge($default, $config), true);          
        if (count(self::config()) == 0)
            trigger_error("Config error!", E_USER_ERROR);                             
        
        // 设置系统时区
        if (function_exists("date_default_timezone_set"))
            date_default_timezone_set(self::config("App.Timezone"));
        
        // 定义调试状态
        defined("PITHY_DEBUG") || define("PITHY_DEBUG", self::config("App.Debug"));

        // 预定义常量
        $arr = self::config("App.Define");
        if (is_array($arr) && !empty($arr)){
            foreach($arr as $k => $v)
                defined($k) || define($k, $v);
        }
        defined("PITHY_PATH_RUNTIME") || define("PITHY_PATH_RUNTIME", sys_get_temp_dir());
        defined("PITHY_PATH_CONFIG")  || define("PITHY_PATH_CONFIG", PITHY_APPLICATION."config".DIRECTORY_SEPARATOR);

        // 预加载类         
        $arr = self::config("App.Preload");
        if (is_array($arr) && !empty($arr)){
            foreach($arr as $k => $v){
                if (!self::import($v["path"]))     
                    trigger_error("Preload class not exists !", E_USER_ERROR); 
                self::$_object[$k] = $v;   
            }                
        }                 
        
        // 类自动加载功能设置
        if (self::config("App.Autoload.Enable") && function_exists("spl_autoload_register")){
            
            // 将本类的autoload方法放在最前面
            $arr = spl_autoload_functions(); 
            $arr = empty($arr) ? array() : $arr;

            $funcs = $arr;
            foreach($funcs as $func){
                spl_autoload_unregister($func);    
            }

            array_unshift($arr, array(__CLASS__, "autoload")); 

            $funcs = $arr;
            foreach($funcs as $func){
                spl_autoload_register($func);    
            }             
            
            // 设置文件自动加载路径
            $paths = self::config("App.Autoload.Path");
            if (empty($paths))
                $paths = array();
            elseif (is_string($paths))
                $paths = explode(",", $paths);
            $paths = array_unique(array_merge(explode(",", "#.*"), $paths));             
            
            foreach ($paths as $path){
                self::import($path);
            }
        }    

        
        // 非调试状态下结束检查
        if (PITHY_DEBUG != true)
            return;


        // 检查PHP版本及PHP相关的必须信息
        if (version_compare(PHP_VERSION, "5.0.0", "<")){
            trigger_error("PHP version must >5.0 !", E_USER_ERROR);
        }
        
    }

    /**
     * 执行高级模式
     * 
     */
    static protected function exec(){

        if (!in_array(PITHY_MODE, array("custom", "cli", "mvc")))
            return;

        // 加载基础核心配置        
        if (false === ($config = @include(PITHY_SYSTEM."base/PithyBase.config.php"))){
            trigger_error("Config file not found! [PithyBase]", E_USER_ERROR);    
        }         
        self::config(self::merge($config, self::config()), true);  
            
        // 导入基础核心目录
        self::import("#.base.*");     

        // 导入应用目录 
        self::import("~.extend.*");
        
        
        /* custom 模式 */ 
        if (PITHY_MODE == "custom"){
            return;
        }
        
        
        /* cli 模式 */ 
        if (PITHY_MODE == "cli"){
            Command::singleton()->call();
            return;
        }
        
        
        /* mvc 模式 按下列流程顺序执行 */

        /***********************************/ 
        // 钩子 
        /***********************************/ 
        Pithy::benchmark("hook");
        
        // 实例化钩子类
        $hook = Hook::singleton();     

        // 初始钩子：可以挂载 日志处理、实例化外部扩展类 等优先级高的操作
        $hook->call("init");


        /***********************************/ 
        // 路由 
        /***********************************/ 
        Pithy::benchmark("router"); 

        // 实例化路由类
        $router = Router::singleton();
        
        // 判断并加载分组的扩展
        if ($router->group != ""){
            Pithy::import("~.@".$router->group.".extend.*");
        }
        

        /***********************************/ 
        // 缓存输出
        /***********************************/ 
        Pithy::benchmark("cache");

        // 缓存构造钩子：可以替换系统自带的缓存类
        if ($hook->call("cache_init") == true){
            Pithy::$outputer = Output::singleton();
        }

        // 缓存显示钩子：可以替换系统自带的缓存输出
        if ($hook->call("cache_display") == true){
            $rtn = Pithy::$outputer->cacheDisplay();    
        }                                      

        // 本次请求如果是输出缓存内容，则退出
        if (isset($rtn) && $rtn)
            return; 


        /***********************************/ 
        // 输入过滤 
        /***********************************/   
        Pithy::benchmark("input");

        // 输入构造钩子：可以替换系统自带的输入类
        if ($hook->call("input_init") == true){
            Pithy::$inputer = Input::singleton();
        }

        // 输入过滤器钩子：可以挂载 参数过滤 、编码转换、安全检测 操作
        if ($hook->call("input_filter") == true){
            Pithy::$inputer->filter();     
        }                                


        /***********************************/ 
        // 控制器 
        /***********************************/ 
        Pithy::benchmark("controller"); 

        // 控制器钩子：此处可以挂载 权限验证 操作
        $hook->call("controller");
                           
        // 实例化控制器并执行动作 
        Controller::factory($router->controller)->run();


        /***********************************/ 
        // 输出（输出过滤、构造缓存、输出显示） 
        /***********************************/ 
        Pithy::benchmark("output"); 

        // 输出构造钩子：可以替换系统自带的输出类
        if ($hook->call("output_init")){
            Pithy::$outputer = Output::singleton();
        }

        // 输出过滤器钩子： 输出控制、格式化等
        if ($hook->call("output_filter")){
            Pithy::$outputer->filter();    
        }

        // 缓存文件构造钩子：可以替换系统自带的缓存构造
        if ($hook->call("output_cache")){
            Pithy::$outputer->cacheBuild();    
        }

        // 输出显示替换钩子：此处可以替换系统自带的输出类显示
        if ($hook->call("output_display")){
            Pithy::$outputer->display();   
        } 
                                       
    }                                                       



    /*********************************************************/
    /************************ 类工具 *************************/  
    /*********************************************************/    
            
    /**
     * 导入类文件（路径）
     * 采用命名空间的方式导入，实际只是把路径缓存起来，实例化的时候才通过 autoload 真正载入
     * 例：不带扩展名可以用 . 隔开 import('#.web.pager') import('#.top.*') import('~.model.user') 
     * 例：如带扩展名必须用 / 隔开 import('#/test.php') import(PITHY_HOME.'/test.php')
     * 
     * @param mixed $name 导入路径
     * @return bool 是否导入成功
     */
    static public function import($name){
        
        if (empty($name)) return false;

        static $data = array();        
        
        // 首先判断是否已经导入过，导入过则返回之前的判断结果       
        if (!PITHY_DEBUG && is_array($data) && isset($data[$name])){
            return $data[$name];
        }                       
        
        // 批量导入
        if (substr($name, -2) == ".*"){
            
            $status = false;
            $folder = substr($name, 0, -1);
            if (self::exists($folder) && false !== ($handle = opendir($folder))){
                while(false !== ($file = readdir($handle))){
                    if (substr($file, -4) == ".php" && is_file($folder.$file)){ 
                        self::import($folder.$file);
                    }
                }
                closedir($handle);
                $status = true;
            }  
            
            // 保存到缓存中
            $data[$name] = $status;             
            
            return $status;
        }

        // 获取单个文件的路径和类名
        $filepath = $name;
        if (in_array($name[0], array("#","~")) && isset($name[1]) && $name[1] == "."){                                  
            $name = strstr($name, "@") == "" ? $name : preg_replace("/(.{2})([^@]*)@(.*)/", "$1@$3.$2", $name);
            $filepath = str_replace(".", "/", $name).".class.php";                     
        }
        $class = basename(basename($filepath, ".php"), ".class");
          
        // 判断文件是否存在，并暂存数据
        $exists = $data[$name] = self::exists($filepath);
        if (isset(self::$_alias[$class]) && self::$_alias[$class] != $filepath)
            return trigger_error("Class [$class] is already defined! ", E_USER_ERROR);
        
        $exists && self::$_alias[$class] = $filepath;

        return $exists;
    }
    
    /**
    *  载入预加载的对象
    *  
    * @param string $name 对象名
    * @param boolean $force 是否强制显示出错信息
    * @return 返回载入成功的类或者显示出错信息
    */
    static public function load($name, $force = true){
        if (!isset(self::$_object[$name])){
            $force == true && trigger_error("Object [$name] not preload! ", E_USER_ERROR);
            return null;
        }
        if (!isset(self::$_object[$name]["instance"])){
            self::$_object[$name]["instance"] = self::instance(self::$_object[$name]["class"], self::$_object[$name]["params"], true);       
        }   
        return self::$_object[$name]["instance"];         
    }         
    
    /**
     * 自动载入类文件
     * 
     * @param mixed $class 类名
     */
    static public function autoload($class){
        
        if (self::config("App.Autoload.Enable") && isset($class[0]) && isset(self::$_alias[$class]) && false !== include(self::$_alias[$class]))
            return;
        
        if (PITHY_MODE == "extend")
            return;
        
        trigger_error("Class ($class) is not defined!", E_USER_ERROR);          
    }      

    /**
     * 获取指定类的实体(即实例化的对象）
     * 
     * @param mixed $class 类目
     * @param mixed $args  参数
     * @param mixed $singleton 是否单体
     * @return object 实例化的对象
     */
    static public function instance($class, $args=array(), $singleton=true){
        
        static $data = array();

        $singleton = (!is_array($args) && is_null($args)) ? (boolean) $args : $singleton;

        if ($singleton && isset($data[$class]))
            return $data[$class];

        if (!class_exists($class))
            return trigger_error("Class ($class) is not defined!", E_USER_ERROR);

        if (!is_array($args) || empty($args)){
            $object = new $class();   
        }
        else{
            $keys = array_keys($args);
            $params = '$args["'.(count($keys) > 1 ? implode('"],$args["',$keys) : $keys[0]).'"]';
            eval('$object = new $class('.$params.');');

            /*
            // Note: ReflectionClass::newInstanceArgs() is available for PHP 5.1.3+
            $_class = new ReflectionClass($class);
            $object = $_class->newInstanceArgs($args);
            $object = call_user_func_array(array($_class, "newInstance"),$args);
            */
        }
        $singleton && $data[$class] = $object;

        return $object;
    } 
    
    /**
     * 远程调用类或对象的方法
     * 
     * @param mixed $object 对象或类
     * @param mixed $methodName 方法名
     * @param mixed $params 参数
     * @param mixed $vars 类属性
     * @return mixed
     */
    static public function call($object, $methodName, &$params=null, $vars=null){
        
        if (!method_exists($object, $methodName))
            trigger_error(get_class($object)."::{$methodName} : Method not exists!", E_USER_ERROR);
        
        if (!is_object($object))
            $object = self::instance($object);

        $class = new ReflectionClass($object);
        if (!empty($vars) && is_array($vars)){    
            foreach($vars as $name => $value){
                if ($class->hasProperty($name)){
                    $property = $class->getProperty($name);
                    if ($property->isPublic() && !$property->isStatic()){
                        $object->$name = $value;
                        unset($vars[$name]);
                    }
                }
            }
            if (!empty($vars))
                trigger_error(get_class($object)." : Unknown property ".implode(', ',array_keys($vars))." !", E_USER_ERROR);    
        }

        $method = new ReflectionMethod($object, $methodName);
        if ($method->getNumberOfParameters() <= 0)
            return $object->$methodName();

        $args = array();
        foreach($method->getParameters() as $i=>$param){
            $name = $param->getName();
            if (is_array($params) && isset($params[$name])){
                if ($param->isArray())
                    $args[$name] = is_array($params[$name]) ? $params[$name] : array($params[$name]);
                elseif (!is_array($params[$name]))
                    $args[$name] = $params[$name];
                else
                    trigger_error(get_class($object)."::{$methodName} : Getted paramters [{$name}] is error!", E_USER_ERROR);
            }
            elseif ($param->isDefaultValueAvailable())
                $args[$name] = $param->getDefaultValue();
            else
                trigger_error(get_class($object)."::{$methodName} : Getted paramters [{$name}] is missing!", E_USER_ERROR);
        }
        $params = self::merge($params, $args);
        return $method->invokeArgs($object, $args);
    }

              
    /*********************************************************/
    /************************ 调试工具 ***********************/  
    /*********************************************************/     

    // 指标统计 
    static public function benchmark($tag=null){
        static $data = array();
        
        if (is_null($tag))
            return $data;   
            
        $args = func_get_args(); 
        if (count($args) == 3){
            if (!isset($data[$args[0]], $data[$args[1]], $data[$args[0]][$args[2]], $data[$args[1]][$args[2]]))
                return 0;
            return abs($data[$args[0]][$args[2]] - $data[$args[1]][$args[2]]);
        }
            
        $data[$tag] = array("time" => microtime(true), "memory" => memory_get_usage());
    }   

    // 累计计数
    static public function count($key, $step=1){
        
        static $data = array();
        
        if (!isset($data[$key])) {
            $data[$key] = 0;
        }
        if (empty($step))
            return $data[$key];
        else
            $data[$key] = $data[$key] + (int)$step;
    } 

    // 变量分解
    static public function dump(){

        $params = func_get_args();
   
        $var = $params[0];

        $label = "";
        if (isset($params[1]) && is_string($params[1]))
            $label = $params[1];
        if (isset($params[2]) && is_string($params[2]))
            $label = $params[2];

        $echo = true;    
        if (isset($params[1]) && is_bool($params[1]))
            $echo = $params[1];
        if (isset($params[2]) && is_bool($params[2]))
            $echo = $params[2];


        $output = var_export($var, true);   
        if (IS_CLI || !$echo){
            $label = empty($label) ? $label : $label.PHP_EOL;
            $output = $label.$output;
        }
        else{
            $output = ini_get('html_errors') ? htmlspecialchars($output,ENT_QUOTES) : $output;    
            $output = "<pre>".$output."</pre>";            
            if (!empty($label))
                $output = "<fieldset><legend style='margin-top:10px;padding:5px;font-weight:600;background:#CCC;'> ".$label." </legend>".$output."</fieldset>";
        }

        if ($echo)
            echo $output;

        return $output;
    } 

    // 调试
    static public function debug() {
        
        static $data = array();
        $args = func_get_args();
        if (empty($args))
            return $data;
        
        $str = "";
        foreach ($args as $arg){
            $str .= print_r($arg, true)." ";
        }
        array_push($data, $str);
        
        if (!PITHY_DEBUG)
            return;
        
        if (IS_CLI){
            echo IS_WIN ? mb_convert_encoding($str, "gbk", "utf-8")."\r\n" : $str."\n";
            return;
        }
        
        try {
            $handle = @stream_socket_client("udp://255.255.255.255:9527", $errno, $errstr);
            if (!$handle)
                return;
            fwrite($handle, "\n");
            for ($i = 0; $i < strlen($str); $i = $i + 100){
                fwrite($handle, substr($str, $i, 100));
            }
            fclose($handle);
        }
        catch(Exception $e){}
    } 

    // 跟踪
    static public function trace($msg="", $traces=array()){

        static $data = array();
        
        if (empty($msg))
            return $data;  

        if (empty($traces) || !is_array($traces)){
            if (function_exists("debug_backtrace"))                 
                $traces = debug_backtrace();
            else
                $traces = array();
        }

        if (!empty($traces)){ 
            $msg .= " ".PHP_EOL."-------------------------------".PHP_EOL;                               
            foreach($traces as $t){
                $msg .= "# ";
                if (isset($t["file"]))
                    $msg .= $t["file"]." [".$t["line"]."]  ".PHP_EOL;
                else
                    $msg .= "[PHP inner-code] ".PHP_EOL;
                if (isset($t["class"]))
                    $msg .= $t["class"].$t["type"];
                $msg .= $t["function"]."(";
                if (isset($t["args"]) && sizeof($t["args"]) > 0){
                    $count = 0;
                    foreach($t["args"] as $item){

                        if (is_string($item)){
                            $str = str_replace(array("\r","\n","\r\n"), "", $item);
                            if (strlen($item)>200)
                                $msg .= "'". substr($str, 0, 200) . "...'";
                            else
                                $msg .= "'" . $str . "'";
                        }                               
                        elseif (is_bool($item))
                            $msg .= $item ? "true" : "false";
                        elseif (is_null($item))
                            $msg .= "NULL";
                        elseif (is_numeric($item))
                            $msg .= $item;
                        elseif (is_object($item))
                            $msg .= get_class($item); 
                        elseif (is_resource($item))
                            $msg .= get_resource_type($item);
                        elseif (is_array($item)){
                            if ($count < 3){
                                @array_walk($item, create_function('&$v,$k','if (is_array($v)){ $v = "[ARRAY] ".count($v); } if (is_object($v)){ $v = "[OBJECT] ".get_class($v); } if (is_resource($v)){ $v = "[RESOURCE] ".get_resource_type($v); }'));    
                                $msg .= str_replace(array("\r","\n","\r\n"), "", var_export($item, true));
                            }
                            else
                                $msg .= "array(".count($item).")"; 
                        }

                        $count++;
                        if (count($t["args"]) > $count)
                            $msg .= ", ";
                    }
                }                    
                $msg .= ") ".PHP_EOL;
            }
            $msg .=  ( IS_CLI ? "" : "@ ".date("Y-m-d H:i:s")." | ".$_SERVER["SERVER_ADDR"]." : ".$_SERVER["REMOTE_ADDR"].PHP_EOL);
        }

        array_push($data, (strstr($msg,"\n") ? "\n" : "").$msg);
        count($data) <= 100 || $data = array_slice($data, -100);

        return $msg;
    }

    // 日志记录
    static public function log($message="", $options=null, $force=false){ 

        static $data = array();
        
        // 如果日志内容为空，则表示返回之前记录的所有日志内容
        if (empty($message))
            return $data; 
        
        !is_string($message) && $message = print_r($message, true);
        
        // 支持的日志记录类型
        $types = array(
            "SYSTEM" => 0,
            "MAIL" => 1,
            "TCP" => 2,
            "FILE" => 3,
        );

        // 支持的日志记录级别
        $levels = array(
            "ALERT",
            "ERROR",
            "WARNING",
            "NOTICE",
            "INFO",
            "DEBUG",
        );

        // 默认的日志设置参数
        $config = array(
            "type" => 3,            // 日志记录类型
            "level" => "INFO",      // 日志记录级别
            "destination" => "",    // 日志记录位置  {PITHY_PATH_RUNTIME}/log/Ymd/{$level}.log
            "extra" => "",          // 日志扩展信息（日志记录类型为 MAIL 和 TCP 时使用，参见 error_log 函数)
        );        

        // 参数是布尔时，是否强制调用 debug
        if (is_bool($options)){
            $force = $options;
            $options = null;
        }

        // 合并参数
        !is_array($options) && $options = array($options);
        foreach ($options as $v){
            // 参数是数字时，设置日志记录类型
            if (is_int($v))
                $config["type"] = $v;

            // 参数是字符串时，设置日志记录级别或位置
            if (is_string($v)){
                if (in_array(strtoupper($v), $levels))
                    $config["level"] = strtoupper($v); 
                else
                    $config["destination"] = $v;
            }
        }

        // 设置相关变量            
        $folder = PITHY_PATH_RUNTIME.DIRECTORY_SEPARATOR."log".DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR;
        $now = date("Y-m-d H:i:s");

        // 最终的日志参数
        $type = in_array($config["type"], array_keys($types)) ?  $types[$config["type"]] : $types["FILE"] ;
        $level = in_array(strtoupper($config["level"]), $levels) ? strtoupper($config["level"]) : "INFO" ;
        $destination = empty($config["destination"]) ? $folder."pithy.".strtolower($level).".log" : ((strstr($config["destination"],"/") || strstr($config["destination"],"\\")) ? $config["destination"] : $folder.$config["destination"].".log");   
        $extra = $config["extra"];
        
        
        // 获取 logger
        $logger = self::load("logger", false);

        // 执行内部日志处理程序
        if ((!is_object($logger) || $force) && (PITHY_DEBUG || self::config("App.Log.Level") || in_array($level, self::config("App.Log.Level")))){

            // 拼接最终日志内容(如果已经拼接好，则不需拼接)，并放入全局公共属性中
            $msg = $message;
            !preg_match("/^[\d]{4}\-[\d]{2}\-[\d]{2}/", $msg) && $msg = "{$now} [{$level}] {$msg}";    
            array_push($data, $msg);
            count($data) <= 1000 || array_slice($data, -1000); 

            // 文件类型的日志记录预处理
            if ($type == $types["FILE"]){ 
                if (!is_dir($folder) && (@mkdir($folder, 0777, true) == false || @chmod($folder, 0777) == false)){
                    array_push($data, "$now [ALERT] Can not mkdir($folder)!");
                    return;        
                }
                if (is_file($destination) && floor(self::config("App.Log.Size")) <= filesize($destination)){
                    extract(pathinfo($destination));
                    @rename($destination, $dirname.DIRECTORY_SEPARATOR.$basename."_".time().".".$extension);
                }
            }

            // 调用 php 自带的日志记录函数
            error_log($msg.PHP_EOL, $type, $destination, $extra);
            
            !IS_CLI && $force && strstr($msg, "::debug(") == false && self::debug($msg);
        }

        // 执行外部日志处理程序 (如果定义了外部的日志处理程序并且没有强制使用内部的，则使用外部日志处理程序来处理日志)
        if (is_object($logger) && !$force){
            $args = array(
                "message" => $message,
                "level" => $level,
                "category" => "Pithy.Extend.".basename($destination, ".log"),
            );            
            return call_user_func_array($logger, array($args)); 
        }                                 
    }   

    // 错误处理
    static public function error(){      

        if (4 > func_num_args())
            return;

        $params = func_get_args();

        $errno = $params[0];
        $errstr = $params[1];
        $errfile = $params[2];
        $errline = $params[3];


        // 是否终止执行，并输出错误
        $halt = false;

        // 错误类型
        $type = "error"; 

        // 设置日志类型
        switch ($errno) {
            case E_NOTICE:
            case E_USER_NOTICE:
                $type = "notice";
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $type = "warning";
                break;
            case E_ERROR:
            case E_USER_ERROR:
                $halt = true;
                $type = "error";
                break;
            default:
                $type = "alert";
                break;
        }

        $msg = $err = $errstr;

        // 跟踪错误
        (PITHY_DEBUG || self::config("App.Error.Trace")) && $err = self::trace($err, debug_backtrace()); 

        // 记录错误 
        if (self::config("App.Error.Log"))
            self::log($errfile."(".$errline.") -=> ".$err, array("destination" => basename(basename($errfile, ".php"), ".class").".error", "level" => strtoupper($type)), true);

        // 输出错误
        if ($halt){
            if (!PITHY_DEBUG && !IS_CLI && !self::config("App.Error.Display"))
                $msg = self::config("App.Error.Message");
            return self::halt(PITHY_DEBUG ? $err : $msg);
        }
    } 

    // 异常处理
    static public function exception($e){  

        $e = (array) $e;   

        $trace = array();
        $traces=array();

        $keys = array("message", "code", "file", "line", "trace");
        foreach($e as $k=>$v){
            foreach($keys as $key){
                if (strstr($k, $key) <> ""){
                    if ($key == "trace")
                        //$traces+=$v;
                        $traces = $v;
                    else
                        $trace[$key] = $v;    
                }    
            }                                
        }
        $trace["function"] = "throw new Exception";
        $trace["args"] = array($trace["message"]);
        array_unshift($traces, $trace);
        $traces = array_merge(debug_backtrace(), $traces);

        //self::dump($e);
        //self::dump($traces); 

        $msg = $err = $trace["message"]; 

        // 跟踪错误
        (PITHY_DEBUG || self::config("App.Error.Trace")) && $err = self::trace($err, $traces);

        // 记录错误 
        if (self::config("App.Error.Log"))
            self::log($trace["file"]."(".$trace["line"].") -=> ".$err, array("destination"=> basename(basename($trace["file"], ".php"), ".class").".exception", "level"=>"ALERT"), true);

        // 输出异常
        if (!PITHY_DEBUG && !IS_CLI && !self::config("App.Error.Display"))
            $msg = self::config("App.Error.Message");
        return self::halt(PITHY_DEBUG ? $err : $msg);
    }  


    
    /*********************************************************/
    /************************ 系统工具 ***********************/  
    /*********************************************************/
    
    /**
     * 判断文件(夹)是否存在
     * 支持使用名称空间方式、以及缩写方式
     * 
     * @param string $filepath 文件(夹)路径 地址引用
     * @return bool 是否存在
     */
    static public function exists(&$filepath){
        
        if (empty($filepath)) return false;

        // 将 filepath 转换成真实路径            
        if (in_array($filepath[0], array("#","~")) && (!isset($filepath[1]) || in_array($filepath[1], array(".","/","\\")))){
            if (isset($filepath[1]) && $filepath[1] == ".")
                $filepath = str_replace(".", "/", $filepath);
            $filepath = str_replace(array("#","~"), array(PITHY_SYSTEM,PITHY_APPLICATION), $filepath);
        }
        $filepath = preg_replace(array("/(\\\\+)/", "/(\/+)/"), array("/", "/"), $filepath);
                     
        // 在之前保存的数据缓存中，判断文件是否存在
        static $data = array();        
        if (!PITHY_DEBUG && is_array($data) && isset($data[$filepath])){
            return $data[$filepath];
        }

        // 判断文件是否真实存在，并将结果缓存起来
        $rtn = $data[$filepath] = file_exists($filepath);

        return $rtn; 
    }     
    
    /**                          
     * 合并数组
     * 将 第二个 数组中的单元合并至 第一个 数组中，存在则覆盖，不存在则新增
     * 
     * @param mixed $a
     * @param mixed $b
     * @return mixed
     */
    static public function merge($a, $b){
        foreach($b as $k => $v){
            if (isset($a[$k])){
                if (is_scalar($v) && is_scalar($a[$k])){
                    is_integer($k) ? $a[] = $v : $a[$k] = $v;                    
                }    
                elseif (is_array($v) && is_array($a[$k])){
                    $a[$k] = self::merge($a[$k], $v);    
                }  
            } 
            else{
                $a[$k] = $v;    
            }                
        }
        return $a; 
    }
        
    /** 
     * 配置或获取参数
     * 支持批量赋值，支持 . 连接符获取和设置多级配置，例如： App.Error.Display
     * 第二参数使用默认值表示获取，否则表示设置
     * 第一参数为数组时表示批量设置
     * 
     * @param mixed $name
     * @param mixed $value
     */
    static public function config($name=null, $value=PITHY_RANDOM){ 
    
        static $data = array();             

        // 无参数时获取所有
        if (empty($name)) 
            return $data; 

        // 批量设置
        if (is_array($name))
            return $data = $value === true ? $name : self::merge($data, $name);
    
        // 执行设置获取或赋值，支持 . 操作 
        if (is_string($name)){                
            
            $arr = explode('.', $name);
            
            if (count($arr) == 1){
                if (PITHY_RANDOM === $value)
                    return isset($data[$name]) ? $data[$name] : null;
                return $data[$name] = $value;    
            }
             
            $key = array_pop($arr);
            $str = implode(".", $arr); 
            if (PITHY_RANDOM === $value){
                $result = self::config($str);
                return !empty($result) && isset($result[$key]) ? $result[$key] : null;
            }
            return self::config($str, array($key => $value));        
        
        }
           
        return null;           
    }  

    /**
     * 缓存处理(服务端简单数据暂存）
     * 第二参数使用默认值表示获取，否则表示设置
     * 第一参数为数组时表示批量设置
     *        
     * @param mixed $key 缓存键
     * @param mixed $value 缓存值，为默认值时表示获取、为 null 时表示删除
     * @return mixed  执行操作的状态
     */
    static public function cache($key, $value=PITHY_RANDOM){
        
        // 缓存文件路径
        $folder = PITHY_PATH_RUNTIME.DIRECTORY_SEPARATOR."cache_".substr(md5(PITHY_APPLICATION), 8, 8);
        $filename = $folder.DIRECTORY_SEPARATOR.$key.".php";  
        
        // 获取缓存（如果 $value 为默认值）
        if (PITHY_RANDOM === $value)
            return is_file($filename) ? @include($filename) : null ;

        // 设置缓存
        if (!is_null($value)){
            @mkdir($folder, 0755, true);
            return @file_put_contents($filename, "<?php".PHP_EOL."return ".var_export($value, true).";".PHP_EOL."?>");
        }
        
        // 删除缓存 
        return @unlink($filename);       
    }

    /**
     * 会话处理（客户端简单数据暂存）
     * 支持批量赋值，支持 . 连接符操作
     * 第二参数使用默认值表示获取，否则表示设置
     * 第二参数为数组时表示批量设置
     * 
     * @param mixed $name
     * @param mixed $value
     * @param mixed $option
     * @param mixed $init
     */
    static public function cookie($name, $value=PITHY_RANDOM, $option="", $init=true){

        // 默认设置
        $config = array(
            'prefix' => self::config('App.Cookie.Prefix'), // cookie 名称前缀
            'expire' => self::config('App.Cookie.Expire'), // cookie 保存时间
            'path'   => self::config('App.Cookie.Path'),   // cookie 保存路径
            'domain' => self::config('App.Cookie.Domain'), // cookie 有效域名
        );
        // 参数设置(会覆盖黙认设置)
        if (!empty($option)) {
            if (is_numeric($option))
                $option = array('expire'=>$option);
            elseif (is_string($option))
                parse_str($option,  $option);
            $config = self::merge($config, array_change_key_case($option));
        }
        
        $domain = preg_replace("(:\d+)", "", strtolower($_SERVER["HTTP_HOST"]));
        list($a, $b) = explode(".", strrev($domain));

        $prefix =  !empty($config["prefix"]) ? $config["prefix"] : str_replace(".", "_", $domain);
        $expire = is_null($value) ? time()-3600 : (intval($config["expire"]) > 0 ? time() + intval($config["expire"]) : 0);
        $path = !empty($config["path"]) ? $config["path"] : "/";
        
        (!preg_match("/[\d]+\.[\d]+\./", $domain) && strstr($domain, ":") == false) && $domain = strrev("$a.$b");
        !empty($config["domain"]) && $domain = $config["domain"];

        // 获取 cookie
        if (!is_null($value) && $value === PITHY_RANDOM){
            if (strstr($name,".") <> ""){
                $key = "_COOKIE['".$prefix."']['".str_replace(".","']['",$name)."']";
                $key_root = "_COOKIE['".$prefix."']";
                $key_parent = substr($key, 0, strrpos($key,"["));
                eval("$"."_cookie=isset($".$key_root.",$".$key_parent.",$".$key.")?$".$key.":null;");    
            }
            else{            
                $_cookie = isset($_COOKIE[$prefix],$_COOKIE[$prefix][$name]) ? $_COOKIE[$prefix][$name] : null;
            }
            return $_cookie;        
        }


        // 初始化，删除 cookie 、整理 value
        if ($init){ 

            // 删除 cookie
            if (is_null($value)){

                $_cookie = self::cookie($name);
                //echo "<xmp>".print_r($_cookie,true)."</xmp>";

                // 值为 null 的 cookie 直接返回
                if (!is_null($_cookie)){ 

                    // 值为非数组型的 cookie 直接赋值（删除）；否则进行整理（数组型 cookie 无法一次删除，需将所有 value 设置成 null），然后通过赋值的方式删除            
                    if (!is_array($_cookie)){
                        self::cookie($name, null, null, false);                    
                    }
                    else{
                        array_walk_recursive($_cookie, create_function('&$v,$k','$v=null;'));                        
                        self::cookie($name, $_cookie, null, false);                    
                    }                
                }

                return;
            }        

            // 整理 value            
            $root = strstr($name,".") <> "" ? substr($name, 0, strpos($name,".")) : $name; // 根节点
            if ($root <> $name){
                $$root = array();
                $key = $root."['".str_replace(".","']['",substr(strstr($name,"."),1))."']";
                eval("$".$key."=$"."value;");                                
                $value = $$root;
            }
            //echo "<xmp>".print_r($value,true)."</xmp>";
            self::cookie($root, $value, $option, false);            
            return;
        }

        //echo "<xmp>[$name]\r\n\r\ncookie = ".print_r(self::cookie($name),true)."</xmp><xmp>value = ".print_r($value,true)."</xmp>")


        // 设置 cookie

        // 如果需要赋值的变量为数组，则将数组分解分别赋值
        if (is_array($value)){
            foreach($value as $k=>$v){                
                self::cookie($name.".".$k, $v, $option, false);                
            }
            return;
        }

        // 如果之前的 cookie 为数组，则先清空再赋值
        if (is_array(self::cookie($name))){
            self::cookie($name, null, null, true);
        }            

        // 设置新 cookie
        $rtn = setcookie($prefix."[".str_replace(".","][",$name)."]", $value, $expire, $path, $domain); 
        //echo $rtn;

        // 设置 $_COOKIE 变量
        if (!isset($_COOKIE[$prefix])){
            $_COOKIE[$prefix] = array();    
        }
        if (strstr($name, ".") == ""){
            // 根节点赋值 
            if (is_null($value)){
                unset($_COOKIE[$prefix][$name]);
            }
            else{
                $_COOKIE[$prefix][$name] = $value;
            }
        }
        else{
            // 子节点赋值
            $key = "_COOKIE['".$prefix."']['".str_replace(".","']['",$name)."']";  
            $key_parent = substr($key,0,strrpos($key,"["));
            $name_parent = substr($name,0,strrpos($name,"."));
            //echo "$key -> $key_parent ->$name_parent";
            if (is_null($value)){                    
                eval("if(is_array($".$key_parent.")){unset($".$key.");};");
                eval("if(empty($".$key_parent.")){self::cookie('$name_parent',null,null,false);}");
            }
            else{                
                eval("$".$key_parent."=isset($".$key_parent.") && is_array($".$key_parent.")?$".$key_parent.":array();");
                eval("$".$key."='".$value."';");
            }                
        }
    }          
              
    /**
     * 程序终止执行并输出错误消息
     * 
     * @param string $msg
     */
    static public function halt($msg){ 
    
        // 如果不是字符串则转换
        if (!is_string($msg)){
            $msg = self::dump($msg, false);
        }

        // 显示要输出的内容
        if (!IS_CLI){

            !headers_sent() && header("Content-type: text/html; charset=utf-8");

            if (PITHY_DEBUG){
                $msg = preg_replace("/".PHP_EOL."(#|@)/", PHP_EOL."<b style='color:#33F;'>$1</b>", $msg);
                $msg = "<h1 style='color:#F33;'>".preg_replace("/".PHP_EOL."/", "</h1><pre>", $msg, 1)."</pre>";
                $msg = strstr($msg, "<pre>") <> "" ? $msg : "<pre>".$msg."</pre>";
                $msg = "<div title='双击关闭' ondblclick='this.style.display=\"none\"' style='position:fixed;top:10%;left:10%;width:78%;height:78%;padding:1%;background:#000;border-radius:10px;color:#999;font-size:14px;font-weight:400;line-height:24px;opacity:0.8;overflow:auto;'>".$msg."</div>";  
            }

            if (!is_null(Pithy::$terminator))
                return call_user_func(Pithy::$terminator, $msg); 
        }
        
        if (IS_CLI && IS_WIN)
            $msg = mb_convert_encoding($msg, "gbk", "utf-8")."\r\n\r\n";

        echo $msg;
        exit;
    }

    /**
     * 页面重定向
     * 
     * @param mixed $url  网址
     * @param mixed $time 等待时间(秒，支持自然数和1以内的小数)
     * @param mixed $msg  提示信息
     */
    static public function redirect($url, $time=0, $msg=''){
        
        // 多行URL地址支持
        $url = str_replace(array("\n", "\r"), '', $url);
        if (empty($msg))
            $msg = "系统将在 {$time} 秒之后自动跳转到：<a href='{$url}'>{$url}</a>"; 

        if (!headers_sent()) {
            if (0 === $time) {
                header("Location: {$url}");
            }
            else {
                header("refresh:{$time};url={$url}");
                echo($msg);
            }                
        }
        else{                                      

            if ($time > 0 and $time < 1){
                $str = "<script language='javascript'>setTimeout(function(){self.location='$url';},".($time*1000).")</script>";
            }
            else{
                $str = "<meta http-equiv='Refresh' content='{$time};URL={$url}'>";
            }

            if ($time != 0)
                $str .= $msg;

            echo($str);
        } 

        exit;            
    }

    /**
     * 执行系统命令
     *  
     * @param mixed $bin    命令行
     * @param mixed $args   参数
     * @param mixed $asyn   是否异步执行
     * @return int 是否执行成功
     */
    static public function execute($bin, $args=array(), $asyn=true){  
        
        if (self::config("App.Execute.Enable") != true)
            return false;
            
        $args = is_string($args) ? array($args) : $args;
            
        if (self::config("App.Execute.SafeMode") == true){
            
            $list = self::config("App.Execute.Alias");
            if (!is_array($list) || !isset($list[$bin]))
                return false;
            
            // 检查参数中是否包含非法字符
            if (array_reduce(array_values($args), create_function('$r, $v', 'return $r || preg_match("/(#|&|\<|\>|\|)/", $v);'), false))
                return false;            
            
            // 替换参数变量
            if (!empty($args))    
                array_walk($args, create_function('&$v, $k, $str', '$str = str_replace("{".$k."}", $v, $str);'), $list[$bin]);    
        
            $cmd = $list[$bin];
        }
        else{
            $cmd = $bin." ".implode(" ", array_values($args));
        } 
        
        $log = IS_WIN ? "nul" : "/dev/null";
        !PITHY_DEBUG && $cmd = "{$cmd} 1>>{$log} 2>&1";
        self::log($cmd, "execute_".basename($bin));
        
        if (!$asyn){
            passthru($cmd, $rtn);
            return !$rtn;
        }
        $cmd = IS_WIN ? 'start cmd /c "'.$cmd.'" ' : "nohup ".$cmd." &";
        return !pclose(popen($cmd, "r"));
    }    

}