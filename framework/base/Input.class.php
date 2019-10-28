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

    public function filter(){

        // 设置不进行变量转义
        if(version_compare(PHP_VERSION,'6.0.0','<')) {
            ini_set("magic_quotes_runtime", 0);
            if(get_magic_quotes_gpc()==1){                                  
                array_walk_recursive($_GET,create_function('&$v,$k','$v=stripslashes($v);'));
                array_walk_recursive($_POST,create_function('&$v,$k','$v=stripslashes($v);'));
                array_walk_recursive($_COOKIE,create_function('&$v,$k','$v=stripslashes($v);'));
            }            
        }


        // 执行过滤
        $filters = Pithy::config("Input.filters");
        if( !empty($filters) && is_array($filters) ){
            foreach($filters as $filter){
                if( !isset($filter["class"],$filter["method"]) || (isset($filter["enable"]) && !$filter["enable"]) )
                    continue;
                $filter["params"] = !isset($filter["params"]) ? array() : $filter["params"];
                Pithy::call($filter["class"], $filter["method"], $filter["params"]);
            }
        } 

    }

    // 可以内置一些常用的过滤方法，使用的时候只需要配置即可

    public function filterTokenCheck(){

    }

    public function filterSql(){

    }

    public function filterXss(){

    }

}