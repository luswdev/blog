---
layout: post
title: "XV6 Appendix B The Boot loader"
date: 2018-08-27 14:15:00 +0800
comments: true
categories: xv6 
tag: [xv6, boot, bootstrap, elf, 函數指標, c,  asm]
copyright: true
description: 詳解 xv6 的開機流程，及 kernel 啟動方式。
images: https://i.imgur.com/DMQi16S.png
---
- x86 開機時，會先呼叫位於主機板上的 BIOS。
- BIOS 的工作：準備硬體，將控制權轉給 OS (xv6)。
- 準確的說，控制權轉給 boot sector，位於開機碟的第一個磁碟扇區(512 byte)。
- Boot sector 包含 boot loader—負責將 kernel 載入記憶體。
- BIOS 將 boot sector 寫入 0x7c00 的位置，並跳至該位址(透過設定暫存器 %ip)。
- xv6 boot loader 包含兩個檔案：*bootasm.s*、*bootmain.c*。

## Code: Assembly bootstrap
### File: bootasm.s

```x86asm
#include "asm.h"
#include "memlayout.h"
#include "mmu.h"

# Start the first CPU: switch to 32-bit protected mode, jump into C.
# The BIOS loads this code from the first sector of the hard disk into
# memory at physical address 0x7c00 and starts executing in real mode
# with %cs=0 %ip=7c00.
```
- 第一行指令：`cli`，禁止處理器中斷。
- 硬體可以透過中斷觸發中斷處理，進而操作系統的功能。BIOS 為了初始化硬體，可能設置了自己的中斷處理。但控制權已經給 boot loader 了，所以現在處理中斷是不安全的；當 xv6 準備完成後會重新允許中斷。


```x86asm first_line:9
.code16                       # Assemble for 16-bit mode
.globl start
start:
  cli                         # BIOS enabled interrupts; disable
```
- 處理器在模擬 Intel 8088 的 **real mode** 狀態下，有 8 個 16 位元的通用暫存器，但處理器傳送的是20位元的地址給記憶體；因此多出來的四個位元由段暫存器(`%cs`, `%ds`, `%es`, `%ss`)提供。
- `%cs` 取指令用
- `%ds` 讀寫資料用
- `%ss` 讀寫堆疊用
- BIOS 完成工作後 `%ds`, `%es`, `%ss` 是未知的，所以將其設為 0

```x86asm first_line:13
  # Zero data segment registers DS, ES, and SS.
  xorw    %ax,%ax             # Set %ax to zero
  movw    %ax,%ds             # -> Data Segment
  movw    %ax,%es             # -> Extra Segment
  movw    %ax,%ss             # -> Stack Segment
```
- xv6 假設 x86 的指令是使用虛擬地址，但實際上使用的是邏輯地址。
- 一個邏輯地址包含一個段選擇器及一個差值，有時表示為 segment:offset。
- 更多時候，段是固定的，所以程式只會使用差值。
- 分段硬體負責將邏輯地址翻譯成線性地址。
- 如果分頁硬體是啟用的，它會把線性地址轉成物理地址；若未啟用，處理器會把線性地址當作物理地址。

