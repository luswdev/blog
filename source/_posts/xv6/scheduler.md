---
title: "XV6 - Scheduling"
date: 2018-08-14 14:14:42 +0800
tag: [XV6, scheduler, kernel]
category: XV6
---
## Multiplexing
- 如果 process 需要等待 I/O 或是子 process 結束，XV6 讓其進入睡眠狀態，接著將處理器 switch 給其他 process。 
- 此機制使 process 有獨佔 CPU 的假象。
- 完成 switch 的動作由 context switch 完成：
    - 透明化 -> 使用 **timer interrupt handler** 驅動 context switch。
    - 同時多個 process 需要 switch -> lock

---
## Code: Context switch
- 從 process 的 kernel stack -> schedluler kernel stack (CPU) -> 另一個 process 的 kernel stack。
- 永遠不會從 process 的 kernel stack -> process 的 kernel stack。 

![context switch](https://i.imgur.com/qtfH1Vj.png "Switching from one user process to another.")

- 每個 process 都有自己的 kernel stack 及暫存器集合，每個 CPU 有自己的 scheduler stack。
- context 即 process 的暫存器集合，用一個 `struct context *` 表示。

{% alert success %}
**File:** proc.h
{% endalert %}

```c =44
struct context {
  uint edi;
  uint esi;
  uint ebx;
  uint ebp;
  uint eip;
};
```
- trap 有可能會呼叫 `yield`，`yield` 會呼叫 `sched`，最後 `sched` 呼叫 `swtch(&proc->context, cpu->scheduler);`。

### File: swtch.S
- *swtch* 有兩個參數：`struct context ** old`、`struct context * new`。

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
```
- 將 `%eax` 指向 `struct context ** old`，`%ebx` 指向 `struct context * new`。

![swtch](https://i.imgur.com/0QWW7gZ.png "Context switch step. 1")

```x86asm =12
  # Save old callee-save registers
  pushl %ebp
  pushl %ebx
  pushl %esi
  pushl %edi
```
- 依序將 context push 進堆疊

![swtch](https://i.imgur.com/CXUNIYY.png "Context switch step. 2") 
```x86asm =17
  # Switch stacks
  movl %esp, (%eax)
  movl %edx, %esp
```
- 將 `struct context ** old`（`%eax`） 指向 `%esp`（當前堆疊的 **top**）
- 將 `%esp` 指向 `struct context * new`（`%ebx`）

![swtch](https://i.imgur.com/AB38T7t.png "Context switch step. 3")

```x86asm =20
  # Load new callee-save registers
  popl %edi
  popl %esi
  popl %ebx
  popl %ebp
  ret
```
- 恢復保存的暫存器，用`ret` 指令跳回

---
## Scheduling

- process 要讓出 CPU 前需要先取得 ptable.lock，釋放其他擁有的鎖，修改 p->state，呼叫 `sched`。
- `sched` 會再次檢查以上動作，並且確定此時中斷是關閉的，最後呼叫 `swtch`，將 process 的暫存器保存在 proc->context，switch 到 cpu->scheduler。

{% alert success %}
**File:** proc.c
{% endalert %}

### sched

| 功能 | 回傳值 |
| --- | ------ |
| 檢查並跳至 swtch.h | void |

```c =292
void
sched(void)
{
  int intena;

  if(!holding(&ptable.lock))
    panic("sched ptable.lock");
  if(cpu->ncli != 1)
    panic("sched locks");
  if(proc->state == RUNNING)
    panic("sched running");
  if(readeflags()&FL_IF)
    panic("sched interruptible");
  intena = cpu->intena;
  swtch(&proc->context, cpu->scheduler);
  cpu->intena = intena;
}
```
- `swtch` 會 return 回 scheduler 的堆疊上，scheduler 繼續執行迴圈：找到一個 process 運行，`swtch` 到該 process，repeat。

### scheduler

| 功能 | 回傳值 |
| --- | ------ |
| 執行調度，指定執行的 process | void |

```c =249
void
scheduler(void)
{
  struct proc *p;

  for(;;){
    // Enable interrupts on this processor.
    sti();

    // Loop over process table looking for process to run.
    acquire(&ptable.lock);
    for(p = ptable.proc; p < &ptable.proc[NPROC]; p++){
      if(p->state != RUNNABLE)
        continue;

      // Switch to chosen process.  It is the process's job
      // to release ptable.lock and then reacquire it
      // before jumping back to us.
      proc = p;
      switchuvm(p);
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
- `scheduler` 找到一個 `RUNNABLE` 的 process，將 per-cpu 設為此 process，呼叫 `switchuvm` 切換到該 process 的頁表，更新狀態為`RUNNING`，`swtch` 到該 process 中運行。

---
## Code:  mycpu  and  myproc
- CPU 需要辨識目前正在執行的 process，XV6 有一個 struct cpu 的陣列，裡面包含了一些 CPU 的資訊，及當前 process 的資訊。
- `mycpu` 尋找當前的 `lapicid` 是哪顆 CPU 的。

### mycpu

| 功能 | 回傳值 |
| --- | ------ |
| 找到目前所在的 CPU | CPU 結構指標 |

```c =37
struct cpu*
mycpu(void)
{
  int apicid, i;
  
  if(readeflags()&FL_IF)
    panic("mycpu called with interrupts enabled\n");
  
  apicid = lapicid();
  // APIC IDs are not guaranteed to be contiguous. Maybe we should have
  // a reverse map, or reserve a register to store &cpus[i].
  for (i = 0; i < ncpu; ++i) {
    if (cpus[i].apicid == apicid)
      return &cpus[i];
  }
  panic("unknown apicid\n");
}
```
- `myproc` 呼叫 `mycpu`，從正確的 CPU 上尋找當前的 process。

| 功能 | 回傳值 |
| --- | ------ |
| 找到當前的 process | proc 結構指標 |

```c =57
struct proc*
myproc(void) {
  struct cpu *c;
  struct proc *p;
  pushcli();
  c = mycpu();
  p = c->proc;
  popcli();
  return p;
}
```

---
## 睡眠與喚醒（例子）
```c
struct q {
    void *ptr;
};

void*
send(struct q *q, void *p)
{
    while(q->ptr != 0)
        ;
    q->ptr = p;
}

void*
recv(struct q * q)
{
    void *p;
    while((p = q->ptr) == 0)
        ;
    q->ptr = 0;
    return p;
}
```
- `send` 直到隊伍 `q` 為空時，將要傳送的資料 `p` 放入隊中，`recv` 直到 `q` 有東西時將資料取出，把 `q` 再次設為 `0`
- 這允許不同的 process 交換資料，但是很耗資源。

### 方案 1
- 加入 `sleep` 及 `wakeup` 機制，`sleep(chan)` 將 process 在 `chan` 中睡眠（一個 wait channel），`wakeup(chan)` 將 `chan` 上睡眠的 process 喚醒。

```diff
void*
send(struct q *q, void *p)
{
    while(q->ptr != 0)
        ;
    q->ptr = p;
+    wakeup(q);    /*wake recv*/
}

void*
recv(struct q *q)
{
    void *p;
    while((p = q->ptr) == 0)
+        sleep(q);
    q->ptr = 0;
    return p;
}
```
- 如此一來 `recv` 能讓出 CPU，直到有人（`send`）將它喚醒。

{% alert danger %}
**問題**：遺失的喚醒：
![遺失的喚醒](https://i.imgur.com/aPcvuOZ.png "Example lost wakeup problem")
- 假設 `recv` 在 215 行查看隊伍，發現需要睡眠，就在要呼叫 `sleep` 之前發生中斷，`send` 在其他的 CPU 將資料放進隊伍中，呼叫 `wakeup`，發現沒有正在休眠的 process，於是不做事；接著 `recv` 終於呼叫 `sleep` 進入睡眠。
- 此時，`revc` 正在等待 `send` 將它喚醒，但是 `send` 正在等待隊伍清空，清空的動作須由 `recv` 完成（休眠中），於是就產生了 **deadlock**。
{% endalert %}

### 方案 2
- 讓 `send` 及 `recv` 在睡眠及喚醒前都持有鎖。

```diff
struct q {
    struct spinlock lock;
    void *ptr;
};

void *
send(struct q *q, void *p)
{
+    acquire(&q->lock);
    while(q->ptr != 0)
        ;
    q->ptr = p;
    wakeup(q);
+    release(&q->lock);
}

void*
recv(struct q *q)
{
    void *p;
+    acquire(&q->lock);
    while((p = q->ptr) == 0)
        sleep(q);
    q->ptr = 0;
+    release(&q->lock);
    return p;
}
```
- 但這一樣有問題：`recv` 帶著鎖進入睡眠，`send` 也同時需要鎖來喚醒，於是就產生了 **deadlock**。

### 方案 3
- 在 `sleep` 時一併釋放鎖，也就是將鎖當成參數傳進去。

```diff
struct q {
    struct spinlock lock;
    void *ptr;
};

void *
send(struct q *q, void *p)
{
    acquire(&q->lock);
    while(q->ptr != 0)
        ;
    q->ptr = p;
    wakeup(q);
    release(&q->lock);
}

void*
recv(struct q *q)
{
    void *p;
    acquire(&q->lock);
    while((p = q->ptr) == 0)
+        sleep(q, &q->lock);
    q->ptr = 0;
    release(&q->lock);
    return p;
}
```

---
## Code: 睡眠與喚醒

| 副程式    | 目標                                          | 
| -------- | -------------------------------------------- | 
| `sleep`  | 將狀態改成 `SLEEPING`，呼叫 `sched` 釋放 CPU     |
| `wakeup` | 尋找狀態為 `SLEEPING` 的 process，改成 `RUNNABLE`|

### sleep

| 功能 | 回傳值 |
| --- | ------ |
| 讓 process 睡眠 | void |

| `*chan` | `*lk` |
| ------- | ----- |
| 欲睡眠的頻道 | 持有的鎖 |

```c =418
void
sleep(void *chan, struct spinlock *lk)
{
  struct proc *p = myproc();
  
  if(p == 0)
    panic("sleep");

  if(lk == 0)
    panic("sleep without lk");
```
- 首先確保 process 存在，及持有鎖。

```c =428
  if(lk != &ptable.lock){  //DOC: sleeplock0
    acquire(&ptable.lock);  //DOC: sleeplock1
    release(lk);
  }
```
- 接著檢查是否持有 `ptable->lock`，如果沒有則要求一個，把 `lk` 釋出。

```c =432
  // Go to sleep.
  p->chan = chan;
  p->state = SLEEPING;

  sched();
```
- 睡眠，並呼叫 `sched`

```c =437
  // Tidy up.
  p->chan = 0;

  // Reacquire original lock.
  if(lk != &ptable.lock){  //DOC: sleeplock2
    release(&ptable.lock);
    acquire(lk);
  }
}
```

---
### wakeup1

| 功能 | 回傳值 | `*chan` |
| --- | ------ | ------- |
| 叫醒頻道上的 process | void | 頻道 | 

```c =458
// 
static void
wakeup1(void *chan)
{
  struct proc *p;

  for(p = ptable.proc; p < &ptable.proc[NPROC]; p++)
    if(p->state == SLEEPING && p->chan == chan)
      p->state = RUNNABLE;
}
```

---
### wakeup

| 功能 | 回傳值 | `*chan` |
| --- | ------ | ------- |
| 叫醒頻道上的 process | void | 頻道 | 


```c =468
void
wakeup(void *chan)
{
  acquire(&ptable.lock);
  wakeup1(chan);
  release(&ptable.lock);
}
```
- `wakeup` 找到 `chan` 上在睡眠的 process，並喚醒。

---
## Code: Pipes

{% alert success %}
**File:** pipe.c
{% endalert %}

- pipe 為一個結構，包含一個鎖，一個 `buf` 等。
- 當 pipe 為空時，`nread == nwrite`
- 當 pipe 為滿時，`nwrite == nread % PIPESIZE`

```c =12
struct pipe {
  struct spinlock lock;
  char data[PIPESIZE];
  uint nread;     // number of bytes read
  uint nwrite;    // number of bytes written
  int readopen;   // read fd is still open
  int writeopen;  // write fd is still open
};
```

---
### pipewrite

| 功能 | 回傳值 |
| --- | ------ |
| 寫入 pipe | 寫入的大小 |

| `*p` | `*addr` | `n` |
| ---- | ------- | --- |
| 欲寫入的 pipe | 欲寫入的值 | 欲寫入的大小 |

```c =77
int
pipewrite(struct pipe *p, char *addr, int n)
{
  int i;

  acquire(&p->lock);
  for(i = 0; i < n; i++){
    while(p->nwrite == p->nread + PIPESIZE){  //DOC: pipewrite-full
      if(p->readopen == 0 || myproc()->killed){
        release(&p->lock);
        return -1;
      }
      wakeup(&p->nread);
      sleep(&p->nwrite, &p->lock);  //DOC: pipewrite-sleep
    }
    p->data[p->nwrite++ % PIPESIZE] = addr[i];
  }
  wakeup(&p->nread);  //DOC: pipewrite-wakeup1
  release(&p->lock);
  return n;
}
```
- 首先須取得鎖。
- `pipewrite` 從 0 開始將資料讀入 `buf`，更新 `nwrite` 計數器，當 `buf` 滿了，則喚醒 `piperead` 並睡眠；或是讀入完畢，一樣喚醒 `pipiread`。

---
### piperead

| 功能 | 回傳值 |
| --- | ------ |
| 讀取 pipe | 讀入的大小 |

| `*p` | `*addr` | `n` |
| ---- | ------- | --- |
| 欲讀取的 pipe | 讀取資料存放區 | 欲讀入的大小 |

```c =99
int
piperead(struct pipe *p, char *addr, int n)
{
  int i;

  acquire(&p->lock);
  while(p->nread == p->nwrite && p->writeopen){  //DOC: pipe-empty
    if(myproc()->killed){
      release(&p->lock);
      return -1;
    }
    sleep(&p->nread, &p->lock); //DOC: piperead-sleep
  }
  for(i = 0; i < n; i++){  //DOC: piperead-copy
    if(p->nread == p->nwrite)
      break;
    addr[i] = p->data[p->nread++ % PIPESIZE];
  }
  wakeup(&p->nwrite);  //DOC: piperead-wakeup
  release(&p->lock);
  return i;
}
```
- 首先須取得鎖。
- `piperead` 確認 pipe 是否為空，為空則進入睡眠。
- 當 pipe 不為空時，寫入資料，更新 `nread` 計數器。
- 讀取完畢後，喚醒 `pipewrite`。

---
## Code: Wait, exit and kill

{% alert success %}
**File:** proc.c
{% endalert %}

### wait

| 功能 | 回傳值 |
| --- | ------ |
| 等待子 process 結束 | pid (ok) / -1 (err) |

```c =208
int
wait(void)
{
  struct proc *p;
  int havekids, pid;
  struct proc *curproc = myproc();
  
  acquire(&ptable.lock);
  for(;;){
    // Scan through table looking for exited children.
    havekids = 0;
    for(p = ptable.proc; p < &ptable.proc[NPROC]; p++){
      if(p->parent != curproc)
        continue;
      havekids = 1;
      if(p->state == ZOMBIE){
        // Found one.
        pid = p->pid;
        kfree(p->kstack);
        p->kstack = 0;
        freevm(p->pgdir);
        p->pid = 0;
        p->parent = 0;
        p->name[0] = 0;
        p->killed = 0;
        p->state = UNUSED;
        release(&ptable.lock);
        return pid;
      }
    }

    // No point waiting if we don't have any children.
    if(!havekids || curproc->killed){
      release(&ptable.lock);
      return -1;
    }

    // Wait for children to exit.  (See wakeup1 call in proc_exit.)
    sleep(curproc, &ptable.lock);  //DOC: wait-sleep
  }
}
```
- 首先須取得鎖。
- 接著尋找是否有子 process，如果有，並且還沒退出，則睡眠，等待子 process 退出。
- 如果找到已退出的子 process，紀錄該子 process 的 pid，清理 `struct proc`，釋放相關記憶體。

---
### exit
| 功能 | 回傳值 |
| --- | ------ |
| 自行結束 process | void |

```c =166
void
exit(void)
{
  struct proc *curproc = myproc();
  struct proc *p;
  int fd;

  if(curproc == initproc)
    panic("init exiting");

  // Close all open files.
  for(fd = 0; fd < NOFILE; fd++){
    if(curproc->ofile[fd]){
      fileclose(curproc->ofile[fd]);
      curproc->ofile[fd] = 0;
    }
  }

  begin_op();
  iput(curproc->cwd);
  end_op();
  curproc->cwd = 0;

  acquire(&ptable.lock);

  // Parent might be sleeping in wait().
  wakeup1(curproc->parent);

  // Pass abandoned children to init.
  for(p = ptable.proc; p < &ptable.proc[NPROC]; p++){
    if(p->parent == curproc){
      p->parent = initproc;
      if(p->state == ZOMBIE)
        wakeup1(initproc);
    }
  }

  // Jump into the scheduler, never to return.
  curproc->state = ZOMBIE;
  sched();
  panic("zombie exit");
}
```
- 首先須取得鎖。
- 喚醒父 process，將子 process 交給 *initproc*，修改狀態，呼叫 `sched` switch 至 scheduler。

---
### kill

| 功能 | 回傳值 | `pid` |
| --- | ------ | ----- |
| 使 process 終止 | 0 (ok) / -1 (err) | 欲終止的 process id |

```c =402
int
kill(int pid)
{
  struct proc *p;

  acquire(&ptable.lock);
  for(p = ptable.proc; p < &ptable.proc[NPROC]; p++){
    if(p->pid == pid){
      p->killed = 1;
      // Wake process from sleep if necessary.
      if(p->state == SLEEPING)
        p->state = RUNNABLE;
      release(&ptable.lock);
      return 0;
    }
  }
  release(&ptable.lock);
  return -1;
}
```
- `kill` 將 `p->killed` 設為 1 ，當此 process 發生中斷或是 system call 進入 kernel，離開時 `trap` 會檢查 `p->killed`，如果被設置了，則呼叫 `exit`。
- 當要被 kill 的 process 處於睡眠狀態，則喚醒它。 