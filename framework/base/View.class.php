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

/**
+------------------------------------------------------------------------------
* PithyPHP 视图类
* 支持布局继承(extend)、块替换(block)、资源打包发布(publish)、脚本动态注册(register)
+------------------------------------------------------------------------------
* @category   Pithy
* @package  Pithy
* @subpackage  Core
* @author Jenvan <jenvan@pithy.cn>
* @version  $Id$
+------------------------------------------------------------------------------
*/
class View extends PithyBase {

    // 控制器实例
    protected $controller = null;
    
    // 视图数据
    public $data = array();

    // 内容块
    private $_blocks = array();

    // TAG 信息
    private $_tags = array();

    // 资源文件路径
    private $_assets = array();

    // 已注册的资源文件路径  
    private $_paths = array();


    /**
    +----------------------------------------------------------
    * 初始化
    +----------------------------------------------------------
    * @access public
    +----------------------------------------------------------
    */
    public function initialize($controller) {
        $this->controller = $controller;
    }
    public function getRouter() {
        return $this->controller->router;
    }
    public function getGroup(){
        return $this->controller->group;
    }
    public function getModule(){
        return $this->controller->module;
    }
    public function getAction(){
        return $this->controller->action;
    }
    public function getParams(){
        return $this->controller->params;
    }

    /**
    +----------------------------------------------------------
    * 获取模板文件绝对路径
    +----------------------------------------------------------
    * @access private
    +----------------------------------------------------------
    * @param string $template 模板名
    +----------------------------------------------------------
    * @return string
    +----------------------------------------------------------
    */
    private function getPath($template) {
        
        $group = empty($this->group) ? "" : "@" . $this->group;
        if ( ($pos = strpos($template, "@")) > 0){
            $group = substr($template, $pos);
            $template = substr($template, 0, $pos);
        } 

        $theme = empty($this->theme) ? "" : "$" . $this->theme;
        
        if (substr($template, 0, 2) == "//")
            $template = "/view/".$theme."/".substr($template, 2);
        elseif (substr($template, 0, 1) == "/")
            $template = "/".$group."/view/".$theme."/".substr($template, 1);
        else
            $template = "/".$group."/view/".$theme."/".$this->module."/".$template;
        $template = str_replace("//", "/", $template);

        // 检查当前主题下是否存在对应的视图文件，不存在则在默认主题下查找，还不存在则显示错误提示
        $filepath = PITHY_APPLICATION."/".$template.Pithy::config("View.Template.Suffix");
        if (!Pithy::exists($filepath)){
            $filepath = str_replace($theme, "", $filepath);
            if (!Pithy::exists($filepath)){
                trigger_error("视图文件[$template]不存在！(分组：$this->group 模块：$this->module 操作：$this->action)", E_USER_WARNING);
                return "";
            }
        }

        return $filepath;
    }
    
    /**
     * 获取模板样式
     *
     */
    protected function getTheme(){
        $theme = !empty($this->controller->theme) ? $this->controller->theme : Pithy::config("View.Template.Theme");
        return empty($theme) ? "" : "{$theme}";
    }
    
    /**
     * 获取模板布局
     * 
     */
    protected function getLayout(){
        return !empty($this->controller->layout) ? $this->controller->layout : Pithy::config("View.Template.Layout"); 
    }
    
    /**
     * 获取模板文件
     * 
     */
    protected function getTemplate(){
        return !empty($this->controller->template) ? $this->controller->template : $this->action;
    }
    
    

    /**
    +----------------------------------------------------------
    * 加载模板
    +----------------------------------------------------------
    * @access public
    +----------------------------------------------------------
    * @param mixed $arg1 模板路径 或 替换变量
    * @param mixed $arg2 模板路径 或 替换变量
    +----------------------------------------------------------
    * @return string 
    +----------------------------------------------------------
    */
    public function fetch($arg1 = null, $arg2 = null) {

        // 分析参数并给相关变量赋值
        $template = $this->template;
        $params = $this->params;
        if (!empty($arg1) && is_string($arg1))
            $template = $arg1;
        if (!empty($arg2) && is_string($arg2))
            $template = $arg2;
        if (!empty($arg1) && is_array($arg1))
            $params = $arg1 + $params;
        if (!empty($arg2) && is_array($arg2))
            $params = $arg2 + $params;
        
        $this->data = $params;

        // 获取最终模板路径
        $filepath = $this->getPath($template);
        //Pithy::debug("模板路径：", $filepath);
        //Pithy::debug("模板数据：", $params);

        // 开始页面缓存
        ob_start();

        // 在匿名函数内分解变量并载入模板
        @call_user_func_array(create_function('$pithy_params,$pithy_filename','extract($pithy_params,EXTR_OVERWRITE);require($pithy_filename);'), array($params, $filepath));

        // 获取并清空缓存
        $content = ob_get_clean();

        // 返回
        return $content;
    } 

