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
    foreach($params as $k=>&$v){ $refs[]=&$v; }
    return call_user_func_array([$stmt,'bind_param'],$refs);
}
function allowed_percents_15(){ static $vals=null; if($vals!==null) return $vals; $vals=[]; for($k=0;$k<=15;$k++){ $v=(int)round($k*100/15); if(!in_array($v,$vals,true)) $vals[]=$v; } return $vals; }
function nearest_allowed($x){ $allowed=allowed_percents_15(); $best=$allowed[0]; $dmin=abs($x-$best); foreach($allowed as $v){ $d=abs($x-$v); if($d<$dmin){ $dmin=$d; $best=$v; } } return $best; }
function is_int_type($t){ return in_array(strtolower($t),['int','integer','tinyint','smallint','mediumint','bigint','bit']); }
function is_float_type($t){ return in_array(strtolower($t),['float','double','decimal','dec','numeric']); }
function is_dt_type($t){ return in_array(strtolower($t),['datetime','timestamp','date','time','year']); }
function is_text_type($t){ return in_array(strtolower($t),['varchar','char','text','tinytext','mediumtext','longtext']); }

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

/* ---------- TESTS registry (из прежней версии) ---------- */
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

/* ---------- XTVR registry (как раньше) ---------- */
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

/* ---------- TPATB: динамическая интроспекция ---------- */
function tpa_list_tables($db){
    $rows=[]; 
    $sql="SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='tpatb' ORDER BY TABLE_NAME";
    $res=mysqli_query($db,$sql);
    while($res && ($r=mysqli_fetch_assoc($res))){ $rows[]=$r['TABLE_NAME']; }
    return $rows;
}
function tpa_table_meta($db,$table){
    $meta=['columns'=>[], 'pk'=>null, 'auto_inc'=>false,'searchable'=>[],'dt_field'=>null];
    $q="SELECT COLUMN_NAME,DATA_TYPE,IS_NULLABLE,COLUMN_KEY,EXTRA 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA='tpatb' AND TABLE_NAME=?";
    $st=mysqli_prepare($db,$q);
    if(!$st) return $meta;
    mysqli_stmt_bind_param($st,"s",$table);
    mysqli_stmt_execute($st);
    $rs=mysqli_stmt_get_result($st);
    while($rs && ($c=mysqli_fetch_assoc($rs))){
        $col=$c['COLUMN_NAME']; $type=strtolower($c['DATA_TYPE']);
        $meta['columns'][$col]=['type'=>$type,'nullable'=>$c['IS_NULLABLE']==='YES','key'=>$c['COLUMN_KEY'],'extra'=>$c['EXTRA']];
        if($c['COLUMN_KEY']==='PRI' && !$meta['pk']) $meta['pk']=$col;
        if(!$meta['dt_field'] && is_dt_type($type)) $meta['dt_field']=$col;
        if(is_text_type($type)) $meta['searchable'][]=$col;
        if(stripos($c['EXTRA'],'auto_increment')!==false && $c['COLUMN_KEY']==='PRI') $meta['auto_inc']=true;
    }
    mysqli_stmt_close($st);
    return $meta;
}
$tpa_tables = tpa_list_tables($linkTPA);
$tpa_tbl = $_GET['p'] ?? ( $tpa_tables ? $tpa_tables[0] : '' );
if (!in_array($tpa_tbl,$tpa_tables,true) && $active_tab==='tpatb') { $tpa_tbl = $tpa_tables ? $tpa_tables[0] : ''; }

