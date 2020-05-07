---
title: 如何查看 Linux 被佔用的 port
tags: [Linux, lsof, netstat]
date: 2020-04-08 15:08:31
category: Linux
summary: 介紹幾種在 Linux CLI 下查看哪些人佔用哪幾個 port
top: true
---
> 當我們下 `hexo s` 後，預設將會開啟 `:4000`，但如果 `:4000` 被佔用就會報錯；為解決此問題，我們必須知道是哪個行程佔用，並 `kill` 它。
以下我們介紹 2 種 Linux 的指令。

## lsof
lsof (List Open Files)[^1]，可以列出所有被行程打開的檔案。可以利用 `-i` 來查找所有網路連線；於是

```shell
$ lsof -i
```

將會列出所有使用 port 的行程。而有時候有些 port 會有別名，為了方便找查，我們加上 `-P` (列出實際的 port number)；於是

```shell
$ lsof -i -P
```

最後為了簡化結果，我們將原本的結果傳給 `grep`

```shell
$ lsof -i -P | grep :4000
```

{% alert info %}
`:4000` 可任意改成想要的 port number，如 `:1234`
{% endalert %}

[^1]:[Linux 列出行程開啟的檔案，lsof 指令用法教學與範例](https://blog.gtwang.org/linux/linux-lsof-command-list-open-files-tutorial-examples/)

## netstat
netstat 可以用來查看各種網路狀態，一樣可以拿來查找被佔用的 port。

```shell
$ netstat -tulpn
```

其中：
- `-t` 代表找走 `TCP` 協定的
- `-u` 代表找走 `UDP` 協定的
- `-l` 代表找 `LISTEN` 的 socket
- `-n` 代表顯示硬體名稱，`-p` 代表顯示 PID。[^2]

我們一樣可以用 `grep` 來協助尋找

```shell
$ netstat -tulpn | grep :4000
```

[^2]:[netstat Command Usage on Linux](https://geekflare.com/netstat/)

---
- Reference
    - [3 種 Linux 查看 port 被程式佔用的方法](https://www.opencli.com/linux/3-way-check-linux-listen-port)

