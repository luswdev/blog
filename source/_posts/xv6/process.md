---
title: "XV6 - OS Organization"
date: 2018-07-16 13:58:18 +0800
tag: [XV6, process, kernel]
category: XV6
---
>OS 必須具備三項技能：多工、獨立及交流。

## kernel 組織
- **Monolithic kernel**：整個 OS 都位於 kernel 中，如此一來所有 system calls 都會在 kernel 中執行（XV6）。
    - 好處
        1. 設計者不須決定 OS 的哪些部份不需要完整的硬體特權。
        2. 更方便的讓不同部份的 OS 去合作。
    - 壞處
        1. 通常在不同部份的 OS 中的介面是複雜的。
        2. 這會容易讓開發者出錯。
- **Microkernel**：為了減少 kernel 出錯的風險，設計者可以將 kernel mode 上執行的 OS 程式碼最小化，並大讓OS 在 user mode 中執行。

![microkernel](https://i.imgur.com/lCf7yfw.png "A mkernel with a file system server")

---
## Process 概觀
- 為 UNIX（XV6） 中的一個獨立單元。
- 確保一個 process 不會破壞或是竊取另一程序的記憶體、CPU、檔案描述符等等。
- 亦確保 kernel 不會被破壞。
- Process 為抽象的，這讓一個程式可以假設它佔有一台虛擬機器，即一個接近私有的記憶體或是 address space，其他的 process 不可以 r/w。
- 私有的 adderss space 由不同的 page table 實做，即一個 process 有一個 page table
- 每個 process 的 page 都分為 kernel 及 user（如上圖），因此當 process 呼叫一個 system call 時，會直接在自己的 kernel 映射（mapping）中執行。
- Thread：用來執行指令，可以被暫緩，稍後再恢復運作。
- 大部分 thread 的狀態（區域變數等）被保存在 thread 的堆疊上，每個 process 有兩個堆疊：user/kernel 堆疊。
    - user 指令執行時，只會用到 user 堆疊，此時 kernel 堆疊為空。
    - kernel 指令執行時，user 堆疊的資料不會清空，也不會使用到。
- `p->state` 指 process 的狀態：新建、準備執行、執行中、等待I/O及退出。
- `p->pgdir`：保存 process 的 page table。

![address space](https://i.imgur.com/AQ9pMhW.png "Layout of a virtual address space")

---
## Code: 第一個 address space
- XV6 為 kernel 建立第一個 address space 的流程：
    1. 開機
    2. 初始化自己
    3. 從硬碟中讀取 boot loader 至記憶體中執行。
    4. Boot loader 從硬碟讀取 kernel 並從 *entry.s* 開始執行。
    5. Boot loader 會把 XV6 的 kernel 載入實體位址 0x100000。
    6. 為了讓剩下的 kernel 能夠執行，設置一個 page table，將虛擬位址 0x80000000（KERNBASE）映射到實體位址 0x0。將兩個虛擬位址映 到同一個實體位址是 page 的常見手法。
    7. 跳到 kernel 的 c code，並在高位址上執行：
        - `%esp` 指向高位址的 stack 記憶體。
        - 跳到高位址的 *main*。

![address space](https://i.imgur.com/aks2sld.png "Layout of a virtual address space")

{% alert success %}
****File:**** entry.s
{% endalert  %}

```x86asm=
_start = V2P_WO(entry)

# Entering XV6 on boot processor, with paging off.
.globl entry
entry:
    # Turn on page size extension for 4Mbyte pages
    movl    %cr4, %eax
    orl     $(CR4_PSE), %eax
    movl    %eax, %cr4
    # Set page directory
    movl    $(V2P_WO(entrypgdir)), %eax
    movl    %eax, %cr3
    # Turn on paging.
    movl    %cr0, %eax
    orl     $(CR0_PG|CR0_WP), %eax
    movl    %eax, %cr0

    # Set up the stack pointer.
    movl $(stack + KSTACKSIZE), %esp

    # Jump to main(), and switch to executing at
    # high addresses. The indirect call is needed because
    # the assembler produces a PC-relative instruction
    # for a direct jump.
    mov $main, %eax
    jmp *%eax

.comm stack, KSTACKSIZE
```

---
## Code: 建立第一個 process

{% alert success %}
****File:**** proc.c
{% endalert  %}

- 呼叫 `userinit()` 來建立第一個 process（只有在第一個process時會呼叫）。
- 呼叫 `allocproc()`（每個 process 都會呼叫）。
- `Allocproc` 在 process table 中分配一個 slot（`struct proc`），並初始化有關 kernel thread 的 process 片段。
- `Allocproc` 掃描 proc tabel，找到 `p->state` 是 `UNUSED`，接著設定為 `EMBRYO` 來標示被使用，並給予一組唯一的 pid。

### allocproc
| 功能 | 回傳值 |
| --- | ------ |
| 建立一個 process | process 結構 |

```c =29
// Look in the process table for an UNUSED proc.
// If found, change state to EMBRYO and initialize
// state required to run in the kernel.
// Otherwise return 0.
static struct proc*
allocproc(void)
{
  struct proc *p;
  char *sp;

  acquire(&ptable.lock);
  for(p = ptable.proc; p < &ptable.proc[NPROC]; p++)
    if(p->state == UNUSED)
      goto found;
  release(&ptable.lock);
  return 0;

found:
  p->state = EMBRYO;
  p->pid = nextpid++;
  release(&ptable.lock);
```
- 接著嘗試請求分配一個 kernel stack，如果失敗，把 `p->state` 改回 `UNUSED`。

```c =50
  // Allocate kernel stack.
  if((p->kstack = kalloc()) == 0){
    p->state = UNUSED;
    return 0;
  }
  sp = p->kstack + KSTACKSIZE;
  
  // Leave room for trap frame.
  sp -= sizeof *p->tf;
  p->tf = (struct trapframe*)sp;
```
![kstack](https://i.imgur.com/Q2wSVQX.png "A new kernel stack.")

- Allocproc 通過設定返回程式計數器的值來導致新 process 的 kernel thread 會先在 forkret 中執行，再回到 trapret。
- Kernel thread 從 p->context 的拷貝開始執行，因此設定 p->context->eip 指向 forkret 會導致 kernel thread 從 forkret 的開頭開始執行。

```c =60
  // Set up new context to start executing at forkret,
  // which returns to trapret.
  sp -= 4;
  *(uint*)sp = (uint)trapret;
  
  sp -= sizeof *p->context;
  p->context = (struct context*)sp;
  memset(p->context, 0, sizeof *p->context);
  p->context->eip = (uint)forkret;

  return p;
}
```
- `Forkret` return 堆疊（`p->context->eip`）底。
- `Allocate` 將 `trapret` 放在 `eip` 的上方，即 `forkret` return 的位置。
- `Trapret` 從 kernel 堆疊頂恢復 user 的暫存器並跳至程序。

---
- 第一個 process 會運行一個小程式 *initcode.s*。
- Process 需要實體記憶體來保存此程式。
- Process 需要被拷貝到記憶體中，也需要 page table 來指向此位址。
- `Userinit` 呼叫 `setupkvm` 來建立 page table 只映射到 kernel 會用到的記憶體。

| 功能 | 回傳值 |
| --- | ------ |
| 建立系統的初始 process | void |

### userinit
```c =79
void
userinit(void)
{
  struct proc *p;
  extern char _binary_initcode_start[], _binary_initcode_size[];
  
  p = allocproc();
  initproc = p;
  if((p->pgdir = setupkvm()) == 0)
    panic("userinit: out of memory?");
```
- `inituvm` 請求一個 page 大小的實體記憶體，將虛擬記憶體 0 映射到此記憶體，並將 `_binary_initcode_start_` 及 `_binary_initcode_size_` 拷貝到 page。

```c =88
  inituvm(p->pgdir, _binary_initcode_start, (int)_binary_initcode_size);
```
- 把 trap frame 設定為初始使用者模式。

```c =89
  p->sz = PGSIZE;
  memset(p->tf, 0, sizeof(*p->tf));
  p->tf->cs = (SEG_UCODE << 3) | DPL_USER;
  p->tf->ds = (SEG_UDATA << 3) | DPL_USER;
  p->tf->es = p->tf->ds;
  p->tf->ss = p->tf->ds;
  p->tf->eflags = FL_IF; //allow hardware interrupt
  p->tf->esp = PGSIZE;
  p->tf->eip = 0;  // beginning of initcode.S
```
- `p->name` 設為 `"initcode"` 是為了 debug，`p->cwd` 設在 process 的現在目錄。

```c =98
  safestrcpy(p->name, "initcode", sizeof(p->name));
  p->cwd = namei("/");
```
- 設定 `p->state` 為 `RUNNABLE`。

```c =100
  p->state = RUNNABLE;
}
```

---
## Code: 執行第一個 process
- 當 *main* 呼叫完 *userinit* 後，呼叫 *mpmain*，*mpmain* 接著呼叫 *scheduler* 開始運行 process。

{% alert success %}
**File:** main.c
{% endalert  %}

| 功能 | 回傳值 |
| --- | ------ |
| 完成多核心開機程序 | void |


```c =55
// Common CPU setup code.
static void
mpmain(void)
{
  cprintf("cpu%d: starting\n", cpu->id);
  idtinit();       // load idt register
  xchg(&cpu->started, 1); // tell startothers() we're up
  scheduler();     // start running processes
}
```

---

{% alert success %}
**File:** proc.c
{% endalert  %}

| 功能 | 回傳值 |
| --- | ------ |
| 執行調度，指定執行的 process | void |

```c =249
//PAGEBREAK: 42
// Per-CPU process scheduler.
// Each CPU calls scheduler() after setting itself up.
// Scheduler never returns.  It loops, doing:
//  - choose a process to run
//  - swtch to start running that process
//  - eventually that process transfers control
//      via swtch back to the scheduler.
void
scheduler(void)
{
  struct proc *p;
```
- 第一行指令：`sti`，啟動處理器中斷；開機的時候在 *bootasm.S* 中將中斷禁止(`cli`)，在 XV6 準備完成後重新開啟。

```c =261
  for(;;){
    // Enable interrupts on this processor.
    sti();
```
- *Scheduler* 找到一個`p->state`為`RUNNABLE`的 process，此時是唯一的：`initproc`。

```c =264
    // Loop over process table looking for process to run.
    acquire(&ptable.lock);
    for(p = ptable.proc; p < &ptable.proc[NPROC]; p++){
      if(p->state != RUNNABLE)
        continue;
```
- 接著把 pre-cpu 的變量 `proc` 設為此 process。
- 呼叫 `switchuvm` 通知硬體開始使用目標 process 的  page table。

```c =369
      // Switch to chosen process.  It is the process's job
      // to release ptable.lock and then reacquire it
      // before jumping back to us.
      proc = p;
      switchuvm(p);
```
- 接著把 `p->state` 設為 `RUNNING`。
- 呼叫 `swtch`，context switch 到目標程序的 kernel thread。

```c =274
      p->state = RUNNING;
      swtch(&cpu->scheduler, proc->context);
      switchkvm();

      // Process is done running for now.
      // It should have changed its p->state before coming back.
      proc = 0;
    }
    release(&ptable.lock);
  }
}
```

---

{% alert success %}
****File:**** vm.c
{% endalert  %}

```c=163
    // Switch TSS and h/w page table to correspond to process p.
    void
    switchuvm(struct proc *p)
    {
      pushcli();
      cpu->gdt[SEG_TSS] = SEG16(STS_T32A, &cpu->ts, sizeof(cpu->ts)-1, 0);
      cpu->gdt[SEG_TSS].s = 0;
      cpu->ts.ss0 = SEG_KDATA << 3;
      ltr(SEG_TSS << 3);
      if(p->pgdir == 0)
        panic("switchuvm: no pgdir");
      lcr3(v2p(p->pgdir));  // switch to new address space
      popcli();
    }
```

- `switchuvm` 同時設置好任務狀態段 `SEG_TSS`，讓硬體在 process 的 kernel stack 中執行 system call 與中斷。

```c=177
      // Switch to chosen process.  It is the process's job
      // to release ptable.lock and then reacquire it
      // before jumping back to us.
      proc = p;
      switchuvm(p);
```

- 接著把 `p->state` 設為 `RUNNING`。
- 呼叫 `swtch`，context switch 到目標程序的 kernel thread。

```c=182
      p->state = RUNNING;
      swtch(&cpu->scheduler, proc->context);
      switchkvm();

      // Process is done running for now.
      // It should have changed its p->state before coming back.
      proc = 0;
    }
    release(&ptable.lock);
  }
}
```

---

### **File:** swtch.S
```x86asm =
# Context switch
#
#   void swtch(struct context **old, struct context *new);
# 
# Save current register context in old
# and then load register context from new.

.globl swtch
swtch:
     movl 4(%esp), %eax
     movl 8(%esp), %edx

     # Save old callee-save registers
     pushl %ebp
     pushl %ebx
     pushl %esi
     pushl %edi

     # Switch stacks
     movl %esp, (%eax)
     movl %edx, %esp

     # Load new callee-save registers
     popl %edi
     popl %esi
     popl %ebx
     popl %ebp
     ret
```

- `ret` 指令從 stack pop 目標程序的 `%eip`，結束 context switch。
- 現在處理器在程序 p 的 kernel stack 上執行。
- `allocproc` 把 `initproc` 的 `p->context->eip` 設為 `forkret`，使得 `ret` 開始執行 `forkret`。
- 第一次執行 `forkret` 時會呼叫一些初始化函數(`initlog`)，接著返回。

---

{% alert success %}
**File:** proc.c
{% endalert  %}

| 功能 | 回傳值 |
| --- | ------ |
| - | void |

```c =320
// A fork child's very first scheduling by scheduler()
// will swtch here.  "Return" to user space.
void
forkret(void)
{
  static int first = 1;
  // Still holding ptable.lock from scheduler.
  release(&ptable.lock);

  if (first) {
    // Some initialization functions must be run in the context
    // of a regular process (e.g., they call sleep), and thus cannot 
    // be run from main().
    first = 0;
    initlog();
  }
  
  // Return to "caller", actually trapret (see allocproc).
}
```

- 接著位於 p->context 的是 `trapret`。
- `%esp` 保存著 `p->tf`。
- `trapret` 恢復暫存器，如同　`swtch` 進行　context switch 一樣。
- `popal` 恢復通用暫存器
- `popl` 恢復`%gs`、`%fs`、`%es`、`%ds`
- `addl` 跳過 `trapno` 和 `errcode` 兩個數據
- 最後 `iret` pop `%gs`、`%fs`、`%es`、`%ds` 出堆疊。

{% alert success %}
**File:** trapasm.S
{% endalert  %}

```x86asm
  # Return falls through to trapret...
.globl trapret
trapret:
  popal
  popl %gs
  popl %fs
  popl %es
  popl %ds
  addl $0x8, %esp  # trapno and errcode
  iret
```

{% alert info %}
`iret`：interrupt return，程序返回中斷前的位址。
{% endalert  %}

- 處理器從 `%eip` 的值繼續執行，對於 `initproc` 即為虛擬地址 0，也就是 *initcode.S* 的第一條指令。

---
## 第一個 system call：exec
- *initcode.S* 第一件事是觸發 `exec` system call。
- `exec` 用一個新的程式代替當前 process 的記憶體及暫存器。
- 首先將`$argv`、`$init`、`$0` push 進堆疊，接著把 `%eax` 設為 `$SYS_exec`。
- 最後執行 `int $T_SYSCALL`。
- 這告訴 kernel 來運行 `exec`。
- 正常情況下，`exec` 不會返回；會運行名叫 `$init`(23) 的程式。
- `$init` 會 return `"/init\0"`
- 若 `exec` 失敗了且返回，*initcode* 會不斷的呼叫一個 system call：`exit()`(17)。

### **File:** initcode.S
```x86asm =
# Initial process execs /init.

#include "syscall.h"
#include "traps.h"


# exec(init, argv)
.globl start
start:
  pushl $argv
  pushl $init
  pushl $0  // where caller pc would be
  movl $SYS_exec, %eax
  int $T_SYSCALL

# for(;;) exit();
exit:
  movl $SYS_exit, %eax
  int $T_SYSCALL
  jmp exit

# char init[] = "/init\0";
init:
  .string "/init\0"

# char *argv[] = { init, 0 };
.p2align 2
argv:
  .long init
  .long 0
```