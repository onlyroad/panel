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
$query = "SELECT distinct `path` FROM `uploadfile`";
$result = mysqli_query($connection, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['path'])) {
            $available_dirs[] = $row['path'];
        }
    }
}

// GET 요청에서 dir 파라미터 확인
$dir = ''; // 기본값
if (!empty($available_dirs)) {
    $dir = $available_dirs[0]; // 첫 번째 디렉토리를 기본값으로 설정
}
if (isset($_GET['dir']) && in_array($_GET['dir'], $available_dirs)) {
    $dir = $_GET['dir'];
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
$document_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'hwp'];

// POST 요청으로 파일 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $file_to_delete = basename($_POST['delete_file']); // 보안을 위해 basename() 사용
    $file_path_to_delete = $full_dir_path . '/' . $file_to_delete;
    if (file_exists($file_path_to_delete)) {
        unlink($file_path_to_delete);
    }
    // 새로고침 시 중복 삭제를 방지하기 위해 리디렉션
    header("Location: " . $_SERVER['PHP_SELF'] . '?dir=' . urlencode($dir));
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
    <title>첨부파일 관리</title>
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

<h1 style="text-align: center;">첨부파일 관리 (<?php echo $dir; ?>)</h1>

<div style="text-align: center; margin-bottom: 20px;">
    <form action="" method="GET">
        <select name="dir" onchange="this.form.submit()">
            <?php foreach ($available_dirs as $d): ?>
                <option value="<?php echo htmlspecialchars($d); ?>" <?php if ($d === $dir) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($d); ?>
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
    if (is_dir($full_dir_path)) {
        $files = scandir($full_dir_path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $original_file = $file;
            $display_file = $file;

            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if ($extension === 'dat') {
                $display_file = pathinfo($file, PATHINFO_FILENAME);
                $extension = strtolower(pathinfo($display_file, PATHINFO_EXTENSION));
            }

            $is_image = in_array($extension, $image_extensions);
            $is_document = in_array($extension, $document_extensions);

            if ($is_image || $is_document) {
                $file_key = substr($display_file, 0, 6);
                $full_file_path = $full_dir_path . '/' . $original_file;
                $filesize_formatted = format_bytes(filesize($full_file_path));
                $file_creation_date = date("Y-m-d H:i:s", filectime($full_file_path));
                $key_exists = check_key_exists($file_key);

                echo "<li>";
                if ($is_image) {
                    $image_path = $dir . "/" . $display_file;
                    echo "<img src='" . htmlspecialchars($image_path) . "' alt='" . htmlspecialchars($display_file) . "'>";
                } else {
                    echo "<img src='https://cdn.icon-icons.com/icons2/1378/PNG/512/documentfile_92740.png' alt='file icon' style='width: 80px; height: 80px; object-fit: contain; margin: 10px;'>";
                }
                echo "<span class='filename'>" . htmlspecialchars($display_file) . " (키: " . htmlspecialchars($file_key) . ")<br><small>" . $filesize_formatted . " | " . $file_creation_date . "</small></span>";
                
                if ($key_exists) {
                    echo "<span class='status'>있음</span>";
                } else {
                    echo "<form method='POST' action='' class='status'>";
                    echo "<input type='hidden' name='delete_file' value='" . htmlspecialchars($original_file) . "'>";
                    echo "<button type='submit' class='delete-btn'>삭제</button>";
                    echo "</form>";
                }
                echo "</li>";
            }
        }
    } else {
        echo "<li>지정된 경로에 디렉토리가 없습니다.</li>";
    }
    $connection->close();
    ?>
</ul>

</body>
</html>