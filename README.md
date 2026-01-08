# ICMP-AES-RemoteExecTunnel
A Secure AES-Encrypted ICMP Tunnel for Remote Command Execution and Botnet Control Research  
基於 AES 對稱加密的 ICMP 隧道的遠端指令執行與節點管理框架  

> ## ⚠️Warning
> This project "ICMP-AES-RemoteExecTunnel" is intended solely for cybersecurity research, academic study, protocol analysis and authorized network testing.
> 
> Do NOT use this tool on systems, networks or services without explicit authorization.
> 
> Any misuse is the sole responsibility of the user. The author assumes no liability for damages, misuse, or legal consequences arising from the use of this software.
## 特性

- AES 對稱加密保障 ICMP 封包傳輸安全
- 支援遠端指令執行
- PHP Web 控制面板，方便管理上線節點
- 心跳機制，實時追蹤節點在線狀態
- 動態生成被控端程式碼，自動注入自訂 AES Key

## 系統結構
<img width="100%" src="https://github.com/user-attachments/assets/1b678210-4db2-494d-be56-126224bb4ae8" />

| 檔案名稱                  | 功能說明                                                                 |
|---------------------------|--------------------------------------------------------------------------|
| `rj.c`                    | 被控端核心程式（C語言），監聽加密 ICMP 封包、解密執行指令並回應          |
| `icmp_function.php`       | 封裝 ICMP 封包建構、Raw Socket 操作及校驗和計算                          |
| `panel.php`               | 主控制面板，顯示上線節點並下發指令                                       |
| `panel_attack.php`        | 群控指令發送介面，負責加密並透過 ICMP 發送指令                           |
| `panel_addrj.php`         | 新增被控端至資料庫                                                       |
| `panel_delrj.php`         | 從資料庫移除被控端                                                       |
| `panel_download_rj.c.php` | 動態生成並替換 AES Key 的被控端程式碼，供下載                            |
| `keep_alive.php`          | 心跳檢查腳本（需配合 Crontab 定時執行）                                  |
| `panel_login.php`         | 管理員登入驗證介面（支援 Cloudflare Turnstile 驗證碼）                   |
| `sql_connect.php`         | 資料庫連線配置                                                           |
## 主控端環境
Debian 11  
Nginx 1.24.0  
MariaDB 10.11   
PHP 8.3

## 主控端配置

修改`keep_alive.php`、`panel_attack.php`中的`/www/wwwroot/rjpanel.qooqle.date`路徑為實際路徑   
修改`panel_login.php`中的管理員密碼、Cloudflare Turnstile Secret Key (不需要驗證碼的話可以修改`if (!$resultData['success'])`判斷 
)  
修改`sql_connect.php`到真實資料庫帳密

資料庫：
```sql
CREATE TABLE `botnet` (
 `ip` binary(4) NOT NULL,
 `aes_key` binary(32) NOT NULL,
 `last_alive_time` timestamp NULL DEFAULT NULL,
 `is_deleted` tinyint(1) NOT NULL,
 PRIMARY KEY (`ip`),
 UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
```



## 被控端部署
安裝套件：  
```bash
apt update -y
apt install gcc  screen  openssl libssl-dev -y
```  

安裝bombardier：  
```bash
wget https://github.com/codesenberg/bombardier/releases/latest/download/bombardier-linux-amd64
chmod +x bombardier-linux-amd64
sudo mv bombardier-linux-amd64 /usr/local/bin/bombardier
```  

上傳受控程式並編譯：
```bash
gcc rj.c -lcrypto -lssl -o rj
```  

使用screen保持運行：
```bash
screen -S xxx
screen -r xxx
```
## 注意事項與
主控端與被控端均需 root 權限才能操作 Raw Socket

## 結課論文
[下載](./essay_for_public.pdf)
