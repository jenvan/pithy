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


/**
 +------------------------------------------------------------------------------
 * 数据库驱动类
 +------------------------------------------------------------------------------
 * @category   Pithy
 * @package    Data
 * @subpackage Database
 * @author     jenvan <jenvan@pithy.cn>
 * @version    $Id$                                                              
 +------------------------------------------------------------------------------
 一：本数据库驱动类是一个通用的驱动接口类。
 理论上只需扩展相关的驱动程序，即可支持多种驱动类型的数据库，如 mysql,sqlite,oracle 等。
 针对不同的驱动类型，其驱动方法会在各自驱动程序中实现，而且可以暴露给本基类使用。
 例如： mysql 类型的数据库，可以使用 Database::singleton()->execute("xxx");
 
 二：配置及使用方法： 
 1、把配置作为参数在实例化类时直接使用，配置可以是数组形式也可以是DSN形式
 $db = new Database( array("type"=>"mysql", "host"=>"localhost", "username"=>"root", "password"=>"", "database"=>"test") );
 $db = new Database("mysql://username:passwd@localhost:3306/DbName");
 
 2、先定义配置文件路径，再实例化类
 define("PITHY_CONFIG_DATABASE", dirname(__FILE__)."/config.php");
 $db = new Database();
 
 3、先定义配置字串，再实例化类  
 define("PITHY_CONFIG_DATABASE_DSN", "mysql://username:passwd@localhost:3306/DbName");
 $db = new Database();
 
 注意：如果在一个会话中只想连接一次数据库，可以使用单例(建议使用)。
 $db = new Database(xxx, true); 
 OR
 $db = Database::singleton(xxx);
 
 三：本数据库类支持连贯操作，连贯操作支持的方法详见 __call 方法。     
 Database::singleton(xxx)->table("table")->where("id>10 and id<100")->order("id asc")->limit("10,20")->select();
 
 
 +------------------------------------------------------------------------------
 */ 

defined("PITHY_PATH_CONFIG") || define("PITHY_PATH_CONFIG", dirname(__FILE__));
defined("PITHY_CONFIG_DATABASE") || define("PITHY_CONFIG_DATABASE", PITHY_PATH_CONFIG.DIRECTORY_SEPARATOR."main.php");

class Database
{
    // 数据库默认配置(请勿直接改动或通过程序间接改动此配置)
    static protected $defaultConfig = array(
        'type'              => 'mysql',         // 数据库类型
        'host'              => 'localhost',     // 服务器地址
        'port'              => 3306,            // 端口
        'username'          => 'root',          // 用户名
        'password'          => '',              // 密码    
        'database'          => 'test',          // 数据库名
        'charset'           => 'utf8',          // 数据库编码默认采用gbk
        'persistent'        => false,           // 数据库是否采取持久连接
        'deploy'            => false,           // 数据库部署方式:false 集中式(单一服务器),true 分布式(主从服务器)
        'separate'          => false,           // 数据库读写是否分离 主从式有效      
        'prefix'            => '',              // 数据表前缀
        'suffix'            => '',              // 数据表后缀  
    );
    
    // 数据库驱动实例集合
    static public $instance = array();
    // 数据库 database 实例 和 驱动实例
    protected $parent = null, $driver= null;
    // 是否数据库驱动
    public $isDriver = false; 



    /* 以下是驱动子类的属性 */

    // 数据库连接参数配置
    protected $config = '';
    // 数据库类型
    protected $dbType = null;
    // 是否已经连接数据库
    protected $connected = false;
    // 数据库连接ID 支持多个连接
    protected $links = array();
    // 当前连接ID
    protected $linkID = null;
    // 数据库操作参数配置
    private $options = array();
    // 所有SQL指令
    protected $sqls = array();
    // 当前SQL指令
    protected $sql = '';
    // 当前查询ID
    protected $queryID = null;
    // 最后插入ID
    protected $lastInsID = null;
    // 返回或者影响记录数
    protected $numRows = 0;
    // 返回字段数
    protected $numCols = 0;
    // 事务指令数
    protected $transTimes = 0;
    // 查询表达式
    protected $selectSql = 'SELECT %DISTINCT% %FIELDS% FROM %TABLE% %JOIN% %WHERE% %GROUP% %HAVING% %ORDER% %LIMIT% ';
    // 比较表达式
    protected $comparison = array('eq'=>'=','neq'=>'!=','gt'=>'>','egt'=>'>=','lt'=>'<','elt'=>'<=','notlike'=>'NOT LIKE','like'=>'LIKE');

