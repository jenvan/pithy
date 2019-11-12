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

/**
 +------------------------------------------------------------------------------
 * PithyPHP 控制器基类
 +------------------------------------------------------------------------------
 * @category   Pithy
 * @package  Pithy
 * @subpackage  Core
 * @author Jenvan <jenvan@pithy.cn>
 * @version  $Id$
 +------------------------------------------------------------------------------
 */
class Controller extends PithyBase {  

    // 路由实例
    protected $router = null;        

    // 视图实例
    protected $view = null; 
    
    // 模板样式
    public $theme = "";
    
    // 模板布局
    public $layout = "";  
    
    // 模板文件
    public $template = "";
    
    // 动作参数
    private $_params = null;
    
    /**
     +----------------------------------------------------------
     * 魔术方法 有不存在的操作的时候执行
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $method 方法名
     * @param array $parmas 参数
     +----------------------------------------------------------
     * @return mixed
     +----------------------------------------------------------
     */
    public function __call($method, $params) {
        
        $method = strtolower($method);
        
        // 判断 http 请求类型
        if( in_array($method, array('ispost', 'isget', 'ishead', 'isdelete', 'isput')) ){
            return strtolower($_SERVER['REQUEST_METHOD']) == substr($method, 2);
        }
        
        // 条件跳转
        if( is_object($this->view) && method_exists($this->view, "show") && in_array($method, array('show', 'redirect', 'goto', 'info', 'message', 'msg', 'succeed', 'failed', 'success', 'failure', 'ok', 'canel', 'error')) ){
            $var = $params[0];
            if( in_array($method, array('redirect', 'goto')) ){
                $status = null;                               
            }
            else{
                $status = in_array($method, array('show', 'info', 'message', 'msg')) ? (isset($params[1]) ? trim($params[1]) : "") : (in_array($method, array('succeed', 'success', 'ok')) ? 0 : 1);                
            }
            return $this->view->show($var, $status);
        }
        
        // 视图类其他方法                
        if( is_object($this->view) && method_exists($this->view, $method) && in_array($method, array("ajax","fetch","render")) ){
            return call_user_func_array(array($this->view, $method), $params);
        }
        
        // 调用父类的 __call
        return parent::__call($method, $params);
    }
    
    
    /**
     +----------------------------------------------------------
     * 初始化
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     */
    public function initialize() { 
        
        // 获取路由类的实例
        $this->router = Pithy::instance("Router"); 
        
        // 默认实例化系统自带的视图类(模板引擎)
        $this->view = Pithy::instance("View", array($this));
        
        // 子控制器是否存在预加载方法，可以在子控制器的 _init 中实例化其他视图类(模板引擎)，或者做一些其他初始操作
        method_exists($this, "_init") && call_user_func(array($this, "_init")); 
    }    
    
    /**
     +----------------------------------------------------------
     * 控制器异常
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $msg 异常信息
     +----------------------------------------------------------
     * @return mixed
     +----------------------------------------------------------
     */
    public function exception($msg){
    
        // 子类中是否存在 _exception 或 _error 方法，存在则调用
        if( method_exists($this, "_exception") )
            return $this->_exception($msg); 
        if( method_exists($this, "_error") )
            return $this->_error($msg); 

        // 视图类是否存在 show 方法，存在则调用
        if( !PITHY_DEBUG && is_object($this->view) && method_exists($this->view, "show") ){                   
            return $this->error($msg);   
        }

        // 否则触发错误
        throw new Exception($msg);                                                                 
    } 

    /**         
     * 实例化指定的控制器    
     *
     * @param string $id 控制器名称
     * @return Controller
     *          
     */
    static public function factory($id){

        $exists = Pithy::import($id);        
        if( !$exists ){
            Controller::singleton()->exception("控制器 {$id} 不存在！");
        }
        
        $class = preg_replace("/@.*$/", "", substr($id, strrpos($id, ".")+1));
        $object = Pithy::instance($class);
        if( !is_object($object) || !is_subclass_of($object, "Controller") ){
            Controller::singleton()->exception("控制器 {$class} 类型出错！");
        }                       
        
        return $object;    
    }   
    

