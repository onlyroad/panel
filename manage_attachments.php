<?php
require_once 'config_init.php'; 
session_start();

// 세션 확인
if (!isset($_SESSION['user_uid'])) {
    header('Location: login.php');
    exit;
}

// --- 로직 부분 --- //
// DB에서 사용 가능한 디렉토리 목록 읽어오기
$available_dirs = [];
$query = "SELECT distinct `kind`, `path` FROM `uploadfile` WHERE `kind` IS NOT NULL AND `kind` != '' ORDER BY `kind`";
$result = mysqli_query($connection, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $available_dirs[] = ['kind' => $row['kind'], 'path' => $row['path']];
    }
}
/* 
NEWSIMG     /images/news                            church_news.publish_yn     parent_table    parent_no 
NEWSFILE    /data/uploads/news                      church_news.publish_yn     parent_table    parent_no 
IMG         /images/weekly                          church_weekly(use_yn없음)                   parent_no  
board_board /www/hk7vvfym2hupesyw/data/uploads/     board(가변적) 
SWIPERPC    /images/main_swiper                     main_swiper.use_yn,pc_img_path           parent_table    parent_no
SWIPERMO    /images/main_swiper                     main_swiper.use_yn,mo_img_path           parent_table    parent_no
CB_FILE     /data/uploads/cb/archive                church_board.publish_yn    parent_table    parent_no
CB_IMG      /images/cb/archive                      church_board.publish_yn    parent_table    parent_no
POPUP       /images/main_modalpopup                 main_modalpopup.use_yn     parent_table    parent_no

*/
// GET 요청에서 kind 파라미터 확인
$selected_kind = '';
$dir = ''; // 기본값

if (!empty($available_dirs)) {
    $selected_kind = $available_dirs[0]['kind'];
    $dir = $available_dirs[0]['path'];
}

if (isset($_GET['kind'])) {
    foreach ($available_dirs as $item) {
        if ($item['kind'] === $_GET['kind']) {
            $selected_kind = $item['kind'];
            $dir = $item['path'];
            break;
        }
    }
}


// $dir 값에 따라 $full_dir_path 경로 조합
if (strpos($dir, '/data') === 0) {
    $full_dir_path = $USER_ROOT . $dir;
} else if(strpos($dir, '/images') === 0) {
    $full_dir_path = __DIR__ . "/" . $dir;
} else {
    $full_dir_path = $dir;
}
$image_extensions = ['jpg', 'jpeg', 'png', 'gif'];

// POST 요청으로 파일 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['delete_file_no']) || isset($_POST['delete_file']))) {
    if (isset($_POST['delete_file_no'])) {
        // DB 기반 삭제 (새로운 방식)
        $file_no_to_delete = (int)$_POST['delete_file_no'];

        // 1. DB에서 파일 정보 가져오기 (fullpath 필요)
        $query = "SELECT `fullpath` , `upload_name` FROM `uploadfile` WHERE `file_no` = ?";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "i", $file_no_to_delete);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $file_info = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($file_info) {
            // 2. 파일 시스템에서 파일 삭제
            $file_path_to_delete = $file_info['fullpath'] . "/" .  $file_info['upload_name'] ;
            if (!empty($file_path_to_delete) && file_exists($file_path_to_delete)) {
                unlink($file_path_to_delete);
            }

            // 3. DB에서 레코드 삭제
            $query = "DELETE FROM `uploadfile` WHERE `file_no` = ?";
            $stmt = mysqli_prepare($connection, $query);
            mysqli_stmt_bind_param($stmt, "i", $file_no_to_delete);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } else {
        // 기존 파일 시스템 기반 삭제 로직 (혹시 모를 경우 대비)
        $file_to_delete = basename($_POST['delete_file']); // 보안을 위해 basename() 사용
        $file_path_to_delete = $full_dir_path . '/' . $file_to_delete;
        if (file_exists($file_path_to_delete)) {
            unlink($file_path_to_delete);
        }
    }

    // 새로고침 시 중복 삭제를 방지하기 위해 리디렉션
    header("Location: " . $_SERVER['PHP_SELF'] . '?kind=' . urlencode($selected_kind));
    exit;
}


