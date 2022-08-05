<?php
    class CRest
	{
		const BATCH_COUNT    = 50;//count batch 1 query
		const TYPE_TRANSPORT = 'json';// json or xml
		const SCOPE          = 'crm';
		
		const APP_TYPE_FREE  = 1;
		const APP_TYPE_PRO   = 2;
		
		const ENCODE_KEY = '...';
		
		private $_connection;
		private static $_instance; //The single instance
		private $_host     = "...";
		private $_username = "...";
		private $_password = "...";
		private $_database = "...";
		
		public $appType;
		public $expiredDate;
		public $expiredDays;
		public $license;
		
		public $dbcon;
		public $domain;
		public $liqpay;
		
		public $APP_ID;
		public $C_REST_CLIENT_ID;
		public $C_REST_CLIENT_SECRET;
		public $C_REST_WEB_HOOK_URL;
		public $REDIRECT_URI;
		
		public $uninstallHandler;
		
		public $lang    = '';
		public $isAdmin = 0;
		public $memberId;
		public $dialogId;
		
		public $userId = 0;
		public $isConcurent = 0;
		
		public $tablePortalUsers = 'portals_users';
		public $tableTokens     = 'marketplace_tokens';
		public $tableUsers      = 'marketplace_users';
		public $tableApps       = 'applications_settings';
		public $tableWebform    = 'webform';
		public $tableAppHistory = 'marketplace_app_user_history';
		public $tableMessages   = 'messages_history';
		public $tableFormGenerator = 'form_generator';
		public $tableDealStages = 'logging_deal_stages';
		
		
		public $appName;
		
		public $domainData;
		public $tokenData;
		public $windowType;
		
		public $access_token;
		
		public $oliCodes;
		public $openlineCode;
		public $dialogCode = 0;
		
		
		public $devriseLeadDbId = 0;
		public $devriseLeadId   = 0;
		
		public $devriseAppDbId  = 0;
		public $devriseAppId    = 0;
		
		public $devriseUserDbId = 0;
		public $devriseUserId   = 0;
		
		function __construct($domain, $APP_ID, $C_REST_CLIENT_ID, $C_REST_CLIENT_SECRET, $REDIRECT_URI, $memberId, $lang, $access_token, $checkConnection, $windowType = '-') {
		    $this->domain = $domain;
		    $this->APP_ID = $APP_ID;
		    
		    $appsNames = [
		        'devrise.manager_calls' => 'Аналітика дзвінків по кожному менеджеру',
		        'devrise.birthday_notifier' => 'Нагадування про день народження',
		        'devrise.responsible_queue' => 'Черга відповідальних за ліди',
		        'devrise.currency_cource' => 'Завжди актуальний курс валют',
		        'devrise.salary_accounting' => 'Облік зарплат',
		        'devrise.chat_emodji' => 'Смайлики в чатах',
		        'devrise.products_analytics' => 'Звіт із проданих товарів',
		        'devrise.chat_sticker' => 'Стікери в чатах',
		        'devrise.tasks_report' => 'Моніторинг завантаження співробітників (PRO)',
		        'devrise.responsible_queue_ex' => 'Автоматичний розподіл лідів (PRO)',
		        'devrise.leads_to_b' => 'Ліди з сайту',
		        'devrise.deal_on_invoice' => 'Автоматична зміна статусу угоди',
		        'devrise.invoice_notifier' => 'Сповіщення в рахунках',
		        'devrise.form_generator' => 'Опитувальники / Анкети. DevRise Форми',
		    ];
		    
		    $this->appName = !empty($appsNames[$this->APP_ID]) ? $appsNames[$this->APP_ID] : $this->APP_ID;
		    
		    $this->openlineCode = '7af6d88a422156d0ad0bbc84bc824412';
		    
		    if ( !$C_REST_CLIENT_ID ) {
		        $this->C_REST_WEB_HOOK_URL = $access_token;
		    }
		    
		    $this->C_REST_CLIENT_ID     = $C_REST_CLIENT_ID;      //Application ID
		    $this->C_REST_CLIENT_SECRET = $C_REST_CLIENT_SECRET;  //Application key
		    $this->REDIRECT_URI         = $REDIRECT_URI;
		    $this->memberId             = $memberId;
		    $this->lang                 = $lang;
		    $this->windowType           = $windowType;
		    
		    $this->access_token         = $access_token;
		    
		    $this->oliCodes = array(
		        'ua' => '7af6d88a422156d0ad0bbc84bc824412',
		        'en' => '853474b94f0b160944a633604d4819cb',
		        'ru' => '5ed81fa8d8f3350bf85730d5ad92f51d',
		    );
		    
		    $this->_connection = new mysqli($this->_host, $this->_username, $this->_password, $this->_database);
		    $this->_connection->set_charset("utf8");
		    if(mysqli_connect_error()) {
		        trigger_error("Failed to conencto to MySQL: " . mysql_connect_error(),
		            E_USER_ERROR);
		    }
		    $this->dbcon = self::getConnection();
		    
		    
		    if ( $this->domain ) {
		        $dbData = $this->selectRow('*', 'domains_data', "domain='$this->domain'");
		        if ( !empty($dbData) ) {
		            $this->devriseLeadDbId = $dbData['id'];
		            $this->devriseLeadId   = $dbData['object_id'];
		        }
		    }
		    if ( $this->domain && $this->APP_ID ) {
		        $dbData = $this->selectRow('*', 'marketplace_tokens', "domain='$this->domain' AND app_id='$this->APP_ID'");
		        if ( !empty($dbData) ) {
		            $this->devriseAppDbId = $dbData['id'];
		            $this->devriseAppId   = $dbData['object_id'];
		            $this->dialogId       = $dbData['dialog_id'];
		        }
		    }
		    
		    if ( $checkConnection ) {
		        $checkPortalConnection = $this->checkPortalConnection();
		        $this->userId  = $checkPortalConnection['userId'];
		        $this->isAdmin = $checkPortalConnection['isAdmin'];
		        
		        $this->insertIntoHistory();
		    }
		}
		
		public function sendOpenlineMessage($userId, $message, $code = '') {
		    
		    
            $fields = array(
                'CODE'    => !$code ? $this->openlineCode : $code,
                'USER_ID' => $userId,
                'MESSAGE' => $message,
            );
            $result = $this->call('imopenlines.network.message.add', $fields);
            if ( !empty($result['error']) ) {
                $fields = array(
                    'USER_ID'     => $userId,
                    'MESSAGE'     => $message,
                    'MESSAGE_OUT' => $message,
                    'TAG'         => 'new_application',
                );
                $result = $this->call('im.notify.system.add', $fields);
            }
            return $result;
		}
		
		public function prepareToJson( $array ) {
		    $preparedArray = array();
		    if ( is_array($array) ) {
		        foreach ($array as $key => $value) {
		            $preparedArray[$key] = prepareToJson($value);
		        }
		    } else {
		        if ( !empty($array) ) {
		          $preparedArray = str_replace("'","\'", str_replace('"','\"', $array));
		        }
		    }
		    return $preparedArray;
		}
		
		public function sendMessage(array $to, string $message) {
		    $batchArray = array();
		    $index = 1;
		    $section = 1;
		    $results = array();
		    foreach ($to as $userId) {
		        $batchArray[$section]['im.notify.system.add_' . $userId] = array(
		            'method' => 'im.notify.system.add',
		            'params' => array(
		                'USER_ID' => $userId,
		                'MESSAGE' => $message,
		            )
	            );
		        if ( $index >= 50) {
		            $section++;
		            $index = 0;
		        }
		        $index++;
		    }
		    
		    if ( !empty($batchArray) ) {
		        foreach ( $batchArray as $batch ) {
		            $batchResult = $this->callBatch($batch)['result'];
		            $results[] = $batchResult;
		        }
		    }
		    return $results;
		}
		
		public function timeToDays($time) {
		    return  round($time / (60 * 60 * 24));
		}
		
		public function getDateRange($dateFrom, $dateTo) {
		    $range = array();
		    $date_from = strtotime($dateFrom);
		    $date_to   = strtotime($dateTo);
		    for ($i=$date_from; $i<=$date_to; $i+=86400) {
		        $range[] = date("d.m.Y", $i);
		    }
		    return $range;
		}
		
		public static function getInstance() {
		    if(!self::$_instance) { // If no instance then make one
		        self::$_instance = new self();
		    }
		    return self::$_instance;
		}
		
		public function createPayment($monthCount, $price, $description, $domain, $appCode, $isRegular, $urlParams) {
		    
		    $params = array(
		        'domain'      => $domain,
		        'appCode'     => $appCode,
		        'price'       => $price,
		        'monthCount'  => $monthCount,
		        'description' => $description,
		        'isRegular'   => $isRegular,
		    );
		    
		    $payParams = array(
		        'action'         => 'pay',
		        'amount'         => $price,
		        'currency'       => 'UAH',
		        'description'    => $description . " Портал {$domain}",
		        'order_id'       => $domain . '_' . $appCode . '_' . time(),
		        'version'        => '3',
				'server_url'     => 'https://dr.com.ua/marketplace/payment.php?' . http_build_query($params),
		    );
		    if ( !empty($isRegular) ) {
		        $payParams['action'] = 'subscribe';
		        $payParams['subscribe_date_start'] = date('Y-m-d H:i:s', strtotime('+5 minutes'));
		        if ( $monthCount > 1 ) {
		            $payParams['subscribe_periodicity'] = 'year';
		        } else {
		            $payParams['subscribe_periodicity'] = 'month';
		        }
		    }
		    $html = $this->liqpay->cnb_form($payParams);
		    return $html;
		}
		
		public function createPaymentQr() {
		    
		    
		    $payParams = array(
		        'action'         => 'payqr',
		        'version'        => '3',
		        'amount'         => '1',
		        'currency'       => 'USD',
		        'description'    => 'description text',
		        'order_id'       => 'order_id_1'
		    );
		    $html = $this->liqpay->api("request", $payParams);
		    return $html;
		}
		
		
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
		            $value = $this->dbcon->real_escape_string($value);
		        } else {
		            $value = "'" .$this->dbcon->real_escape_string($value) . "'";
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
		            if ( !$value ) {
		                $value = 0;
		            }
		        } else {
		            if ( !empty(trim($value)) ) {
                        $value = "'" .sprintf($this->dbcon->real_escape_string($value)) . "'";
		            } else {
		                $value = "''";
		            }
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
		        return $this->dbcon->insert_id;
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
		    $fileName = 'errors_db_log';
		    
		    $path = '/home/mandyb01/app.devrise.com.ua/logs/' . $this->APP_ID . '/' . date("m.Y");
		    @mkdir($path, 0775, true);
		    $path .= '/' . $fileName;
		    
		    $arData = array(
		        'date'   => date('d.m.Y H:i:s'),
		        'domain' => $this->domain,
		        'error'  => $errorArray,
		    );
		    return file_put_contents($path . '.json', $this->wrapData($arData) . "\n", FILE_APPEND);
		}
		
		public function isJSON($string){
		    return is_string($string) && is_array(json_decode($string, true)) ? true : false;
		}
		
		public function parceQuery($qstring) {
		    $args = explode('&', $qstring);
		    $data = array();
		    for ($i = 0; $i < count($args); $i++) {
		        $param = explode('=', $args[$i]);
		        if(empty($data[$param[0]]))
		            $data[$param[0]] = array();
		            
		            if(!empty($param[1]))
		                $data[$param[0]][] = $param[1];
		    }
		    return $data;
		}
		
		public function getBatch($prevId, $arParams, $method, $isTask = 0, $isItem = 0, $isUser = 0)
		{
		    $batch = [];
		    for ($i = 0; $i < 50; $i++) {

		        if ( $isItem ) {
		            $arParams['filter']['>id'] = $prevId;
		        } else {
		            $arParams['filter']['>ID'] = $prevId;
		        }
		        
		        $arParams['start'] = -1;
		        
		        $batch['step_' . $i] = [
		            'method' => $method,
		            'params' => $arParams
		        ];
		        
		        if ( $isItem ) {
		            $prevId = '$result[step_'.$i.'][items][49][id]';
		        } elseif ( $isTask ) {
		            $prevId = '$result[step_'.$i.'][tasks][49][id]';
		        } else {
		            $prevId = '$result[step_'.$i.'][49][ID]';
		        }
		    }
		    
		    return $batch;
		}

		public function getObjectListExtended($method, $arParams)
		{
		    $prevId = 0;
		    $isTask = $method == 'tasks.task.list' ? 1 : 0;
		    $isItem = $method == 'crm.item.list' ? 1 : 0;
		    $isUser = $method == 'user.get' ? 1 : 0;
		    
		    $objects = [];
		    while (true) {
		        
		        $batch  = $this->getBatch($prevId, $arParams, $method, $isTask, $isItem);
		        $result = $this->callBatch($batch);
		        
		        if ( !empty($result['result']['result']) ) {
		            foreach ($result['result']['result'] as $list) {
		                
		                if ( $isTask ) {
		                    $list = $list['tasks'];
		                }
		                if ( $isItem ) {
		                    $list = current($list);
		                }
		                $count = count($list);
		                
	                    foreach ($list as $object)
	                    {
	                        $objects[] = $object;
	                        $count++;
	                    }
		                
		                $last = end($list);
		                
		                if ( $count < 50 ) {
		                    break 2;
		                    
		                } elseif (!$isTask && $last['ID'] > $prevId) {
		                    $prevId = $last['ID'];
		                    
		                } elseif ($isTask && $last['id'] > $prevId) {
		                    $prevId = $last['id'];
		                    
		                } else {
		                    break 2;
		                }
		                
		                
		            }
		        } else {
		            break;
		        }
		    }
		    
		    return $objects;
		}
		
		public function getObjectList($fields, $method, $defaultData = array()){
		    $elements = array();
		    $defaultCount = 0;
	        if ( !empty($defaultData['result']) ) {
	            $defaultCount = $defaultData['total'];
	        }
	        
	        if ( $defaultCount == 0 ) {
	            
	            if ( !empty($fields['SELECT']) ) {
	                $fields['SELECT'] = $fields['SELECT'];
	            } elseif ( !empty($fields['select']) ) {
	                $fields['SELECT'] = $fields['select'];
	            } else {
	                $fields['SELECT'] = array('*');
	            }
	            
	            $defaultData = $this->call($method, $fields);
	            if ( !empty($defaultData['result']) ) {
                    $defaultCount = $defaultData['total'];
	            }
	        }

	        if ( !empty($defaultData['result']) ) {
		        if ( $defaultCount > 50 ) {
		            $maxBatches = ceil ($defaultCount/2500);
		            $next = 0;
		            for($k = 0; $k < $maxBatches;$k++) {
		                $fieldsForBatch = array();
		                $total = $k+1 == $maxBatches ? $k*2500 + $defaultCount - (($k)*2500) : ($k+1)*2500;
		                //echo $total."\n";
		                for ($i = $next; $i <= $total; $i += 50) {
		                    $fields['start'] = $i;
		                    if ( !empty($fields['SELECT']) ) {
		                        $fields['SELECT'] = $fields['SELECT'];
		                    } elseif ( !empty($fields['select']) ) {
		                        $fields['SELECT'] = $fields['select'];
		                    } else {
		                        $fields['SELECT'] = array('*');
		                    }
		                    $fieldsForBatch[$i] = $method . '?' . http_build_query($fields);
		                }
		                $batch = $this->call('batch', array(
		                    "halt" => 0,
		                    "cmd" => $fieldsForBatch
		                ))['result'];
		                
		                if ( isset( $batch['result'] ) ) {
		                    foreach ($batch['result'] as $list) {
    		                    foreach ($list as $item) {
    		                        $elements[$item['ID']] = $item;
    		                    }
    		                }
		                }
		                $next = ($k+1)*2500;
		            }
		        } else {
		            foreach ($defaultData['result'] as $item) {
		                $elements[$item['ID']] = $item;
		            }
		        }
		    } else {
		        $elements = array();
		    }
		    return $elements;
		}
		
		public function getTaskElapsedList($fields) {
		    $method = 'task.elapseditem.getlist';
		    $elements = array();
		    $fields['SELECT'] = array('*');
		    $index = 1;
		    $frq = $this->call($method, $fields);
		    
		    if ( !empty($frq['result']) ) {
		        if ( $frq['total'] > 50 ) {
		            $maxBatches = ceil ($frq['total']/2500);
		            $next = 0;
		            for($k = 0; $k < $maxBatches;$k++) {
		                $fieldsForBatch = array();
		                $total = $k+1 == $maxBatches ? $k*2500 + $frq['total'] - (($k)*2500) : ($k+1)*2500;
		                //echo $total."\n";
		                for ($i = $next; $i <= $total; $i += 50) {
		                    $fields['SELECT'] = array('*');
		                    $fields['PARAMS'] = array(
    		                        'NAV_PARAMS' => array(
        		                        "nPageSize" => 50, 
    		                            'iNumPage'  => $index++
        		                    )
		                    );
		                    $fieldsForBatch[$i] = $method . '?' . http_build_query($fields);
		                }
		                $batch = $this->call('batch', array(
		                    "halt" => 0,
		                    "cmd" => $fieldsForBatch
		                ))['result']['result'];
		                
		                foreach ($batch as $list) {
		                    foreach ($list as $item) {
		                        $elements[$item['ID']] = $item;
		                    }
		                }
		                $next = ($k+1)*2500;
		            }
		        } else {
		            foreach ($frq['result'] as $item) {
		                $elements[$item['ID']] = $item;
		            }
		        }
		    } else {
		        $elements = array();
		    }
		    return $elements;
		}
		
		public function getTasksList($fields = array()){
		    $method = 'tasks.task.list';
		    
		    $fields = array(
		        'order' => array('ID' => 'desc'),
		        'filter' => !empty($fields['filter']) ? $fields['filter'] : array(),
		        'select' => !empty($fields['select']) ? $fields['select'] : array(),
		    );
		    
		    $frq = $this->call($method, $fields);
		    
		    
		    if ( !empty($frq['result']['tasks']) ) {
		        if ( $frq['total'] > 50 ) {
		            $maxBatches = ceil ($frq['total']/2500);
		            $next = 0;
		            for($k = 0; $k < $maxBatches;$k++) {
		                $fieldsForBatch = array();
		                $total = $k+1 == $maxBatches ? $k*2500 + $frq['total'] - (($k)*2500) : ($k+1)*2500;
		                for ($i = $next; $i <= $total; $i += 50) {
		                    $fields['start'] = $i;
		                    $fieldsForBatch[$i] = $method . '?' . http_build_query($fields);
		                }
		                $batch = $this->call('batch', array(
		                    "halt" => 0,
		                    "cmd" => $fieldsForBatch
		                ))['result']['result'];
		                
		                foreach ($batch as $list) {
		                    if ( !empty($list['tasks']) ) {
		                        foreach ($list['tasks'] as $item) {
    		                        $elements[$item['id']] = $item;
    		                    }
		                    }
		                }
		                $next = ($k+1)*2500;
		            }
		        } else {
		            foreach ($frq['result']['tasks'] as $item) {
		                $elements[$item['id']] = $item;
		            }
		        }
		    } else {
		        $elements = array();
		    }
		    return $elements;
		}
		
		public function getDepartmentName($list, $id){
		    foreach($list as $item){
		        if($item['ID'] == $id){
		            return $item['NAME'];
		        }
		    }
		}
		
		public function installApp($setAppKeys = 0)
		{
			$result = [
				'rest_only' => true,
				'install' => false
			];
			
			$setAppSettings = [];
			
			if(!empty($_REQUEST[ 'event' ]) && $_REQUEST[ 'event' ] == 'ONAPPINSTALL' && !empty($_REQUEST[ 'auth' ]))
			{
			    $result['rest_only'] = false;
			    
			    $setAppSettings = [
			        'access_token'           => htmlspecialchars($_REQUEST[ 'auth' ]['access_token']),
			        'expires_in'             => htmlspecialchars($_REQUEST[ 'auth' ]['expires_in']),
			        'application_token'      => htmlspecialchars($_REQUEST[ 'auth' ]['application_token']),
			        'refresh_token'          => htmlspecialchars($_REQUEST[ 'auth' ]['refresh_token']),
			        'installed_by_member_id' => htmlspecialchars($this->memberId),
			        'domain'                 => htmlspecialchars($this->domain),
			        'client_endpoint'        => 'https://' . htmlspecialchars($this->domain) . '/rest/',
			    ];
			}
			elseif( !empty($_POST[ 'PLACEMENT' ]) && (!empty($_POST['AUTH_ID'])) && $_POST['PLACEMENT'] == 'DEFAULT')
			{
				$result['rest_only'] = false;
				$setAppSettings = [
				    'access_token'           => htmlspecialchars($_POST['AUTH_ID']),
				    'expires_in'             => htmlspecialchars($_POST['AUTH_EXPIRES']),
				    'application_token'      => htmlspecialchars($_GET['APP_SID']),
				    'refresh_token'          => htmlspecialchars($_POST['REFRESH_ID']),
				    'installed_by_member_id' => htmlspecialchars($this->memberId),
				    'domain'                 => htmlspecialchars($this->domain),
				    'client_endpoint'        => 'https://' . htmlspecialchars($this->domain) . '/rest/',
				];
			}
			
			if ( !empty($setAppSettings) ) {
			    
			    if ($setAppKeys) {
			        $setAppSettings['is_installed']         = 1;
			        $setAppSettings['c_rest_client_id']     = $this->C_REST_CLIENT_ID;
			        $setAppSettings['c_rest_client_secret'] = $this->C_REST_CLIENT_SECRET;
			    }
			    
                $result['install'] = $this->setAppSettings($setAppSettings, true);
			}
			
			return $result;
		}

		/**
		 * @var $arParams array
		 * $arParams = [
		 *      'method'    => 'some rest method',
		 *      'params'    => []//array params of method
		 * ];
		 * @return mixed array|string|boolean curl-return or error
		 *
		 */
		protected function callCurl($arParams)
		{
			if(!function_exists('curl_init'))
			{
				return [
					'error'             => 'error_php_lib_curl',
					'error_information' => 'need install curl lib'
				];
			}
			
			$arSettings = $this->getAppSettings();
			
			if($arSettings !== false)
			{
				if(isset($arParams[ 'this_auth' ]) && $arParams[ 'this_auth' ] == 'Y')
				{
					$url = 'https://oauth.b.info/oauth/token/';
				}
				else
				{
				    $url = $arSettings[ "client_endpoint" ] . $arParams[ 'method' ] . '.' . self::TYPE_TRANSPORT;
					if(empty($arSettings[ 'is_web_hook' ]) || $arSettings[ 'is_web_hook' ] != 'Y')
					{
						$arParams[ 'params' ][ 'auth' ] = $arSettings[ 'access_token' ];
					}
				}
				
				
				$sPostFields = http_build_query($arParams[ 'params' ]);

				try
				{
					$obCurl = curl_init();
					curl_setopt($obCurl, CURLOPT_URL, $url);
					curl_setopt($obCurl, CURLOPT_RETURNTRANSFER, true);
					if($sPostFields)
					{
						curl_setopt($obCurl, CURLOPT_POST, true);
						curl_setopt($obCurl, CURLOPT_POSTFIELDS, $sPostFields);
					}
					curl_setopt(
						$obCurl, CURLOPT_FOLLOWLOCATION, (isset($arParams[ 'followlocation' ]))
						? $arParams[ 'followlocation' ] : 1
					);
					if(defined("C_REST_IGNORE_SSL") && C_REST_IGNORE_SSL === true)
					{
						curl_setopt($obCurl, CURLOPT_SSL_VERIFYPEER, false);
						curl_setopt($obCurl, CURLOPT_SSL_VERIFYHOST, false);
					}
					$out = curl_exec($obCurl);
					$info = curl_getinfo($obCurl);
					if(curl_errno($obCurl))
					{
						$info[ 'curl_error' ] = curl_error($obCurl);
					}
					if(self::TYPE_TRANSPORT == 'xml' && (!isset($arParams[ 'this_auth' ]) || $arParams[ 'this_auth' ] != 'Y'))//auth only json support
					{
						$result = $out;
					}
					else
					{
						$result = $this->expandData($out);
					}
					curl_close($obCurl);
					
					if(!empty($result[ 'error' ]))
					{
						if($result[ 'error' ] == 'expired_token')
						{
							$result = $this->GetNewAuth($arParams);
						}
						else
						{
							$arErrorInform = [
								'expired_token'          => 'expired token, cant get new auth? Check access oauth server.',
								'invalid_token'          => 'invalid token, need reinstall application',
								'invalid_grant'          => 'invalid grant, check out define $this->C_REST_CLIENT_SECRET or C_REST_CLIENT_ID',
								'invalid_client'         => 'invalid client, check out define $this->C_REST_CLIENT_SECRET or C_REST_CLIENT_ID',
								'QUERY_LIMIT_EXCEEDED'   => 'Too many requests, maximum 2 query by second',
								'ERROR_METHOD_NOT_FOUND' => 'Method not found! You can see the permissions of the application: CRest::call(\'scope\')',
								'NO_AUTH_FOUND'          => 'Some setup error b24, check in table "b_module_to_module" event "OnRestCheckAuth"',
								'INTERNAL_SERVER_ERROR'  => 'Server down, try later'
							];
							if(!empty($arErrorInform[ $result[ 'error' ] ]))
							{
								$result[ 'error_information' ] = $arErrorInform[ $result[ 'error' ] ];
							}
						}
					}
					if(!empty($info[ 'curl_error' ]))
					{
						$result[ 'error' ] = 'curl_error';
						$result[ 'error_information' ] = $info[ 'curl_error' ];
					}

					return $result;
				}
				catch(Exception $e)
				{
					return [
						'error'             => 'exception',
						'error_information' => $e -> getMessage(),
					];
				}
			} else {
			    $install = $this->installApp();
			    
			    if ( !empty($install['install']) ) {
			        return $this->GetNewAuth($arParams);
			    } else {
			        return [
			            'error'             => 'no_install_app',
			            'error_information' => 'error install app, pls install local application '
			        ];
			    }
			}
			
		}

		/**
		 * Generate a request for callCurl()
		 *
		 * @var $method string
		 * @var $params array method params
		 * @return mixed array|string|boolean curl-return or error
		 */
		
		public function call($method, $params = array(), $setErrorLog = 1)
		{
		    $arPost = [
		        'method' => $method,
		        'params' => $params
		    ];
		    if(defined('C_REST_CURRENT_ENCODING'))
		    {
		        $arPost[ 'params' ] = $this->changeEncoding($arPost[ 'params' ]);
		    }
		    
		    $result = $this->callCurl($arPost);
		    
		    if ( $setErrorLog ) {
		        if ( !empty( $result['error'] ) ) {
		            $this->setErrorLog($result, $arPost);
		        }
		    }
		    
		    return $result;
		}
		
		public function checkPortalConnection()
		{
		    $userId = 0;
		    $arData = array(
		        'cmd' => array(
		            'user.current' => 'user.current',
		            'user.admin'   => 'user.admin',
		        ),
		        'halt' => 0,
		    );
		    
            $arResult = $this->callByAuth('batch', $arData);
            
		    
		    $clientData = [];
		    
		    if ( !empty($arResult['result']['result']) ) {
		        $data = $arResult['result']['result'];
		        
		        if ( !empty($data['user.current']) ) {
		            $clientData = $data['user.current'];
		        }
		        
		        if ( !empty($data['user.admin']) ) {
                    $clientData['IS_ADMIN'] = 1;
		        }
		    }
		    
		    $clientData['IS_ADMIN'] = isset($clientData['IS_ADMIN']) ? $clientData['IS_ADMIN'] : 0;
		    
		    $userId = 0;
		    if ( !empty($clientData['ID']) ) {
    		    $userId = $clientData['ID'];
    		    $this->updateUserData($clientData);
		    }
		     //////////////////////////////////////////////////////////////
		     
		    return ['userId' => $userId, 'isAdmin' => $clientData['IS_ADMIN']];
		}
		
		
		
		public static function encode($value, $key = 'WhoseDevRise!?'){
		    
		    $key .= date('Ymd');
		    
		    $key = sha1($key);
		    if(!$value){return false;}
		    $strLen = strlen($value);
		    $keyLen = strlen($key);
		    $j=0;
		    $crypttext= '';
		    for ($i = 0; $i < $strLen; $i++) {
		        $ordStr = ord(substr($value,$i,1));
		        if ($j == $keyLen) { $j = 0; }
		        $ordKey = ord(substr($key,$j,1));
		        $j++;
		        $crypttext .= strrev(base_convert(dechex($ordStr + $ordKey),16,36));
		    }
		    return $crypttext;
		}
		
		
		
		public static function decode($value, $key = 'WhoseDevRise!?'){
		    
		    $key .= date('Ymd');
		    
		    if(!$value){return false;}
		    $key = sha1($key);
		    $strLen = strlen($value);
		    $keyLen = strlen($key);
		    $j=0;
		    $decrypttext= '';
		    for ($i = 0; $i < $strLen; $i+=2) {
		        $ordStr = hexdec(base_convert(strrev(substr($value,$i,2)),36,16));
		        if ($j == $keyLen) { $j = 0; }
		        $ordKey = ord(substr($key,$j,1));
		        $j++;
		        $decrypttext .= chr($ordStr - $ordKey);
		    }
		    return $decrypttext;
		}
		
		public function callByAuth($method, $params = array(), $setErrorLog = 1)
		{
		    $queryUrl = 'https://'.$this->domain.'/rest/'.$method.".json";
		    $params['access_token'] = $this->access_token;
		    
		    $queryData = http_build_query($params);
		    
		    $curl = curl_init();
		    curl_setopt_array($curl, array(
		        CURLOPT_SSL_VERIFYPEER => 0,
		        CURLOPT_POST => 1,
		        CURLOPT_HEADER => 0,
		        CURLOPT_RETURNTRANSFER => 1,
		        CURLOPT_URL => $queryUrl,
		        CURLOPT_POSTFIELDS => $queryData,
		    ));
		    
		    $response = curl_exec($curl);
		    curl_close($curl);
		    
		    $result = json_decode($response, 1);
		    
		    if ( $setErrorLog ) {
		        if ( !empty( $result['error'] ) ) {
		            $this->setErrorLog($result, $params);
		        }
		    }
		    
		    return $result;
		}
		
		public function callDevRise($method, $params = array(), $setErrorLog = 1)
		{
		    $queryUrl  = 'https://devrise.b24.ua/rest/1/lebxdfcigeq9yl9n/'.$method.".json";
		    
		    $queryData = http_build_query($params);
		    
		    $curl = curl_init();
		    curl_setopt_array($curl, array(
		        CURLOPT_SSL_VERIFYPEER => 0,
		        CURLOPT_POST => 1,
		        CURLOPT_HEADER => 0,
		        CURLOPT_RETURNTRANSFER => 1,
		        CURLOPT_URL => $queryUrl,
		        CURLOPT_POSTFIELDS => $queryData,
		    ));
		    
		    $response = curl_exec($curl);
		    curl_close($curl);
		    
		    $result = json_decode($response, 1);
		    
		    if ( $setErrorLog ) {
		        if ( !empty( $result['error'] ) ) {
		            $this->setErrorLog($result, $params);
		        }
		    }
		    
		    return $result;
		}

		/**
		 * @example $arData:
		 * $arData = [
		 *      'find_contact' => [
		 *          'method' => 'crm.duplicate.findbycomm',
		 *          'params' => [ "entity_type" => "CONTACT",  "type" => "EMAIL", "values" => array("info@b24.com") ]
		 *      ],
		 *      'get_contact' => [
		 *          'method' => 'crm.contact.get',
		 *          'params' => [ "id" => '$result[find_contact][CONTACT][0]' ]
		 *      ],
		 *      'get_company' => [
		 *          'method' => 'crm.company.get',
		 *          'params' => [ "id" => '$result[get_contact][COMPANY_ID]', "select" => ["*"],]
		 *      ]
		 * ];
		 *
		 * @var $arData array
		 * @var $halt   integer 0 or 1 stop batch on error
		 * @return array
		 *
		 */

		public function callBatch($arData, $setErrorLog = 1)
		{
		    $halt = 0;
			$arResult = [];
			if(is_array($arData))
			{
				if(defined('C_REST_CURRENT_ENCODING'))
				{
					$arData = $this->changeEncoding($arData);
				}
				$arDataRest = [];
				$i = 0;
				foreach($arData as $key => $data)
				{
					if(!empty($data[ 'method' ]))
					{
						$i++;
						if(self::BATCH_COUNT > $i)
						{
							$arDataRest[ 'cmd' ][ $key ] = $data[ 'method' ];
							if(!empty($data[ 'params' ]))
							{
								$arDataRest[ 'cmd' ][ $key ] .= '?' . http_build_query($data[ 'params' ]);
							}
						}
					}
				}
				if(!empty($arDataRest))
				{
					$arDataRest[ 'halt' ] = $halt;
					$arPost = [
						'method' => 'batch',
						'params' => $arDataRest
					];
					
					$arResult = $this->callCurl($arPost);
					
					if ( $setErrorLog ) {
    					if(!empty($arResult['result']['result_error']))
    					{
                            $this->setErrorLog($arResult['result']['result_error'], $arData);
    					}
					}
				}
			}
			return $arResult;
		}
		
		public function queryRefreshToken($method, $url, $data = null) {
		    $url .= strpos ( $url, "?" ) > 0 ? "&" : "?";
		    
		    
		    $url .= http_build_query ( $data );
		    
		    $res = file_get_contents ( $url );
		    return $res;
		}
		
		/**
		 * Getting a new authorization and sending a request for the 2nd time
		 *
		 * @var $arParams array request when authorization error returned
		 * @return array query result from $arParams
		 *
		 */

		public function GetNewAuth($arParams)
		{
			$result = [];
			$arSettings = $this->getAppSettings();
			
			if($arSettings !== false)
			{
			    $params = array (
			        "grant_type"    => "refresh_token",
			        "client_id"     => urlencode($this->C_REST_CLIENT_ID),
			        "client_secret" => $this->C_REST_CLIENT_SECRET,
			        "refresh_token" => $arSettings[ "refresh_token" ]
			    );
			     
			    $path = "/oauth/token/";
			    $domainOauth = 'oauth.b.info';
			    $query_data  = $this->queryRefreshToken( "GET", "https://" . $domainOauth . $path, $params );
			    
			    if ( !empty($query_data) ) {
    			    $newData = json_decode($query_data, 1);
    			    
    			    if($this->setAppSettings($newData) && !empty($arParams))
    				{
    					$arParams[ 'this_auth' ] = 'N';
    					$result = $this->callCurl($arParams);
    				}
			    } else {
			        $this->installApp();
			    }
			}
			return $result;
		}

		/**
		 * @var $arSettings array settings application
		 * @var $isInstall  boolean true if install app by installApp()
		 * @return boolean
		 */

		private function setAppSettings($arSettings, $isInstall = false)
		{
			$return = false;
			if(is_array($arSettings))
			{
				$oldData = $this->getAppSettings();
				if($isInstall != true && !empty($oldData) && is_array($oldData))
				{
					$arSettings = array_merge($oldData, $arSettings);
				}
				
				$return = $this->setSettingData($arSettings);
			}
			return $return;
		}

		/**
		 * @return mixed setting application for query
		 */

		public function getAppSettings()
		{
		    if( !empty($this->C_REST_WEB_HOOK_URL))
			{
				$arData = [
				    'client_endpoint' => $this->C_REST_WEB_HOOK_URL,
					'is_web_hook'     => 'Y'
				];
				$isCurrData = true;
			}
			else
			{
			    $arData = $this->getSettingData();
				$isCurrData = false;
				if(
					!empty($arData[ 'access_token' ]) &&
					!empty($arData[ 'domain' ]) &&
					!empty($arData[ 'refresh_token' ]) &&
					!empty($arData[ 'application_token' ]) &&
					!empty($arData[ 'client_endpoint' ])
				)
				{
					$isCurrData = true;
				}
			}
			
			return ($isCurrData) ? $arData : false;
		}

		/**
		 * Can overridden this method to change the data storage location.
		 *
		 * @return array setting for getAppSettings()
		 */

		protected function getSettingData()
		{
		    $return = $this->selectRow('*', 'marketplace_tokens', "domain='$this->domain' AND app_id='".$this->APP_ID."'");
		    
		    if(defined("C_REST_CLIENT_ID") && !empty($this->C_REST_CLIENT_ID))
			{
			    $return['C_REST_CLIENT_ID'] = $this->C_REST_CLIENT_ID;
			}
			if(defined("C_REST_CLIENT_SECRET") && !empty($this->C_REST_CLIENT_SECRET))
			{
				$return['C_REST_CLIENT_SECRET'] = $this->C_REST_CLIENT_SECRET;
			}
			
			return $return;
		}

		/**
		 * @var $data mixed
		 * @var $encoding boolean true - encoding to utf8, false - decoding
		 *
		 * @return string json_encode with encoding
		 */
		protected function changeEncoding($data, $encoding = true)
		{
			if(is_array($data))
			{
				$result = [];
				foreach ($data as $k => $item)
				{
					$k = $this->changeEncoding($k, $encoding);
					$result[$k] = $this->changeEncoding($item, $encoding);
				}
			}
			else
			{
				if($encoding)
				{
					$result = iconv(C_REST_CURRENT_ENCODING, "UTF-8//TRANSLIT", $data);
				}
				else
				{
					$result = iconv( "UTF-8",C_REST_CURRENT_ENCODING, $data);
				}
			}

			return $result;
		}

		/**
		 * @var $data mixed
		 * @var $debag boolean
		 *
		 * @return string json_encode with encoding
		 */
		protected function wrapData($data, $debag = false)
		{
			if(defined('C_REST_CURRENT_ENCODING'))
			{
				$data = $this->changeEncoding($data, true);
			}
			$return = json_encode($data, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);

			if($debag)
			{
				$e = json_last_error();
				if ($e != JSON_ERROR_NONE)
				{
					if ($e == JSON_ERROR_UTF8)
					{
						return 'Failed encoding! Recommended \'UTF - 8\' or set define C_REST_CURRENT_ENCODING = current site encoding for function iconv()';
					}
				}
			}

			return $return;
		}

		/**
		 * @var $data mixed
		 * @var $debag boolean
		 *
		 * @return string json_decode with encoding
		 */
		protected function expandData($data)
		{
			$return = json_decode($data, true);
			if(defined('C_REST_CURRENT_ENCODING'))
			{
				$return = $this->changeEncoding($return, false);
			}
			return $return;
		}

		/**
		 * Can overridden this method to change the data storage location.
		 *
		 * @var $arSettings array settings application
		 * @return boolean is successes save data for setSettingData()
		 */

		protected function setSettingData($arSettings)
		{
		    if ( $arSettings['domain'] == 'oauth.b.info' ) {
    		    $arSettings['domain'] = $this->domain;
		    }
		    
		    $dataToInsert = array(
		        'app_id' => $this->APP_ID,
		        'domain' => $arSettings['domain'],
		    );
		    
		    if ( !empty( $arSettings['access_token'] ) ) {
		        $dataToInsert['access_token'] = $arSettings['access_token'];
		    }
		    
		    if ( !empty( $arSettings['expires_in'] ) ) {
		        $dataToInsert['expires_in'] = $arSettings['expires_in'];
		    }
		    
		    if ( !empty( $arSettings['application_token'] ) ) {
		        $dataToInsert['application_token'] = $arSettings['application_token'];
		    }
		    
		    if ( !empty( $arSettings['refresh_token'] ) ) {
		        $dataToInsert['refresh_token'] = $arSettings['refresh_token'];
		    }
		    
		    if ( !empty( $arSettings['client_endpoint'] ) ) {
		        $dataToInsert['client_endpoint'] = $arSettings['client_endpoint'];
		    }
		    
		    if ( !empty( $arSettings['installed_by_member_id'] ) ) {
		        $dataToInsert['installed_by_member_id'] = $arSettings['installed_by_member_id'];
		    }
		    
		    if ( !empty( $arSettings['c_rest_client_id'] ) ) {
		        $dataToInsert['c_rest_client_id'] = $arSettings['c_rest_client_id'];
		    }
		    
		    if ( !empty( $arSettings['c_rest_client_secret'] ) ) {
		        $dataToInsert['c_rest_client_secret'] = $arSettings['c_rest_client_secret'];
		    }
		    
		    if ( isset( $arSettings['is_installed'] ) ) {
		        $dataToInsert['is_installed'] = $arSettings['is_installed'];
		    }
		    
		    
		    $usertokenDb = $this->selectRow('*', 'marketplace_tokens', "domain='$this->domain' AND app_id='".$this->APP_ID."'");
		    
		    $this->tokenData = $dataToInsert;
		    if ( !empty($usertokenDb) ) {
		        return  (boolean)$this->update($dataToInsert, 'marketplace_tokens', "id={$usertokenDb['id']}");
		    } else {
		        return  (boolean)$this->insert($dataToInsert, 'marketplace_tokens');
		    }
		}

		/**
		 * Can overridden this method to change the log data storage location.
		 *
		 * @var $arData array of logs data
		 * @var $type   string to more identification log data
		 * @return boolean is successes save log data
		 */

		public function setLog($arData, $type = '')
		{
			$return = false;
			if(!defined("C_REST_BLOCK_LOG") || C_REST_BLOCK_LOG !== true)
			{
				if(defined("C_REST_LOGS_DIR"))
				{
					$path = C_REST_LOGS_DIR;
				}
				else
				{
				    $path = '/home/mandyb01/app.devrise.com.ua/logs/' . $this->APP_ID . '/';
				}
				$path .= date("m.Y");
				@mkdir($path, 0775, true);
				$path .= '/' . $type;
				
				$arData = array(
				    'date'   => date('d.m.Y H:i:s'),
				    'domain' => $this->domain,
				    'arData' => $arData,
				);
				if(!defined("C_REST_LOG_TYPE_DUMP") || C_REST_LOG_TYPE_DUMP !== true)
				{
				    $return = file_put_contents($path . '.json', $this->wrapData($arData) . "\n", FILE_APPEND);
				}
				else
				{
				    $return = file_put_contents($path . '.txt', var_export($arData, true) . "\n", FILE_APPEND);
				}
			}
			return $return;
		}

		/**
		 * Can overridden this method to change the log data storage location.
		 *
		 * @var $arData array of logs data
		 * @var $type   string to more identification log data
		 * @return boolean is successes save log data
		 */
		
		public function setErrorLog($errorArray, $arData)
		{
		    $return = false;
		    if(!defined("C_REST_BLOCK_LOG") || C_REST_BLOCK_LOG !== true)
		    {
		        if(defined("C_REST_LOGS_DIR"))
		        {
		            $path = C_REST_LOGS_DIR;
		        }
		        else
		        {
		            $path = '/home/mandyb01/app.devrise.com.ua/logs/' . $this->APP_ID . '/';
		        }
		        $path .= date("m.Y") . '/';
		        @mkdir($path, 0775, true);
		        $path .= 'errors_log';
		        
		        $arData = array(
		            'date'   => date('d.m.Y H:i:s'),
		            'domain' => $this->domain,
		            'error'  => $errorArray,
		            'arData' => $arData,
		        );
		        
		        $return = file_put_contents($path . '.json', $this->wrapData($arData) . "\n", FILE_APPEND);
		        
		    }
		    return $return;
		}
		
		public function setChangesLog($logArray, $fileName = 'general_log')
		{
		    $return = false;
		    if(!defined("C_REST_BLOCK_LOG") || C_REST_BLOCK_LOG !== true)
		    {
		        if(defined("C_REST_LOGS_DIR"))
		        {
		            $path = C_REST_LOGS_DIR;
		        }
		        else
		        {
		            $path = '/home/mandyb01/app.devrise.com.ua/logs/' . $this->APP_ID . '/';
		        }
		        $path .= date("m.Y") . '/';
		        @mkdir($path, 0775, true);
		        $path .= $fileName;
		        
		        $arData = array(
		            'date'   => date('d.m.Y H:i:s'),
		            'domain' => $this->domain,
		            $logArray,
		        );
		        
		        $return = file_put_contents($path . '.json', $this->wrapData($arData) . "\n", FILE_APPEND);
		        
		    }
		    return $return;
		}
		
		public function getPortalInfo()
		{
		    $arData = array(
		        'app.info' => array(
		            'method' => 'app.info',
		            'params' => array()
		        ),
		        'user.current' => array(
		            'method' => 'user.current',
		            'params' => array()
		        ),
		        'user.get' => array(
		            'method' => 'user.get',
		            'params' => array(
		                'USER_TYPE' => 'employee',
		                'ACTIVE' => true,
		            )
		        ),
		        'user.admin' => array(
		            'method' => 'user.admin',
		            'params' => array()
		        ),
		        'crm.lead.list' => array(
		            'method' => 'crm.lead.list',
		            'params' => array(
		                'SELECT' => array('ID')
		            )
		        ),
		        'crm.contact.list' => array(
		            'method' => 'crm.contact.list',
		            'params' => array(
		                'SELECT' => array('ID')
		            )
		        ),
		        'crm.company.list' => array(
		            'method' => 'crm.company.list',
		            'params' => array(
		                'SELECT' => array('ID')
		            )
		        ),
		        'crm.deal.list' => array(
		            'method' => 'crm.deal.list',
		            'params' => array(
		                'SELECT' => array('ID')
		            )
		        ),
		    );
		    
		    $arResult = $this->callBatch($arData, 0);
		    $clientData = array(
		        'domain' => $this->domain
		    );
		    
		    if ( !empty($arResult['result']['result']) ) {
		        $data = $arResult['result']['result'];
		        $totals = $arResult['result']['result_total'];
		        
		        $clientData['user_is_admin'] = 0;
		        if ( !empty($data['user.admin']) ) {
                    $clientData['user_is_admin'] = 1;
		        }
		        
		        if ( !empty($data['app.info']) ) {
		            $clientData['license'] = $data['app.info']['LICENSE'];
		            $this->license = $clientData['license'];
		        }
		        
		        if ( !empty($data['user.get']) ) {
		            $userList = array();
		            foreach ( $data['user.get'] as $userData ) {
		                $phones = array(
		                    isset($userData['PERSONAL_PHONE']) ? $userData['PERSONAL_PHONE'] : '',
		                    isset($userData['PERSONAL_MOBILE']) ? $userData['PERSONAL_MOBILE'] : '',
		                    isset($userData['WORK_PHONE']) ? $userData['WORK_PHONE'] : '',
		                    isset($userData['UF_PHONE_INNER']) ? $userData['UF_PHONE_INNER'] : '',
		                );
		                $phones = implode(',', array_unique(array_filter($phones)));
		                $user_name = "{$userData['NAME']} {$userData['LAST_NAME']} {$userData['SECOND_NAME']}";
		                $email     = isset($userData['EMAIL']) ? $userData['EMAIL'] : '';
		                
		                $userList[$userData['ID']] = array(
		                    'phones'    => $phones,
		                    'user_name' => $user_name,
		                    'email'     => $email,
		                    'work_position' => isset($userData['WORK_POSITION']) ? $userData['WORK_POSITION'] : '',
		                );
		            }
		            $clientData['subusers'] = json_encode($userList);
		        }
		        
		        if ( !empty($data['user.current']) ) {
		            
		            $phones = array(
		                isset($data['user.current']['PERSONAL_PHONE']) ? $data['user.current']['PERSONAL_PHONE'] : '',
		                isset($data['user.current']['PERSONAL_MOBILE']) ? $data['user.current']['PERSONAL_MOBILE'] : '',
		                isset($data['user.current']['WORK_PHONE']) ? $data['user.current']['WORK_PHONE'] : '',
		                isset($data['user.current']['UF_PHONE_INNER']) ? $data['user.current']['UF_PHONE_INNER'] : '',
		            );
		            $phones = implode(',', array_unique(array_filter($phones)));
		            
		            $clientData['user_id']   = $data['user.current']['ID'];
		            $clientData['phones']    = $phones;
		            $clientData['user_name'] = "{$data['user.current']['NAME']} {$data['user.current']['LAST_NAME']} {$data['user.current']['SECOND_NAME']}";
		            $clientData['user_first_name'] = $data['user.current']['NAME'];
		            $clientData['email']     = isset($data['user.current']['EMAIL']) ? $data['user.current']['EMAIL'] : '';
		        }
		        
		        $clientData['users']     = isset($totals['user.get']) ? $totals['user.get'] : 0;
		        $clientData['contacts']  = isset($totals['crm.contact.list']) ? $totals['crm.contact.list'] : 0;
		        $clientData['companies'] = isset($totals['crm.company.list']) ? $totals['crm.company.list'] : 0;
		        $clientData['leads']     = isset($totals['crm.lead.list']) ? $totals['crm.lead.list'] : 0;
		        $clientData['deals']     = isset($totals['crm.deal.list']) ? $totals['crm.deal.list'] : 0;
		    }
		    return $clientData;
		}
		
		public function updateAppSettings($additional)
		{
		    $appSettings = $this->selectRow('*', $this->tableApps, "domain='$this->domain' AND app_code='".$this->APP_ID."'");
		    $result = '';
		    if ( !empty($appSettings) ) {
		        
		    } else {
		        $settings = array(
		            'domain'   => $this->domain,
		            'app_code' => $this->APP_ID,
		            'app_type' => !empty($additional['app_type']) ? $additional['app_type'] : 1,
		            'token'    => $this->generateToken(),
		            'settings' => (!empty($additional['settings']) && is_array($additional['settings'])) ? json_encode($additional['settings']) : '',
		        );
		        
		        if ( !empty($additional['expired_date']) ) {
		            $settings['expired_date'] = $additional['expired_date'];
		        }
		        
		        $result = $this->insert($settings, $this->tableApps);
		    }
		    
		}
		
		public function createDialogIfNeed()
		{
		}
		
		public function insertIntoHistory($clientData = [])
		{
			$history = array(
				'app_id'      => $this->APP_ID,
				'domain'      => $this->domain,
				'user_id'     => $this->userId,
				'lang'        => $this->lang,
				'window_type' => $this->windowType,
			);
			$result = $this->insert($history, $this->tableAppHistory);
		}
		
		public function updatePortalInfo($clientData)
		{
		    $userFromDb = $this->selectRow('*', $this->tableUsers, "domain='$this->domain'");
		    
		    $query = array(
		        'domain'    => !empty($clientData['domain']) ? $clientData['domain'] : '',
		        'license'   => !empty($clientData['license']) ? $clientData['license'] : '',
		        'subusers'  => !empty($clientData['subusers']) ? $clientData['subusers'] : '',
		        'phones'    => !empty($clientData['phones']) ? $clientData['phones'] : '',
		        'user_name' => !empty($clientData['user_name']) ? $clientData['user_name'] : '',
		        'email'     => !empty($clientData['email']) ? $clientData['email'] : '',
		        'users'     => !empty($clientData['users']) ? $clientData['users'] : (!empty($userFromDb['users']) ? $userFromDb['users'] : 0),
		        'contacts'  => !empty($clientData['contacts']) ? $clientData['contacts'] : (!empty($userFromDb['contacts']) ? $userFromDb['contacts'] : 0),
		        'companies' => !empty($clientData['companies']) ? $clientData['companies'] : (!empty($userFromDb['companies']) ? $userFromDb['companies'] : 0),
		        'leads'     => !empty($clientData['leads']) ? $clientData['leads'] : (!empty($userFromDb['leads']) ? $userFromDb['leads'] : 0),
		        'deals'     => !empty($clientData['deals']) ? $clientData['deals'] : (!empty($userFromDb['deals']) ? $userFromDb['deals'] : 0),
		    );
		    
		    $result = '';
		    if ( !empty($userFromDb) ) {
		        $result = $this->update($query, $this->tableUsers, "id={$userFromDb['id']}");
		    } else {
		        $result = $this->insert($query, $this->tableUsers);
		    }
		    
		}
		
		public function updateUserInfo($getExtendedData, $langArr)
		{
		    
		    if ( $getExtendedData ) {
		        $arData = array(
		            'app.info' => array(
		                'method' => 'app.info',
		                'params' => array()
		            ),
		            'user.current' => array(
		                'method' => 'user.current',
		                'params' => array()
		            ),
		            'user.get' => array(
		                'method' => 'user.get',
		                'params' => array(
		                    'USER_TYPE' => 'employee',
		                    'ACTIVE' => true,
		                )
		            ),
		            'user.admin' => array(
		                'method' => 'user.admin',
		                'params' => array()
		            ),
		            'crm.lead.list' => array(
		                'method' => 'crm.lead.list',
		                'params' => array(
		                    'SELECT' => array('ID')
		                )
		            ),
		            'crm.contact.list' => array(
		                'method' => 'crm.contact.list',
		                'params' => array(
		                    'SELECT' => array('ID')
		                )
		            ),
		            'crm.company.list' => array(
		                'method' => 'crm.company.list',
		                'params' => array(
		                    'SELECT' => array('ID')
		                )
		            ),
		            'crm.deal.list' => array(
		                'method' => 'crm.deal.list',
		                'params' => array(
		                    'SELECT' => array('ID')
		                )
		            ),
		        );
		    } else {
		        $arData = array(
		            'user.current' => array(
		                'method' => 'user.current',
		                'params' => array()
		            ),
		            'user.admin' => array(
		                'method' => 'user.admin',
		                'params' => array()
		            ),
		        );
		    }
		    
		    $arResult = $this->callBatch($arData, 0);
		    $clientData = array(
		        'domain' => $this->domain,
		        'lang'   => $this->lang,
		    );
		    
		    if ( !empty($arResult['result']['result']) ) {
		        $data   = $arResult['result']['result'];
		        $totals = $arResult['result']['result_total'];
		        
		        if ( !empty($data['app.info']) ) {
		            $clientData['license'] = $data['app.info']['LICENSE'];
		            $this->license = $clientData['license'];
		        }
		        $clientData['users']     = isset($totals['user.get']) ? $totals['user.get'] : 0;
		        $clientData['contacts']  = isset($totals['crm.contact.list']) ? $totals['crm.contact.list'] : 0;
		        $clientData['companies'] = isset($totals['crm.company.list']) ? $totals['crm.company.list'] : 0;
		        $clientData['leads']     = isset($totals['crm.lead.list']) ? $totals['crm.lead.list'] : 0;
		        $clientData['deals']     = isset($totals['crm.deal.list']) ? $totals['crm.deal.list'] : 0;
		        
		        if ( !empty($data['user.get']) ) {
		            $userList = array();
		            foreach ( $data['user.get'] as $userData ) {
		                $phones = array();
		                
		                if ( isset($data['user.current']['PERSONAL_PHONE']) ) {
		                    $phones[] = $data['user.current']['PERSONAL_PHONE'];
		                }
		                if ( isset($data['user.current']['PERSONAL_MOBILE']) ) {
		                    $phones[] = $data['user.current']['PERSONAL_MOBILE'];
		                }
		                if ( isset($data['user.current']['WORK_PHONE']) ) {
		                    $phones[] = $data['user.current']['WORK_PHONE'];
		                }
		                if ( isset($data['user.current']['UF_PHONE_INNER']) ) {
		                    $phones[] = $data['user.current']['UF_PHONE_INNER'];
		                }
		                
		                $phones = implode(',', array_unique(array_filter($phones)));
		                $user_name = "{$userData['NAME']} {$userData['LAST_NAME']} {$userData['SECOND_NAME']}";
		                $email     = isset($userData['EMAIL']) ? $userData['EMAIL'] : '';
		                
		                $userList[$userData['ID']] = array(
		                    'phones'        => $phones,
		                    'user_name'     => $user_name,
		                    'email'         => $email,
		                    'work_position' => isset($userData['WORK_POSITION']) ? $userData['WORK_POSITION'] : '',
		                );
		            }
		            $clientData['subusers'] = json_encode($userList);
		        }
  
		        $clientData['user_is_admin'] = 0;
		        if ( !empty($data['user.admin']) ) {
		            $clientData['user_is_admin'] = 1;
		        }
		        
		        if ( !empty($data['user.current']) ) {
		            
		            $phones = array();
		            
		            if ( isset($data['user.current']['PERSONAL_PHONE']) ) {
		                $phones[] = $data['user.current']['PERSONAL_PHONE'];
		            }
		            if ( isset($data['user.current']['PERSONAL_MOBILE']) ) {
		                $phones[] = $data['user.current']['PERSONAL_MOBILE'];
		            }
		            if ( isset($data['user.current']['WORK_PHONE']) ) {
		                $phones[] = $data['user.current']['WORK_PHONE'];
		            }
		            if ( isset($data['user.current']['UF_PHONE_INNER']) ) {
		                $phones[] = $data['user.current']['UF_PHONE_INNER'];
		            }
		            
		            $phones = implode(',', array_unique(array_filter($phones)));
		            
		            $clientData['phones'] = $phones;
		            $clientData = array_merge($clientData, $data['user.current']);
					
		            $clientData['user_id']         = $data['user.current']['ID'];
		            $clientData['phones']          = $phones;
		            $clientData['user_name']       = "{$data['user.current']['NAME']} {$data['user.current']['LAST_NAME']} {$data['user.current']['SECOND_NAME']}";
		            $clientData['user_first_name'] = $data['user.current']['NAME'];
		            $clientData['email']           = isset($data['user.current']['EMAIL']) ? $data['user.current']['EMAIL'] : '';
		            
		            $this->userId = $clientData['user_id'];
		        }
		    }
		    
		    $userFromDb = array();
		    
		    if ( !empty($clientData['user_id']) ) {
                $userFromDb = $this->selectRow('*', $this->tablePortalUsers, "domain='$this->domain' AND user_id='{$clientData['user_id']}'");
		    }
		    
		    $result = '';
		    if ( !empty($userFromDb) ) {
		        $query = array(
		            'is_admin'       => !empty($clientData['user_is_admin']) ? $clientData['user_is_admin'] : $userFromDb['is_admin'],
		            'phones'         => !empty($clientData['phones']) ? $clientData['phones'] : $userFromDb['phones'],
		            'email'          => !empty($clientData['EMAIL']) ? $clientData['EMAIL'] : $userFromDb['email'],
		            'first_name'     => !empty($clientData['NAME']) ? $clientData['NAME'] : $userFromDb['first_name'],
		            'last_name'      => !empty($clientData['LAST_NAME']) ? $clientData['LAST_NAME'] : $userFromDb['last_name'],
		            'second_name'    => !empty($clientData['SECOND_NAME']) ? $clientData['SECOND_NAME'] : $userFromDb['second_name'],
		            'lang'           => !empty($clientData['lang']) ? $clientData['lang'] : $userFromDb['lang'],
		            'work_position'  => !empty($clientData['WORK_POSITION']) ? $clientData['WORK_POSITION'] : $userFromDb['work_position'],
		            'birthday'       => !empty($clientData['PERSONAL_BIRTHDAY']) ? explode('T', $clientData['PERSONAL_BIRTHDAY'])[0] : $userFromDb['birthday'],
		            'gender'         => !empty($clientData['PERSONAL_GENDER']) ? $clientData['PERSONAL_GENDER'] : $userFromDb['gender'],
		            'is_active'      => !empty($clientData['ACTIVE']) ? $clientData['ACTIVE'] : $userFromDb['is_active'],
		            'city'           => !empty($clientData['PERSONAL_CITY']) ? $clientData['PERSONAL_CITY'] : $userFromDb['city'],
		            'skills'         => !empty($clientData['UF_SKILLS']) ? $clientData['UF_SKILLS'] : $userFromDb['skills'],
		            'linkedin'       => !empty($clientData['UF_LINKEDIN']) ? $clientData['UF_LINKEDIN'] : $userFromDb['linkedin'],
		            'facebook'       => !empty($clientData['UF_FACEBOOK']) ? $clientData['UF_FACEBOOK'] : $userFromDb['facebook'],
		            'twitter'        => !empty($clientData['UF_TWITTER']) ? $clientData['UF_TWITTER'] : $userFromDb['twitter'],
		            'interests'      => !empty($clientData['UF_INTERESTS']) ? $clientData['UF_INTERESTS'] : $userFromDb['interests'],
		            'personal_photo' => !empty($clientData['PERSONAL_PHOTO']) ? $clientData['PERSONAL_PHOTO'] : $userFromDb['personal_photo'],
		            'last_app_id'    => $this->APP_ID,
		            'last_update'    => date('Y-m-d H:i:s'),
		        );
		        
		        $result = $this->update($query, $this->tablePortalUsers, "id={$userFromDb['id']}");
		    } else {
		        
		        $query = array(
		            'domain'         => !empty($clientData['domain']) ? $clientData['domain'] : '',
		            'user_id'        => !empty($clientData['ID']) ? $clientData['ID'] : 0,
		            'is_admin'       => !empty($clientData['user_is_admin']) ? $clientData['user_is_admin'] : '',
		            'phones'         => !empty($clientData['phones']) ? $clientData['phones'] : '',
		            'email'          => !empty($clientData['EMAIL']) ? $clientData['EMAIL'] : '',
		            'first_name'     => !empty($clientData['NAME']) ? $clientData['NAME'] : '',
		            'last_name'      => !empty($clientData['LAST_NAME']) ? $clientData['LAST_NAME'] : '',
		            'second_name'    => !empty($clientData['SECOND_NAME']) ? $clientData['SECOND_NAME'] : '',
		            'lang'           => !empty($clientData['lang']) ? $clientData['lang'] : '',
		            'work_position'  => !empty($clientData['WORK_POSITION']) ? $clientData['WORK_POSITION'] : '',
		            'birthday'       => !empty($clientData['PERSONAL_BIRTHDAY']) ? explode('T', $clientData['PERSONAL_BIRTHDAY']) : '',
		            'gender'         => !empty($clientData['PERSONAL_GENDER']) ? $clientData['PERSONAL_GENDER'] : '',
		            'is_active'      => !empty($clientData['ACTIVE']) ? $clientData['ACTIVE'] : '',
		            'city'           => !empty($clientData['PERSONAL_CITY']) ? $clientData['PERSONAL_CITY'] : '',
		            'skills'         => !empty($clientData['UF_SKILLS']) ? $clientData['UF_SKILLS'] : '',
		            'linkedin'       => !empty($clientData['UF_LINKEDIN']) ? $clientData['UF_LINKEDIN'] : '',
		            'facebook'       => !empty($clientData['UF_FACEBOOK']) ? $clientData['UF_FACEBOOK'] : '',
		            'twitter'        => !empty($clientData['UF_TWITTER']) ? $clientData['UF_TWITTER'] : '',
		            'interests'      => !empty($clientData['UF_INTERESTS']) ? $clientData['UF_INTERESTS'] : '',
		            'personal_photo' => !empty($clientData['PERSONAL_PHOTO']) ? $clientData['PERSONAL_PHOTO'] : 0,
		            'last_app_id'    => $this->APP_ID,
		            'last_update'    => date('Y-m-d H:i:s'),
		        );
		        
		        $result = $this->insert($query, $this->tablePortalUsers);
		    }

		    $query = array_merge($clientData, $query);
		    return $query;
		}
		
		public function updateUserData($userData)
		{
		    $appPortalsUserDb = $this->selectRow('*', 'app_portals_users', "domain='$this->domain' AND user_id='{$userData['ID']}'");
		    $domainData       = $this->selectRow('*', 'domains_data', "domain='$this->domain'");
		    
		    $appDataDb = $this->selectRow('*', 'marketplace_tokens', "domain='$this->domain' AND app_id='$this->APP_ID'");
		    
		    
		    $date_register = '';
		    if ( !empty($userData['DATE_REGISTER']) ) {
		        $dateArr = explode('T', $userData['DATE_REGISTER']);
		        $date_register = $dateArr[0];
		    }
		    
		    $birthdate = '';
		    if ( !empty($userData['PERSONAL_BIRTHDAY']) ) {
		        $dateArr = explode('T', $userData['PERSONAL_BIRTHDAY']);
		        $birthdate = $dateArr[0];
		    }
		    
		    $params = [
		        'domain'          => $this->domain,
		        'user_id'         => $userData['ID'],
		        'email'           => isset($userData['EMAIL']) ? $userData['EMAIL'] : '',
		        'date_register'   => $date_register,
		        'name'            => isset($userData['NAME']) ? $userData['NAME'] : '',
		        'last_name'       => isset($userData['LAST_NAME']) ? $userData['LAST_NAME'] : '',
		        'second_name'     => isset($userData['SECOND_NAME']) ? $userData['SECOND_NAME'] : '',
		        'profession'      => isset($userData['PERSONAL_PROFESSION']) ? $userData['PERSONAL_PROFESSION'] : '',
		        'birthdate'       => $birthdate,
		        'photo'           => isset($userData['PERSONAL_PHOTO']) ? $userData['PERSONAL_PHOTO'] : '',
		        'personal_phone'  => isset($userData['PERSONAL_PHONE']) ? $userData['PERSONAL_PHONE'] : '',
		        'personal_mobile' => isset($userData['PERSONAL_MOBILE']) ? $userData['PERSONAL_MOBILE'] : '',
		        'work_phone'      => isset($userData['WORK_PHONE']) ? $userData['WORK_PHONE'] : '',
		        'city'            => isset($userData['PERSONAL_CITY']) ? $userData['PERSONAL_CITY'] : '',
		        'facebook'        => isset($userData['UF_FACEBOOK']) ? $userData['UF_FACEBOOK'] : '',
		        'position'        => isset($userData['WORK_POSITION']) ? $userData['WORK_POSITION'] : '',
		        'is_admin'        => !empty($userData['IS_ADMIN']) ? $userData['IS_ADMIN'] : 0,
		        'is_active'       => !empty($userData['ACTIVE']) ? $userData['ACTIVE'] : 0,
		        'member_id'       => $this->memberId,
		        'lang'            => $this->lang,
		    ];

		    $needToCreate = 0;
		    if ( !empty($appPortalsUserDb['object_id']) ) {
		        $fields = [
		            'entityTypeId' => 164, // Користувачі застосунків
		            'id'           => $appPortalsUserDb['object_id'],
		        ];
		        $itemData = $this->callDevRise('crm.item.get', $fields);
		        if ( !empty($itemData['error']) && $itemData['error'] == 'NOT_FOUND' ) {
		            $needToCreate = 1;
		        } else {
		            $this->devriseUserId = $appPortalsUserDb['object_id'];
		        }
		    } else {
		        $needToCreate = 1;
		    }
		    
		    if ( $needToCreate ) {
		        $fields = [
		            'entityTypeId' => 164, // Користувачі застосунків
		            'fields' => [
		                'title' => "{$params['last_name']} {$params['name']}",
		                'ufCrm7UserId'         => $userData['ID'],
		                'ufCrm7IsAdmin'        => $params['is_admin'] ? 'Y' : 'N', // Так/Ні
		                'ufCrm7FirstName'      => $params['name'],
		                'ufCrm7LastName'       => $params['last_name'],
		                'ufCrm7SecondName'     => $params['second_name'],
		                'ufCrm7Lang'           => $params['lang'],
		                'ufCrm7Birthdate'      => $params['birthdate'],
		                'ufCrm7Position'       => $params['position'] . ' | ' . $params['profession'],
		                'ufCrm7Email'          => $params['email'],
		                'ufCrm7Photo'          => $params['photo'],
		                'ufCrm7Domain'         => [$this->domain],
		                'ufCrm7PersonalPhone'  => $params['personal_phone'],
		                'ufCrm7PersonalMobile' => $params['personal_mobile'],
		                'ufCrm7WorkPhone'      => $params['work_phone'],
		                'ufCrm7Apps'           => $this->devriseAppId,
		                'ufCrm7DateRegister'   => $date_register,
		                'ufCrm7LastApp'        => $this->APP_ID,
		                'ufCrm7LastActive'     => date('Y-m-d H:i:s'),
		                'utmCampaign'          => $this->APP_ID,
		                'utmMedium'            => 'marketplace',
		            ]
	            ];
		        if ( $this->isConcurent ) {
		            $fields['fields']['stageId'] = 'DT164_7:UC_BNIYKV';
		        }
		        
		        if ( $domainData['object_id'] ) {
		            $fields['fields']['parentId1'] = $domainData['object_id'];
		        }

		        $objectAdd = $this->callDevRise('crm.item.add', $fields);
		        
		        if ( !empty($objectAdd['result']['item']) ) {
		            $this->devriseUserId = $objectAdd['result']['item']['id'];
		            $params['object_id'] = $this->devriseUserId;
		        }
		    } else {
		        $fields = [
		            'entityTypeId' => 164, 
		            'id'           => $appPortalsUserDb['object_id'],
		            'fields' => [
		                'title' => "{$params['last_name']} {$params['name']}",
		                'ufCrm7IsAdmin'        => $params['is_admin'] ? 'Y' : 'N', // Так/Ні
		                'ufCrm7FirstName'      => $params['name'],
		                'ufCrm7LastName'       => $params['last_name'],
		                'ufCrm7SecondName'     => $params['second_name'],
		                'ufCrm7Lang'           => $params['lang'],
		                'ufCrm7Position'       => $params['position'] . ' | ' . $params['profession'],
		                'ufCrm7Photo'          => $params['photo'],
		                'ufCrm7LastApp'        => $this->APP_ID,
		                'ufCrm7LastActive'     => date('Y-m-d H:i:s'),
		                'ufCrm7DateRegister'   => $date_register,
		            ]
		        ];
		        
		        if ( !empty($itemData['result']['item']['ufCrm7Apps']) ) {
		            $fields['fields']['ufCrm7Apps'] = $itemData['result']['item']['ufCrm7Apps'];
		            $fields['fields']['ufCrm7Apps'][] = $this->devriseAppId;
		            $fields['fields']['ufCrm7Apps'] = array_unique($fields['fields']['ufCrm7Apps']);
		        } else {
		            $fields['fields']['ufCrm7Apps'] = [$this->devriseAppId];
		        }
		        
		        if ( $domainData['object_id'] ) {
		            $fields['fields']['parentId1'] = $domainData['object_id'];
		        }
		        
		        if ( $this->isConcurent ) {
		            $fields['fields']['stageId'] = 'DT164_7:UC_BNIYKV';
		        }
		        if ( !empty($params['email']) ) {
		            $fields['fields']['ufCrm7Email'] = $params['email'];
		        }
		        if ( !empty($params['personal_phone']) ) {
		            $fields['fields']['ufCrm7PersonalPhone'] = $params['personal_phone'];
		        }
		        if ( !empty($params['personal_mobile']) ) {
		            $fields['fields']['ufCrm7PersonalMobile'] = $params['personal_mobile'];
		        }
		        if ( !empty($params['work_phone']) ) {
		            $fields['fields']['ufCrm7WorkPhone'] = $params['work_phone'];
		        }
		        
		        $objectUpdate = $this->callDevRise('crm.item.update', $fields);
		    }
    		
		    if ( !empty($appPortalsUserDb) ) {
		        $this->devriseUserDbId = $appPortalsUserDb['id'];
		        
		        $result = $this->update($params, 'app_portals_users', "id={$appPortalsUserDb['id']}");
    		} else {
    		    $this->devriseUserDbId = $this->insert($params, 'app_portals_users');
    		}
		}
		
		public function formatUserData($userData) {
		    $date_register = '';
		    if ( !empty($userData['DATE_REGISTER']) ) {
		        $dateArr = explode('T', $userData['DATE_REGISTER']);
		        $date_register = $dateArr[0];
		    }
		    
		    $birthdate = '';
		    if ( !empty($userData['PERSONAL_BIRTHDAY']) ) {
		        $dateArr = explode('T', $userData['PERSONAL_BIRTHDAY']);
		        $birthdate = $dateArr[0];
		    }
		    
		    return [
		        'domain'          => $this->domain,
		        'user_id'         => $userData['ID'],
		        'email'           => isset($userData['EMAIL']) ? $userData['EMAIL'] : '',
		        'date_register'   => $date_register,
		        'name'            => isset($userData['NAME']) ? $userData['NAME'] : '',
		        'last_name'       => isset($userData['LAST_NAME']) ? $userData['LAST_NAME'] : '',
		        'second_name'     => isset($userData['SECOND_NAME']) ? $userData['SECOND_NAME'] : '',
		        'profession'      => isset($userData['PERSONAL_PROFESSION']) ? $userData['PERSONAL_PROFESSION'] : '',
		        'birthdate'       => $birthdate,
		        'photo'           => isset($userData['PERSONAL_PHOTO']) ? $userData['PERSONAL_PHOTO'] : '',
		        'personal_phone'  => isset($userData['PERSONAL_PHONE']) ? $userData['PERSONAL_PHONE'] : '',
		        'personal_mobile' => isset($userData['PERSONAL_MOBILE']) ? $userData['PERSONAL_MOBILE'] : '',
		        'work_phone'      => isset($userData['WORK_PHONE']) ? $userData['WORK_PHONE'] : '',
		        'city'            => isset($userData['PERSONAL_CITY']) ? $userData['PERSONAL_CITY'] : '',
		        'facebook'        => isset($userData['UF_FACEBOOK']) ? $userData['UF_FACEBOOK'] : '',
		        'position'        => isset($userData['WORK_POSITION']) ? $userData['WORK_POSITION'] : '',
		        'is_active'       => isset($userData['ACTIVE']) ? $userData['ACTIVE'] : 0,
		    ];
		}
		
		public function installAppDataToDb($mergeArData = []) {
		    
		    $arData = array(
		        'app.info' => array(
		            'method' => 'app.info',
		            'params' => array()
		        ),
		        'user.current' => array(
		            'method' => 'user.current',
		            'params' => array()
		        ),
		        'user.admin' => array(
		            'method' => 'user.admin',
		            'params' => array()
		        ),
		        'user.get' => array(
		            'method' => 'user.get',
		            'params' => array()
		        ),
		        'imopenlines.network.join' => array(
		            'method' => 'imopenlines.network.join',
		            'params' => array( 'CODE' => $this->openlineCode )
		        ),
		        'event.bind' => array(
		            'method' => 'event.bind',
		            'params' => array(
		                'EVENT'   => 'OnAppUninstall',
		                'HANDLER' => 'https://app.devrise.com.ua/marketplace/uninstall.php?token=uimyapp&app_id=' . $this->APP_ID,
		            )
		        ),
		    );
		    
		    $arData = array_merge($arData, $mergeArData);
		    
		    $arResult = $this->callBatch($arData, 0);
		    
		    $app_portals_users  = [];
		    $marketplace_tokens = [];
		    
		    if ( !empty($arResult['result']['result']) ) {
		        $data = $arResult['result']['result'];

                $sysText = '';
		        
		        if ( !empty($data['app.info']) ) {
		            $licenseName = $data['app.info']['LICENSE'];
		            
		            /*
		             * Get users data
		             */
    		        if ( !empty($data['user.get']) ) {
    		            foreach ( $data['user.get'] as $userData ) {
    		                
    		                $formatUserData = $this->formatUserData($userData);
    		                
    		                $sysText .= "{$formatUserData['email']} {$formatUserData['name']} {$formatUserData['last_name']} {$formatUserData['second_name']} {$formatUserData['position']} {$formatUserData['profession']}";
    		                
                            if ( $userData['ID'] == 1 ) {
                                $app_portals_users[$userData['ID']] = $formatUserData;
                            }
    		            }
    		        }
    		        
    		        if ( !empty($data['user.current']) ) {
    		            
    		            $userData     = $data['user.current'];
    		            $this->userId = $userData['ID'];
    		            
    		            $app_portals_users[$userData['ID']] = $this->formatUserData($userData);
    		            
    		            $sysText .= "{$app_portals_users[$userData['ID']]['email']} {$app_portals_users[$userData['ID']]['name']} {$app_portals_users[$userData['ID']]['last_name']} {$app_portals_users[$userData['ID']]['second_name']} {$app_portals_users[$userData['ID']]['position']} {$app_portals_users[$userData['ID']]['profession']}";
    		            
    		        }
    		        
    		        $usersList = $app_portals_users;
    		        
    		        $usersIds = [];
    		        foreach ( $app_portals_users as $userId => $userAr ) {
    		            $usersIds[] = $userId;
    		        }
    		        
    		        $usersIdsArray = implode("','",$usersIds);
    		        $appPortalsUsersDb = $this->selectAll('*', 'app_portals_users', "domain='$this->domain' AND user_id IN ('".$usersIdsArray."')");
    		        
    		        if ( !empty($appPortalsUsersDb) ) {
    		            foreach ( $appPortalsUsersDb as $userDataFromDb ) {
    		                $userAr = $app_portals_users[$userDataFromDb['user_id']];
    		                $result = $this->update($userAr, 'app_portals_users', "id={$userDataFromDb['id']}");
    		                
    		                unset($app_portals_users[$userDataFromDb['user_id']]);
    		            }
    		        }
    		        
    		        foreach ( $app_portals_users as $userId => $userAr ) {
    		            $result = $this->insert($userAr, 'app_portals_users');
    	            }

    	            
    	            /*
    	             * Find concurent name
    	             */
    	            $concurentName = $this->findCouncurentName($sysText);

    	            
    	            
    	            /*
    	             * Create lead in DevRise portal
    	             */
    	            $objectId     = 0;
    	            $needToCreate = 0;
    	            $usersCount   = !empty($arResult['result']['result_total']['user.get']) ? $arResult['result']['result_total']['user.get'] : 0;
    	            $licenseId    = $this->getlicenseId($licenseName, $usersCount);
    	            
    	            $domainDataTableDb = $this->selectRow('*', 'domains_data', "domain='$this->domain'");
    	            if ( !empty($domainDataTableDb['object_id']) ) {
    	                $objectData  = $this->callDevRise('crm.lead.get', ['id'=> $domainDataTableDb['object_id']]);
    	                
    	                if ( !empty($objectData['error_description']) && $objectData['error_description'] == 'Not found' ) {
    	                    $needToCreate = 1;
    	                } else {
    	                    if ( !empty($objectData['result']) ) {
    	                        $objectId = $objectData['result']['ID'];
    	                        
    	                        if ( !empty($licenseId) && $licenseId != $objectData['result']['UF_CRM_1616264230'] ) {
    	                            $fields = [
    	                                'id' => $objectId,
    	                                'fields' => [
    	                                    'UF_CRM_1616264230' => $licenseId,
    	                                    'UF_CRM_1635949799' => $licenseName,
    	                                ]
    	                            ];
    	                            if ( $concurentName ) {
    	                                $fields['fields']['UF_CRM_1617907057'] = $concurentName;
    	                            }
    	                            $objectAdd = $this->callDevRise('crm.lead.update', $fields);
    	                        }
    	                    }
    	                }
    	            } else {
    	                $needToCreate = 1;
    	            }
    	            
    	            $currentDate = date('d-m-Y');
    	            
    	            if ( $needToCreate ) {
    	                
    	                $leadName = $this->domain;
    	                $fields = [
    	                    'fields' => [
    	                        'UF_CRM_1549035496636' => 1367,
    	                        'UF_CRM_1616264230' => $licenseId,
    	                        'UF_CRM_1635949799' => $licenseName,
    	                        'UF_CRM_1617288300' => $this->userId,
    	                        'UF_CRM_1617288416' => !empty($usersList[$this->userId]['lang']) ? ($usersList[$this->userId]['lang'] == 'ru' ? 1023 : ($usersList[$this->userId]['lang'] == 'ua' ? 1021 : '')) : 1021,
    	                        'UF_CRM_1617905902' => $this->domain,
    	                        'UF_CRM_1617912728' => $currentDate,
    	                        'UF_CRM_1617907057' => $concurentName,
    	                        'POST'              => $usersList[$this->userId]['position'] . ' | ' . $usersList[$this->userId]['profession'],
    	                        'NAME'              => $usersList[$this->userId]['name'],
    	                        'LAST_NAME'         => $usersList[$this->userId]['last_name'],
    	                        'SECOND_NAME'       => $usersList[$this->userId]['second_name'],
    	                        'ADDRESS'           => $usersList[$this->userId]['city'],
    	                        'TITLE'             => $leadName,
    	                        'SOURCE_ID'         => 3,
    	                    ]
    	                ];
    	                $objectAdd = $this->callDevRise('crm.lead.add', $fields);
    	                
    	                if ( !empty($objectAdd['result']) ) {
    	                    $objectId = $objectAdd['result'];
    	                }
    	            }

    	            $tokenData = $this->selectRow('*', 'marketplace_tokens', "domain='$this->domain' AND app_id='$this->APP_ID'");
    	            
    	            $updateToken = [
		                'dialog_id'     => !empty($data['imopenlines.network.join']) ? $data['imopenlines.network.join'] : 0,
		                'openline_code' => $this->openlineCode,
    	                'version'       => !empty($data['app.info']['VERSION']) ? $data['app.info']['VERSION'] : 0,
    	            ];
    	            
    	            $this->dialogId = $updateToken['dialog_id'];
    	            
    	            $needToCreate = 0;
    	            
    	            if ( !empty($tokenData['object_id']) ) {
    	                $fields = [
    	                    'entityTypeId' => 131, // Застосунки клієнтів
	                        'id'           => $tokenData['object_id'],
    	                ];
    	                $itemData = $this->callDevRise('crm.item.get', $fields);
    	                
    	                if ( !empty($itemData['error']) && $itemData['error'] == 'NOT_FOUND' ) {
    	                    $needToCreate = 1;
    	                }
    	            } else {
    	                $needToCreate = 1;
    	            }
    	            
    	            $params = [
    	                'domain'      => $this->domain,
    	                'license'     => $licenseName,
    	                'users_count' => $usersCount,
    	                'object_id'   => $objectId,
    	                'member_id'   => $this->memberId,
    	            ];
    	            
    	            if ( $needToCreate ) {
    	                $fields = [
    	                    'entityTypeId' => 131, // Застосунки клієнтів
    	                    'fields' => [
    	                        'title'                 => $this->appName,
    	                        'ufCrm9AppId'           => $this->APP_ID,
    	                        'ufCrm9Version'         => $data['app.info']['VERSION'],
    	                        'ufCrm9IsInstalled'     => 1987, // Так - 1987 , Ні - 1989
    	                        'stageId'               => 'DT131_9:NEW',
    	                        'ufCrm9Portal'          => 'L_' . $objectId,
    	                        'parentId1'             => $objectId,
    	                        'ufCrm9Domain'          => $this->domain,
    	                        'ufCrm9LastActivity'    => $currentDate,
    	                        'ufCrm9UserIdInstall'   => $this->userId,
    	                        'ufCrm9DateInstall'     => $currentDate,
    	                        'ufCrm9LeadId'          => $objectId,
    	                    ]
    	                ];
    	                
    	                
    	                $objectAdd = $this->callDevRise('crm.item.add', $fields);
    	                
    	                if ( !empty($objectAdd['result']['item']) ) {
    	                    $updateToken['object_id'] = $objectAdd['result']['item']['id'];
    	                }
    	            } else {
    	                $fields = [
    	                    'entityTypeId' => 131, // Застосунки клієнтів
    	                    'id'           => $tokenData['object_id'],
    	                    'fields' => [
    	                        'title'                 => $this->appName,
    	                        'ufCrm9Version'         => $data['app.info']['VERSION'],
    	                        'ufCrm9IsInstalled'     => 1987, // Так - 1987 , Ні - 1989
    	                        'stageId'               => 'DT131_9:NEW',
    	                        'ufCrm9Portal'          => 'L_' . $objectId,
    	                        'parentId1'             => $objectId,
    	                        'ufCrm9LastActivity'    => $currentDate,
    	                        'ufCrm9UserIdInstall'   => $this->userId,
    	                        'ufCrm9DateInstall'     => $currentDate,
    	                        'ufCrm9LeadId'          => $objectId,
    	                    ]
    	                ];
    	                $objectUpdate = $this->callDevRise('crm.item.update', $fields);
    	            }
    	            
    	            if ( !empty($tokenData['id']) ) {
    	               $result = $this->update($updateToken, 'marketplace_tokens', "id={$tokenData['id']}");
    	            }

    	            if ( !empty($domainDataTableDb) ) {
    	                $result = $this->update($params, 'domains_data', "id={$domainDataTableDb['id']}");
    	            } else {
    	                $result = $this->insert($params, 'domains_data');
    	            }
		        }
		    }
		}
		
		public function json_encode_advanced(array $arr, $sequential_keys = true, $quotes = true, $beautiful_json = true) {
		    
		    $output = "{";
		    $count = 0;
		    foreach ($arr as $key => $value) {
		        
		        $output .= ($quotes ? '"' : '') . $key . ($quotes ? '"' : '') . ' : ';
		        
		        if (is_array($value)) {
		            $output .= $this->json_encode_advanced($value, $sequential_keys, $quotes, $beautiful_json);
		        } else if (is_bool($value)) {
		            $output .= ($value ? 'true' : 'false');
		        } else if (is_numeric($value)) {
		            $output .= $value;
		        } else {
		            $value = str_replace('"',"\'", $str_replace("'","\'", $str_replace("`","\'", $value)));
		            $output .= ($quotes || $beautiful_json ? '"' : '') . $value . ($quotes || $beautiful_json ? '"' : '');
		        }
		        
		        if (++$count < count($arr)) {
		            $output .= ', ';
		        }
		    }
		    
		    $output .= "}";
		    
		    return $output;
		}
		
		public function generateToken() 
		{
		    $token = bin2hex(random_bytes(15));
		    $tokenDb = $this->selectRow('*', $this->tableApps, "token='".$token."'");
		    if ( !empty($tokenDb) ) {
		        $token = $this->generateToken();
		    }
		    return $token;
		}
		
		public function getApplicationSettings()
		{
		    return $this->selectRow('*', $this->tableApps, "domain='$this->domain' AND app_code='".$this->APP_ID."'");
		}
		
		public function convertSeconds($seconds, $escapeSeconds = 0) {
		    $t = round($seconds);
		    
		    if ( $escapeSeconds ) {
		        return sprintf('%02d:%02d', ($t/3600),($t/60%60));
		    } else {
                return sprintf('%02d:%02d:%02d', ($t/3600),($t/60%60), $t%60);
		    }
		}
		
		public static function encodeString($string, $key) {
		    $j = 0;
		    $hash = '';
		    $key = sha1($key);
		    $strLen = strlen($string);
		    $keyLen = strlen($key);
		    for ($i = 0; $i < $strLen; $i++) {
		        $ordStr = ord(substr($string,$i,1));
		        if ($j == $keyLen) { $j = 0; }
		        $ordKey = ord(substr($key,$j,1));
		        $j++;
		        $hash .= strrev(base_convert(dechex($ordStr + $ordKey),16,36));
		    }
		    return $hash;
		}
		
		public static function decodeString($string, $key) {
		    $j = 0;
		    $hash = '';
		    $key = sha1($key);
		    $strLen = strlen($string);
		    $keyLen = strlen($key);
		    for ($i = 0; $i < $strLen; $i+=2) {
		        $ordStr = hexdec(base_convert(strrev(substr($string,$i,2)),36,16));
		        if ($j == $keyLen) { $j = 0; }
		        $ordKey = ord(substr($key,$j,1));
		        $j++;
		        $hash .= chr($ordStr - $ordKey);
		    }
		    return $hash;
		}
		
		public function getFieldsData1($typeRelation, $fieldsList, $counter, $selectedKey = '', $selectedValue = '') {
		    
		    $fieldsHtml = array();
		    
		    foreach($fieldsList as $key => $arField)
		    {
		        
		        if ( $key == $selectedKey ) {
		            $value = $selectedValue;
		        } else {
		            $value ='';
		        }
		        
		        $return = '';
		        $caseTypes = array();
		        switch($arField['type'])
		        {
		            case 'enumeration':
		                
		                if(!empty($arField[ 'items' ])) {
		                    $arList = array_column($arField[ 'items' ], 'VALUE', 'ID');
		                    
		                    $return = CPrintForm::select(
		                        [
		                            'NAME'     => $counter . '[value]',
		                            'ID'       => $key,
		                            'TEXT'     => !empty($arField['formLabel']) ? $arField['formLabel'] : $arField['title'],
		                            'VALUE'    => $value,
		                        ],
		                        $arList);
		                    
		                    $caseTypes = array('=','!=');
		                }
		                break;
		            case 'date':
		                $return = CPrintForm::input(
		                [
							'NAME'     => $counter . '[value]',
							'ID'       => $key,
							'TEXT'     => !empty($arField['formLabel']) ? $arField['formLabel'] : $arField['title'],
							'TYPE'     => 'date',
							'VALUE'    => $value,
		                ]);
		                $caseTypes = array('=','!=','>','>=','<','<=');
		                break;
		            case 'datetime':
		                $return = CPrintForm::input(
		                [
							'NAME'     => $counter . '[value]',
							'ID'       => $key,
							'TEXT'     => !empty($arField['formLabel']) ? $arField['formLabel'] : $arField['title'],
							'TYPE'     => 'datetime-local',
							'VALUE'    => $value,
		                ]);
		                $caseTypes = array('=','!=','>','>=','<','<=');
		                break;
		            case 'integer':
		            case 'double':
		                $return = CPrintForm::input(
		                [
							'NAME'     => $counter . '[value]',
							'ID'       => $key,
							'TEXT'     => !empty($arField['formLabel']) ? $arField['formLabel'] : $arField['title'],
							'TYPE'     => 'number',
							'VALUE'    => $value,
		                ]);
		                $caseTypes = array('=','!=','>','>=','<','<=');
		                break;
		            case 'S:employee':
		                $arUser = [];
		                $return = CPrintForm::select(
		                    [
		                        'NAME'     => $counter . '[value]',
		                        'ID'       => $key,
		                        'TEXT'     => !empty($arField['formLabel']) ? $arField['formLabel'] : $arField['title'],
		                        'VALUE'    => $value,
		                    ],
		                    $arUser);
		                break;
		            case 'string':
		            case 'char':
		                
		                $rowCount = !empty($arField['ROW_COUNT']) ? $arField['ROW_COUNT'] : 1;
		                $return = CPrintForm::input(
		                    [
		                        'NAME'     => $counter . '[value]',
		                        'TEXT'     => !empty($arField['formLabel']) ? $arField['formLabel'] : $arField['title'],
		                        'ID'       => $key,
		                        'TYPE'     => 'text',
		                        'VALUE'    => $value,
		                    ]);
		                
		                $caseTypes = array('=','!=','%');
		                
		                break;
		            default:
		                $return = array();
		                break;
		        }
		        
		        if ( $return ) {
		            $fieldsHtml[$key] = array(
		                'NAME'  => !empty($arField['formLabel']) ? $arField['formLabel'] : $arField['title'],
		                'FIELD' => $return,
		                'ID'    => $key,
		                'TYPE'  => $arField['type'],
		                'TYPE_TEXT' => !empty($typeRelation[$arField['type']]) ? $typeRelation[$arField['type']] : '',
		                'CASES' => $caseTypes,
		            );
		        }
		        
		    }
		    return $fieldsHtml;
		}

	

	}