    /**
    +----------------------------------------------------------
    * 渲染视图
    +----------------------------------------------------------
    * @access public
    +----------------------------------------------------------
    * @param string $content 模板内容 或者 array($template, $data) 或者 $data
    * @param array $headers header控制参数
    +----------------------------------------------------------
    * @return string
    +----------------------------------------------------------
    */
    public function render($content = "", $headers = array()) {

        // 获取布局内容
        $html = $this->extend($this->layout, false);
        // 替换布局内容
        $html = $this->blockReplace($html);

        // 获取模板内容
        is_array($content) && $content = isset($content[0]) ? call_user_func_array(array($this, "fetch"), $content) : call_user_func(array($this, "fetch"), $content);
        // 替换模板内容
        $content = $this->blockReplace($content);

        // 设定默认标签内容（防止被覆盖）
        $this->tag(Pithy::config("View.Tag.Content"), $content);
        foreach (array("appname", "sitename", "title", "keyword", "description") as $val) {
            empty($this->_tags[strtoupper($val)]) && $this->tag($val, Pithy::config("App.Name"));
        }
        // 替换标签内容
        foreach ($this->_tags as $key => $val) {
            $html = str_ireplace("<!--{ {$key} }-->", $val, $html);
        }
        !PITHY_DEBUG && $html = preg_replace("/([\s]*<\!\-\-[^>]*\-\->[\s]*)/im", "", $html);


        // 加载脚本文件
        if (!empty($this->_paths)){
            
            $codes = array("HEAD_HEAD"=>array(), "HEAD_TAIL"=>array(), "BODY_HEAD"=>array(), "BODY_TAIL"=>array());
            
            foreach ($this->_paths as $item){
                $attr = ""; 
                foreach($item as $k => $v){
                    if (in_array($k, array("tag","pos","weight")))
                        continue;
                    $attr .=" {$k}='{$v}'";
                }
                $code = "<".$item["tag"].$attr."></".$item["tag"].">\r\n";
                $codes[$item["pos"]][$item["weight"]] = isset($codes[$item["pos"]][$item["weight"]]) ? $codes[$item["pos"]][$item["weight"]].$code : $code;
            }
            
            foreach($codes as $pos => $arr){
                ksort($arr);
                $code = implode("", $arr);
                
                list($tag, $tail) = explode("_", strtolower($pos));
                $tail = $tail == "tail" ? true : false;
                
                $pattern = '/(<'.($tail ? '\\/' : '').$tag.'\s*>)/is';
                if (preg_match($pattern, $html, $matches))
                    $html = str_replace($matches[1], ($tail ? $code.$matches[1] : $matches[1].$code), $html);
                else
                    $html = $tail ? $html.$code : $code.$html;
            }
        }


        // 发布 app 的视图目录下的资源文件
        $folder = PITHY_APPLICATION."/view/".$this->theme."/assets";
        $this->publish("app", $folder); 

        // 发布 当前分组 和 当前模块 的视图目录下的资源文件
        $folder1 = PITHY_APPLICATION."/@".$this->group."/view/".$this->theme."/assets";
        $folder2 = PITHY_APPLICATION."/@".$this->group."/view/".$this->theme."/".$this->module."/assets";
        $this->publish(array("group" => $folder1, "module" => $folder2));
        
        // 替换资源文件路径
        $assets_app = isset($this->assets["app"]) ? $this->assets["app"] : "";
        $assets_group = isset($this->assets["group"]) ? $this->assets["group"] : $assets_app;
        $assets_module = isset($this->assets["module"]) ? $this->assets["module"] : $assets_group;
        $pattern = array(
            "|([=\(\s]+)(['\"]+)(//assets)|is",
            "|([=\(\s]+)(['\"]+)(/@assets)|is",
            "|([=\(\s]+)(['\"]+)(/\.assets)|is",
        );
        $replace = array(
            "\\1\\2".$assets_app,
            "\\1\\2".$assets_group,
            "\\1\\2".$assets_module,
        );
        $html = preg_replace($pattern, $replace, $html);


        // header控制 (网页内容类型及字符编码、缓存有效期等)
        if (!headers_sent()){
            
            if (isset($headers["contentType"]) && !empty($headers["contentType"]))
                $contentType = $headers["contentType"];
            if (isset($headers["charset"]) && !empty($headers["charset"]))
                $charset = $headers["charset"];
            if (isset($contentType, $charset) && !empty($contentType) && !empty($charset))
                header("Content-Type:".$contentType."; charset=".$charset);
            
        }


        // 输出页面代码
        echo $html;
    }



