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
    
    defined("OPEN_CONFIG") || define("OPEN_CONFIG", dirname(__FILE__)."/config.php");
    defined("OPEN_SECRET") || define("OPEN_SECRET", md5(__FILE__));
    
    class Open { 
        
        public $output = false;
        public $traces = array();
        public $logs = array();
         
        private $_options;
        private $_account;       

        static public $config = array();
        
        public function __get($name){  
            $getter = 'get'.$name;
            if( method_exists($this, $getter) )
                return $this->$getter();
                
            throw new Exception("Property '".get_class($this)."::".$name."' is not defined.");
        }

        public function __set($name,$value){          
            $setter = 'set'.$name;
            if( method_exists($this, $setter) )
                return $this->$setter($value);

            if( method_exists($this, 'get'.$name) )
                throw new Exception("Property '".get_class($this)."::".$name."' is read only.");
            else
                throw new Exception("Property '".get_class($this)."::".$name."' is not defined.");   
        }  

        public function __isset($name){
            $getter = 'get'.$name;
            if( method_exists($this, $getter) )
                return $this->$getter() !== null;

            return false;
        }

        public function __unset($name){
            $setter = 'set'.$name;
            if( method_exists($this, $setter) )
                return $this->$setter(null);

            if( method_exists($this, 'get'.$name) )
                throw new Exception("Property '".get_class($this)."::".$name."' is read only.");
            else
                throw new Exception("Property '".get_class($this)."::".$name."' is not defined.");             
        }

        public function __call($method,$args){       
            throw new Exception("Method '".get_class($this)."::".$method."()' is not defined!");
        }
        
        
        // 基类初始化
        public function __construct(){                      
            $this->options = OPEN_CONFIG;  
            $args = func_get_args();            
            $this->account = array_shift($args);    
            call_user_func_array(array($this,"init"), $args);                                              
        }
        
        // 子类初始化
        public function init(){ 
        } 
        
        // 获取或设置配置
        final public function getOptions(){  
                     
            if( empty($this->_options) )
                throw new Exception("Options is not setup."); 
            
            if( !is_array($this->_options) || !isset($this->_options["interface"], $this->_options["account"]) )
                throw new Exception("Options is not available.");
                    
            return $this->_options;           
        } 
        final public function setOptions($value){
            if( is_string($value) && is_file($value) )
                $this->_options = require($value);  
                              
            if( is_array($value) )
                $this->_options = $value;
        } 

        // 获取或设置账号
        final public function getAccount(){
            
            if( empty($this->_account) )
                throw new Exception("Account is not setup.");
            
            if( !is_array($this->_account) || !isset($this->_account["app_type"], $this->_account["app_id"], $this->_account["app_secret"]) )
                throw new Exception("Account is not available.");
                    
            return $this->_account;    
        }
        final public function setAccount($value){      
            
            if( is_string($value) && isset($this->options["account"], $this->options["account"][$value]) )
                $this->_account = $this->options["account"][$value];
            
            if( is_array($value) )
                $this->_account = $value;
        }  
        
        // curl
        static public function curl($url, $fields = array(), $type = "POST"){

            if( !is_array($fields) ){
                $type = $fields;
                $fields = array();
            }
            
            if( !is_string($type) || !in_array(strtoupper($type), array("GET","POST","PUT","HEAD","DELETE")) ){
                $type = "GET";
            }
            
            $type = strtoupper($type);
            
            $ch = curl_init();            

            if( $type != "POST" ){
                $url .= ( strstr($url, "?") == "" ? "?" : "&" ) . ( is_array($fields) && !empty($fields) ? http_build_query($fields) : "" );    
            }
            else{
                curl_setopt($ch, CURLOPT_POST, true);
                
                if( ($pos = strpos($url, "?")) !== false && $pos < strlen($url) ){
                    $query = substr($url, $pos + 1);
                    parse_str($query, $params);
                    if( !empty($params) ){
                        $url = substr($url, 0, $pos);
                        $fields = array_merge($params, $fields);
                    }                                  
                }
                
                if( is_array($fields) && count($fields) > 0 ){                
                    $postBodyString = "";
                    $postMultipart = false;
                    foreach( $fields as $k => $v ){
                        //判断是不是文件上传
                        if("@" != substr($v, 0, 1)) {
                            $postBodyString .= "$k=" . urlencode($v) . "&"; 
                        }
                        else{
                            //文件上传用multipart/form-data，否则用www-form-urlencoded                     
                            $postMultipart = true;
                        }
                    }
                    unset($k, $v);                
                    if( $postMultipart ){
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
                    }
                    else{
                        curl_setopt($ch, CURLOPT_POSTFIELDS, substr($postBodyString,0,-1));
                    }
                }
            }
            
            curl_setopt($ch, CURLOPT_URL, $url);
            
            if( substr($url,0,5) == "https" ){
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            }

            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0)");

            curl_setopt($ch, CURLOPT_FAILONERROR, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);             
            
            $reponse = curl_exec($ch);

            if(curl_errno($ch)){
                throw new Exception(curl_error($ch),0);                
            }
            else{
                $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if (200 !== $httpStatusCode){
                    //throw new Exception($reponse,$httpStatusCode);
                }
            }
            curl_close($ch);
            return $reponse;
        }     

    }
?>