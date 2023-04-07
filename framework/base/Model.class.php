<?php
// +----------------------------------------------------------------------
// | PithyPHP [ 精练PHP ]
// +----------------------------------------------------------------------
// | Copyright (c) 2010 http://pithy.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed  (http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: jenvan <jenvan@pithy.cn>
// +----------------------------------------------------------------------

define('HAS_ONE', 1);
define('BELONGS_TO', 2);
define('HAS_MANY', 3);
define('MANY_TO_MANY', 4);

/**
 +------------------------------------------------------------------------------
 * Model 模型类
 * 实现 ORM 模式
 +------------------------------------------------------------------------------
 * @category   Pithy
 * @package  Pithy
 * @subpackage  Core
 * @author Jenvan <jenvan@pithy.cn>
 * @version  $Id$
 +------------------------------------------------------------------------------
 */
class Model extends PithyBase {

    // 模型名称
    private $name = "";

    // 数据库配置
    protected $connection = "";
    // 数据库操作对象
    protected $db = null;
    // 数据库名称
    protected $dbName = "";

    // 数据表名（包含表前后缀）
    protected $tableName = "";
    // 数据表前缀
    protected $tablePrefix = null;
    // 数据表后缀
    protected $tableSuffix = null;

    // 字段信息
    protected $field = array();
    // 字段为 json 类型
    protected $json = array();
    // 主键名称
    protected $pk = "id";


    // 查询信息
    private $query = array();

    // 模型数据（经过处理）
    private $data = array();

    // 本模型是否已指定数据集
    private $assigned = false;
    

    /**
     +----------------------------------------------------------
     * 构造函数
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param mixed $name   模型名称（ @开头表示完整数据表名）
     * @param array $config 配置参数
     +----------------------------------------------------------
     */
    public function initialize($name = "", $config = array()) {

        if (is_array($name)){
            $config = $name;
            $name = "";
        }
        
        // 模型属性赋值
        $arr = array_keys(get_object_vars($this));
        foreach ($config as $key => $val) {
            if (in_array($key, $arr)) {
                $rp = new ReflectionProperty($this, $key);
                if ($rp->isProtected()) {
                    $rp->setAccessible(true);
                    $rp->setValue($this, $val);
                }
            }
        }

        // 获取模型名称
        if (!empty($name)) {
            $this->name = preg_replace("/^@/", "", $name);
            substr($name, 0, 1) == "@" && $this->tablePrefix = "";
        }
        elseif(empty($this->name)){
            $this->name = substr(get_class($this), 0, -5);
        }
        $this->name = ucfirst($this->convert($this->name, true));

        // 数据库初始化操作（获取数据库操作对象，当前模型有独立的数据库连接信息）
        $this->db = Pithy::instance("Database", array(!empty($this->connection) ? $this->connection : ""));
        
        // 设置表前后缀
        is_null($this->tablePrefix) && $this->tablePrefix = Pithy::config("Model.Prefix");
        is_null($this->tableSuffix) && $this->tableSuffix = Pithy::config("Model.Suffix");
        
        $this->query["table"] = $this->fetchTableName();
        is_string($this->json) && $this->json = explode(",", $this->json);
        
        // 子类初始化
        method_exists($this, "_init") && call_user_func(array($this, "_init"), $config);
    }

    /**
     +----------------------------------------------------------
     * 设置数据对象的值
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $name 名称 ( _ 前缀的变量非模型的属性，例如 field 中包含count(*) as _num )
     * @param mixed $value 值
     +----------------------------------------------------------
     * @return void
     +----------------------------------------------------------
     */
    public function __set($name, $value) {
        $name = $this->convert($name, false);
        if (parent::__isset($name)) {
            return parent::__set($name, $value);
        }
        $data = substr($name, 0, 1) != "_" ? $this->filter(array($name => $value)) : array($name => $value);
        if (!in_array($name, array_keys($data))) {
            throw new Exception("模型属性 {$name} 不存在");
        }
        $this->data[$name] = $data[$name];
    }

    /**
     +----------------------------------------------------------
     * 获取数据对象的值
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $name 名称
     +----------------------------------------------------------
     * @return mixed
     +----------------------------------------------------------
     */
    public function __get($name) {
        if (in_array($name, array("tableName", "tableField", "tablePK"))) {
            $getter = "fetch".ucfirst($name);
            return $this->$getter();
        }
        $name = $this->convert($name, false);
        if (parent::__isset($name)) {
            return parent::__get($name);
        }
        if (self::__isset($name)) {
            return $this->data[$name];
        }
        if (empty($this->data)) {
            throw new Exception("模型属性数据为空");
        }
        throw new Exception("模型属性 {$name} 不存在");
    }

