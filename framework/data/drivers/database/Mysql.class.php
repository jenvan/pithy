<?php
// +----------------------------------------------------------------------
// | PithyPHP [ 精练PHP ]
// +----------------------------------------------------------------------
// | Copyright (c) 2010 http://pithy.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed (http://www.apache.org/licenses/LICENSE-2.0)
// +----------------------------------------------------------------------
// | Author: jenvan <jenvan@pithy.cn>
// +----------------------------------------------------------------------

define('CLIENT_MULTI_RESULTS', 131072);

/**
 +------------------------------------------------------------------------------
 * 数据库中间层实现类
 * Mysql 类
 +------------------------------------------------------------------------------
 * @category   Pithy
 * @package  Data
 * @subpackage  Database
 * @author    jenvan <jenvan@pithy.cn>
 * @version   $Id$
 +------------------------------------------------------------------------------
 */  
class Mysql extends Database { 
    
    /**
     +----------------------------------------------------------
     * 连接数据库方法
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     */
    public function connect($config = '', $linkNum = 0) {

        if (!isset($this->links[$linkNum])) {
            
            empty($config) && $config = $this->config;
            if (!isset($config["host"]) || empty($config["database"]))
                return self::error("配置错误！", "ERROR");

            $this->links[$linkNum] = mysqli_connect(($config["persistent"] ? "p:" : "").$config['host'], $config['username'], $config['password'], $config['database'], (!empty($config['port']) ? $config['port'] : 3306));
            if (!$this->links[$linkNum])
                return self::error("数据库连接失败！", "ERROR");
            
            // 使用UTF8存取数据库 需要mysql 4.1.0以上支持
            mysqli_query($this->links[$linkNum], "SET NAMES '".$this->config['charset']."'");

            // 强制不设定MySql模式（如不作输入检测、错误提示、语法模式检查等）应该能提高性能
            mysqli_query($this->links[$linkNum], "SET sql_mode=''");
     
        }

        // 标记连接成功
        $this->connected = true;

        return $this->links[$linkNum];
    }

    /**
     +----------------------------------------------------------
     * 释放查询结果
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     */
    public function free() {
        if (!empty($this->queryID))
            mysqli_free_result($this->queryID);
        $this->queryID = 0;
    }

    /**
     +----------------------------------------------------------
     * 关闭数据库
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     */
    public function close() {
        $this->free();
        if ($this->linkID && !mysqli_close($this->linkID))
            return self::error($this->getLastError(), "ERROR"); 
        $this->linkID = 0;
    } 


    /**
     +----------------------------------------------------------
     * 执行查询 返回数据集
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $str  sql指令
     +----------------------------------------------------------
     * @return mixed
     +----------------------------------------------------------
     */
    public function query($str) {
        
        $this->initConnect(false);
        if (!$this->linkID) 
            return self::error($this->getLastError(), "ERROR");
 
        $this->free();

        $this->sql = $str;
        array_push($this->sqls, $str);
        $this->count("query", true);
        
        $this->queryID = mysqli_query($this->linkID, $str);
        if (false === $this->queryID) {
            return self::error($this->getLastError(), "ERROR");
        } 
        else{
            $this->numRows = mysqli_num_rows($this->queryID);
            $result = array();
            if ($this->numRows > 0) {
                while($row = mysqli_fetch_assoc($this->queryID)){
                    $result[] = $row;
                }
                mysqli_data_seek($this->queryID, 0);
            }
            return $result;
        }
    }

    /**
     +----------------------------------------------------------
     * 执行语句
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $str  sql指令
     +----------------------------------------------------------
     * @return integer
     +----------------------------------------------------------
     */
    public function execute($str) {
        
        $this->initConnect(true);
        if (!$this->linkID)
            return self::error($this->getLastError(), "ERROR");

        $this->free();

        $this->sql = $str;
        array_push($this->sqls, $str);
        $this->count("execute", true);

        $result = mysqli_query($this->linkID, $str);
        if (false === $result) {
            return self::error($this->getLastError(), "ERROR");
        } 
        else {
            $this->numRows = mysqli_affected_rows($this->linkID);
            $this->lastInsID = mysqli_insert_id($this->linkID);
            return max($this->numRows, $this->lastInsID);
        }
    }

    /**
     +----------------------------------------------------------
     * 启动事务
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @return void
     +----------------------------------------------------------
     */
    public function startTrans() {
        $this->initConnect(true);
        if (!$this->linkID) 
            return false;            
        if ($this->transTimes == 0) {
            mysqli_query($this->linkID, 'START TRANSACTION');
        }
        $this->transTimes++;
        return ;
    }

    /**
     +----------------------------------------------------------
     * 用于非自动提交状态下面的查询提交
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @return boolen
     +----------------------------------------------------------
     */
    public function commit() {
        if ($this->transTimes > 0) {
            $result = mysqli_query($this->linkID, 'COMMIT');
            $this->transTimes = 0;
            if (!$result){
                return self::error($this->getLastError(), "ERROR");
            }
        }
        return true;
    }

    /**
     +----------------------------------------------------------
     * 事务回滚
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @return boolen
     +----------------------------------------------------------
     */
    public function rollback() {
        if ($this->transTimes > 0) {
            $result = mysqli_query($this->linkID, 'ROLLBACK');
            $this->transTimes = 0;
            if (!$result){
                return self::error($this->getLastError(), "ERROR");
            }
        }
        return true;
    }

    /**
     +----------------------------------------------------------
     * 获取所有查询的sql语句
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    public function getAllSql() {
        return $this->sqls;
    }
	
    /**
     +----------------------------------------------------------
     * 获取最近一次查询的sql语句
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    public function getLastSql() {
        return $this->sql;
    }

    /**
     +----------------------------------------------------------
     * 获取最近一次 insert ID
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    public function getLastId() {
        return $this->lastInsID;
    }

    /**
     +----------------------------------------------------------
     * 获取最近的错误信息
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    public function getLastError() {
        $error = empty($this->linkID) ? "连接错误" : mysqli_error($this->linkID);
        if (!empty($error) && '' != $this->getLastSql()){
            $error .= "\t[SQL] : ".$this->getLastSql();
        }
        return $error;
    }

    /**
     +----------------------------------------------------------
     * 取得数据库的表信息
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     */
    public function getTables($dbName='') {
        if (!empty($dbName)) {
            $sql = 'SHOW TABLES FROM '.$dbName;
        }
        else{
            $sql = 'SHOW TABLES ';
        }
        $result = $this->query($sql);
        $info   = array();
        foreach($result as $key => $val) {
            $info[$key] = current($val);
        }
        return $info;
    } 

    /**
     +----------------------------------------------------------
     * 取得数据表的字段信息
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     */
    public function getFields($tableName) {
        $result = $this->query('SHOW COLUMNS FROM '.$tableName);
        $info   = array();
        if ($result) {
            foreach($result as $key => $val) {
                $info[$val['Field']] = array(
                'name'    => $val['Field'],
                'type'    => $val['Type'],
                'notnull' => (bool) ($val['Null'] === ''), // not null is empty, null is yes
                'default' => $val['Default'],
                'primary' => (strtolower($val['Key']) == 'pri'),
                'autoinc' => (strtolower($val['Extra']) == 'auto_increment'),
               );
            }
        }
        return $info;
    }

    /**
     +----------------------------------------------------------
     * 编码
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $str
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    public function escape($str){
        return addslashes($str);
    } 

}