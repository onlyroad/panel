<?php
// 세션을 시작합니다.
session_start();

// 모든 세션 변수를 비웁니다.
$_SESSION = array();

// 세션을 파기합니다.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 로그인 페이지로 리디렉션합니다.
header("Location: login.php");
exit;
?>