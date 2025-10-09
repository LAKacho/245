<?php
session_start();
if (!isset($_COOKIE['id_tpa'])) { header("Location: index.php"); exit(); }

/* ---------- DB connections ---------- */
$link = mysqli_connect("localhost", "root", "", "classnost");
if (!$link) { die("Ошибка подключения classnost: " . mysqli_connect_error()); }
mysqli_set_charset($link, "utf8mb4");

$linkTest = mysqli_connect("localhost", "root", "", "test");
if (!$linkTest) { die("Ошибка подключения test: " . mysqli_connect_error()); }
mysqli_set_charset($linkTest, "utf8mb4");

$linkXTVR = mysqli_connect("localhost", "root", "", "xtvr");
if (!$linkXTVR) { die("Ошибка подключения xtvr: " . mysqli_connect_error()); }
mysqli_set_charset($linkXTVR, "utf8mb4");

$linkTPA = mysqli_connect("localhost", "root", "", "tpatb");
if (!$linkTPA) { die("Ошибка подключения tpatb: " . mysqli_connect_error()); }
mysqli_set_charset($linkTPA, "utf8mb4");

/* ---------- Admin auth ---------- */
$adminLogin = $_COOKIE['id_tpa'] ?? '';
$stmt = mysqli_prepare($link, "SELECT 1 FROM administrators WHERE user_login=?");
mysqli_stmt_bind_param($stmt, "s", $adminLogin);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
if (mysqli_stmt_num_rows($stmt) === 0) { header("Location: category.php"); exit(); }
mysqli_stmt_close($stmt);

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

/* ---------- Helpers ---------- */
function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function dir_safe($v){ return strtolower($v)==='asc'?'asc':'desc'; }
function flash_set($m){ $_SESSION['flash']=$m; }
function flash_get(){ if(isset($_SESSION['flash'])){$m=$_SESSION['flash'];unset($_SESSION['flash']);return $m;} return ""; }
function stmt_bind_params($stmt, $types, $params){
    $refs = []; $refs[] = $types;
    foreach($params as $k=>&$v){ $refs[]=&$v; } // ссылки обязательны
    return call_user_func_array([$stmt,'bind_param'],$refs);
}
function allowed_percents_15(){ static $vals=null; if($vals!==null) return $vals; $vals=[]; for($k=0;$k<=15;$k++){ $v=(int)round($k*100/15); if(!in_array($v,$vals,true)) $vals[]=$v; } return $vals; }
function nearest_allowed($x){ $allowed=allowed_percents_15(); $best=$allowed[0]; $dmin=abs($x-$best); foreach($allowed as $v){ $d=abs($x-$v); if($d<$dmin){ $dmin=$d; $best=$v; } } return $best; }

/* ---------- Tabs ---------- */
$allowedTabs = ['nastav','starchenstvo','mentorship','seniority','tests','xtvr','tpatb'];
$tabLabels = [
    'nastav' => 'Наставники',
    'starchenstvo' => 'Старосты',
    'mentorship' => 'Результаты наставничества',
    'seniority' => 'Результаты староства',
    'tests' => 'Тесты',
    'xtvr' => 'XTVR',
    'tpatb' => 'TPATB'
];
$active_tab = $_GET['tab'] ?? 'mentorship';
if (!in_array($active_tab, $allowedTabs, true)) $active_tab = 'mentorship';

/* ---------- Registry: TESTS (DSM/DPK/SR/TDVS2) ---------- */
$testsModels = [
    'dsm' => [
        'label' => 'DSM',
        'table' => 'sess1',
        'pk'    => 'imdex7',
        'base_where' => "level2 = 5",
        'columns' => ['user_login','ERROR','times','level2','level3','error1','error2','error3','sum','TIME_TEST'],
        'types'   => ['user_login'=>'text','ERROR'=>'int','times'=>'datetime','level2'=>'int','level3'=>'text','error1'=>'int','error2'=>'int','error3'=>'int','sum'=>'datetime','TIME_TEST'=>'int'],
        'labels'  => ['user_login'=>'Табельный №','ERROR'=>'Ошибки','times'=>'Дата сдачи','level2'=>'LEVEL2','level3'=>'LEVEL3','error1'=>'error1','error2'=>'Оценка','error3'=>'error3','sum'=>'Дата','TIME_TEST'=>'TIME_TEST'],
        'searchable' => ['user_login','level3'],
        'date_field' => 'sum'
    ],
    'sess_dpk' => [
        'label' => 'DPK',
        'table' => 'sess_dpk',
        'pk'    => 'imdex7',
        'base_where' => "level2 = 'test_dpk'",
        'columns' => ['user_login','ERROR','times','level2','level3','error1','error2','error3','sum','TIME_TEST'],
        'types'   => ['user_login'=>'text','ERROR'=>'int','times'=>'datetime','level2'=>'text','level3'=>'text','error1'=>'int','error2'=>'int','error3'=>'int','sum'=>'datetime','TIME_TEST'=>'text'],
        'labels'  => ['user_login'=>'Табельный №','ERROR'=>'Ошибки','times'=>'Дата сдачи','level2'=>'LEVEL2','level3'=>'LEVEL3','error1'=>'error1','error2'=>'Оценка','error3'=>'error3','sum'=>'Дата','TIME_TEST'=>'TIME_TEST'],
        'searchable' => ['user_login','level3'],
        'date_field' => 'sum'
    ],
    'sess_sr' => [
        'label' => 'SR',
        'table' => 'sess_sr',
        'pk'    => 'imdex7',
        'base_where' => "level2 = 'test'",
        'columns' => ['user_login','ERROR','times','level2','level3','error1','error2','error3','sum','TIME_TEST'],
        'types'   => ['user_login'=>'text','ERROR'=>'int','times'=>'datetime','level2'=>'text','level3'=>'text','error1'=>'int','error2'=>'int','error3'=>'int','sum'=>'datetime','TIME_TEST'=>'text'],
        'labels'  => ['user_login'=>'Табельный №','ERROR'=>'Ошибки','times'=>'Дата сдачи','level2'=>'LEVEL2','level3'=>'LEVEL3','error1'=>'error1','error2'=>'Оценка','error3'=>'error3','sum'=>'Дата','TIME_TEST'=>'TIME_TEST'],
        'searchable' => ['user_login','level3'],
        'date_field' => 'sum'
    ],
    'sess_tdvs2' => [
        'label' => 'TDVS2',
        'table' => 'sess_tdvs2',
        'pk'    => 'index7',
        'base_where' => "",
        'columns' => ['user_login','ERROR','times','level2','level3','error1','error2','error3','sum','TIME_TEST'],
        'types'   => ['user_login'=>'text','ERROR'=>'int','times'=>'int','level2'=>'int','level3'=>'text','error1'=>'int','error2'=>'int','error3'=>'int','sum'=>'datetime','TIME_TEST'=>'text'],
        'labels'  => ['user_login'=>'Табельный №','ERROR'=>'Ошибки','times'=>'Время (сек)','level2'=>'LEVEL2','level3'=>'LEVEL3','error1'=>'error1','error2'=>'Оценка','error3'=>'error3','sum'=>'Дата','TIME_TEST'=>'TIME_TEST'],
        'searchable' => ['user_login','level3'],
        'date_field' => 'sum'
    ]
];
$tests_key = $_GET['t'] ?? 'dsm';
if (!array_key_exists($tests_key, $testsModels)) $tests_key = 'dsm';

/* ---------- Registry: XTVR ---------- */
$xtvrModels = [
    'sessions' => [
        'label' => 'Сессии',
        'table' => 'xtvr_sesions24',
        'pk'    => 'id24',
        'columns' => ['user_login','user_name','time_in','lesson_name','lesson_result','object_result','res1','res2','forOne','lesson_times','answer_24','video','old_result'],
        'types'   => ['user_login'=>'text','user_name'=>'text','time_in'=>'datetime','lesson_name'=>'text','lesson_result'=>'int','object_result'=>'int','res1'=>'datetime','res2'=>'int','forOne'=>'int','lesson_times'=>'int','answer_24'=>'longtext','video'=>'text','old_result'=>'int'],
        'labels'  => ['user_login'=>'Табельный №','user_name'=>'ФИО','time_in'=>'Начало','lesson_name'=>'Урок','lesson_result'=>'Результат урока','object_result'=>'Рез. объекта','res1'=>'Рез. дата/время','res2'=>'res2','forOne'=>'forOne','lesson_times'=>'Время урока (сек)','answer_24'=>'Ответ','video'=>'Видео','old_result'=>'Старый результат'],
        'searchable' => ['user_login','user_name','lesson_name','video'],
        'date_field' => 'time_in'
    ],
    'users' => [
        'label' => 'Пользователи',
        'table' => 'users_act',
        'pk'    => 'user_id',
        'columns' => ['user_login','user_password','user_name','user_cond','user_time'],
        'types'   => ['user_login'=>'text','user_password'=>'text','user_name'=>'text','user_cond'=>'int','user_time'=>'datetime'],
        'labels'  => ['user_login'=>'Табельный №','user_password'=>'Пароль','user_name'=>'ФИО','user_cond'=>'Статус','user_time'=>'Время'],
        'searchable' => ['user_login','user_name'],
        'date_field' => 'user_time'
    ],
    'attempt' => [
        'label' => 'Попытки',
        'table' => 'attempt',
        'pk'    => 'key_id',
        'columns' => ['user_login','datatime'],
        'types'   => ['user_login'=>'text','datatime'=>'datetime'],
        'labels'  => ['user_login'=>'Табельный №','datatime'=>'Дата/время'],
        'searchable' => ['user_login'],
        'date_field' => 'datatime'
    ],
    'standards' => [
        'label' => 'Стандарты',
        'table' => 'standart_xtvs',
        'pk'    => 'key_id',
        'columns' => ['user_login','datatime'],
        'types'   => ['user_login'=>'text','datatime'=>'datetime'],
        'labels'  => ['user_login'=>'Табельный №','datatime'=>'Дата/время'],
        'searchable' => ['user_login'],
        'date_field' => 'datatime'
    ],
];
$xtvr_key = $_GET['x'] ?? 'sessions';
if (!array_key_exists($xtvr_key, $xtvrModels)) $xtvr_key = 'sessions';

