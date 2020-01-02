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
 * PithyPHP 路由类
 +------------------------------------------------------------------------------
 * @category   Pithy
 * @package  Pithy
 * @subpackage  Core
 * @author Jenvan <jenvan@pithy.cn>
 * @version  $Id$
 +------------------------------------------------------------------------------
 */
class Router extends PithyBase {

    public $mode = "";
    public $groups = array();

    private $_file;
    private $_controller;
    private $_route;
    private $_group; 
    private $_module;   
    private $_action;
    private $_params;

    public function initialize(){

        // 获取配置
        $this->mode = Pithy::config("Router.mode");
        $this->groups = array_keys(Pithy::config("Router.groups"));

        // 获取 url
        $url = $_SERVER["REQUEST_URI"];
        $url = substr($url, 0, 1) == "/" ? $url : preg_replace("/^(http[s]?:\/\/[^\/]+)/i", "", $url);

        // 分解 url
        $arr = $this->parse($url, $_GET); 

        // 赋值
        $this->_file = $arr["file"];
        $this->_controller = $arr["controller"]; 
        $this->_route = $arr["route"];  
        $this->_group = $arr["group"];
        $this->_module = $arr["module"];
        $this->_action = $arr["action"]; 
        $this->_params = $arr["params"];
        
        /*
        Pithy::debug("路由", array(
            "file" => $this->file,
            "controller" => $this->controller,
            "route" => $this->route,
            "group" => $this->group,
            "module" => $this->module,
            "action" => $this->action,
            "params" => $this->params,
        ));
        */
    }

    public function getFile(){
        return $this->_file;
    }
    
    public function getController(){
        return $this->_controller;  
    }  

    public function getRoute(){
        return $this->_route;
    } 
    
    public function getGroup(){
        $host = preg_replace("/:[\d]+$/", "", $_SERVER["HTTP_HOST"]);
        $groups = Pithy::config("Router.groups");
        foreach ($groups as $group => $list){
            if (is_array($list) && in_array($host, $list)){
                $this->_group = $group;
                break;
            }
        }      
        if (empty($this->_group))
            $this->_group = Pithy::config("Router.default.group");
        return $this->_group;
    }
    
    public function getModule(){
        if (empty($this->_module))
            $this->_module = Pithy::config("Router.default.module");
        return $this->_module;
    }
    
 
        
    public function getAction(){
        if (empty($this->_action))
            $this->_action = Pithy::config("Router.default.action");
        return $this->_action;  
    }  

    public function getParams(){
        if (!is_array($this->_params))
            $this->_params = array();
        return Pithy::merge($this->_params, $_POST);
    }


    // 将url地址解析成参数
    public function parse($url, $params=array()){

        // 赋初值
        $group = $this->group;
        $module = $this->module;
        $action = $this->action;
        $params = !is_array($params) ? array() : $params;

        // 将 url 分解成 目录 和 路径
        $file = $_SERVER["SCRIPT_NAME"];
        $folder = dirname($file);
        $len1 = strlen($file);
        $len2 = strlen($folder);
        $path = substr($url, 0, $len1) == $file ? substr($url, $len1) : substr($url, $len2);   

        // 通过 pathinfo || rewrite 方式获取 route
        $route = strstr($path, "?") == "" ? $path : substr($path, 0, strpos($path, "?"));

        // 通过 get 方式获取 route
        $arr = parse_url($path);
        if (!empty($arr["query"])){
            parse_str($arr["query"], $query);
            if (isset($query["r"]))
                $route = $query["r"];
        } 

        // 剔除
        if (!empty($route)){
            $route = ltrim($route, "/");
        }

        // 分解
        if (!empty($route)){

            // 分割
            $arr = explode("/", rtrim($route, "/"));

            // 获取 group
            if (count($arr) >= 1 && in_array($arr[0], $this->groups)){
                $group = $arr[0];
                unset($arr[0]);
                $arr = array_values($arr);
            }

            // 获取 module 和 action
            if (count($arr) >= 2){
                $module = $arr[0];
                $action = $arr[1];
                unset($arr[0]);
                unset($arr[1]);
            }
            elseif (count($arr) == 1){
                if (strpos($route, "/") !== false)
                    $module = $arr[0];
                else
                    $action = $arr[0];
        
                unset($arr[0]);
            } 
            $arr = array_values($arr);

            // 获取 params
            $len = count($arr);
            for ($i=0; $i<$len; $i=$i+2){
                $params[$arr[$i]] = ($i+1<$len) ? $arr[$i+1] : "";
            }

        }

        // 保存
        $result = array(
            "file" => $file,
            "controller" => "~.controller.".ucfirst($module)."Controller".( empty($group) ? "" : "@{$group}" ), 
            "route" => ( empty($group) ? "" : "/{$group}" ) . "/{$module}/{$action}",                            
            "group" => $group,    
            "module" => $module,
            "action" => $action,
            "params" => $params,
        );

        // 返回    
        return $result; 
    }

    // 将参数构建成url地址
    public function build($route, $params = array()){
        is_array($route) && isset($route["params"]) && $params = $route["params"];
        is_array($route) && $route = "/".(isset($route["group"]) ? $route["group"] : $this->group)."/".(isset($route["module"]) ? $route["module"] : $this->module)."/".(isset($route["action"]) ? $route["action"] : $this->action);
        return preg_replace("/^(\/+)/", "/", $route) . "?" . http_build_query($params);
    }
}
