<?php
session_start();
if (!isset($_COOKIE['id_tpa'])) { header("Location: index.php"); exit(); }

/* Подключения: основная (classnost) и тестовая (test) */
$link = mysqli_connect("localhost", "root", "", "classnost");
if (!$link) { die("Ошибка подключения: " . mysqli_connect_error()); }
mysqli_set_charset($link, "utf8mb4");

$linkTest = mysqli_connect("localhost", "root", "", "test");
if (!$linkTest) { die("Ошибка подключения к test: " . mysqli_connect_error()); }
mysqli_set_charset($linkTest, "utf8mb4");

$use_cache = function_exists('apcu_fetch') || function_exists('apc_fetch');

/* Аутентификация администратора */
$adminLogin = $_COOKIE['id_tpa'];
$stmt = mysqli_prepare($link, "SELECT 1 FROM administrators WHERE user_login=?");
mysqli_stmt_bind_param($stmt, "s", $adminLogin);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
if (mysqli_stmt_num_rows($stmt) === 0) { header("Location: category.php"); exit(); }
mysqli_stmt_close($stmt);

/* CSRF */
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

/* Навигация */
$allowedTabs = ['nastav','starchenstvo','mentorship','seniority','tests'];
$tabLabels = [
    'nastav' => 'Наставники',
    'starchenstvo' => 'Старосты',
    'mentorship' => 'Результаты наставничества',
    'seniority' => 'Результаты староства',
    'tests' => 'Тесты'
];
$active_tab = $_GET['tab'] ?? 'mentorship';
if (!in_array($active_tab, $allowedTabs, true)) $active_tab = 'mentorship';

/* Модели тестов (DSM теперь sess1) */
$testsModels = [
    'dsm'        => ['label'=>'DSM',    'table'=>'sess1',      'pk'=>'index7', 'where'=>"level2 = 5"],
    'sess_dpk'   => ['label'=>'DPK',    'table'=>'sess_dpk',   'pk'=>'id',     'where'=>"level2 = 'test_dpk'"],
    'sess_sr'    => ['label'=>'SR',     'table'=>'sess_sr',    'pk'=>'id',     'where'=>"level2 = 'test'"],
    'sess_tdvs2' => ['label'=>'TDVS2',  'table'=>'sess_tdvs2', 'pk'=>'id',     'where'=>"level2 = 5"]
];
$tests_key = $_GET['t'] ?? 'dsm';
if (!isset($testsModels[$tests_key])) $tests_key = 'dsm';

