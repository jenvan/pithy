<?php
// +----------------------------------------------------------------------
// | PithyPHP [ 精练PHP ]
// +----------------------------------------------------------------------
// | Copyright (c) 2010 http://pithy.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed  (http://www.apache.org/licenses/LICENSE-2.0)
// +----------------------------------------------------------------------
// | Author: jenvan <jenvan@pithy.cn>
// +----------------------------------------------------------------------

class PithyBase {
    
    public $hacker = null;
    protected $_objs = array();     

    public function __construct(){ 

        // 核心类自动加载动态绑定
        /*
        $name = get_class($this);
        $core = array("Hook", "Router", "Input", "Output", "View");
        if (in_array($name, $core) ||  ($c = is_subclass_of($this, "Controller")) ||  ($m = is_subclass_of($this, "Model"))){
            isset($c) && $c && $name = "Controller";        
            isset($m) && $m && $name = "Model";        
            $config = Pithy::config($name.".Bind");
            if (is_array($config) && !empty($config)){
                foreach($config as $name => $item){                                       
                    Pithy::import($item["class"]);  
                    $class  = preg_replace("/.*[\.]/", "", basename(basename($item["class"], ".php"), ".class"));
                    $params = isset($item["params"]) ? $item["params"] : null;                    
                    $object = Pithy::instance($class, $params, true);                    
                    $object->hacker = $this;
                    $this->hack("attach", $class, $object);    
                }
            }
        } 
        */ 

        // 初始化
        $args = func_get_args();        
        if (method_exists($this, "initialize"))
            return call_user_func_array(array($this, "initialize"), $args);
    }  

    public function __get($name){  
        $getter = 'get'.$name;
        if (method_exists($this, $getter))
            return $this->$getter();

        throw new Exception("Property '".get_class($this)."::".$name."' is not defined.");
    }

    public function __set($name, $value){
        $setter = 'set'.$name;
        if (method_exists($this, $setter))
            return $this->$setter($value);

        if (method_exists($this, 'get'.$name))
            throw new Exception("Property '".get_class($this)."::".$name."' is read only.");
        else
            throw new Exception("Property '".get_class($this)."::".$name."' is not defined.");   
    }  

    public function __isset($name){
        $getter = 'get'.$name;
        if (method_exists($this, $getter))
            return $this->$getter() !== null;
        return false;
    }

    public function __unset($name){
        $setter = 'set'.$name;
        if (method_exists($this, $setter))
            return $this->$setter(null);

        if (method_exists($this, 'get'.$name))
            throw new Exception("Property '".get_class($this)."::".$name."' is read only.");
        else
            throw new Exception("Property '".get_class($this)."::".$name."' is not defined.");
    }

    public function __call($method,$args){       
        throw new Exception("Method '".get_class($this)."::".$method."()' is not defined!");
    }
    
    public function debug(){
        $args = func_get_args();
        call_user_func_array(array("Pithy", "debug"), $args);
    }

    // 获取 singleton 
    static public function singleton(){ 
        $bt = debug_backtrace();
        $lines = file($bt[0]['file']);
        $line = $lines[$bt[0]['line']-1];
        preg_match('/([a-zA-Z0-9\_]+)::'.$bt[0]['function'].'/', $line, $matches); 
        $class = $matches[1]; 
        if (!empty($class)){
            $args = func_get_args();                
            return Pithy::instance($class, $args, true); 
        }
        throw new Exception("Create singleton 'new {$class}()' error!");
    }
}