// 키가 DB에 있는지 확인하는 함수
function check_key_exists($key) {
    global $connection;

    $qstr = "select count(*) as cnt from `uploadfile` WHERE file_no = " . $key; 
    $result = mysqli_query($connection,$qstr);
    if($obj = mysqli_fetch_object($result)) {
        return $obj->cnt > 0; 
    }
    return false;
}

// 파일 크기를 포맷하는 함수
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>첨부파일관리</title>
    <style>
        body { font-family: sans-serif; }
        ul { list-style-type: none; padding: 0; max-width: 800px; margin: 20px auto; }
        li { display: flex; align-items: center; gap: 15px; border-bottom: 1px solid #eee; padding: 10px; }
        li:last-child { border-bottom: none; }
        img { max-width: 100px; max-height: 100px; }
        .filename { flex-grow: 1; }
        .status { width: 80px; text-align: center; }
        .delete-btn { background-color: #ff4d4d; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; }
    </style>
</head>
<body>

<?php include 'navi.php'; ?>

<h1 style="text-align: center;">첨부파일관리 (<?php echo $selected_kind; ?>)</h1>

<div style="text-align: center; margin-bottom: 20px;">
    <form action="" method="GET">
        <select name="kind" onchange="this.form.submit()">
            <?php foreach ($available_dirs as $d): ?>
                <option value="<?php echo htmlspecialchars($d['kind']); ?>" <?php if ($d['kind'] === $selected_kind) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($d['kind']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div style="text-align: center; font-size: 12px; color: #666; margin-bottom: 20px;">
    <strong>Full Path:</strong> <?php echo htmlspecialchars($full_dir_path); ?>
</div>

<ul>
    <?php
    // --- 페이징 처리 ---
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $page = max(1, $page); // 페이지는 1 이상
    $items_per_page = $maxline; // 파일 상단에 정의된 $maxline 변수 사용
    $offset = ($page - 1) * $items_per_page;

    // 전체 아이템 개수 계산
    $count_query = "SELECT COUNT(*) as total FROM `uploadfile` WHERE `kind` = ?";
    $count_stmt = mysqli_prepare($connection, $count_query);
    mysqli_stmt_bind_param($count_stmt, "s", $selected_kind);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_rows = mysqli_fetch_assoc($count_result)['total'];
    mysqli_stmt_close($count_stmt);

    $total_pages = ceil($total_rows / $items_per_page);
    // --- 페이징 처리 끝 ---

    // 쿼리를 현재 선택된 디렉토리($dir)에 대해서만 실행하도록 수정합니다.
    $query = "SELECT `file_no`, `kind`, `name`, `type`, `size`, `down_count`, `path`, `fullpath`, `upload_name`,  `use_yn`, `creation_time`, `parent_table`, `parent_no` FROM `uploadfile` WHERE `kind` = ? ORDER BY `file_no` DESC LIMIT ?, ?";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "sii", $selected_kind, $offset, $items_per_page);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            // fullpath가 비어있을 경우, dir과 upload_name을 조합하여 경로를 만듭니다.
            if (empty($row['fullpath'])) {
                // 이 부분은 서버 환경에 맞게 조정이 필요할 수 있습니다.
                // 예: $USER_ROOT 와 같은 기본 경로를 사용
                if (strpos($row['path'], '/data') === 0) {
                    $file_path_on_disk = $USER_ROOT . $row['path'] . '/' . $row['upload_name'];
                } else {
                    $file_path_on_disk = $_SERVER['DOCUMENT_ROOT'] . $row['path'] . '/' . $row['upload_name'];
                }
            } else {
                $file_path_on_disk = $row['fullpath'];
            }

            $file_exists = file_exists($file_path_on_disk);

            // 웹에서 접근 가능한 이미지 URL 생성
            $image_url = '';
            $file_ext = strtolower(pathinfo($row['upload_name'], PATHINFO_EXTENSION));
            $is_web_accessible = strpos($row['path'], '/data') !== 0;

            if (in_array($file_ext, $image_extensions) && $is_web_accessible) {
                $image_url = $row['path'] . '/' . $row['upload_name'];
            }

            // kind에 따라 부모 테이블의 사용여부 가져오기
            $parent_status = '부모없음 .'; // 기본값
            $parent_status_color = '#555'; // 기본값 for 부모없음
            $parent_table = $row['parent_table'];
            $parent_no = $row['parent_no'];
            $kind = $row['kind'];

            
                $sql = "";
                $bind_type = "i";
                $bind_value = $parent_no;
                $use_yn_col = 'use_yn'; // default column name

                switch ($kind) {
                    case 'NEWSIMG':
                    case 'NEWSFILE':
                        $sql = "SELECT `publish_yn` FROM `church_news` WHERE `news_no` = ?";
                        $use_yn_col = 'publish_yn';
                        break;
                    case 'SWIPERPC':
                    case 'SWIPERMO':
                        $sql = "SELECT `use_yn` FROM `main_swiper` WHERE `swiper_no` = ?";
                        break;
                    case 'CB_FILE':
                    case 'CB_IMG':
                        $sql = "SELECT `publish_yn` FROM `church_board` WHERE `board_no` = ?";
                        $use_yn_col = 'publish_yn';
                        break;
                    case 'POPUP':
                        $sql = "SELECT `use_yn` FROM `main_modalpopup` WHERE `popup_no` = ?";
                        break;
                    case 'IMG' : 
                        $sql = "SELECT  'Y' as use_yn FROM `church_weekly` WHERE `weekly_no` = ?";
                        break;
                    case 'board_board' : 
                        $sql = "SELECT  `use_yn` FROM `board` WHERE `no` = ?";
                        break;

                }

                if (!empty($sql)) {
                    $stmt_use_yn = mysqli_prepare($connection, $sql);
                    if ($stmt_use_yn) {
                        mysqli_stmt_bind_param($stmt_use_yn, $bind_type, $bind_value);
                        mysqli_stmt_execute($stmt_use_yn);
                        $result_use_yn = mysqli_stmt_get_result($stmt_use_yn);
                        $row_use_yn = mysqli_fetch_assoc($result_use_yn);
                        
                        if ($row_use_yn === null) {
                            $parent_status = '부모 없음';
                        } else {
                            $parent_use_yn = $row_use_yn[$use_yn_col];
                            if ($parent_use_yn === 'Y') {
                                $parent_status = '부모 사용';
                                $parent_status_color = 'blue';
                            } else {
                                $parent_status = '부모 미사용';
                                $parent_status_color = '#888';
                            }
                        }
                        mysqli_stmt_close($stmt_use_yn);
                    }
                }
            

            $status_text = $row['use_yn'] === 'Y' ? '사용중' : '미사용';
            $status_color = $row['use_yn'] === 'Y' ? 'blue' : '#888';
    ?>
    <li>
        <?php if ($image_url && $file_exists): ?>
            <a href="<?php echo htmlspecialchars($image_url); ?>" target="_blank">
                <img src="<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
            </a>
        <?php else: ?>
            <div style="width:100px; height:100px; background-color:#f0f0f0; display:flex; align-items:center; justify-content:center; font-size:12px; text-align:center; color: #666; border: 1px solid #ddd;">
                <?php echo htmlspecialchars($file_ext); ?><?php if (!$file_exists) echo '<br>(no file)'; ?>
            </div>
        <?php endif; ?>

        <div class="filename">
            <strong>원본명:</strong> <?php echo htmlspecialchars($row['name']); ?><br>
            <strong>저장명:</strong> <?php echo htmlspecialchars($row['upload_name']); ?><br>
            <small>
                <strong>Size:</strong> <?php echo format_bytes($row['size']); ?> |
                <strong>Down:</strong> <?php echo $row['down_count']; ?> |
                <strong>Date:</strong> <?php echo date("Y-m-d", strtotime($row['creation_time'])); ?> |
                <strong>Parent:</strong> <?php echo htmlspecialchars($row['parent_table'] . ' (' . $row['parent_no'] . ')'); ?>
            </small>
            <?php if (!$file_exists): ?>
                <br><strong style="color: red;">실제 파일 없음!</strong><br>
                <small style="color: #999;"><?php echo htmlspecialchars($file_path_on_disk); ?></small>
            <?php endif; ?>
        </div>
        <div class="status" style="color: <?php echo $status_color; ?>;">
            <strong><?php echo $status_text; ?></strong>
            <br><small style="color: <?php echo $parent_status_color; ?>;">(<?php echo $parent_status; ?>)</small>
        </div>
        <?php if ($row['use_yn'] === 'Y'): ?>
            <button type="submit" class="delete-btn" disabled>삭제</button>
        <?php else: ?>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?kind=' . urlencode($selected_kind)); ?>" method="POST" onsubmit="return confirm('정말 이 항목을 삭제하시겠습니까? DB 기록과 실제 파일(존재하는 경우)이 모두 삭제됩니다.');">
                <input type="hidden" name="delete_file_no" value="<?php echo htmlspecialchars($row['file_no']); ?>">
                <button type="submit" class="delete-btn">삭제</button>
            </form>
        <?php endif; ?>
    </li>
    <?php
        } // end while
    } else {
        echo "<li><p>이 디렉토리에 대한 파일 정보가 DB에 없습니다.</p></li>";
    }
    mysqli_stmt_close($stmt);
    ?>
