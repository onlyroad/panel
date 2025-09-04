<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config_init.php';

header('Content-Type: application/json');
session_start();

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['uid'])) {
    echo json_encode(['status' => 'error', 'message' => 'UID가 없습니다.']);
    exit;
}

$uid = $data['uid'];
$email = isset($data['email']) ? $data['email'] : null;
$name = isset($data['name']) ? $data['name'] : null;
$photo = isset($data['photo']) ? $data['photo'] : null;

// 1. 사용자가 승인되었는지 확인 (confirm_yn = 'Y')
$stmt = $connection->prepare("SELECT * FROM admin_user WHERE admin_uid = ? AND confirm_yn = 'Y'");
$stmt->bind_param("s", $uid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // 사용자가 존재하고 승인된 경우
    $user = $result->fetch_assoc();

    // 세션 생성
    $_SESSION['user_uid'] = $user['admin_uid'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];

    echo json_encode(['status' => 'success']);
} else {
    // 사용자가 없거나 승인되지 않은 경우, 먼저 사용자가 존재하는지 확인
    $stmt_check = $connection->prepare("SELECT admin_uid FROM admin_user WHERE admin_uid = ?");
    $stmt_check->bind_param("s", $uid);
    $stmt_check->execute();
    $check_result = $stmt_check->get_result();

    if ($check_result->num_rows > 0) {
        // 사용자는 존재하지만 confirm_yn != 'Y' 이므로 미인증 처리
        echo json_encode(['status' => 'unauthenticated']);
    } else {
        // 사용자가 아예 존재하지 않으므로 새로 추가
        // TODO: INSERT 쿼리의 컬럼들이 실제 DB와 일치하는지 확인해주세요.
        $stmt_insert = $connection->prepare("INSERT INTO admin_user (admin_uid, email, name, photo, use_yn, confirm_yn,creator,changer,idate, udate) VALUES (?, ?, ?, ?, 'Y', 'N', ?, ?, CONVERT_TZ(UTC_TIMESTAMP(),'+00:00','+09:00'), CONVERT_TZ(UTC_TIMESTAMP(),'+00:00','+09:00'))");
        $stmt_insert->bind_param("ssssss", $uid, $email, $name, $photo, $uid, $uid);
        
        if ($stmt_insert->execute()) {
            echo json_encode(['status' => 'unauthenticated']);
        } else {
            echo json_encode(['status' => 'error', 'message' => '사용자 등록에 실패했습니다: ' . $stmt_insert->error]);
        }
        $stmt_insert->close();
    }
    $stmt_check->close();
}

$stmt->close();
$connection->close();
?>