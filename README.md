# ICMP-AES-RemoteExecTunnel
A Secure AES-Encrypted ICMP Tunnel for Remote Command Execution and Botnet Control Research  
基於 AES 加密 ICMP 隧道的遠端指令執行與節點管理研究框架

> ## ⚠️Warning
> This project "ICMP-AES-RemoteExecTunnel" is intended solely for cybersecurity research, academic study, protocol analysis and authorized network testing.
> 
> Do NOT use this tool on systems, networks or services without explicit authorization.
> 
> Any misuse is the sole responsibility of the user. The author assumes no liability for damages, misuse, or legal consequences arising from the use of this software.

## 系統組成文件
### `rj.c`
被控端（肉雞） 的核心原始碼，在被控端電腦上編譯並運行  
監聽來自主控端的 ICMP 加密封包  
使用 AES 演算法解密封包內容，提取指令並執行  
使用ICMP 加密封包回應給主控端  

### `icmp_function.php`
封裝 ICMP 封包，包括建立 Raw Socket、建構 ICMP 標頭、計算校驗和（Checksum）的程式碼  
提供Web Panel調用  

### `panel.php` 
主控制台  
顯示目前已上線的肉雞列表、狀態，下發群控指令等  


### `panel_attack.php`
群控指令發送介面(接口)
管理員在將需要執行的指令POST方式傳送給本頁面，本腳本會調用 icmp_function.php 將指令加密並封裝成 ICMP 封包發送給指定的被控端
而後，接收並顯示被控端回傳的回應

### `panel_addrj.php`
新增新的被控端資料加入資料庫進行管理

### `panel_delrj.php`

從資料庫中移除被控端

### `panel_download_rj.c.php`
動態生成被控端代碼
先讀取 rj.c 的內容，將其中的 AES KEY 變量自動替換成當前控制端的AES KEY，然後讓使用者下載   
使用者下載後無需手動修改程式碼即可直接編譯使用  

### `keep_alive.php`



用途：心跳維持/狀態檢查。

功能：可能用於定期 Ping 受害者列表中的 IP，以確認它們是否仍然在線（Alive），並更新資料庫中的狀態。



### `test_client.php`



用途：測試用客戶端。

功能：這是一個用 PHP 寫的模擬客戶端。開發者在不編譯 C 代碼 (rj.c) 的情況下，可以用這個腳本來測試伺服器端的 icmp_function.php 邏輯是否正常（例如測試加密解密是否匹配）。

### `sql_connect.php`
存放 MySQL 資料庫的主機、使用者名稱、密碼和資料庫名稱配置
### `panel_login.php`
提供管理員認證介面，防止未授權人員存取Web Panel  



