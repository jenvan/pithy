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
 * PithyPHP 输出类
 * 支持缓存、页面压缩，能显示 Runtime 信息和 Debug 信息
 +------------------------------------------------------------------------------
 * @category   Pithy
 * @package  Pithy
 * @subpackage  Core
 * @author Jenvan <jenvan@pithy.cn>
 * @version  $Id$
 +------------------------------------------------------------------------------
 */
class Output extends PithyBase {     

    protected $router = null;       // 路由实例  
    protected $content = "";        // 最终输出内容

    /**
     +----------------------------------------------------------
     * 初始化 取得输出对象实例
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     */
    public function initialize() {
        $this->router = Pithy::instance("Router");            
    }


    /**
     +----------------------------------------------------------
     * 获取最终输出内容
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $output
     +----------------------------------------------------------
     */
    public function get(){
        if( empty($this->content) )
            $this->content = ob_get_clean();
        return $this->content;    
    }

    /**
     +----------------------------------------------------------
     * 设置最终输出内容
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $output
     +----------------------------------------------------------
     */
    public function set($content){
        $this->content = $content;    
    }

    /**
     +----------------------------------------------------------
     * 追加最终输出内容
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $output
     +----------------------------------------------------------
     */
    public function append($content){
        $this->content = $this->get() . $content;
    }  



    /**
     +----------------------------------------------------------
     * 输出缓存的内容
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     */
    public function cacheDisplay(){

        // 获取缓存有效期
        $cacheTime = intval(Pithy::config("Output.Cache.Expires"));            

        // 设定的缓存有效期无效
        if( $cacheTime <= 0 )                
            return false;


        // 获取缓存文件路径
        $cachePath = $this->cachePath;            

        // 缓存文件不存在              
        if( !is_file($cachePath) )                
            return false;

        // 缓存不在有效期内
        if( time() > filemtime($cachePath) + $cacheTime )                
            return false;


        // 获取缓存内容
        $content = @file_get_contents( $cachePath );
        $this->set($content);
        $this->display(); 

        return true;    
    }

    /**
     +----------------------------------------------------------
     * 构建最终输出内容的缓存
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     */
    public function cacheBuild(){

        // 如果禁止缓存则返回
        if( intval(Pithy::config("Output.Cache.Expires")) <= 0 )                
            return false;

        // 写入缓存
        $dir = dirname($this->cachePath);
        if( !is_dir($dir) ){
            $mode = 0777;
            @mkdir($dir, $mode, true);
            @chmod($dir, $mode);     
        }                
        return @file_put_contents($this->cachePath, $this->content); 
    }


    /**
     +----------------------------------------------------------
     * 获取缓存文件路径
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    public function getCachePath(){

        $group = $this->router->group;    
        $module = $this->router->module;    
        $action = $this->router->action;    
        $params = $this->router->params;    

        ksort($params);
        $ignore = Pithy::config("Output.Cache.Ignore");
        $ignore = empty($ignore) ? "" : explode("," ,$ignore);

        $folder = Pithy::config("Output.Cache.Folder");
        $folder = empty($folder) ? "html" : $folder;
        $folder = PITHY_PATH_RUNTIME.$folder.DIRECTORY_SEPARATOR.$group.DIRECTORY_SEPARATOR.$module.DIRECTORY_SEPARATOR;

        if( is_array($ignore) && !empty($ignore) && is_array($params) && !empty($params)){
            foreach($params as $k=>$v){
                if( in_array($k,$ignore) ){
                    unset($params[$k]);
                }    
            }    
        }                
        $filename = $action ."-". ( empty($params) ? "default" : md5(http_build_query($params)) );            

        $ext = Pithy::config("Output.Cache.Suffix");
        $ext = empty($ext) ? ".html" : $ext;

        return $folder.$filename.$ext;                                            
    }



    /**
     +----------------------------------------------------------
     * 输出过滤
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     */       

