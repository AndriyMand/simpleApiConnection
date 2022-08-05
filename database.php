<?php
/*
* Mysql database class - only one connection alowed
*/
class Database {
	private $_connection;
	private static $_instance; //The single instance
	private $_host = "localhost";
	private $_username = "...";
	private $_password = "...";
	private $_database = "...";
	public $dbcon;
	
	public static function getInstance() {
	    
		if(!self::$_instance) { // If no instance then make one
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		$this->_connection = new mysqli($this->_host, $this->_username, $this->_password, $this->_database);
		$this->_connection->set_charset("utf8");
		if(mysqli_connect_error()) {
			trigger_error("Failed to conencto to MySQL: " . mysql_connect_error(),
				 E_USER_ERROR);
		}
		$this->dbcon = self::getConnection();
	}
	
	private function __clone() { }
	
	public function getConnection() {
		return $this->_connection;
	}
	
	public function selectQuery($data, $table, $where = '', $order = null, $limit = null )
	{
	    if ($data !== '*' && is_array($data)) {
	        $cols = '';
	        
	        foreach($data as $key => $value) {
	            $cols .= is_numeric($key) ? "`$value`," : "`$value` as `$key`,";
	        }
	        
	        $data = trim($cols, ',');
	    }
	    
	    $sql = "SELECT $data FROM `$table` ";
	    
	    if ($where) {
	        $sql .= " WHERE $where ";
	    }
	    
	    if ($order) {
	        $sql .= " order by $order ";
	    }
	    
	    if ($limit) {
	        $limit = (int)$limit;
	        $sql .= " limit $limit";
	    }
	    
	    return $sql;
	}
	
	public function selectRow($data, $table, $where = '', $order = '')
	{
	    $sql = self::selectQuery($data, $table, $where, $order, 1);
	    $dbResult = $this->dbcon->query($sql);
	    if (isset($dbResult->num_rows) && $dbResult->num_rows > 0) {
	        while($record = $dbResult->fetch_assoc()) {
	            return $record;
	        }
	    }
        return array();
	}
	
	public function selectAll($data, $table, $where = '', $order = '', $limit = '')
	{
	    $sql = self::selectQuery($data, $table, $where, $order, $limit);
	    $allRecords = array();
	    $dbResult = $this->dbcon->query($sql);
	    if (isset($dbResult->num_rows) && $dbResult->num_rows > 0) {
	        while($record = $dbResult->fetch_assoc()) {
	            $allRecords[] = $record;
	        }
	    } else {
	        return array();
	    }
	    return $allRecords;
	}
	
	public function getTableFieldsTypes($table)
	{
	    $allFields = array();
	    $dbResult = $this->dbcon->query('DESCRIBE ' . $table);
	    if (isset($dbResult->num_rows) && $dbResult->num_rows > 0) {
	        while($record = $dbResult->fetch_assoc()) {
	            $allFields[$record['Field']] = $record['Type'];
	        }
	    }
	    return $allFields;
	}
	
	public function update($data, $table, $where) {
	    $updates = array();
	    $dbFieldsTypes = $this->getTableFieldsTypes($table);
	    foreach ( $data as $column => $value ) {
	        if (!empty($dbFieldsTypes[$column]) && strpos($dbFieldsTypes[$column], 'int(') !== false) {
	            $value = sprintf($this->dbcon->real_escape_string($value));
	        } else {
	            $value = "'" .sprintf($this->dbcon->real_escape_string($value)) . "'";
	        }
	        $updates[] = "$column=$value";
	    }
	    
	    $sql = "UPDATE $table SET ". implode(',', $updates) ." WHERE $where";
	    $result = $this->dbcon->query($sql);
	    if ($result) {
	        return $result;
	    } else {
	        $errorArray = array(
	            'error_description' => $this->dbcon->error,
	            'query' => $sql,
	        );
	        $this->setDbErrorLog($errorArray);
	    }
	}
	public function insert($data, $table) {
	    $inserts = array();
	    $dbFieldsTypes = $this->getTableFieldsTypes($table);
	    foreach ( $data as $column => $value ) {
	        if (!empty($dbFieldsTypes[$column]) && strpos($dbFieldsTypes[$column], 'int(') !== false) {
	            $value = sprintf($this->dbcon->real_escape_string($value));
	        } else {
	            $value = "'" .sprintf($this->dbcon->real_escape_string($value)) . "'";
	        }
	        $inserts[$column] = $value;
	    }
	    
	    if (!empty($inserts)) {
	        $fields = implode('`,`', array_keys($inserts));
	        $values = implode(",", $inserts);
	        
	        $sql = "INSERT INTO `$table` (`$fields`) VALUES ($values);";
	    } else {
	        $sql = "INSERT INTO `$table` VALUES ();";
	    }
	    $result = $this->dbcon->query($sql);
	    if ($result) {
	        return $result;
	    } else {
	        $errorArray = array(
	            'error_description' => $this->dbcon->error,
	            'query' => $sql,
	        );
	        $this->setDbErrorLog($errorArray);
	    }
	}
	public function delete($table, $where) {
	    $sql = "DELETE FROM $table WHERE $where";
	    $result = $this->dbcon->query($sql);
	    if ($result) {
	        return $result;
	    } else {
	        $errorArray = array(
	            'error_description' => $this->dbcon->error,
	            'query' => $sql,
	        );
	        $this->setDbErrorLog($errorArray);
	    }
	}
	
	public function setDbErrorLog($errorArray)
	{
        $path = 'errors_db_log';
        $arData = array(
            'date' => date('d.m.Y H:i:s'),
            $errorArray,
        );
	    return file_put_contents($path . '.log', json_encode($arData) . ",\n", FILE_APPEND);
	}
}