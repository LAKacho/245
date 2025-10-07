<?php
session_start();
if (!isset($_COOKIE['id_tpa'])) { header("Location: index.php"); exit(); }
$link = mysqli_connect("localhost", "root", "", "classnost");
if (!$link) { die("Ошибка подключения: " . mysqli_connect_error()); }
mysqli_set_charset($link, "utf8mb4");

$adminLogin = $_COOKIE['id_tpa'];
$stmt = mysqli_prepare($link, "SELECT 1 FROM administrators WHERE user_login=?");
mysqli_stmt_bind_param($stmt, "s", $adminLogin);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
if (mysqli_stmt_num_rows($stmt) === 0) { header("Location: category.php"); exit(); }
mysqli_stmt_close($stmt);

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

$allowedTabs = ['nastav','starchenstvo','mentorship','seniority'];
$tabLabels = [
    'nastav' => 'Наставники',
    'starchenstvo' => 'Старосты',
    'mentorship' => 'Результаты наставничества',
    'seniority' => 'Результаты староства'
];
$active_tab = $_GET['tab'] ?? 'mentorship';
if (!in_array($active_tab, $allowedTabs, true)) $active_tab = 'mentorship';

function sanitize_dir($v){ return strtolower($v)==='asc'?'asc':'desc'; }
function esc($v){ return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) { http_response_code(403); exit('CSRF'); }
    $current_admin = $adminLogin;
    $current_date = date('Y-m-d H:i:s');

    if ($active_tab === 'nastav' || $active_tab === 'starchenstvo') {
        $table = $active_tab;

        if (isset($_POST['add_record'])) {
            $user_login = trim($_POST['user_login']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $stmt = mysqli_prepare($link, "INSERT INTO $table (user_login, date_added, added_by, is_active) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssi", $user_login, $current_date, $current_admin, $is_active);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Запись добавлена успешно!" : "Ошибка: ".mysqli_error($link);
            mysqli_stmt_close($stmt);
        }

        if (isset($_POST['delete_record'])) {
            $id = (int)$_POST['record_id'];
            $stmt = mysqli_prepare($link, "DELETE FROM $table WHERE id=?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Запись удалена успешно!" : "Ошибка: ".mysqli_error($link);
            mysqli_stmt_close($stmt);
        }

        if (isset($_POST['update_record'])) {
            $id = (int)$_POST['record_id'];
            $user_login = trim($_POST['user_login']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $stmt = mysqli_prepare($link, "UPDATE $table SET user_login=?, is_active=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "sii", $user_login, $is_active, $id);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Запись обновлена успешно!" : "Ошибка: ".mysqli_error($link);
            mysqli_stmt_close($stmt);
        }

        if (isset($_POST['toggle_status'])) {
            $id = (int)$_POST['record_id'];
            $new_status = (int)$_POST['new_status'];
            $stmt = mysqli_prepare($link, "UPDATE $table SET is_active=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "ii", $new_status, $id);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Статус изменен успешно!" : "Ошибка: ".mysqli_error($link);
            mysqli_stmt_close($stmt);
        }
    }

    if ($active_tab === 'mentorship' || $active_tab === 'seniority') {
        $table = ($active_tab === 'mentorship') ? 'mentorship_results' : 'seniority_results';

        if (isset($_POST['add_record'])) {
            $user_login = trim($_POST['user_login']);
            $score = is_numeric($_POST['score']) ? (float)$_POST['score'] : 0;
            $time_spent = (int)$_POST['time_spent'];
            $attempt_number = (int)$_POST['attempt_number'];
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['test_date']);
            $test_date = $dt ? $dt->format('Y-m-d H:i:s') : $current_date;
            $stmt = mysqli_prepare($link, "INSERT INTO $table (user_login, score, time_spent, attempt_number, test_date) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sdiss", $user_login, $score, $time_spent, $attempt_number, $test_date);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Запись добавлена успешно!" : "Ошибка: ".mysqli_error($link);
            mysqli_stmt_close($stmt);
        }

        if (isset($_POST['delete_record'])) {
            $id = (int)$_POST['record_id'];
            $stmt = mysqli_prepare($link, "DELETE FROM $table WHERE id=?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Запись удалена успешно!" : "Ошибка: ".mysqli_error($link);
            mysqli_stmt_close($stmt);
        }

        if (isset($_POST['update_record'])) {
            $id = (int)$_POST['record_id'];
            $user_login = trim($_POST['user_login']);
            $score = is_numeric($_POST['score']) ? (float)$_POST['score'] : 0;
            $time_spent = (int)$_POST['time_spent'];
            $attempt_number = (int)$_POST['attempt_number'];
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['test_date']);
            $test_date = $dt ? $dt->format('Y-m-d H:i:s') : $current_date;
            $stmt = mysqli_prepare($link, "UPDATE $table SET user_login=?, score=?, time_spent=?, attempt_number=?, test_date=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "sdissi", $user_login, $score, $time_spent, $attempt_number, $test_date, $id);
            $ok = mysqli_stmt_execute($stmt);
            $message = $ok ? "Запись обновлена успешно!" : "Ошибка: ".mysqli_error($link);
            mysqli_stmt_close($stmt);
        }
    }
}

$limit = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$q = trim($_GET['q'] ?? '');
$like = '%'.$q.'%';

if ($active_tab === 'nastav' || $active_tab === 'starchenstvo') {
    $table = $active_tab;
    $columns = ['id','user_login','date_added','added_by','is_active'];
    $sortAllow = ['id','user_login','date_added','added_by','is_active'];
} else {
    $table = $active_tab === 'mentorship' ? 'mentorship_results' : 'seniority_results';
    $columns = ['id','user_login','score','time_spent','attempt_number','test_date'];
    $sortAllow = ['id','user_login','score','time_spent','attempt_number','test_date'];
}

$sort = $_GET['sort'] ?? 'id';
if (!in_array($sort, $sortAllow, true)) $sort = 'id';
$dir = sanitize_dir($_GET['dir'] ?? 'desc');

$where = $q !== '' ? "WHERE user_login LIKE ?" : "";
$countSql = "SELECT COUNT(*) FROM $table $where";
if ($q !== '') {
    $stmt = mysqli_prepare($link, $countSql);
    mysqli_stmt_bind_param($stmt, "s", $like);
} else {
    $stmt = mysqli_prepare($link, $countSql);
}
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $totalRows);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$totalPages = max(1, (int)ceil($totalRows / $limit));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $limit;

$selectCols = implode(',', $columns);
$listSql = "SELECT $selectCols FROM $table $where ORDER BY $sort $dir LIMIT ? OFFSET ?";
if ($q !== '') {
    $stmt = mysqli_prepare($link, $listSql);
    mysqli_stmt_bind_param($stmt, "sii", $like, $limit, $offset);
} else {
    $stmt = mysqli_prepare($link, $listSql);
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$rows = [];
while ($res && ($r = mysqli_fetch_assoc($res))) { $rows[] = $r; }
mysqli_stmt_close($stmt);

$table_name = $tabLabels[$active_tab];
$table_columns = $active_tab==='nastav'||$active_tab==='starchenstvo'
    ? ['ID','Логин','Дата добавления','Добавил','Статус']
    : ['ID','Логин','Баллы','Время (сек)','Попытка','Дата теста'];
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
.tab{padding:10px 14px;border:1px solid var(--border);border-radius:999px;background:#0b1226;color:#cbd5e1;cursor:pointer;user-select:none}
.tab:hover{background:#0e1630}
.tab.active{background:var(--accent);color:white;border-color:transparent}
.panel{padding:16px 16px 8px 16px}
.title{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.title h2{margin:0;font-size:18px}
.actions-bar{display:flex;gap:8px;flex-wrap:wrap}
.search{display:flex;gap:8px;align-items:center;width:100%}
.search input{flex:1;min-width:200px;padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:#0b1226;color:var(--text)}
.search button,.btn{padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:#0b1226;color:var(--text);cursor:pointer}
.btn-primary{background:var(--accent);border-color:transparent;color:white}
.btn-primary:hover{filter:brightness(1.1)}
.btn-danger{background:transparent;border-color:var(--danger);color:#fecaca}
.btn-danger:hover{background:rgba(239,68,68,.1)}
.btn-ghost{background:transparent}
.message{margin:12px 16px 0 16px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.3);color:#e5edff;padding:10px 12px;border-radius:10px}
.table-wrap{margin-top:12px;border-top:1px solid var(--border)}
table{width:100%;border-collapse:separate;border-spacing:0}
thead th{position:sticky;top:0;background:#0b1226;border-bottom:1px solid var(--border);padding:12px;text-align:left;font-size:13px;color:#cbd5e1;z-index:1}
tbody td{border-bottom:1px solid var(--border);padding:12px;font-size:14px}
tbody tr:hover{background:#0c1328}
th .th-btn{display:inline-flex;align-items:center;gap:6px;cursor:pointer}
.sort-indicator{font-size:10px;opacity:.7}
.badge{padding:4px 8px;border-radius:999px;font-size:12px;display:inline-block}
.badge-ok{background:rgba(34,197,94,.15);color:#86efac;border:1px solid rgba(34,197,94,.3)}
.badge-off{background:rgba(239,68,68,.15);color:#fecaca;border:1px solid rgba(239,68,68,.3)}
.row-actions{display:flex;gap:6px}
.edit-form{display:none;background:#0b1226;border-top:1px dashed var(--border)}
.form-row{display:flex;gap:12px}
.form-row > div{flex:1}
.form-group label{display:block;margin:6px 0 6px 0;font-size:13px;color:#cbd5e1}
.form-group input{width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);background:#0b1226;color:var(--text)}
.switch{display:flex;align-items:center;gap:8px}
.pagination{display:flex;gap:6px;align-items:center;justify-content:flex-end;padding:12px 16px;border-top:1px solid var(--border)}
.page{padding:8px 12px;border:1px solid var(--border);border-radius:10px;background:#0b1226;color:#cbd5e1;text-decoration:none}
.page.active{background:var(--accent);border-color:transparent;color:white}
.page:hover{background:#0e1630}
.tools{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
hr.sep{border:0;border-top:1px solid var(--border);margin:14px 0}
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
        </div>

        <?php if ($message !== ''): ?>
            <div class="message"><?php echo esc($message); ?></div>
        <?php endif; ?>

        <div class="panel">
            <div class="title">
                <h2><?php echo esc($table_name); ?></h2>
                <div class="tools">
                    <button class="btn btn-primary" onclick="toggleAddForm()">Добавить запись</button>
                    <a class="btn btn-ghost" href="index.php">Выйти</a>
                </div>
            </div>

            <form class="search" method="GET">
                <input type="hidden" name="tab" value="<?php echo esc($active_tab); ?>">
                <input type="hidden" name="sort" value="<?php echo esc($sort); ?>">
                <input type="hidden" name="dir" value="<?php echo esc($dir); ?>">
                <input type="text" name="q" value="<?php echo esc($q); ?>" placeholder="Поиск по логину...">
                <button class="btn">Найти</button>
            </form>

            <div id="addForm" class="edit-form" style="padding:16px;border-top:1px solid var(--border);margin-top:12px;border-radius:10px">
                <h3 style="margin-top:0">Добавить запись</h3>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo esc($csrf); ?>">
                    <?php if ($active_tab == 'nastav' || $active_tab == 'starchenstvo'): ?>
                        <div class="form-row">
                            <div class="form-group"><label>Логин пользователя</label><input type="text" name="user_login" required></div>
                            <div class="form-group switch" style="margin-top:28px">
                                <label><input type="checkbox" name="is_active" checked> Активен</label>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="form-row">
                            <div class="form-group"><label>Логин пользователя</label><input type="text" name="user_login" required></div>
                            <div class="form-group"><label>Баллы</label><input type="number" step="0.01" name="score" required></div>
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

            <div class="table-wrap">
                <table id="dataTable">
                    <thead>
                        <tr>
                            <?php
                            $sortKeys = $active_tab==='nastav'||$active_tab==='starchenstvo'
                                ? ['id','user_login','date_added','added_by','is_active']
                                : ['id','user_login','score','time_spent','attempt_number','test_date'];
                            foreach ($table_columns as $i=>$col):
                                $key = $sortKeys[$i];
                                $isActive = $sort===$key;
                                $arrow = $isActive ? ($dir==='asc'?'▲':'▼') : '';
                            ?>
                            <th data-sort="<?php echo esc($key); ?>"><span class="th-btn" onclick="clickSort('<?php echo esc($key); ?>')"><?php echo esc($col); ?> <span class="sort-indicator"><?php echo esc($arrow); ?></span></span></th>
                            <?php endforeach; ?>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <tr id="row-<?php echo (int)$row['id']; ?>">
                            <?php if ($active_tab == 'nastav' || $active_tab == 'starchenstvo'): ?>
                                <td data-type="number"><?php echo (int)$row['id']; ?></td>
                                <td data-type="string"><?php echo esc($row['user_login']); ?></td>
                                <td data-type="date" data-order="<?php echo (int)strtotime($row['date_added']); ?>"><?php echo esc($row['date_added']); ?></td>
                                <td data-type="string"><?php echo esc($row['added_by']); ?></td>
                                <td data-type="number" data-status="<?php echo (int)$row['is_active']; ?>">
                                    <span class="badge <?php echo ((int)$row['is_active'])?'badge-ok':'badge-off'; ?>" onclick="toggleStatus(<?php echo (int)$row['id']; ?>, <?php echo ((int)$row['is_active'])?0:1; ?>)"><?php echo ((int)$row['is_active'])?'Активен':'Неактивен'; ?></span>
                                </td>
                            <?php else: ?>
                                <td data-type="number"><?php echo (int)$row['id']; ?></td>
                                <td data-type="string"><?php echo esc($row['user_login']); ?></td>
                                <td data-type="number"><?php echo esc($row['score']); ?></td>
                                <td data-type="number"><?php echo (int)$row['time_spent']; ?></td>
                                <td data-type="number"><?php echo (int)$row['attempt_number']; ?></td>
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
                                                <div class="form-group switch" style="margin-top:28px">
                                                    <label><input type="checkbox" name="is_active" <?php echo ((int)$row['is_active'])?'checked':''; ?>> Активен</label>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="form-row">
                                                <div class="form-group"><label>Логин пользователя</label><input type="text" name="user_login" value="<?php echo esc($row['user_login']); ?>" required></div>
                                                <div class="form-group"><label>Баллы</label><input type="number" step="0.01" name="score" value="<?php echo esc($row['score']); ?>" required></div>
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
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php
                $buildLink = function($p) use($active_tab,$q,$sort,$dir){
                    $params = ['tab'=>$active_tab,'page'=>$p,'q'=>$q,'sort'=>$sort,'dir'=>$dir];
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
function goTab(tab){const u=new URL(location.href);u.searchParams.set('tab',tab);u.searchParams.set('page','1');history.replaceState(null,'',u);location.href=u;}
function toggleAddForm(){const f=document.getElementById('addForm');f.style.display=f.style.display==='block'?'none':'block'}
function toggleEditForm(id){const f=document.getElementById('edit-form-'+id);f.style.display=f.style.display==='table-row'?'none':'table-row'}
function clickSort(key){const u=new URL(location.href);const cur=u.searchParams.get('sort')||'id';let dir=u.searchParams.get('dir')||'desc';if(cur===key){dir=dir==='asc'?'desc':'asc'}else{dir='asc'}u.searchParams.set('sort',key);u.searchParams.set('dir',dir);u.searchParams.set('page','1');location.href=u.toString()}
function toggleStatus(id,newStatus){if(!confirm('Изменить статус?'))return;const f=document.createElement('form');f.method='POST';f.style.display='none';const h=(n,v)=>{const i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;f.appendChild(i)};h('csrf',CSRF_TOKEN);h('record_id',id);h('new_status',newStatus);h('toggle_status','1');document.body.appendChild(f);f.submit()}
</script>
</body>
</html>