<?php
/**
 * 字符串处理类
 */

class String
{

    static public function encode($str, $type = "escape"){
        if ($type == "base64"){
            $data = base64_encode($str);
            $data = str_replace(array('+','/','='), array('-','_',''), $data);
            return $data;
        }

        if ($type == "escape"){
            preg_match_all("/[\x80-\xff].|[\x01-\x7f]+/", $str, $r);
            $arr = $r[0];
            foreach ($arr as $k => $v){
                if (ord($v[0]) < 128){
                    $arr[$k] = rawurlencode($v);
                }
                else{
                    $arr[$k] = "%u".strtoupper(bin2hex(mb_convert_encoding($v, "UCS-2","GB2312")));
                }
            }
            return join("", $arr);
        }

        return $str;
    }

    static public function decode($str, $type = "escape"){
        if ($type == "base64"){
            $data = str_replace(array('-','_'), array('+','/'), $str);
            $mod4 = strlen($data) % 4;
            if ($mod4){
                $data .= substr('====', $mod4);
            }
            return base64_decode($data);
        }

        if ($type == "escape"){
            preg_match_all("/(%u[0-9|A-F]{4})/", $str, $r);
            $arr = $r[0];
            foreach($arr as $k => $v){
                if (substr($v,0,2) == "%u" && strlen($v) == 6){
                    $str = str_replace($v, mb_convert_encoding(pack("H4", substr($v, -4)), "GB2312", "UCS-2"), $str);
                }
            }
            return rawurldecode($str);
        }

        return $str;
    }

    static public function encrypt($str, $secret = "") {
        empty($secret) && $secret = md5(__FILE__);
        return base64_encode(openssl_encrypt($str, "aes-256-ecb", $secret, OPENSSL_RAW_DATA));
    }

    static public function decrypt($str, $secret = "") {
        empty($secret) && $secret = md5(__FILE__);
        return trim(openssl_decrypt(base64_decode($str), "aes-256-ecb", $secret, OPENSSL_RAW_DATA));
    }

    static public function sign(){
        if (func_num_args() == 0)
            return null;

        $args = func_get_args();
        $arr = is_array($args[0]) ? $args[0] : $args;
        if (array_keys($arr) !== range(0, count($arr) - 1)){
            unset($arr["sign"]);
            ksort($arr);
            $str = urldecode(http_build_query($arr));
            isset($args[1]) && $str = $args[1].$str.$args[1];
        }
        else{
            $str = implode("|", $arr);
        }
        
        return md5($str);
    }

    static public function convert($content, $from = "gbk", $to = "utf-8"){
        $from = in_array(strtoupper($from), array("UTF","UTF8","UTF-8")) ? 'UTF-8' : strtoupper($from);
        $to = in_array(strtoupper($to),array("UTF","UTF8","UTF-8")) ? 'UTF-8' : strtoupper($to);
        if (empty($content) || (is_scalar($content) && !is_string($content))){
            return $content;
        }
        if (is_string($content)) {

            if (preg_match('/^.*$/u', $content) > 0)
                $from = "UTF-8";

            if ($from != $to) {
                if (function_exists('mb_convert_encoding')){
                    return mb_convert_encoding($content, $to, $from);
                }
                elseif (function_exists('iconv')){
                    return iconv($from,$to,$content);
                }
            }

            return $content;
        }
        elseif (is_array($content)){
            foreach ($content as $key => $val) {
                $_key = self::convert($key, $from, $to);
                $content[$_key] = self::convert($val, $from, $to);
                if ($key != $_key )
                    unset($content[$key]);
            }
            return $content;
        }
        elseif (is_object($content)){
            foreach ($content as $key => $val) {
                $content->$key = self::convert($val, $from, $to);
            }
            return $content;
        }
        else{
            return $content;
        }
    } 

    static public function named($str) {
        if (strstr($str,"_") != ""){
            return ucfirst(preg_replace_callback("/_([a-zA-Z])/m", create_function('$m', 'return strtoupper($m[1]);') , $str));
        }
        if (preg_match("/[A-Z]/", $str)){
            $str = preg_replace("/[A-Z]/", "_\\0", $str);
            return strtolower(trim($str, "_"));
        }
        return $str;
    }

    static public function safe($str, $all = false){
        return $all ? htmlentities($str,ENT_QUOTES) : htmlspecialchars($str,ENT_QUOTES);
    }

    static public function strip($str, $all = true){
        $pattern = array("/<script[^>]+\/>/sim", "/<script[^>]*>.*?<\/script>/sim", '/(<[^>]+[\s]+)(on[a-z]+=[^>|\/|\s]+)/sim', '/(java|js|vb)(script)/im');
        $str = preg_replace($pattern, array("", "", "$1","$1 $2"), $str);
        if ($all) {
            $arr = array("meta", "base", "link", "iframe", "frame", "frameset");
            foreach ($arr as $tag) {
                $str = preg_replace(array("/<{$tag}[^>]+\/>/sim", "/<{$tag}[^>]*>.*?<\/{$tag}>/sim"), "", $str);
            }
        }
        return $str;
    }



    /**
     * 切割中文字符串(等长和非等长)
     * 
     * @param mixed $content 字符内容
     * @param mixed $charset 字符编码
     * @param mixed $size  切块长度，即最多支持的中文字数
     * @param mixed $equilong 是否等长切割，是则按照字符数算，否则按字数算
     * @return array 切割后的字符数组，可以通过 count 数组来获取切割的数量
     */
    static public function split($content, $charset=null, $size=100, $equilong = true){ 

        // 字符编码
        if (empty($charset))
            $charset = strlen("好") == 3 ? "UTF-8" : "GBK"; 

        // 切割后的字符串长度
        if (empty($size))
            $size = 100;
            
            
        // 切割后的字符串保存数组
        $arr = array(); 
            
        
        // ******** 非等长切割 ********
        if (!$equilong){
            
            // 总字数
            $len = mb_strlen($content, $charset);
            
            if ($len <= $size)
                return array($content);
            
            for ($i = 0; $i < $len; $i = $i + $size){
                $arr[] = mb_substr($content, $i, $size, $charset);
            }
            return $arr;
        }
        
            
        // ******** 等长切割 ******** 
                    
        // 中文字符占位长度
        $remainder = $charset == "GBK" ? 2 : 3;
        // 总长度
        $total = strlen( $content );
        // 当前长度
        $len = 0;
        // 当前位置
        $pos = 0; 

        for ($i = 0; $i < $total; $i++,$len++){

            if ($size - $len < $remainder){
                if ($charset != "GBK"){
                    $num = ord(mb_substr($content, $i, 1));
                    $num = $num < 192 ? 0 : ($num <= 224 ? 1 : 2);
                }
                else {
                    $rtn = preg_match_all("/[".chr(0xa1)."-".chr(0xff)."]/", mb_substr($content, $pos, $len), $res);
                    $num = (isset($res[0]) && fmod(count($res[0]), 2) == 0) ? 1 : 0 ;
                }

                if ($num > 0 || $size == $len){
                    $arr[] = mb_substr($content, $pos, $len);
                    $len = 0;
                    $pos = $i; 
                }
            }

            if ($total - $pos <= $size){
                $arr[] = mb_substr($content, $pos, $total - $pos);
                break;
            } 
        }

        return $arr;
    }

}