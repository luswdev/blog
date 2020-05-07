---
title: Ubuntu 更改家目錄位置
date: 2018-11-01 10:18:59
tag: [Ubuntu]
category: Linux
---
之前為了解決我的問題，上網[^1]找到的解答，紀錄一下。

[^1]:[Partitioning/Home/Moving](https://help.ubuntu.com/community/Partitioning/Home/Moving)

## 1. 找到目標分割的 UUID

- command:

```shell 
sudo blkid
```

- result:

```shell 
/dev/sda1: UUID="1a2b3c4d-1a2b-1a2b-1a2b-1a2b3c4d5e6f" TYPE="ext4" PARTUUID="12345678-01"
```

- 其中 `UUID="1a2b3c4d-1a2b-1a2b-1a2b-1a2b3c4d5e6f"` 為此分割的 UUID，記著。

---
## 2. 設定 fstab

fstab 是用來設定開機時哪些分割需要被載入，接下來會將原本的 fstab 備份，並於檔名中加入當前日期，接著修改該文件。

- 備份 (Duplicate)

```shell 
sudo cp /etc/fstab /etc/fstab.$(date +%Y-%m-%d)
```

- 比較兩檔案

```shell 
cmp /etc/fstab /etc/fstab.$(date +%Y-%m-%d)
```

- 開啟文字編輯器修改 fstab

```shell 
sudo gedit /etc/fstab 
```

(gedit 可替換成任何文字編輯器，如 vim)
加入以下文字：

``` 
# (identifier)  (location, eg sda5)   (format, eg ext3 or ext4)      (some settings) 
UUID=1a2b3c4d-1a2b-1a2b-1a2b-1a2b3c4d5e6f   /media/home    ext4          defaults       0       2 
```
記得填入自己的 UUID，及正確的格式。

存檔，關閉編輯器。

- 建立新資料夾

```shell 
sudo mkdir /media/home
```

此資料夾是為了掛載新的分割，需與上一步驟填入的相同。

- 重開機。

---
## 3. 複製原本的 home 到新分割

```shell 
sudo rsync -aXS --progress --exclude='/*/.gvfs' /home/. /media/home/.
```

- 檢查是否全複製過去了

```shell 
sudo diff -r /home /media/home -x ".gvfs/*"
```

---
## 4. 再次修改 fstab

- 開啟

```shell 
sudo gedit /etc/fstab
```

- 修改上次新增的部分，將 `/media/home` 改成 `default`:

```shell 
# (identifier)  (location, eg sda5)   (format, eg ext3 or ext4)      (some settings) 
UUID=????????   /home    ext3          defaults       0       2
```

---
## 5. 備份舊的家目錄

```shell 
cd / && sudo mv /home /old_home && sudo mkdir /home
```

---
## 6. 重開機，大功告成

1. fstab 將新分割掛載在 /home 上
2. 原本的 /home 改名成 /old_home

---
## 刪除 old_home

如果磁碟空間不夠，或是想清理磁碟的話，可透過以下指令刪除。

```shell 
cd /
sudo rm -rI /old_home
```