    /**
     +----------------------------------------------------------
     * 运行 action
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $action 动作
     * @param array $params 参数
     * @param bool $enable 是否执行过滤和验证
     +----------------------------------------------------------
     * @return mixed
     +----------------------------------------------------------
     */
    final public function run($action="", $params=null, $enable=true){
        
        // 赋值 
        if( empty($action) )
            $action = $this->router->action;                    
        if( is_null($params) || !is_array($params) )
            $params = $this->router->params;
        if( !is_null($params) && !is_array($params) )
            $enable = (bool) $params;              

        //Pithy::debug("动作", $action);
        //Pithy::debug("用户参数", $params);
        //Pithy::debug("合成参数", $this->params);

        // 过滤(判断 action 是否符合条件)
        $filters = $this->filters();
        if( $enable && !empty($filters) && is_array($filters) ){
            foreach( $filters as $filter ){

                // 剔除空格
                $filter = str_replace(" ", "", $filter);

                // 判断是否符合过滤条件
                $execute = true;
                if( strstr($filter, "+") != "" || strstr($filter, "-") != "" ){        
                    $gap = strstr($filter, "+") == "" ? "-" : "+";
                    list($filter, $actions) = explode($gap, $filter);
                    $actions = explode(",", $actions);
                    $execute = in_array($action, $actions) == ($gap == "+") ? true : false;
                }

                // 执行过滤操作
                $filterAction = "filter".ucfirst($filter);
                if( $execute && !$this->$filterAction($action) ){
                    return $this->exception("不符合执行条件[ ".$filter." ]，程序终止！");
                }   
            }
        }
        
        // 规则(判断 action 的参数是否符合条件)
        $rules = $this->rules();
        if( $enable && !empty($rules) && is_array($rules) && !empty($rules[$action]) ){
            
            // 判断请求方法
            $rule = $rules[$action];
            if( isset($rule["method"]) && !in_array(strtolower($_SERVER["REQUEST_METHOD"]), explode(",", $rule["method"])) ){
                return $this->exception("当前请求的方法不在允许列表中[ ".$rule["method"]." ]，程序终止！");        
            }
            
            // 判断必需参数是否存在
            if( !empty($rule["require"]) ){
                $items = array_filter(explode("|", $rule["require"]));
                foreach($items as $item){
                    $keys1 = array_filter(explode(",", $item));        
                    $keys2 = is_array($params) ? array_keys($params) : array();
                    $arr = array_diff($keys1, $keys2);
                    if( !empty($arr) )
                        return $this->exception("缺少必需参数[ ".$item." ]，程序终止！");
                }
            }
            
            // 判断参数是否合法            
            $fields = !empty($params) && isset($rule["fields"]) && is_array($rule["fields"]) ? $rule["fields"] : array();
            foreach( $fields as $field => $option ){    
                if( !isset($params[$field]) )
                    continue;                 
 
                $func = create_function('$var', 'return true;');
                if( $option["type"] == "callback" ){
                    $func = $option["rule"];
                }
                if( $option["type"] == "function" ){
                    $func = create_function('$var', $option["rule"]);
                }                 
                if( $option["type"] == "regex" ){
                    $func = create_function('$var', 'return preg_match("'.$option["rule"].'", $var);'); 
                }
                if( $option["type"] == "confirm" ){
                    $v = isset( $params[$option["rule"]] ) ? $params[$option["rule"]] : "";
                    $func = create_function('$var', 'return $var == "'.$v.'";');    
                }                 
                if( ( $rtn = call_user_func($func, $params[$field]) ) == false ){
                    return $this->exception($option["info"]);   
                }                       
            }     
        
        }

        
        // 执行自身 action
        $actionName = "action".ucfirst($action);
        if( method_exists($this, $actionName) ){            
            if( !method_exists($this, "_before") || $this->_before($action, $params) )
                Pithy::call($this, $actionName, $params);                    
            method_exists($this, "_after") && $this->_after($action, $params);            
            
            return;
        }
        
        
        // 执行自定义动作
        $actions = $this->actions();
        if( !empty($actions) && is_array($actions) && isset($actions[$action]) ){      

            $_params = $actions[$action];        
            if( !is_array($_params) ){ 
                $route = $_params;
            }
            else{
                $route = $_params["route"];
                unset( $_params["route"] );
                $params = Pithy::merge($_params, $params);  
            }

            $this->call($route, $params);
            
            return; 
        } 
        

        // 动作不存在
        if( method_exists($this, "_miss") )   
            return $this->_miss($action, $params);         
        
        return $this->exception('控制器 '.get_class($this).' 的动作 '.$action.' 不存在！');
    }


    /**
     +----------------------------------------------------------
     * 运行外部控制器的 action
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $route 路由
     * @param array $params 参数      
     +----------------------------------------------------------
     * @return mixed
     +----------------------------------------------------------
     */
    final public function call($route, $params=null){      

        $arr = $this->router->parse($route);
        
        self::factory($arr["controller"])->run($arr["action"], $params, false); 
    }
  
    /**
     * 获取路由参数
     * 
     */
    function getRouter(){
        return $this->router;
    }
    function getGroup(){
        return $this->router->group;
    }
    function getModule(){
        return $this->router->module;
    }
    function getAction(){
        return $this->router->action;
    }
  
    function getParams(){
        return is_array($this->_params) ? $this->_params : array();
    }
    function setParams($params){
        $this->_params = Pithy::merge($this->router->params, $params);
    }

    /**
     * 定义外部动作集合
     * 
     */
    public function actions() {
        return Pithy::config("Controller.Actions");

        return array(
          "test" => "/help/test",
          "demo" => array(
            "route" => "/help/test",
            "arg1" => 1,
            "arg2" => array("a","b","c"),
          ),
        );
    }

    /**
     * 定义动作过滤集合
     * 用于过滤动作是否被允许 
     */
    public function filters() {        
        return Pithy::config("Controller.Filters");

        return array(
          "filterA",
          "filterB + index",
          "filterC - test,temp",
        );
    }

    /**
     * 定义验证规则集合
     * 用于验证客户端提交的数据是否合法
     */
    public function rules() {
        return Pithy::config("Controller.Rules");
        
        return array(
        
            'example' => array(
                'method' => 'get,post',
                'require' => 'username,password,pithy_token|username,pass,code',
                'fields' => array(
                    'username' => array(   
                        'type' => 'regex',
                        'rule' => '/[a-z0-9_]{4,10}/ig',
                        'info' => '用户名不符合规则',
                    ),
                    'password' => array(       
                        'type' => 'function',
                        'rule' => 'return strlen($var) >= 6 && strlen($var) <= 20;',
                        'info' => '密码不符合规则',
                    ),
                    'pass' => array( 
                        'type' => 'confirm',
                        'rule' => 'password',
                        'info' => '重复输入密码不一致',
                    ),
                    'code' => array(                     
                        'type' => 'callback',
                        'rule' => array($this, 'check'),
                        'info' => '验证码错误',
                    ),                    
                    
                ),
            ),
        
        ); 
    }

}