    /**
    +----------------------------------------------------------
    * 消息提示 AJAX输出以及调用模板显示
    +----------------------------------------------------------
    * @access public
    +----------------------------------------------------------
    * @param number $rtn 提示状态
    * @param string $msg 提示信息
    * @param array $data 返回数据
    +----------------------------------------------------------
    * @return void
    +----------------------------------------------------------
    */
    public function show($params) {
      
        // 临时关闭静态页面缓存
        Pithy::config("Output.Cache.Expires", 0);  

        !headers_sent() && header("X-Powered-By: PithyPHP ".PITHY_VERSION);
        
        // 判断参数
        $args = func_get_args();
        (!is_array($params) || count($args) > 1) && $params = $args;
        foreach($params as $k => $v){
           !isset($params["rtn"]) && is_numeric($v) && $params["rtn"] = $v;
           !isset($params["msg"]) && is_string($v)  && $params["msg"] = $v;
           !isset($params["data"]) && is_array($v)  && $params["data"] = $v;
           if (is_numeric($k))
               unset($params[$k]);
        }
        !isset($params["rtn"])  && $params["rtn"] = Pithy::config("View.Show.StatusValue");
        !isset($params["msg"])  && $params["msg"] = $params["rtn"] ? Pithy::config("View.Show.MessageFailure") : Pithy::config("View.Show.MessageSuccess");
        !isset($params["data"]) && $params["data"] = array();
        //Pithy::debug($params);

        // AJAX输出 或 SCRIPT输出（跨域提交、结果通过window.name返回）
        $callback1 = Pithy::config("View.Show.Ajax");
        $callback2 = Pithy::config("View.Show.Script");
        if (Pithy::config("View.Show.Direct") || (isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && "xmlhttprequest" == strtolower($_SERVER["HTTP_X_REQUESTED_WITH"])) || !empty($_REQUEST[$callback1]) || !empty($_REQUEST[$callback2])){
            if (Pithy::config("View.Show.StatusField") != "rtn") {
                !empty(Pithy::config("View.Show.StatusField")) && $params[Pithy::config("View.Show.StatusField")] = $params["rtn"];
                unset($params["rtn"]);
            }
            if (Pithy::config("View.Show.MessageField") != "msg") {
                !empty(Pithy::config("View.Show.MessageField")) && $params[Pithy::config("View.Show.MessageField")] = $params["msg"];
                unset($params["msg"]);
            }
            if (empty(Pithy::config("View.Show.ReturnData"))) {
                $params = Pithy::merge($params, $params["data"]);
                unset($params["data"]);
            }
            krsort($params);
            $content = json_encode($params, JSON_UNESCAPED_UNICODE);
            if (!empty($_REQUEST[$callback1])){
                $content = $_REQUEST[$callback1]."(".$content.");";
            }
            if (!empty($_REQUEST[$callback2])){
                $content = "<script>window.name='".base64_encode($_REQUEST[$callback2]."(".json_encode($params).")")."';</script>";
            }
            echo $content;
            exit;
        }

        // 模板输出
        $template = Pithy::config("View.Show.Template");
        if ($this->getPath($template) !== "")
            echo $this->fetch($template, $params);
        else
            Pithy::dump($params);
        exit;
    }