/* ---------- Registry: TPATB ---------- */
$tpatbModels = [
    'users' => [
        'label' => 'Пользователи',
        'table' => 'tpa_users',
        'pk'    => 'id',
        'columns'=> ['login','fio','department','created_at'],
        'types'  => ['login'=>'text','fio'=>'text','department'=>'text','created_at'=>'datetime'],
        'labels' => ['login'=>'Логин','fio'=>'ФИО','department'=>'Отдел','created_at'=>'Создан'],
        'searchable'=>['login','fio','department'],
        'date_field'=>'created_at'
    ],
    'tests' => [
        'label' => 'Тесты',
        'table' => 'tpa_tests',
        'pk'    => 'id',
        'columns'=> ['title','description','created_at','updated_at'],
        'types'  => ['title'=>'text','description'=>'longtext','created_at'=>'datetime','updated_at'=>'datetime'],
        'labels' => ['title'=>'Название','description'=>'Описание','created_at'=>'Создан','updated_at'=>'Обновлён'],
        'searchable'=>['title','description'],
        'date_field'=>'created_at'
    ],
    'attempts' => [
        'label' => 'Попытки',
        'table' => 'tpa_attempts',
        'pk'    => 'id',
        'columns'=> ['user_login','test_id','score','time_spent','attempt_number','test_date'],
        'types'  => ['user_login'=>'text','test_id'=>'int','score'=>'int','time_spent'=>'int','attempt_number'=>'int','test_date'=>'datetime'],
        'labels' => ['user_login'=>'Логин','test_id'=>'Тест ID','score'=>'Процент','time_spent'=>'Время (сек)','attempt_number'=>'Попытка','test_date'=>'Дата'],
        'searchable'=>['user_login'],
        'date_field'=>'test_date'
    ],
    'results' => [
        'label' => 'Результаты',
        'table' => 'tpa_results',
        'pk'    => 'id',
        'columns'=> ['attempt_id','question_id','answer','is_correct'],
        'types'  => ['attempt_id'=>'int','question_id'=>'int','answer'=>'text','is_correct'=>'int'],
        'labels' => ['attempt_id'=>'Попытка','question_id'=>'Вопрос','answer'=>'Ответ','is_correct'=>'Верно'],
        'searchable'=>['answer'],
        'date_field'=> null
    ],
];
$tpa_key = $_GET['p'] ?? 'users';
if (!array_key_exists($tpa_key, $tpatbModels)) $tpa_key = 'users';

