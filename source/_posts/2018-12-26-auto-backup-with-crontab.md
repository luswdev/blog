---
title: 使用 crontab 自動備份網站原始碼
tag: [note, crontab]
date: 2018-12-26 12:14:07
category: Note
cover: true
summary: 自動備份 hexo 中的 md 檔及主題配置，因為 hexo 生成網頁時不會上傳原始的 md 與主題資源
---
## shell script
透過 git 備份時，一定是使用下列指令

```bash
git add .
git commit -m 'log'
git push
```

而使用 shell script 可以讓我們一次執行一大串指令，因此我們來寫一個 shell script

```bash
#!/bin/zsh
nowTime="$(date +'%Y-%m-%d %H:%M:%S')"
echo "# Using crontab with auto.sh"
echo "# File in ~/Desktop/auto.sh"
echo "# Log  in ~/Desktop/cront.log"
echo "#"
echo "# Auto backup at ${nowTime}"
echo "# --------------------------------------------------\n"

cd /Users/username
cd $1
echo "Now at $(pwd)\n"

log="auto backup at "${nowTime}
git add .
git commit -m "$log"
result=$(git push site hexo_source_new 2>&1)

case $result in
    "Everything up-to-date")
        osascript -e 'display notification "Everything up-to-date." with title "Automatically backup" sound name "basso"'
        ;;
    *)
        osascript -e 'display notification "Done!" with title "Automatically backup" sound name "hero"'
        ;;
esac
echo ${result}
echo "Backup complete."
```

其中我們為了方便整理，在提交的紀錄上增加了時間；並且在 push 完根據結果有不同的通知

>此通知是基於 mac 上的 applescript 所寫的，在 linux 上會產生錯誤

## 建立 crontab
寫好 script shell 後，再來就是要定時的執行它。使用 crontab 可以在指定的時間，或是固定的區間內執行。

使用方式，輸入指令 `crontab -e`，接著會跳進 vim，寫入

```vim
@hourly chmod +x /Users/PATH_TO_YOUR.sh
@hourly /Users/PATH_TO_YOUR.sh PATH_TO_YOUR_SOURCE >> /Users/PATH_TO_YOUR.log 2>&1
```

這裡首先提升一次權限，並設定每一次整點都備份一次 `@hourly`，並將結果寫入 log 中。我們需要先建立一個 .log 檔：

```bash
touch /Users/PATH_TO_YOUR.log
```

當然這裡的所有路徑因人而異，檔名也無所謂，最後 `:wq` 存檔退出，安裝完畢

![](https://i.imgur.com/w3qGjus.png "大功告成")