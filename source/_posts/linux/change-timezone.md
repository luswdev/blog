---
title: Ubuntu 更換時區指令
tags: [Linux, timezone, timedatectl]
date: 2020-05-07 21:51:50
category: Linux
---

Linux 的預設時時區是 `UTC`，如果在安裝的時候沒有更改，或是租 VPS 的時候就需要手動修改。
這裡介紹 Ubuntu 內建的指令 `timedatectl`。

{% alert info %}
這是 Ubuntu 的指令，若是你的 Linux 發行版不是 Ubuntu 則不適用。
{% endalert %}

## timedatectl
這是一個可以顯示、設定及修改系統時區的指令。

### 顯示所有時區
可以用 `list-timezones` 來列出所有時區

```bash
$ timedatectl list-timezones
```

- 結果會像是這樣：
```bash
Aferica/...
...
```

可以結合 `grep` 來搜尋想要的結果，如我們只列出亞洲的：

```bash
$ timedatectl list-timezones | grep Asia
```

### 顯示目前時區
不加上任何設定即可顯示目前設定的時區

```bash
$ timedatectl
```

- 結果會像是這樣：

```bash	
Local time: Thu 2020-05-07 22:08:01 CST
Universal time: Thu 2020-05-07 14:08:01 UST
RTC time: Thu 2020-05-07 22:08:01
Time zone: Asia/Taipei (CST +8000)
System clock synchronized: yes
NTP service: active
RTC in local TZ: no
```

加上 `status` 可以顯示設定

```bash
$ timedatectl status
```

- 結果會像是這樣：

```bash
Timezone=Asia/Taipei
LocalRTC=no
CanNTP=yes
NTP=yes
NTPSynchronized=yes
TimeUSec=Thu 2020-05-07 22:08:01 CST
RTCTimeUSec=Thu 2020-05-07 22:08:01 CTS
```

### 改變時區
使用 `set-timezone` 可以修改系統設定的時區

```bash
$ timedatectl set-timezone Zone
```

`Zone` 需要修改成想要設定的時區，如 `Asia/Taipei`。

修改後可以使用 `show` 來看是否修改成功。
```bash
$ timedatectl show
```