    /**
     +----------------------------------------------------------
     * 检测数据对象的值
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $name 名称
     +----------------------------------------------------------
     * @return boolean
     +----------------------------------------------------------
     */
    public function __isset($name) {
        $name = $this->convert($name, false);
        return parent::__isset($name) || in_array($name, array_keys($this->data));
    }

    /**
     +----------------------------------------------------------
     * 销毁数据对象的值
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $name 名称
     +----------------------------------------------------------
     * @return void
     +----------------------------------------------------------
     */
    public function __unset($name) {
        $name = $this->convert($name, false);
        if (parent::__isset($name)) {
            parent::__unset($name);
        }
        if (self::__isset($name)) {
            unset($this->data[$name]);
        }
    }


    /**
     +----------------------------------------------------------
     * 获取数据表名
     +----------------------------------------------------------
     * @access private
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    private function fetchTableName() {
        $tableName = !empty($this->tableName) ? $this->tableName : $this->convert(lcfirst($this->name), false);
        !empty($this->tablePrefix) && $tableName  = $this->tablePrefix . $tableName;
        !empty($this->tableSuffix) && $tableName .= $this->tableSuffix;
        !empty($this->dbName) && $tableName = $this->dbName.".".$tableName;
        return $tableName;
    }

    /**
     +----------------------------------------------------------
     * 获取数据表字段
     +----------------------------------------------------------
     * @access private
     +----------------------------------------------------------
     * @return array
     +----------------------------------------------------------
     */
    private function fetchTableField() {
        if (!empty($this->field)) return $this->field;
        
        $name = $this->name.".Field";
        $this->field = Pithy::cache($name);
        if (empty($this->field)) {
            $this->field = array();
            $arr = $this->db->getFields($this->fetchTableName());
            foreach ($arr as $key => $val) {
                $this->field[$key] = $val["type"];
                $val["primary"] && empty($this->pk) && $this->pk = $key;
            }
            Pithy::cache($name, $this->field);
        }
        return $this->field;
    }

    /**
     +----------------------------------------------------------
     * 获取数据表主键
     +----------------------------------------------------------
     * @access private
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    private function fetchTablePK() {
        if (!empty($this->pk)) return $this->pk;
        $this->fetchTableField();
        return $this->pk;
    }

    /**
     +----------------------------------------------------------
     * 载入模型数据
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param mixed $data 配置参数
     +----------------------------------------------------------
     * @return object
     +----------------------------------------------------------
     */
    public function load($data) {
        if (empty($data) || !is_array($data)) {
            $this->data = array();
        }
        else {
            foreach ($data as $key => $val) {
                $this->__set($key, $val);
            }
        }
        $this->assigned = !empty($this->data);
        return $this;
    }

    /**
     +----------------------------------------------------------
     * 清空模型数据
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @return object
     +----------------------------------------------------------
     */
    public function clear() {
        return $this->load(null);
    }


    /**
     +----------------------------------------------------------
     * 设置查询条件
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param mixed $condition 表达式参数
     * @param mixed $data 表达式内容
     +----------------------------------------------------------
     * @return object 对象本身
     +----------------------------------------------------------
     */
    public function where($condition = "", $data = array()) {
        $this->assigned = false;

        if (empty($condition)) {
            $this->query["where"] = array();
            return $this;
        }
        
        empty($this->query["where"]) && $this->query["where"] = array();

        if (is_array($condition)) {
            $this->query["where"][] = $condition;
        }
        else if (is_numeric($condition) || (is_string($condition) && preg_match("/^[\w\.\-_]+$/", $condition))) {
            $this->query["where"][] = empty($data) ? array($this->fetchTablePK(), "=", $condition) : array($condition, "=", $data);
        }
        else if (is_string($condition) && is_array($data)) {
            $this->query["where"][] = array($condition, $data);
        }

        //$this->debug($this->query["where"]);
        return $this;
    }

