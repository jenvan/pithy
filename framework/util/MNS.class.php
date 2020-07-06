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
    
defined("PITHY_PATH_CONFIG") || define("PITHY_PATH_CONFIG", dirname(__FILE__));
defined("PITHY_CONFIG_MNS") || define("PITHY_CONFIG_MNS", PITHY_PATH_CONFIG.DIRECTORY_SEPARATOR."config.php");

/*
define("PITHY_CONFIG_MNS", serialize(
    array(
         "MNS" => array(
            "topic" => array(
                "type" => "topic",
                "host" => "$accountId.mns.cn-shenzhen.aliyuncs.com",
                "key" => "$key",
                
            ),
            "queue" => array(
                "type" => "queue",
                "host" => "$accountId.mns.cn-shenzhen.aliyuncs.com",
                "key" => "$key",
            ),
        ), 
    )
));
*/

class MNS  {
    
    private $name = null;   // 消息队列名称
    private $config = null; // 配置
    
    public $response = null; // 请求MNS接口返回的原始值
    public $message = null;  // 请求MNS接口返回的正确值
    
    
    // 初始化
    public function __construct($name) {
        
        $config = file_exists(PITHY_CONFIG_MNS) ? @require(PITHY_CONFIG_MNS) : @unserialize(PITHY_CONFIG_MNS);
        isset($config["mns"]) && $config = $config["mns"];
        isset($config["MNS"]) && $config = $config["MNS"];
        
        if (empty($config)) {
            return trigger_error("MNS 配置有误", E_USER_ERROR);
        }        
        if (empty($name) || !is_string($name) || !isset($config[$name], $config[$name]["key"], $config[$name]["type"])) {
            return trigger_error("MNS 初始化出错", E_USER_ERROR);
        }        
        if (!in_array($config[$name]["type"], array("topic", "queue"))) {
            return trigger_error("MNS 消息类型出错", E_USER_ERROR);
        }
        
        $this->name = $name;
        $this->config = $config[$name];
    }

    
    // Topic 订阅
    public function subscribe($name, $tag, $point){
        if (!$this->check("topic")) return false;
        $retry = substr($point, 0, 4) == "http" ? "EXPONENTIAL_DECAY_RETRY" : "BACKOFF_RETRY";
        $format = substr($point, 0, 4) == "http" ? "SIMPLIFIED" : "JSON";
        $content = "<?xml version='1.0' encoding='utf-8'?>\n<Subscription xmlns='http://mns.aliyuncs.com/doc/v1/'>\n<FilterTag>{$tag}</FilterTag>\n<Endpoint>{$point}</Endpoint>\n<NotifyStrategy>{$retry}</NotifyStrategy>\n<NotifyContentFormat>{$format}</NotifyContentFormat>\n</Subscription>";
        $rtn = $this->request("PUT", "/topics/{$this->name}/subscriptions/{$name}", $content);
        return $rtn == 201;
    } 
    
    // Topic 取消订阅
    public function unsubscribe($name){
        if (!$this->check("topic")) return false;
        $rtn = $this->request("DELETE", "/topics/{$this->name}/subscriptions/{$name}");
        return $rtn == 204;
    }
    
    // Topic 发布消息
    public function publish($body, $tag, $attributes = ""){
        if (!$this->check("topic")) return false;
        $content = "<?xml version='1.0' encoding='utf-8'?>\n<Message xmlns='http://mns.aliyuncs.com/doc/v1/'>\n<MessageBody>".base64_encode(json_encode($body))."</MessageBody>\n<MessageTag>{$tag}</MessageTag>\n{$attributes}</Message>";
        $rtn = $this->request("POST", "/topics/{$this->name}/messages", $content);
        return $rtn == 201;
    }
    
    
    // 发送消息
    public function send($body, $delay = 0){
        if (!$this->check("queue")) return false;
        $content = "<?xml version='1.0' encoding='UTF-8'?>\n<Message xmlns='http://mns.aliyuncs.com/doc/v1/'>\n<MessageBody>".base64_encode(json_encode($body))."</MessageBody>\n<DelaySeconds>".intval($delay)."</DelaySeconds>\n</Message>";
        $rtn = $this->request("POST", "/queues/{$this->name}/messages", $content);
        return $rtn == 201;
    }
    
    // 接收消息
    public function receive($destroy = true){
        if (!$this->check("queue")) return false;
        $rtn = $this->request("GET", "/queues/{$this->name}/messages");
        $rtn == 200 && $destroy && $this->destroy();
        return $rtn == 200 ? $this->message["MessageBody"] : null;
    }
    
    // 延长消息可见时间
    public function delay($timeout, $id = ""){
        if (!$this->check("queue")) return false;
        empty($id) && isset($this->message["ReceiptHandle"]) && $id = $this->message["ReceiptHandle"];
        $rtn = $this->request("PUT", "/queues/{$this->name}/messages?receiptHandle={$id}&visibilityTimeout=".intval($timeout));
        return $rtn == 200;
    }
    
    // 删除消息
    public function destroy($id = ""){
        if (!$this->check("queue")) return false;
        empty($id) && isset($this->message["ReceiptHandle"]) && $id = $this->message["ReceiptHandle"];
        $rtn = $this->request("DELETE", "/queues/{$this->name}/messages?receiptHandle={$id}");
        return $rtn == 204;
    }
    

    protected function check($type){
        if ($this->config["type"] != $type){
            trigger_error("该消息的类型不支持此操作", E_USER_ERROR);
            return false;
        }
        return true;
    }
    
    protected function request($method, $uri, $content = ""){
        
        $appkey = explode(",", $this->config["key"]);
        
        $token = empty($content) ? "" : base64_encode(md5($content));
        $type = "text/xml";
        $date = gmdate("D, d M Y H:i:s")." GMT";
        $sign = base64_encode(hash_hmac("sha1", "{$method}\n{$token}\n{$type}\n{$date}\nx-mns-version:2015-06-06\n{$uri}", $appkey[1], true));
        $header = array(
            "Authorization: MNS ".$appkey[0].":".$sign,
            "Content-Length: ".strlen($content),
            "Content-Type: ". $type,
            "Date: ".$date,
            "x-mns-version: 2015-06-06",
        );
        !empty($content) && $header[] = "Content-MD5: {$token}";
        
        $data = HTTP::curl("http://".$this->config["host"].$uri, $content, array(
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $header,
        ));
        
        //Pithy::debug($header, $content, $data);
        
        $this->response = HTTP::$response;
        $this->message = $this->obj2array(@simplexml_load_string($data));
        
        isset($this->message["MessageBody"]) && $this->message["MessageBody"] = json_decode(base64_decode($this->message["MessageBody"]), true);
        
        //Pithy::debug($this->response);
        
        return HTTP::$status;
    }
    
    protected function obj2array($obj) {
        if (is_object($obj)) {
            $obj = get_object_vars($obj);
        }
        if (is_array($obj)) {
            foreach ($obj as $key => $val) {
                $obj[$key] = $this->obj2array($val);
            }
        }
        return $obj;
    }
}
