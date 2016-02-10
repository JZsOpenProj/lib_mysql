<?php

/*
$_config = array();
$_config['db']['hostname'] = '127.0.0.1';//服务器地址
$_config['db']['username'] = 'root';//数据库用户名
$_config['db']['password'] = 'cta2015';//数据库密码
$_config['db']['database'] = 'ssy'; //数据库名称
$_config['db']['charset'] = 'utf8';//数据库编码
$_config['db']['pconnect'] = 0;//开启持久连接
$_config['db']['tablepre'] = 'ssy_';
$_config['db']['log'] = 0;//开启日志
$_config['db']['logfilepath'] = './';//开启日志
*/


class mysql_db {
	private $link;
	
	private $dbhost;
	private $dbuser;
	private $dbpw;
	private $dbname;
	private $pconnect;
	private $charset;
	
	private $tablepre;
	
	private $handle;
	private $is_log;
	private $time;

	private  $goneaway = 5;
	//构造函数
	public function __construct() {
	    $this->time = $this->microtime_float();
	    $db_config=$GLOBALS['_config']['db'];
	    $this->connect($db_config["hostname"], $db_config["username"], $db_config["password"], $db_config["database"], $db_config["pconnect"]);
	    $this->tablepre=$db_config["tablepre"];
	    $this->is_log = $db_config["log"];
	    if($this->is_log){
	        $handle = fopen(SSY_DATADIR."logs/dblog.txt", "a+");
	        $this->handle=$handle;
	    }
	}	
	//数据库连接
	private function connect($dbhost, $dbuser, $dbpw, $dbname, $pconnect = 0,$charset='utf8') {
	    $this->dbhost=$dbhost;
	    $this->dbuser=$dbuser;
	    $this->dbpw=$dbpw;
	    $this->dbname=$dbname;
	    $this->pconnect=$pconnect;
	    $this->charset=$charset;
	    if($pconnect) {
	        if(!$this->link = mysql_pconnect($dbhost, $dbuser, $dbpw)) {
	            $this->halt("数据库持久连接失败");
	        }
	    } else {
	        if(!$this->link = mysql_connect($dbhost, $dbuser, $dbpw)){
	            $this->halt("数据库连接失败");
	        }
	    }
	    if($this->version() > '4.1') {
	        if($charset) {
	            mysql_query("SET character_set_connection=".$charset.", character_set_results=".$charset.", character_set_client=binary", $this->link);
	        }
	    
	        if($this->version() > '5.0.1') {
	            mysql_query("SET sql_mode=''", $this->link);
	        }
	    }
	    if(!@mysql_select_db($dbname,$this->link)) {
	        $this->halt('数据库选择失败');
	    }
	    
	}
	//查询
	private function query($sql) {
	    $this->write_log("查询 ".$sql);
	    $query = mysql_query($sql,$this->link);
	    if(!$query) $this->halt('Query Error: ',$sql);
	    return $query;
	}
	 
	//获取一条记录（MYSQL_ASSOC，MYSQL_NUM，MYSQL_BOTH）
	public function get_one($list,$table,$condition,$result_type = MYSQL_ASSOC) {
	    $sql = "SELECT $list FROM $table WHERE $condition LIMIT 0,1";
	    $query = $this->query($sql);
	    $rt =mysql_fetch_array($query,$result_type);
	    $this->write_log("获取一条记录 ".$sql);
	    return $rt;
	}
	
	//获取全部记录
	public function get_all($list,$table,$condition,$addition='',$key='',$result_type = MYSQL_ASSOC) {
	    $sql = "SELECT $list FROM $table WHERE $condition $addition";
	    $query = $this->query($sql);
	    $i = 0;
	    $rt = array();
	    while($row =mysql_fetch_array($query,$result_type)) {
	        $rt[empty($key)?$i:$row[$key]]=$row;
	        $i++;
	    }
	    $this->write_log("获取全部记录 ".$sql);
	    return $rt;
	}
	
	//插入
	public function insert($table,$dataArray) {
	    $field = "";
	    $value = "";
	    if( !is_array($dataArray) || count($dataArray)<=0) {
	        $this->halt('没有要插入的数据');
	        return false;
	    }
	    while(list($key,$val)=each($dataArray)) {
	        $field .="$key,";
	        $value .="'$val',";
	    }
	    $field = substr( $field,0,-1);
	    $value = substr( $value,0,-1);
	    $sql = "insert into $table($field) values($value)";
	    $this->write_log("插入 ".$sql);
	    if(!$this->query($sql)) return false;
	    return mysql_affected_rows();
	}
	