    /**
     +----------------------------------------------------------
     * 构造函数
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param mixed $config 数据库配置数组或DNS
     * @param boolean $singleton 是否单例
     +----------------------------------------------------------
     */
    function __construct($config='', $singleton=false){
        if( get_class($this) == __CLASS__ ){                
            $this->driver = self::factory($config, $singleton);
            $this->driver->parent = $this;    
        }
        else{
            $this->isDriver = true;                 
        }
    }

    /**
     +----------------------------------------------------------
     * 析构方法
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     */
    public function __destruct(){
        $object = $this->isDriver ? $this : $this->driver ;           
        if( method_exists($object, "close") )
            $object->close();
    }

    /**
     +----------------------------------------------------------
     * 利用__call方法实现一些特殊的方法
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $method 方法名称
     * @param array $args 调用参数
     +----------------------------------------------------------
     * @return mixed
     +----------------------------------------------------------
     */
    public function __call($method, $args) {

        // 连贯操作的实现 
        if( in_array(strtolower($method), array('table','field','join','where','having','group','order','limit','page','lock','distinct'), true) ) {
            $this->options[strtolower($method)] = $args[0];
            return $this;
        }

        // 返回子类方法
        if( !$this->isDriver && method_exists($this->driver, $method) )
            return call_user_func_array( array($this->driver, $method), $args );

        return self::error("Unknown method : ".$method, "ERROR");
    }

    /**
     +----------------------------------------------------------
     * 取得数据库类实例
     +----------------------------------------------------------
     * @static
     * @access public
     +----------------------------------------------------------
     * @param mixed $config 数据库配置信息 
     +----------------------------------------------------------
     * @return mixed 返回数据库类实例
     +----------------------------------------------------------
     */
    static public function singleton($config=""){ 
        $class = __CLASS__;
        return new $class($config, true);
    }

    /**
     +----------------------------------------------------------
     * 加载数据库 支持配置文件或者DSN
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param mixed $config 数据库配置信息
     * @param boolean $singleton 是否单例
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    static protected function factory($config="", $singleton=false){

        // 读取数据库配置
        $config = self::parseConfig($config); 

        // 数据库类名    
        $class = ucwords(strtolower($config["type"]));
        $hash = md5(serialize($config)); 
        if ($singleton && isset(self::$instance[$hash]) )
            return self::$instance[$hash];

        // 包含驱动类文件
        $filepath = dirname(__FILE__).DIRECTORY_SEPARATOR."drivers".DIRECTORY_SEPARATOR."database".DIRECTORY_SEPARATOR.$class.".class.php";
        if( !is_file($filepath) ){
            return self::error("Class file (".basename($filepath).") not found!"); 
        }
        require_once( $filepath );

        // 实例化驱动类
        if( class_exists($class) ){
            $driver = new $class();
            $driver->config = $config;
            $driver->dbType = strtoupper($config["type"]); 
            self::$instance[$hash] = $driver;
            return $driver;
        }

        return self::error("Not support database : ". $config['type']);
    }                  

    /**
     +----------------------------------------------------------
     * 分析数据库配置信息，支持数组和DSN
     +----------------------------------------------------------
     * @access private
     +----------------------------------------------------------
     * @param mixed $config 数据库配置信息
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    static protected function parseConfig($config="") {           

        // 如果配置为空，读取配置文件设置；
        if( empty($config) ){  
            if( defined("PITHY_CONFIG_DATABASE_DSN") ){
                $config = PITHY_CONFIG_DATABASE_DSN;
            }
            else{
                $config = require(PITHY_CONFIG_DATABASE);
                isset($config["database"]) && $config = $config["database"];
                isset($config["Database"]) && $config = $config["Database"];
                isset($config["DATABASE"]) && $config = $config["DATABASE"];
            } 
        }            
        
        // 如果配置为DSN字符串则进行解析
        if( is_string($config) ){                
            $config = self::parseDSN($config);
        }

        // 同默认配置合并
        $config = array_merge(self::$defaultConfig, $config);            
        if( empty($config) || !is_array($config) || !isset($config["type"], $config["database"]) )
            return self::error("Database config error!");

        ksort($config);                  

        return $config;
    }

    /**
     +----------------------------------------------------------
     * DSN解析
     * 格式： mysql://username:passwd@localhost:3306/DbName
     +----------------------------------------------------------
     * @static
     * @access public
     +----------------------------------------------------------
     * @param string $dsn
     +----------------------------------------------------------
     * @return array
     +----------------------------------------------------------
     */
    static protected function parseDSN($dsn) {
        if( empty($dsn) ) return false;
        $info = parse_url($dsn);
        if( $info['scheme'] ){
            $config = array(
                'type'      => $info['scheme'],
                'username'  => isset($info['user']) ? $info['user'] : '',
                'password'  => isset($info['pass']) ? $info['pass'] : '',
                'host'      => isset($info['host']) ? $info['host'] : '',
                'port'      => isset($info['port']) ? $info['port'] : '',
                'database'  => isset($info['path']) ? substr($info['path'], 1) : ''
            );
        }
        else{
            preg_match('/^(.*?)\:\/\/(.*?)\:(.*?)\@(.*?)\:([0-9]{1, 6})\/(.*?)$/', trim($dsn), $matches);
            $config = array (
                'type'      => $matches[1],
                'username'  => $matches[2],
                'password'  => $matches[3],
                'host'      => $matches[4],
                'port'      => $matches[5],
                'database'  => $matches[6]
            );
        }
        return $config;
    }

