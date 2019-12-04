<?php
    class OpenAuth extends Open { 

        private $_data = array();                 
        private $_error = array();

        static public $instance = null;        

        // 初始化
        public function init(){  

            $args = func_get_args(); 

        }


        // 获取数据信息
        public function getData(){
            return $this->_data;
        }
        // 设置数据信息
        public function setData($value){
            $this->_data=$value;    
        }

        // 获取出错信息
        public function getError(){
            return $this->_error;
        }
        // 设置出错信息
        public function setError($value){
            $this->_error=$value;    
        }    



        // 判断当前页面是否是从外部登录过来的，如果是则可以进行登录验证 validate ，否则跳转到授权页面 login
        public function check(){
            if( is_array($_GET) && ( isset($_GET["code"]) || isset($_GET["error"]) ) ) 
                return true;
            return false;       
        }         

        // 登录授权
        public function login($target="",$params=array(),$return=false){

            self::$config = $this->account;

            $target = !empty($target) ? $target : $this->account["app_redirect"];
            $target = urldecode($target);  
            if( strstr($target,"?") != "" ){                
                $target = preg_replace_callback("/=([^&]*)/",create_function('$m','return "=".urlencode($m[1]);'),$target)."";
            }                       
            self::$config["redirect_uri"] = urlencode($target);

            if( is_array($params) && !empty($params) ){
                foreach($params as $k=>$v){
                    if( substr($k,0,1) == "_" ){
                        $_k = substr($k,1);
                        self::$config[$_k] = $v;
                        unset($params[$k]);
                    }
                }
                $params["token"] = self::sign($params);             
                self::$config["state"] = urlencode(base64_encode(serialize($params)));       
            }

            $url = $this->options["interface"][$this->account["app_type"]]["authorize_code"];
            $url = preg_replace_callback("|\\{(.*?)\\}|",create_function('$m','$k=strtolower($m[1]);return isset(Open::$config[$k])?Open::$config[$k]:"";'),$url);  

            if( $return )
                return $url;                
            elseif( !headers_sent() ) 
                header("Location: {$url}");      
            else                                     
                exit("<script language='javascript'>self.location='{$url}';</script>"); 
        } 


        // 登录验证
        public function validate(){            

            if( isset($_GET["code"]) || isset($_GET["error"]) ){

                if( isset($_GET["code"]) ){

                    self::$config = $this->account;
                    self::$config["redirect_uri"] = $this->account["app_redirect"];
                    self::$config["authorization_code"] = $_GET["code"];

                    $url = $this->options["interface"][$this->account["app_type"]]["authorize_token"];
                    $url = preg_replace_callback("|\\{(.*?)\\}|",create_function('$m','$k=strtolower($m[1]);return isset(Open::$config[$k])?Open::$config[$k]:"";'),$url);  

                    // 兼容处理（部分 OAuth 接口需要 Post 方式提交数据）
                    $type = in_array($this->account["app_type"], array("taobao","weibo")) ? "POST" : "GET";
                    
                    $response = self::curl($url, $type); 
                    if( strlen($response) < 3 ){
                        $msg = "请求验证超时或返回错误！";
                    }
                    else{

                        //var_dump($response);exit;  

                        if( strstr($response,"{") != "" ){                       
                            $str = strstr($response,"{");
                            $str = substr($str,0,strpos($str,"}")+1); 
                            $obj = json_decode($str);
                            $obj = get_object_vars($obj);                            
                        }
                        else{
                            parse_str($response, $obj);  
                        } 
                        
                        //var_dump($obj);exit;                       

                        if( !is_array($obj) ){
                            $msg = "返回的URL参数未通过验证！".$response;    
                        }
                        elseif( isset($obj["error"]) ){
                            $msg = $obj["error"] . ( isset($obj["error_description"]) ? " : " . $obj["error_description"] : "" );    
                        }						
                        elseif( !isset($obj["access_token"]) ){
                            $msg = "匿名用户尚未登录！";    
                        }
                        else{

                            if( isset($_GET["state"]) ){
                                $params = unserialize(base64_decode(urldecode($_GET["state"])));                          
                                if( is_array($params) && isset($params["token"]) && $params["token"] == self::sign($params) ){
                                    $obj = array_merge($params, $obj);
                                }
                            }

                            $this->data = $obj;                                                         
                            return true;    
                        }

                    }

                }
                else{
                    $msg = $_GET["error"] . " : " .$_GET["error_description"];           
                }
                $this->error = array("error"=>"验证失败","error_description"=>$msg); 
            }
            else{
                $this->error = array("error"=>"请求无效","error_description"=>"当前请求的网址非法！");
            }                    

            return false; 
        } 


        // 一次性登录和验证
        static public function auth($account, $target="", $params=array()){

            if( is_array($target) ){
                $params = $target;
                $target = "";
            } 

            if( is_null(self::$instance) ){
                self::$instance = new OpenAuth($account); 
            }

            if( !self::$instance->check() ){
                return self::$instance->login($target, $params);
            }

            if( self::$instance->validate() ){                    
                return self::$instance->data;                                        
            }

            return self::$instance->error;
        }

        // 给指定的数组签名
        static public function sign($params){

            array_push($params, OPEN_SECRET); 

            if( isset($params["token"]) )
                unset($params["token"]);

            if(!empty($params)){
                $str="";
                foreach($params as $v){
                    $str.=trim($v);
                }                
                return base64_encode(md5($str,true));
            }    
            return null;   
        }
    }
?>