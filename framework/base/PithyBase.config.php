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
        "init" => array(
            "enable" => true,
            "list" => array(
            
            ),
        ),

    ),

    // Router设置
    'Router'=>array(         
        'mode' => 'get', // 路由模式： get || pathinfo || rewrite         
        'groups' => array(),
        'default' => array(
            'group' => '',
            'module' => 'site',
            'action' => 'index', 
        ),
    ),    


    // Input设置
    'Input'=>array(

        // 过滤器
        'Filters' => array(
            array(
                "enable" => true,
                "intro" => "检查表单中的 token 值",
                "class" => "Input",
                "method" => "filterToken",
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

        // Runtime
        "Runtime" => array(
            "Enable" => false,                // 是否显示 runtime 变量
        
        ),
        
        // Debug
        "Debug" => array(
            "Enable" => true,                // 是否显示 Debug 变量跟踪
            "Append" => array(               // 需要增加的其他 Debug 变量
        
            ),
        ),
        
        // 开启gzip压缩传输
        "Gzip" => true,        

    ),

    // Controller 设置
    'Controller'=>array( 
        
        // 动态绑定
        "~~~Bind" => array(
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

        // Ajax 方式返回配置
        "Ajax" => array(
            "Flag" => "callback",       // 提交的字段中包含该字串表示用Ajax方式返回结果
            "Return" => 0,         		// 默认返回状态
            "Message" => "OK",          // 默认返回消息
        ),

        // 模板相关配置
        "Template" => array(
            "Theme"     => "",          // 默认主题
            "Layout"    => "//main",    // 默认布局
            "Suffix"    => ".php",      // 布局及模板文件的后缀
            "Message"   => "//message", // 信息显示模板的路径
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