    /**
     +----------------------------------------------------------
     * 设置查询范围
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param mixed $exp 表达式
     +----------------------------------------------------------
     * @return object 对象本身
     +----------------------------------------------------------
     */
    public function scope($exp) {
        $args = is_array($exp) ? $exp : func_get_args();
        foreach ($args as $arg){
            $arg = trim($arg);
            if ($arg == "*" || preg_match("/^[a-z][\w]+$/", $arg) || preg_match("/^([a-z][\w|\(|\*|\)]+)(([\s]?,[\s]?[a-z][\w|\(|\*|\)]+)*)$/", preg_replace("/([\s]+as[\s]+[^,]+)/", "", $arg))){
                $this->query["field"] = $arg;
                continue;
            }
            if (preg_match("/^group[\s]+by[\s]+/i", $arg)){
                $this->query["group"] = preg_replace("/^group[\s]+by[\s]+/i", "", $arg);
                continue;
            }
            if (preg_match("/^having[\s]+/i", $arg)){
                $this->query["having"] = preg_replace("/^having[\s]+/i", "", $arg);
                continue;
            }
            if (preg_match("/.+[\s]+(de|a)sc$/i", $arg) || $arg == "rand()"){
                $this->query["order"] = $arg;
                continue;
            }
            if (is_numeric($arg) || preg_match("/^\d+[\s]?,[\s]?\d+$/", $arg)){
                $this->query["limit"] = $arg;
                continue;
            }
        }

        //$this->debug($this->query);
        return $this;
    }


    /**
     +----------------------------------------------------------
     * 搜索器
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param mixed $name 搜索器名称
     * @param array $data 搜索参数
     * @param array $alias 参数别名
     +----------------------------------------------------------
     * @return object 对象本身
     +----------------------------------------------------------
     */
    public function search($name, $data, $alias = array()) {
        is_string($name) && $name = explode(",", $name);
        if (!empty($alias)) {
            foreach ($alias as $key => $val) {
                $data[$key] = $data[$val];
            }
        }
        foreach ($name as $item) {
            $runner = "search".ucfirst($item);
            if (!method_exists($this, $runner)) throw new Exception("模型搜索器 {$runner} 不存在");
            $this->$runner($data);
        }
        return $this;
    }

    /**
     +----------------------------------------------------------
     * 查询数据集
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $field 要查询的字段（单个字段，查询结果为键值为主键、内容为字段的数组）
     * @param boolean $force 未查询到数据是否抛出异常
     +----------------------------------------------------------
     * @return mixed
     +----------------------------------------------------------
     */
    public function select($field = "", $force = false) {
        is_bool($field) && $force = $field;

        $this->query["table"] = $this->fetchTableName();

        $rows = null;
        $rtn = Pithy::trigger("model.read.before", array($this, &$this->query));
        $rtn && $rows = $this->db->select($this->query);
        $this->query = array();
        if (empty($rows)) {
            if ($force) throw new Exception(is_null($rows) ? "模型数据查询出错" : "模型未查询到符合条件的数据");
            return $rows;
        }

        if (!empty($this->json)) {
            foreach ($rows as $i => $row) {
                foreach ($this->json as $key) {
                    $rows[$i][$key] = json_decode($row[$key], true, 512, JSON_BIGINT_AS_STRING);
                }
            }
        }

        Pithy::trigger("model.read.after", array($this, &$rows));
        
        is_string($field) && !empty($field) && $rows = array_column($rows, $field, $this->fetchTablePK());
        
        return $rows;
    }

    /**
     +----------------------------------------------------------
     * 查询单条数据
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $field 字段名称（单个字段）
     * @param boolean $force 未查询到数据是否抛出异常
     +----------------------------------------------------------
     * @return mixed
     +----------------------------------------------------------
     */
    public function find($field = "", $force = false) {
        is_bool($field) && $force = $field;

        $this->query["limit"] = 1;

        $rows = $this->select($force);

        $this->load(empty($rows) ? null : $rows[0]);

        $data = $this->data;
        foreach ($data as $key => $val) {
            $data[$this->convert($key, true)] = $val;
        }

        if (empty($field)) return $data;

        if (isset($data[$field])) return $data[$field];

        if ($force) throw new Exception("模型未查询到符合条件的指定数据");

        return null;
    }

    /**
     +----------------------------------------------------------
     * 保存数据
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param array $data 数据
     * @param mixed $allow 允许保存的字段（数组或用,隔开的字符串）
     * @param mixed $force 强制保存模式 (true|false|"":不为空时，若where不为空则先查询再判断是update还是insert)
     +----------------------------------------------------------
     * @return boolean 是否保存成功
     +----------------------------------------------------------
     */
    public function save($data = array(), $allow = "", $force = "") {
        if (empty($data)) {
            if  (empty($this->data)) {
                throw new Exception("模型缺少数据");
            }
            $data = $this->data;
        }
        if (is_bool($allow)) {
            $force = $allow;
            $allow = "";
        }

        $pk = $this->fetchTablePK();

        // 数据过滤
        if (!empty($allow)) {
            is_string($allow) && $allow = explode(",", $allow);
            $data = array_intersect_key($data, array_flip($allow));
        }
        $data = $this->filter($data, true);

        // 判断数据库操作
        is_bool($force) && !empty($this->query["where"]) && $this->find();
        $this->assigned && $this->where(array($pk, "=", $this->data[$pk]));
        $job = empty($this->query["where"]) ? "insert" : "update";

        // 保存前事件
        $this->query["table"] = $this->fetchTableName();
        if (false === Pithy::trigger("model.write.before.".$job, array($this, &$this->query, &$data))) {
            return false;
        }

        // 保存
        $mode = is_bool($force) ? ($force ? "replace" : "ignore") : "";
        $rtn = $this->db->$job($data, $this->query, $mode);
        $this->query = array();

        // 保存后事件
        $job == "insert" && $data[$pk] = $rtn;
        $this->load($data);
        Pithy::trigger("model.write.after.".$job, array($this, &$this->data));

        return !empty($rtn);
    }

