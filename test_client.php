
<?php

if (!isset($_GET['ip']) || !isset($_GET['cmd'])) {
    echo "用法: client.php?ip=<VPS_IP>&cmd=<message>";
    exit;
}

$dst_ip = $_GET['ip'];
$cmd = $_GET['cmd'];
$aes_key=$_GET['key'];

$run = "php /www/wwwroot/rjpanel.qooqle.date/icmp_function.php "
     . escapeshellarg($dst_ip) . " ". escapeshellarg($cmd). " ".escapeshellarg($aes_key);

$output = shell_exec($run);

echo "<pre>$output</pre>";

?>
