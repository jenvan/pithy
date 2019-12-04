<?php
    class OpenApi extends Open {

        public $method;
        public $session;

        public $query;
        public $object;   

        private $params;                                  

        // 魔术函数 
        public function __call($method,$args){       
            if( $method == "method" ){
                return $this->method = $args[0];
            }       
            if( $method == "session" ){
                return $this->session = $args[0];
            }               
            if( substr($method,0,3) == "get"){
                return $this->__get(substr($method,3));
            }
            if( substr($method,0,3) == "set" ){
                return $this->__set(substr($method,3),$args[0]); 
            }

            trigger_error("Method ($method) not exists!",E_USER_WARNING);
        }  

        // 初始化
        public function init(){  

            $args = func_get_args();            

            if( !empty($args[0]) ){
                if( is_string($args[0]) ){                   
                    $this->method = $args[0];
                }
                if( is_array($args[0]) ){
                    
                    self::$config = $args[0];

                    if( isset(self::$config["method"]) ){
                        $_method = self::$config["method"];
                        if( !empty($_method) )
                            $this->method = $_method;
                    }

                    if( isset(self::$config["session"]) ){
                        $_session = self::$config["session"];
                        if( !empty($_session) )
                            $this->session = $_session;
                    }
                } 
            }

            if( !empty($args[1]) ){                
                $this->session = $args[1];
            }
        }

        // 获取应用级参数
        public function get($name){        
            return isset($this->params[$name]) ? $this->params[$name] : null;
        }   

        // 设置应用级参数
        public function set($name,$value){
            if( !empty($name) ){
                if( is_array($name) ){                    
                    foreach($name as $key=>$value){
                        $this->set($key, $value);
                    }
                    return;
                }
                if( is_string($name) ){
                    if( is_null($value) && isset($this->params[$name]) ){
                        unset($this->params[$name]);
                        return;    
                    }                     
                    $this->params[$name] = $value;
                    return;
                }
            }
        }                                      

        // 获取 params 的值
        public function getParams(){              
            return is_array($this->params) ? $this->params : array();
        }  

        // 设置 params 的值
        public function setParams($data,$clear=false){ 
            if(is_null($data) || $clear){
                $this->params = array();
            } 
            if(is_array($data)){
                $this->set($data,"");
            }           
        }        


        // 调用接口
        public function execute($params=array(), $type="POST"){
            
            if(empty($this->method) || !is_string($this->method)){
                trigger_error("API method is null", E_USER_WARNING);
                return 0;
            }

            // 设置系统参数
            self::$config = array_merge($this->account, self::$config);
            self::$config["method"] = $this->method;            
            if( !empty($this->session) )
                self::$config["session"] = $this->session;

            // 设置业务参数
            if( !is_array($params) ){
                $type = $params;
                $params = array();
            }            
            $params = array_merge($this->getParams(), $params); 

            // 设置请求 url 及 type
            $url = $this->options["interface"][$this->account["app_type"]]["api_gateway"];
            $url = preg_replace_callback("|\\{(.*?)\\}|",create_function('$m','$k=strtolower($m[1]);return isset(Open::$config[$k])?Open::$config[$k]:"";'),$url);  
            $type = strtoupper($type);
            
            try{
                // 设置相关变量
                $this->query = array("url"=>$url, "params"=>$params, "result"=>"");
                $this->object = null; 
                
                // 发起HTTP请求
                $resp = self::curl($url, $params, $type);
            }
            catch(Exception $e){                
                return -1;
            } 
            
            // 保存返回的原始结果
            $this->query["result"] = $resp; 

            // 解析返回结果(只支持json格式)
            $resp = strstr($resp, "{");
            $resp = substr($resp, 0, strrpos($resp,"}")+1);
            
            // PHP5.3以下版本的json_decode存在整型数溢出问题
            $resp = preg_replace('/([^\\\\]"[^:]*":)(\d{8,})/i', '${1}"${2}"', $resp);  

            // 替换值中的 "
            $respObject = json_decode($resp);
            if( null == $respObject ){                     
                $resp = preg_replace_callback('/("[^:]*":")(.+?)("[,}\s])/', create_function('$matches','return $matches[1].str_replace("\"","\\\\\"",$matches[2]).$matches[3];'), $resp);     
                $respObject = json_decode($resp);
            }  
            
            // 返回的HTTP文本不是标准JSON或者XML，记下错误日志   
            if( null == $respObject ){
                return 0; 
            } 
            
            // 兼容淘宝
            if( $this->account["app_type"] == "taobao" ){
                $respObject = reset($respObject);    
            } 

            // 保存返回结果
            $this->object = $respObject; 

            
            return 1;
        }
        
    }
?>
