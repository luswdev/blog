---
title: Perl 模組安裝
tags: perl
date: 2019-12-27 18:40:13
category: Note
summary: 學習如何簡單的安裝 perl 的模組。
---
## 使用自動安裝的環境
```shell
$ perl -MCPAN -e shell
```

- 類似於 `apt`、`brew` 的好用套件，輸入指令會進入此環境中，成功的話終端機會顯示此畫面:

```shell
$ cman>
```

## 安裝
```shell
$ cman> install Module::Name
```

- 直接下 install 指令 後面接模組名稱即可。