/* Утилиты */
function sanitize_dir($v){ return strtolower($v)==='asc'?'asc':'desc'; }
function esc($v){ return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function cache_get($k){ if (function_exists('apcu_fetch')) return apcu_fetch($k); if (function_exists('apc_fetch')) return apc_fetch($k); return false; }
function cache_set($k,$v,$ttl=60){ if (function_exists('apcu_store')) return apcu_store($k,$v,$ttl); if (function_exists('apc_store')) return apc_store($k,$v,$ttl); return false; }
function cache_inc($k){ if (function_exists('apcu_inc')) { $s=apcu_inc($k,1,$ok); if(!$ok){ apcu_store($k,1); return 1; } return $s; } if (function_exists('apc_inc')) { $s=@apc_inc($k,1,$ok); if(!$ok){ apc_store($k,1); return 1; } return $s; } return false; }
function cache_ns_get($table){ $k="ns_".$table; $ns=cache_get($k); if($ns===false){ cache_set($k,1,0); $ns=1; } return $ns; }
function cache_ns_bump($table){ $k="ns_".$table; cache_inc($k); }
function logAdminAction($link, $admin, $action, $details) {
    $stmt = mysqli_prepare($link, "INSERT INTO admin_logs (admin_login, action, details, ip_address, user_agent, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    mysqli_stmt_bind_param($stmt, "sssss", $admin, $action, $details, $ip, $ua);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
function allowed_percents_15(){
    static $vals=null; if($vals!==null) return $vals;
    $vals=[]; for($k=0;$k<=15;$k++){ $v=(int)round($k*100/15); if(!in_array($v,$vals,true)) $vals[]=$v; }
    return $vals;
}
function nearest_allowed($x){
    $allowed=allowed_percents_15(); $best=$allowed[0]; $dmin=abs($x-$best);
    foreach($allowed as $v){ $d=abs($x-$v); if($d<$dmin){ $dmin=$d; $best=$v; } }
    return $best;
}

/* POST-обработчик */
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) { http_response_code(403); exit('CSRF'); }
    $current_admin = $adminLogin;
    $current_date = date('Y-m-d H:i:s');

    /* Массовое удаление для наставников/старост */
    if (isset($_POST['bulk_action']) && !empty($_POST['selected_ids'])) {
        $selected_ids = array_map('intval', $_POST['selected_ids']);
        $ids_list = implode(',', $selected_ids);
        if ($_POST['bulk_action'] === 'delete' && ($active_tab === 'nastav' || $active_tab === 'starchenstvo')) {
            $table = $active_tab;
            $stmt = mysqli_prepare($link, "DELETE FROM $table WHERE id IN ($ids_list)");
            if (!$stmt) { $_SESSION['flash'] = "SQL ошибка: ".mysqli_error($link); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$active_tab); exit(); }
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Удалено записей: " . count($selected_ids) : "Ошибка: ".mysqli_error($link);
            if ($ok) { cache_ns_bump($table); logAdminAction($link, $current_admin, "BULK_DELETE", "Таблица: $table, ID: $ids_list"); }
            mysqli_stmt_close($stmt);
        }
    }

    /* CRUD наставники/старосты */
    if ($active_tab === 'nastav' || $active_tab === 'starchenstvo') {
        $table = $active_tab;

        if (isset($_POST['add_record'])) {
            $user_login = trim($_POST['user_login']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $stmt = mysqli_prepare($link, "INSERT INTO $table (user_login, date_added, added_by, is_active) VALUES (?, ?, ?, ?)");
            if (!$stmt) { $_SESSION['flash']="SQL ошибка: ".mysqli_error($link); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$active_tab); exit(); }
            mysqli_stmt_bind_param($stmt, "sssi", $user_login, $current_date, $current_admin, $is_active);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Запись добавлена успешно!" : "Ошибка: ".mysqli_error($link);
            if ($ok) { cache_ns_bump($table); logAdminAction($link, $current_admin, "ADD", "Таблица: $table, Логин: $user_login"); }
            mysqli_stmt_close($stmt);
        }
        if (isset($_POST['delete_record'])) {
            $id = (int)$_POST['record_id'];
            $stmt = mysqli_prepare($link, "DELETE FROM $table WHERE id=?");
            if (!$stmt) { $_SESSION['flash']="SQL ошибка: ".mysqli_error($link); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$active_tab); exit(); }
            mysqli_stmt_bind_param($stmt, "i", $id);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Запись удалена успешно!" : "Ошибка: ".mysqli_error($link);
            if ($ok) { cache_ns_bump($table); logAdminAction($link, $current_admin, "DELETE", "Таблица: $table, ID: $id"); }
            mysqli_stmt_close($stmt);
        }
        if (isset($_POST['update_record'])) {
            $id = (int)$_POST['record_id'];
            $user_login = trim($_POST['user_login']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $stmt = mysqli_prepare($link, "UPDATE $table SET user_login=?, is_active=? WHERE id=?");
            if (!$stmt) { $_SESSION['flash']="SQL ошибка: ".mysqli_error($link); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$active_tab); exit(); }
            mysqli_stmt_bind_param($stmt, "sii", $user_login, $is_active, $id);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Запись обновлена успешно!" : "Ошибка: ".mysqli_error($link);
            if ($ok) { cache_ns_bump($table); logAdminAction($link, $current_admin, "UPDATE", "Таблица: $table, ID: $id"); }
            mysqli_stmt_close($stmt);
        }
        if (isset($_POST['toggle_status'])) {
            $id = (int)$_POST['record_id'];
            $new_status = (int)$_POST['new_status'];
            $stmt = mysqli_prepare($link, "UPDATE $table SET is_active=? WHERE id=?");
            if (!$stmt) { $_SESSION['flash']="SQL ошибка: ".mysqli_error($link); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$active_tab); exit(); }
            mysqli_stmt_bind_param($stmt, "ii", $new_status, $id);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Статус изменен успешно!" : "Ошибка: ".mysqli_error($link);
            if ($ok) { cache_ns_bump($table); logAdminAction($link, $current_admin, "TOGGLE_STATUS", "Таблица: $table, ID: $id, Статус: $new_status"); }
            mysqli_stmt_close($stmt);
        }
    }

    /* CRUD результаты (проценты) */
    if ($active_tab === 'mentorship' || $active_tab === 'seniority') {
        $table = ($active_tab === 'mentorship') ? 'mentorship_results' : 'seniority_results';
        $allowed = allowed_percents_15();

        if (isset($_POST['add_record'])) {
            $user_login = trim($_POST['user_login']);
            $score_in = $_POST['score'] ?? '';
            if ($score_in === '' || !is_numeric($score_in)) { $_SESSION['flash']="Некорректный процент"; header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$active_tab); exit(); }
            $score = (int)$score_in;
            if (!in_array($score, $allowed, true)) { $_SESSION['flash']="Процент должен быть одним из: ".implode(', ',$allowed); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$active_tab); exit(); }
            $time_spent = (int)$_POST['time_spent'];
            $attempt_number = (int)$_POST['attempt_number'];
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['test_date']);
            $test_date = $dt ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
            $stmt = mysqli_prepare($link, "INSERT INTO $table (user_login, score, time_spent, attempt_number, test_date) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) { $_SESSION['flash']="SQL ошибка: ".mysqli_error($link); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$active_tab); exit(); }
            mysqli_stmt_bind_param($stmt, "siiis", $user_login, $score, $time_spent, $attempt_number, $test_date);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Запись добавлена успешно!" : "Ошибка: ".mysqli_error($link);
            if ($ok) { cache_ns_bump($table); logAdminAction($link, $current_admin, "ADD", "Таблица: $table, Логин: $user_login"); }
            mysqli_stmt_close($stmt);
        }

        if (isset($_POST['delete_record'])) {
            $id = (int)$_POST['record_id'];
            $stmt = mysqli_prepare($link, "DELETE FROM $table WHERE id=?");
            if (!$stmt) { $_SESSION['flash']="SQL ошибка: ".mysqli_error($link); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$active_tab); exit(); }
            mysqli_stmt_bind_param($stmt, "i", $id);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Запись удалена успешно!" : "Ошибка: ".mysqli_error($link);
            if ($ok) { cache_ns_bump($table); logAdminAction($link, $current_admin, "DELETE", "Таблица: $table, ID: $id"); }
            mysqli_stmt_close($stmt);
        }

        if (isset($_POST['update_record'])) {
            $id = (int)$_POST['record_id'];
            $user_login = trim($_POST['user_login']);
            $score_in = $_POST['score'] ?? '';
            if ($score_in === '' || !is_numeric($score_in)) { $_SESSION['flash']="Некорректный процент"; header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$active_tab); exit(); }
            $score = (int)$score_in;
            $allowed = allowed_percents_15();
            if (!in_array($score, $allowed, true)) { $_SESSION['flash']="Процент должен быть одним из: ".implode(', ',$allowed); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$active_tab); exit(); }
            $time_spent = (int)$_POST['time_spent'];
            $attempt_number = (int)$_POST['attempt_number'];
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['test_date']);
            $test_date = $dt ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
            $stmt = mysqli_prepare($link, "UPDATE $table SET user_login=?, score=?, time_spent=?, attempt_number=?, test_date=? WHERE id=?");
            if (!$stmt) { $_SESSION['flash']="SQL ошибка: ".mysqli_error($link); header('Location: '.$_SERVER['PHP_SELF'].'?tab='.$active_tab); exit(); }
            mysqli_stmt_bind_param($stmt, "siiisi", $user_login, $score, $time_spent, $attempt_number, $test_date, $id);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Запись обновлена успешно!" : "Ошибка: ".mysqli_error($link);
            if ($ok) { cache_ns_bump($table); logAdminAction($link, $current_admin, "UPDATE", "Таблица: $table, ID: $id"); }
            mysqli_stmt_close($stmt);
        }
    }

    /* Редактирование тестов */
    if ($active_tab === 'tests') {
        $tkey = $_POST['t'] ?? $tests_key;
        if (!isset($testsModels[$tkey])) $tkey = 'dsm';
        $m = $testsModels[$tkey];
        $table = $m['table'];
        $pk = $m['pk'];

        if (isset($_POST['update_test'])) {
            $id = (int)$_POST['record_pk'];
            $user_login = trim($_POST['user_login']);
            $error = (int)$_POST['error'];
            $error2 = (int)$_POST['error2'];
            $times = (int)$_POST['times'];
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['time_test']);
            $time_test = $dt ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

            $sql = "UPDATE `{$table}` SET `user_login`=?, `ERROR`=?, `Error2`=?, `times`=?, `TIME_TEST`=? WHERE `{$pk}`=?";
            $stmt = mysqli_prepare($linkTest, $sql);
            if (!$stmt) { $_SESSION['flash']="SQL ошибка: ".mysqli_error($linkTest); header('Location: '.$_SERVER['PHP_SELF'].'?tab=tests&t='.$tkey); exit(); }
            mysqli_stmt_bind_param($stmt, "siiisi", $user_login, $error, $error2, $times, $time_test, $id);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Данные обновлены" : "Ошибка: ".mysqli_error($linkTest);
            if ($ok) { cache_ns_bump('tests_'.$tkey); logAdminAction($link, $adminLogin, "UPDATE_TEST", "Таблица test.$table, PK: $id"); }
            mysqli_stmt_close($stmt);
        }

        if (isset($_POST['delete_test'])) {
            $id = (int)$_POST['record_pk'];
            $sql = "DELETE FROM `{$table}` WHERE `{$pk}`=?";
            $stmt = mysqli_prepare($linkTest, $sql);
            if (!$stmt) { $_SESSION['flash']="SQL ошибка: ".mysqli_error($linkTest); header('Location: '.$_SERVER['PHP_SELF'].'?tab=tests&t='.$tkey); exit(); }
            mysqli_stmt_bind_param($stmt, "i", $id);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Запись удалена" : "Ошибка: ".mysqli_error($linkTest);
            if ($ok) { cache_ns_bump('tests_'.$tkey); logAdminAction($link, $adminLogin, "DELETE_TEST", "Таблица test.$table, PK: $id"); }
            mysqli_stmt_close($stmt);
        }
    }

    $_SESSION['flash'] = $message;
    $redir = [
        'tab'=>$active_tab,
        'page'=>1,
        'q'=>$_GET['q'] ?? '',
        'sort'=>$_GET['sort'] ?? ($active_tab==='tests'?'time_test':'id'),
        'dir'=>$_GET['dir'] ?? 'desc',
        'status'=>$_GET['status'] ?? 'all',
        't'=>$tests_key,
        'from'=>$_GET['from'] ?? '',
        'to'=>$_GET['to'] ?? ''
    ];
    header('Location: '.$_SERVER['PHP_SELF'].'?'.http_build_query($redir));
    exit();
}

