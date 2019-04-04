---
title: RT-Thread 小記憶體動態管理 
tag: [RT-Thread, 記憶體管理, kernel]
date: 2018-12-05 15:42:44
category: RT-Thread
summary: RTT 記憶體管理 4，專門給總記憶體小的情況使用
---
>File: mem.c

- 與 memory heap 的做法類似，一開始是一塊大的記憶體，包含 header
- 分配記憶體時適當的切割
- 所有的記憶體塊透過 header 串起來，形成一個雙向鏈結

![](https://i.imgur.com/tbptSYO.png "small memory example")

>使用此管理方式： `#defined RT_USING_HEAP && #defined RT_USING_SMALL_MEM`

## 結構
```c 
struct heap_mem
{
    /* magic and used flag */
    rt_uint16_t magic;
    rt_uint16_t used;

    rt_size_t next, prev;

#ifdef RT_USING_MEMTRACE
    rt_uint8_t thread[4];   /* thread name */
#endif
};
```

- 此結構即為一個記憶體塊的 header
- 包含了
    - magic 碼 `0x1ea0`
    - 使用中標記
    - 前一顆與下一顆
    - 使用此記憶體的 thread 名稱（選）

---
## 初始化 heap
<i class="fa fa-code"></i> Code: `rt_system_heap_init`

| 功能 | 回傳值 |
| --- | ------ |
| 初始化 heap | void |

| `*begin_addr` | `*end_addr` |
| ------------- | ----------- |
| 記憶體起始位址 | 結束位址 |

```c 
/**
 * @ingroup SystemInit
 *
 * This function will initialize system heap memory.
 *
 * @param begin_addr the beginning address of system heap memory.
 * @param end_addr the end address of system heap memory.
 */
void rt_system_heap_init(void *begin_addr, void *end_addr)
{
    struct heap_mem *mem;
    rt_uint32_t begin_align = RT_ALIGN((rt_uint32_t)begin_addr, RT_ALIGN_SIZE);
    rt_uint32_t end_align = RT_ALIGN_DOWN((rt_uint32_t)end_addr, RT_ALIGN_SIZE);

    RT_DEBUG_NOT_IN_INTERRUPT;
```

- 向上對齊起始位址與向下對齊結束位址

```c 
    /* alignment addr */
    if ((end_align > (2 * SIZEOF_STRUCT_MEM)) &&
        ((end_align - 2 * SIZEOF_STRUCT_MEM) >= begin_align))
    {
        /* calculate the aligned memory size */
        mem_size_aligned = end_align - begin_align - 2 * SIZEOF_STRUCT_MEM;
    }
    else
    {
        rt_kprintf("mem init, error begin address 0x%x, and end address 0x%x\n",
                   (rt_uint32_t)begin_addr, (rt_uint32_t)end_addr);

        return;
    }
```

- 接著檢查起始與結束位址是否合法
- 如果合法，給定 `mem_size` 為結束位址 - 起始位址 - 2 倍的 `struct mem` 大小
- 也就是與 `mem_heap` 相同，一開始的記憶體設定為一大塊，頭與尾都要有一個 header

```c 
    /* point to begin address of heap */
    heap_ptr = (rt_uint8_t *)begin_align;

    RT_DEBUG_LOG(RT_DEBUG_MEM, ("mem init, heap begin address 0x%x, size %d\n",
                                (rt_uint32_t)heap_ptr, mem_size_aligned));

    /* initialize the start of the heap */
    mem        = (struct heap_mem *)heap_ptr;
    mem->magic = HEAP_MAGIC;
    mem->next  = mem_size_aligned + SIZEOF_STRUCT_MEM;
    mem->prev  = 0;
    mem->used  = 0;
```

- 接著設定前面的 header：
    - 設定 magic 碼
    - 下一塊為結尾的 header
    - 上一塊為自己
    - 以及沒有使用過

```c 
#ifdef RT_USING_MEMTRACE
    rt_mem_setname(mem, "INIT");
#endif

    /* initialize the end of the heap */
    heap_end        = (struct heap_mem *)&heap_ptr[mem->next];
    heap_end->magic = HEAP_MAGIC;
    heap_end->used  = 1;
    heap_end->next  = mem_size_aligned + SIZEOF_STRUCT_MEM;
    heap_end->prev  = mem_size_aligned + SIZEOF_STRUCT_MEM;
#ifdef RT_USING_MEMTRACE
    rt_mem_setname(heap_end, "INIT");
#endif
```

- 接著設定結尾的 header
    - magic 碼
    - 已被使用過
    - 上一塊與下一塊指向自己

```c 
    rt_sem_init(&heap_sem, "heap", 1, RT_IPC_FLAG_FIFO);

    /* initialize the lowest-free pointer to the start of the heap */
    lfree = (struct heap_mem *)heap_ptr;
}
```

- 最後初始化 semaphore
- 把這一塊掛上 `lfree`

---
## 分配記憶體
### Code: rt_malloc

| 功能 | 回傳值 |
| --- | ------ |
| 要求記憶體 | 記憶體位址 |

| `size` |
| ------ |
| 欲要求的大小 |

```c 
/**
 * Allocate a block of memory with a minimum of 'size' bytes.
 *
 * @param size is the minimum size of the requested block in bytes.
 *
 * @return pointer to allocated memory or NULL if no free memory was found.
 */
void *rt_malloc(rt_size_t size)
{
    rt_size_t ptr, ptr2;
    struct heap_mem *mem, *mem2;

    if (size == 0)
        return RT_NULL;
```

- 如果 `size` 為 0，回傳 NULL

```c 
    RT_DEBUG_NOT_IN_INTERRUPT;

    if (size != RT_ALIGN(size, RT_ALIGN_SIZE))
        RT_DEBUG_LOG(RT_DEBUG_MEM, ("malloc size %d, but align to %d\n",
                                    size, RT_ALIGN(size, RT_ALIGN_SIZE)));
    else
        RT_DEBUG_LOG(RT_DEBUG_MEM, ("malloc size %d\n", size));

    /* alignment size */
    size = RT_ALIGN(size, RT_ALIGN_SIZE);

    if (size > mem_size_aligned)
    {
        RT_DEBUG_LOG(RT_DEBUG_MEM, ("no memory\n"));

        return RT_NULL;
    }
```

- 向上對齊 `size`，如果超過可用大小，回傳 NULL

```c 
    /* every data block must be at least MIN_SIZE_ALIGNED long */
    if (size < MIN_SIZE_ALIGNED)
        size = MIN_SIZE_ALIGNED;
```

- 如果小於 min size，設為 min size

```c 
    /* take memory semaphore */
    rt_sem_take(&heap_sem, RT_WAITING_FOREVER);
```

- 取得 semaphore

```c 
    for (ptr = (rt_uint8_t *)lfree - heap_ptr;
         ptr < mem_size_aligned - size;
         ptr = ((struct heap_mem *)&heap_ptr[ptr])->next)
    {
        mem = (struct heap_mem *)&heap_ptr[ptr];
```

這裡特別的說明一下 for 迴圈：
首先起點是 `lfree` - `heap_ptr`，這裡代表最左邊的 free block 與 heap 起點的距離。 我們把 `heap_ptr` 看成是一個 `rt_uint8_t` 的陣列，也就是一格一個 byte 的陣列。 再來把 `lfree` - `heap_ptr` 看成是差量 (offset)，單位是 byte。 如此一來，`&heap_ptr[ptr]` 就會是 `lfree` 的起始位置了。

再來我們看 `next`，在初始化的時候，`next` 是指向 0，這個意思是下一顆在陣列的第 0 個，也就是自己；所以 `next` 存放的是下一顆的 index，而不是起始位置。

最後來看上界，理論上我們需要從 lfree 找到最後一顆，實際上如果最後幾顆不夠大的話是不需要檢查的，所以這裡上界設在 `mem_size_aligned` - `size` 的意思就是說如果最後幾顆的大小總和不夠大，我們可以略過。

```c 
        if ((!mem->used) && (mem->next - (ptr + SIZEOF_STRUCT_MEM)) >= size)
        {
            /* mem is not used and at least perfect fit is possible:
             * mem->next - (ptr + SIZEOF_STRUCT_MEM) gives us the 'user data size' of mem */
```

- first fit，如果找到第一顆可用的就進去

```c 
            if (mem->next - (ptr + SIZEOF_STRUCT_MEM) >=
                (size + SIZEOF_STRUCT_MEM + MIN_SIZE_ALIGNED))
            {
```

- 又，如果這顆夠大到可以切割的話

```c 
                /* (in addition to the above, we test if another struct heap_mem (SIZEOF_STRUCT_MEM) containing
                 * at least MIN_SIZE_ALIGNED of data also fits in the 'user data space' of 'mem')
                 * -> split large block, create empty remainder,
                 * remainder must be large enough to contain MIN_SIZE_ALIGNED data: if
                 * mem->next - (ptr + (2*SIZEOF_STRUCT_MEM)) == size,
                 * struct heap_mem would fit in but no data between mem2 and mem2->next
                 * @todo we could leave out MIN_SIZE_ALIGNED. We would create an empty
                 *       region that couldn't hold data, but when mem->next gets freed,
                 *       the 2 regions would be combined, resulting in more free memory
                 */
                ptr2 = ptr + SIZEOF_STRUCT_MEM + size;

                /* create mem2 struct */
                mem2       = (struct heap_mem *)&heap_ptr[ptr2];
                mem2->magic = HEAP_MAGIC;
                mem2->used = 0;
                mem2->next = mem->next;
                mem2->prev = ptr;
#ifdef RT_USING_MEMTRACE
                rt_mem_setname(mem2, "    ");
#endif
```

- 設定下一顆的資料，同時把 `next` 與 `prev` 接到正確位置

```c 
                /* and insert it between mem and mem->next */
                mem->next = ptr2;
                mem->used = 1;

                if (mem2->next != mem_size_aligned + SIZEOF_STRUCT_MEM)
                {
                    ((struct heap_mem *)&heap_ptr[mem2->next])->prev = ptr2;
                }
```

- 接著把原本那塊的 `next` 指向新的那塊，設為使用中
- 如果新的那塊 `next` 不是最後一塊，設定 `prev`

```c 
#ifdef RT_MEM_STATS
                used_mem += (size + SIZEOF_STRUCT_MEM);
                if (max_mem < used_mem)
                    max_mem = used_mem;
#endif
            }
```

- 最後更新 `used_mem` 與 `max_mem`

```c            
            else
            {
                /* (a mem2 struct does no fit into the user data space of mem and mem->next will always
                 * be used at this point: if not we have 2 unused structs in a row, plug_holes should have
                 * take care of this).
                 * -> near fit or excact fit: do not split, no mem2 creation
                 * also can't move mem->next directly behind mem, since mem->next
                 * will always be used at this point!
                 */
                mem->used = 1;
#ifdef RT_MEM_STATS
                used_mem += mem->next - ((rt_uint8_t *)mem - heap_ptr);
                if (max_mem < used_mem)
                    max_mem = used_mem;
#endif
            }
```

- 如果不可切割，只需設定使用中即可

```c    
            /* set memory block magic */
            mem->magic = HEAP_MAGIC;
#ifdef RT_USING_MEMTRACE
            if (rt_thread_self())
                rt_mem_setname(mem, rt_thread_self()->name);
            else
                rt_mem_setname(mem, "NONE");
#endif

            if (mem == lfree)
            {
                /* Find next free block after mem and update lowest free pointer */
                while (lfree->used && lfree != heap_end)
                    lfree = (struct heap_mem *)&heap_ptr[lfree->next];

                RT_ASSERT(((lfree == heap_end) || (!lfree->used)));
            }
```

- 視情況更新 `lfree`

```c 
            rt_sem_release(&heap_sem);
            RT_ASSERT((rt_uint32_t)mem + SIZEOF_STRUCT_MEM + size <= (rt_uint32_t)heap_end);
            RT_ASSERT((rt_uint32_t)((rt_uint8_t *)mem + SIZEOF_STRUCT_MEM) % RT_ALIGN_SIZE == 0);
            RT_ASSERT((((rt_uint32_t)mem) & (RT_ALIGN_SIZE - 1)) == 0);

            RT_DEBUG_LOG(RT_DEBUG_MEM,
                         ("allocate memory at 0x%x, size: %d\n",
                          (rt_uint32_t)((rt_uint8_t *)mem + SIZEOF_STRUCT_MEM),
                          (rt_uint32_t)(mem->next - ((rt_uint8_t *)mem - heap_ptr))));

            RT_OBJECT_HOOK_CALL(rt_malloc_hook,
                                (((void *)((rt_uint8_t *)mem + SIZEOF_STRUCT_MEM)), size));

            /* return the memory data except mem struct */
            return (rt_uint8_t *)mem + SIZEOF_STRUCT_MEM;
        }
    }
```

- 還鎖，並回傳找到的記憶體位址

```c 
    rt_sem_release(&heap_sem);

    return RT_NULL;
}
RTM_EXPORT(rt_malloc);
```

- 沒找到一樣還鎖，並回傳 NULL

---
### Code: rt_realloc

| 功能 | 回傳值 |
| --- | ------ |
| 增長/縮減記憶體 | 記憶體位址 |

| `*rmeme` | `newsize` |
| -------- | --------- |
| 欲增長/縮減的記憶體位址 | 新的大小 |

```c 
/**
 * This function will change the previously allocated memory block.
 *
 * @param rmem pointer to memory allocated by rt_malloc
 * @param newsize the required new size
 *
 * @return the changed memory block address
 */
void *rt_realloc(void *rmem, rt_size_t newsize)
{
    rt_size_t size;
    rt_size_t ptr, ptr2;
    struct heap_mem *mem, *mem2;
    void *nmem;

    RT_DEBUG_NOT_IN_INTERRUPT;

    /* alignment size */
    newsize = RT_ALIGN(newsize, RT_ALIGN_SIZE);
    if (newsize > mem_size_aligned)
    {
        RT_DEBUG_LOG(RT_DEBUG_MEM, ("realloc: out of memory\n"));

        return RT_NULL;
    }
    else if (newsize == 0)
    {
        rt_free(rmem);
        return RT_NULL;
    }
```

- 向上對齊 size，如果：
    - 大於可用大小，回傳 NULL
    - 等於 0，free 記憶體，回傳 NULL

```c  
    /* allocate a new memory block */
    if (rmem == RT_NULL)
        return rt_malloc(newsize);
```

- 如原來的記憶體為空，直接 `malloc`，並回傳

```c 
    rt_sem_take(&heap_sem, RT_WAITING_FOREVER);

    if ((rt_uint8_t *)rmem < (rt_uint8_t *)heap_ptr ||
        (rt_uint8_t *)rmem >= (rt_uint8_t *)heap_end)
    {
        /* illegal memory */
        rt_sem_release(&heap_sem);

        return rmem;
    }
```

- 接著取得鎖，檢查傳入的記憶體是否合法

```c 
    mem = (struct heap_mem *)((rt_uint8_t *)rmem - SIZEOF_STRUCT_MEM);

    ptr = (rt_uint8_t *)mem - heap_ptr;
    size = mem->next - ptr - SIZEOF_STRUCT_MEM;
    if (size == newsize)
    {
        /* the size is the same as */
        rt_sem_release(&heap_sem);

        return rmem;
    }
```

- 找到記憶體塊的起始位址，算出 size，如果記憶體大小不需要變動，不做事，回傳原本的記憶體位址

```c 
    if (newsize + SIZEOF_STRUCT_MEM + MIN_SIZE < size)
    {
        /* split memory block */
#ifdef RT_MEM_STATS
        used_mem -= (size - newsize);
#endif

        ptr2 = ptr + SIZEOF_STRUCT_MEM + newsize;
        mem2 = (struct heap_mem *)&heap_ptr[ptr2];
        mem2->magic = HEAP_MAGIC;
        mem2->used = 0;
        mem2->next = mem->next;
        mem2->prev = ptr;
#ifdef RT_USING_MEMTRACE
        rt_mem_setname(mem2, "    ");
#endif
        mem->next = ptr2;
        if (mem2->next != mem_size_aligned + SIZEOF_STRUCT_MEM)
        {
            ((struct heap_mem *)&heap_ptr[mem2->next])->prev = ptr2;
        }
```

- 如果可以切割，與上面的動作相同

```c 
        plug_holes(mem2);

        rt_sem_release(&heap_sem);

        return rmem;
    }
```

-使用 `plug_holes` 來合併 free block
- 還鎖，回傳更新後的記憶體位置

```c 
    rt_sem_release(&heap_sem);

    /* expand memory */
    nmem = rt_malloc(newsize);
    if (nmem != RT_NULL) /* check memory */
    {
        rt_memcpy(nmem, rmem, size < newsize ? size : newsize);
        rt_free(rmem);
    }

    return nmem;
}
RTM_EXPORT(rt_realloc);
```

- 如果不可切割，或是需要增長，直接要一塊 new size，釋放原本的記憶體
- 最後回傳新的記憶體位址

---
<i class="fa fa-code"></i> Code: `plug_holes`

| 功能 | 回傳值 |
| --- | ------ |
| 合併 free block | void |

| `*mem` |
| ------ |
| 欲合併的記憶體位址 |

```c 
static void plug_holes(struct heap_mem *mem)
{
    struct heap_mem *nmem;
    struct heap_mem *pmem;

    RT_ASSERT((rt_uint8_t *)mem >= heap_ptr);
    RT_ASSERT((rt_uint8_t *)mem < (rt_uint8_t *)heap_end);
    RT_ASSERT(mem->used == 0);

    /* plug hole forward */
    nmem = (struct heap_mem *)&heap_ptr[mem->next];
    if (mem != nmem &&
        nmem->used == 0 &&
        (rt_uint8_t *)nmem != (rt_uint8_t *)heap_end)
    {
        /* if mem->next is unused and not end of heap_ptr,
         * combine mem and mem->next
         */
        if (lfree == nmem)
        {
            lfree = mem;
        }
        mem->next = nmem->next;
        ((struct heap_mem *)&heap_ptr[nmem->next])->prev = (rt_uint8_t *)mem - heap_ptr;
    }
```

- 如果可以與下一顆合併
- 檢查是否需要更新 `lfree`
- 重新接上 `next` 與 `prev` (`next` 的 `prev`)

```c 
    /* plug hole backward */
    pmem = (struct heap_mem *)&heap_ptr[mem->prev];
    if (pmem != mem && pmem->used == 0)
    {
        /* if mem->prev is unused, combine mem and mem->prev */
        if (lfree == mem)
        {
            lfree = pmem;
        }
        pmem->next = mem->next;
        ((struct heap_mem *)&heap_ptr[mem->next])->prev = (rt_uint8_t *)pmem - heap_ptr;
    }
}
```

- 如果可以與上一顆合併，動作一樣

---
### Code: rt_calloc

| 功能 | 回傳值 |
| --- | ------ |
| 要求一段連續的記憶體 | 記憶體位址 |

| `count` | `size` |
| ------- | ------ |
| 欲要求的數量 | 一塊的大小 |

```c 
/**
 * This function will contiguously allocate enough space for count objects
 * that are size bytes of memory each and returns a pointer to the allocated
 * memory.
 *
 * The allocated memory is filled with bytes of value zero.
 *
 * @param count number of objects to allocate
 * @param size size of the objects to allocate
 *
 * @return pointer to allocated memory / NULL pointer if there is an error
 */
void *rt_calloc(rt_size_t count, rt_size_t size)
{
    void *p;

    /* allocate 'count' objects of size 'size' */
    p = rt_malloc(count * size);

    /* zero the memory */
    if (p)
        rt_memset(p, 0, count * size);

    return p;
}
RTM_EXPORT(rt_calloc);
```

- 與 memheap 相同，一次要一塊 count 乘 size 的記憶體
- 清 0 並回傳起始位址

---
## 釋放記憶體
<i class="fa fa-code"></i> Code: `rt_free`

| 功能 | 回傳值 |
| --- | ------ |
| 釋放記憶體 | void |

| `*rmem` |
| -------- |
| 欲釋放的記憶體 |

```c 
/**
 * This function will release the previously allocated memory block by
 * rt_malloc. The released memory block is taken back to system heap.
 *
 * @param rmem the address of memory which will be released
 */
void rt_free(void *rmem)
{
    struct heap_mem *mem;

    if (rmem == RT_NULL)
        return;
```

- 如果需要釋放得記憶體為空，不做事

```c 
    RT_DEBUG_NOT_IN_INTERRUPT;

    RT_ASSERT((((rt_uint32_t)rmem) & (RT_ALIGN_SIZE - 1)) == 0);
    RT_ASSERT((rt_uint8_t *)rmem >= (rt_uint8_t *)heap_ptr &&
              (rt_uint8_t *)rmem < (rt_uint8_t *)heap_end);

    RT_OBJECT_HOOK_CALL(rt_free_hook, (rmem));

    if ((rt_uint8_t *)rmem < (rt_uint8_t *)heap_ptr ||
        (rt_uint8_t *)rmem >= (rt_uint8_t *)heap_end)
    {
        RT_DEBUG_LOG(RT_DEBUG_MEM, ("illegal memory\n"));

        return;
    }

    /* Get the corresponding struct heap_mem ... */
    mem = (struct heap_mem *)((rt_uint8_t *)rmem - SIZEOF_STRUCT_MEM);
```

- 檢查記憶體位址是否合法，並找到真正的記憶體區塊位址

```c 
    RT_DEBUG_LOG(RT_DEBUG_MEM,
                 ("release memory 0x%x, size: %d\n",
                  (rt_uint32_t)rmem,
                  (rt_uint32_t)(mem->next - ((rt_uint8_t *)mem - heap_ptr))));


    /* protect the heap from concurrent access */
    rt_sem_take(&heap_sem, RT_WAITING_FOREVER);

    /* ... which has to be in a used state ... */
    if (!mem->used || mem->magic != HEAP_MAGIC)
    {
        rt_kprintf("to free a bad data block:\n");
        rt_kprintf("mem: 0x%08x, used flag: %d, magic code: 0x%04x\n", mem, mem->used, mem->magic);
    }
    RT_ASSERT(mem->used);
    RT_ASSERT(mem->magic == HEAP_MAGIC);
```

- 要鎖，檢查是否是使用中的區塊，及是否屬於 heap

```c 
    /* ... and is now unused. */
    mem->used  = 0;
    mem->magic = HEAP_MAGIC;
#ifdef RT_USING_MEMTRACE
    rt_mem_setname(mem, "    ");
#endif

    if (mem < lfree)
    {
        /* the newly freed struct is now the lowest */
        lfree = mem;
    }
```

- 接著設為可使用，及更新 `lfree`

```c
#ifdef RT_MEM_STATS
    used_mem -= (mem->next - ((rt_uint8_t *)mem - heap_ptr));
#endif

    /* finally, see if prev or next are free also */
    plug_holes(mem);
    rt_sem_release(&heap_sem);
}
RTM_EXPORT(rt_free);
```

- 最後合併記憶體塊，並還鎖