    /**
     +----------------------------------------------------------
     * 数据库错误信息
     * 并显示当前的SQL语句
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    static public function error($msg="", $level="WARNING") {  
        $type = array("NOTICE"=>E_USER_NOTICE, "WARNING"=>E_USER_WARNING, "ERROR"=>E_USER_ERROR);
        $level = strtoupper($level);
        $level = isset($type[$level]) ? $type[$level] : end($type);
        trigger_error($msg, $level);
        return null;
    }




    /**
     +----------------------------------------------------------
     * 初始化数据库连接
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param boolean $master 主服务器
     +----------------------------------------------------------
     * @return void
     +----------------------------------------------------------
     */
    protected function initConnect($master=true) {
        if( $this->config['deploy'] )
            // 采用分布式数据库
            $this->linkID = $this->multiConnect($master);
        elseif( !$this->connected )
            // 默认单数据库
            $this->linkID = $this->connect();
    }

    /**
     +----------------------------------------------------------
     * 连接分布式服务器
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param boolean $master 主服务器
     +----------------------------------------------------------
     * @return void
     +----------------------------------------------------------
     */
    protected function multiConnect($master=false) {

        $num = count( explode(",", $this->config["host"]) );

        // 数据库读写是否分离
        if( $this->config('separate')){
            // 主从式采用读写分离
            if($master)
                // 默认主服务器是连接第一个数据库配置
                $r = 0;
            else
                // 读操作连接从服务器,每次随机连接的数据库
                $r = floor(mt_rand(1, $num-1));
        }
        else{
            // 读写操作不区分服务器,每次随机连接的数据库
            $r = floor(mt_rand(0, $num-1));
        }

        $config = array();
        foreach( $this->config as $key => $value ){
            $arr = explode(",", $value);
            $config[$key] = isset($arr[$r]) ? $arr[$r] : $value;
        }

        return $this->connect($config, $r);
    }

    /**
     +----------------------------------------------------------
     * 增加数据库连接(相同类型的)
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param mixed $config 数据库连接信息
     * @param mixed $linkNum  创建的连接序号
     +----------------------------------------------------------
     * @return void
     +----------------------------------------------------------
     */
    public function addConnect($config, $linkNum=null) {
        $config = self::parseConfig($config);
        if( empty($linkNum) )
            $linkNum = count($this->links);
        if( isset($this->links[$linkNum]) )
            return false;

        return $this->connect($config, $linkNum);
    }

    /**
     +----------------------------------------------------------
     * 切换数据库连接
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param integer $linkNum  创建的连接序号
     +----------------------------------------------------------
     * @return void
     +----------------------------------------------------------
     */
    public function switchConnect($linkNum) {
        if( isset($this->links[$linkNum]) ){
            $this->linkID = $this->links[$linkNum];
            return true;
        }
        return false;
    }



