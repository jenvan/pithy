<?php
if( !defined('PITHY') ) exit;
    
// 系统默认配置
return array(
           
    // 命令行设置
    'Command' => array(
        'Root' => PITHY_APPLICATION."command/",
        'Map' => array(
            
        ),
    ),

     // HOOK 设置
    'Hook'=>array(

        // 是否允许使用钩子
        "Enable" => false,

        // init 钩子
        "Init" => array(
            "enable" => true,
            "list" => array(
            
            ),
        ),

    ),

    // Router设置
    'Router'=>array(
        'Groups' => array(),
        'Default' => array(
            'group' => '',
            'module' => 'site',
            'action' => 'index', 
        ),
        'Map' => array(
            'uid' => '\/_([^\/]+)_\/'
        ),
    ),


    // Input设置
    'Input'=>array(

        // 过滤器
        'Filters' => array(
            array(
                "enable" => true,
                "intro" => "检查 SqlInject",
                "class" => "Input",
                "method" => "filterSqlInject",
            ),
            array(
                "enable" => true,
                "intro" => "检查 Xss",
                "class" => "Input",
                "method" => "filterXss",
            ),
        ),
        
    ),

    // Output设置
    'Output'=>array(

        // 过滤器
        'Filters' => array(
            array(
                "enable" => true,
                "intro" => "替换表单中指定标签为 token",
                "class" => "Output",
                "method" => "filterTokenBuild",
                "params" => array(
                    "tag" => "__PITHY_FORM_TOKEN__",
                ),
            ),        
        ),

        // Cache设置
        "Cache" => array(
            "Expires"   => 0,           // 缓存时间，小于等于零时不缓存
            "Folder"    => "html",      // 缓存文件存放目录名称
            "Suffix"    => ".html",     // 缓存文件名后缀
            "Ignore"    => "",          // 缓存文件名生成时需要忽略的参数名    
        ),    
        
        // Replace    
        "Replace" => array(            
            "__POWERED__" => "<a class='pithy_powered' href='http://pithy.cn' target='_blank'>Powered By Pithy</a>",    
            "__VERSION__" => PITHY_VERSION,    
        ),
        
        // Debug
        "Debug" => array(
            "Enable" => false,                  // 是否允许显示调试信息（总开关）
            "Trace"=> true,                     // 是否显示 trace 变量
            "Runtime"=> true,                   // 是否显示 runtime 变量
            "Benchmark"=> true,                 // 是否显示 benchmark 变量
            "Log"=> true,                       // 是否显示 log 变量
            "Append" => array(                  // 需要增加的其他变量
        
            ),
        ),
        
        // 开启gzip压缩传输
        "Gzip" => true,        

    ),

    // Controller 设置
    'Controller'=>array( 
        
        // 动态绑定
        "Bind" => array(
            "name" => array(
                "class" => "~.bind.ControllerExt",
                "params" => array(
                    "test" => "abc123",
                ),
            ),
        ),
        
        // 默认动作集合
        "Actions" => array(),
        
        // 默认动作过滤集合
        "Filters" => array(),
        
        // 默认动作规则集合
        "Rules" => array(),
        
    ),

    // View 设置
    'View'=>array( 

        // 资源管理
        'Assets' => array(
            "Publish" => false,
            "Root" => "",
            "Prefix" => "/assets/",
        ),

        // 简单页面显示配置
        "Show" => array(
            "Return" => 0,         		// 信息显示默认返回状态
            "Success" => "OK",          // 信息显示默认返回成功消息
            "Failure" => "ERR",         // 信息显示默认返回失败消息
            "Template" => "//message",  // 信息显示模板的路径
            "Direct" => false,          // 直接显示JSON
            "Ajax" => "callback",       // Ajax方式返回结果
            "Script" => "script",       // Script方式返回结果
        ),

        // 模板相关配置
        "Template" => array(
            "Theme"     => "",          // 默认主题
            "Layout"    => "//main",    // 默认布局
            "Suffix"    => ".php",      // 布局及模板文件的后缀
        ),
        
        // 模板标签
        "Tag" => array(
            "Prefix"        => "<!--{",
            "Suffix"        => "}-->",  
            "Block"         => "block",
            "BlockBegin"    => "block_begin",
            "BlockEnd"      => "block_end",            
            "Content"       => "content",
        ),
        
    ),


    // Model设置
    'Model'=>array(

    ),





);  