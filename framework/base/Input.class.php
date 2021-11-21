<?php
// +----------------------------------------------------------------------
// | PithyPHP [ 精练PHP ]
// +----------------------------------------------------------------------
// | Copyright (c) 2010 http://pithy.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed  (http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: jenvan <jenvan@pithy.cn>
// +----------------------------------------------------------------------

/**
 +------------------------------------------------------------------------------
 * PithyPHP 输入类
 +------------------------------------------------------------------------------
 * @category   Pithy
 * @package  Pithy
 * @subpackage  Core
 * @author Jenvan <jenvan@pithy.cn>
 * @version  $Id$
 +------------------------------------------------------------------------------
 */
class Input extends PithyBase {  

    public function filter (){

        // 设置不进行变量转义
        if (version_compare(PHP_VERSION, "6.0.0", "<")) {
            ini_set("magic_quotes_runtime", 0);
            if (get_magic_quotes_gpc() == 1){
                array_walk_recursive($_GET,    create_function('&$v,$k','$v=stripslashes($v);'));
                array_walk_recursive($_POST,   create_function('&$v,$k','$v=stripslashes($v);'));
                array_walk_recursive($_COOKIE, create_function('&$v,$k','$v=stripslashes($v);'));
            }
        }

        // 执行过滤
        $filters = Pithy::config("Input.Filters");
        if (!empty($filters) && is_array($filters)){
            foreach ($filters as $filter){
                if (!isset($filter["class"], $filter["method"]) || (isset($filter["enable"]) && !$filter["enable"]))
                    continue;
                !empty($_GET)  && $_GET  = call_user_func(array($filter["class"], $filter["method"]), $_GET);
                !empty($_POST) && $_POST = call_user_func(array($filter["class"], $filter["method"]), $_POST);
            }
        }
    }

    // 可以内置一些常用的过滤方法，使用的时候只需要配置即可
    public function filterSqlInject($data){
        foreach ($data as $k => $v){
            $data[$k] = is_array($v) ? $this->filterSqlInject($v) : preg_replace("/'/", "", $v);
        }
        return $data;
    }
    public function filterXss($data){
        foreach ($data as $k => $v){
            $data[$k] = is_array($v) ? $this->filterXss($v) : preg_replace("/script/i", "scr ipt", $v);
        }
        return $data;
    }

}