    /**
     +----------------------------------------------------------
     * 设置锁机制
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    protected function parseLock($lock=false) {
        if( !$lock ) 
            return '';
        if( 'ORACLE' == $this->dbType )
            return ' FOR UPDATE NOWAIT ';
        return ' FOR UPDATE ';
    }

    /**
     +----------------------------------------------------------
     * set分析
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param array $data
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    protected function parseSet($data) {
        $arr = array();
        foreach($data as $key => $val){
            $value = $this->parseValue($val);
            if (is_scalar($value))
                $arr[] = $this->addSpecialChar($key)."=".$value;
        }
        return " SET ".implode(",", $arr);
    }

    /**
     +----------------------------------------------------------
     * value分析
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param mixed $value
     +----------------------------------------------------------
     * @return mixed
     +----------------------------------------------------------
     */
    protected function parseValue($value) {
        if (is_string($value)) {
            $value = "'".addslashes($value)."'";
        }
        elseif (is_array($value) && isset($value["eval"])){
            $value = $value["eval"];
        }
        elseif (is_array($value)) {
            $value = array_map(array($this, "parseValue"), $value);
        }
        elseif (is_null($value)) {
            $value = "'\0'";
        }
        return $value;
    }



    /**
     +----------------------------------------------------------
     * table分析
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param mixed $table
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    protected function parseTable($tables) {
        if(is_string($tables))
            $tables = explode(',',$tables);
        array_walk($tables, array(&$this, 'addSpecialChar'));
        return implode(',',$tables);
    }

    /**
     +----------------------------------------------------------
     * distinct分析
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param mixed $distinct
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    protected function parseDistinct($distinct) {
        return !empty($distinct)? ' DISTINCT ' :'';
    }

    /**
     +----------------------------------------------------------
     * field分析
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param mixed $fields
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    protected function parseField($fields) {
        if(is_array($fields)) {
            // 完善数组方式传字段名的支持
            // 支持 'field1'=>'field2' 这样的字段别名定义
            $array = array();
            foreach ($fields as $key=>$field){
                if(!is_numeric($key))
                    $array[] =  $this->addSpecialChar($key).' AS '.$this->addSpecialChar($field);
                else
                    $array[] =  $this->addSpecialChar($field);
            }
            $fieldsStr = implode(',', $array);
        }
        elseif(is_string($fields) && !empty($fields)) {
            $fieldsStr = $this->addSpecialChar($fields);
        }
        else{
            $fieldsStr = '*';
        }
        return $fieldsStr;
    }

    /**
     +----------------------------------------------------------
     * join分析
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param mixed $join
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    protected function parseJoin($join) {
        $joinStr = '';
        if(!empty($join)) {
            if(is_array($join)) {
                foreach ($join as $key=>$_join){
                    if(false !== stripos($_join,'JOIN'))
                        $joinStr .= ' '.$_join;
                    else
                        $joinStr .= ' LEFT JOIN ' .$_join;
                }
            }
            else{
                $joinStr .= ' LEFT JOIN ' .$join;
            }
        }
        return $joinStr;
    }

    /**
     +----------------------------------------------------------
     * where分析
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param mixed $where
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    protected function parseWhere($where) {
        if (empty($where)) return " ";
        (!is_array($where) || !is_array($where[0])) && $where = array($where);
        $condition =  $this->parseWhereArray($where);
        return empty($condition) ? "" : " WHERE ".$condition;
    }
    private function parseWhereString($str) {
        return $str;
    }
    private function parseWhereArray($where) {
        $logic = "AND"; // NOT AND OR XOR
        if (array_key_exists("_", $where)) {
            $logic = strtoupper($where["_"]);
            unset($where["_"]);
        }

        $arr = array();
        foreach ($where as $key => $val){
            if (!is_numeric($key)) {
                continue;
            }
            if (is_string($val)) {
                $arr[] = $this->parseWhereString($val);
                continue;
            }
            if (is_array($val[0])) {
                $arr[] = $this->parseWhereArray($val);
                continue;
            }

            // 方式1：array(sql, data)
            if (is_string($val[0]) && is_array($val[1])) {
                $sql = $val[0];
                foreach ($val[1] as $k => $v) {
                    $sql = preg_replace("/(:{$k})(\b)/", $this->parseValue($v)."$2", $sql);
                }
                $arr[] = $sql;;
            }

            // 方式2：array(key, op, value)
            if (!is_string($val[0]) || !is_string($val[1])) {
                continue;
            }
            $key = $this->addSpecialChar($val[0]);
            
            // 使用表达式
            if ("exp" == strtolower($val[1])) { 
                $arr[] = $key." ".$val[2];
            }
            // 比较运算
            elseif (preg_match("/^[=|>|<|\!]+$/i", $val[1])) {
                $arr[] = $key." ".$val[1]." ".$this->parseValue($val[2]);
            }
            // LIKE 运算
            elseif (preg_match("/^(NOT[\s]+)?LIKE$/i", $val[1]) || in_array($val[1], array("*", "!*"))) {
                $val[1] = str_replace(array("!", "*"), array("not ", "like"), $val[1]);
                $arr[] = $key." ".strtoupper($val[1])." ".$this->parseValue($val[2]);
            }
            // IN 运算
            elseif (preg_match("/^(NOT[\s]+)?IN$/i", $val[1]) || in_array($val[1], array("|", "!|"))) {
                $val[1] = str_replace(array("!", "|"), array("not ", "in"), $val[1]);
                $data = is_string($val[2]) ? explode(",", $val[2]) : $val[2];
                $data = implode(",", $this->parseValue($data));
                $arr[] = $key." ".strtoupper($val[1])." (".$data.")";
            }
            // BETWEEN 运算
            elseif (preg_match("/^(NOT[\s]+)?BETWEEN$/i", $val[1]) || in_array($val[1], array("-", "!-"))){ 
                $val[1] = str_replace(array("!", "-"), array("not ", "between"), $val[1]);
                $data = is_string($val[2]) ? explode(",", $val[2]) : $val[2];
                $data = $this->parseValue($data);
                $arr[] = $key." ".strtoupper($val[1])." ".$data[0]." AND ".$data[1];
            }
            // FIND_IN_SET 运算
            elseif (preg_match("/^(NOT[\s]+)?FIND_IN_SET$/i", $val[1]) || in_array($val[1], array("~", "!~"))) {
                $val[1] = str_replace(array("!", "~"), array("not ", "find_in_set"), $val[1]);
                $arr[] = " ".strtoupper($val[1])."(".$this->parseValue($val[2]).",".$key.")";
            }
            else {
                self::error("parse where error : ".$val[1], "WARNING");
            }
        }

        return empty($arr) ? "" : " ".(count($arr) == 1 ? $arr[0] : "(".implode(") {$logic} (", $arr).")")." ";
    }

    /**
     +----------------------------------------------------------
     * group分析
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param mixed $group
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    protected function parseGroup($group) {
        return !empty($group) ? " GROUP BY ".$group : "";
    }

    /**
     +----------------------------------------------------------
     * having分析
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param string $having
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    protected function parseHaving($having) {
        return  !empty($having) ? " HAVING ".$having : "";
    }

    /**
     +----------------------------------------------------------
     * order分析
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param mixed $order
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    protected function parseOrder($order) {
        if (is_array($order)) {
            $arr = array();
            foreach ($order as $key => $val){
                if (is_numeric($key)) {
                    $arr[] = $this->addSpecialChar($val);
                }
                else{
                    $arr[] = $this->addSpecialChar($key)." ".(strtoupper($val) == "DESC" ? "DESC" : "ASC");
                }
            }
            $order = implode(",", $arr);
        }
        return !empty($order)? " ORDER BY ".$order : "";
    }

    /**
     +----------------------------------------------------------
     * limit分析
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param mixed $lmit
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    protected function parseLimit($limit) {
        $limit = preg_replace("/[^0-9,]/", "", $limit);
        return !empty($limit)? " LIMIT ".$limit : "";
    }


    /**
     +----------------------------------------------------------
     * 查找记录
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param array $options 表达式
     +----------------------------------------------------------
     * @return array
     +----------------------------------------------------------
     */
    public function select($options=array()) {
        $options = empty($options) ? $this->options : $options;
        if(isset($options['page'])) {
            // 根据页数计算limit
            list($page,$listRows) =  explode(',',$options['page']);
            $page    = $page?$page:1;
            $listRows = $listRows?$listRows:($options['limit']?$options['limit']:20);
            $offset  =  $listRows*((int)$page-1);
            $options['limit'] =  $offset.','.$listRows;
        }
        $sql = str_replace(
        array('%TABLE%','%DISTINCT%','%FIELDS%','%JOIN%','%WHERE%','%GROUP%','%HAVING%','%ORDER%','%LIMIT%'),
        array(
        $this->parseTable($options['table']),
        $this->parseDistinct(isset($options['distinct'])?$options['distinct']:false),
        $this->parseField(isset($options['field'])?$options['field']:'*'),
        $this->parseJoin(isset($options['join'])?$options['join']:''),
        $this->parseWhere(isset($options['where'])?$options['where']:''),
        $this->parseGroup(isset($options['group'])?$options['group']:''),
        $this->parseHaving(isset($options['having'])?$options['having']:''),
        $this->parseOrder(isset($options['order'])?$options['order']:''),
        $this->parseLimit(isset($options['limit'])?$options['limit']:'')
        ),$this->selectSql);
        $sql .= $this->parseLock(isset($options['lock'])?$options['lock']:false);
        $this->options = array();
        return $this->query($sql);
    }        
    
