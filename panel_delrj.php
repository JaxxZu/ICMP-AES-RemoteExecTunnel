<?php
include 'panel_login.php';

include 'sql_connect.php';

if (!isset($_GET['ip'])) {
    die("缺少 IP");
}

$ip = $_GET['ip'];

// 將 IPv4 字串 → binary(4) ，因為你資料庫存的是 binary
$ip_bin = inet_pton($ip);

if ($ip_bin === false) {
    die("IP 格式錯誤");
}

// 使用 prepared statement 更新 is_deleted
$sql = "UPDATE botnet SET is_deleted = 1 WHERE ip = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $ip_bin);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// 回到主頁
header("Location: panel.php");
exit;
?>
