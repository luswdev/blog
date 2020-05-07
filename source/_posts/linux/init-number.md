---
title: Linux 的 "init" 行程
tags: [Linux, kernel, init]
date: 2020-04-08 14:18:01
category: Linux
summary: 解釋 Linux 下 init 0~6 的功能 
---
## init 行程
init 行程（process/tas）是 Linux 內核下的第一個行程，內核會在初始化完硬體後建立該行程。
>如[XV6 啟動流程](https://blog.lusw.dev/starting-xv6/#toc-heading-3) 中提到的 `userinit()`

正常來說 init 應該被放在 `/sbin/init` 中，如果內核找不到，會試著在 `/bin/sh` 中尋找，若都失敗則將導致**啟動失敗**。

## init 等級
| 等級 | 用途 |
|-----|-----|
| 0   | 關機 |
| 1   | single user mode |
| 6   | 重新啟動 |

- 對於 2 ~ 5，不同的發行版有不同的解釋，大部分的系統中：
    - 3 代表正常啟動 CLI
    - 5 代表正常啟動 GUI 

---

- Reference
    - [linux 下的init 0，1，2，3，4，5，6知识介绍](https://blog.csdn.net/cougar_mountain/article/details/9798191)
    - [init演化歷程 – [轉貼] 淺析 Linux 初始化 init 系統，第 1 部分: sysvinit](http://felix-lin.com/linux/init%E6%BC%94%E5%8C%96%E6%AD%B7%E7%A8%8B-%E8%BD%89%E8%B2%BC-%E6%B7%BA%E6%9E%90-linux-%E5%88%9D%E5%A7%8B%E5%8C%96-init-%E7%B3%BB%E7%B5%B1%EF%BC%8C%E7%AC%AC-1-%E9%83%A8%E5%88%86-sysvinit/)
