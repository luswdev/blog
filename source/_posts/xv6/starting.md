---

title: "XV6 - Starting Process"
date: 2018-08-27 14:15:29 +0800
tag: [XV6, kernel]
category: XV6
---
>BIOS -> boot section -> main -> scheduler 的詳細流程在 [Ch1](/post/xv6/process.html)、[Ch5](/post/xv6/scheduler.html)及[Appendix B](/post/xv6/bootloader.html)，本文強調 CPU0 以外的 CPU 啟動流程及更詳細的 main 解析。

## Code: startothers
{% alert success %}
**File:** main.c
{% endalert %}

- 在 main 初始化一些設備後，會先呼叫 startothers，再呼叫 mpmain 來完成 cpu 的設定及呼叫 scheduler。

| 功能 | 回傳值 |
| --- | ------ |
| 啟動其他 CPU | void |

```c =68
static void
startothers(void)
{
  extern uchar _binary_entryother_start[], _binary_entryother_size[];
  uchar *code;
  struct cpu *c;
  char *stack;

  // Write entry code to unused memory at 0x7000.
  // The linker has placed the image of entryother.S in
  // _binary_entryother_start.
  code = p2v(0x7000);
  memmove(code, _binary_entryother_start, (uint)_binary_entryother_size);
```
- entryother.S 的入口被 linked 到 `0x7000`，這裡將 code 指向 `0x7000` 作為 entryother.S 的進入點。

```c =81
  for(c = cpus; c < cpus+ncpu; c++){
    if(c == cpus+cpunum())  // We've started already.
      continue;

    // Tell entryother.S what stack to use, where to enter, and what 
    // pgdir to use. We cannot use kpgdir yet, because the AP processor
    // is running in low  memory, so we use entrypgdir for the APs too.
    stack = kalloc();
    *(void**)(code-4) = stack + KSTACKSIZE;
    *(void**)(code-8) = mpenter;
    *(int**)(code-12) = (void *) v2p(entrypgdir);
```
- 為待會的 entryother 建立一個堆疊 ...


```c =92
    lapicstartap(c->id, v2p(code));
```
- 正式的啟動 CPU `c`，即進入 entryother.S
- entryother.S 做完設定後會呼叫 `mpenter()`，`mpmenter` 最後會呼叫 `mpmain()`。

```c =93
    // wait for cpu to finish mpmain()
    while(c->started == 0)
      ;
  }
}
```
- 在 `mpmain()` 會將 `cpu->started` 設為 `1`，CPU0 在 `while` 迴圈等待 CPU `c` 啟動完畢，才繼續啟動下一個 CPU。

---

| 功能 | 回傳值 |
| --- | ------ |
| 完成多核心啟動流程	 | void |

```c =46
static void
mpenter(void)
{
  switchkvm(); 
  seginit();
  lapicinit();
  mpmain();
}
```

---

| 功能 | 回傳值 |
| --- | ------ |
| 執行多核心任務 | void |

```c =56
static void
mpmain(void)
{
  cprintf("cpu%d: starting\n", cpu->id);
  idtinit();       // load idt register
  xchg(&cpu->started, 1); // tell startothers() we're up
  scheduler();     // start running processes
}
```

### lapicstartup

| 功能 | 回傳值 |
| --- | ------ |
| 啟動 lapic | void |

| `apicid` | `addr` |
| -------- | ------ |
| 欲啟動的 lapic | 填入的值 |

```c =137
void
lapicstartap(uchar apicid, uint addr)
{
  int i;
  ushort *wrv;
  
  // "The BSP must initialize CMOS shutdown code to 0AH
  // and the warm reset vector (DWORD based at 40:67) to point at
  // the AP startup code prior to the [universal startup algorithm]."
  outb(IO_RTC, 0xF);  // offset 0xF is shutdown code
  outb(IO_RTC+1, 0x0A);
  wrv = (ushort*)P2V((0x40<<4 | 0x67));  // Warm reset vector
  wrv[0] = 0;
  wrv[1] = addr >> 4;

  // "Universal startup algorithm."
  // Send INIT (level-triggered) interrupt to reset other CPU.
  lapicw(ICRHI, apicid<<24);
  lapicw(ICRLO, INIT | LEVEL | ASSERT);
  microdelay(200);
  lapicw(ICRLO, INIT | LEVEL);
  microdelay(100);    // should be 10ms, but too slow in Bochs!
  
  // Send startup IPI (twice!) to enter code.
  // Regular hardware is supposed to only accept a STARTUP
  // when it is in the halted state due to an INIT.  So the second
  // should be ignored, but it is part of the official Intel algorithm.
  // Bochs complains about the second one.  Too bad for Bochs.
  for(i = 0; i < 2; i++){
    lapicw(ICRHI, apicid<<24);
    lapicw(ICRLO, STARTUP | (addr>>12));
    microdelay(200);
  }
}
```


---
## Main 解析
### main

```c =17
int
main(void)
{
  kinit1(end, P2V(4*1024*1024)); // phys page allocator
  kvmalloc();      // kernel page table
  mpinit();        // collect info about this machine
  lapicinit();
  seginit();       // set up segments
  cprintf("\ncpu%d: starting XV6\n\n", cpu->id);
  picinit();       // interrupt controller
  ioapicinit();    // another interrupt controller
  consoleinit();   // I/O devices & their interrupts
  uartinit();      // serial port
  pinit();         // process table
  tvinit();        // trap vectors
  binit();         // buffer cache
  fileinit();      // file table
  iinit();         // inode cache
  ideinit();       // disk
  if(!ismp)
    timerinit();   // uniprocessor timer
  startothers();   // start other processors
  kinit2(P2V(4*1024*1024), P2V(PHYSTOP)); // must come after startothers()
  userinit();      // first user process
  // Finish setting up this processor in mpmain.
  mpmain();
}
```
- `kinit1()` [In ch2](/post/xv6/mem.html#kinit1-2)
- `kvmalloc()` [In Ch2](/post/xv6/mem.html#kvmalloc)
- `mpinit()`
- `lapicinit()`
- `seginit()`
- `picinit()`
- `ioapicinit()`
- `consoleinit()`
- `uartinit()`
- `pinit()`
- `tvinit()` [In Ch3](/post/xv6/trap.html#Code-Assembly-trap-handler)
- `binit()`
- `fileinit()`
- `iinit()`
- `ideinit()` [In Ch3](/post/xv6/trap.html#File-buf-h)
- `timerinit()`
- `startothers()` [Above](#Code-startothers)
- `kinit2()` [In ch2](/post/xv6/mem.html#kinit1-2)
- `userinit()` [In Ch1](/post/xv6/process.html#userinit)
- `mpmain()`