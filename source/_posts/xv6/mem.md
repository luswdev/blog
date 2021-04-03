---
title: "XV6 - Page Tables"
date: 2018-07-23 14:14:35 +0800
tag: [XV6, 記憶體管理, kernel]
category: XV6
---

{% alert success %}
**File:** vm.c
{% endalert %}

## 分頁硬體
- PTE：Page table entry，包含 20-bit PPN 及 flags
- PPN：Physical Page number
- x86的頁表：$2^{20}$ 條 PTE。

### 目標
>分頁硬體使用虛擬位址找到對應的 PTE，接著把高 20-bit 替換為 PTE 的 PPN，低 12-bit 直接沿用，即完成轉譯的動作。

### XV6 頁表
![](https://i.imgur.com/Jj4vbJt.png "x86 page table hardware.")
- 一個頁表在物理記憶體中為一顆兩層的樹
	- 樹根為一個 4096 字節的目錄（page dir），包含 1024 個類 PTE，分別指向不同的頁表頁（page table page）。
	- 每頁包含 1024 個 32-bit PTE。
- 轉譯過程
	1. 分頁硬體用虛擬地址的高 10-bit 找到指定的頁。
	2. 如果指向的頁存在的話，繼續使用接著的 10-bit 來找到指定的 PTE。
	3. 不存在的話，拋出錯誤。
	
### flags

| flags | name | 為 1 時 | 為 0 時 |
| :---: | ---- | ------ | ------- |
| P | Present | 表示頁存在 | 不存在 |
| W | Writable | 可以寫入 | 只能讀/取 |
| U | User | user 能使用此頁 | 只有 kernel 能使用 |
| WT | - | Write-through | Write-back |
| CD | Cache Disable | 不會對此頁進行 cache | 進行 cache |
| A | Accessed | 為 0 時被存取， 處理器會將此位設為 1 | - |
| D | Dirty | 為 0 時寫入此頁， 處理器會將此位設為 1 | - |
| AVL | Available for system use | - | - |

{% alert warning %}
只有軟體可以將 A、D 清 0。[^1]
{% endalert %}

[^1]:[記憶體管理／分頁架構](https://www.csie.ntu.edu.tw/~wcchen/asm98/asm/proj/b85506061/chap2/paging.html)

### 名詞解釋
- 物理記憶體：DRAM
- 物理地址：DRAM 的位址

---
## Process address space
- `main` 呼叫 `kvmalloc` 跳到新的頁表，重新映射至記憶體。
![](https://i.imgur.com/xq8Po1n.png "Layout of the virtual address space of a process and physical address space.")
- 每個 process 都有自己的頁表，在切換 process 時也會切換頁表。
- process 的頁表從 0 開始，最多至 `KERNBASE`，限制 process 最多使用 2GB。
- 如果需要更多記憶體時：
	1. XV6 先找到一個空的頁
	2. 將對應的 PTE 加入 process 的頁表裡
- 每個 process 的頁表都有包含對應的 kernel 映射（ `KERNBASE` 之上），這樣當發生中斷時就不需要切換頁表。
- `KERNBASE` 之上的頁對應的 PTE，PTE_U 均設為 0。

---
## Code: 建立 address space
- `main` 呼叫 `kvmalloc` 來建立 `KERNBASE` 之上的頁表

### kvmalloc
| 功能 | 回傳值 |
| --- | ------ |
| 建立 kernel page | void |

```c =146
// Allocate one page table for the machine for the kernel address
// space for scheduler processes.
void
kvmalloc(void)
{
  kpgdir = setupkvm();
  switchkvm();
}
```
- 建立頁表的工作由 `setupkvm` 完成

### setupkvm

| 功能 | 回傳值 |
| --- | ------ |
| 設定 kernel page | PDE |

```c =155
// Set up kernel part of a page table.
pde_t*
setupkvm(void)
{
  pde_t *pgdir;
  struct kmap *k;

  if((pgdir = (pde_t*)kalloc()) == 0)
    return 0;
```
- 首先分配一頁來存放目錄

```c =164
  memset(pgdir, 0, PGSIZE);
  if (p2v(PHYSTOP) > (void*)DEVSPACE)
    panic("PHYSTOP too high");
  for(k = kmap; k < &kmap[NELEM(kmap)]; k++)
    if(mappages(pgdir, k->virt, k->phys_end - k->phys_start, 
                (uint)k->phys_start, k->perm) < 0)
      return 0;
  return pgdir;
}
```
- 接著呼叫 `mappages` 來建立 kernel 所需的映射。
- 映射存放在 kmap 裡

---

```c =113
// This table defines the kernel's mappings, which are present in
// every process's page table.
static struct kmap {
  void *virt;
  uint phys_start;
  uint phys_end;
  int perm;
} kmap[] = {
 { (void*)KERNBASE, 0,             EXTMEM,    PTE_W}, // I/O space
 { (void*)KERNLINK, V2P(KERNLINK), V2P(data), 0},     // kern text+rodata
 { (void*)data,     V2P(data),     PHYSTOP,   PTE_W}, // kern data+memory
 { (void*)DEVSPACE, DEVSPACE,      0,         PTE_W}, // more devices
};
```
- kmap 包含 kernel 的資料及指令、`PHYTOP`以下的物理記憶體、及 I/O 設備的記憶體。
- 這裡不會建立有關 user 的映射

### mappages
| 功能 | 回傳值 |
| --- | ------ |
| 設定 PTE | 0 (ok) / -1 (err)  |

```c =67
// Create PTEs for virtual addresses starting at va that refer to
// physical addresses starting at pa. va and size might not
// be page-aligned.
static int
mappages(pde_t *pgdir, void *va, uint size, uint pa, int perm)
{
  char *a, *last;
  pte_t *pte;
  
  a = (char*)PGROUNDDOWN((uint)va);
  last = (char*)PGROUNDDOWN(((uint)va) + size - 1);
  for(;;){
    if((pte = walkpgdir(pgdir, a, 1)) == 0)
      return -1;
```
- 首先呼叫 `walkpgdir` 來找到對應的 PTE

```c =81
    if(*pte & PTE_P)
      panic("remap");
```
- 接著確認 PTE_P flags

```c =83
    *pte = pa | perm | PTE_P;
    if(a == last)
      break;
    a += PGSIZE;
    pa += PGSIZE;
  }
  return 0;
}
```
- 最後初始化 PTE。
- 問題：如何初始化

---

| 功能 | 回傳值 |
| --- | ------ |
| 從目錄尋找對應的 PTE | PTE |

| `*pgdir` | `*va` | `alloc` |
| -------- | ----- | ------- |
| 目標目錄 | 目標虛擬地址 | 是否有 alloc |

```c =42
// Return the address of the PTE in page table pgdir
// that corresponds to virtual address va.  If alloc!=0,
// create any required page table pages.
static pte_t *
walkpgdir(pde_t *pgdir, const void *va, int alloc)
{
  pde_t *pde;
  pte_t *pgtab;

  pde = &pgdir[PDX(va)];
  if(*pde & PTE_P){
    pgtab = (pte_t*)p2v(PTE_ADDR(*pde));
  } else {
    if(!alloc || (pgtab = (pte_t*)kalloc()) == 0)
      return 0;
    // Make sure all those PTE_P bits are zero.
    memset(pgtab, 0, PGSIZE);
    // The permissions here are overly generous, but they can
    // be further restricted by the permissions in the page table 
    // entries, if necessary.
    *pde = v2p(pgtab) | PTE_P | PTE_W | PTE_U;
  }
  return &pgtab[PTX(va)];
}
```

| 功能 | 回傳值 |
| --- | ------ |
| 切換至 kernel 頁 | void |

```c =155
void
switchkvm(void)
{
  lcr3(v2p(kpgdir));   // switch to the kernel page table
}
```

---
## 分配物理記憶體
kernel 在運行時須為以下物件分配物理記憶體：

- Page table
- Process 的 user 記憶體
- kernel stack
- Pipe buffers

## Code: 物理記憶體分配器

{% alert success %}
**File:** kalloc.c
{% endalert %}

- 分配器為一個可分配的記憶體頁所構成的 **free list**
- `main` 呼叫 `kinit1(end, P2V(4*1024*1024))` 及 `kinit2(P2V(4*1024*1024), P2V(PHYSTOP))` 初始化分配器

### kinit1 / 2

| 功能 | 回傳值 |
| --- | ------ |
| 初始化物理記憶體分配器 | void |

| `*vstart` | `*vend` |
| --------- | ------- |
| 起始位址 | 結束位址 |

```c =25
void
kinit1(void *vstart, void *vend)
{
  initlock(&kmem.lock, "kmem");
  kmem.use_lock = 0;
  freerange(vstart, vend);
}
```

---

| 功能 | 回傳值 |
| --- | ------ |
| 初始化物理記憶體分配器 | void |

| `*vstart` | `*vend` |
| --------- | ------- |
| 起始位址 | 結束位址 |

```c =38
void
kinit2(void *vstart, void *vend)
{
  freerange(vstart, vend);
  kmem.use_lock = 1;
}
```
- `kinit1 / 2` 呼叫 `freerange` 將記憶體加入 free list

### freerange

| 功能 | 回傳值 |
| --- | ------ |
| 釋放一段記憶體 | void |

| `*vstart` | `*vend` |
| --------- | ------- |
| 起始位址 | 結束位址 |

```c =45
void
freerange(void *vstart, void *vend)
{
  char *p;
  p = (char*)PGROUNDUP((uint)vstart);
  for(; p + PGSIZE <= (char*)vend; p += PGSIZE)
    kfree(p);
}
```
- `freerange` 呼叫 `kfree` 來完成工作

### kfree

| 功能 | 回傳值 | `*v` |
| --- | ------ | ---- |
| 釋放記憶體 | void | 欲 free 的虛擬地址 |

```c =54
//PAGEBREAK: 21
// Free the page of physical memory pointed at by v,
// which normally should have been returned by a
// call to kalloc().  (The exception is when
// initializing the allocator; see kinit above.)
void
kfree(char *v)
{
  struct run *r;

  if((uint)v % PGSIZE || v < end || v2p(v) >= PHYSTOP)
    panic("kfree");

  // Fill with junk to catch dangling refs.
  memset(v, 1, PGSIZE);
```
- 首先將每個字節設為 1

```c =69
  if(kmem.use_lock)
    acquire(&kmem.lock);
  r = (struct run*)v;
  r->next = kmem.freelist;
  kmem.freelist = r;
  if(kmem.use_lock)
    release(&kmem.lock);
}
```
- 接著把 v 轉為 `struct run` 的指標，插在 free list 的第一顆。

---
## User part of an address space
![](https://i.imgur.com/sZaPwda.png "Memory layout of a user process with its initial stack")

## Code: sbrk

{% alert success %}
**File:** sysproc.c
{% endalert %}

| 功能 | 回傳值 |
| --- | ------ |
| 增長/收縮 process 的記憶體 | 記憶體大小（結果） |

```c =44
int
sys_sbrk(void)
{
  int addr;
  int n;

  if(argint(0, &n) < 0)
    return -1;
  addr = proc->sz;
  if(growproc(n) < 0)
    return -1;
  return addr;
}
```

- `sbrk` 透過呼叫 `growproc` 來完成工作。

---

{% alert success %}
**File:** proc.c
{% endalert %}

| 功能 | 回傳值 | `n` |
| --- | ------ | --- |
| 增長/收縮 process 的記憶體 | 0 (ok) / -1 (err) | 增長/收縮大小 |

```c =105
// Grow current process's memory by n bytes.
// Return 0 on success, -1 on failure.
int
growproc(int n)
{
  uint sz;
  
  sz = proc->sz;
  if(n > 0){
    if((sz = allocuvm(proc->pgdir, sz, sz + n)) == 0)
      return -1;
  } else if(n < 0){
    if((sz = deallocuvm(proc->pgdir, sz, sz + n)) == 0)
      return -1;
  }
  proc->sz = sz;
  switchuvm(proc);
  return 0;
}
```

- 如果 n>0：`allocuvm`
- 如果 n<0：`deallocuvm`

---
### allocuvm、deallocuvm

{% alert success %}
**File:** vm.c
{% endalert %}

| 功能 | 回傳值 |
| --- | ------ |
| 增長記憶體 | 記憶體大小（結果）|

| `*pgdir` | `oldsz` | `newsz` |
| -------- | ------- | ------- |
| 從該目錄尋找可用記憶體 | 舊的大小 | 新的大小 |

```c =218
// Allocate page tables and physical memory to grow process from oldsz to
// newsz, which need not be page aligned.  Returns new size or 0 on error.
int
allocuvm(pde_t *pgdir, uint oldsz, uint newsz)
{
  char *mem;
  uint a;

  if(newsz >= KERNBASE)
    return 0;
  if(newsz < oldsz)
    return oldsz;

```

- 首先檢查是否有要超過大小，及動作是否合法。

```c =231
  a = PGROUNDUP(oldsz);
  for(; a < newsz; a += PGSIZE){
    mem = kalloc();
    if(mem == 0){
      cprintf("allocuvm out of memory\n");
      deallocuvm(pgdir, newsz, oldsz);
      return 0;
    }
    memset(mem, 0, PGSIZE);
    mappages(pgdir, (char*)a, PGSIZE, v2p(mem), PTE_W|PTE_U);
  }
  return newsz;
}
```

- 接著透過 `kalloc()` 來要記憶體，並將要到的記憶體清空
- 最後回傳 process 目前總共的大小
---

| 功能 | 回傳值 |
| --- | ------ |
| 縮減記憶體 | 記憶體大小（結果）|

| `*pgdir` | `oldsz` | `newsz` |
| -------- | ------- | ------- |
| 從該目錄釋放記憶體 | 舊的大小 | 新的大小 |

```c =245
// Deallocate user pages to bring the process size from oldsz to
// newsz.  oldsz and newsz need not be page-aligned, nor does newsz
// need to be less than oldsz.  oldsz can be larger than the actual
// process size.  Returns the new process size.
int
deallocuvm(pde_t *pgdir, uint oldsz, uint newsz)
{
  pte_t *pte;
  uint a, pa;

  if(newsz >= oldsz)
    return oldsz;

```

- 一樣先檢查動作是否合法

```c =258
  a = PGROUNDUP(newsz);
  for(; a  < oldsz; a += PGSIZE){
    pte = walkpgdir(pgdir, (char*)a, 0);
    if(!pte)
      a += (NPTENTRIES - 1) * PGSIZE;
    else if((*pte & PTE_P) != 0){
      pa = PTE_ADDR(*pte);
      if(pa == 0)
        panic("kfree");
      char *v = p2v(pa);
      kfree(v);
      *pte = 0;
    }
  }
  return newsz;
}
```

- 接著一個一個 pte free，先將 flags 歸0，再透過 `kfree` 完成工作。

---
## Code: exec
- 功用：創建 user part address space
- 概觀：打開及讀取 ELF 文件來初始化 user part

### struct elfhdr

{% alert success %}
**File:** elf.h
{% endalert %}

```c =5
// File header
struct elfhdr {
  uint magic;  // must equal ELF_MAGIC
  uchar elf[12];
  ushort type;
  ushort machine;
  uint version;
  uint entry;
  uint phoff;
  uint shoff;
  uint flags;
  ushort ehsize;
  ushort phentsize;
  ushort phnum;
  ushort shentsize;
  ushort shnum;
  ushort shstrndx;
};
```
- 一個 ELF 文件包含一個 elfhdr、program setion hdr(struct proghdr)

### struct proghdr
```c =24
// Program section header
struct proghdr {
  uint type;
  uint off;
  uint vaddr;
  uint paddr;
  uint filesz;
  uint memsz;
  uint flags;
  uint align;
};
```
- 一個 proghdr 描述了須載入至記憶體的 program section

{% alert info %}
XV6 的 program 只有一個 section，其他 OS 可能會有多個。
{% endalert %}

### File: exec.c 
```c =
#include "types.h"
#include "param.h"
#include "memlayout.h"
#include "mmu.h"
#include "proc.h"
#include "defs.h"
#include "x86.h"
#include "elf.h"

int
exec(char *path, char **argv)
{
  char *s, *last;
  int i, off;
  uint argc, sz, sp, ustack[3+MAXARG+1];
  struct elfhdr elf;
  struct inode *ip;
  struct proghdr ph;
  pde_t *pgdir, *oldpgdir;

  if((ip = namei(path)) == 0)
    return -1;
```
- 用 `namei` 打開二進制文件（ch6 會說明）

```c =23
  ilock(ip);
  pgdir = 0;

  // Check ELF header
  if(readi(ip, (char*)&elf, 0, sizeof(elf)) < sizeof(elf))
    goto bad;
  if(elf.magic != ELF_MAGIC)
    goto bad;
```
- 接著確認 ELF 是否正確（藉由 ELF_magic）

```c =31
  if((pgdir = setupkvm()) == 0)
    goto bad;

  // Load program into memory.
  sz = 0;
  for(i=0, off=elf.phoff; i<elf.phnum; i++, off+=sizeof(ph)){
    if(readi(ip, (char*)&ph, off, sizeof(ph)) != sizeof(ph))
      goto bad;
    if(ph.type != ELF_PROG_LOAD)
      continue;
    if(ph.memsz < ph.filesz)
      goto bad;
    if((sz = allocuvm(pgdir, sz, ph.vaddr + ph.memsz)) == 0)
      goto bad;
    if(loaduvm(pgdir, (char*)ph.vaddr, ip, ph.off, ph.filesz) < 0)
      goto bad;
  }
  iunlockput(ip);
  ip = 0;
```

1. `setupkvm` 分配一個沒有 user part 的頁
2. `allocuvm` 分配給每個 ELF 的 program section 記憶體。
3. `loaduvm` 將 section 載入至記憶體

```c =50
  // Allocate two pages at the next page boundary.
  // Make the first inaccessible.  Use the second as the user stack.
  sz = PGROUNDUP(sz);
  if((sz = allocuvm(pgdir, sz, sz + 2*PGSIZE)) == 0)
    goto bad;
  clearpteu(pgdir, (char*)(sz - 2*PGSIZE));
  sp = sz;

  // Push argument strings, prepare rest of stack in ustack.
  for(argc = 0; argv[argc]; argc++) {
    if(argc >= MAXARG)
      goto bad;
    sp = (sp - (strlen(argv[argc]) + 1)) & ~3;
    if(copyout(pgdir, sp, argv[argc], strlen(argv[argc]) + 1) < 0)
      goto bad;
    ustack[3+argc] = sp;
  }
  ustack[3+argc] = 0;

  ustack[0] = 0xffffffff;  // fake return PC
  ustack[1] = argc;
  ustack[2] = sp - (argc+1)*4;  // argv pointer

  sp -= (3+argc+1) * 4;
  if(copyout(pgdir, sp, ustack, (3+argc+1)*4) < 0)
    goto bad;

  // Save program name for debugging.
  for(last=s=path; *s; s++)
    if(*s == '/')
      last = s+1;
  safestrcpy(proc->name, last, sizeof(proc->name));

  // Commit to the user image.
  oldpgdir = proc->pgdir;
  proc->pgdir = pgdir;
  proc->sz = sz;
  proc->tf->eip = elf.entry;  // main
  proc->tf->esp = sp;
  switchuvm(proc);
  freevm(oldpgdir);
  return 0;

 bad:
  if(pgdir)
    freevm(pgdir);
  if(ip)
    iunlockput(ip);
  return -1;
}
```