/* flash */
if (isset($_SESSION['flash'])) { $message = $_SESSION['flash']; unset($_SESSION['flash']); }

/* Параметры списка */
$limit = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$q = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? 'all';
$like = '%'.$q.'%';
$fromDate = trim($_GET['from'] ?? '');
$toDate = trim($_GET['to'] ?? '');

/* Определяем таблицу/колонки активной вкладки */
if ($active_tab === 'nastav' || $active_tab === 'starchenstvo') {
    $table = $active_tab;
    $columns = ['id','user_login','date_added','added_by','is_active'];
    $sortAllow = ['id','user_login','date_added','added_by','is_active'];
    $db = $link;
} elseif ($active_tab === 'mentorship' || $active_tab === 'seniority') {
    $table = $active_tab === 'mentorship' ? 'mentorship_results' : 'seniority_results';
    $columns = ['id','user_login','score','time_spent','attempt_number','test_date'];
    $sortAllow = ['id','user_login','score','time_spent','attempt_number','test_date'];
    $db = $link;
} else {
    $m = $testsModels[$tests_key];
    $table = $m['table'];
    $pk = $m['pk'];
    $db = $linkTest;
    $columns = ['pk','user_login','error','error2','times','time_test'];
    $sortAllow = ['user_login','error','error2','times','time_test'];
}

