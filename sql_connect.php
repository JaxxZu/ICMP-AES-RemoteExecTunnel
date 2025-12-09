<?php







$host = "localhost";
$user = "kongzi";
$pass = "cNDnbbBfZHz2mMT2";
$db   = "kongzi";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("❌ MySQL 連線失敗: " . mysqli_connect_error());
}
?>