    /**
     +----------------------------------------------------------
     * 删除数据
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $key 主键
     +----------------------------------------------------------
     * @return int 删除的记录条数
     +----------------------------------------------------------
     */
    public function delete($key = null) {
        
        // 如果删除条件为空，则删除当前数据对象所对应的记录
        $pk = $this->fetchTablePK();
        if ($this->assigned && empty($key)) {
            if (empty($this->data) || !isset($this->data[$pk]))
                return false;
            $key = $this->data[$pk];
        }

        // 根据主键删除记录
        if (is_numeric($key) || is_string($key)) {
            $this->where(array($pk, strpos($key, ",") ? "IN" : "=", $key));
        }

        // 判断
        if (empty($this->query["where"]))
            throw new Exception("模型未设置删除条件");

        // 删除前事件
        $this->query["table"] = $this->fetchTableName();
        if (false === Pithy::trigger("model.write.before.delete", array($this, &$this->query))) {
            return false;
        }

        // 删除
        $rtn = $this->db->delete($this->query);
        $this->query = array();

        // 删除后事件
        Pithy::trigger("model.write.after.delete", array($this, &$this->data));
        $this->load(null);

        // 返回删除记录条数
        return $rtn;
    }

    /**
     +----------------------------------------------------------
     * call db method
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $method
     +----------------------------------------------------------
     * @return mixed
     +----------------------------------------------------------
     */
    public function call($method = "", $params = array()) {
        return call_user_func_array(array($this->db, $method), $params);
    }

    /**
     +----------------------------------------------------------
     * 驼峰命名 和 蛇形命名 相互转换
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $str 名称
     * @param boolean $toCamel 强制转换成 驼峰命名
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    protected function convert($str, $toCamel = true) {
        if ($toCamel) {
            preg_match("/[\w]_/i", $str) && $str = lcfirst(preg_replace_callback("/_([a-zA-Z])/m", create_function('$m', 'return strtoupper($m[1]);') , strtolower($str)));
        }
        else {
            $str = strtolower(preg_replace("/[A-Z]/", "_\\0", $str));
        }
        return $str;
    }

    /**
     +----------------------------------------------------------
     * 对数据进行处理
     +----------------------------------------------------------
     * @access protected
     +----------------------------------------------------------
     * @param mixed $data 要操作的数据
     * @param boolean $save 是否为保存到数据库
     +----------------------------------------------------------
     * @return array 处理后的数据
     +----------------------------------------------------------
     */
    protected function filter($data, $save = false) {

        $field = $this->fetchTableField();
        if (empty($field)) return $data;

        foreach ($data as $key => $val){
            if (!in_array($key, array_keys($field))) {
                unset($data[$key]);
                continue;
            }

            if ($save && in_array($key, $this->json)) {
                $data[$key] = json_encode($val, JSON_UNESCAPED_UNICODE);
                continue;
            }

            if (is_scalar($val)) {
                $type = strtolower($field[$key]);
                if (false !== strpos($type, "char")) {
                    $data[$key] = trim(addslashes($val));
                }
                elseif (false !== strpos($type, "int")) {
                    $data[$key] = preg_replace("/[^\d]+/", "", $val);
                }
                elseif (false !== strpos($type, "float") || false !== strpos($type, "double") || false !== strpos($type, "real") || false !== strpos($type, "decimal") || false !== strpos($type, "numberic")){
                    $data[$key] = floatval($val);
                }
                elseif (false !== strpos($type, "date") || false !== strpos($type, "time")) {
                    $data[$key] = preg_replace("/[^0-9:\-\s]+/", "", $val);
                }
                if (!$save && in_array($key, $this->json)) {
                    $data[$key] = json_decode($val, true, 512, JSON_BIGINT_AS_STRING);
                }
            }
            elseif (!is_null($val) && !in_array($key, $this->json)) {
                throw new Exception("模型属性 {$key} 的数据类型不符");
            }
            
        }

        return $data;
    }
}