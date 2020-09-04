---
title: Apache 將網站架設於子目錄
tags: [Apache, Linux]
date: 2020-09-04 21:11:33
category: Linux
---
一般而言，如果要在不同目錄架設網站的話，需要使用 virtual host，但這是在子網域下的設定。

```
example.com
sub.example.com
```

如果我們需要將網站建立在子目錄而不將網頁資料放在同一個資料夾裡呢？

```
example.com
example.com/sub/
```

## a2ensite
首先建立一個 apache site config，名字取名跟網站相關及可，放置於 `/etc/apache2/site-available/`。

```bash
sudo touch /etc/apache2/site-available/example.conf
```

接著輸入以下指令啟用設定欓
```bash
a2ensite example.conf
```

## .conf
下一步是撰寫設定欓

```apache
Alias /example /path/to/source
<Directory /path/to/source>
        Order allow,deny
        allow from all
        Allowoverride All
</Directory>
```

如此一來，此資源將會被導向 `/example` 

### 重啟
最後請重啟 apache

```bash
sudo service apache2 restart
```