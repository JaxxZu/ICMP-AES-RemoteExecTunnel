<?php
session_start();
date_default_timezone_set("Asia/Taipei");

// ===== 設定你的密碼 =====
$PANEL_PASSWORD = "PW";  // 自己改

// ===== Cloudflare Turnstile Secret Key =====
$TURNSTILE_SECRET = "CF-KEY";

// ===== 登入動作 =====
if (isset($_POST['panel_pass'])) {

    // === Cloudflare Turnstile 後端驗證 ===
    $token = $_POST['cf-turnstile-response'] ?? "";

    $verifyURL = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
    $data = [
        'secret'   => $TURNSTILE_SECRET,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data)
        ]
    ];

    $result = file_get_contents($verifyURL, false, stream_context_create($options));
    $resultData = json_decode($result, true);

    if (!$resultData['success']) {
        $error = "驗證碼失敗，請再試一次";
    } else {

        // === 驗證碼通過後再比對密碼 ===
        if ($_POST['panel_pass'] === $PANEL_PASSWORD) {
            $_SESSION['panel_login'] = "OK";

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "密碼錯誤";
        }
    }
}

// ===== 尚未登入，顯示密碼輸入頁 =====
if (!isset($_SESSION['panel_login']) || $_SESSION['panel_login'] !== "OK") {
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>管理員登入</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Cloudflare Turnstile -->
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-4">

            <div class="card shadow">
                <div class="card-body p-4">

                    <h3 class="mb-4 text-center fw-bold">輸入密碼</h3>

                    <?php if (!empty($error)) { ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php } ?>

                    <form method="POST">

                        <input type="password" 
                               name="panel_pass" 
                               class="form-control form-control-lg mb-3"
                               placeholder="請輸入密碼" 
                               required>

                        <!-- Cloudflare Turnstile Captcha -->
                        <div class="cf-turnstile mb-3"
                             data-sitekey="0x4AAAAAACFVbSqYa-mxI7IS">
                        </div>

                        <button class="btn btn-primary w-100" type="submit">
                            登入
                        </button>

                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>

<?php
exit;
}
// === 注意：以下會自動回到你的原本頁面 ===
?>