/* ---------- POST: CRUD ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) { http_response_code(403); exit('CSRF'); }

    /* staff */
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

    /* tpatb — динамический CRUD */
    if (isset($_POST['scope']) && $_POST['scope']==='tpatb') {
        $tbl = $_POST['p'] ?? '';
        if ($tbl==='') { flash_set('Не выбрана таблица'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=tpatb'); exit(); }
        $meta = tpa_table_meta($linkTPA,$tbl);
        if (!$meta['columns']) { flash_set('Таблица не найдена'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=tpatb'); exit(); }

        if (isset($_POST['delete_tpa'])) {
            if (!$meta['pk']) { flash_set('Нет первичного ключа'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=tpatb&p='.$tbl); exit(); }
            $id = $_POST['record_pk'];
            $stmt=mysqli_prepare($linkTPA,"DELETE FROM `$tbl` WHERE `{$meta['pk']}`=?");
            if(!$stmt){ flash_set('SQL: '.mysqli_error($linkTPA)); header('Location: '.$_SERVER['PHP_SELF'].'?tab=tpatb&p='.$tbl); exit(); }
            $btype = is_int_type($meta['columns'][$meta['pk']]['type']) ? "i" : (is_float_type($meta['columns'][$meta['pk']]['type'])?"d":"s");
            mysqli_stmt_bind_param($stmt,$btype,$id);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set($ok?'Удалено':'Ошибка');
            header('Location: '.$_SERVER['PHP_SELF'].'?tab=tpatb&p='.$tbl); exit();
        }

        if (isset($_POST['update_tpa']) || isset($_POST['add_tpa'])) {
            $cols = array_keys($meta['columns']);

            /* подготовка значений */
            $values=[]; $types=""; $sets=[]; $insertCols=[]; $placeholders=[];
            foreach($cols as $c){
                $info=$meta['columns'][$c]; $type=$info['type'];
                $isPK = ($meta['pk']===$c);

                if (isset($_POST['update_tpa']) && $isPK) { continue; } // PK в SET не трогаем
                if (isset($_POST['add_tpa']) && $isPK && $meta['auto_inc']) { continue; } // автоинкремент PK не вставляем

                $v = $_POST[$c] ?? null;

                if (is_dt_type($type)){
                    // поддержим text input в формате datetime-local (Y-m-dTH:i)
                    if ($v !== null && $v !== '') {
                        if (strpos($type,'date')!==false || $type==='timestamp' || $type==='datetime'){
                            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $v);
                            if (!$dt) { // может быть просто дата
                                $dt = DateTime::createFromFormat('Y-m-d', $v);
                            }
                            $v = $dt ? $dt->format('Y-m-d H:i:s') : $v;
                        }
                    } else {
                        $v = null;
                    }
                    $types .= "s";
                } elseif (is_int_type($type)) {
                    if ($v === '' || $v===null) $v = null;
                    else $v = (int)$v;
                    $types .= "i";
                } elseif (is_float_type($type)) {
                    if ($v === '' || $v===null) $v = null;
                    else $v = (float)$v;
                    $types .= "d";
                } else {
                    $types .= "s";
                }

                $values[] = $v;
                if (isset($_POST['update_tpa'])) {
                    $sets[] = "`$c`=?";
                } else {
                    $insertCols[] = "`$c`";
                    $placeholders[] = "?";
                }
            }

            if (isset($_POST['update_tpa'])) {
                if (!$meta['pk']) { flash_set('Нет первичного ключа'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=tpatb&p='.$tbl); exit(); }
                $id = $_POST['record_pk'];
                $types .= is_int_type($meta['columns'][$meta['pk']]['type']) ? "i" : (is_float_type($meta['columns'][$meta['pk']]['type'])?"d":"s");
                $values[] = $id;
                $sql = "UPDATE `$tbl` SET ".implode(", ",$sets)." WHERE `{$meta['pk']}`=?";
            } else {
                $sql = "INSERT INTO `$tbl` (".implode(",",$insertCols).") VALUES (".implode(",",$placeholders).")";
            }

            $stmt=mysqli_prepare($linkTPA,$sql);
            if(!$stmt){ flash_set('SQL: '.mysqli_error($linkTPA)); header('Location: '.$_SERVER['PHP_SELF'].'?tab=tpatb&p='.$tbl); exit(); }
            stmt_bind_params($stmt,$types,$values);
            $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            flash_set($ok?(isset($_POST['add_tpa'])?'Добавлено':'Обновлено'):'Ошибка');
            header('Location: '.$_SERVER['PHP_SELF'].'?tab=tpatb&p='.$tbl); exit();
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
/* tpatb динамика */
else {
    $db=$linkTPA; $table=$tpa_tbl;
    $meta = $table ? tpa_table_meta($db,$table) : ['columns'=>[],'pk'=>null,'searchable'=>[],'dt_field'=>null];
    $cols = array_keys($meta['columns']);
    $labels = array_combine($cols, $cols); // имена столбцов = подписи (можно переименовать вручную при желании)
    $table_columns = $cols;
    $sortKeys = $cols;
    if (!$sort || !in_array($sort,$sortKeys,true)) {
        $sort = $meta['pk'] ?: ($cols[0] ?? '');
    }

    $where=""; $params=[]; $types="";
    if($q!==""){
        $cond=[];
        foreach($meta['searchable'] as $s){ $cond[]="`$s` LIKE ?"; $params[]=$like; $types.="s"; }
        if($cond){ $where.="WHERE (".implode(" OR ",$cond).")"; }
    }
    if($meta['dt_field']){
        if(!empty($fromDate)){ $where.=($where?" AND ":"WHERE ")."`{$meta['dt_field']}` >= ?"; $params[]=$fromDate.' 00:00:00'; $types.="s"; }
        if(!empty($toDate)){ $where.=($where?" AND ":"WHERE ")."`{$meta['dt_field']}` <= ?"; $params[]=$toDate.' 23:59:59'; $types.="s"; }
    }

    $totalRows=0; $rows=[];
    if ($table){
        $stmt=mysqli_prepare($db,"SELECT COUNT(*) FROM `$table` $where");
        if($stmt){
            if($params) stmt_bind_params($stmt,$types,$params);
            mysqli_stmt_execute($stmt); mysqli_stmt_bind_result($stmt,$totalRows); mysqli_stmt_fetch($stmt); mysqli_stmt_close($stmt);
        }
        $totalPages=max(1,(int)ceil($totalRows/$limit)); if($page>$totalPages)$page=$totalPages; $offset=($page-1)*$limit;

        $select = $meta['pk'] ? "`{$meta['pk']}` AS pk, " : "";
        $select .= implode(",",array_map(fn($c)=>"`$c`",$cols));
        $order = $sort ? "ORDER BY `$sort` $dir" : "";
        $sql="SELECT $select FROM `$table` $where $order LIMIT ? OFFSET ?";
        $stmt=mysqli_prepare($db,$sql);
        if($stmt){
            if($params){ $params2=$params; $types2=$types.'ii'; $params2[]=$limit; $params2[]=$offset; stmt_bind_params($stmt,$types2,$params2);}
            else { mysqli_stmt_bind_param($stmt,"ii",$limit,$offset); }
            mysqli_stmt_execute($stmt); $res=mysqli_stmt_get_result($stmt); while($res && ($r=mysqli_fetch_assoc($res))) $rows[]=$r; mysqli_stmt_close($stmt);
        }
    }
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
                <?php foreach($tpa_tables as $t):
                    $cls = $tpa_tbl===$t?'subtab active':'subtab';
                    $u = $_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['tab'=>'tpatb','p'=>$t,'page'=>1]));
                ?>
                <a class="<?php echo $cls; ?>" href="<?php echo $u; ?>"><?php echo esc($t); ?></a>
                <?php endforeach; ?>
                <?php if(!$tpa_tables): ?><span style="color:#94a3b8">В базе tpatb нет таблиц</span><?php endif; ?>
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
                    <input type="hidden" name="p" value="<?php echo esc($tpa_tbl); ?>">
                <?php endif; ?>
                <input type="hidden" name="sort" value="<?php echo esc($sort); ?>">
                <input type="hidden" name="dir" value="<?php echo esc($dir); ?>">
                <input type="text" name="q" value="<?php echo esc($q); ?>" placeholder="Поиск...">
                <?php
                $showDates=false;
                if($active_tab==='tests'){ $df=$testsModels[$tests_key]['date_field'] ?? null; $showDates = !empty($df); }
                if($active_tab==='xtvr'){ $df=$xtvrModels[$xtvr_key]['date_field'] ?? null; $showDates = !empty($df); }
                if($active_tab==='tpatb'){ 
                    $meta_show = $tpa_tbl ? tpa_table_meta($linkTPA,$tpa_tbl) : ['dt_field'=>null];
                    $df=$meta_show['dt_field'] ?? null; 
                    $showDates = !empty($df); 
                }
                if($showDates): ?>
                    <label>с <input type="date" name="from" value="<?php echo esc($fromDate); ?>"></label>
                    <label>по <input type="date" name="to" value="<?php echo esc($toDate); ?>"></label>
                <?php endif; ?>
                <button class="btn">Найти</button>
                <a class="btn" href="<?php
                    $params = ['tab'=>$active_tab,'page'=>1,'sort'=>$sort,'dir'=>$dir];
                    if ($active_tab==='tests') $params['t']=$tests_key;
                    if ($active_tab==='xtvr')  $params['x']=$xtvr_key;
                    if ($active_tab==='tpatb') $params['p']=$tpa_tbl;
                    echo $_SERVER['PHP_SELF'].'?'.http_build_query($params);
                ?>">Сброс</a>
            </form>

                        <!-- Add forms -->
            <?php
            $showAdd = true;

            if ($active_tab==='nastav' || $active_tab==='starchenstvo'): ?>
                <div id="addForm" class="edit-form" style="padding:16px;border-top:1px solid var(--border);margin-top:12px;border-radius:10px">
                    <h3 style="margin:0 0 8px">Добавить запись</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                        <input type="hidden" name="scope" value="staff">
                        <input type="hidden" name="table" value="<?php echo esc($active_tab); ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Логин пользователя</label>
                                <input type="text" name="user_login" required>
                            </div>
                            <div class="form-group">
                                <label>Статус</label>
                                <select name="is_active">
                                    <option value="1">Активен</option>
                                    <option value="0">Неактивен</option>
                                </select>
                            </div>
                        </div>
                        <div class="tools">
                            <button class="btn btn-primary" type="submit" name="add_record">Сохранить</button>
                            <button class="btn" type="button" onclick="toggleAddForm()">Отмена</button>
                        </div>
                    </form>
                </div>

            <?php elseif ($active_tab==='mentorship' || $active_tab==='seniority'):
                $allowed_percent_values = allowed_percents_15(); ?>
                <div id="addForm" class="edit-form" style="padding:16px;border-top:1px solid var(--border);margin-top:12px;border-radius:10px">
                    <h3 style="margin:0 0 8px">Добавить запись</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                        <input type="hidden" name="scope" value="results">
                        <input type="hidden" name="table" value="<?php echo $active_tab==='mentorship'?'mentorship_results':'seniority_results'; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Логин пользователя</label>
                                <input type="text" name="user_login" required>
                            </div>
                            <div class="form-group">
                                <label>Процент правильных ответов</label>
                                <select name="score" required>
                                    <?php foreach ($allowed_percent_values as $p): ?>
                                        <option value="<?php echo $p; ?>"><?php echo $p; ?>%</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Время (сек)</label>
                                <input type="number" name="time_spent" required>
                            </div>
                            <div class="form-group">
                                <label>Номер попытки</label>
                                <input type="number" name="attempt_number" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Дата теста</label>
                            <input type="datetime-local" name="test_date" required>
                        </div>
                        <div class="tools">
                            <button class="btn btn-primary" type="submit" name="add_record">Сохранить</button>
                            <button class="btn" type="button" onclick="toggleAddForm()">Отмена</button>
                        </div>
                    </form>
                </div>

            <?php elseif ($active_tab==='tests'):
                $m = $testsModels[$tests_key];
                $cols = $m['columns'];
                ?>
                <div id="addForm" class="edit-form" style="padding:16px;border-top:1px solid var(--border);margin-top:12px;border-radius:10px">
                    <h3 style="margin:0 0 8px">Добавить запись (<?php echo esc($m['label']); ?>)</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                        <input type="hidden" name="scope" value="tests">
                        <input type="hidden" name="t" value="<?php echo esc($tests_key); ?>">
                        <div class="form-row">
                        <?php foreach ($cols as $c):
                            $type = $m['types'][$c] ?? 'text';
                            $label = $m['labels'][$c] ?? $c;
                            ?>
                            <div class="form-group">
                                <label><?php echo esc($label); ?></label>
                                <?php if ($type==='datetime'): ?>
                                    <input type="datetime-local" name="<?php echo esc($c); ?>">
                                <?php elseif ($type==='int'): ?>
                                    <input type="number" name="<?php echo esc($c); ?>">
                                <?php elseif ($type==='longtext'): ?>
                                    <textarea name="<?php echo esc($c); ?>"></textarea>
                                <?php else: ?>
                                    <input type="text" name="<?php echo esc($c); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <div class="tools">
                            <button class="btn btn-primary" type="submit" name="add_test">Сохранить</button>
                            <button class="btn" type="button" onclick="toggleAddForm()">Отмена</button>
                        </div>
                    </form>
                </div>

            <?php elseif ($active_tab==='xtvr'):
                $m = $xtvrModels[$xtvr_key];
                $cols = $m['columns'];
                ?>
                <div id="addForm" class="edit-form" style="padding:16px;border-top:1px solid var(--border);margin-top:12px;border-radius:10px">
                    <h3 style="margin:0 0 8px">Добавить запись (<?php echo esc($m['label']); ?>)</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                        <input type="hidden" name="scope" value="xtvr">
                        <input type="hidden" name="x" value="<?php echo esc($xtvr_key); ?>">
                        <div class="form-row">
                        <?php foreach ($cols as $c):
                            $type = $m['types'][$c] ?? 'text';
                            $label = $m['labels'][$c] ?? $c; ?>
                            <div class="form-group">
                                <label><?php echo esc($label); ?></label>
                                <?php if ($type==='datetime'): ?>
                                    <input type="datetime-local" name="<?php echo esc($c); ?>">
                                <?php elseif ($type==='int'): ?>
                                    <input type="number" name="<?php echo esc($c); ?>">
                                <?php elseif ($type==='longtext'): ?>
                                    <textarea name="<?php echo esc($c); ?>"></textarea>
                                <?php else: ?>
                                    <input type="text" name="<?php echo esc($c); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <div class="tools">
                            <button class="btn btn-primary" type="submit" name="add_xtvr">Сохранить</button>
                            <button class="btn" type="button" onclick="toggleAddForm()">Отмена</button>
                        </div>
                    </form>
                </div>

            <?php else: // tpatb динамически
                if ($tpa_tbl) {
                    $metaAdd = tpa_table_meta($linkTPA,$tpa_tbl);
                    $cols = array_keys($metaAdd['columns']);
                ?>
                <div id="addForm" class="edit-form" style="padding:16px;border-top:1px solid var(--border);margin-top:12px;border-radius:10px">
                    <h3 style="margin:0 0 8px">Добавить запись — <?php echo esc($tpa_tbl); ?></h3>
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                        <input type="hidden" name="scope" value="tpatb">
                        <input type="hidden" name="p" value="<?php echo esc($tpa_tbl); ?>">
                        <div class="form-row">
                            <?php foreach ($cols as $c):
                                $info = $metaAdd['columns'][$c]; $type = $info['type'];
                                $isPK = ($metaAdd['pk']===$c);
                                $auto = $metaAdd['auto_inc'] && $isPK;
                                if ($auto) continue; ?>
                                <div class="form-group">
                                    <label><?php echo esc($c); ?><?php echo $isPK?' (PK)':''; ?></label>
                                    <?php if (is_dt_type($type)): ?>
                                        <input type="datetime-local" name="<?php echo esc($c); ?>">
                                    <?php elseif (is_int_type($type) || is_float_type($type)): ?>
                                        <input type="number" step="<?php echo is_float_type($type)?'0.01':'1'; ?>" name="<?php echo esc($c); ?>">
                                    <?php else: ?>
                                        <input type="text" name="<?php echo esc($c); ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="tools">
                            <button class="btn btn-primary" type="submit" name="add_tpa">Сохранить</button>
                            <button class="btn" type="button" onclick="toggleAddForm()">Отмена</button>
                        </div>
                    </form>
                </div>
                <?php } ?>
            <?php endif; ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <?php
                            // Итоговый список заголовков
                            $headers = $table_columns;
                            // Для динамических разделов добавим "Действия"
                            foreach ($headers as $i=>$hdr):
                                $key = isset($sortKeys[$i]) ? $sortKeys[$i] : (is_array($sortKeys)?($sortKeys[$i]??''):'');
                                $isActive = $sort===$key;
                                $arrow = $isActive ? ($dir==='asc'?'▲':'▼') : '';
                            ?>
                                <th>
                                    <?php if ($key): ?>
                                        <span class="th-btn" onclick="clickSort('<?php echo esc($key); ?>')">
                                            <?php echo esc($hdr); ?> <span class="sort-indicator"><?php echo esc($arrow); ?></span>
                                        </span>
                                    <?php else: ?>
                                        <?php echo esc($hdr); ?>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="<?php echo count($table_columns)+1; ?>" style="color:#94a3b8">Нет данных</td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr id="row-<?php echo esc($r['pk'] ?? $r['id'] ?? ('k'.md5(json_encode($r)))); ?>">
                                <?php if ($active_tab==='nastav' || $active_tab==='starchenstvo'): ?>
                                    <td><?php echo (int)$r['id']; ?></td>
                                    <td><?php echo esc($r['user_login']); ?></td>
                                    <td><?php echo esc($r['date_added']); ?></td>
                                    <td><?php echo esc($r['added_by']); ?></td>
                                    <td>
                                        <span class="btn" style="padding:4px 8px" onclick="toggleEditForm('<?php echo (int)$r['id']; ?>')">
                                            <?php echo $r['is_active']?'Активен':'Неактивен'; ?>
                                        </span>
                                    </td>

                                <?php elseif ($active_tab==='mentorship' || $active_tab==='seniority'): ?>
                                    <td><?php echo (int)$r['id']; ?></td>
                                    <td><?php echo esc($r['user_login']); ?></td>
                                    <td><?php echo (int)$r['score']; ?>%</td>
                                    <td><?php echo (int)$r['time_spent']; ?></td>
                                    <td><?php echo (int)$r['attempt_number']; ?></td>
                                    <td><?php echo esc($r['test_date']); ?></td>

                                <?php elseif ($active_tab==='tests'):
                                    $m = $testsModels[$tests_key];
                                    foreach ($m['columns'] as $c):
                                        $type = $m['types'][$c] ?? 'text';
                                        $val = $r[$c] ?? '';
                                        echo '<td>'.esc($val).'</td>';
                                    endforeach;

                                elseif ($active_tab==='xtvr'):
                                    $m = $xtvrModels[$xtvr_key];
                                    foreach ($m['columns'] as $c):
                                        $val = $r[$c] ?? '';
                                        echo '<td>'.esc($val).'</td>';
                                    endforeach;

                                else: // tpatb
                                    $metaRow = tpa_table_meta($linkTPA,$tpa_tbl);
                                    foreach (array_keys($metaRow['columns']) as $c):
                                        $val = $r[$c] ?? '';
                                        echo '<td>'.esc($val).'</td>';
                                    endforeach;
                                endif; ?>

                                <td style="white-space:nowrap">
                                    <button class="btn" onclick="toggleEditForm('<?php echo esc($r['pk'] ?? $r['id'] ?? ('k'.md5(json_encode($r)))); ?>')">Изменить</button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Удалить запись?')">
                                        <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                                        <?php if ($active_tab==='nastav' || $active_tab==='starchenstvo'): ?>
                                            <input type="hidden" name="scope" value="staff">
                                            <input type="hidden" name="table" value="<?php echo esc($active_tab); ?>">
                                            <input type="hidden" name="record_id" value="<?php echo (int)$r['id']; ?>">
                                            <button class="btn btn-danger" name="delete_record">Удалить</button>
                                        <?php elseif ($active_tab==='mentorship' || $active_tab==='seniority'): ?>
                                            <input type="hidden" name="scope" value="results">
                                            <input type="hidden" name="table" value="<?php echo $active_tab==='mentorship'?'mentorship_results':'seniority_results'; ?>">
                                            <input type="hidden" name="record_id" value="<?php echo (int)$r['id']; ?>">
                                            <button class="btn btn-danger" name="delete_record">Удалить</button>
                                        <?php elseif ($active_tab==='tests'): ?>
                                            <input type="hidden" name="scope" value="tests">
                                            <input type="hidden" name="t" value="<?php echo esc($tests_key); ?>">
                                            <input type="hidden" name="record_pk" value="<?php echo esc($r['pk']); ?>">
                                            <button class="btn btn-danger" name="delete_test">Удалить</button>
                                        <?php elseif ($active_tab==='xtvr'): ?>
                                            <input type="hidden" name="scope" value="xtvr">
                                            <input type="hidden" name="x" value="<?php echo esc($xtvr_key); ?>">
                                            <input type="hidden" name="record_pk" value="<?php echo esc($r['pk']); ?>">
                                            <button class="btn btn-danger" name="delete_xtvr">Удалить</button>
                                        <?php else: ?>
                                            <input type="hidden" name="scope" value="tpatb">
                                            <input type="hidden" name="p" value="<?php echo esc($tpa_tbl); ?>">
                                            <?php $metaRow = tpa_table_meta($linkTPA,$tpa_tbl); ?>
                                            <?php if (!empty($metaRow['pk'])): ?>
                                                <input type="hidden" name="record_pk" value="<?php echo esc($r[$metaRow['pk']]); ?>">
                                                <button class="btn btn-danger" name="delete_tpa">Удалить</button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit form row -->
                            <tr id="edit-<?php echo esc($r['pk'] ?? $r['id'] ?? ('k'.md5(json_encode($r)))); ?>" class="edit-form">
                                <td colspan="<?php echo count($table_columns)+1; ?>">
                                    <div style="padding:16px">
                                        <h3 style="margin:0 0 8px">Редактирование</h3>
                                        <form method="POST">
                                            <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">

                                            <?php if ($active_tab==='nastav' || $active_tab==='starchenstvo'): ?>
                                                <input type="hidden" name="scope" value="staff">
                                                <input type="hidden" name="table" value="<?php echo esc($active_tab); ?>">
                                                <input type="hidden" name="record_id" value="<?php echo (int)$r['id']; ?>">
                                                <div class="form-row">
                                                    <div class="form-group">
                                                        <label>Логин пользователя</label>
                                                        <input type="text" name="user_login" value="<?php echo esc($r['user_login']); ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Статус</label>
                                                        <select name="is_active">
                                                            <option value="1" <?php echo $r['is_active']?'selected':''; ?>>Активен</option>
                                                            <option value="0" <?php echo !$r['is_active']?'selected':''; ?>>Неактивен</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="tools">
                                                    <button class="btn btn-primary" name="update_record">Сохранить</button>
                                                    <button class="btn" type="button" onclick="toggleEditForm('<?php echo (int)$r['id']; ?>')">Отмена</button>
                                                </div>

                                            <?php elseif ($active_tab==='mentorship' || $active_tab==='seniority'):
                                                $allowed = allowed_percents_15(); ?>
                                                <input type="hidden" name="scope" value="results">
                                                <input type="hidden" name="table" value="<?php echo $active_tab==='mentorship'?'mentorship_results':'seniority_results'; ?>">
                                                <input type="hidden" name="record_id" value="<?php echo (int)$r['id']; ?>">
                                                <div class="form-row">
                                                    <div class="form-group">
                                                        <label>Логин пользователя</label>
                                                        <input type="text" name="user_login" value="<?php echo esc($r['user_login']); ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Процент</label>
                                                        <select name="score">
                                                            <?php foreach ($allowed as $p): ?>
                                                                <option value="<?php echo $p; ?>" <?php echo ((int)$r['score']===$p)?'selected':''; ?>><?php echo $p; ?>%</option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="form-row">
                                                    <div class="form-group">
                                                        <label>Время (сек)</label>
                                                        <input type="number" name="time_spent" value="<?php echo (int)$r['time_spent']; ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Номер попытки</label>
                                                        <input type="number" name="attempt_number" value="<?php echo (int)$r['attempt_number']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>Дата теста</label>
                                                    <input type="datetime-local" name="test_date" value="<?php echo esc(date('Y-m-d\TH:i', strtotime($r['test_date']))); ?>">
                                                </div>
                                                <div class="tools">
                                                    <button class="btn btn-primary" name="update_record">Сохранить</button>
                                                    <button class="btn" type="button" onclick="toggleEditForm('<?php echo (int)$r['id']; ?>')">Отмена</button>
                                                </div>

                                            <?php elseif ($active_tab==='tests'):
                                                $m = $testsModels[$tests_key]; ?>
                                                <input type="hidden" name="scope" value="tests">
                                                <input type="hidden" name="t" value="<?php echo esc($tests_key); ?>">
                                                <input type="hidden" name="record_pk" value="<?php echo esc($r['pk']); ?>">
                                                <div class="form-row">
                                                    <?php foreach ($m['columns'] as $c):
                                                        $type = $m['types'][$c] ?? 'text';
                                                        $label = $m['labels'][$c] ?? $c;
                                                        $val = $r[$c] ?? ''; ?>
                                                        <div class="form-group">
                                                            <label><?php echo esc($label); ?></label>
                                                            <?php if ($type==='datetime'): ?>
                                                                <input type="datetime-local" name="<?php echo esc($c); ?>" value="<?php echo $val?esc(date('Y-m-d\TH:i', strtotime($val))):''; ?>">
                                                            <?php elseif ($type==='int'): ?>
                                                                <input type="number" name="<?php echo esc($c); ?>" value="<?php echo esc($val); ?>">
                                                            <?php elseif ($type==='longtext'): ?>
                                                                <textarea name="<?php echo esc($c); ?>"><?php echo esc($val); ?></textarea>
                                                            <?php else: ?>
                                                                <input type="text" name="<?php echo esc($c); ?>" value="<?php echo esc($val); ?>">
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="tools">
                                                    <button class="btn btn-primary" name="update_test">Сохранить</button>
                                                    <button class="btn" type="button" onclick="toggleEditForm('<?php echo esc($r['pk']); ?>')">Отмена</button>
                                                </div>

                                            <?php elseif ($active_tab==='xtvr'):
                                                $m = $xtvrModels[$xtvr_key]; ?>
                                                <input type="hidden" name="scope" value="xtvr">
                                                <input type="hidden" name="x" value="<?php echo esc($xtvr_key); ?>">
                                                <input type="hidden" name="record_pk" value="<?php echo esc($r['pk']); ?>">
                                                <div class="form-row">
                                                    <?php foreach ($m['columns'] as $c):
                                                        $type = $m['types'][$c] ?? 'text';
                                                        $label = $m['labels'][$c] ?? $c;
                                                        $val = $r[$c] ?? ''; ?>
                                                        <div class="form-group">
                                                            <label><?php echo esc($label); ?></label>
                                                            <?php if ($type==='datetime'): ?>
                                                                <input type="datetime-local" name="<?php echo esc($c); ?>" value="<?php echo $val?esc(date('Y-m-d\TH:i', strtotime($val))):''; ?>">
                                                            <?php elseif ($type==='int'): ?>
                                                                <input type="number" name="<?php echo esc($c); ?>" value="<?php echo esc($val); ?>">
                                                            <?php elseif ($type==='longtext'): ?>
                                                                <textarea name="<?php echo esc($c); ?>"><?php echo esc($val); ?></textarea>
                                                            <?php else: ?>
                                                                <input type="text" name="<?php echo esc($c); ?>" value="<?php echo esc($val); ?>">
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="tools">
                                                    <button class="btn btn-primary" name="update_xtvr">Сохранить</button>
                                                    <button class="btn" type="button" onclick="toggleEditForm('<?php echo esc($r['pk']); ?>')">Отмена</button>
                                                </div>

                                            <?php else:
                                                $metaE = tpa_table_meta($linkTPA,$tpa_tbl); ?>
                                                <input type="hidden" name="scope" value="tpatb">
                                                <input type="hidden" name="p" value="<?php echo esc($tpa_tbl); ?>">
                                                <?php if ($metaE['pk']): ?>
                                                    <input type="hidden" name="record_pk" value="<?php echo esc($r[$metaE['pk']]); ?>">
                                                <?php endif; ?>
                                                <div class="form-row">
                                                    <?php foreach (array_keys($metaE['columns']) as $c):
                                                        $info = $metaE['columns'][$c]; $type=$info['type']; $val = $r[$c] ?? ''; ?>
                                                        <div class="form-group">
                                                            <label><?php echo esc($c); ?><?php echo ($metaE['pk']===$c)?' (PK)':''; ?></label>
                                                            <?php if ($metaE['pk']===$c): ?>
                                                                <input type="text" value="<?php echo esc($val); ?>" disabled>
                                                            <?php else: ?>
                                                                <?php if (is_dt_type($type)): ?>
                                                                    <input type="datetime-local" name="<?php echo esc($c); ?>" value="<?php echo $val?esc(date('Y-m-d\TH:i', strtotime($val))):''; ?>">
                                                                <?php elseif (is_int_type($type) || is_float_type($type)): ?>
                                                                    <input type="number" step="<?php echo is_float_type($type)?'0.01':'1'; ?>" name="<?php echo esc($c); ?>" value="<?php echo esc($val); ?>">
                                                                <?php else: ?>
                                                                    <input type="text" name="<?php echo esc($c); ?>" value="<?php echo esc($val); ?>">
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="tools">
                                                    <button class="btn btn-primary" name="update_tpa">Сохранить</button>
                                                    <button class="btn" type="button" onclick="toggleEditForm('<?php echo esc($r['pk'] ?? $r['id']); ?>')">Отмена</button>
                                                </div>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php
                $build = function($p) use($active_tab,$tests_key,$xtvr_key,$tpa_tbl,$sort,$dir,$q,$fromDate,$toDate){
                    $params = ['tab'=>$active_tab,'page'=>$p,'sort'=>$sort,'dir'=>$dir];
                    if ($q!=='') $params['q']=$q;
                    if ($fromDate!=='') $params['from']=$fromDate;
                    if ($toDate!=='') $params['to']=$toDate;
                    if ($active_tab==='tests') $params['t']=$tests_key;
                    if ($active_tab==='xtvr')  $params['x']=$xtvr_key;
                    if ($active_tab==='tpatb') $params['p']=$tpa_tbl;
                    return $_SERVER['PHP_SELF'].'?'.http_build_query($params);
                };
                $window=2;
                if ($page>1) echo '<a class="page" href="'.$build($page-1).'">Назад</a>';
                $start=max(1,$page-$window); $end=min($totalPages,$page+$window);
                if ($start>1) echo '<a class="page" href="'.$build(1).'">1</a>'.($start>2?'<span class="page">…</span>':'');
                for($i=$start;$i<=$end;$i++) echo '<a class="page '.($i==$page?'active':'').'" href="'.$build($i).'">'.$i.'</a>';
                if ($end<$totalPages) echo ($end<$totalPages-1?'<span class="page">…</span>':'').'<a class="page" href="'.$build($totalPages).'">'.$totalPages.'</a>';
                if ($page<$totalPages) echo '<a class="page" href="'.$build($page+1).'">Вперёд</a>';
                ?>
            </div>
        </div>
    </div>
</div>

<script>
function goTab(tab){
    const u=new URL(location.href);
    u.searchParams.set('tab',tab);
    // сброс подтабов
    if(tab!=='tests') u.searchParams.delete('t');
    if(tab!=='xtvr')  u.searchParams.delete('x');
    if(tab!=='tpatb') u.searchParams.delete('p');
    u.searchParams.set('page','1');
    location.href=u.toString();
}
function clickSort(key){
    const u=new URL(location.href);
    const cur=u.searchParams.get('sort')||'';
    let dir=u.searchParams.get('dir')||'desc';
    if(cur===key){ dir = (dir==='asc'?'desc':'asc'); } else { dir='asc'; }
    u.searchParams.set('sort',key);
    u.searchParams.set('dir',dir);
    u.searchParams.set('page','1');
    location.href=u.toString();
}
function toggleAddForm(){
    const f=document.getElementById('addForm');
    if(!f) return;
    f.style.display = (f.style.display==='block') ? 'none' : 'block';
}
function toggleEditForm(id){
    const row=document.getElementById('edit-'+id);
    if(!row) return;
    row.style.display = (row.style.display==='table-row') ? 'none' : 'table-row';
}
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.edit-form').forEach(el=>{ if(!el.id.startsWith('addForm')) el.style.display='none'; });
});
</script>
</body>
</html>