/* ---------- POST: CRUD ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) { http_response_code(403); exit('CSRF'); }

    /* staff: nastav/starchenstvo */
    if (isset($_POST['scope']) && $_POST['scope']==='staff') {
        $table = $_POST['table'] ?? '';
        if (!in_array($table, ['nastav','starchenstvo'], true)) { flash_set('Неверная таблица'); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$table); exit(); }

        if (isset($_POST['add_record'])) {
            $user_login = $_POST['user_login']; $is_active = isset($_POST['is_active'])?1:0;
            $stmt = mysqli_prepare($link, "INSERT INTO $table (user_login, date_added, added_by, is_active) VALUES (?, NOW(), ?, ?)");
            if (!$stmt){ flash_set('SQL: '.mysqli_error($link)); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$table); exit(); }
            mysqli_stmt_bind_param($stmt, "ssi", $user_login, $adminLogin, $is_active);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set($ok?"Добавлено":"Ошибка");
        }
        if (isset($_POST['update_record'])) {
            $id=(int)$_POST['record_id']; $user_login=$_POST['user_login']; $is_active=isset($_POST['is_active'])?1:0;
            $stmt=mysqli_prepare($link,"UPDATE $table SET user_login=?, is_active=? WHERE id=?");
            if (!$stmt){ flash_set('SQL: '.mysqli_error($link)); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$table); exit(); }
            mysqli_stmt_bind_param($stmt,"sii",$user_login,$is_active,$id);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set($ok?"Обновлено":"Ошибка");
        }
        if (isset($_POST['delete_record'])) {
            $id=(int)$_POST['record_id'];
            $stmt=mysqli_prepare($link,"DELETE FROM $table WHERE id=?");
            if (!$stmt){ flash_set('SQL: '.mysqli_error($link)); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$table); exit(); }
            mysqli_stmt_bind_param($stmt,"i",$id);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set($ok?"Удалено":"Ошибка");
        }
        header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$table); exit();
    }

    /* mentorship/seniority */
    if (isset($_POST['scope']) && $_POST['scope']==='results') {
        $table = $_POST['table'] ?? '';
        if (!in_array($table, ['mentorship_results','seniority_results'], true)) { flash_set('Неверная таблица'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=mentorship'); exit(); }
        $allowed = allowed_percents_15();
        $score = (int)($_POST['score'] ?? 0);
        if (!in_array($score, $allowed, true)) { flash_set("Процент: ".implode(', ',$allowed)); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.($_GET['tab']??'mentorship')); exit(); }
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['test_date'] ?? ''); $test_date = $dt ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

        if (isset($_POST['add_record'])) {
            $user_login=$_POST['user_login']; $time_spent=(int)$_POST['time_spent']; $attempt_number=(int)$_POST['attempt_number'];
            $stmt=mysqli_prepare($link,"INSERT INTO $table (user_login, score, time_spent, attempt_number, test_date) VALUES (?,?,?,?,?)");
            if (!$stmt){ flash_set('SQL: '.mysqli_error($link)); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.($_GET['tab']??'mentorship')); exit(); }
            mysqli_stmt_bind_param($stmt,"siiis",$user_login,$score,$time_spent,$attempt_number,$test_date);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set($ok?"Добавлено":"Ошибка");
        }
        if (isset($_POST['update_record'])) {
            $id=(int)$_POST['record_id']; $user_login=$_POST['user_login']; $time_spent=(int)$_POST['time_spent']; $attempt_number=(int)$_POST['attempt_number'];
            $stmt=mysqli_prepare($link,"UPDATE $table SET user_login=?, score=?, time_spent=?, attempt_number=?, test_date=? WHERE id=?");
            if (!$stmt){ flash_set('SQL: '.mysqli_error($link)); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.($_GET['tab']??'mentorship')); exit(); }
            mysqli_stmt_bind_param($stmt,"siiisi",$user_login,$score,$time_spent,$attempt_number,$test_date,$id);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set($ok?"Обновлено":"Ошибка");
        }
        if (isset($_POST['delete_record'])) {
            $id=(int)$_POST['record_id'];
            $stmt=mysqli_prepare($link,"DELETE FROM $table WHERE id=?");
            if (!$stmt){ flash_set('SQL: '.mysqli_error($link)); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.($_GET['tab']??'mentorship')); exit(); }
            mysqli_stmt_bind_param($stmt,"i",$id);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set($ok?"Удалено":"Ошибка");
        }
        header('Location: '.$_SERVER['PHP_SELF'].'?tab='.($_GET['tab']??'mentorship')); exit();
    }

    /* tests */
    if (isset($_POST['scope']) && $_POST['scope']==='tests') {
        $tkey = $_POST['t'] ?? 'dsm';
        if (!isset($testsModels[$tkey])) $tkey='dsm';
        $m=$testsModels[$tkey]; $table=$m['table']; $pk=$m['pk'];

        if (isset($_POST['delete_test'])) {
            $id=(int)$_POST['record_pk'];
            $stmt=mysqli_prepare($linkTest,"DELETE FROM `$table` WHERE `$pk`=?");
            if(!$stmt){ flash_set('SQL: '.mysqli_error($linkTest)); header('Location: '.$_SERVER['PHP_SELF'].'?tab=tests&t='.$tkey); exit(); }
            mysqli_stmt_bind_param($stmt,"i",$id);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set($ok?'Удалено':'Ошибка');
            header('Location: '.$_SERVER['PHP_SELF'].'?tab=tests&t='.$tkey); exit();
        }

        if (isset($_POST['update_test']) || isset($_POST['add_test'])) {
            $cols=$m['columns']; $vals=[]; $types=""; $sets=[];
            foreach($cols as $c){
                $type=$m['types'][$c]??'text';
                if($type==='int'){ $v=isset($_POST[$c])?(int)$_POST[$c]:0; $types.="i"; $vals[]=$v; }
                elseif($type==='datetime'){ $dt=!empty($_POST[$c])?DateTime::createFromFormat('Y-m-d\TH:i',$_POST[$c]):null; $v=$dt?$dt->format('Y-m-d H:i:s'):null; $types.="s"; $vals[]=$v; }
                else { $v=$_POST[$c]??null; $types.="s"; $vals[]=$v; }
                $sets[]="`$c`=?";
            }
            if (isset($_POST['update_test'])){
                $id=(int)$_POST['record_pk']; $types.="i"; $vals[]=$id;
                $sql="UPDATE `$table` SET ".implode(", ",$sets)." WHERE `$pk`=?";
            } else {
                $place=implode(",",array_fill(0,count($cols),"?" ));
                $sql="INSERT INTO `$table` (".implode(",",array_map(fn($c)=>"`$c`",$cols)).") VALUES ($place)";
            }
            $stmt=mysqli_prepare($linkTest,$sql);
            if(!$stmt){ flash_set('SQL: '.mysqli_error($linkTest)); header('Location: '.$_SERVER['PHP_SELF'].'?tab=tests&t='.$tkey); exit(); }
            stmt_bind_params($stmt,$types,$vals);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set($ok?(isset($_POST['add_test'])?'Добавлено':'Обновлено'):'Ошибка');
            header('Location: '.$_SERVER['PHP_SELF'].'?tab=tests&t='.$tkey); exit();
        }
    }

    /* xtvr */
    if (isset($_POST['scope']) && $_POST['scope']==='xtvr') {
        $xkey=$_POST['x']??'sessions'; if(!isset($xtvrModels[$xkey])) $xkey='sessions';
        $m=$xtvrModels[$xkey]; $table=$m['table']; $pk=$m['pk'];

        if (isset($_POST['delete_xtvr'])) {
            $id=(int)$_POST['record_pk'];
            $stmt=mysqli_prepare($linkXTVR,"DELETE FROM `$table` WHERE `$pk`=?");
            if(!$stmt){ flash_set('SQL: '.mysqli_error($linkXTVR)); header('Location: '.$_SERVER['PHP_SELF'].'?tab=xtvr&x='.$xkey); exit(); }
            mysqli_stmt_bind_param($stmt,"i",$id);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set($ok?'Удалено':'Ошибка');
            header('Location: '.$_SERVER['PHP_SELF'].'?tab=xtvr&x='.$xkey); exit();
        }

        if (isset($_POST['update_xtvr']) || isset($_POST['add_xtvr'])) {
            $cols=$m['columns']; $vals=[]; $types=""; $sets=[];
            foreach($cols as $c){
                $type=$m['types'][$c]??'text';
                if($type==='int'){ $v=isset($_POST[$c])?(int)$_POST[$c]:0; $types.="i"; $vals[]=$v; }
                elseif($type==='datetime'){ $dt=!empty($_POST[$c])?DateTime::createFromFormat('Y-m-d\TH:i',$_POST[$c]):null; $v=$dt?$dt->format('Y-m-d H:i:s'):null; $types.="s"; $vals[]=$v; }
                elseif($type==='longtext'){ $v=$_POST[$c]??null; $types.="s"; $vals[]=$v; }
                else { $v=$_POST[$c]??null; $types.="s"; $vals[]=$v; }
                $sets[]="`$c`=?";
            }
            if (isset($_POST['update_xtvr'])) {
                $id=(int)$_POST['record_pk']; $types.="i"; $vals[]=$id;
                $sql="UPDATE `$table` SET ".implode(", ",$sets)." WHERE `$pk`=?";
            } else {
                $place=implode(",",array_fill(0,count($cols),"?" ));
                $sql="INSERT INTO `$table` (".implode(",",array_map(fn($c)=>"`$c`",$cols)).") VALUES ($place)";
            }
            $stmt=mysqli_prepare($linkXTVR,$sql);
            if(!$stmt){ flash_set('SQL: '.mysqli_error($linkXTVR)); header('Location: '.$_SERVER['PHP_SELF'].'?tab=xtvr&x='.$xkey); exit(); }
            stmt_bind_params($stmt,$types,$vals);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set($ok?(isset($_POST['add_xtvr'])?'Добавлено':'Обновлено'):'Ошибка');
            header('Location: '.$_SERVER['PHP_SELF'].'?tab=xtvr&x='.$xkey); exit();
        }
    }

    /* tpatb */
    if (isset($_POST['scope']) && $_POST['scope']==='tpatb') {
        $pkey=$_POST['p']??'users'; if(!isset($tpatbModels[$pkey])) $pkey='users';
        $m=$tpatbModels[$pkey]; $table=$m['table']; $pk=$m['pk'];

        if (isset($_POST['delete_tpa'])) {
            $id=(int)$_POST['record_pk'];
            $stmt=mysqli_prepare($linkTPA,"DELETE FROM `$table` WHERE `$pk`=?");
            if(!$stmt){ flash_set('SQL: '.mysqli_error($linkTPA)); header('Location: '.$_SERVER['PHP_SELF'].'?tab=tpatb&p='.$pkey); exit(); }
            mysqli_stmt_bind_param($stmt,"i",$id);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set($ok?'Удалено':'Ошибка');
            header('Location: '.$_SERVER['PHP_SELF'].'?tab=tpatb&p='.$pkey); exit();
        }

        if (isset($_POST['update_tpa']) || isset($_POST['add_tpa'])) {
            $cols=$m['columns']; $vals=[]; $types=""; $sets=[];
            foreach($cols as $c){
                $type=$m['types'][$c]??'text';
                if($type==='int'){ $v=isset($_POST[$c])?(int)$_POST[$c]:0; $types.="i"; $vals[]=$v; }
                elseif($type==='datetime'){ $dt=!empty($_POST[$c])?DateTime::createFromFormat('Y-m-d\TH:i',$_POST[$c]):null; $v=$dt?$dt->format('Y-m-d H:i:s'):null; $types.="s"; $vals[]=$v; }
                elseif($type==='longtext'){ $v=$_POST[$c]??null; $types.="s"; $vals[]=$v; }
                else { $v=$_POST[$c]??null; $types.="s"; $vals[]=$v; }
                $sets[]="`$c`=?";
            }
            if (isset($_POST['update_tpa'])) {
                $id=(int)$_POST['record_pk']; $types.="i"; $vals[]=$id;
                $sql="UPDATE `$table` SET ".implode(", ",$sets)." WHERE `$pk`=?";
            } else {
                $place=implode(",",array_fill(0,count($cols),"?" ));
                $sql="INSERT INTO `$table` (".implode(",",array_map(fn($c)=>"`$c`",$cols)).") VALUES ($place)";
            }
            $stmt=mysqli_prepare($linkTPA,$sql);
            if(!$stmt){ flash_set('SQL: '.mysqli_error($linkTPA)); header('Location: '.$_SERVER['PHP_SELF'].'?tab=tpatb&p='.$pkey); exit(); }
            stmt_bind_params($stmt,$types,$vals);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set($ok?(isset($_POST['add_tpa'])?'Добавлено':'Обновлено'):'Ошибка');
            header('Location: '.$_SERVER['PHP_SELF'].'?tab=tpatb&p='.$pkey); exit();
        }
    }
}

/* ---------- Flash ---------- */
$message = flash_get();

/* ---------- Common list params ---------- */
$limit = 25;
$page  = max(1,(int)($_GET['page'] ?? 1));
$sort  = $_GET['sort'] ?? '';
$dir   = dir_safe($_GET['dir'] ?? 'desc');
$q     = trim($_GET['q'] ?? '');
$like  = '%'.$q.'%';
$fromDate = trim($_GET['from'] ?? '');
$toDate   = trim($_GET['to'] ?? '');

/* ---------- Data fetching ---------- */
$rows=[]; $table_name=$tabLabels[$active_tab]; $table_columns=[]; $sortKeys=[]; $db=$link; $totalPages=1;

