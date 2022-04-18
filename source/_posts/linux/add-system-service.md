---
title: 在 Linux 新增一個 Service
tags: [Linux, systemctl, journalctl, systemd, journald]
date: 2022-04-16 11:11:17
category: Linux
---

## 建立 Unit 檔案

首先建立一個 system service unit 檔案，格式如下

```bash
[Unit]
Description=service discription

[Service]
Type=simple
ExecStart=execute path/command
Restart=always

[Install]
WantedBy=multi-user.target
```

並存放於 `/usr/lib/systemd/system/service-name.service`（自行修改 service name）

- `ExecStart` 為該執行的程式或是指令，如 `php /path/to/main.php`
- 如果有需要先執行的指令，可以使用 `ExecStartPre`，如 `ExecStartPre=mkdir /path/to/log/`
- 如果有需要**後**執行的指令，可以使用 `ExecStartPost`，如 `ExecStartPost=rm /path/to/some.txt`

## 安裝 service

安裝的意思為，為 service 設定權限，在何種開機模式下會自動執行，如上述範例為 multi-user，其餘各種開機模式可見[此篇](https://blog.lusw.dev/posts/linux/init-number.html)

- 指令

```bash
systemctl install service-name.service
```

- 也可以先執行確認結果

```bash
systemctl start service-name.service
```

## 確認 Log

若是要確認 system service 的 log，則需要使用 journald 這個 service

```bash
journalctl -fu service-name
```
