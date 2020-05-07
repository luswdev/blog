---
title: "XV6 - OS Interfaces"
date: 2018-07-09 12:53:37 +0800
tag: [XV6, kernel]
category: XV6
---
- OS的工作：有效率的在電腦上執行數個程式。
- Kernel：提供 process 服務
- Process：執行中的程式，擁有記憶體存放指令、資料、及堆疊。
    - 指令：實現程式的運算。
    - 資料：運算中所用到的變數。
    - 堆疊：組織 procedure 呼叫。

1. 當一個 process 需要調用 kernel 的服務時，就會調用一個**procedure 呼叫**至OS介面。
2. procedure 即為 **system call**
3. system call 進入 kernel，kernel 回傳其服務。

![System call sample](https://i.imgur.com/N2chcG4.png "A kernel and two user processes.") 
- Kernel 使用 CPU 的硬體保護機制去確保 process 只會在 user space 中執行，及只存取自己的記憶體（使用特權 privilege 機制）。

---
## Process 及記憶體
- Time-share：透明的在等待執行的 process 中切換可用的CPU。
    - 當一個 process未執行時，XV6 保存其 CPU 暫存器，當下次要執行時再恢復。
    - Kernel 將一個 process 與其 pid（process identifier）連結。
- `fork()`：一個 system call 用來新增子 process。

子 process 與父 porcess 擁有同樣的記憶體**內容**，但是在不同的記憶體及暫存器上執行，所以在其中一個process 中改變一個變數值並不會影響另一個。


- `exit()`：用來結束子 process。
- `wait()`：在主 process 中使用；當子 process 結束後，才繼續執行主 process（通常搭配 `exit()` 使用）。

---
## I/O 及檔案描述符
- 檔案描述符 file descriptor：一個小整數代表一個 process 可能會讀取或寫入的kernel-managed 物件；XV6 kernel 使用檔案描述符做為一個 pre-process table 的索引。
    - 0 standard input
    - 1 standard output
    - 2 standard error
- Shell 確保上述三個描述符每次都會被打開。

```c
// read something from file descriptor to "buf"
read(fd, buf, n);
// write something from "buf" to file descriptor
write(fd, buf, n);
// both read and write will return n of how much its read/write
```

---
## Pipes
- 如同一條水管，pipes 的兩端連接不同的 process，其中一端寫入資料，其中一端讀取其資料。也就是說，pipes 提供使兩個不同的 process 互相溝通的方法。

```bash
# sample of pipes
echo hello world | wc
# another way call "temporary files"
echo hello world >/tmp/xyz; wc </tmp/xyz
```
- 比較
    1. Pipes 會自我清理。
    2. Pipes 可以任意長。
    3. Pipes 允許同步，兩個 process 可以利用一條 pipe 進行訊息溝通。

--- 
## 檔案系統
- 一個文件即為位元組陣列。
- 一個目錄中包含一些檔案或其他的目錄。
- 目錄的結構為樹，且有一個**根目錄 /**（例如：/a/b/c）。
- 任何不從根目錄開始的路徑稱作 process 的當前目錄。

```bash
# create a new director
mkdir("/dir");
# open with 0_CREATE flag create a new file
open("/dir/file",0_CREARTE|0_WRONLY);
```
- inode[^1]：儲存一個檔案的基本訊息，如檔案的權限、擁有者等等。
- 每個 inode 有一組唯一的號碼，檔案系統用此號碼來識別文件。

[^1]:[理解inode](http://www.ruanyifeng.com/blog/2011/12/inode.html?utm_source=tool.lu)