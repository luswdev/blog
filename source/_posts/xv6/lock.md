---
title: "XV6 - Locking"
date: 2018-08-07 14:14:40 +0800
tag: [XV6, kernel,lock]
category: XV6
---
## Race conditions
### 例子
```c
struct list{
    int data;
    struct list *next;
};

struct list *list = 0;

void
insert(int data)
{
    struct list *l;
    l = malloc(sizeof *l);
    l->data = data;
    l->next = list;
    list = l;
}
```

![](https://i.imgur.com/T6893f9.png "Example race")

假設現在有兩個 process 同時在不同的 CPU 上執行上述程式碼，當兩個 process 都執行到 14 行，則兩條鏈結的 `next` 都設置為 `list`；接著，先執行的 process A 將 list 設定為自己的鏈結，後執行的 process B 也將 list 設為自己的鏈結。

**問題**：此時 list 上將會遺失原本 process A `insert` 的節點。

### 圖解

- 一開始的 list

![](https://i.imgur.com/2eXmAxp.png)
- 假設 pocess A 及 B 同時將自己的資料插在 list 的第一顆
![](https://i.imgur.com/h95r477.png)
- 接著 process A 以些微的差距先將 list 設為 l（自己的鏈結），此時 list 的第一顆為 A 的資料
![](https://i.imgur.com/u0udl7M.png)
- 最後 process B 也將 list 設為 l，此時 list 上的第一顆為 B 的資料，且 A 的資料遺失了。
![](https://i.imgur.com/B659a3t.png)

### 使用鎖
```diff
struct list *list = 0;
struct lock listlock;

void
insert(int data)
{
    struct list *l;
+    acquire(&listlock);
    l = malloc(sizeof *l);
    l->data = data;
    l->next = list;
    list = l;
+    release(&listlock);
}
```

---
## Code: 鎖
- XV6 使用 `struct spinlock`，其中以 locked 作為標記。
    - 為 0 時，此鎖無人使用，可以被取用
    - **非** 0 時，此鎖有人在使用，無法被取用

### File: spinlock.h
```c =
// Mutual exclusion lock.
struct spinlock {
  uint locked;       // Is the lock held?
  
  // For debugging:
  char *name;        // Name of lock.
  struct cpu *cpu;   // The cpu holding the lock.
  uint pcs[10];      // The call stack (an array of program counters)
                     // that locked the lock.
};
```

- 邏輯上，`acquire` 應該長這樣：

```c
void
acquire(struct spinlock *lk)
{
    for(;;) {
        if(!lk->locked) {
            lk->locked = 1;
            break;
        }
    }
}
```
- 但是我們發現，可能會有多個 CPU 執行至第五行，發現 `lk->locked` 為 `0`，接著都拿到了鎖，即違反了互斥
- XV6 使用 x86 的特殊指令 `xchg` 來完成動作。

---

{% alert success %}
**File:** spinlock.c
{% endalert %}

| 功能 | 回傳值 | `*lk` |
| --- | ------ | ----- |
| 要求鎖 | void | 欲要求的鎖 |

```c =20
// Acquire the lock.
// Loops (spins) until the lock is acquired.
// Holding a lock for a long time may cause
// other CPUs to waste time spinning to acquire it.
void
acquire(struct spinlock *lk)
{
  pushcli(); // disable interrupts to avoid deadlock.
  if(holding(lk))
    panic("acquire");

  // The xchg is atomic.
  // It also serializes, so that reads after acquire are not
  // reordered before it. 
  while(xchg(&lk->locked, 1) != 0)
    ;

  // Record info about lock acquisition for debugging.
  lk->cpu = cpu;
  getcallerpcs(&lk, lk->pcs);
}
```

---

| 功能 | 回傳值 | `*lk` |
| --- | ------ | ----- |
| 還鎖 | void | 欲還的鎖 |

```c =42
// Release the lock.
void
release(struct spinlock *lk)
{
  if(!holding(lk))
    panic("release");

  lk->pcs[0] = 0;
  lk->cpu = 0;

  // The xchg serializes, so that reads before release are 
  // not reordered after it.  The 1996 PentiumPro manual (Volume 3,
  // 7.2) says reads can be carried out speculatively and in
  // any order, which implies we need to serialize here.
  // But the 2007 Intel 64 Architecture Memory Ordering White
  // Paper says that Intel 64 and IA-32 will not move a load
  // after a store. So lock->locked = 0 would work here.
  // The xchg being asm volatile ensures gcc emits it after
  // the above assignments (and after the critical section).
  xchg(&lk->locked, 0);

  popcli();
}
```

---

{% alert success %}
**File:** x86.h
{% endalert %}

| 功能 | 回傳值 |
| --- | ------ |
| 交換值（x86特殊指令） | 結果 |

| `*addr` | `newval` |
| -------- | ------- |
| 欲交換值得目標 | 欲填的值 |

```c =120
static inline uint
xchg(volatile uint *addr, uint newval)
{
  uint result;
  
  // The + in "+m" denotes a read-modify-write operand.
  asm volatile("lock; xchgl %0, %1" :
               "+m" (*addr), "=a" (result) :
               "1" (newval) :
               "cc");
  return result;
}
```