![logic address](https://i.imgur.com/BzuyHUc.png "The relationship between logical, linear, and physical addresses.")

- 一個 segment:offset 可能產生 21-bit 的物理地址，但在模擬 Intel 8088 下只能使用 20 bits 的記憶體位置，IBM 提出了一個方法：如果鍵盤控制器輸出端的第二位高於第一位，則第 21 個 bit 可以正常使用，反之則歸零。
- boot loader 用 I/O 指令控制鍵盤控制器端 0x64、0x60 來確保第 21 個 bit 正常運作。

```x86asm first_line:18
  # Physical address line A20 is tied to zero so that the first PCs 
  # with 2 MB would run software that assumed 1 MB.  Undo that.
seta20.1:
  inb     $0x64,%al               # Wait for not busy
  testb   $0x2,%al
  jnz     seta20.1

  movb    $0xd1,%al               # 0xd1 -> port 0x64
  outb    %al,$0x64

seta20.2:
  inb     $0x64,%al               # Wait for not busy
  testb   $0x2,%al
  jnz     seta20.2

  movb    $0xdf,%al               # 0xdf -> port 0x60
  outb    %al,$0x60
```
- 由於 real mode 只有 16-bit 的暫存器，導致一個程式如果要使用超過 65536 bytes 的記憶體會很困難，也不可能使用超過 1MB 的記憶體。
- x86 從 80286 開始有 **protected mode**，允許物理位置能擁有更多 bits，從 80386 後有 32-bit 模式。
- Boot loader 接著開啟 protected mode 和 32-bit 模式。
- 在 protected mode 下的段暫存器保存著段描述符表的索引。

![segment](https://i.imgur.com/UHmc4y3.png "Segments in protected mode.")

- Limits 代表最大的虛擬地址
- 段描述符表包含一個權限(被 protected mode 保護)，kernel 可以使用這個權限確保一個程式只會使用自己的記憶體。
- Xv6 幾乎不用段，取而代之的是分頁。
- Boot loader 設定段描述符表 gdt，每一段的基址為 0，且 limit 為 4GB (2^32)。
- Flag 使程式碼會在 32-bit 中執行。
- 由上述設定能確保當 boot loader 進入 protected mode 時，邏輯地址映射到物理地址會是 1-1 的。
- `lgdt` 指令將 GDT 暫存器(指向 gdt) 載入 gdtdesc 的值。
<p id="gdt"></p>
- {% label info@補充 %}：在創建 GDT 的時候，第一項須為空(規定)，接著我們為此臨時的 GDT 設立 code 及 data 段。
```x86asm :GDT line_number:false
# Line 78 in bootasm.S
# Bootstrap GDT
.p2align 2                                   # force 4 byte alignment
gdt:
     SEG_NULLASM                             # null seg
     SEG_ASM(STA_X|STA_R, 0x0, 0xffffffff)   # code seg
     SEG_ASM(STA_W, 0x0, 0xffffffff)         # data seg
gdtdesc:
     .word   (gdtdesc - gdt - 1)             # sizeof(gdt) - 1
     .long   gdt                             # address gdt
```
```x86asm first_line:35
  # Switch from real to protected mode.  Use a bootstrap GDT that makes
  # virtual addresses map directly to physical addresses so that the
  # effective memory map doesn't change during the transition.
  lgdt    gdtdesc
```
- Boot loader 將 `%cr0` 中的 `CRO_PE` 設為 1，來啟用 protected mode。
- 啟用 protceted mode 不會立即改變處理器轉譯邏輯地址的過程；只有當段暫存器載入了新的值，處理器讀取 GDT 改變其內部的斷設定。

```x86asm first_line:39
  movl    %cr0, %eax
  orl     $CR0_PE, %eax
  movl    %eax, %cr0
```
- `ljmp` 指令語法：`ljmp segment offset`，此時段暫存器為 `SEG_KCODE<<3`，即 8 (`SEG_KCODE == 1`，定義於`mmu.h`)
- `ljmp` 指令跳至 start32 執行。

```x86asm first_line:42
//PAGEBREAK!
  # Complete transition to 32-bit protected mode by using long jmp
  # to reload %cs and %eip.  The segment descriptors are set up with no
  # translation, so that the mapping is still the identity mapping.
  ljmp    $(SEG_KCODE<<3), $start32
```
- 進入 32 位元後的第一個動作：用`SEG_KDATA`初始化數據段暫存器

```x86asm first_line:48
.code32  # Tell assembler to generate 32-bit code now.
start32:
  # Set up the protected-mode data segment registers
  movw    $(SEG_KDATA<<3), %ax    # Our data segment selector
  movw    %ax, %ds                # -> DS: Data Segment
  movw    %ax, %es                # -> ES: Extra Segment
  movw    %ax, %ss                # -> SS: Stack Segment
  movw    $0, %ax                 # Zero segments not ready for use
  movw    %ax, %fs                # -> FS
  movw    %ax, %gs                # -> GS
```
- 最後建立一個 stack，跳至 *bootmain.c*。
- 記憶體 0xa0000 至 0x100000 為設備區，xv6 kernel 放在 0x100000。
- Boot loader 位於 0x7c00 至 0x7e00 (512 bytes)，所以其他位置都能拿來建立堆疊；這裡選擇 0x7c00 當作 top (`$start`)，堆疊向下延伸，直到 0x0000。

```x86asm first_line:58
  # Set up the stack pointer and call into C.
  movl    $start, %esp
  call    bootmain
```
- 如果出錯了，會向 0x8a00 端輸出一些字。
- 實際上沒有設備連接到 0x8a00。
- 如果使用模擬器，boot loader 會把控制權還給模擬器。

{% note warning %}
真正的 boot loader 會印出一些錯誤訊息。
{% endnote %}

```x86asm first_line:61
  # If bootmain returns (it shouldnt), trigger a Bochs
  # breakpoint if running under Bochs, then loop.
  movw    $0x8a00, %ax            # 0x8a00 -> port 0x8a00
  movw    %ax, %dx
  outw    %ax, %dx
  movw    $0x8ae0, %ax            # 0x8ae0 -> port 0x8a00
  outw    %ax, %dx
```
- 接著進入無限迴圈

```x86asm first_line:68
spin:
  jmp     spin
```

---
## Code: C bootstrap
### File: bootmain.c
- *bootmain* 的工作：載入並執行 kernel。
- Kernel 為 ELF 格式的二進位檔。
- ELF(Executable and Linking Format) ，為 UNIX 中的目錄檔格式。

```c
// Boot loader.
// 
// Part of the boot sector, along with bootasm.S, which calls bootmain().
// bootasm.S has put the processor into protected 32-bit mode.
// bootmain() loads an ELF kernel image from the disk starting at
// sector 1 and then jumps to the kernel entry routine.

#include "types.h"
#include "elf.h"
#include "x86.h"
#include "memlayout.h"

#define SECTSIZE  512

void readseg(uchar*, uint, uint);

void
bootmain(void)
{
  struct elfhdr *elf;
  struct proghdr *ph, *eph;
  void (*entry)(void);
  uchar* pa;
```
- 為了存取 ELF 開頭，*bootmain* 載入 ELF 文件的前 4096 bytes，並拷貝到 010000 中。

```c first_line:24
  elf = (struct elfhdr*)0x10000;  // scratch space

  // Read 1st page off disk
  readseg((uchar*)elf, 4096, 0);
```
- 接著確認是否為 ELF 文件。

{% note danger%}
正常情況下 *bootmain* 不會`return`，這裡`return`會跳回 *bootasm.S* 中，由 *bootasm.S* 來處理此錯誤。
{% endnote %}

```c first_line:28
  // Is this an ELF executable?
  if(elf->magic != ELF_MAGIC)
    return;  // let bootasm.S handle error
```
- *bootmain* 從 ELF 開頭之後的 off bytes 讀取內容，並存入 paddr 中(呼叫`readseg`)。
- 呼叫`stosb`將段的剩餘部分設 0

```c first_line:31
  // Load each program segment (ignores ph flags).
  ph = (struct proghdr*)((uchar*)elf + elf->phoff);
  eph = ph + elf->phnum;
  for(; ph < eph; ph++){
    pa = (uchar*)ph->paddr;
    readseg(pa, ph->filesz, ph->off);
    if(ph->memsz > ph->filesz)
      stosb(pa + ph->filesz, 0, ph->memsz - ph->filesz);
  }
```
- Boot loader 最後一項工作：呼叫 kernel 的進入指令，即 kernel 第一條執行指令的地址(0x10000c)。
- *entry.S* 中定義的`_start`即為 ELF 入口。
- xv6 虛擬記憶體尚未建立，因此 entry 為物理地址。

```c first_line:40
  // Call the entry point from the ELF header.
  // Does not return!
  entry = (void(*)(void))(elf->entry);
  entry();
}
```
{% note info %}
**函數指標**的補充[^1]：
上述用一個 `void (*entry)(void)` 指標即為一個函數指標，此指標指向一個函數，於上述 42 行將此指標指向 `elf->entry`，此動作將 `entry` 指標指向一個函數的進入點位置（`elf->entry`）。
此時呼叫 `entry()` 會進入此指標位置，並當作一個副函式執行；因此執行完上述程式碼會進入 *entry.S*，並執行其中的程式碼。
{% endnote %}

[^1]:[指標函數和函數指標有什麼區別](http://bluelove1968.pixnet.net/blog/post/222285883-%E6%8C%87%E6%A8%99%E5%87%BD%E6%95%B8%E5%92%8C%E5%87%BD%E6%95%B8%E6%8C%87%E6%A8%99%E6%9C%89%E4%BB%80%E9%BA%BC%E5%8D%80%E5%88%A5)

```c :waitdisk()
void
waitdisk(void)
{
  // Wait for disk ready.
  while((inb(0x1F7) & 0xC0) != 0x40)
    ;
}
```

```c :readsect()
// Read a single sector at offset into dst.
void
readsect(void *dst, uint offset)
{
  // Issue command.
  waitdisk();
  outb(0x1F2, 1);   // count = 1
  outb(0x1F3, offset);
  outb(0x1F4, offset >> 8);
  outb(0x1F5, offset >> 16);
  outb(0x1F6, (offset >> 24) | 0xE0);
  outb(0x1F7, 0x20);  // cmd 0x20 - read sectors

  // Read data.
  waitdisk();
  insl(0x1F0, dst, SECTSIZE/4);
}
```

```c :readseg()
// Read 'count' bytes at 'offset' from kernel into physical address 'pa'.
// Might copy more than asked.
void
readseg(uchar* pa, uint count, uint offset)
{
  uchar* epa;

  epa = pa + count;

  // Round down to sector boundary.
  pa -= offset % SECTSIZE;

  // Translate from bytes to sectors; kernel starts at sector 1.
  offset = (offset / SECTSIZE) + 1;

  // If this is too slow, we could read lots of sectors at a time.
  // We'd write more to memory than asked, but it doesn't matter --
  // we load in increasing order.
  for(; pa < epa; pa += SECTSIZE, offset++)
    readsect(pa, offset);
}
```