/* staff */
if ($active_tab==='nastav' || $active_tab==='starchenstvo') {
    $table=$active_tab; $db=$link;
    $table_columns=['ID','Логин','Дата добавления','Добавил','Статус'];
    $sortKeys=['id','user_login','date_added','added_by','is_active'];
    if(!in_array($sort,$sortKeys,true)) $sort='id';
    $where=""; $params=[]; $types="";
    if($q!==""){ $where.="WHERE user_login LIKE ?"; $params[]=$like; $types.="s"; }
    $stmt=mysqli_prepare($db,"SELECT COUNT(*) FROM $table $where"); if($params) stmt_bind_params($stmt,$types,$params);
    mysqli_stmt_execute($stmt); mysqli_stmt_bind_result($stmt,$totalRows); mysqli_stmt_fetch($stmt); mysqli_stmt_close($stmt);
    $totalPages=max(1,(int)ceil($totalRows/$limit)); if($page>$totalPages)$page=$totalPages; $offset=($page-1)*$limit;
    $sql="SELECT id,user_login,date_added,added_by,is_active FROM $table $where ORDER BY $sort $dir LIMIT ? OFFSET ?";
    $stmt=mysqli_prepare($db,$sql);
    if($params){ $params2=$params; $types2=$types.'ii'; $params2[]=$limit; $params2[]=$offset; stmt_bind_params($stmt,$types2,$params2);}
    else { mysqli_stmt_bind_param($stmt,"ii",$limit,$offset); }
    mysqli_stmt_execute($stmt); $res=mysqli_stmt_get_result($stmt); while($res && ($r=mysqli_fetch_assoc($res))) $rows[]=$r; mysqli_stmt_close($stmt);
}
/* mentorship/seniority */
elseif ($active_tab==='mentorship' || $active_tab==='seniority') {
    $table=($active_tab==='mentorship')?'mentorship_results':'seniority_results'; $db=$link;
    $table_columns=['ID','Логин','Баллы','Время (сек)','Попытка','Дата теста'];
    $sortKeys=['id','user_login','score','time_spent','attempt_number','test_date'];
    if(!in_array($sort,$sortKeys,true)) $sort='id';
    $where=""; $params=[]; $types="";
    if($q!==""){ $where.="WHERE user_login LIKE ?"; $params[]=$like; $types.="s"; }
    $stmt=mysqli_prepare($db,"SELECT COUNT(*) FROM $table $where"); if($params) stmt_bind_params($stmt,$types,$params);
    mysqli_stmt_execute($stmt); mysqli_stmt_bind_result($stmt,$totalRows); mysqli_stmt_fetch($stmt); mysqli_stmt_close($stmt);
    $totalPages=max(1,(int)ceil($totalRows/$limit)); if($page>$totalPages)$page=$totalPages; $offset=($page-1)*$limit;
    $sql="SELECT id,user_login,score,time_spent,attempt_number,test_date FROM $table $where ORDER BY $sort $dir LIMIT ? OFFSET ?";
    $stmt=mysqli_prepare($db,$sql);
    if($params){ $params2=$params; $types2=$types.'ii'; $params2[]=$limit; $params2[]=$offset; stmt_bind_params($stmt,$types2,$params2);}
    else { mysqli_stmt_bind_param($stmt,"ii",$limit,$offset); }
    mysqli_stmt_execute($stmt); $res=mysqli_stmt_get_result($stmt); while($res && ($r=mysqli_fetch_assoc($res))) $rows[]=$r; mysqli_stmt_close($stmt);
}
/* tests */
elseif ($active_tab==='tests') {
    $m=$testsModels[$tests_key]; $db=$linkTest; $table=$m['table']; $pk=$m['pk'];
    $cols=$m['columns']; $labels=$m['labels']; $date_field=$m['date_field'];
    $table_columns=array_map(fn($c)=>$labels[$c]??$c,$cols);
    $sortKeys=$cols; if(!in_array($sort,$sortKeys,true)) $sort=$date_field ?? $cols[0];
    $where=""; $params=[]; $types="";
    if($m['base_where']!==""){ $where.="WHERE ".$m['base_where']; }
    if($q!==""){
        $cond=[]; foreach($m['searchable'] as $s){ $cond[]="`$s` LIKE ?"; $params[]=$like; $types.="s"; }
        if($cond){ $where.=($where?" AND ":"WHERE ")."(".implode(" OR ",$cond).")"; }
    }
    if($date_field){
        if(!empty($fromDate)){ $where.=($where?" AND ":"WHERE ")."`$date_field` >= ?"; $params[]=$fromDate.' 00:00:00'; $types.="s"; }
        if(!empty($toDate)){ $where.=($where?" AND ":"WHERE ")."`$date_field` <= ?"; $params[]=$toDate.' 23:59:59'; $types.="s"; }
    }
    $stmt=mysqli_prepare($db,"SELECT COUNT(*) FROM `$table` $where"); if($params) stmt_bind_params($stmt,$types,$params);
    mysqli_stmt_execute($stmt); mysqli_stmt_bind_result($stmt,$totalRows); mysqli_stmt_fetch($stmt); mysqli_stmt_close($stmt);
    $totalPages=max(1,(int)ceil($totalRows/$limit)); if($page>$totalPages)$page=$totalPages; $offset=($page-1)*$limit;
    $select="`$pk` AS pk, ".implode(",",array_map(fn($c)=>"`$c`",$cols));
    $sql="SELECT $select FROM `$table` $where ORDER BY `$sort` $dir LIMIT ? OFFSET ?";
    $stmt=mysqli_prepare($db,$sql);
    if($params){ $params2=$params; $types2=$types.'ii'; $params2[]=$limit; $params2[]=$offset; stmt_bind_params($stmt,$types2,$params2);}
    else { mysqli_stmt_bind_param($stmt,"ii",$limit,$offset); }
    mysqli_stmt_execute($stmt); $res=mysqli_stmt_get_result($stmt); while($res && ($r=mysqli_fetch_assoc($res))) $rows[]=$r; mysqli_stmt_close($stmt);
}
/* xtvr */
elseif ($active_tab==='xtvr') {
    $m=$xtvrModels[$xtvr_key]; $db=$linkXTVR; $table=$m['table']; $pk=$m['pk'];
    $cols=$m['columns']; $labels=$m['labels']; $date_field=$m['date_field'];
    $table_columns=array_map(fn($c)=>$labels[$c]??$c,$cols);
    $sortKeys=$cols; if(!in_array($sort,$sortKeys,true)) $sort=$date_field ?? $cols[0];
    $where=""; $params=[]; $types="";
    if($q!==""){ $cond=[]; foreach($m['searchable'] as $s){ $cond[]="`$s` LIKE ?"; $params[]=$like; $types.="s"; } if($cond){ $where.="WHERE (".implode(" OR ",$cond).")"; } }
    if($date_field){
        if(!empty($fromDate)){ $where.=($where?" AND ":"WHERE ")."`$date_field` >= ?"; $params[]=$fromDate.' 00:00:00'; $types.="s"; }
        if(!empty($toDate)){ $where.=($where?" AND ":"WHERE ")."`$date_field` <= ?"; $params[]=$toDate.' 23:59:59'; $types.="s"; }
    }
    $stmt=mysqli_prepare($db,"SELECT COUNT(*) FROM `$table` $where"); if($params) stmt_bind_params($stmt,$types,$params);
    mysqli_stmt_execute($stmt); mysqli_stmt_bind_result($stmt,$totalRows); mysqli_stmt_fetch($stmt); mysqli_stmt_close($stmt);
    $totalPages=max(1,(int)ceil($totalRows/$limit)); if($page>$totalPages)$page=$totalPages; $offset=($page-1)*$limit;
    $select="`$pk` AS pk, ".implode(",",array_map(fn($c)=>"`$c`",$cols));
    $sql="SELECT $select FROM `$table` $where ORDER BY `$sort` $dir LIMIT ? OFFSET ?";
    $stmt=mysqli_prepare($db,$sql);
    if($params){ $params2=$params; $types2=$types.'ii'; $params2[]=$limit; $params2[]=$offset; stmt_bind_params($stmt,$types2,$params2);}
    else { mysqli_stmt_bind_param($stmt,"ii",$limit,$offset); }
    mysqli_stmt_execute($stmt); $res=mysqli_stmt_get_result($stmt); while($res && ($r=mysqli_fetch_assoc($res))) $rows[]=$r; mysqli_stmt_close($stmt);
}
/* tpatb */
else {
    $m=$tpatbModels[$tpa_key]; $db=$linkTPA; $table=$m['table']; $pk=$m['pk'];
    $cols=$m['columns']; $labels=$m['labels']; $date_field=$m['date_field'];
    $table_columns=array_map(fn($c)=>$labels[$c]??$c,$cols);
    $sortKeys=$cols; if(!in_array($sort,$sortKeys,true)) $sort=$date_field ?? $cols[0];
    $where=""; $params=[]; $types="";
    if($q!==""){ $cond=[]; foreach($m['searchable'] as $s){ $cond[]="`$s` LIKE ?"; $params[]=$like; $types.="s"; } if($cond){ $where.="WHERE (".implode(" OR ",$cond).")"; } }
    if($date_field){
        if(!empty($fromDate)){ $where.=($where?" AND ":"WHERE ")."`$date_field` >= ?"; $params[]=$fromDate.' 00:00:00'; $types.="s"; }
        if(!empty($toDate)){ $where.=($where?" AND ":"WHERE ")."`$date_field` <= ?"; $params[]=$toDate.' 23:59:59'; $types.="s"; }
    }
    $stmt=mysqli_prepare($db,"SELECT COUNT(*) FROM `$table` $where"); if($params) stmt_bind_params($stmt,$types,$params);
    mysqli_stmt_execute($stmt); mysqli_stmt_bind_result($stmt,$totalRows); mysqli_stmt_fetch($stmt); mysqli_stmt_close($stmt);
    $totalPages=max(1,(int)ceil($totalRows/$limit)); if($page>$totalPages)$page=$totalPages; $offset=($page-1)*$limit;
    $select="`$pk` AS pk, ".implode(",",array_map(fn($c)=>"`$c`",$cols));
    $sql="SELECT $select FROM `$table` $where ORDER BY `$sort` $dir LIMIT ? OFFSET ?";
    $stmt=mysqli_prepare($db,$sql);
    if($params){ $params2=$params; $types2=$types.'ii'; $params2[]=$limit; $params2[]=$offset; stmt_bind_params($stmt,$types2,$params2);}
    else { mysqli_stmt_bind_param($stmt,"ii",$limit,$offset); }
    mysqli_stmt_execute($stmt); $res=mysqli_stmt_get_result($stmt); while($res && ($r=mysqli_fetch_assoc($res))) $rows[]=$r; mysqli_stmt_close($stmt);
}

