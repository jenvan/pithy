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
 * PithyPHP 钩子类
 +------------------------------------------------------------------------------
 * @category   Pithy
 * @package  Pithy
 * @subpackage  Core
 * @author Jenvan <jenvan@pithy.cn>
 * @version  $Id$
 +------------------------------------------------------------------------------
 */
class Hook extends PithyBase {

    /**
     * 判断指定的标签是否允许执行挂钩操作
     * 
     * @param string $tag 挂钩标签名称
     * @return boolean  检查结果
     */
    public function check($tag){
        $enable_all = Pithy::config("Hook.Enable");
        $enable_tag = Pithy::config("Hook.".$tag.".Enable");
        if( $enable_all && $enable_tag )
            return true;
        return false;    
    } 

    /**
     * 执行指定标签的挂钩操作
     * 
     * @param string $tag 挂钩标签名称
     * @return boolean 执行结果
     */
    public function call($tag){

        // 判断是否允许执行
        if( !$this->check($tag) )            
            return true;

        // 默认返回值
        $rtn = true;    

        // 顺序执行挂钩操作    
        Pithy::benchmark("Hook_".$tag, "start");
        $hooks = Pithy::config("Hook.".$tag);
        if( !empty($hooks) ){               
            foreach($hooks as $name => $params){
                Pithy::benchmark("Hook_".$tag."_".$name,"start");            
                $rtn = $rtn & self::factory($name, $params)->run($params);                    
                Pithy::benchmark("Hook_".$tag."_".$name,"end");
            }  
        }
        Pithy::benchmark("Hook_".$tag, "end");

        return $rtn;
    }

    /**
     * 为指定标签插入(prepend)挂钩操作
     * 
     * @param string $tag
     * @param string $name
     * @param mixed $params 
     */
    public function prepend($tag, $name, $params){             
        $this->add($tag, $name, $params, "head");    
    }

    /**
     * 为指定标签追加(append)挂钩操作
     * 
     * @param string $tag
     * @param string $name
     * @param mixed $params 
     */
    public function append($tag, $name, $params){             
        $this->add($tag, $name, $params, "tail");
    }

    /**
     * 为指定标签添加挂钩操作
     * 
     * @param string $tag
     * @param mixed $params 
     */
    public function add($tag, $name, $params, $pos="tail"){

        $hooks = Pithy::config("Hook.".$tag);

        if( empty($hooks) || !is_array($hook) )
            $hooks = array("enable"=>true);
        if( !isset($hooks["enable"]) )
            $hooks["enable"] = true;    

        if( isset($hooks[$name]) )
            unset($hooks[$name]);    

        if( $pos == "head" )
            array_unshift($hooks, array($name=>$params));
        else
            array_push($hooks, array($name=>$params));    

        Pithy::config("Hook.".$tag, $hooks);
    }

    /**
     * 为指定标签删除挂钩操作
     * 
     * @param mixed $tag
     * @param mixed $name
     */
    public function remove($tag, $name=null){
        $hooks = Pithy::config("Hook.".$tag); 

        if( empty($hooks) || !is_array($hooks) )
            return;

        if( is_null($name) ){
            $config = Pithy::config("Hook");
            unset($config[$tag]);
            Pithy::config("Hook", $config);
        }
        elseif( isset($hooks[$name]) ){
            unset($hooks[$name]); 
            Pithy::config("Hook.".$tag, $hooks);    
        }                                                              
    }




    /**
     * 实例化 hook 子类
     * 
     * @param mixed $name
     */
    static public function factory($name, &$params){
        if( empty($params) || is_array($params) && !isset($params["path"]) )
            throw new Exception("Hook ($name) paramters is error!");

        $path = is_array($params) ? $params["path"] : $params;
        $path = substr($path, 0, 2)=="~." ? $path : "~.HOOK.".$path; 

        $exists = Pithy::import($path);
        if( !$exists )
            throw new Exception("Hook ($name) file ($path) is not exists!");   

        $object = Pithy::instance($path);     
        if( !is_object($object) || !method_exists($object, "run") )
            throw new Exception("Hook ($name) method (run) is not exists!"); 

        return $object;     
    }  
}
