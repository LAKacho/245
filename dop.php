$where=""; $params=[]; $types="";

/* разбиваем колонки по типам */
$textCols = [];
$numCols  = [];
foreach ($meta['columns'] as $col => $info) {
    $t = strtolower($info['type']);
    if (is_text_type($t)) $textCols[] = $col;
    if (is_int_type($t) || is_float_type($t)) $numCols[] = $col;
}

/* специально подсветим tb_nomer, если есть */
if (isset($meta['columns']['tb_nomer']) && !in_array('tb_nomer', $numCols, true)) {
    $numCols[] = 'tb_nomer';
}

if ($q !== "") {
    $cond = [];

    // По всем текстовым столбцам — LIKE
    foreach ($textCols as $c) {
        $cond[]   = "`$c` LIKE ?";
        $params[] = $like;
        $types   .= "s";
    }

    // По числовым: если запрос из цифр — точное = и LIKE по CAST,
    // иначе только LIKE по CAST (даже если ввод не-числовой).
    $isDigits = ctype_digit($q);
    foreach ($numCols as $c) {
        if ($isDigits) {
            $cond[]   = "`$c` = ?";
            $params[] = (int)$q;
            $types   .= "i";
        }
        $cond[]   = "CAST(`$c` AS CHAR) LIKE ?";
        $params[] = $like;
        $types   .= "s";
    }

    if ($cond) {
        $where = "WHERE (" . implode(" OR ", $cond) . ")";
    }
}

/* фильтр по дате, если нашли какой-либо date/datetime столбец */
if ($meta['dt_field']) {
    if (!empty($fromDate)) {
        $where .= ($where ? " AND " : "WHERE ") . "`{$meta['dt_field']}` >= ?";
        $params[] = $fromDate.' 00:00:00';
        $types   .= "s";
    }
    if (!empty($toDate)) {
        $where .= ($where ? " AND " : "WHERE ") . "`{$meta['dt_field']}` <= ?";
        $params[] = $toDate.' 23:59:59';
        $types   .= "s";
    }
}