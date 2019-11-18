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
 
// trace 记录
$GLOBALS["pithy_traces"] = array();

// 日志记录
$GLOBALS["pithy_logs"] = array();

class Command extends PithyBase {
    
    // 是否调试（调试时在屏幕打印输出）
    public $debug = false;       
    
    // 是否每个方法记录到一个独立的日志文件中
    public $alone = false;
    
    // 多少条记录就写入到日志文件
    public $amount = 1000;  
    
    
    /**
     * 初始化   
     *  
     */
    public function initialize() {

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
        $msg = IS_WIN ? mb_convert_encoding($msg, "gbk", "utf-8") : $msg;
         
         // 子类中是否存在 _exception 或 _error 方法，存在则调用
        if (method_exists($this, "_exception"))
            return $this->_exception($msg); 
        if (method_exists($this, "_error"))
            return $this->_error($msg); 

        throw new Exception($msg);
    } 

    /**         
     * 实例化指定的命令行    
     *
     * @param string $name 命令行名称
     * @return command
     *          
     */
    static public function factory($name){    
        
        $class = ucfirst(strtolower($name))."Command";
        $args = array(); 
         
        $root = Pithy::config("Command.Root");
        $map = Pithy::config("Command.Map");
        if (isset($map[$name])){            
            if (is_array($map[$name])){
                $path = $map[$name]["path"];
                unset($map[$name]["path"]);
                $args = $map[$name];    
            }                
            else{
                $path = $map[$name];    
            }         
        }
        else{
            $path = $class;
        }
        
        if (strstr($path, "/") == "" || strstr($path, "\\") == ""){
            $path = $root.$path.".class.php";    
        }       
        
        
        $exists = Pithy::import($path);        
        if (!$exists){
            Command::singleton()->exception("命令行 {$name} 不存在！");
        }                
        
        
        $object = Pithy::instance($class, $args);
        if (!is_object($object) || !is_subclass_of($object, "Command")){
            Command::singleton()->exception("命令行 {$class} 类型出错！");
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

        return array("params"=>$params, "vars"=>$vars);        
    }   
       
    /**
     * 运行 action
     * 
     * @param string $action 动作
     * @param array $params 参数
     * @param array $vars 对象的属性
     * @return mixed
     */
    final public function run($action, $params=null, $vars=null){
        
        // 赋值
        if (!is_array($params))
            $params  = array();  
        if (!is_array($vars))
            $vars = array(); 
        
        // 执行自身 action
        $actionName = "action".ucfirst($action);
        if (method_exists($this, $actionName)){

            $GLOBALS["pithy_log_name"] = get_class($this).( $this->alone ? "_".$action : "");
            is_array( $GLOBALS["pithy_logs"]) || $GLOBALS["pithy_logs"] = array();
            is_array( $GLOBALS["pithy_traces"]) || $GLOBALS["pithy_traces"] = array(); 
            
            
            $actionName = "action".ucfirst($action);
            if (!method_exists($this, "_before") || $this->_before($action))
                Pithy::call($this, $actionName, $params, $vars); 
            method_exists($this, "_after") && $this->_after($action);
                         
            
            $GLOBALS["pithy_traces"] = array();
            $this->log2file();
            
            return;
        }

        // 动作不存在
        if (method_exists($this, "_miss"))  
            return $this->_miss($action); 
        
        return $this->help( substr(get_class($this), 0, strlen("Command")*-1));
    }
        
    /**
     * 运行外部控制器的 action
     * 
     * @param string $command 命令名称
     * @param mixed $args 参数 数组或空格隔开的字符串      
     * @return mixed
     */
    final public function call($command="", $action="", $args=null){

        if (empty($command)){
            if (!isset($_SERVER["argv"][1]))
                return $this->help();
            $command = $_SERVER["argv"][1];            
            if (!isset($_SERVER["argv"][2]))
                return $this->help($command);
            $action = $_SERVER["argv"][2];
        }            
        
        if (empty($args))
            $args = array_slice($_SERVER["argv"], 3);
        if (!is_array($args))
            $args = array_filter(explode(" ", $args));
        
        $arr = $this->parse($args);         
        self::factory($command)->run($action, $arr["params"], $arr["vars"]); 
    }
    
    /**
     * 显示相关帮助信息(未指定命令行则显示所有可用的命令行，指定命令行则显示所有可用的动作)
     * 
     * @param mixed $command
     */
    final public function help($command=""){
    
        if (empty($command))
            $msg = "未指定命令！";     
        else
            $msg = "命令行的动作不存在！";
                 
        $this->exception($msg);
    }
    
    
    /** 
     * 跟踪调试信息
     * 
     * @param mixed $msg    调试内容 
     * @param mixed $vars   环境变量
     * @param mixed $type   强制输出
     * this->trace("test", get_defined_vars());
     */
    public function trace($msg, $vars=array(), $force="normal"){
        if (is_array($vars) && !empty($vars)){
            $vars = array_merge(get_object_vars($this), $vars);
            @array_walk($vars, create_function('&$v,$k','if (is_object($v)){ $v = "<OBJECT>"; }'));
            is_array( $GLOBALS["pithy_traces"]) || $GLOBALS["pithy_traces"] = array();
            count( $GLOBALS["pithy_traces"]) <= 3 || array_splice( $GLOBALS["pithy_traces"], 0, -3);
            array_push($GLOBALS["pithy_traces"], $vars);                                     
        }
        
        if (!is_array($vars))
            $force = $vars;

        if (is_array($msg))
            $msg = print_r($msg, true);
            
        if ($force !== false && ( $force === true || $this->debug)){
            $msg = str_replace("#", PHP_EOL, $msg);
            $msg = IS_WIN ? mb_convert_encoding($msg, "gbk", "utf-8") : $msg;
            echo $msg;    
        }    
    }
    
    /** 
     * 记录日志信息
     * 
     * @param mixed $msg    日志内容 
     * @param mixed $vars   环境变量
     * @param mixed $type   强制输出
     */    
    public function log($msg, $vars=array(), $force="normal"){
        $this->trace($msg, $vars, $force);    
        
        $msg = date("Y-m-d H:i:s") . " " . preg_replace(array("/^(#+)/","/(#+)$/"), array("",""), $msg);
        
        is_array( $GLOBALS["pithy_logs"]) || $GLOBALS["pithy_logs"] = array();
        array_push( $GLOBALS["pithy_logs"], $msg);
        if (count($GLOBALS["pithy_logs"]) >= $this->amount){
            $this->log2file();       
        }
    }                             
    private function log2file(){
        if (empty($GLOBALS["pithy_logs"]))
            return;
                
        $msg = implode("\r\n", $GLOBALS["pithy_logs"]);
        Pithy::log($msg, "cli_".$GLOBALS["pithy_log_name"], true);
        $GLOBALS["pithy_logs"] = array();    
    }
    
    /**
     * 命令终止
     * 
     */
    public function halt($msg=""){
        !empty($msg) && $this->log($msg, true);
        $this->log2file();
        exit;
    }      

}
