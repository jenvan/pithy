<?php

// 定义系统相关常量
define("IS_WIN", stristr(PHP_OS, "WIN") ? 1 : 0 );
define("IS_CGI", substr(PHP_SAPI, 0, 3) == "cgi" ? 1 : 0 );
define("IS_CLI", PHP_SAPI == "cli"  ? 1 : 0 ); 

// 定义调试状态
defined("PITHY_ERROR_DEBUG") || define("PITHY_ERROR_DEBUG", true);
defined("PITHY_ERROR_LOG") || define("PITHY_ERROR_LOG", true);
defined("PITHY_LOG_LEVEL") || define("PITHY_LOG_LEVEL", "WARNING,ERROR,ALERT");
defined("PITHY_LOG_FOLDER") || define("PITHY_LOG_FOLDER", dirname(__FILE__)."/log/");

/*********************************************************/
/************************ 调试工具 ***********************/  
/*********************************************************/ 

Class PithyDebug {

    static public $bug = array();    

    // 指标统计 
    static public function benchmark($tag=null){
        static $data = array();
        
        if( is_null($tag) )
            return $data;   
            
        $args = func_get_args(); 
        if( count($args) == 3 ){
            if( !isset($data[$args[0]], $data[$args[1]], $data[$args[0]][$args[2]], $data[$args[1]][$args[2]]) )
                return 0;
            return abs( $data[$args[0]][$args[2]] - $data[$args[1]][$args[2]] );
        }
            
        $data[$tag] = array("time" => microtime(true), "memory" => memory_get_usage());
    }   

    // 累计计数
    static public function count($key, $step=1){
        
        static $data = array();
        
        if( !isset($data[$key]) ) {
            $data[$key] = 0;
        }
        if( empty($step) )
            return $data[$key];
        else
            $data[$key] = $data[$key] + (int)$step;
    } 

    // 变量输出
    static public function dump(){

        $params = func_get_args();
   
        $var = $params[0];

        $label = "";
        if( isset($params[1]) && is_string($params[1]) )
            $label = $params[1];
        if( isset($params[2]) && is_string($params[2]) )
            $label = $params[2];

        $echo = true;    
        if( isset($params[1]) && is_bool($params[1]) )
            $echo = $params[1];
        if( isset($params[2]) && is_bool($params[2]) )
            $echo = $params[2];


        $output = var_export($var, true);   
        if( IS_CLI || !$echo ){
            $label = empty($label) ? $label : $label.PHP_EOL;
            $output = $label.$output;
        }
        else{
            $output = ini_get('html_errors') ? htmlspecialchars($output,ENT_QUOTES) : $output;    
            $output = "<pre>".$output."</pre>";            
            if( !empty($label) )
                $output = "<fieldset><legend style='margin-top:10px;padding:5px;font-weight:600;background:#CCC;'> ".$label." </legend>".$output."</fieldset>";
        }

        if( $echo )
            echo $output;

        return $output;
    } 

    // 记录
    static public function record($title=null, $value=null) {
        
        static $data = array();
        
        if( is_array($title) ){
            $data = array_merge($data, $title);    
        }                
        elseif( !empty($title) && !is_null($value) ){
            $data[$title] = $value;    
        }
        elseif( is_null($title) ){
            $var = $data;
            $data = array();
            return $var;        
        }
        elseif( is_null($value) && isset($data[$title]) ){
            return $data[$title];
        }
        return null;
    } 

    // 跟踪
    static public function trace($msg, $traces=array()){

        static $data = array();
        
        if( empty($msg) )
            return $data;  

        if( empty($traces) || !is_array($traces) ){
            if( function_exists("debug_backtrace") )                 
                $traces = debug_backtrace();
            else
                $traces = array();
        }

        if( !empty($traces) ){ 
            $msg .= " ".PHP_EOL."-------------------------------".PHP_EOL;                               
            foreach( $traces as $t ){
                $msg .= "# ";
                if( isset($t["file"]) )
                    $msg .= $t["file"]." [".$t["line"]."]  ".PHP_EOL;
                else
                    $msg .= "[PHP inner-code] ".PHP_EOL;
                if( isset($t["class"]) )
                    $msg .= $t["class"].$t["type"];
                $msg .= $t["function"]."(";
                if( isset($t["args"]) && sizeof($t["args"]) > 0 ){
                    $count = 0;
                    foreach( $t["args"] as $item ){

                        if( is_string($item) ){
                            $str = str_replace(array("\r","\n","\r\n"), "", $item);
                            if( strlen($item)>200 )
                                $msg .= "'". substr($str, 0, 200) . "...'";
                            else
                                $msg .= "'" . $str . "'";
                        }                               
                        elseif( is_bool($item) )
                            $msg .= $item ? "true" : "false";
                        elseif( is_null($item) )
                            $msg .= "NULL";
                        elseif( is_numeric($item) )
                            $msg .= $item;
                        elseif( is_object($item ))
                            $msg .= get_class($item); 
                        elseif( is_resource($item) )
                            $msg .= get_resource_type($item);
                        elseif( is_array($item) ){
                            if( $count < 3 ){
                                @array_walk($item, create_function('&$v,$k','if( is_object($v) ){ $v = "<OBJECT>".get_class($v); } if( is_resource($v) ){ $v = "<RESOURCE>".get_resource_type($v); }'));    
                                $msg .= str_replace(array("\r","\n","\r\n"), "", var_export($item, true));
                            }
                            else
                                $msg .= "array(".count($item).")"; 
                        }

                        $count++;
                        if( count($t["args"]) > $count )
                            $msg .= ", ";
                    }
                }                    
                $msg .= ") ".PHP_EOL;
            }
            $msg .=  (  IS_CLI ? "" : "-=> ".$_SERVER["SERVER_ADDR"]." : ".$_SERVER["REMOTE_ADDR"]." @ ".date("Y-m-d H:i:s") );            
        }

        $data[] = $msg;
        count( $data ) <= 100 || array_slice($data, -100);

        return $msg;
    }

    // 日志记录
    static public function log($message="", $options=null){ 

        static $data = array();
        
        // 如果日志内容为空，则表示返回之前记录的所有日志内容
        if( empty($message) )
            return $data; 

        // 支持的日志记录类型
        $types = array(
            "SYSTEM" => 0,
            "MAIL" => 1,
            "TCP" => 2,
            "FILE" => 3,
        );

        // 支持的日志记录级别
        $levels = array(
            "ALERT",
            "ERROR",
            "WARNING",
            "NOTICE",
            "INFO",
            "DEBUG",
        );

        // 默认的日志设置参数
        $config = array(
            "type" => "FILE",         // 日志记录类型
            "level" => "INFO",        // 日志记录级别
            "destination" => "common",// 日志记录位置  __DIR__/log/Ymd/common.log
            "extra" => "",            // 日志扩展信息（日志记录类型为 MAIL 和 TCP 时使用，参见 error_log 函数)
        ); 

        // 参数是数字时，设置日志记录类型
        if( is_int($options) )
            $config["type"] = $options;            

        // 参数是字符串时，设置日志记录级别或位置
        if( is_string($options) ){
            if( in_array(strtoupper($options), $levels) )
                $config["level"] = strtoupper($options); 
            else
                $config["destination"] = $options;
        }

        // 参数是数组时，将其同默认参数合并
        if( is_array($options) )
            $config = self::merge($config, $options);

        // 设置相关变量            
        $folder = PITHY_LOG_FOLDER.DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR;   
        $now = date("Y-m-d H:i:s");

        // 最终的日志参数
        $type = in_array($config["type"], array_keys($types)) ?  $types[$config["type"]] : $types["FILE"] ;
        $level = in_array(strtoupper($config["level"]), $levels) ? strtoupper($config["level"]) : "INFO" ;
        $destination = empty($config["destination"]) ? $folder.strtolower($level).".log" : ( ( strstr($config["destination"],"/") || strstr($config["destination"],"\\") ) ? $config["destination"] : $folder.$config["destination"].".log");   
        $extra = $config["extra"];        
     
        // 执行日志记录处理程序
        if( PITHY_ERROR_DEBUG || PITHY_LOG_LEVEL === true || ( is_string(PITHY_LOG_LEVEL) && preg_match("/".$level."/i", PITHY_LOG_LEVEL) ) ){

            // 拼接最终日志内容(如果已经拼接好，则不需拼接)，并放入全局公共属性中
            $msg = $message;
            !preg_match("/^[\d]{4}/", $msg) && $msg = "{$now} [{$level}] {$msg}";    
            array_push($data, $msg);               
            count( $data ) <= 1000 || array_slice($data, -1000); 

            // 文件类型的日志记录预处理
            if( $type == $types["FILE"] ){ 
                if( !is_dir($folder) && ( @mkdir($folder, 0777, true) == false || @chmod($folder, 0777) == false ) ){
                    array_push($data, "$now [ALERT] Can not mkdir($folder)!");
                    return;        
                }
                if( is_file($destination) && filesize($destination) > 100*1024*1024 ){
                    extract( pathinfo($destination) );
                    @rename($destination, $dirname.DIRECTORY_SEPARATOR.$basename."_".time().".".$extension);
                }                  
            }

            // 调用 php 自带的日志记录函数
            error_log($msg.PHP_EOL, $type, $destination, $extra);
        }
        
    }   

    // 错误处理
    static public function error(){      

        if( 4 > func_num_args() )
            return;

        $params = func_get_args();

        $errno = $params[0];
        $errstr = $params[1];
        $errfile = $params[2];
        $errline = $params[3];


        // 是否终止执行，并输出错误
        $halt = true;

        // 错误类型
        $type = "error"; 

        // 设置日志类型
        switch ($errno) {
            case E_NOTICE:
            case E_USER_NOTICE:
                $halt = false;
                $type = "notice";
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $type = "warning";
                break;
            case E_ERROR:
            case E_USER_ERROR:
                $type = "error";
                break;
            default:
                $type = "alert";                                                                                                 
                break;
        }

        $msg = $errstr;            

        // 调试错误
        $bug = self::trace($msg, debug_backtrace()); 
        array_push(self::$bug, $bug);

        // 记录错误 
        $info = $errfile."(".$errline.") -=> ".( PITHY_ERROR_DEBUG ? $bug : $msg );

        if( isset($params[4]) && !empty($params[4]) && PITHY_ERROR_DEBUG ){
            $param = array_slice($params[4], 0, 10);                
            @array_walk($param, create_function('&$v,$k','if( is_array($v) ){ $v = "<ARRAY>".count($v); } if( is_object($v) ){ $v = "<OBJECT>".get_class($v); } if( is_resource($v) ){ $v = "<RESOURCE>".get_resource_type($v); }'));
            //$info .= " ".PHP_EOL."-------------------------------".PHP_EOL.var_export($param, true);
        }            

        if( PITHY_ERROR_LOG ) 
            self::log($info, array("destination" => "pithy_".$type/*."_".basename($errfile)*/, "level" => strtoupper($type)));

        // 输出错误
        if( $halt )
            self::halt($info); 
    } 

    // 异常处理
    static public function exception($e){  

        $e = (array) $e;   

        $trace = array();
        $traces=array();

        $keys = array("message", "code", "file", "line", "trace");
        foreach( $e as $k=>$v ){
            foreach( $keys as $key ){
                if( strstr($k, $key) <> "" ){
                    if( $key == "trace" )
                        //$traces+=$v;
                        $traces = $v;
                    else
                        $trace[$key] = $v;    
                }    
            }                                
        }
        $trace["function"] = "throw new Exception";
        $trace["args"] = array($trace["message"]);
        array_unshift($traces, $trace);
        $traces = array_merge(debug_backtrace(), $traces);

        //self::dump($e);
        //self::dump($traces); 

        $msg = $trace["message"]; 

        // 调试异常              
        $bug = self::trace($msg, $traces); 
        array_push(self::$bug, $bug);            

        // 记录错误 
        $info = $trace["file"]."(".$trace["line"].") -=> ".( PITHY_ERROR_DEBUG ? $bug : $msg );
        if( PITHY_ERROR_LOG ) 
            self::log($info, array("destination"=> "pithy_exception"/*."_".basename($trace["file"])*/, "level"=>"ALERT"));    

        // 输出异常
        self::halt($info);
    }  

    // 合并
    static public function merge($a, $b){
        foreach( $b as $k => $v ){
            if( isset($a[$k]) ){
                if( is_scalar($v) && is_scalar($a[$k]) ){
                    is_integer($k) ? $a[] = $v : $a[$k] = $v;                    
                }    
                elseif( is_array($v) && is_array($a[$k]) ){
                    $a[$k] = self::merge($a[$k], $v);    
                }  
            } 
            else{
                $a[$k] = $v;    
            }                
        }
        return $a; 
    }

    // 终止
    static public function halt($msg){ 

        // 如果不是字符串则转换
        if( !is_string($msg) ){
            $msg = self::dump($msg, false);
        }

        // 显示要输出的内容
        if( !IS_CLI ){

            // 如果没有发送头部，则发送编码
            if( !headers_sent() )
                header("Content-type: text/html; charset=utf-8");

            $msg = "<B style='color:#F33;'>".preg_replace("/".PHP_EOL."/", "</B><pre>", $msg, 1)."</pre>";
            $msg = strstr($msg, "<pre>") <> "" ? $msg : "<pre>".$msg."</pre>";                
            $msg = count(self::record()) == 0 ? $msg : $msg."<hr><pre>".print_r(self::record(), true)."</pre>";
            $msg = "<div style='position:absolute;bottom:0px;right:0px;width:600px;height:400px;padding:10px;background:#FFFFCC;border:solid 1px #CCCCFF;color:#333333;font-size:12px;line-height:21px;overflow:auto;'>".$msg."</div>";  
        }
        
        if( IS_CLI && IS_WIN )
            $msg = mb_convert_encoding($msg, "gbk", "utf-8")."\r\n\r\n";

        echo $msg;
        exit;
    }
}

set_exception_handler( array("PithyDebug", "exception") );
set_error_handler( array("PithyDebug", "error") );  