    /**
     +----------------------------------------------------------
     * 查找一条记录
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $filed 返回指定字段
     +----------------------------------------------------------
     * @return array
     +----------------------------------------------------------
     */
    public function selectOne($filed="") {
        $this->options['limit'] = 1;        
        $rows = $this->select();
        if( !empty($rows) && isset($rows[0]) ){
            if( !empty($filed) )
                return isset($rows[0][$filed]) ? $rows[0][$filed] : null;
            return $rows[0];
        }
        return null; 
    }

    /**
     +----------------------------------------------------------
     * 插入记录
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param mixed $data 数据
     * @param array $options 参数表达式
     * @param string $mode 插入模式 replace | ignore | DUPLICATE
     +----------------------------------------------------------
     * @return false | integer
     +----------------------------------------------------------
     */
    public function insert($data, $options = array(), $mode = "") {
        if (!is_array($options)){
            $mode = $options;
            $options = array();
        }
        $options = empty($options) ? $this->options : $options;
        foreach ($data as $key => $val){
            $value = $this->parseValue($val);
            if (is_scalar($value)) {
                $fields[] = $this->addSpecialChar($key);
                $values[] = $value;
            }
        }
        $sql  = ($mode == "replace" ? 'REPLACE' : ($mode == "ignore" ? 'INSERT IGNORE' : 'INSERT')).' INTO '.$this->parseTable($options['table']).' ('.implode(',', $fields).') VALUES ('.implode(',', $values).')';
        $sql .= $this->parseLock(isset($options['lock']) ? $options['lock'] : false);
        $this->options = array();
        return $this->execute($sql);
    }

