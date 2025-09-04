<?php
// 이 파일은 세션이 이미 시작되었다고 가정합니다.
// require_once 'config_init.php'; 가 필요하다면 호출하는 파일에서 이미 처리되어야 합니다.

$current_page = basename($_SERVER['PHP_SELF']);

$links = [
    'index.php' => '홈',
    'missing_file_list.php' => '미관리 첨부파일',
    'manage_attachments.php' => 'DB파일관리',
    'admin_list.php' => '관리자 목록'
];

?>
<div style="text-align: right; padding: 10px; border-bottom: 1px solid #ccc; margin-bottom: 20px;">
    <?php foreach ($links as $url => $title): ?>
        <?php if ($current_page === $url): ?>
            <span style="margin-left: 15px; font-weight: bold;"><?php echo $title; ?></span>
        <?php else: ?>
            <a href="<?php echo $url; ?>" style="margin-left: 15px;"><?php echo $title; ?></a>
        <?php endif; ?>
    <?php endforeach; ?>

    <span style="margin-left: 30px;"></span> <!-- Separator -->

    <?php if (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) : ?>
        <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>님, 환영합니다.
    <?php else: ?>
        <strong><?php echo htmlspecialchars($_SESSION['user_email']); ?></strong>님, 환영합니다.
    <?php endif; ?>
    <a href="logout.php" style="margin-left: 15px;">로그아웃</a>
</div>