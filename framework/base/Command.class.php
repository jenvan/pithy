<?php
// +----------------------------------------------------------------------
// | PithyPHP [ 精练PHP ]
// +----------------------------------------------------------------------
// | Copyright (c) 2010 http://pithy.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0)
// +----------------------------------------------------------------------
// | Author: jenvan <jenvan@pithy.cn>
// +----------------------------------------------------------------------

/**
 +------------------------------------------------------------------------------
 * PithyPHP 命令行基类
 +------------------------------------------------------------------------------
 * @category   Pithy
 * @package  Pithy
 * @subpackage  Core
 * @author Jenvan <jenvan@pithy.cn>
 * @version  $Id$
 +------------------------------------------------------------------------------
 */

@ini_set("memory_limit", "512M");

// 日志处理
$GLOBALS["pithy_log_file"] = "cli";
$GLOBALS["pithy_log_content"] = array();
register_shutdown_function("log2file");
function log2file(){
    if (!is_array($GLOBALS["pithy_log_content"]) || empty($GLOBALS["pithy_log_content"]))
        return;
    $msg = implode("\r\n", $GLOBALS["pithy_log_content"]);
    $GLOBALS["pithy_log_content"] = array();
    Pithy::log($msg, array("destination" => $GLOBALS["pithy_log_file"], "level" => "NOTICE"));
}

// 基类
class Command extends PithyBase {
    
    // 是否强制输出（调试时在屏幕打印输出所有信息，即命令行参数带上 -force ）
    public $force = false;
    
    // 是否每个方法记录到一个独立的日志文件中
    public $logAlone = false;

    // 指定日志文件名称
    public $logName = "";
    
    // 当前执行的 group
    protected $_group = "";

    // 当前执行的 module
    protected $_module = "";

    // 当前执行的 action
    protected $_action = "";

    // 当前执行的 params
    protected $_params = "";

    public function getGroup(){
        return $this->_group;
    }

    public function getModule(){
        return $this->_module;
    }

    public function getAction(){
        return $this->_action;
    }

    public function getParams(){
        return $this->_params;
    }

    public function getCommand(){
        return $this->module.(empty($this->group) ? "" : "@".$this->group);
    }


    
    /**
     * 初始化
     *
     */
    public function initialize() {
        // 设置 Pithy 的异常处理方法
        is_null(Pithy::$terminator) && Pithy::$terminator = array($this, "exception");

        // 子控制器是否存在预加载方法
        method_exists($this, "_init") && call_user_func(array($this, "_init"));
    }
    
    /**
     * 异常
     *
     * @param string $msg 异常信息
     * @return mixed
     */
    public function exception($msg){
         
         // 子类中是否存在 _exception 或 _error 方法，存在则调用
        if (method_exists($this, "_exception"))
            return $this->_exception($msg); 
        if (method_exists($this, "_error"))
            return $this->_error($msg); 

        return $this->halt($msg);
    }

    /**
     * 实例化指定的命令行
     *
     * @param string $name 命令行名称
     * @return object command
     *
     */
    static public function factory($name){

        $root = PITHY_APPLICATION;
        list($name, $group) = explode("@", $name."@");
        if (!empty($group)) {
            $root .= "@{$group}/";
            $config = @include(PITHY_APPLICATION."@".$group."/config.php");
            $config && Pithy::config(Pithy::merge(Pithy::config(), $config), true);
            Pithy::import("~.@".$group.".extend.*");
        }
        
        $class = ucfirst(strtolower($name))."Command";
        $args = array();

        $map = Pithy::config("Command.Map");
        if (empty($map) || !isset($map[$name])){
            $path = $class;
        }
        elseif (is_array($map[$name])){
            $path = $map[$name]["path"];
            unset($map[$name]["path"]);
            $args = $map[$name];
        }
        else{
            $path = $map[$name];
        }
        if (strstr($path, "/") == "" || strstr($path, "\\") == ""){
            $path = $root."command/".$path.".class.php";
        }
        
        $exists = Pithy::import($path);
        if (!$exists){
            Command::singleton()->exception("命令行 {$name} 不存在！");
        }
        
        $object = Pithy::instance($class, $args);
        if (!is_object($object) || !is_subclass_of($object, "Command")){
            Command::singleton()->exception("命令行 {$class} 类型出错！");
        }

        foreach (array("_group" => $group, "_module" => strtolower($name)) as $key => $val) {
            //$object->$key = $val;
            $rp = new ReflectionProperty($object, $key);
            if ($rp->isProtected()) {
                $rp->setAccessible(true);
                $rp->setValue($object, $val);
            }
        }
        
        return $object;
    }
    
    /**
     * 解析命令行参数
     * 
     * @param mixed $args
     * @return mixed array(params, vars);
     */
    final public function parse($args){
        $params = $vars = array();
        
        foreach($args as $arg){
            if (preg_match('/^--(\w+)(=(.*))?$/', $arg, $matches)){
                $name = $matches[1];
                $value = isset($matches[3]) ? $matches[3] : true;
                if (isset($params[$name])){
                    !is_array($params[$name]) || $params[$name] = array($params[$name]);
                    $params[$name][] = $value;
                }
                else
                    $params[$name] = $value;
            }
            if (preg_match('/^-(\w+)(=(.*))?$/', $arg, $matches)){
                $name = $matches[1];
                $value = isset($matches[3]) ? $matches[3] : true;
                if (isset($vars[$name])){
                    !is_array($vars[$name]) || $vars[$name] = array($vars[$name]);
                    $vars[$name][] = $value;
                }
                else
                    $vars[$name] = $value;
            }
        }

        return array("params" => $params, "vars" => $vars);
    }   
       
