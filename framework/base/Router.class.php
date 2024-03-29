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

    private $_history = array();

    private $_group;
    private $_module;
    private $_action;
    private $_params;


    public function initialize(){

        !isset($_SERVER["HTTP_HOST"]) && $_SERVER["HTTP_HOST"] = $_SERVER["SERVER_NAME"];

        // 获取 url
        $url = $_SERVER["REQUEST_URI"];
        $url = substr($url, 0, 1) == "/" ? $url : preg_replace("/^(http[s]?:\/\/[^\/]+)/i", "", $url);
        $url = preg_replace("/(\/+)/", "/", $url);

        // 解析
        $arr = $this->parse($url, Pithy::merge($_GET, $_POST));
        
        // 赋值
        $this->_group = $arr["group"];
        $this->_module = $arr["module"];
        $this->_action = $arr["action"];
        $this->_params = $arr["params"];

        // 加载分组配置
        if (!empty($arr["group"])) {
            $filepath = PITHY_APPLICATION."/@".$arr["group"]."/config.php";
            Pithy::exists($filepath) && Pithy::config(Pithy::merge(Pithy::config(), @include($filepath)), true);
        }

        return ;
    }

    // 将url地址解析成参数
    public function parse($url, $params = array()){

        // 赋初值
        $group = null;
        $module = "";
        $action = "";
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
            if (isset($query["_r"]))
                $route = $query["_r"];
        } 

        // 剔除
        if (!empty($route)){
            $route = "/".$route;
            $map = Pithy::config("Router.Map");
            foreach ($map as $key => $var){
                if (preg_match("/{$var}/i", $route, $matches)){
                    $route = "/".str_replace($matches[0], "", $route);
                    $params[$key] = $matches[1];
                }
            }
            $route = ltrim($route, "/");
        }

        // 分解
        if (!empty($route)){

            // 分割
            $arr = explode("/", rtrim($route, "/"));

            // 获取 group
            $groups = array_keys(Pithy::config("Router.Groups"));
            if (count($arr) >= 1 && in_array($arr[0], $groups)){
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
            $alias = Pithy::config("Router.Alias");
            if (is_array($alias) && !empty($module)) {
                if (isset($alias[$module])) {
                    if (strpos($alias[$module], "@") == false) 
                        $module = $alias[$module];
                    else
                        list($module, $group) = explode("@", $alias[$module]);
                }
                isset($alias[$module."/".$action]) && list($module, $action) = explode("/", $alias[$module."/".$action]);
            }

            // 获取 params
            $arr = array_values($arr);
            $len = count($arr);
            for ($i=0; $i<$len; $i=$i+2){
                $params[$arr[$i]] = ($i+1<$len) ? $arr[$i+1] : "";
            }

        }

        if (is_null($group)) {
            $group = "";
            $domain = preg_replace("/:[\d]+$/", "", $_SERVER["HTTP_HOST"]);
            $wildcard = preg_replace("/^[^\.]+\.(.+)$/i", "*.$1", $domain);
            $groups = Pithy::config("Router.Groups");
            foreach ($groups as $key => $list){
                if (!is_array($list) || empty($list))
                    continue;
                if (in_array($domain, $list) || in_array($wildcard, $list)){
                    $group = $key;
                    break;
                }
            }
        }

        $arr = array(
            "group" => strtolower($group),
            "module" => strtolower($module),
            "action" => strtolower($action),
            "params" => $params,
        );
        $arr["entry"] = $this->getEntry($arr);

        array_push($this->_history, $arr);

        return $arr;
    }

    // 将参数构建成url地址
    public function build($route, $params = array()){
        is_array($route) && isset($route["params"]) && $params = $route["params"];
        is_array($route) && $route = "/".(isset($route["group"]) ? $route["group"] : $this->group)."/".(isset($route["module"]) ? $route["module"] : $this->module)."/".(isset($route["action"]) ? $route["action"] : $this->action);
        return preg_replace("/^(\/+)/", "/", $route) . "?" . http_build_query($params);
    }
    

    // 获取路由相关参数
    public function getEntry($arr = "") {
        empty($arr) && $arr = $this->first;
        $group = !empty($arr["group"]) ? $arr["group"] : Pithy::config("Router.Default.group");
        $module = !empty($arr["module"]) ? $arr["module"] : Pithy::config("Router.Default.module");
        return "~.controller.".ucfirst($module)."Controller".(empty($group) ? "" : "@{$group}");
    }

    public function getHistory() {
        return $this->_history;
    }
    public function getFirst() {
        return $this->_history[0];
    }
    public function getFinal() {
        return array_slice($this->_history, -1);
    }

    public function getRoute(){
        return (empty($this->group) ? "" : "/{$this->group}") . "/{$this->module}/{$this->action}";
    }
    public function getGroup(){
        empty($this->_group) && $this->_group = Pithy::config("Router.Default.group");
        return $this->_group;
    }
    public function getModule(){
        empty($this->_module) && $this->_module = Pithy::config("Router.Default.module");
        return $this->_module;
    } 
    public function getAction(){
        empty($this->_action) && $this->_action = Pithy::config("Router.Default.action");
        return $this->_action;
    }
    public function getParams(){
        !is_array($this->_params) && $this->_params = array();
        return $this->_params;
    }

}