/* ---------- HTML ---------- */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Панель администратора</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root{--bg:#0f172a;--panel:#111827;--muted:#6b7280;--text:#f9fafb;--accent:#3b82f6;--danger:#ef4444;--border:#1f2937;--hover:#0b1226}
*{box-sizing:border-box}
body{margin:0;background:linear-gradient(135deg,#0b1020 0%,#0f172a 60%,#0b1226 100%);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial}
.container{max-width:1200px;margin:36px auto;padding:0 20px}
.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.brand{display:flex;align-items:center;gap:12px}
.brand-badge{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--accent) 0%,#60a5fa 100%);display:flex;align-items:center;justify-content:center;font-weight:700;color:white}
.brand h1{font-size:20px;margin:0}
.user{font-size:14px;color:var(--muted)}
.card{background:rgba(17,24,39,0.8);border:1px solid var(--border);border-radius:14px;backdrop-filter:blur(6px)}
.tabs{display:flex;flex-wrap:wrap;gap:8px;padding:12px;border-bottom:1px solid var(--border)}
.tab{padding:10px 14px;border:1px solid var(--border);border-radius:999px;background:#0b1226;color:#cbd5e1;cursor:pointer;user-select:none}
.tab:hover{background:#0e1630}
.tab.active{background:var(--accent);color:white;border-color:transparent}
.panel{padding:16px 16px 8px 16px}
.title{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.title h2{margin:0;font-size:18px}
.tools{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.btn{padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:#0b1226;color:#f9fafb;cursor:pointer}
.btn-primary{background:var(--accent);border-color:transparent;color:white}
.btn-danger{background:transparent;border-color:var(--danger);color:#fecaca}
.message{margin:12px 16px 0 16px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.3);color:#e5edff;padding:10px 12px;border-radius:10px}
.table-wrap{margin-top:12px;border-top:1px solid var(--border);overflow:auto}
table{width:100%;border-collapse:separate;border-spacing:0;min-width:1000px}
thead th{position:sticky;top:0;background:#0b1226;border-bottom:1px solid var(--border);padding:12px;text-align:left;font-size:13px;color:#cbd5e1;z-index:1;white-space:nowrap}
tbody td{border-bottom:1px solid var(--border);padding:12px;font-size:14px;vertical-align:top}
th .th-btn{display:inline-flex;align-items:center;gap:6px;cursor:pointer}
.sort-indicator{font-size:10px;opacity:.7}
.subtabs{display:flex;gap:8px;margin:10px 0;flex-wrap:wrap}
.subtab{padding:8px 12px;border:1px solid var(--border);border-radius:999px;background:#0b1226;color:#cbd5e1;cursor:pointer;text-decoration:none}
.subtab.active{background:var(--accent);color:white;border-color:transparent}
.form-row{display:flex;gap:12px;flex-wrap:wrap}
.form-group{flex:1;min-width:220px}
.form-group label{display:block;margin:6px 0 6px 0;font-size:13px;color:#cbd5e1}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);background:#0b1226;color:#f9fafb}
textarea{min-height:90px}
.edit-form{display:none;background:#0b1226;border-top:1px dashed var(--border)}
.pagination{display:flex;gap:6px;align-items:center;justify-content:flex-end;padding:12px 16px;border-top:1px solid var(--border)}
.page{padding:8px 12px;border:1px solid var(--border);border-radius:10px;background:#0b1226;color:#cbd5e1;text-decoration:none}
.page.active{background:var(--accent);border-color:transparent;color:white}
.search{display:flex;gap:8px;align-items:center;width:100%;flex-wrap:wrap}
.search input,.search select{padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:#0b1226;color:#f9fafb}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="brand">
            <div class="brand-badge">A</div>
            <h1>Панель администратора</h1>
        </div>
        <div class="user">Вы вошли как: <strong><?php echo esc($adminLogin); ?></strong></div>
    </div>

    <div class="card">
        <div class="tabs">
            <div class="tab <?php echo $active_tab=='nastav'?'active':''; ?>" onclick="goTab('nastav')">Наставники</div>
            <div class="tab <?php echo $active_tab=='starchenstvo'?'active':''; ?>" onclick="goTab('starchenstvo')">Старосты</div>
            <div class="tab <?php echo $active_tab=='mentorship'?'active':''; ?>" onclick="goTab('mentorship')">Результаты наставничества</div>
            <div class="tab <?php echo $active_tab=='seniority'?'active':''; ?>" onclick="goTab('seniority')">Результаты староства</div>
            <div class="tab <?php echo $active_tab=='tests'?'active':''; ?>" onclick="goTab('tests')">Тесты</div>
            <div class="tab <?php echo $active_tab=='xtvr'?'active':''; ?>" onclick="goTab('xtvr')">XTVR</div>
            <div class="tab <?php echo $active_tab=='tpatb'?'active':''; ?>" onclick="goTab('tpatb')">TPATB</div>
        </div>

        <?php if ($active_tab==='tests'): ?>
        <div class="panel" style="padding-top:8px">
            <div class="subtabs">
                <?php foreach(['dsm','sess_dpk','sess_sr','sess_tdvs2'] as $k):
                    $cls = $tests_key===$k?'subtab active':'subtab';
                    $u = $_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['tab'=>'tests','t'=>$k,'page'=>1]));
                ?>
                <a class="<?php echo $cls; ?>" href="<?php echo $u; ?>"><?php echo esc($testsModels[$k]['label']); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($active_tab==='xtvr'): ?>
        <div class="panel" style="padding-top:8px">
            <div class="subtabs">
                <?php foreach(['sessions','users','attempt','standards'] as $k):
                    $cls = $xtvr_key===$k?'subtab active':'subtab';
                    $u = $_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['tab'=>'xtvr','x'=>$k,'page'=>1]));
                ?>
                <a class="<?php echo $cls; ?>" href="<?php echo $u; ?>"><?php echo esc($xtvrModels[$k]['label']); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($active_tab==='tpatb'): ?>
        <div class="panel" style="padding-top:8px">
            <div class="subtabs">
                <?php foreach(['users','tests','attempts','results'] as $k):
                    $cls = $tpa_key===$k?'subtab active':'subtab';
                    $u = $_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['tab'=>'tpatb','p'=>$k,'page'=>1]));
                ?>
                <a class="<?php echo $cls; ?>" href="<?php echo $u; ?>"><?php echo esc($tpatbModels[$k]['label']); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="message"><?php echo esc($message); ?></div>
        <?php endif; ?>

        <div class="panel">
            <div class="title">
                <h2><?php echo esc($table_name); ?></h2>
                <div class="tools">
                    <button class="btn btn-primary" onclick="toggleAddForm()">Добавить запись</button>
                    <a class="btn" href="index.php">Выйти</a>
                </div>
            </div>

            <form class="search" method="GET">
                <input type="hidden" name="tab" value="<?php echo esc($active_tab); ?>">
                <?php if ($active_tab==='tests'): ?>
                    <input type="hidden" name="t" value="<?php echo esc($tests_key); ?>">
                <?php elseif ($active_tab==='xtvr'): ?>
                    <input type="hidden" name="x" value="<?php echo esc($xtvr_key); ?>">
                <?php elseif ($active_tab==='tpatb'): ?>
                    <input type="hidden" name="p" value="<?php echo esc($tpa_key); ?>">
                <?php endif; ?>
                <input type="hidden" name="sort" value="<?php echo esc($sort); ?>">
                <input type="hidden" name="dir" value="<?php echo esc($dir); ?>">
                <input type="text" name="q" value="<?php echo esc($q); ?>" placeholder="Поиск...">
                <?php
                $showDates=false;
                if($active_tab==='tests'){ $df=$testsModels[$tests_key]['date_field'] ?? null; $showDates = !empty($df); }
                if($active_tab==='xtvr'){ $df=$xtvrModels[$xtvr_key]['date_field'] ?? null; $showDates = !empty($df); }
                if($active_tab==='tpatb'){ $df=$tpatbModels[$tpa_key]['date_field'] ?? null; $showDates = !empty($df); }
                if($showDates): ?>
                    <label>с <input type="date" name="from" value="<?php echo esc($fromDate); ?>"></label>
                    <label>по <input type="date" name="to" value="<?php echo esc($toDate); ?>"></label>
                <?php endif; ?>
                <button class="btn">Найти</button>
                <a class="btn" href="<?php
                    $params = ['tab'=>$active_tab,'page'=>1,'sort'=>$sort,'dir'=>$dir];
                    if ($active_tab==='tests') $params['t']=$tests_key;
                    if ($active_tab==='xtvr')  $params['x']=$xtvr_key;
                    if ($active_tab==='tpatb') $params['p']=$tpa_key;
                    echo $_SERVER['PHP_SELF'].'?'.http_build_query($params);
                ?>">Сброс</a>
            </form>

            <!-- Add forms -->
            <?php if ($active_tab==='nastav' || $active_tab==='starchenstvo'): ?>
            <div id="addForm" class="edit-form" style="padding:16px;margin-top:12px;border-radius:10px;display:none">
                <h3 style="margin-top:0">Добавить запись</h3>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                    <input type="hidden" name="scope" value="staff">
                    <input type="hidden" name="table" value="<?php echo esc($active_tab); ?>">
                    <div class="form-row">
                        <div class="form-group"><label>Логин</label><input type="text" name="user_login" required></div>
                        <div class="form-group" style="margin-top:28px"><label><input type="checkbox" name="is_active" checked> Активен</label></div>
                    </div>
                    <div class="tools"><button class="btn btn-primary" name="add_record">Сохранить</button><button class="btn" type="button" onclick="toggleAddForm()">Отмена</button></div>
                </form>
            </div>
            <?php elseif ($active_tab==='mentorship' || $active_tab==='seniority'): ?>
            <div id="addForm" class="edit-form" style="padding:16px;margin-top:12px;border-radius:10px;display:none">
                <h3 style="margin-top:0">Добавить запись</h3>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                    <input type="hidden" name="scope" value="results">
                    <input type="hidden" name="table" value="<?php echo $active_tab==='mentorship'?'mentorship_results':'seniority_results'; ?>">
                    <div class="form-row">
                        <div class="form-group"><label>Логин</label><input type="text" name="user_login" required></div>
                        <div class="form-group"><label>Процент</label>
                            <select name="score" required>
                                <?php foreach(allowed_percents_15() as $p): ?><option value="<?php echo $p; ?>"><?php echo $p; ?>%</option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Время (сек)</label><input type="number" name="time_spent" required></div>
                        <div class="form-group"><label>Попытка</label><input type="number" name="attempt_number" required></div>
                    </div>
                    <div class="form-group"><label>Дата</label><input type="datetime-local" name="test_date" required></div>
                    <div class="tools"><button class="btn btn-primary" name="add_record">Сохранить</button><button class="btn" type="button" onclick="toggleAddForm()">Отмена</button></div>
                </form>
            </div>
            <?php elseif ($active_tab==='tests'): ?>
            <?php $m=$testsModels[$tests_key]; ?>
            <div id="addForm" class="edit-form" style="padding:16px;margin-top:12px;border-radius:10px;display:none">
                <h3 style="margin-top:0">Добавить запись — <?php echo esc($m['label']); ?></h3>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                    <input type="hidden" name="scope" value="tests">
                    <input type="hidden" name="t" value="<?php echo esc($tests_key); ?>">
                    <div class="form-row">
                        <?php foreach($m['columns'] as $c):
                            $type=$m['types'][$c]; $lab=$m['labels'][$c]??$c; ?>
                            <div class="form-group">
                                <label><?php echo esc($lab); ?></label>
                                <?php if($type==='int'): ?>
                                    <input type="number" name="<?php echo esc($c); ?>">
                                <?php elseif($type==='datetime'): ?>
                                    <input type="datetime-local" name="<?php echo esc($c); ?>">
                                <?php else: ?>
                                    <input type="text" name="<?php echo esc($c); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="tools"><button class="btn btn-primary" name="add_test">Сохранить</button><button class="btn" type="button" onclick="toggleAddForm()">Отмена</button></div>
                </form>
            </div>
            <?php elseif ($active_tab==='xtvr'): ?>
            <?php $m=$xtvrModels[$xtvr_key]; ?>
            <div id="addForm" class="edit-form" style="padding:16px;margin-top:12px;border-radius:10px;display:none">
                <h3 style="margin-top:0">Добавить запись — <?php echo esc($m['label']); ?></h3>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                    <input type="hidden" name="scope" value="xtvr">
                    <input type="hidden" name="x" value="<?php echo esc($xtvr_key); ?>">
                    <div class="form-row">
                        <?php foreach($m['columns'] as $c):
                            $type=$m['types'][$c]; $lab=$m['labels'][$c]??$c; ?>
                            <div class="form-group">
                                <label><?php echo esc($lab); ?></label>
                                <?php if($type==='int'): ?>
                                    <input type="number" name="<?php echo esc($c); ?>">
                                <?php elseif($type==='datetime'): ?>
                                    <input type="datetime-local" name="<?php echo esc($c); ?>">
                                <?php elseif($type==='longtext'): ?>
                                    <textarea name="<?php echo esc($c); ?>"></textarea>
                                <?php else: ?>
                                    <input type="text" name="<?php echo esc($c); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="tools"><button class="btn btn-primary" name="add_xtvr">Сохранить</button><button class="btn" type="button" onclick="toggleAddForm()">Отмена</button></div>
                </form>
            </div>
            <?php else: /* tpatb */ ?>
            <?php $m=$tpatbModels[$tpa_key]; ?>
            <div id="addForm" class="edit-form" style="padding:16px;margin-top:12px;border-radius:10px;display:none">
                <h3 style="margin-top:0">Добавить запись — <?php echo esc($m['label']); ?></h3>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                    <input type="hidden" name="scope" value="tpatb">
                    <input type="hidden" name="p" value="<?php echo esc($tpa_key); ?>">
                    <div class="form-row">
                        <?php foreach($m['columns'] as $c):
                            $type=$m['types'][$c]; $lab=$m['labels'][$c]??$c; ?>
                            <div class="form-group">
                                <label><?php echo esc($lab); ?></label>
                                <?php if($type==='int'): ?>
                                    <input type="number" name="<?php echo esc($c); ?>">
                                <?php elseif($type==='datetime'): ?>
                                    <input type="datetime-local" name="<?php echo esc($c); ?>">
                                <?php elseif($type==='longtext'): ?>
                                    <textarea name="<?php echo esc($c); ?>"></textarea>
                                <?php else: ?>
                                    <input type="text" name="<?php echo esc($c); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="tools"><button class="btn btn-primary" name="add_tpa">Сохранить</button><button class="btn" type="button" onclick="toggleAddForm()">Отмена</button></div>
                </form>
            </div>
            <?php endif; ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <?php
                            if ($active_tab==='nastav' || $active_tab==='starchenstvo'){
                                $heads=['id'=>'ID','user_login'=>'Логин','date_added'=>'Дата добавления','added_by'=>'Добавил','is_active'=>'Статус'];
                                foreach($heads as $k=>$v): $is=($sort===$k); $arrow=$is?($dir==='asc'?'▲':'▼'):''; ?>
                                    <th><span class="th-btn" onclick="clickSort('<?php echo esc($k); ?>')"><?php echo esc($v); ?> <span class="sort-indicator"><?php echo esc($arrow); ?></span></span></th>
                                <?php endforeach; ?>
                                <th>Действия</th>
                            <?php } elseif ($active_tab==='mentorship' || $active_tab==='seniority') {
                                $heads=['id'=>'ID','user_login'=>'Логин','score'=>'Баллы','time_spent'=>'Время (сек)','attempt_number'=>'Попытка','test_date'=>'Дата теста'];
                                foreach($heads as $k=>$v): $is=($sort===$k); $arrow=$is?($dir==='asc'?'▲':'▼'):''; ?>
                                    <th><span class="th-btn" onclick="clickSort('<?php echo esc($k); ?>')"><?php echo esc($v); ?> <span class="sort-indicator"><?php echo esc($arrow); ?></span></span></th>
                                <?php endforeach; ?>
                                <th>Действия</th>
                            <?php } else {
                                $keys = ($active_tab==='tests') ? $testsModels[$tests_key]['columns']
                                       : (($active_tab==='xtvr') ? $xtvrModels[$xtvr_key]['columns']
                                                                 : $tpatbModels[$tpa_key]['columns']);
                                $labels = ($active_tab==='tests') ? $testsModels[$tests_key]['labels']
                                        : (($active_tab==='xtvr') ? $xtvrModels[$xtvr_key]['labels']
                                                                  : $tpatbModels[$tpa_key]['labels']);
                                foreach($keys as $i=>$k): $lab=$labels[$k]??$k; $is=($sort===$k); $arrow=$is?($dir==='asc'?'▲':'▼'):''; ?>
                                    <th><span class="th-btn" onclick="clickSort('<?php echo esc($k); ?>')"><?php echo esc($lab); ?> <span class="sort-indicator"><?php echo esc($arrow); ?></span></span></th>
                                <?php endforeach; ?>
                                <th>Действия</th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($active_tab==='nastav' || $active_tab==='starchenstvo'): ?>
                            <?php foreach($rows as $r): ?>
                            <tr>
                                <td><?php echo (int)$r['id']; ?></td>
                                <td><?php echo esc($r['user_login']); ?></td>
                                <td><?php echo esc($r['date_added']); ?></td>
                                <td><?php echo esc($r['added_by']); ?></td>
                                <td><?php echo ((int)$r['is_active'])?'Активен':'Неактивен'; ?></td>
                                <td class="row-actions">
                                    <button class="btn" onclick="toggleEditForm('stf-<?php echo (int)$r['id']; ?>')">Изменить</button>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                                        <input type="hidden" name="scope" value="staff">
                                        <input type="hidden" name="table" value="<?php echo esc($active_tab); ?>">
                                        <input type="hidden" name="record_id" value="<?php echo (int)$r['id']; ?>">
                                        <button class="btn btn-danger" name="delete_record" onclick="return confirm('Удалить запись?')">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                            <tr id="edit-form-stf-<?php echo (int)$r['id']; ?>" class="edit-form">
                                <td colspan="6">
                                    <div style="padding:16px">
                                        <h3 style="margin-top:0">Редактирование #<?php echo (int)$r['id']; ?></h3>
                                        <form method="POST">
                                            <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                                            <input type="hidden" name="scope" value="staff">
                                            <input type="hidden" name="table" value="<?php echo esc($active_tab); ?>">
                                            <input type="hidden" name="record_id" value="<?php echo (int)$r['id']; ?>">
                                            <div class="form-row">
                                                <div class="form-group"><label>Логин</label><input type="text" name="user_login" value="<?php echo esc($r['user_login']); ?>"></div>
                                                <div class="form-group" style="margin-top:28px"><label><input type="checkbox" name="is_active" <?php echo ((int)$r['is_active'])?'checked':''; ?>> Активен</label></div>
                                            </div>
                                            <div class="tools"><button class="btn btn-primary" name="update_record">Сохранить</button><button class="btn" type="button" onclick="toggleEditForm('stf-<?php echo (int)$r['id']; ?>')">Отмена</button></div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(!$rows): ?><tr><td colspan="6" style="color:#94a3b8">Нет данных</td></tr><?php endif; ?>

                        <?php elseif ($active_tab==='mentorship' || $active_tab==='seniority'): ?>
                            <?php foreach($rows as $r): ?>
                            <tr>
                                <td><?php echo (int)$r['id']; ?></td>
                                <td><?php echo esc($r['user_login']); ?></td>
                                <td><?php echo nearest_allowed((float)$r['score']); ?>%</td>
                                <td><?php echo (int)$r['time_spent']; ?></td>
                                <td><?php echo (int)$r['attempt_number']; ?></td>
                                <td><?php echo esc($r['test_date']); ?></td>
                                <td class="row-actions">
                                    <button class="btn" onclick="toggleEditForm('res-<?php echo (int)$r['id']; ?>')">Изменить</button>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                                        <input type="hidden" name="scope" value="results">
                                        <input type="hidden" name="table" value="<?php echo $active_tab==='mentorship'?'mentorship_results':'seniority_results'; ?>">
                                        <input type="hidden" name="record_id" value="<?php echo (int)$r['id']; ?>">
                                        <button class="btn btn-danger" name="delete_record" onclick="return confirm('Удалить запись?')">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                            <tr id="edit-form-res-<?php echo (int)$r['id']; ?>" class="edit-form">
                                <td colspan="7">
                                    <div style="padding:16px">
                                        <h3 style="margin-top:0">Редактирование #<?php echo (int)$r['id']; ?></h3>
                                        <form method="POST">
                                            <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                                            <input type="hidden" name="scope" value="results">
                                            <input type="hidden" name="table" value="<?php echo $active_tab==='mentorship'?'mentorship_results':'seniority_results'; ?>">
                                            <input type="hidden" name="record_id" value="<?php echo (int)$r['id']; ?>">
                                            <div class="form-row">
                                                <div class="form-group"><label>Логин</label><input type="text" name="user_login" value="<?php echo esc($r['user_login']); ?>"></div>
                                                <div class="form-group"><label>Процент</label>
                                                    <select name="score">
                                                        <?php foreach(allowed_percents_15() as $p): ?><option value="<?php echo $p; ?>" <?php echo (nearest_allowed((float)$r['score'])==$p)?'selected':''; ?>><?php echo $p; ?>%</option><?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group"><label>Время (сек)</label><input type="number" name="time_spent" value="<?php echo (int)$r['time_spent']; ?>"></div>
                                                <div class="form-group"><label>Попытка</label><input type="number" name="attempt_number" value="<?php echo (int)$r['attempt_number']; ?>"></div>
                                            </div>
                                            <div class="form-group"><label>Дата</label><input type="datetime-local" name="test_date" value="<?php echo date('Y-m-d\TH:i', strtotime($r['test_date'])); ?>"></div>
                                            <div class="tools"><button class="btn btn-primary" name="update_record">Сохранить</button><button class="btn" type="button" onclick="toggleEditForm('res-<?php echo (int)$r['id']; ?>')">Отмена</button></div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(!$rows): ?><tr><td colspan="7" style="color:#94a3b8">Нет данных</td></tr><?php endif; ?>

                        <?php elseif ($active_tab==='tests'): ?>
                            <?php foreach($rows as $r): $rid=(int)$r['pk']; ?>
                            <tr>
                                <?php foreach($testsModels[$tests_key]['columns'] as $c): ?>
                                    <td><?php echo esc($r[$c]); ?></td>
                                <?php endforeach; ?>
                                <td class="row-actions">
                                    <button class="btn" onclick="toggleEditForm('tests-<?php echo $rid; ?>')">Изменить</button>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                                        <input type="hidden" name="scope" value="tests">
                                        <input type="hidden" name="t" value="<?php echo esc($tests_key); ?>">
                                        <input type="hidden" name="record_pk" value="<?php echo $rid; ?>">
                                        <button class="btn btn-danger" name="delete_test" onclick="return confirm('Удалить запись?')">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                            <tr id="edit-form-tests-<?php echo $rid; ?>" class="edit-form">
                                <td colspan="<?php echo count($testsModels[$tests_key]['columns']) + 1; ?>">
                                    <div style="padding:16px">
                                        <h3 style="margin-top:0">Редактирование #<?php echo $rid; ?></h3>
                                        <form method="POST">
                                            <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                                            <input type="hidden" name="scope" value="tests">
                                            <input type="hidden" name="t" value="<?php echo esc($tests_key); ?>">
                                            <input type="hidden" name="record_pk" value="<?php echo $rid; ?>">
                                            <div class="form-row">
                                                <?php foreach($testsModels[$tests_key]['columns'] as $c):
                                                    $type=$testsModels[$tests_key]['types'][$c]; $lab=$testsModels[$tests_key]['labels'][$c]??$c;
                                                    $val=$r[$c];
                                                    if($type==='datetime'){
                                                        $val = $val ? (is_numeric($val) ? date('Y-m-d\TH:i', $val) : date('Y-m-d\TH:i', strtotime($val))) : '';
                                                    } ?>
                                                <div class="form-group">
                                                    <label><?php echo esc($lab); ?></label>
                                                    <?php if($type==='int'): ?>
                                                        <input type="number" name="<?php echo esc($c); ?>" value="<?php echo esc($val); ?>">
                                                    <?php elseif($type==='datetime'): ?>
                                                        <input type="datetime-local" name="<?php echo esc($c); ?>" value="<?php echo esc($val); ?>">
                                                    <?php else: ?>
                                                        <input type="text" name="<?php echo esc($c); ?>" value="<?php echo esc($val); ?>">
                                                    <?php endif; ?>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="tools"><button class="btn btn-primary" name="update_test">Сохранить</button><button class="btn" type="button" onclick="toggleEditForm('tests-<?php echo $rid; ?>')">Отмена</button></div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(!$rows): ?><tr><td colspan="<?php echo count($table_columns)+1; ?>" style="color:#94a3b8">Нет данных</td></tr><?php endif; ?>

                        <?php elseif ($active_tab==='xtvr'): ?>
                            <?php foreach($rows as $r): $rid=(int)$r['pk']; ?>
                            <tr>
                                <?php foreach($xtvrModels[$xtvr_key]['columns'] as $c): ?>
                                    <td><?php echo esc($r[$c]); ?></td>
                                <?php endforeach; ?>
                                <td class="row-actions">
                                    <button class="btn" onclick="toggleEditForm('xtvr-<?php echo $rid; ?>')">Изменить</button>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                                        <input type="hidden" name="scope" value="xtvr">
                                        <input type="hidden" name="x" value="<?php echo esc($xtvr_key); ?>">
                                        <input type="hidden" name="record_pk" value="<?php echo $rid; ?>">
                                        <button class="btn btn-danger" name="delete_xtvr" onclick="return confirm('Удалить запись?')">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                            <tr id="edit-form-xtvr-<?php echo $rid; ?>" class="edit-form">
                                <td colspan="<?php echo count($xtvrModels[$xtvr_key]['columns']) + 1; ?>">
                                    <div style="padding:16px">
                                        <h3 style="margin-top:0">Редактирование #<?php echo $rid; ?></h3>
                                        <form method="POST">
                                            <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                                            <input type="hidden" name="scope" value="xtvr">
                                            <input type="hidden" name="x" value="<?php echo esc($xtvr_key); ?>">
                                            <input type="hidden" name="record_pk" value="<?php echo $rid; ?>">
                                            <div class="form-row">
                                                <?php foreach($xtvrModels[$xtvr_key]['columns'] as $c):
                                                    $type=$xtvrModels[$xtvr_key]['types'][$c]; $lab=$xtvrModels[$xtvr_key]['labels'][$c]??$c;
                                                    $val=$r[$c]; if($type==='datetime'){ $val=$val?date('Y-m-d\TH:i',strtotime($val)):''; } ?>
                                                <div class="form-group">
                                                    <label><?php echo esc($lab); ?></label>
                                                    <?php if($type==='int'): ?>
                                                        <input type="number" name="<?php echo esc($c); ?>" value="<?php echo esc($val); ?>">
                                                    <?php elseif($type==='datetime'): ?>
                                                        <input type="datetime-local" name="<?php echo esc($c); ?>" value="<?php echo esc($val); ?>">
                                                    <?php elseif($type==='longtext'): ?>
                                                        <textarea name="<?php echo esc($c); ?>"><?php echo esc($val); ?></textarea>
                                                    <?php else: ?>
                                                        <input type="text" name="<?php echo esc($c); ?>" value="<?php echo esc($val); ?>">
                                                    <?php endif; ?>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="tools"><button class="btn btn-primary" name="update_xtvr">Сохранить</button><button class="btn" type="button" onclick="toggleEditForm('xtvr-<?php echo $rid; ?>')">Отмена</button></div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(!$rows): ?><tr><td colspan="<?php echo count($table_columns)+1; ?>" style="color:#94a3b8">Нет данных</td></tr><?php endif; ?>

                        <?php else: /* tpatb */ ?>
                            <?php foreach($rows as $r): $rid=(int)$r['pk']; ?>
                            <tr>
                                <?php foreach($tpatbModels[$tpa_key]['columns'] as $c): ?>
                                    <td><?php echo esc($r[$c]); ?></td>
                                <?php endforeach; ?>
                                <td class="row-actions">
                                    <button class="btn" onclick="toggleEditForm('tpa-<?php echo $rid; ?>')">Изменить</button>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                                        <input type="hidden" name="scope" value="tpatb">
                                        <input type="hidden" name="p" value="<?php echo esc($tpa_key); ?>">
                                        <input type="hidden" name="record_pk" value="<?php echo $rid; ?>">
                                        <button class="btn btn-danger" name="delete_tpa" onclick="return confirm('Удалить запись?')">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                            <tr id="edit-form-tpa-<?php echo $rid; ?>" class="edit-form">
                                <td colspan="<?php echo count($tpatbModels[$tpa_key]['columns']) + 1; ?>">
                                    <div style="padding:16px">
                                        <h3 style="margin-top:0">Редактирование #<?php echo $rid; ?></h3>
                                        <form method="POST">
                                            <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                                            <input type="hidden" name="scope" value="tpatb">
                                            <input type="hidden" name="p" value="<?php echo esc($tpa_key); ?>">
                                            <input type="hidden" name="record_pk" value="<?php echo $rid; ?>">
                                            <div class="form-row">
                                                <?php foreach($tpatbModels[$tpa_key]['columns'] as $c):
                                                    $type=$tpatbModels[$tpa_key]['types'][$c]; $lab=$tpatbModels[$tpa_key]['labels'][$c]??$c;
                                                    $val=$r[$c]; if($type==='datetime'){ $val=$val?date('Y-m-d\TH:i',strtotime($val)):''; } ?>
                                                <div class="form-group">
                                                    <label><?php echo esc($lab); ?></label>
                                                    <?php if($type==='int'): ?>
                                                        <input type="number" name="<?php echo esc($c); ?>" value="<?php echo esc($val); ?>">
                                                    <?php elseif($type==='datetime'): ?>
                                                        <input type="datetime-local" name="<?php echo esc($c); ?>" value="<?php echo esc($val); ?>">
                                                    <?php elseif($type==='longtext'): ?>
                                                        <textarea name="<?php echo esc($c); ?>"><?php echo esc($val); ?></textarea>
                                                    <?php else: ?>
                                                        <input type="text" name="<?php echo esc($c); ?>" value="<?php echo esc($val); ?>">
                                                    <?php endif; ?>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="tools"><button class="btn btn-primary" name="update_tpa">Сохранить</button><button class="btn" type="button" onclick="toggleEditForm('tpa-<?php echo $rid; ?>')">Отмена</button></div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(!$rows): ?><tr><td colspan="<?php echo count($table_columns)+1; ?>" style="color:#94a3b8">Нет данных</td></tr><?php endif; ?>

                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php
                $buildLink = function($p) use($active_tab,$q,$sort,$dir,$tests_key,$xtvr_key,$tpa_key,$fromDate,$toDate){
                    $params=['tab'=>$active_tab,'page'=>$p,'sort'=>$sort,'dir'=>$dir];
                    if($q!=='') $params['q']=$q;
                    if($active_tab==='tests'){ $params['t']=$tests_key; if($fromDate!=='') $params['from']=$fromDate; if($toDate!=='') $params['to']=$toDate; }
                    if($active_tab==='xtvr'){ $params['x']=$xtvr_key; if($fromDate!=='') $params['from']=$fromDate; if($toDate!=='') $params['to']=$toDate; }
                    if($active_tab==='tpatb'){ $params['p']=$tpa_key; if($fromDate!=='') $params['from']=$fromDate; if($toDate!=='') $params['to']=$toDate; }
                    return '?'.http_build_query($params);
                };
                if ($page>1) echo '<a class="page" href="'.$buildLink($page-1).'">Назад</a>';
                $start=max(1,$page-2); $end=min($totalPages,$page+2);
                if ($start>1) echo '<a class="page" href="'.$buildLink(1).'">1</a>'.($start>2?' <span class="page">…</span>':'');
                for($p=$start;$p<=$end;$p++){ echo '<a class="page '.($p==$page?'active':'').'" href="'.$buildLink($p).'">'.$p.'</a>'; }
                if ($end<$totalPages) echo ($end<$totalPages-1?' <span class="page">…</span>':'').'<a class="page" href="'.$buildLink($totalPages).'">'.$totalPages.'</a>';
                if ($page<$totalPages) echo '<a class="page" href="'.$buildLink($page+1).'">Вперёд</a>';
                ?>
            </div>
        </div>
    </div>
</div>

<script>
function goTab(tab){
  const u=new URL(location.href);
  u.searchParams.set('tab',tab); u.searchParams.set('page','1');
  if(tab!=='tests'){u.searchParams.delete('t');u.searchParams.delete('from');u.searchParams.delete('to');}
  if(tab!=='xtvr'){u.searchParams.delete('x');u.searchParams.delete('from');u.searchParams.delete('to');}
  if(tab!=='tpatb'){u.searchParams.delete('p');u.searchParams.delete('from');u.searchParams.delete('to');}
  location.href=u.toString();
}
function toggleAddForm(){
  const f=document.getElementById('addForm'); if(!f)return;
  f.style.display=f.style.display==='block'?'none':'block';
}
function toggleEditForm(id){
  const f=document.getElementById('edit-form-'+id); if(!f)return;
  f.style.display=f.style.display==='table-row'?'none':'table-row';
}
function clickSort(key){
  const u=new URL(location.href);
  const cur=u.searchParams.get('sort')||''; let dir=u.searchParams.get('dir')||'desc';
  if(cur===key){ dir=dir==='asc'?'desc':'asc'; } else { dir='asc'; }
  u.searchParams.set('sort',key); u.searchParams.set('dir',dir); u.searchParams.set('page','1');
  location.href=u.toString();
}
</script>
</body>
</html>