---
title: 使用 ANSI 跳脫碼印出有色文字
date: 2019-12-26 22:50:57
tags: [C/C++, ANSI]
category: C/C++
summary: 在 C 中使用 ANSI 跳脫碼，讓 printf 印出有顏色的文字
---

## ANSI 跳脫碼
ANSI 跳脫碼，即 ASCII Escape Code，標準 CSI 格式為

```c
CSI n1 [;n2 [;...]] m
```

- `n1` 通常填入 `\x1b`，在 ASCII 表中 `0x1b` 代表著 escape。
- `n1`、`n2` 為 **SGR (Select Graphic Rendition)**，可參考表格對應相對的值。[^1]

[^1]:[[Linux C] ANSI逃脫碼與printf顏色教學](http://naeilproj.blogspot.com/2015/08/linux-c-c-printf.html)

## 顏色輸出
### 範例：粗紅體

```c
\x1b[;31;1m
```

- SGR 30~37 代表著顏色，可參照此表格[^2]
![](https://i.imgur.com/8HEFwxZ.png)
- 後面的 1 代表粗體，不寫則為一般字型；通常一般的終端機會將粗體顯示成較亮的顏色，而非粗體。
- 有些終端機提供用高位的數字指定較亮的顏色，90-97 及 100-107，如下圖
![](https://i.imgur.com/mEP8AjU.png)

### xterm-256color
- 使用 8 位元的 SGR，進而提供 256 色的輸出。
![](https://i.imgur.com/hjjHfve.png)

[^2]:[ANSI跳脫序列](https://zh.wikipedia.org/wiki/ANSI%E8%BD%AC%E4%B9%89%E5%BA%8F%E5%88%97)