</ul>

<div style="text-align: center; margin-top: 20px; font-size: 14px;">
    <?php if ($total_pages > 1): ?>
        <?php if ($page > 1): ?>
            <a href="?kind=<?php echo urlencode($selected_kind); ?>&page=<?php echo $page - 1; ?>" style="text-decoration: none; padding: 5px 10px; border: 1px solid #ddd; color: #333;">&laquo; 이전</a>
        <?php endif; ?>

        <?php 
        // 페이지네이션 그룹 설정
        $start_page = max(1, $page - 5);
        $end_page = min($total_pages, $page + 4);

        if ($start_page > 1) {
            echo '<a href="?kind='.urlencode($selected_kind).'&page=1" style="text-decoration: none; padding: 5px 10px; border: 1px solid #ddd; color: #333;">1</a>';
            if ($start_page > 2) {
                echo '<span style="padding: 5px;">...</span>';
            }
        }

        for ($i = $start_page; $i <= $end_page; $i++): 
        ?>
            <a href="?kind=<?php echo urlencode($selected_kind); ?>&page=<?php echo $i; ?>" style="text-decoration: none; padding: 5px 10px; border: 1px solid #ddd; color: #333; <?php if ($i == $page) echo 'font-weight: bold; background-color: #f0f0f0;'; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                echo '<span style="padding: 5px;">...</span>';
            }
            echo '<a href="?kind='.urlencode($selected_kind).'&page='.$total_pages.'" style="text-decoration: none; padding: 5px 10px; border: 1px solid #ddd; color: #333;">'.$total_pages.'</a>';
        }
        ?>

        <?php if ($page < $total_pages): ?>
            <a href="?kind=<?php echo urlencode($selected_kind); ?>&page=<?php echo $page + 1; ?>" style="text-decoration: none; padding: 5px 10px; border: 1px solid #ddd; color: #333;">다음 &raquo;</a>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>