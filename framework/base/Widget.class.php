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
 * PithyPHP 小部件类
 +------------------------------------------------------------------------------
 * @category   Pithy
 * @package  Pithy
 * @subpackage  Core
 * @author Jenvan <jenvan@pithy.cn>
 * @version  $Id$
 +------------------------------------------------------------------------------
 */
abstract class Widget extends PithyBase {

    private $name;
    private $view;

    /**
    +----------------------------------------------------------
    * 初始化
    +----------------------------------------------------------
    * @access public
    +----------------------------------------------------------
    * @param string $name 小部件名称（包含分组）
    * @param string $view 已实例化的视图对象
    +----------------------------------------------------------
    * @return string 
    +----------------------------------------------------------
    */
    public function initialize($name, $view){
        $this->name = $name;
        $this->view = $view;
    }

    /**
    +----------------------------------------------------------
    * 发布资源
    +----------------------------------------------------------
    * @access public
    +----------------------------------------------------------
    * @param string $folder 资源文件夹（相对路径）
    * @param array $files 要注册到 html 页面的资源文件 
    +----------------------------------------------------------
    * @return void
    +----------------------------------------------------------
    */
    public function publish($folder, $files = array()) {
        $name = "Widget-{$this->name}";
        $this->view->publish($name, $this->getPath($folder));
        foreach ($files as $file) {
            $this->view->register($this->view->assets[$name]."/".$file);
        }
    }

    /**
    +----------------------------------------------------------
    * 渲染模板
    +----------------------------------------------------------
    * @access public
    +----------------------------------------------------------
    * @param string $filepath 模板路径（相对路径）
    * @param array $params 模板变量
    +----------------------------------------------------------
    * @return void 
    +----------------------------------------------------------
    */
    public function render($filepath, $params = array()) {
        ob_start();
        @call_user_func_array(create_function('$pithy_filepath,$pithy_params','extract($pithy_params,EXTR_OVERWRITE);require($pithy_filepath);'), array($this->getPath($filepath), $params));
        $content = ob_get_clean();
        echo $content;
        return $content;
    }

    // 获取文件绝对路径
    private function getPath($path) {
        list($class, $group) = explode("@", $this->name);
        $root = "~".(empty($group) ? "" : ".@{$group}").".widget";
        $root = Pithy::exists($root) ? $root : PITHY_APPLICATION."/widget/";
        $root = str_replace(array("\\", "//"), "/", $root);
        $path = str_replace(array("\\", "//"), "/", $root.$path);
        if (!Pithy::exists($path)) {
            throw new Exception("The resource file of widget not exists");
        }
        if (substr($path, 0, strlen($root)) !== $root) {
            throw new Exception("The resource file of widget not allowed");
        }
        return $path;
    }

    // 抽象方法，子类实现 小部件的入口
    abstract public function run();


    /**
     * 实例化指定名称的小部件
     *
     * @param string $view 视图对象
     * @param string $name 部件名称
     * @return Widget
     *
     */
    static public function factory($name, $view){
        list($class, $group) = explode("@", $name);
        $class = ucfirst($class)."Widget";
        $path = "~.widget.{$class}";
        !empty($group) && $path .= "@".$group;
        $exists = Pithy::import($path);
        if (!$exists){
            return trigger_error("小部件类 {$path} 不存在！");
        }

        !empty($group) && Pithy::import("~.@".$group.".extend.*");
        $object = Pithy::instance($class, array("name" => $name, "view" => $view));
        if (!is_object($object) || !is_subclass_of($object, "Widget")){
            return trigger_error("小部件类 {$class} 类型出错！");
        }

        return $object;
    }
}