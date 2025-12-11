<?php
date_default_timezone_set("Asia/Taipei");







$host = "localhost";
$user = "kongzi";
$pass = "PW";
$db   = "kongzi";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("❌ MySQL 連線失敗: " . mysqli_connect_error());
}
?>