    public function filter(){

        $output = $this->get(); 

        // 执行过滤
        $filters = Pithy::config("Output.Filters");
        if( !empty($filters) && is_array($filters) ){
            foreach($filters as $filter){
                if( !isset($filter["class"],$filter["method"]) || (isset($filter["enable"]) && !$filter["enable"]) )
                    continue;
                $filter["params"] = !isset($filter["params"]) ? array("output"=>&$output) : array("output"=>&$output) + $filter["params"];
                Pithy::call($filter["class"], $filter["method"], $filter["params"]);
            }
        } 

        $this->set($output);    
    }

    public function filterTokenBuild($output, $tag=""){
        // tag 为空，表示所有表单都需要加验证
    }

    /**
     +----------------------------------------------------------
     * 将最终输出内容显示在页面上
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     */
    public function display(){

        // 获取最终输出内容
        $output = $this->get();   

        // 检查是否需要输出 Runtime 和 debug 信息，以及自定义的标签替换
        $output = $this->replace($output);            

        // 判断是否启用 gzip 压缩
        if( Pithy::config("Output.Gzip") === true ){
            if( extension_loaded('zlib') && @ini_get('zlib.output_compression') ){
                if( isset($_SERVER['HTTP_ACCEPT_ENCODING']) and strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false ){
                    ob_start('ob_gzhandler');
                }
            }
        }

        if( !headers_sent() ){

            $headers = implode("", headers_list());

            header("Cache-control: private"); 

            if( strstr($headers, "charset") == "" )
                header("Content-type: ".Pithy::config("App.ContentType")."; charset=".Pithy::config("App.Charset"));

            header("X-Powered-By: PithyPHP ".PITHY_VERSION);

        }                   

        echo $output; 

        flush();    
    }      

    /**
     +----------------------------------------------------------
     * 替换最终输出内容
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $content
     +----------------------------------------------------------
     * @return string $content
     +----------------------------------------------------------
     */
    private function replace($content){ 
        
        // 自定义的标签替换
        $replace = Pithy::config("Output.Replace");
        if( !empty($replace) && is_array($replace) ) 
            $content = str_replace(array_keys($replace), array_values($replace), $content);

        // 非调试和非MVC模式下退出    
        if( !PITHY_DEBUG || PITHY_MODE != "mvc" )
            return $content;

        // 是否显示调试信息
        $enable = Pithy::config("Output.Debug.Enable"); 
        if( !empty($enable) ){
            $code = $this->getDebug();
            if( preg_match('/<!--DEBUG_START-->(.*)<!--DEBUG_END-->/is', $content, $match) ){  
                $content = preg_replace($match[0], '<div id="pithy_debug">'.$code.'</div>', $content);  
            }                    
            elseif( preg_match('/<\/body(\s*)>/is', $content, $match) ) {
                $content = str_replace($match[0], "\r\n<!--DEBUG_START-->".$code."<!--DEBUG_END-->\r\n".$match[0], $content);
            }
            else{
                $content .= $code;
            }
        } 

        return $content;
    }

