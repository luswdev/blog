---
layout: post
title: "XV6 Appendix A PC 硬體"
date: 2018-08-27 14:14:57 +0800
tag: [xv6 ,暫存器 ,x86]
---
## 通用暫存器
| Name   | Function          |
| -------| ------------------|
| `%eax` | Accumulator 累加器 |
| `%ebx` | Based 基址暫存器 |
| `%ecx` | Counter 計數器 |
| `%edx` | Data 數據暫存器 |
| `%edi` | Destination Index 目的地址指針暫存器 |
| `%esi` | Source Index 源地址指針暫存器 |
| `%ebp` | Based 基址指針暫存器 |
| `%esp` | Stack 堆疊指針暫存器 |

- **程式計數器** `%eip`：Instruction Pointer 指令指針暫存器 


`e` 指的是 extended，為 16 位元暫存器的 32 位元擴展。
同時 `%ax` 為 `%eax` 的低位，以此類推；修改任意一個都會影響另一個。
另外，前四個暫存器有另一套低位別名：`%al`、`%ah`...etc，低八位為 `%al`，高八位為 `%ah`。

<!-- more -->

![eax](https://i.imgur.com/Sf9vGTD.png "Layout of eax.")

---
## 控制暫存器

| Name   | Function |
| -------| ---------|
| `%cr0` | 包含了兩個標誌：|
|        | 0 位（PE）用於啟動 **protected mode**，若 PE = 1，則啟動 protected mode，為 0 則在 **real mode** 下運行。 |
|        | 31 位（PG）為分頁允許位，用來表示分頁硬體是否允許作業 |
| `%cr1` | 保留 |
| `%cr2` | 保存最後一次出現夜故障時的32位線性地址 |
| `%cr3` | 保存頁目錄表的物理地址 |
    
![cr](https://i.imgur.com/iEAZDAK.png "Layout of CR.")
   
---    
## 段暫存器

| Name  | Function      | Name  | Function      |
| ------| --------------| ------| --------------|
| `%cs` | Code Segment  | `%es` | extra Segment |
| `%ds` | Data Segment  | `%fs` | extra Segment |
| `%fs` | Stack Segment | `%gs` | extra Segment |

---
## 其他
- `%gdtr` 全域段描述符表、`%ldtr` 區域段描述符表
（參考[Appendix B](https://omuskywalker.github.io/hexo/2018/08/27/appendix-b/#gdt))
- `%eflags` **標誌暫存器**[^1]

    ![eflags](https://i.imgur.com/1nmVnUZ.png "Layout of eflags.")
    - 灰色為保留位，值不變[^2]


| Name | Function              | Name | Function                       |
| ---- | --------------------- | ---- | ------------------------------ |
| CF   | Carry Flag            | IOPL | I/O Privilege Level field      |
| PF   | Parity Flag           | NT   | Nested Task flag               |
| AF   | Auxiliary Carry Flag  | RF   | Resume Flag                    |
| ZF   | Zero Flag             | VM   | Virtual-8086 Mode Flag         |
| SF   | Sign Flag             | AC   | Alignment Check Flag           |
| TF   | Trap Flag             | VIF  | Virtual Interrupt Flag         |
| IF   | Interrupt Enable Flag | VIP  | Virtual Interrupt Pending flag |
| DF   | Direction Flag        | ID   | Identification Flag            |
| OF   | Overflow Flag         | |

[^1]:[X86 彙編/X86 架構](https://zh.wikibooks.org/zh-tw/X86_汇编/X86_架构#16-位通用暂存器_(通存器_GPR)
[^2]:[x86—EFLAGS寄存器详解](https://blog.csdn.net/jn1158359135/article/details/7761011)