/* Сортировка */
$sort = $_GET['sort'] ?? ($active_tab==='tests' ? 'time_test' : 'id');
if (!in_array($sort, $sortAllow, true)) $sort = $active_tab==='tests' ? 'time_test' : 'id';
$dir = sanitize_dir($_GET['dir'] ?? 'desc');

/* WHERE */
$where_conditions = [];
$params = [];
$types = '';

if ($active_tab === 'tests') {
    $where_conditions[] = $testsModels[$tests_key]['where']; // base_where
    if ($q !== '') { $where_conditions[] = "`user_login` LIKE ?"; $params[] = $like; $types .= 's'; }
    if ($fromDate !== '') { $where_conditions[] = "`TIME_TEST` >= ?"; $params[] = $fromDate.' 00:00:00'; $types .= 's'; }
    if ($toDate !== '') { $where_conditions[] = "`TIME_TEST` <= ?"; $params[] = $toDate.' 23:59:59'; $types .= 's'; }
} else {
    if ($q !== '') { $where_conditions[] = "user_login LIKE ?"; $params[] = $like; $types .= 's'; }
    if (($active_tab === 'nastav' || $active_tab === 'starchenstvo') && $status_filter !== 'all') {
        $status_value = $status_filter === 'active' ? 1 : 0;
        $where_conditions[] = "is_active = ?";
        $params[] = $status_value;
        $types .= 'i';
    }
}
$where = $where_conditions ? "WHERE " . implode(' AND ', $where_conditions) : "";

/* Кэш-неймспейс */
$nsTableForCache = ($active_tab==='tests' ? 'tests_'.$tests_key : $table);
$ns = $use_cache ? cache_ns_get($nsTableForCache) : 0;

/* COUNT */
$countSql = ($active_tab === 'tests')
    ? "SELECT COUNT(*) FROM `{$table}` {$where}"
    : "SELECT COUNT(*) FROM {$table} {$where}";
$cache_key_count = "{$nsTableForCache}_{$ns}_count_" . md5($where . serialize($params));
if ($use_cache && ($cached_count = cache_get($cache_key_count)) !== false) {
    $totalRows = $cached_count;
} else {
    $stmt = mysqli_prepare($db, $countSql);
    if (!$stmt) { $message = "SQL ошибка: ".mysqli_error($db); $totalRows = 0; }
    else{
        if ($params) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $totalRows);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        if ($use_cache) { cache_set($cache_key_count, $totalRows, 60); }
    }
}

/* Пагинация */
$totalPages = max(1, (int)ceil($totalRows / $limit));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $limit;