    /**
     +----------------------------------------------------------
     * 获取运行时间、数据库操作、缓存次数、内存使用等运行时信息
     +----------------------------------------------------------
     * @access private
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    private function getRuntime() {
        // 显示运行时间
        /*
        G('viewStartTime');
        $showTime   =   'Process: '.G('beginTime','viewEndTime').'s ';
        if(C('SHOW_ADV_TIME')) {
        // 显示详细运行时间
        $showTime .= '( Load:'.G('beginTime','loadTime').'s Init:'.G('loadTime','initTime').'s Exec:'.G('initTime','viewStartTime').'s Template:'.G('viewStartTime','viewEndTime').'s )';
        }
        if(C('SHOW_DB_TIMES') && class_exists('Db',false) ) {
        // 显示数据库操作次数
        $showTime .= ' | DB :'.N('db_query').' queries '.N('db_write').' writes ';
        }
        if(C('SHOW_CACHE_TIMES') && class_exists('Cache',false)) {
        // 显示缓存读写次数
        $showTime .= ' | Cache :'.N('cache_read').' gets '.N('cache_write').' writes ';
        }
        if(MEMORY_LIMIT_ON && C('SHOW_USE_MEM')) {
        // 显示内存开销
        $startMem    =  array_sum(explode(' ', $GLOBALS['_startUseMems']));
        $endMem     =  array_sum(explode(' ', memory_get_usage()));
        $showTime .= ' | UseMem:'. number_format(($endMem - $startMem)/1024).' kb';
        }
        return $showTime;
        */
       return date("Y-m-d H:i:s");
    }

    /**
     +----------------------------------------------------------
     * 获取页面调试信息
     +----------------------------------------------------------
     * @access private
     +----------------------------------------------------------
     */
    private function getDebug(){
        
        $arr = array();
    
        $enable = Pithy::config("Output.Debug.Runtime"); 
        if( !empty($enable) ){
            
            // 系统默认的调试信息
            $arr += array('请求时间' => date('Y-m-d H:i:s',$_SERVER['REQUEST_TIME']));
            $arr += array('加载时间' => (microtime(true) - PITHY_TIME) . " s");
            $arr += array('当前主机' => $_SERVER['HTTP_HOST']);
            $arr += array('当前页面' => $_SERVER['REQUEST_URI']);
            $arr += array('请求方法' => $_SERVER['REQUEST_METHOD']);
            $arr += array('通信协议' => $_SERVER['SERVER_PROTOCOL']);
            $arr += array('用户代理' => $_SERVER['HTTP_USER_AGENT']);
            $arr += array('用户会话' => session_id());
            $arr += array('缓存路径' => $this->getCachePath());

            // 加载的文件
            $files = get_included_files();
            $arr += array('加载文件记录' => count($files).' 个文件<span class="list">'.str_replace("\n",'<br/>',substr(substr(print_r($files,true),7),0,-2)).'</span>');
        }
        
        $debug = Pithy::debug();
        if (!empty($debug)){
            $arr += array('调试记录' => count($debug) ? count($debug).' 条调试记录<span class="list"><xmp>'.Pithy::dump($debug, false).'</xmp></span>' : '无调试记录');
        }
        
        $enable = Pithy::config("Output.Debug.Trace");
        if( !empty($enable) ){
            $trace = Pithy::trace();
            $arr += array('跟踪记录' => count($trace) ? count($trace).' 条跟踪记录<span class="list"><xmp>'.Pithy::dump($trace, false).'</xmp></span>' : '无跟踪记录');            
        }
        
        $enable = Pithy::config("Output.Debug.Benchmark");
        if( !empty($enable) ){
            $benchmark = Pithy::benchmark();
            $arr += array('基准测试记录' => count($benchmark) ? count($benchmark).' 条基准测试记录<span class="list"><xmp>'.Pithy::dump($benchmark, false).'</xmp></span>' : '无基准测试记录');
        }
        
        $enable = Pithy::config("Output.Debug.Log");
        if( !empty($enable) ){
            $log = Pithy::log();   
            $arr += array('日志记录' => count($log) ? count($log).' 条日志<span class="list"><br/><xmp>'.implode('\r\n',$log).'</xmp></span>' : '无日志记录');
        }
        
        
        // 用户配置的调试信息
        $append = Pithy::config("Output.Trace.Append");
        if( !empty($append) && is_array($append) )
            $arr += $append;
        
        // 分解
        $content = "<div style='position:absolute;bottom:0px;right:0px;width:98%;height:50%;padding:1%;background:#FFFFCC;border:solid 1px #999;color:#333333;font-size:12px;line-height:21px;overflow:auto;'>";
        foreach($arr as $k=>$v){
            $content .= "<span class='title'><b>$k</b></span> : <span class='content'>".print_r($v, true)."</span> <br>";               
        }
		$content = $content."</div>";

        return $content;
    }
}