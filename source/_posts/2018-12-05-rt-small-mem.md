---
title: RT-Thread 小記憶體動態管理 
tag: [RT-Thread, 記憶體, kernel, heap]
copyright: true
date: 2018-12-05 15:42:44
category: RT-Thread
---
- <i class="fa fa-file-text-o" aria-hidden="true"></i> File: mem.c
- 與 memory heap 的做法類似，一開始是一塊大的記憶體，包含 header
- 分配記憶體時適當的切割
- 所有的記憶體塊透過 header 串起來，形成一個雙向鏈結

![](https://i.imgur.com/mkfRpUV.png "small memory example")

{%note success%} 
使用此管理方式： `#defined RT_USING_HEAP && #defined RT_USING_SMALL_MEM`
{%endnote%}

<!--more-->

## 結構
```c =95
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
- <i class="fa fa-code" aria-hidden="true"></i> Code: `rt_system_heap_init`

| 功能 | 回傳值 |
| --- | ------ |
| 初始化 heap | void |

| `*begin_addr` | `*end_addr` |
| ------------- | ----------- |
| 記憶體起始位址 | 結束位址 |

```c =183
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

    rt_sem_init(&heap_sem, "heap", 1, RT_IPC_FLAG_FIFO);

    /* initialize the lowest-free pointer to the start of the heap */
    lfree = (struct heap_mem *)heap_ptr;
}
```