/* SELECT */
$cache_key_data = "{$nsTableForCache}_{$ns}_data_" . md5(serialize([$where, $params, $sort, $dir, $limit, $offset]));
if ($use_cache && ($cached_data = cache_get($cache_key_data)) !== false) {
    $rows = $cached_data;
} else {
    if ($active_tab === 'tests') {
        $orderExpr = in_array($sort, $sortAllow, true) ? $sort : 'time_test';
        $listSql = "SELECT `{$pk}` AS pk, `user_login`, `ERROR` AS error, `Error2` AS error2, `times`, `TIME_TEST` AS time_test
                    FROM `{$table}` {$where}
                    ORDER BY {$orderExpr} {$dir} LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($db, $listSql);
    } else {
        $selectCols = implode(',', $columns);
        $listSql = "SELECT $selectCols FROM $table $where ORDER BY $sort $dir LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($db, $listSql);
    }
    if (!$stmt) {
        $rows = [];
        $message = "SQL ошибка: ".mysqli_error($db);
    } else {
        $all_params = $params; $all_types = $types;
        if ($params) { $all_types .= 'ii'; $all_params[] = $limit; $all_params[] = $offset; mysqli_stmt_bind_param($stmt, $all_types, ...$all_params); }
        else { mysqli_stmt_bind_param($stmt, "ii", $limit, $offset); }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
        }
        mysqli_stmt_close($stmt);
        if ($use_cache) { cache_set($cache_key_data, $rows, 30); }
    }
}