    /**
     * 运行 action
     * 
     * @param string $action 动作
     * @param array $params 参数
     * @param array $vars 对象的属性
     * @return mixed
     */
    final public function run($action, $params = null, $vars = null){
        
        // 赋值
        !is_array($params) && $params  = array();
        !is_array($vars) && $vars = array();

        $this->_action = $action;
        $this->_params = $params;

        // 执行自身 action
        $actionName = "action".ucfirst($action);
        if (method_exists($this, $actionName)){
            if (!method_exists($this, "_before") || $this->_before($action))
                $rtn = Pithy::call($this, $actionName, $params, $vars); 
            method_exists($this, "_after") && $this->_after($action);
            log2file();
            return $rtn;
        }

        // 动作不存在
        return $this->_miss($action); 
    }

    /**
     * 运行外部控制器的 action
     * 
     * @param string $command 命令名称
     * @param string $action 命令动作
     * @param array $args 参数 数组或空格隔开的字符串
     * @return mixed
     */
    final public function call($command = "", $action = "", $args = null){
        
        if (empty($command)){
            if (!isset($_SERVER["argv"][1]))
                return $this->exception("缺少命令参数");;
            $command = $_SERVER["argv"][1];
        }

        if (empty($action))
            $action = (isset($_SERVER["argv"][2]) && substr($_SERVER["argv"][2], 0, 1) != "-") ? $_SERVER["argv"][2] : "index";
        
        if (empty($args))
            $args = $_SERVER["argv"];
        if (!is_array($args))
            $args = array_filter(explode(" ", $args));
        
        $arr = $this->parse($args);
        return self::factory($command)->run($action, $arr["params"], $arr["vars"]); 
    }

    /**
     * 开启新的 command 进程
     * 
     * @param mixed $commander
     * @param array $params
     * @param array $vars
     * @return mixed
     */
    public function fork($commander, $params = array(), $vars = array()) {
        if (is_array($commander)) {
            $command = $commander[0];
            $action = $commander[1];
        }
        else {
            $command = $this->command;
            $action = $commander;
        }
        $args = empty($params) ? "" : " --".urldecode(http_build_query($params, "", " --"));
        $args .= empty($vars) ? "" : " -".urldecode(http_build_query($vars, "", " -"));
        return Pithy::execute("php", PITHY_APPLICATION."pithy.php {$command} {$action} {$args} --ts=".date("YmdHis"));
    }


    /** 
     * 屏幕打印信息
     * 
     * @param mixed $msg    调试内容 
     * @param mixed $false  强制输出
     */
    public function show($msg, $force = false){
        $msg = "";
        $num =func_num_args();
        $args = func_get_args();
        is_bool($args[$num-1]) && $force = array_slice($args, -1) && array_splice($args, -1);
        foreach($args as $arg){
            $msg .= !is_array($arg) ? $arg : var_export($arg, true);
        }
        if ($force === true || $this->force){
            $msg = preg_replace("/([^#]\s*)#(\s*[^#])/m", "$1\1$2", $msg);
            $msg = str_replace("\1", "#", str_replace("#", PHP_EOL, $msg));
            $msg = IS_WIN ? mb_convert_encoding($msg, "gbk", "utf-8") : $msg;
            echo $msg;
        }
        return 0;
    }
    public function notice(){
        $force = false;
        $num =func_num_args();
        $args = func_get_args();
        is_bool($args[$num-1]) && $force = array_slice($args, -1) && array_splice($args, -1);
        foreach($args as $i => $arg){
            $args[$i] = is_string($arg) ? $arg : var_export($arg, true);
        }
        $msg = implode(" ", $args);
        $this->log($msg, $force);
        return $this->show("#".$msg, true);
    }
    public function error(){
        $args = func_get_args();
        foreach($args as $i => $arg){
            $args[$i] = is_string($arg) ? $arg : var_export($arg, true);
        }
        $msg = "!!! ".implode(" ", $args)." !!!";
        $this->log($msg, true);
        return $this->show("#".$msg, true);
    }
    
    /** 
     * 记录日志信息
     * 
     * @param mixed $msg    日志内容
     * @param mixed $false  强制实时记录
     */
    public function log($msg, $force = false){
        empty($this->logName) && $this->logName = get_class($this).($this->logAlone ? "-".$this->_action : "");
        $GLOBALS["pithy_log_file"] = $this->logName;
        $msg = date("Y-m-d H:i:s") . " " . preg_replace(array("/^(#+)/","/(#+)$/"), array("",""), $msg);
        is_array($GLOBALS["pithy_log_content"]) || $GLOBALS["pithy_log_content"] = array();
        array_push($GLOBALS["pithy_log_content"], $msg);
        ($force || count($GLOBALS["pithy_log_content"]) >= 1000) && log2file();
    }
    
    /**
     * 命令终止
     * 
     */
    public function halt($msg = ""){
        !empty($msg) && $this->notice($msg);
        log2file();
        PITHY_DEBUG && sleep(3);
        exit;
    }


    /**
     * 指定的动作不存在
     */
    public function _miss(){
        return $this->exception("命令行的动作不存在！");
    }

    // 默认的动作入口
    public function actionIndex(){
        $this->notice("Run task :", $this->command, "/", $this->action, "-=>", json_encode($this->params), "#");
    }

}