    /**
     +----------------------------------------------------------
     * 插入多条记录
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param mixed $datas 数据
     * @param array $options 参数表达式
     * @param string $mode 插入模式 replace | ignore
     +----------------------------------------------------------
     * @return false | integer
     +----------------------------------------------------------
     */
    public function insertAll($datas, $options=array(), $mode="") {
        if( !is_array($options) ){
            $mode = $options;    
            $options = array();
        } 
        $options = empty($options) ? $this->options : $options;
        if( !is_array($datas[0]) ) return false;
        $fields = array_keys($datas[0]);
        array_walk($fields, array($this, 'addSpecialChar'));
        $values = array();
        foreach($datas as $data){
            $value = array();
            foreach($data as $key=>$val){
                $val = $this->parseValue($val);
                if( is_scalar($val) ) { // 过滤非标量数据
                    $value[] = $val;
                }
            }
            $values[] = '('.implode(',', $value).')';
        }
        $sql = ( $mode == "replace" ? 'REPLACE' : ( $mode == "ignore" ? 'INSERT IGNORE' : 'INSERT' ) ).' INTO '.$this->parseTable($options['table']).' ('.implode(',', $fields).') VALUES '.implode(',',$values);
        $this->options = array();
        return $this->execute($sql);
    } 

    /**
     +----------------------------------------------------------
     * 通过Select方式插入记录
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $table 要插入的数据表名
     * @param string $fields 要插入的数据表字段名
     * @param array $option  查询数据参数
     +----------------------------------------------------------
     * @return false | integer
     +----------------------------------------------------------
     */
    public function insertSelect($table, $fields, $options=array()) {
        $options = empty($options) ? $this->options : $options;
        if(is_string($fields))  $fields = explode(',',$fields);
        array_walk($fields, array($this, 'addSpecialChar'));
        $sql  = 'INSERT INTO '.$this->parseTable($table).' ('.implode(',', $fields).') ';
        $sql .= str_replace(
        array('%TABLE%','%DISTINCT%','%FIELDS%','%JOIN%','%WHERE%','%GROUP%','%HAVING%','%ORDER%','%LIMIT%'),
        array(
        $this->parseTable($options['table']),
        $this->parseDistinct(isset($options['distinct'])?$options['distinct']:false),
        $this->parseField(isset($options['field'])?$options['field']:'*'),
        $this->parseJoin(isset($options['join'])?$options['join']:''),
        $this->parseWhere(isset($options['where'])?$options['where']:''),
        $this->parseGroup(isset($options['group'])?$options['group']:''),
        $this->parseHaving(isset($options['having'])?$options['having']:''),
        $this->parseOrder(isset($options['order'])?$options['order']:''),
        $this->parseLimit(isset($options['limit'])?$options['limit']:'')
        ),$this->selectSql);
        $sql .= $this->parseLock(isset($options['lock'])?$options['lock']:false);
        $this->options = array();
        return $this->execute($sql);
    }