	//更新
	public function update( $table,$dataArray,$condition="") {
	    if( !is_array($dataArray) || count($dataArray)<=0) {
	        $this->halt('没有要更新的数据');
	        return -1;
	    }
	    $value = "";
	    while(list($key,$val) = each($dataArray))
	        $value .= "$key = '$val',";
	    $value = substr( $value,0,-1);
	    $sql = "update $table set $value where 1=1 and $condition";
	    $this->write_log("更新 ".$sql);
	    if(!$this->query($sql)) return false;
	    return mysql_affected_rows();
	}
	
	//删除
	public function delete( $table,$condition="") {
	    if( empty($condition) ) {
	        $this->halt('没有设置删除的条件');
	        return false;
	    }
	    $sql = "delete from $table where 1=1 and $condition";
	    $this->write_log("删除 ".$sql);
	    if(!$this->query($sql)) return false;
	    return true;
	}
	
	//返回结果集
	public function fetch_array($query, $result_type = MYSQL_ASSOC){
	    $this->write_log("返回结果集");
	    return mysql_fetch_array($query, $result_type);
	}
	
	//获取记录条数
	public function num_rows($results) {
	    if(!is_bool($results)) {
	        $num = mysql_num_rows($results);
	        $this->write_log("获取的记录条数为".$num);
	        return $num;
	    } else {
	        return 0;
	    }
	}
	
	//释放结果集
	public function free_result() {
	    $void = func_get_args();
	    foreach($void as $query) {
	        if(is_resource($query) && get_resource_type($query) === 'mysql result') {
	            return mysql_free_result($query);
	        }
	    }
	    $this->write_log("释放结果集");
	}
	
	//获取最后插入的id
	public function insert_id() {
	    $id = mysql_insert_id($this->link);
	    $this->write_log("最后插入的id为".$id);
	    return $id;
	}
	
	//关闭数据库连接
	protected function close() {
	    $this->write_log("已关闭数据库连接");
	    return @mysql_close($this->link);
	}
	
	//错误提示
	function halt($message = '', $sql = '') {
	    $error = mysql_error();
	    $errorno = mysql_errno();
	    if($errorno == 2006 && $this->goneaway-- > 0) {
	        $this->connect($this->dbhost, $this->dbuser, $this->dbpw, $this->dbname, $this->pconnect,$this->charset);
	        $this->query($sql);
	    } else {
	        $s = '';
	        if($message) {
	            $s = "<b>SSY info:</b> $message<br />";
	        }
	        if($sql) {
	            $s .= '<b>SQL:</b>'.htmlspecialchars($sql).'<br />';
	        }
	        $s .= '<b>Error:</b>'.$error.'<br />';
	        $s .= '<b>Errno:</b>'.$errorno.'<br />';
	        $this->write_log($s);
	        exit($s);
	    }
	}

	function version() {//取得 MySQL 服务器信息。
		return mysql_get_server_info($this->link);
	}	
	 
	//写入日志文件
	public function write_log($msg=''){
	    if($this->is_log){
	        $text = date("Y-m-d H:i:s")." ".$msg."\r\n";
	        fwrite($this->handle,$text);
	    }
	}
	 
	//获取毫秒数
	public function microtime_float() {
	    list($usec, $sec) = explode(" ", microtime());
	    return ((float)$usec + (float)$sec);
	}
	
	function error() {//返回上一个 MySQL 操作产生的文本错误信息。
		return (($this->link) ? mysql_error($this->link) : mysql_error());
	}

	function errno() {//返回上一个 MySQL 操作中的错误信息的数字编码。
		return intval(($this->link) ? mysql_errno($this->link) : mysql_errno());
	}
	//析构函数
	public function __destruct() {
	    $this->free_result();
	    $use_time = ($this-> microtime_float())-($this->time);
	    $this->write_log("完成整个查询任务,所用时间为".$use_time);
	    if($this->is_log){
	        fclose($this->handle);
	    }
	}

}