/* Заголовки, колонки */
if ($active_tab === 'tests') {
    $table_name = 'Тесты — ' . $testsModels[$tests_key]['label'];
    $table_columns = ['Табельный №','Ошибки','Оценка','Время (сек)','Дата теста'];
    $sortKeys = ['user_login','error','error2','times','time_test'];
} else {
    $table_name = $tabLabels[$active_tab];
    $table_columns = $active_tab==='nastav'||$active_tab==='starchenstvo'
        ? ['ID','Логин','Дата добавления','Добавил','Статус']
        : ['ID','Логин','Баллы','Время (сек)','Попытка','Дата теста'];
    $sortKeys = $active_tab==='nastav'||$active_tab==='starchenstvo'
        ? ['id','user_login','date_added','added_by','is_active']
        : ['id','user_login','score','time_spent','attempt_number','test_date'];
}
$allowed_percent_values = allowed_percents_15();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Панель администратора</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root{--bg:#0f172a;--panel:#111827;--muted:#6b7280;--text:#f9fafb;--accent:#3b82f6;--accent-2:#22c55e;--danger:#ef4444;--border:#1f2937;--hover:#0b1226}
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
.tab{padding:10px 14px;border:1px solid var(--border);border-radius:999px;background:#0b1226;color:var(--text);cursor:pointer;user-select:none}
.tab:hover{background:#0e1630}
.tab.active{background:var(--accent);color:white;border-color:transparent}
.panel{padding:16px 16px 8px 16px}
.title{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.title h2{margin:0;font-size:18px}
.search{display:flex;gap:8px;align-items:center;width:100%}
.search input, .search select{flex:1;min-width:200px;padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:#0b1226;color:var(--text)}
.search button,.btn{padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:#0b1226;color:var(--text);cursor:pointer}
.btn-primary{background:var(--accent);border-color:transparent;color:white}
.btn-primary:hover{filter:brightness(1.1)}
.btn-danger{background:transparent;border-color:var(--danger);color:#fecaca}
.btn-danger:hover{background:rgba(239,68,68,.1)}
.btn-ghost{background:transparent}
.btn-loading{opacity:0.6;cursor:not-allowed}
.message{margin:12px 16px 0 16px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.3);color:#e5edff;padding:10px 12px;border-radius:10px}
.table-wrap{margin-top:12px;border-top:1px solid var(--border)}
table{width:100%;border-collapse:separate;border-spacing:0}
thead th{position:sticky;top:0;background:#0b1226;border-bottom:1px solid var(--border);padding:12px;text-align:left;font-size:13px;color:#cbd5e1;z-index:1}
tbody td{border-bottom:1px solid var(--border);padding:12px;font-size:14px}
tbody tr:hover{background:#0c1328}
th .th-btn{display:inline-flex;align-items:center;gap:6px;cursor:pointer}
.sort-indicator{font-size:10px;opacity:.7}
.row-actions{display:flex;gap:6px}
.edit-form{display:none;background:#0b1226;border-top:1px dashed var(--border)}
.form-row{display:flex;gap:12px}
.form-row > div{flex:1}
.form-group label{display:block;margin:6px 0 6px 0;font-size:13px;color:#cbd5e1}
.form-group input, .form-group select{width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);background:#0b1226;color:var(--text)}
.pagination{display:flex;gap:6px;align-items:center;justify-content:flex-end;padding:12px 16px;border-top:1px solid var(--border)}
.page{padding:8px 12px;border:1px solid var(--border);border-radius:10px;background:#0b1226;color:#cbd5e1;text-decoration:none}
.page.active{background:var(--accent);border-color:transparent;color:white}
.page:hover{background:#0e1630}
.subtabs{display:flex;gap:8px;margin:10px 0;flex-wrap:wrap}
.subtab{padding:8px 12px;border:1px solid var(--border);border-radius:999px;background:#0b1226;color:#cbd5e1;cursor:pointer;text-decoration:none}
.subtab.active{background:var(--accent);color:white;border-color:transparent}
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
        </div>

        <?php if ($active_tab==='tests'): ?>
        <div class="panel" style="padding-top:8px">
            <div class="subtabs">
                <?php
                $testsOrder = ['dsm','sess_dpk','sess_sr','sess_tdvs2'];
                foreach($testsOrder as $k){
                    $cls = $tests_key===$k?'subtab active':'subtab';
                    $label = $testsModels[$k]['label'];
                    $u = $_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['tab'=>'tests','t'=>$k,'page'=>1]));
                    echo '<a class="'.$cls.'" href="'.$u.'">'.$label.'</a>';
                }
                ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($message !== ''): ?>
            <div class="message"><?php echo esc($message); ?></div>
        <?php endif; ?>

        <div class="panel">
            <div class="title">
                <h2><?php echo esc($table_name); ?></h2>
                <div class="tools">
                    <?php if ($active_tab!=='tests'): ?>
                    <button class="btn btn-primary" onclick="toggleAddForm()">Добавить запись</button>
                    <?php endif; ?>
                    <a class="btn btn-ghost" href="index.php">Выйти</a>
                </div>
            </div>

            <?php if ($active_tab==='tests'): ?>
            <form class="search" method="GET">
                <input type="hidden" name="tab" value="tests">
                <input type="hidden" name="t" value="<?php echo esc($tests_key); ?>">
                <input type="hidden" name="sort" value="<?php echo esc($sort); ?>">
                <input type="hidden" name="dir" value="<?php echo esc($dir); ?>">
                <input type="text" name="q" value="<?php echo esc($q); ?>" placeholder="Поиск по таб. номеру...">
                <input type="date" name="from" value="<?php echo esc($fromDate); ?>">
                <input type="date" name="to" value="<?php echo esc($toDate); ?>">
                <button class="btn">Найти</button>
                <a class="btn" href="<?php echo $_SERVER['PHP_SELF'].'?'.http_build_query(['tab'=>'tests','t'=>$tests_key]); ?>">Сброс</a>
            </form>
            <?php else: ?>
            <form class="search" method="GET">
                <input type="hidden" name="tab" value="<?php echo esc($active_tab); ?>">
                <input type="hidden" name="sort" value="<?php echo esc($sort); ?>">
                <input type="hidden" name="dir" value="<?php echo esc($dir); ?>">
                <input type="hidden" name="status" value="<?php echo esc($status_filter); ?>">
                <input type="text" name="q" value="<?php echo esc($q); ?>" placeholder="Поиск по логину...">
                <button class="btn">Найти</button>
            </form>
            <?php endif; ?>

            <?php if ($active_tab!=='tests'): ?>
            <div id="addForm" class="edit-form" style="padding:16px;border-top:1px solid var(--border);margin-top:12px;border-radius:10px">
                <h3 style="margin-top:0">Добавить запись</h3>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                    <?php if ($active_tab == 'nastav' || $active_tab == 'starchenstvo'): ?>
                        <div class="form-row">
                            <div class="form-group"><label>Логин пользователя</label><input type="text" name="user_login" required></div>
                            <div class="form-group" style="margin-top:28px">
                                <label><input type="checkbox" name="is_active" checked> Активен</label>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="form-row">
                            <div class="form-group"><label>Логин пользователя</label><input type="text" name="user_login" required></div>
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
                            <div class="form-group"><label>Затраченное время (сек)</label><input type="number" name="time_spent" required></div>
                            <div class="form-group"><label>Номер попытки</label><input type="number" name="attempt_number" required></div>
                        </div>
                        <div class="form-group"><label>Дата тестирования</label><input type="datetime-local" name="test_date" required></div>
                    <?php endif; ?>
                    <div class="tools"><button class="btn btn-primary" type="submit" name="add_record">Сохранить</button><button class="btn" type="button" onclick="toggleAddForm()">Отмена</button></div>
                </form>
            </div>
            <?php endif; ?>

            <div class="table-wrap">
                <table id="dataTable">
                    <thead>
                        <tr>
                            <?php foreach ($table_columns as $i=>$col): $key = $sortKeys[$i]; $isActive = $sort===$key; $arrow = $isActive ? ($dir==='asc'?'▲':'▼') : ''; ?>
                            <th><span class="th-btn" onclick="clickSort('<?php echo esc($key); ?>')"><?php echo esc($col); ?> <span class="sort-indicator"><?php echo esc($arrow); ?></span></span></th>
                            <?php endforeach; ?>
                            <th><?php echo $active_tab==='tests' ? 'Действия' : 'Действия'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($active_tab==='tests'): ?>
                            <?php foreach ($rows as $row): $rid = (int)$row['pk']; ?>
                            <tr id="row-tests-<?php echo $rid; ?>">
                                <td><?php echo esc($row['user_login']); ?></td>
                                <td><?php echo (int)$row['error']; ?></td>
                                <td><?php echo (int)$row['error2']; ?></td>
                                <td><?php echo (int)$row['times']; ?></td>
                                <td data-type="date" data-order="<?php echo (int)strtotime($row['time_test']); ?>"><?php echo esc($row['time_test']); ?></td>
                                <td class="row-actions">
                                    <button class="btn" onclick="toggleEditForm('tests-<?php echo $rid; ?>', event)">Изменить</button>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                                        <input type="hidden" name="t" value="<?php echo esc($tests_key); ?>">
                                        <input type="hidden" name="record_pk" value="<?php echo $rid; ?>">
                                        <button class="btn btn-danger" type="submit" name="delete_test" onclick="return confirm('Удалить запись?')">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                            <tr id="edit-form-tests-<?php echo $rid; ?>" class="edit-form">
                                <td colspan="<?php echo count($table_columns) + 1; ?>">
                                    <div style="padding:16px">
                                        <h3 style="margin-top:0">Редактирование #<?php echo $rid; ?></h3>
                                        <form method="POST">
                                            <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                                            <input type="hidden" name="t" value="<?php echo esc($tests_key); ?>">
                                            <input type="hidden" name="record_pk" value="<?php echo $rid; ?>">
                                            <div class="form-row">
                                                <div class="form-group"><label>Табельный номер</label><input type="text" name="user_login" value="<?php echo esc($row['user_login']); ?>" required></div>
                                                <div class="form-group"><label>Ошибки (ERROR)</label><input type="number" name="error" value="<?php echo (int)$row['error']; ?>" required></div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group"><label>Оценка (Error2)</label><input type="number" name="error2" value="<?php echo (int)$row['error2']; ?>" required></div>
                                                <div class="form-group"><label>Время (сек)</label><input type="number" name="times" value="<?php echo (int)$row['times']; ?>" required></div>
                                            </div>
                                            <div class="form-group"><label>Дата/время теста</label><input type="datetime-local" name="time_test" value="<?php echo date('Y-m-d\TH:i', strtotime($row['time_test'])); ?>" required></div>
                                            <div class="tools"><button class="btn btn-primary" type="submit" name="update_test">Сохранить</button><button class="btn" type="button" onclick="toggleEditForm('tests-<?php echo $rid; ?>')">Отмена</button></div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (!$rows): ?>
                            <tr><td colspan="<?php echo count($table_columns)+1; ?>" style="color:#94a3b8">Нет данных</td></tr>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                            <tr id="row-<?php echo (int)$row['id']; ?>">
                                <?php if ($active_tab == 'nastav' || $active_tab == 'starchenstvo'): ?>
                                    <td><?php echo (int)$row['id']; ?></td>
                                    <td><?php echo esc($row['user_login']); ?></td>
                                    <td data-type="date" data-order="<?php echo (int)strtotime($row['date_added']); ?>"><?php echo esc($row['date_added']); ?></td>
                                    <td><?php echo esc($row['added_by']); ?></td>
                                    <td>
                                        <span class="btn" onclick="toggleStatus(<?php echo (int)$row['id']; ?>, <?php echo ((int)$row['is_active'])?0:1; ?>)"><?php echo ((int)$row['is_active'])?'Активен':'Неактивен'; ?></span>
                                    </td>
                                <?php else: ?>
                                    <?php $score_disp = nearest_allowed((float)$row['score']); ?>
                                    <td><?php echo (int)$row['id']; ?></td>
                                    <td><?php echo esc($row['user_login']); ?></td>
                                    <td><?php echo $score_disp; ?>%</td>
                                    <td><?php echo (int)$row['time_spent']; ?></td>
                                    <td><?php echo (int)$row['attempt_number']; ?></td>
                                    <td data-type="date" data-order="<?php echo (int)strtotime($row['test_date']); ?>"><?php echo esc($row['test_date']); ?></td>
                                <?php endif; ?>
                                <td class="row-actions">
                                    <button class="btn" onclick="toggleEditForm(<?php echo (int)$row['id']; ?>)">Изменить</button>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                                        <input type="hidden" name="record_id" value="<?php echo (int)$row['id']; ?>">
                                        <button class="btn btn-danger" type="submit" name="delete_record" onclick="return confirm('Удалить запись?')">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                            <tr id="edit-form-<?php echo (int)$row['id']; ?>" class="edit-form">
                                <td colspan="<?php echo count($table_columns) + 1; ?>">
                                    <div style="padding:16px">
                                        <h3 style="margin-top:0">Редактирование #<?php echo (int)$row['id']; ?></h3>
                                        <form method="POST">
                                            <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                                            <input type="hidden" name="record_id" value="<?php echo (int)$row['id']; ?>">
                                            <?php if ($active_tab == 'nastav' || $active_tab == 'starchenstvo'): ?>
                                                <div class="form-row">
                                                    <div class="form-group"><label>Логин пользователя</label><input type="text" name="user_login" value="<?php echo esc($row['user_login']); ?>" required></div>
                                                    <div class="form-group" style="margin-top:28px">
                                                        <label><input type="checkbox" name="is_active" <?php echo ((int)$row['is_active'])?'checked':''; ?>> Активен</label>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <?php $sel_score = nearest_allowed((float)$row['score']); ?>
                                                <div class="form-row">
                                                    <div class="form-group"><label>Логин пользователя</label><input type="text" name="user_login" value="<?php echo esc($row['user_login']); ?>" required></div>
                                                    <div class="form-group"><label>Процент правильных ответов</label>
                                                        <select name="score" required>
                                                            <?php foreach ($allowed_percent_values as $p): ?>
                                                                <option value="<?php echo $p; ?>" <?php echo ($p==$sel_score)?'selected':''; ?>><?php echo $p; ?>%</option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="form-row">
                                                    <div class="form-group"><label>Затраченное время (сек)</label><input type="number" name="time_spent" value="<?php echo (int)$row['time_spent']; ?>" required></div>
                                                    <div class="form-group"><label>Номер попытки</label><input type="number" name="attempt_number" value="<?php echo (int)$row['attempt_number']; ?>" required></div>
                                                </div>
                                                <div class="form-group"><label>Дата тестирования</label><input type="datetime-local" name="test_date" value="<?php echo date('Y-m-d\TH:i', strtotime($row['test_date'])); ?>" required></div>
                                            <?php endif; ?>
                                            <div class="tools"><button class="btn btn-primary" type="submit" name="update_record">Сохранить</button><button class="btn" type="button" onclick="toggleEditForm(<?php echo (int)$row['id']; ?>)">Отмена</button></div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (!$rows): ?>
                            <tr><td colspan="<?php echo count($table_columns)+1; ?>" style="color:#94a3b8">Нет данных</td></tr>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php
                $buildLink = function($p) use($active_tab,$q,$sort,$dir,$status_filter,$tests_key,$fromDate,$toDate){
                    $params = ['tab'=>$active_tab,'page'=>$p,'q'=>$q,'sort'=>$sort,'dir'=>$dir,'status'=>$status_filter];
                    if ($active_tab==='tests'){ $params['t']=$tests_key; if($fromDate!=='') $params['from']=$fromDate; if($toDate!=='') $params['to']=$toDate; }
                    return '?'.http_build_query($params);
                };
                $window=2;
                if ($page>1) echo '<a class="page" href="'.$buildLink($page-1).'">Назад</a>';
                $start=max(1,$page-$window);
                $end=min($totalPages,$page+$window);
                if ($start>1) echo '<a class="page" href="'.$buildLink(1).'">1</a>'.($start>2?' <span class="page">…</span>':'');
                for($p=$start;$p<=$end;$p++){
                    echo '<a class="page '.($p===$page?'active':'').'" href="'.$buildLink($p).'">'.$p.'</a>';
                }
                if ($end<$totalPages) echo ($end<$totalPages-1?' <span class="page">…</span>':'').'<a class="page" href="'.$buildLink($totalPages).'">'.$totalPages.'</a>';
                if ($page<$totalPages) echo '<a class="page" href="'.$buildLink($page+1).'">Вперёд</a>';
                ?>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN="<?php echo esc($csrf); ?>";
function goTab(tab){const u=new URL(location.href);u.searchParams.set('tab',tab);u.searchParams.set('page','1');if(tab!=='tests'){u.searchParams.delete('t');u.searchParams.delete('from');u.searchParams.delete('to');}location.href=u.toString();}
function toggleAddForm(){const f=document.getElementById('addForm');if(!f)return;f.style.display=f.style.display==='block'?'none':'block'}
function toggleEditForm(id,event){if(event){event.stopPropagation();}const f=document.getElementById('edit-form-'+id);if(!f)return;f.style.display=f.style.display==='table-row'?'none':'table-row'}
function clickSort(key){const u=new URL(location.href);const cur=u.searchParams.get('sort')||'id';let dir=u.searchParams.get('dir')||'desc';if(cur===key){dir=dir==='asc'?'desc':'asc'}else{dir='asc'}u.searchParams.set('sort',key);u.searchParams.set('dir',dir);u.searchParams.set('page','1');location.href=u.toString()}
function toggleStatus(id,newStatus){if(!confirm('Изменить статус?'))return;const f=document.createElement('form');f.method='POST';f.style.display='none';const h=(n,v)=>{const i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;f.appendChild(i)};h('csrf',CSRF_TOKEN);h('record_id',id);h('new_status',newStatus);h('toggle_status','1');document.body.appendChild(f);f.submit();}
</script>
</body>
</html>