---
title: SED 大量檔案取代
tags: [Linux, bash, sed, find]
date: 2023-04-04 01:07:11
category: Linux
---

# SED 基本操作

- 取代單檔 [^1]

[^1]: [Linux sed 指令 (hy-star.com.tw)](https://www.hy-star.com.tw/tech/linux/sed/sed.html)

```bash
sed -e s/before/after/g target.file > target.file
```

# 多檔取代

直覺上我們會用 wildcard 取代上面的 `target.file`，例如改成 *.file 似乎就能完成

```bash
sed -e s/before/after/g *.file > *.file
```

但是這樣是不能一次的多檔快速取代的，因為要寫入的檔案沒辦法用 wildcard，所以必須搭配 find [^2] 使用。

```bash
find ./ -name '*.file' -exec sed -i "s/before/after/g" {} \;
```

[^2]: [regex - Changing all occurrences in a folder - Stack Overflow](https://stackoverflow.com/questions/905144/changing-all-occurrences-in-a-folder)