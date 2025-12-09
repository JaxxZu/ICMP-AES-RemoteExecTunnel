<?php
include 'panel_login.php';


$key= $_GET['key'] ;
$ip= $_GET['ip'] ;


if ($ip==$key){exit;}

$templatePath = 'rj.c';

if (!file_exists($templatePath)) {
    http_response_code(404);
    die("File not found.");
}

$content = file_get_contents($templatePath);

$modifiedContent = str_replace('<KEY>', $key, $content);


$downloadFileName = 'rj_' . $ip . '.c';

if (ob_get_level()) {
    ob_end_clean();
}

// 指定內容類型為通用的二進制流 (或根據實際類型設定 text/plain, application/json 等)
header('Content-Type: application/octet-stream');
// 關鍵：Content-Disposition attachment 強制瀏覽器彈出下載視窗
header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
// 告訴瀏覽器文件大小 (選填，但推薦)
header('Content-Length: ' . strlen($modifiedContent));
// 禁止緩存
header('Cache-Control: must-revalidate');
header('Pragma: public');

// 6. 輸出修改後的內容
echo $modifiedContent;
exit;
?>