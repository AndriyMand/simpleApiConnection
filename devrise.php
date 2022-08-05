<?php 
require_once ($_SERVER['DOCUMENT_ROOT'] . '/app.d/marketplace/salaryReports/settings.php');
require_once ($_SERVER['DOCUMENT_ROOT'] . '/app.d.com.ua/crest.php');

class DevRise extends CRest
{
    public $oliCodes;
    function __construct($checkConnection = 1) {
        
        $APP_ID               = 'd.salary_accounting';
        $REDIRECT_URI         = 'https://app.d.com.ua/marketplace/salaryReports/index.php';
        
        $memberId = !empty($_POST['member_id']) ? $_POST['member_id'] : (!empty($_POST['auth']['member_id']) ? $_POST['auth']['member_id'] : '0');
        $domain   = !empty($_REQUEST['DOMAIN']) ? $_REQUEST['DOMAIN'] : (!empty($_REQUEST['auth']['domain']) ? $_REQUEST['auth']['domain'] : '');
        $lang     = (!empty($_REQUEST['LANG']) && in_array($_REQUEST['LANG'], array('ru','ua')) ) ? $_REQUEST['LANG'] : 'ru';
        
        $access_token = !empty($_POST['AUTH_ID']) ? $_POST['AUTH_ID'] : (!empty($_POST['auth']['access_token']) ? $_POST['auth']['access_token'] : '');
        
        parent::__construct($domain, $APP_ID, $C_REST_CLIENT_ID, $C_REST_CLIENT_SECRET, $REDIRECT_URI, $memberId, $lang, $access_token, $checkConnection);
    }
    
    public function getEntityItem($settings)
    {
        if ( !empty($settings) ) {
            $settings = json_decode($settings, true);
            $settings = !empty($settings) ? $settings : array();
            return $settings;
        } else {
            return array();
        }
    }
    
    public function printLog($line, $startTime)
    {
        $execution_time = (microtime(true) - $startTime);
    }
    
    public function updateEntityItem($properties)
    {
        $properties = json_encode($properties);
        $appSettings = $this->selectRow('*', $this->tableApps, "domain='$this->domain' AND app_code='".APP_ID."'");
        if ( !empty($appSettings) ) {
            return $this->update(array('settings' => $properties), $this->tableApps, "id={$appSettings['id']}");
        } else {
            $settings = array(
                'domain'   => $this->domain,
                'app_code' => APP_ID,
                'app_type' => 1,
                'settings' => $properties
            );
            return $this->insert($settings, $this->tableApps);
        }
    }
    
    public function getEntityData()
    {
        $appSettings = $this->selectRow('*', $this->tableApps, "domain='$this->domain' AND app_code='".APP_ID."'");
        if ( !empty($appSettings['settings']) && $this->isJSON($appSettings['settings']) ) {
            $settingsData = $this->getEntityItem($appSettings['settings']);
        } else {
            $settingsData = array();
        }
        
        return $settingsData;
    }
    
    public function getUserData($currentUserId, $currentIsAdmin)
    {
        $params = array(
            'FILTER' => array(
                'USER_TYPE' => 'employee',
                'ACTIVE' => true,
            )
        );
        
        if ( $this->domain == 'dobroljudyam.bitrix24.ua' ) {
            $params = array(
                'FILTER' => array(
                    'USER_TYPE' => 'employee',
                )
            );
        }
        
        if ( $currentUserId == 292 && $this->domain == 'bptech.bitrix24.ua' ) {
            $params['FILTER']['UF_DEPARTMENT'] = 42;
        }
        
        $userArray = $this->getObjectList($params, 'user.get');
        $emploeeArray = array();
        $userList = array();
        
        foreach ( $userArray as $index => $user ) {
            
            if ( $user['NAME'] || $user['LAST_NAME'] ) {
                $userName = "{$user['NAME']} {$user['LAST_NAME']}";
            } else {
                $userName = $user['EMAIL'];
            }
            
            $emploeeArray[$user['ID']] = array(
                'WORK_POSITION'   => $user['WORK_POSITION'],
                'FULL_NAME'       => $userName,
                'LINK_TO_PROFILE' => "<a href='https://{$this->domain}/company/personal/user/{$user['ID']}/' target='_blank'>$userName</a>",
                'PERSONAL_PHOTO'  => $user['PERSONAL_PHOTO'],
                'PHONE' => $user['PERSONAL_MOBILE'],
                'EMAIL' => $user['EMAIL'],
                'CITY' => $user['PERSONAL_CITY']
            );
        }
        
        return $emploeeArray;
    }
    
    
    public function buildDepartmantsTree($departments)
    {
        $new = array();
        foreach ($departments as $a){
            $index = !empty($a['PARENT']) ? $a['PARENT'] : 0;
            $new[$index][] = $a;
        }
        $tree = $this->createTree($new, array(array_shift($departments)));
        
        return $tree;
    }
    
    public function getDepartmentsTree($departments)
    {
        $departmentsTree = [];
        foreach ($departments as $parentId => $parent) {
            $departmentsTree[$parentId] = array_unique($this->buildRelations($departments, $parentId));
        }
        return $departmentsTree;
    }
    
