<?php
require_once 'config_init.php';
session_start();

// 세션 확인
if (!isset($_SESSION['user_uid'])) {
    header('Location: login.php');
    exit;
}

$current_user_uid = $_SESSION['user_uid'];

// 날짜 포맷팅 함수
function format_display_date($date_string) {
    if (empty($date_string)) {
        return '-';
    }
    $date = new DateTime($date_string);
    $now = new DateTime();
    if ($date->format('Y-m-d') === $now->format('Y-m-d')) {
        return $date->format('H:i:s');
    } else {
        return $date->format('Y-m-d');
    }
}

// POST 요청 처리 (삭제, 승인, 승인 취소)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $admin_no = $_POST['admin_no'] ?? 0;

    if ($action === 'delete' && $admin_no > 0) {
        $stmt = mysqli_prepare($connection, "DELETE FROM `admin_user` WHERE `admin_no` = ?");
        mysqli_stmt_bind_param($stmt, 'i', $admin_no);
        mysqli_stmt_execute($stmt);
    } elseif ($action === 'approve' && $admin_no > 0) {
        $stmt = mysqli_prepare($connection, "UPDATE `admin_user` SET `confirm_yn`='Y', `confirm_user`=?, `confirm_date`=CONVERT_TZ(UTC_TIMESTAMP(),'+00:00','+09:00'), `changer`=?, `udate`=CONVERT_TZ(UTC_TIMESTAMP(),'+00:00','+09:00') WHERE `admin_no` = ?");
        mysqli_stmt_bind_param($stmt, 'ssi', $current_user_uid, $current_user_uid, $admin_no);
        mysqli_stmt_execute($stmt);
    } elseif ($action === 'cancel_approval' && $admin_no > 0) {
        $stmt = mysqli_prepare($connection, "UPDATE `admin_user` SET `confirm_yn`='N', `confirm_user`=NULL, `confirm_date`=NULL, `changer`=?, `udate`=CONVERT_TZ(UTC_TIMESTAMP(),'+00:00','+09:00') WHERE `admin_no` = ?");
        mysqli_stmt_bind_param($stmt, 'si', $current_user_uid, $admin_no);
        mysqli_stmt_execute($stmt);
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// --- GET 요청 로직 --- //

$search_field = isset($_GET['search_field']) && in_array($_GET['search_field'], ['name', 'email']) ? $_GET['search_field'] : 'name';
$search_term = isset($_GET['search_term']) ? $_GET['search_term'] : '';
$confirm_status = isset($_GET['confirm_yn']) ? $_GET['confirm_yn'] : '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

$where_conditions = [];
$params = [];
$types = '';

if ($search_term !== '') {
    $allowed_fields = ['name', 'email'];
    if (in_array($search_field, $allowed_fields)) {
        $where_conditions[] = "u.`{$search_field}` LIKE ?";
        $search_param = "%{$search_term}%";
        $params[] = &$search_param;
        $types .= 's';
    }
}
if ($confirm_status !== '') {
    $where_conditions[] = "u.`confirm_yn` = ?";
    $params[] = &$confirm_status;
    $types .= 's';
}
$where_clause = !empty($where_conditions) ? " WHERE " . implode(' AND ', $where_conditions) : '';

$total_query = "SELECT COUNT(*) as total FROM `admin_user` u" . $where_clause;
$total_stmt = mysqli_prepare($connection, $total_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($total_stmt, $types, ...$params);
}
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$total_records = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

$query = "SELECT u.`admin_no`, u.`admin_uid`, u.`name`, u.`photo`, u.`email`, u.`use_yn`, u.`confirm_yn`, u.`confirm_date`, u.`idate`, u.`udate`, confirm_admin.name as confirm_user_name, creator_admin.name as creator_name, changer_admin.name as changer_name FROM `admin_user` u LEFT JOIN `admin_user` confirm_admin ON u.confirm_user = confirm_admin.admin_uid LEFT JOIN `admin_user` creator_admin ON u.creator = creator_admin.admin_uid LEFT JOIN `admin_user` changer_admin ON u.changer = changer_admin.admin_uid" . $where_clause . " ORDER BY u.`admin_no` DESC LIMIT ?, ?";
$stmt = mysqli_prepare($connection, $query);

$limit_params = [&$offset, &$records_per_page];
$param_refs = array_merge($params, $limit_params);
$all_types = $types . 'ii';
$bind_args = [&$stmt, &$all_types];
foreach ($param_refs as $key => $value) { $bind_args[] = &$param_refs[$key]; }
if (!empty($where_conditions)) { call_user_func_array('mysqli_stmt_bind_param', $bind_args); } else { mysqli_stmt_bind_param($stmt, 'ii', $offset, $records_per_page); }

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>관리자 목록</title>
    <style>
        body { font-family: sans-serif; color: #333; background-color: #f4f7f6; }
        .container { max-width: 1400px; margin: 20px auto; padding: 0 20px; }
        h1 { text-align: center; color: #2c3e50; }
        .search-container { margin-bottom: 20px; padding: 15px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: flex-end; align-items: center; gap: 10px; }
        .search-container input[type='text'], .search-container select { padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .search-container button { padding: 10px 15px; background-color: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; transition: background-color 0.3s; }
        .search-container button:hover { background-color: #2980b9; }
        
        .list-container { font-size: 14px; }
        .list-header, .list-row { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #e0e0e0; }
        .list-header { font-weight: bold; background-color: #ecf0f1; border-radius: 8px 8px 0 0; color: #34495e; }
        .list-row { background-color: #fff; transition: background-color 0.3s, box-shadow 0.3s; }
        .list-row:hover { background-color: #fdfdfd; box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
        
        .list-item { padding: 8px; box-sizing: border-box; text-align: center; word-break: break-all; }
        .list-item.admin-no { flex-basis: 5%; }
        .list-item.name { flex-basis: 8%; }
        .list-item.photo { flex-basis: 7%; }
        .list-item.email { flex-basis: 15%; text-align: left; }
        .list-item.use-yn { flex-basis: 5%; }
        .list-item.confirm-yn { flex-basis: 5%; }
        .list-item.confirm-user { flex-basis: 8%; }
        .list-item.confirm-date { flex-basis: 8%; }
        .list-item.creator { flex-basis: 8%; }
        .list-item.idate { flex-basis: 8%; }
        .list-item.changer { flex-basis: 8%; }
        .list-item.udate { flex-basis: 8%; }
        .list-item.actions { flex-basis: 12%; }

        .photo-img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid #ecf0f1; }

        .pagination { text-align: center; padding: 20px 0; }
        .pagination a { display: inline-block; color: #3498db; padding: 8px 16px; text-decoration: none; transition: background-color .3s; border: 1px solid #ddd; margin: 0 4px; border-radius: 4px; }
        .pagination a.active { background-color: #3498db; color: white; border-color: #3498db; }
        .pagination a:hover:not(.active) { background-color: #ecf0f1; }
        
        .action-buttons form { display: inline-block; margin: 0 2px; }
        .action-buttons button { padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer; color: white; font-size: 12px; transition: opacity 0.3s; }
        .action-buttons button:hover { opacity: 0.8; }
        .btn-delete { background-color: #e74c3c; }
        .btn-approve { background-color: #2ecc71; }
        .btn-cancel { background-color: #f39c12; }
    </style>
</head>
<body>

<?php include 'navi.php'; ?>

<div class="container">
    <h1>관리자 목록</h1>

    <div class="search-container">
        <form action="" method="GET" style="display: flex; gap: 10px;">
            <select name="confirm_yn"><option value="">전체 승인여부</option><option value="Y" <?= $confirm_status === 'Y' ? 'selected' : '' ?>>승인</option><option value="N" <?= $confirm_status === 'N' ? 'selected' : '' ?>>미승인</option></select>
            <select name="search_field"><option value="name" <?= $search_field === 'name' ? 'selected' : '' ?>>이름</option><option value="email" <?= $search_field === 'email' ? 'selected' : '' ?>>이메일</option></select>
            <input type="text" name="search_term" placeholder="검색어..." value="<?= htmlspecialchars($search_term) ?>">
            <button type="submit">검색</button>
        </form>
    </div>

    <div class="list-container">
        <div class="list-header">
            <div class="list-item admin-no">번호</div>
            <div class="list-item name">이름</div>
            <div class="list-item photo">사진</div>
            <div class="list-item email">이메일</div>
            <div class="list-item use-yn">사용</div>
            <div class="list-item confirm-yn">승인</div>
            <div class="list-item confirm-user">승인자</div>
            <div class="list-item confirm-date">승인일</div>
            <div class="list-item creator">생성자</div>
            <div class="list-item idate">생성일</div>
            <div class="list-item changer">수정자</div>
            <div class="list-item udate">수정일</div>
            <div class="list-item actions">관리</div>
        </div>
        <?php
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                echo '<div class="list-row">';
                echo '<div class="list-item admin-no">' . htmlspecialchars($row['admin_no']) . '</div>';
                echo '<div class="list-item name">' . htmlspecialchars($row['name']) . '</div>';
                echo '<div class="list-item photo">';
                if (!empty($row['photo'])) {
                    echo '<img src="' . htmlspecialchars($row['photo']) . '" alt="' . htmlspecialchars($row['name']) . '" class="photo-img">';
                } else {
                    echo '-';
                }
                echo '</div>';
                echo '<div class="list-item email">' . htmlspecialchars($row['email']) . '</div>';
                echo '<div class="list-item use-yn">' . htmlspecialchars($row['use_yn']) . '</div>';
                echo '<div class="list-item confirm-yn">' . htmlspecialchars($row['confirm_yn']) . '</div>';
                echo '<div class="list-item confirm-user">' . htmlspecialchars($row['confirm_user_name']) . '</div>';
                echo '<div class="list-item confirm-date">' . format_display_date($row['confirm_date']) . '</div>';
                echo '<div class="list-item creator">' . htmlspecialchars($row['creator_name']) . '</div>';
                echo '<div class="list-item idate">' . format_display_date($row['idate']) . '</div>';
                echo '<div class="list-item changer">' . htmlspecialchars($row['changer_name']) . '</div>';
                echo '<div class="list-item udate">' . format_display_date($row['udate']) . '</div>';
                echo '<div class="list-item actions action-buttons">';
                if ($row['confirm_yn'] === 'N') {
                    echo '<form method="POST" action=""><input type="hidden" name="action" value="approve"><input type="hidden" name="admin_no" value="' . $row['admin_no'] . '"><button type="submit" class="btn-approve">승인</button></form>';
                } else {
                    echo '<form method="POST" action=""><input type="hidden" name="action" value="cancel_approval"><input type="hidden" name="admin_no" value="' . $row['admin_no'] . '"><button type="submit" class="btn-cancel">승인 취소</button></form>';
                }
                echo '<form method="POST" action="" onsubmit="return confirm(\'정말로 삭제하시겠습니까?\');"><input type="hidden" name="action" value="delete"><input type="hidden" name="admin_no" value="' . $row['admin_no'] . '"><button type="submit" class="btn-delete">삭제</button></form>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<div style="text-align:center; padding: 20px; background-color: #fff; border-radius: 8px;">표시할 데이터가 없습니다.</div>';
        }
        ?>
    </div>

    <div class="pagination">
        <?php
        $query_params = [];
        if ($search_term !== '') { $query_params['search_field'] = $search_field; $query_params['search_term'] = $search_term; }
        if ($confirm_status !== '') { $query_params['confirm_yn'] = $confirm_status; }
        if ($page > 1) { $prev_page_params = http_build_query(array_merge($query_params, ['page' => $page - 1])); echo "<a href='?$prev_page_params'>&laquo; 이전</a>"; }
        for ($i = 1; $i <= $total_pages; $i++) { $page_params = http_build_query(array_merge($query_params, ['page' => $i])); $active_class = ($i == $page) ? ' class="active"' : ''; echo "<a href='?$page_params'$active_class>$i</a>"; }
        if ($page < $total_pages) { $next_page_params = http_build_query(array_merge($query_params, ['page' => $page + 1])); echo "<a href='?$next_page_params'>다음 &raquo;</a>"; }
        ?>
    </div>
</div>

<?php $connection->close(); ?>

</body>
</html>