    /**
     +----------------------------------------------------------
     * 更新记录
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param mixed $data 数据
     * @param array $options 表达式
     +----------------------------------------------------------
     * @return false | integer
     +----------------------------------------------------------
     */
    public function update($data, $options=array()) {
        $options = empty($options) ? $this->options : $options;
        $sql = 'UPDATE '
        .$this->parseTable($options['table'])
        .$this->parseSet($data)
        .$this->parseWhere(isset($options['where'])?$options['where']:'')
        .$this->parseOrder(isset($options['order'])?$options['order']:'')
        .$this->parseLimit(isset($options['limit'])?$options['limit']:'')
        .$this->parseLock(isset($options['lock'])?$options['lock']:false);
        $this->options = array();
        return $this->execute($sql);
    }

    /**
     +----------------------------------------------------------
     * 删除记录
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param array $options 表达式
     +----------------------------------------------------------
     * @return false | integer
     +----------------------------------------------------------
     */
    public function delete($options=array()) {
        $options = empty($options) ? $this->options : $options;
        $sql = 'DELETE FROM '
        .$this->parseTable($options['table'])
        .$this->parseWhere(isset($options['where'])?$options['where']:'')
        .$this->parseOrder(isset($options['order'])?$options['order']:'')
        .$this->parseLimit(isset($options['limit'])?$options['limit']:'')
        .$this->parseLock(isset($options['lock'])?$options['lock']:false);
        $this->options = array();
        return $this->execute($sql);
    }


    /**
     +----------------------------------------------------------
     * 数据库查询或写入次数计数
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param mixed $type
     * @param mixed $write
     +----------------------------------------------------------
     * @return void
     +----------------------------------------------------------
     */
    public function count($type='time', $write=false) {
        static $sql_counter = array("time"=>0, "query"=>0, "execute"=>0);
        if( !$write ) {
            return $sql_counter[$type];
        }
        if( $type != "time" )
            $sql_counter[$type]++;
        $sql_counter["time"] = microtime(TRUE);           
    }     

    /**
     +----------------------------------------------------------
     * 字段和表名添加 '
     * 保证指令中使用关键字不出错 针对mysql
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param mixed $value
     +----------------------------------------------------------
     * @return mixed
     +----------------------------------------------------------
     */
    protected function addSpecialChar(&$value) {
        if(0 === strpos($this->dbType, 'MYSQL')){
            $value   =  trim($value);
            if( false !== strpos($value,' ') || false !== strpos($value,',') || false !== strpos($value,'*') ||  false !== strpos($value,'(') || false !== strpos($value,'.') || false !== strpos($value,'`')) {
                //如果包含* 或者 使用了sql方法 则不作处理
            }
            else{
                $value = '`'.$value.'`';
            }
        }
        return $value;
    }  

}  
