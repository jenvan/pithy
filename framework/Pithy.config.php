<?php
if( !defined("PITHY") ) exit;
    
// 系统默认配置
return array(

    // 项目设定
    "App" => array(

        "Name"              => "精炼PHP（PITHY PHP） -- Pithy.CN",  // 站点名称
        "Timezone"          => "PRC",                               // 设置时区
        "Charset"           => "utf-8",                             // 设置编码
        "ContentType"       => "text/html",                         // 设置类型
        "Secret"            => md5(__FILE__),                       // 密钥
        "Debug"             => false,                               // 是否开启调试
        
        // 预定义的常量
        "Define" => array(
            /*
            "PITHY_PATH_CONFIG" => PITHY_APPLICATION."config".DIRECTORY_SEPARATOR,            
            "PITHY_CONFIG_DATABASE" => PITHY_APPLICATION."config".DIRECTORY_SEPARATOR."main.php",
            "PITHY_CONFIG_STORAGE" => PITHY_APPLICATION."config".DIRECTORY_SEPARATOR."main.php",
            "PITHY_CONFIG_OPEN" => PITHY_APPLICATION."config".DIRECTORY_SEPARATOR."main.php", 
            "PITHY_CONFIG_TOP" => PITHY_APPLICATION."config".DIRECTORY_SEPARATOR."main.php", 
            */
        ), 
        
        // 预加载的类
        "Preload" => array(
            /*
            "cache" => array(                                 
                "path" => "#.data.Storage",
                "class" => "Storage",
                "params" => array("cache"),    
            ), 
            "queue" => array(                                 
                "path" => "#.data.Storage",
                "class" => "Storage",
                "params" => array("queue"),    
            ), 
            */       
        ),            
        
        // 类自动装载功能
        "Autoload" => array(
            "Enable"   => true,                 // 是否开启SPL_AUTOLOAD_REGISTER
            "Path"     => "",                   // __autoLoad 机制额外检测路径设置
        ),
        
        // 日志记录设置
        "Log" => array(
            "Level"         => true,                                        // 开启记录等级  true, false, array("ALERT", "ERROR", "WARNING")
            "Size"          => 200*1024*1024,                               // 记录文件最大字节数
        ),
        
        // 错误处理设置         
        "Error" => array(
            "Trace"       => true,    // 是否开启错误跟踪
            "Log"         => true,    // 是否开启错误记录
            "Display"     => false,    // 是否开启错误信息显示
            "Message"     => "系统出错，请稍后再试！",    // 自定义的错误信息
        ), 

        // Cookie 设置
        "Cookie" => array(
            "Expire"         => 0,       // Coodie 有效期
            "Domain"         => "",      // Cookie 有效域名
            "Path"           => "/",     // Cookie 路径
            "Prefix"         => "pithy", // Cookie 前缀(避免冲突)
        ),
        
        // Execute 设置
        "Execute" => array(
            "Enable"    => true,
            "SafeMode"  => false,
            "Alias"     => array(
                //"ping"  => "ping {ip}",
            ),        
        ), 
               
    ),

);  