    /**
    +----------------------------------------------------------
    * 页面跳转
    +----------------------------------------------------------
    * @param string $var 跳转地址
    +----------------------------------------------------------
    * @access public
    +---------------------------------------------------------- 
    * @param string | array $url 路径 
    +----------------------------------------------------------
    * @return void
    +----------------------------------------------------------
    */
    public function redirect($url, $interval = 0) {
        Pithy::config("Output.Cache.Expires", 0);
        if (isset($url) && !empty($url)){
            is_array($url) && $url = Pithy::instance("Router")->build($url);
            Pithy::redirect($url, $interval);
            exit;
        }
        throw new Exception("跳转地址错误！");
    }
    
    
    /**
    +----------------------------------------------------------
    * 布局继承
    +----------------------------------------------------------
    * @access public
    +----------------------------------------------------------
    * @param string $layout 布局
    * @param boolean $output 是否输出
    +----------------------------------------------------------
    * @return string 布局内容
    +----------------------------------------------------------
    */
    public function extend($layout, $output = true) {
        $filepath = $this->getPath($layout);
        if (empty($filepath)) {
            throw new Exception("布局文件不存在！");
        }
        
        ob_start();
        extract($this->data, EXTR_SKIP);
        require($filepath);
        $html = ob_get_clean();
        $output && print($html);
        return $html;
    }


