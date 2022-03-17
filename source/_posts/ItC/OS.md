---
title: "計概 - 作業系統"
date: 2018-02-08 10:18:22 +0800
tag: 計算機概論
category: 計算機概論
---
##  作業系統的角色
### 記憶體、程序與CPU管理
- 持續追蹤什麼程式在記憶體，及其位置。
- 程序 process 為執行中的程式，追蹤程序的進展及記錄。
- CPU 排程，為決定哪個程序可以執行。
---
##  記憶體管理
- 邏輯位址：相對位址。
- 位址聯繫 address binding：邏輯位址對應到實體位址的過程。

### 單一連續記憶體管理
記憶體中只有作業系統及一個要執行的程式。
### 分割記憶體管理
記憶體中允許多個程序。

- 固定分割技術：分割區不需要相同大小，每個分割區的大小是固定的。
- 動態分割技術：程式載入至分割區後，多餘的空間會分割成新的分割區。

### 分頁記憶體管理
記憶體分成固定大小的頁框 frame，程序分成同樣大小的分頁 page。

---
##  程序管理
- 程序狀態：new、ready、running、waiting、terminated。
- 程序控制區塊 PCB：儲存有關程序的各項資訊。

---
##  CPU排程
- 先佔式排程：程序有可能在尚未執行完就被強制移出CPU。
- 非先佔式排程：程序不會在尚未執行完就被強制移出CPU。

### 先到先服務 FCFS（非先佔式）
### 最短工作優先 SJN（非先佔式）
### 循環輪流（先佔式）
建立一個時間片段 time slice，使用CPU時間超過時間片段的程序必須先移出，直到下一次輪到此程序時才能繼續使CPU。