    public function buildRelations($departments, $parentId) {
        $result = [];
        foreach ($departments as $id => $depart) {
            
            if (!empty($depart['PARENT']) && $depart['PARENT'] == $parentId) {
                $result[] = $id;
                $result = array_merge($this->buildRelations($departments, $id), $result);
            }
            
        }
        return $result;
    }
    
    public function getDepartmentOptions($tree, $selected, &$subtasksIds)
    {
        $departments_selection = '';
        $subtasksIds = [];
        foreach ($tree as $department) {
            
            if (!in_array($department['ID'], $subtasksIds)) {
                $departments_selection .= $this->getSubtasksOptions($department, '', $selected, $subtasksIds);
            }
            
        }
        $_REQUEST['subtasks_ids'] = null;
        return $departments_selection;
    }
    
    public function getSubtasksOptions($department, $level, $selectedId) {
        
        $selected = in_array($department['ID'], $selectedId) ? 'selected="selected"' : '';
        $responsible_task = '<option value="'.$department['ID'].'" '.$selected.'>'.$level . $department['NAME'].'</option>';
        
        $_REQUEST['subtasks_ids'][] = $department['ID'];
        
        if (!empty($department['CHILDREN'])) {
            $level .= '. ';
            foreach ($department['CHILDREN'] as $hildsDepartments) {
                $responsible_task .= $this->getSubtasksOptions($hildsDepartments, $level, $selectedId);
            }
        }
        return $responsible_task;
    }
    
    public function createTree(&$list, $parent){
        $tree = array();
        foreach ($parent as $k=>$l){
            if(isset($list[$l['ID']])){
                $l['CHILDREN'] = $this->createTree($list, $list[$l['ID']]);
            }
            $tree[] = $l;
        }
        return $tree;
    }
    
    public function getInterval($start, $end, $format='d.m.Y')
    {
        return array_map(create_function('$item', 'return date("'.$format.'", $item);'),range(strtotime($start), strtotime($end), 60*60*24));
    }
    
    public function getTasksByGroup($ids) {
        $resultArray = array();
        $params = array(
            'filter' => array('GROUP_ID' => $ids)
        );
        $groupTasks = $this->getTasksList($params);
        foreach ( $groupTasks as $taskId => $taskData ) {
            $resultArray[] = $taskId;
        }
        return $resultArray;
    }
    
    public function getSubtasks($id) {
        $resultArray = array($id);
        $params = array(
            'filter' => array('PARENT_ID' => $id)
        );
        $groupTasks = $this->getTasksList($params);
        foreach ( $groupTasks as $taskId => $taskData ) {
            $resultArray = array_merge($resultArray, $this->getSubtasks($taskId));
        }
        return $resultArray;
    }
    
    public function getSubtasksOptionsPre($task, $parentRelations, $level, $tasksCount, $subtasksIds) {
        $tasksCount++;
        $subtasksIds[] = $task['id'];
        $responsible_task = '<option value="'.$task['id'].'">'.$level . $task['title'].'</option>';
        
        $level .= '. ';
        
        if ( !empty( $parentRelations[$task['id']] ) ) {
            foreach ( $parentRelations[$task['id']] as $result ) {
                $responsible_task .= $this->getSubtasksOptionsPre($result, $parentRelations, $level, $tasksCount, $subtasksIds);
            }
        }
        
        return $responsible_task;
    }
    
    public function getSubtasksOptionsMain($task, $level, $selectedId, &$subtasksIds, &$tasksCount) {
        
        $tasksCount++;
        $selected = (!empty($_POST['responsible_task']) && in_array($task['id'], $_POST['responsible_task'])) ? 'selected="selected"' : '';
        $responsible_task = '<option value="'.$task['id'].'" '.$selected.'>'.$level . $task['title'].'</option>';
        
        $subtasksIds[] = $task['id'];
        
        $params = array(
            'order'  => array('ID' => 'ASC'),
            'filter' => array('PARENT_ID' => $task['id']),
            'select' => array('GROUP_ID', 'ID', 'TITLE', 'RESPONSIBLE_ID', 'PARENT_ID')
        );
        $groupTasks  = $this->getTasksList($params);
        $level .= '- ';
        
        foreach ( $groupTasks as $result ) {
            $responsible_task .= $this->getSubtasksOptionsMain($result, $level, $selectedId, $subtasksIds, $tasksCount);
        }
        
        return $responsible_task;
    }

    public function secondsToTime($seconds, $blocks = 2) {
        $t = round($seconds);
		return sprintf('%02d:%02d:%02d', ($t/3600),($t/60%60), $t%60);

    }
    
    public function createDateRangeArray($date1, $date2, $format = 'd.m.Y' ) {
        $dates = array();
        $current = strtotime($date1);
        $date2 = strtotime($date2);
        $stepVal = '+1 day';
        while( $current <= $date2 ) {
            $dates[] = date($format, $current);
            $current = strtotime($stepVal, $current);
        }
        return $dates;
    }
}


