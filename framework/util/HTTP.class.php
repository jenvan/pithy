<?php
/**
 * HTTP 协议相关辅助类
 */
class HTTP{
    
    static public $status = 0;
    static public $error = "";
    static public $response = "";
    
    static public function curl($url, $fields = "", $options = array()){  
                           
        self::$error = "";
        self::$response = "";
        
        $config = array(
            CURLOPT_HEADER => 1,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FAILONERROR => 0,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.112 Safari/537.36",
        );
        if (substr($url, 0, 8) == "https://") {
            $config[CURLOPT_SSL_VERIFYPEER] = 0;
            $config[CURLOPT_SSL_VERIFYHOST] = 0;
        } 

        $options = !is_array($options) || empty($options) ? $config : $options + $config;
        
        if (!empty($fields)) { 
            if (!empty($options[CURLOPT_POST]) || !empty($options[CURLOPT_CUSTOMREQUEST])) {
                
                $multipart = is_string($fields);
                
                if (is_array($fields)) {
                    foreach($fields as $k => $v) {
                        if ("@" == substr($v, 0, 1)) {
                            $multipart = true;
                            if (!class_exists("CURLFile"))
                                $options[CURLOPT_SAFE_UPLOAD] = false;
                            else
                                $fields[$k] = new CURLFile(ltrim($v, "@"));
                        }
                    }
                    unset($k, $v);
                }
                
                // 文件上传用 multipart/form-data，否则用 www-form-urlencoded                
                $options[CURLOPT_POSTFIELDS] = $multipart ? $fields : http_build_query($fields);                 
            } 
            else{
                $url .= (strstr($url, "?") == "" ? "?" : "&") . (is_array($fields) ? http_build_query($fields) : $fields);
            }
        }
        
        $options[CURLOPT_URL] = preg_replace("/(#.*)$/", "", $url);
        
        //Pithy::debug($options);

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);

        $data = empty($response) ? "" : substr($response, curl_getinfo($ch, CURLINFO_HEADER_SIZE));         

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (0 !== ($errno = curl_errno($ch)))
            $error = curl_error($ch);
        elseif (200 !== $status)
            $error = preg_replace("/\\n.*/", "", $response);
        else
            $error = "";
               
        curl_close($ch);
        
        self::$status = $status;
        self::$error = $error;
        self::$response = $response;
        
        return $data;
    } 

    static public function request($method, $url){
        
        $method = strtoupper($method);
        $fields = array();
        $options = array();   
        
        if (in_array($method, array("GET","POST")))
            $options[CURLOPT_POST] = ($method == "POST");
        else
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        
        $num = func_num_args();
        $args = func_get_args();
        foreach($args as $i => $arg){
            if ($i < 2)
                continue;
            if (is_array($arg))
                $fields = $arg;
            elseif (is_int($arg))
                $options[CURLOPT_TIMEOUT] = $arg;
            elseif (preg_match("/[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+:[0-9]+$/", $arg)) 
                $options[CURLOPT_PROXY] = $arg; 
            elseif (preg_match("/^http[s]?:\/\/.+/", $arg))
                $options[CURLOPT_REFERER] = $arg;
            elseif (preg_match("/^Mozilla/", $arg))
                $options[CURLOPT_USERAGENT] = $arg;
            elseif (preg_match("/^[a-z0-9\-]+:/i", $arg)) {
                !isset($options[CURLOPT_HTTPHEADER]) && $options[CURLOPT_HTTPHEADER] = array();
                $options[CURLOPT_HTTPHEADER][] = $arg;
            }
            else
                $fields = $arg;
        } 
                
        return self::curl($url, $fields, $options);    
    }
    
    static public function get($url){
        $args = func_get_args();
        array_unshift($args, "GET");
        return call_user_func_array(array(__CLASS__, "request"), $args);    
    }

    static public function post($url){
        $args = func_get_args();
        array_unshift($args, "POST");
        return call_user_func_array(array(__CLASS__, "request"), $args);   
    }

}