    /**
    +----------------------------------------------------------
    * 代码块
    +----------------------------------------------------------
    * @access public
    +----------------------------------------------------------
    * @param string $name 块名称
    * @param string $content 块内容
    +----------------------------------------------------------
    * @return void
    +----------------------------------------------------------
    */
    public function block($name, $content = ""){
        $name = strtoupper($name);
        $this->_blocks = array($name => $content) + $this->_blocks;
    }
    public function blockBegin($name){
        $this->block($name);
        ob_start();
    }
    public function blockEnd(){
        reset($this->_blocks);
        $name = key($this->_blocks);
        $content = ob_get_clean();
        $this->block($name, $content);
    }
    private function blockReplace($html){
        
        if (preg_match("/<\!\-\-\{ block[^>]* \}\-\->/im", $html) != 1 || empty($this->_blocks))
            return $html;
        
        // 替换简写标签
        $html = preg_replace("/<\!\-\-\{ block (\S*) \}\-\->/im", "<!--{ block_begin \\1 }--><!--{ block_end }-->", $html);
        
        // 替换所有标签
        $pattern = "/<\!\-\-\{ block_begin (\S*) \}\-\->([\S|\s]*?)<\!\-\-\{ block_end \}\-\->/im";
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)){
            foreach ($matches as $match){
                $tag = strtoupper($match[1]);
                if (isset($this->_blocks[$tag])){
                    $code = $this->_blocks[$tag];
                    $html = preg_replace(str_replace("(\S*)", $tag, $pattern), "\r\n<!--{ block_begin ".$tag." }-->\r\n".$code."\r\n<!--{ block_end }-->", $html);
                    unset($this->_blocks[$tag]);
                }
            }
        }
 
        // 如果替换过标签，则继续替换一次，可以保证替换后的内容中的标签再次被替换
        if (isset($code))
            return $this->blockReplace($html);
    
        return $html;
    }


    /**
    +----------------------------------------------------------
    * 小部件
    +----------------------------------------------------------
    * @access public
    +----------------------------------------------------------
    * @param string $name 小部件名称（以@结尾表示当前分组下的小部件）
    * @param array $params 小部件参数
    +----------------------------------------------------------
    * @return string 部件内容
    +----------------------------------------------------------
    */
    public function widget($name, $params = array(), $expires = 0){
        substr($name, -1) == "@" && $name .= $this->group;
        ob_start();
        call_user_func_array(array(Widget::factory($name, $this), "run"), $params);
        $html = ob_get_clean();
        return $html;
    }
    

    /**
    +----------------------------------------------------------
    * 页面 SEO
    +----------------------------------------------------------
    * @access public
    +----------------------------------------------------------
    * @param string $title 页面标题
    * @param string $keyword 页面关键字
    * @param string $description 页面描述
    +----------------------------------------------------------
    * @return mixed
    +----------------------------------------------------------
    */
    public function seo($title, $keyword = "", $description = ""){
        $this->tag("title", $title);
        !empty($keyword) && $this->tag("keyword", $keyword);
        !empty($description) && $this->tag("description", $description);
    }

    /**
    +----------------------------------------------------------
    * 页面 TAG
    +----------------------------------------------------------
    * @access public
    +----------------------------------------------------------
    * @param mixed $name 标签名称
    * @param string $value 标签内容
    +----------------------------------------------------------
    * @return mixed
    +----------------------------------------------------------
    */
    public function tag($name, $value = ""){
        $arr = is_array($name) ? $name : array($name => $value);
        foreach ($arr as $key => $val) {
            if (!is_string($key) || !is_scalar($val)) continue;
            $this->_tags[strtoupper($key)] = $val;
        }
    }

    /**
    +----------------------------------------------------------
    * 发布资源文件
    +----------------------------------------------------------
    * @access public
    +----------------------------------------------------------
    * @param string $name 资源名称
    * @param string $folder 资源路径
    +----------------------------------------------------------
    * @return mixed
    +----------------------------------------------------------
    */
    public function publish($name, $folder = ""){
        $root = str_replace(array("\\", "//"), "/", Pithy::config("View.Assets.Root"));
        (empty($root) || $root == "/") && $root = PITHY_HOME;
        substr($root, 0, strlen(PITHY_HOME)) == PITHY_HOME || $root = PITHY_HOME.$root."/";
        
        $host = str_replace(array("\\", "//"), "/", Pithy::config("View.Assets.Host"));
        empty($host) && $host = "/";

        $arr = is_array($name) ? $name : array($name => $folder);
        foreach ($arr as $key => $src){
            $src = str_replace(array("\\", "//"), "/", $src);
            if (!Pithy::exists($src)) {
                continue;
            }

            $suffix = substr(md5($src), -8);

            $dst = str_replace(array("\\", "//"), "/", $root.$suffix);
            if (PITHY_DEBUG || !Pithy::exists($dst) || Pithy::config("View.Assets.Publish")){
                $this->deepCopy($src, $dst);
            }

            $this->_assets = Pithy::merge($this->_assets, array($key => $host.$suffix));
        }
    }

    private function deepCopy($src, $dst) {
        $dir = opendir($src); 
        !is_dir($dst) && @mkdir($dst, 0755, true);
        while (false !== ($file = readdir($dir))) {
            $ext = strtolower(substr($file, strrpos($file, ".") + 1));
            if (in_array($file, array(".", "..")) || (is_file($src."/".$file) && !in_array($ext, explode(",", Pithy::config("View.Assets.Type"))))) continue;
            $func = is_dir($src."/".$file) ? array($this, "deepCopy") : "copy";
            call_user_func($func, $src."/".$file, $dst."/".$file);
        }
        closedir($dir);
    }

    public function getAssets(){
        return $this->_assets;
    }


    /**
    +----------------------------------------------------------
    * 注册脚本文件(tag 可以是 script link iframe object image)
    +----------------------------------------------------------
    * @access public
    +----------------------------------------------------------
    * @param string $filepath 文件路径
    * @param array  $attr 标签相关属性
    +----------------------------------------------------------
    * @return void
    +----------------------------------------------------------
    */
    public function register($filepath, $attr = array(), $pos = "", $weight = 0){

        $arr = array();

        if (is_string($attr))
            $attr = array("pos"=>$attr);
        if (is_numeric($attr))
            $attr = array("weight"=>$attr);
        if (!empty($pos))
            $attr["pos"] = $pos;
        if (!empty($weight))
            $attr["weight"] = $weight;

        $ext = strtolower(substr($filepath, strrpos($filepath, ".") + 1));
 
        if ($ext == "css"){
            $arr["tag"] = "link";
            $arr["rel"] = "stylesheet";
            $arr["href"] = $filepath;
        }            
        if ($ext == "js"){
            $arr["tag"] = "script";
            $arr["language"] = "javascript";
            $arr["src"] = $filepath;
        }
        
        if (!isset($arr["tag"]) || empty($arr["tag"]))
            return;
        
        $arr = array_merge($attr, $arr);
        if (!isset($arr["pos"]) || in_array($arr["pos"], array("HEAD_HEAD", "HEAD_TAIL", "BODY_HEAD", "BODY_TAIL")))
            $arr["pos"] = "HEAD_TAIL";
        if (!isset($arr["weight"]))
            $arr["weight"] = 0;

        $this->_paths[] = $arr;
    }

}
