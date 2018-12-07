---
title: RT-Thread Memory Heap
tag: [RT-Thread, 記憶體, Memory Heap, kernel]
copyright: true
date: 2018-12-05 15:42:29
category: RT-Thread
---
- <i class="fa fa-file-text-o" aria-hidden="true"></i> File: memheap.c
- memheap 的管理方法（動態管理）:
    - 從 RAM 中要一塊記憶體
    - 根據使用者需要的大小進行切割
    - 剩下的以雙向鏈結的方式接起來，形成 free list

{%note success%} 
使用此管理方式： `#define RT_USING_MEMHEAP_AS_HEAP`
{%endnote%}

<!--more-->

## 結構
```c =698 :struct rt_memheap (rtdef.h)
#ifdef RT_USING_MEMHEAP
/**
 * memory item on the heap
 */
struct rt_memheap_item
{
    rt_uint32_t             magic;                      /**< magic number for memheap */
    struct rt_memheap      *pool_ptr;                   /**< point of pool */

    struct rt_memheap_item *next;                       /**< next memheap item */
    struct rt_memheap_item *prev;                       /**< prev memheap item */

    struct rt_memheap_item *next_free;                  /**< next free memheap item */
    struct rt_memheap_item *prev_free;                  /**< prev free memheap item */
};

/**
 * Base structure of memory heap object
 */
struct rt_memheap
{
    struct rt_object        parent;                     /**< inherit from rt_object */

    void                   *start_addr;                 /**< pool start address and size */

    rt_uint32_t             pool_size;                  /**< pool size */
    rt_uint32_t             available_size;             /**< available size */
    rt_uint32_t             max_used_size;              /**< maximum allocated size */

    struct rt_memheap_item *block_list;                 /**< used block list */

    struct rt_memheap_item *free_list;                  /**< free block list */
    struct rt_memheap_item  free_header;                /**< free block list header */

    struct rt_semaphore     lock;                       /**< semaphore lock */
};
#endif
```

- `*start_addr` 指向可用的記憶體<br><br>
- `pool_size` 代表總共可用的大小
- `available_size` 目前可用的大小
- `max_used_size` 已使用的歷史中，最大的使用大小<br><br>
- `*block_list` 所有切割過的區塊（包含 header）<br><br>
- `*free_list` 目前所有可用的區塊
- `*free_list` 的第一顆<br><br>
- `lock` semaphore
---
## 建立 memory heap
- <i class="fa fa-code" aria-hidden="true"></i> Code: `rt_system_heap_init`

| 功能 | 回傳值 |
| --- | ------ |
| 建立 memheap| void |

| `*begin_addr` | `*end_addr` |
| ------------- | ----------- |
| 起始位址（欲分配的） | 結束位址 |

```c =601
void rt_system_heap_init(void *begin_addr, void *end_addr)
{
    /* initialize a default heap in the system */
    rt_memheap_init(&_heap,
                    "heap",
                    begin_addr,
                    (rt_uint32_t)end_addr - (rt_uint32_t)begin_addr);
}
```

- 將起始位置，大小，結構體傳入 `rt_memheap_init`

---

- <i class="fa fa-code" aria-hidden="true"></i> Code: `rt_memheap_init`

| 功能 | 回傳值 |
| --- | ------ |
| 初始化 memheap| `RT_EOK` |

| `*memheap` | `*name` | `*start_addr` | `size` |
| ---------- | ------- | ------------- | ------ |
| memheap 結構 | 名字 | 欲分配的記憶體起始位址 | 記憶體大小 |

```c =39
/*
 * The initialized memory pool will be:
 * +-----------------------------------+--------------------------+
 * | whole freed memory block          | Used Memory Block Tailer |
 * +-----------------------------------+--------------------------+
 *
 * block_list --> whole freed memory block
 *
 * The length of Used Memory Block Tailer is 0,
 * which is prevents block merging across list
 */
rt_err_t rt_memheap_init(struct rt_memheap *memheap,
                         const char        *name,
                         void              *start_addr,
                         rt_uint32_t        size)
{
    struct rt_memheap_item *item;

    RT_ASSERT(memheap != RT_NULL);

    /* initialize pool object */
    rt_object_init(&(memheap->parent), RT_Object_Class_MemHeap, name);

    memheap->start_addr     = start_addr;
    memheap->pool_size      = RT_ALIGN_DOWN(size, RT_ALIGN_SIZE);
    memheap->available_size = memheap->pool_size - (2 * RT_MEMHEAP_SIZE);
    memheap->max_used_size  = memheap->pool_size - memheap->available_size;
```

- 首先填入 `start_addr`
- 向下對齊 `size`
- 設定可用大小為 `size` 減掉 2 個 header
- 設定最大已使用大小為目前已使用的大小（即 2 倍的 header）

```c =66
    /* initialize the free list header */
    item            = &(memheap->free_header);
    item->magic     = RT_MEMHEAP_MAGIC;
    item->pool_ptr  = memheap;
    item->next      = RT_NULL;
    item->prev      = RT_NULL;
    item->next_free = item;
    item->prev_free = item;
```

- 先初始化 free list：
    - 讓 item 指向 free list 的 header
    - 設定 magic 碼
    - 將 `pool_ptr` 指向 memheap 結構
    - `next`、`prev` 指向 `NULL`
    - `next_free`、`prev_free` 指向自己

```c =74
    /* set the free list to free list header */
    memheap->free_list = item;
```

- 給定 free list

```c =76
    /* initialize the first big memory block */
    item            = (struct rt_memheap_item *)start_addr;
    item->magic     = RT_MEMHEAP_MAGIC;
    item->pool_ptr  = memheap;
    item->next      = RT_NULL;
    item->prev      = RT_NULL;
    item->next_free = item;
    item->prev_free = item;
```

- 接著將整個 pool 設定為一個可用的 block
    - 讓 item 指向 起始位址
    - 設定 magic 碼
    - 將 `pool_ptr` 指向 memheap 結構
    - `next`、`prev` 指向 `NULL`
    - `next_free`、`prev_free` 指向自己

```c =84
    item->next = (struct rt_memheap_item *)
                 ((rt_uint8_t *)item + memheap->available_size + RT_MEMHEAP_SIZE);
    item->prev = item->next;
```

- 讓 next 與 prev 指到結尾的 header

```c =87
    /* block list header */
    memheap->block_list = item;
```

- 給定 block_list

```c =89
    /* place the big memory block to free list */
    item->next_free = memheap->free_list->next_free;
    item->prev_free = memheap->free_list;
    memheap->free_list->next_free->prev_free = item;
    memheap->free_list->next_free            = item;
```

- 將 free list (item) 的 `next` 指向 `memheap->free_list->next_free`，也就是 free list
- `prev` 同上
- 將 free list (heap) 的 `next` 指向 `item`
- `prev` 同上

```c =94
    /* move to the end of memory pool to build a small tailer block,
     * which prevents block merging
     */
    item = item->next;
    /* it's a used memory block */
    item->magic     = RT_MEMHEAP_MAGIC | RT_MEMHEAP_USED;
    item->pool_ptr  = memheap;
    item->next      = (struct rt_memheap_item *)start_addr;
    item->prev      = (struct rt_memheap_item *)start_addr;
    /* not in free list */
    item->next_free = item->prev_free = RT_NULL;
```

- 設定尾巴的 header
    - 讓 item 指向 free list 的 header
    - 設定 magic 碼為**使用過**的
    - 將 `pool_ptr` 指向 memheap 結構
    - `next`、`prev` 指向起始位置
    - `next_free`、`prev_free` 指向 `NULL`

```c =105
    /* initialize semaphore lock */
    rt_sem_init(&(memheap->lock), name, 1, RT_IPC_FLAG_FIFO);

    RT_DEBUG_LOG(RT_DEBUG_MEMHEAP,
                 ("memory heap: start addr 0x%08x, size %d, free list header 0x%08x\n",
                  start_addr, size, &(memheap->free_header)));

    return RT_EOK;
}
RTM_EXPORT(rt_memheap_init);
```

- 最後初始化 semaphore 並使用 FIFO

---
## 刪除 memory heap
- <i class="fa fa-code" aria-hidden="true"></i> Code: `rt_memheap_detach`

| 功能 | 回傳值 |
| --- | ------ |
| 刪除 memheap | `RT_EOK` |

| `*heap` |
| ------- |
| 欲刪除的 memheap |

```c =124
rt_err_t rt_memheap_detach(struct rt_memheap *heap)
{
    RT_ASSERT(heap);
    RT_ASSERT(rt_object_get_type(&heap->parent) == RT_Object_Class_MemHeap);
    RT_ASSERT(rt_object_is_systemobject(&heap->parent));

    rt_object_detach(&(heap->lock.parent.parent));
    rt_object_detach(&(heap->parent));

    /* Return a successful completion. */
    return RT_EOK;
}
RTM_EXPORT(rt_memheap_detach);
```

- 使用 `rt_object_detach` 刪除 semaphore 與 memheap

---
## 分配記憶體
### Code: rt_malloc

| 功能 | 回傳值 |
| --- | ------ |
| 要求一塊記憶體 | 取得的記憶體 |

| `size` |
| ------ |
| 欲要求的大小 |

```c =610
void *rt_malloc(rt_size_t size)
{
    void *ptr;

    /* try to allocate in system heap */
    ptr = rt_memheap_alloc(&_heap, size);
```

- 首先嘗試從系統的 heap（`_heap`）要求記憶體（透過 `rt_memheap_alloc`）

```c =616
    if (ptr == RT_NULL)
    {
        struct rt_object *object;
        struct rt_list_node *node;
        struct rt_memheap *heap;
        struct rt_object_information *information;

        /* try to allocate on other memory heap */
        information = rt_object_get_information(RT_Object_Class_MemHeap);
```

- 如果失敗，嘗試從其他的 heap 要求

```c =625
        RT_ASSERT(information != RT_NULL);
        for (node  = information->object_list.next;
             node != &(information->object_list);
             node  = node->next)
        {
            object = rt_list_entry(node, struct rt_object, list);
            heap   = (struct rt_memheap *)object;

            RT_ASSERT(heap);
            RT_ASSERT(rt_object_get_type(&heap->parent) == RT_Object_Class_MemHeap);

            /* not allocate in the default system heap */
            if (heap == &_heap)
                continue;
```

- 跳過系統的 heap

```c =639
            ptr = rt_memheap_alloc(heap, size);
            if (ptr != RT_NULL)
                break;
        }
    }

    return ptr;
}
RTM_EXPORT(rt_malloc);
```

- 一樣透過 `rt_memheap_alloc` 來完成
- 如果成功就跳出迴圈，最後回傳記憶體位址

---
- <i class="fa fa-code" aria-hidden="true"></i> Code: `rt_memheap_alloc`

| 功能 | 回傳值 |
| --- | ------ |
| 要求一塊記憶體 | 取得的記憶體 |

| `*heap` | `size` |
| ------- | ------ |
| 目標 heap | 欲要求的大小 |

```c =138
void *rt_memheap_alloc(struct rt_memheap *heap, rt_uint32_t size)
{
    rt_err_t result;
    rt_uint32_t free_size;
    struct rt_memheap_item *header_ptr;

    RT_ASSERT(heap != RT_NULL);
    RT_ASSERT(rt_object_get_type(&heap->parent) == RT_Object_Class_MemHeap);

    /* align allocated size */
    size = RT_ALIGN(size, RT_ALIGN_SIZE);
    if (size < RT_MEMHEAP_MINIALLOC)
        size = RT_MEMHEAP_MINIALLOC;
```

- 首先向上對齊 `size`
- 如果小於 `RT_MEMHEAP_MINIALLOC` (12)，設定為 `RT_MEMHEAP_MINIALLOC`

```c =151
    RT_DEBUG_LOG(RT_DEBUG_MEMHEAP, ("allocate %d on heap:%8.*s",
                                    size, RT_NAME_MAX, heap->parent.name));

    if (size < heap->available_size)
    {
        /* search on free list */
        free_size = 0;
```

- 如果 heap 還夠使用，先將 `free_size` 設為 0
- `free_size` 代表我們目前找到的可用大小

```c =158
        /* lock memheap */
        result = rt_sem_take(&(heap->lock), RT_WAITING_FOREVER);
        if (result != RT_EOK)
        {
            rt_set_errno(result);

            return RT_NULL;
        }
```

- 接著試著索取 semaphore
- 如果失敗，設定錯誤碼並回傳 NULL

```c =166
        /* get the first free memory block */
        header_ptr = heap->free_list->next_free;
        while (header_ptr != heap->free_list && free_size < size)
        {
            /* get current freed memory block size */
            free_size = MEMITEM_SIZE(header_ptr);
            if (free_size < size)
            {
                /* move to next free memory block */
                header_ptr = header_ptr->next_free;
            }
        }
```

- 接著從 free list 上一個一個找
- 使用 *first fit*，找到一個大魚的就退出迴圈
- `MEMITEM_SIZE(item)`：`((rt_uint32_t)item->next - (rt_uint32_t)item - RT_MEMHEAP_SIZE)`
- 利用下一顆的位址減掉自己的位址取的總體大小，再減掉 header 的大小

```c =178
        /* determine if the memory is available. */
        if (free_size >= size)
        {
            /* a block that satisfies the request has been found. */

            /* determine if the block needs to be split. */
            if (free_size >= (size + RT_MEMHEAP_SIZE + RT_MEMHEAP_MINIALLOC))
            {
                struct rt_memheap_item *new_ptr;

                /* split the block. */
                new_ptr = (struct rt_memheap_item *)
                          (((rt_uint8_t *)header_ptr) + size + RT_MEMHEAP_SIZE);
```

- 如果有成功找到（不是因為走完迴圈才往下）
- 且這塊大到可以再切一塊，切割這塊：
    - 從找到的那塊開始往後一個 `size` 與一個 `RT_MEMHEAP_SIZE` 作為新的 header

```c =191
                RT_DEBUG_LOG(RT_DEBUG_MEMHEAP,
                             ("split: block[0x%08x] nextm[0x%08x] prevm[0x%08x] to new[0x%08x]\n",
                              header_ptr,
                              header_ptr->next,
                              header_ptr->prev,
                              new_ptr));

                /* mark the new block as a memory block and freed. */
                new_ptr->magic = RT_MEMHEAP_MAGIC;

                /* put the pool pointer into the new block. */
                new_ptr->pool_ptr = heap;
```

- 設定 magic 碼
- 設定所屬 heap

```c =203
                /* break down the block list */
                new_ptr->prev          = header_ptr;
                new_ptr->next          = header_ptr->next;
                header_ptr->next->prev = new_ptr;
                header_ptr->next       = new_ptr;
```

- 將此 block 插入 `block_list`

```c =208
                /* remove header ptr from free list */
                header_ptr->next_free->prev_free = header_ptr->prev_free;
                header_ptr->prev_free->next_free = header_ptr->next_free;
                header_ptr->next_free = RT_NULL;
                header_ptr->prev_free = RT_NULL;
```

- 從 free list 中移除找到的 block 

```c =213
                /* insert new_ptr to free list */
                new_ptr->next_free = heap->free_list->next_free;
                new_ptr->prev_free = heap->free_list;
                heap->free_list->next_free->prev_free = new_ptr;
                heap->free_list->next_free            = new_ptr;
                RT_DEBUG_LOG(RT_DEBUG_MEMHEAP, ("new ptr: next_free 0x%08x, prev_free 0x%08x\n",
                                                new_ptr->next_free,
                                                new_ptr->prev_free));
```

- 將分割好的 block 插入 free list

```c =221
                /* decrement the available byte count.  */
                heap->available_size = heap->available_size -
                                       size -
                                       RT_MEMHEAP_SIZE;
                if (heap->pool_size - heap->available_size > heap->max_used_size)
                    heap->max_used_size = heap->pool_size - heap->available_size;
            }
```

- 更新 `available_size` 與 `max_used_size` (如果需要)

```c =228
            else
            {
                /* decrement the entire free size from the available bytes count. */
                heap->available_size = heap->available_size - free_size;
                if (heap->pool_size - heap->available_size > heap->max_used_size)
                    heap->max_used_size = heap->pool_size - heap->available_size;
```

- 如果不能切割，一樣更新 `available_size` 與 `max_used_size` (如果需要)

```c =234
                /* remove header_ptr from free list */
                RT_DEBUG_LOG(RT_DEBUG_MEMHEAP,
                             ("one block: block[0x%08x], next_free 0x%08x, prev_free 0x%08x\n",
                              header_ptr,
                              header_ptr->next_free,
                              header_ptr->prev_free));

                header_ptr->next_free->prev_free = header_ptr->prev_free;
                header_ptr->prev_free->next_free = header_ptr->next_free;
                header_ptr->next_free = RT_NULL;
                header_ptr->prev_free = RT_NULL;
            }
```

- 從 free list 中移除找到的 block 

```c =246
            /* Mark the allocated block as not available. */
            header_ptr->magic |= RT_MEMHEAP_USED;

            /* release lock */
            rt_sem_release(&(heap->lock));
```

- 標記為使用中，釋放 semaphore

```c =251
            /* Return a memory address to the caller.  */
            RT_DEBUG_LOG(RT_DEBUG_MEMHEAP,
                         ("alloc mem: memory[0x%08x], heap[0x%08x], size: %d\n",
                          (void *)((rt_uint8_t *)header_ptr + RT_MEMHEAP_SIZE),
                          header_ptr,
                          size));

            return (void *)((rt_uint8_t *)header_ptr + RT_MEMHEAP_SIZE);
        }
```

- 最後回傳 block 記憶體位址 + header
- 即回傳可用的區塊

```c =260
        /* release lock */
        rt_sem_release(&(heap->lock));
    }

    RT_DEBUG_LOG(RT_DEBUG_MEMHEAP, ("allocate memory: failed\n"));

    /* Return the completion status.  */
    return RT_NULL;
}
RTM_EXPORT(rt_memheap_alloc);
```

- 如果找失敗，一樣釋放 semaphore
- 不論是找失敗，或是記憶體不足，皆回傳 NULL

---
### Code: rt_realloc

| 功能 | 回傳值 |
| --- | ------ |
| 重新要求記憶體（增長或縮減） | 新分配完的記憶體塊 |

| `*rmem` | `newsize` |
| ------- | --------- |
| 欲重新分配的記憶體 | 新的大小 |

```c =656
void *rt_realloc(void *rmem, rt_size_t newsize)
{
    void *new_ptr;
    struct rt_memheap_item *header_ptr;

    if (rmem == RT_NULL)
        return rt_malloc(newsize);
```

- 如果傳入的記憶體位置為空，直接 `rt_malloc(newsize)` 並回傳
 
```c =663
    if (newsize == 0)
    {
        rt_free(rmem);
        return RT_NULL;
    }
```

- 如果 `newsize` 為 0，free 傳入的記憶體位置，回傳 NULL

```c =668
    /* get old memory item */
    header_ptr = (struct rt_memheap_item *)
                 ((rt_uint8_t *)rmem - RT_MEMHEAP_SIZE);
```

- 取得傳入的記憶體塊所屬的 header
- malloc 時回傳的是可使用的起始位址，並不會包含 header，因此這裡減掉一個 header 的大小

```c =671
    new_ptr = rt_memheap_realloc(header_ptr->pool_ptr, rmem, newsize);
```

- 透過 `rt_memheap_realloc` 來完成

```c =672
    if (new_ptr == RT_NULL && newsize != 0)
    {
        /* allocate memory block from other memheap */
        new_ptr = rt_malloc(newsize);
```

- 如果無法在原本的 heap 完成增長（或縮減），直接從別的 heap 要一塊 `newsize` 大的記憶體

```c =676
        if (new_ptr != RT_NULL && rmem != RT_NULL)
        {
            rt_size_t oldsize;

            /* get the size of old memory block */
            oldsize = MEMITEM_SIZE(header_ptr);
            if (newsize > oldsize)
                rt_memcpy(new_ptr, rmem, oldsize);
            else
                rt_memcpy(new_ptr, rmem, newsize);

            rt_free(rmem);
        }
    }
```

- 如果最後有要成功，復原原本的資料

```c =690
    return new_ptr;
}
RTM_EXPORT(rt_realloc);
```

- 最後回傳新的記憶體位址

---
- <i class="fa fa-code" aria-hidden="true"></i> Code: `rt_memheap_realloc`

| 功能 | 回傳值 |
| --- | ------ |
| 重新要求記憶體（增長或縮減） | 新分配完的記憶體塊 |

| `heap` | `*ptr` | `newsize` |
| ------ | ------- | --------- |
| 目標 heap | 欲重新分配的記憶體 | 新的大小 |

```c =284
oid *rt_memheap_realloc(struct rt_memheap *heap, void *ptr, rt_size_t newsize)
{
    rt_err_t result;
    rt_size_t oldsize;
    struct rt_memheap_item *header_ptr;
    struct rt_memheap_item *new_ptr;

    RT_ASSERT(heap);
    RT_ASSERT(rt_object_get_type(&heap->parent) == RT_Object_Class_MemHeap);

    if (newsize == 0)
    {
        rt_memheap_free(ptr);

        return RT_NULL;
    }
```

- 如果 `newsize` 為 0，free 並回傳 NULL

```c =300
    /* align allocated size */
    newsize = RT_ALIGN(newsize, RT_ALIGN_SIZE);
    if (newsize < RT_MEMHEAP_MINIALLOC)
        newsize = RT_MEMHEAP_MINIALLOC;
```

- 向上對齊 `newsize`
- 如果小於 `RT_MEMHEAP_MINIALLOC` (12)，設定為 `RT_MEMHEAP_MINIALLOC`

```c =304
    if (ptr == RT_NULL)
    {
        return rt_memheap_alloc(heap, newsize);
    }
```

- 如果傳入的記憶體位置為空，直接 malloc newsize 的大小並回傳

```c =308
    /* get memory block header and get the size of memory block */
    header_ptr = (struct rt_memheap_item *)
                 ((rt_uint8_t *)ptr - RT_MEMHEAP_SIZE);
    oldsize = MEMITEM_SIZE(header_ptr);
```

- 取得傳入的 block 所屬的 header
- 一併計算這塊的大小

```c =312
    /* re-allocate memory */
    if (newsize > oldsize)
    {
        void *new_ptr;
        struct rt_memheap_item *next_ptr;

        /* lock memheap */
        result = rt_sem_take(&(heap->lock), RT_WAITING_FOREVER);
        if (result != RT_EOK)
        {
            rt_set_errno(result);
            return RT_NULL;
        }
```

- 如果需要增長記憶體，先取得 semaphore

```c =325
        next_ptr = header_ptr->next;

        /* header_ptr should not be the tail */
        RT_ASSERT(next_ptr > header_ptr);

        /* check whether the following free space is enough to expand */
        if (!RT_MEMHEAP_IS_USED(next_ptr))
        {
            rt_int32_t nextsize;

            nextsize = MEMITEM_SIZE(next_ptr);
            RT_ASSERT(next_ptr > 0);
```

- 先判斷下一顆可不可用

```c =337
            /* Here is the ASCII art of the situation that we can make use of
             * the next free node without alloc/memcpy, |*| is the control
             * block:
             *
             *      oldsize           free node
             * |*|-----------|*|----------------------|*|
             *         newsize          >= minialloc
             * |*|----------------|*|-----------------|*|
             */
            if (nextsize + oldsize > newsize + RT_MEMHEAP_MINIALLOC)
            {
                /* decrement the entire free size from the available bytes count. */
                heap->available_size = heap->available_size - (newsize - oldsize);
                if (heap->pool_size - heap->available_size > heap->max_used_size)
                    heap->max_used_size = heap->pool_size - heap->available_size;
```

- 如果可用，而且下一顆足夠分割出一塊新的 block
- 更新 `available_size` 與 `max_used_size` (如果需要)

```c =352
                /* remove next_ptr from free list */
                RT_DEBUG_LOG(RT_DEBUG_MEMHEAP,
                             ("remove block: block[0x%08x], next_free 0x%08x, prev_free 0x%08x",
                              next_ptr,
                              next_ptr->next_free,
                              next_ptr->prev_free));

                next_ptr->next_free->prev_free = next_ptr->prev_free;
                next_ptr->prev_free->next_free = next_ptr->next_free;
                next_ptr->next->prev = next_ptr->prev;
                next_ptr->prev->next = next_ptr->next;
```

- 從 free list 移除舊的下一顆

```c =363
                /* build a new one on the right place */
                next_ptr = (struct rt_memheap_item *)((char *)ptr + newsize);
```

- 重新定指新的下一顆（傳入的起始位址加上 `newsize`）

```c =365
                RT_DEBUG_LOG(RT_DEBUG_MEMHEAP,
                             ("new free block: block[0x%08x] nextm[0x%08x] prevm[0x%08x]",
                              next_ptr,
                              next_ptr->next,
                              next_ptr->prev));

                /* mark the new block as a memory block and freed. */
                next_ptr->magic = RT_MEMHEAP_MAGIC;

                /* put the pool pointer into the new block. */
                next_ptr->pool_ptr = heap;
```

- 設定 magic 碼
- 設定所屬 heap

```c =376
                next_ptr->prev          = header_ptr;
                next_ptr->next          = header_ptr->next;
                header_ptr->next->prev = next_ptr;
                header_ptr->next       = next_ptr;
```

- 插入 block list

```c =380
                /* insert next_ptr to free list */
                next_ptr->next_free = heap->free_list->next_free;
                next_ptr->prev_free = heap->free_list;
                heap->free_list->next_free->prev_free = next_ptr;
                heap->free_list->next_free            = next_ptr;
                RT_DEBUG_LOG(RT_DEBUG_MEMHEAP, ("new ptr: next_free 0x%08x, prev_free 0x%08x",
                                                next_ptr->next_free,
                                                next_ptr->prev_free));
```

插入 free list

```c =388
                /* release lock */
                rt_sem_release(&(heap->lock));

                return ptr;
            }
        }
```

- 釋放 semaphore 並回傳更新後的記憶體位址

```c =394
        /* release lock */
        rt_sem_release(&(heap->lock));

        /* re-allocate a memory block */
        new_ptr = (void *)rt_memheap_alloc(heap, newsize);
        if (new_ptr != RT_NULL)
        {
            rt_memcpy(new_ptr, ptr, oldsize < newsize ? oldsize : newsize);
            rt_memheap_free(ptr);
        }

        return new_ptr;
    }
```

- 如果下一顆不夠大，重新在原本的 heap 上要一塊 `newsize` 大的記憶體
- 成功的話還原資料，並釋放原本的記憶體 
- 回傳新的記憶體位址

```c =407
    /* don't split when there is less than one node space left */
    if (newsize + RT_MEMHEAP_SIZE + RT_MEMHEAP_MINIALLOC >= oldsize)
        return ptr;
```

- 如果是需要縮減，且縮減後剩下的大小不足以切成一塊
- 什麼事都不做，直接回傳原本的位址

```c =410
    /* lock memheap */
    result = rt_sem_take(&(heap->lock), RT_WAITING_FOREVER);
    if (result != RT_EOK)
    {
        rt_set_errno(result);

        return RT_NULL;
    }
```

- 可以分割的話先取得 semaphore

```c =418
    /* split the block. */
    new_ptr = (struct rt_memheap_item *)
              (((rt_uint8_t *)header_ptr) + newsize + RT_MEMHEAP_SIZE);
```

- 定址新的 block

```c =421
    RT_DEBUG_LOG(RT_DEBUG_MEMHEAP,
                 ("split: block[0x%08x] nextm[0x%08x] prevm[0x%08x] to new[0x%08x]\n",
                  header_ptr,
                  header_ptr->next,
                  header_ptr->prev,
                  new_ptr));

    /* mark the new block as a memory block and freed. */
    new_ptr->magic = RT_MEMHEAP_MAGIC;
    /* put the pool pointer into the new block. */
    new_ptr->pool_ptr = heap;
```

- 設定 magic 碼
- 設定所屬 heap

```c =432
    /* break down the block list */
    new_ptr->prev          = header_ptr;
    new_ptr->next          = header_ptr->next;
    header_ptr->next->prev = new_ptr;
    header_ptr->next       = new_ptr;
```

- 插入至 block list

```c =437
    /* determine if the block can be merged with the next neighbor. */
    if (!RT_MEMHEAP_IS_USED(new_ptr->next))
    {
        struct rt_memheap_item *free_ptr;

        /* merge block with next neighbor. */
        free_ptr = new_ptr->next;
        heap->available_size = heap->available_size - MEMITEM_SIZE(free_ptr);
```

- 如果新的 block 下一顆未使用，即可合併
- 先將可用大小減掉下一顆的大小，待會會加回來

```c =445
        RT_DEBUG_LOG(RT_DEBUG_MEMHEAP,
                     ("merge: right node 0x%08x, next_free 0x%08x, prev_free 0x%08x\n",
                      header_ptr, header_ptr->next_free, header_ptr->prev_free));

        free_ptr->next->prev = new_ptr;
        new_ptr->next   = free_ptr->next;
```

- 從 block list 移除下一顆

```c =451
        /* remove free ptr from free list */
        free_ptr->next_free->prev_free = free_ptr->prev_free;
        free_ptr->prev_free->next_free = free_ptr->next_free;
    }
```

- 從 free list 移除下一顆，完成合併

```c =455
    /* insert the split block to free list */
    new_ptr->next_free = heap->free_list->next_free;
    new_ptr->prev_free = heap->free_list;
    heap->free_list->next_free->prev_free = new_ptr;
    heap->free_list->next_free            = new_ptr;
    RT_DEBUG_LOG(RT_DEBUG_MEMHEAP, ("new free ptr: next_free 0x%08x, prev_free 0x%08x\n",
                                    new_ptr->next_free,
                                    new_ptr->prev_free));
```

- 無論下一顆是否可以合併，都把新的 block 插入 free list

```c =463
    /* increment the available byte count.  */
    heap->available_size = heap->available_size + MEMITEM_SIZE(new_ptr);

    /* release lock */
    rt_sem_release(&(heap->lock));

    /* return the old memory block */
    return ptr;
}
RTM_EXPORT(rt_memheap_realloc);
```

- 更新可用大小，並釋放 semaphore
- 回傳更新後的記憶體位址

---
### Code: rt_calloc

| 功能 | 回傳值 |
| --- | ------ |
| 要求多個連續的記憶體 | 第一塊的位址 |

| `count` | `size` |
| ------- | ------ |
| 欲要求的數量 | 欲要求的大小 |

```c =698
void *rt_calloc(rt_size_t count, rt_size_t size)
{
    void *ptr;
    rt_size_t total_size;

    total_size = count * size;
    ptr = rt_malloc(total_size);
    if (ptr != RT_NULL)
    {
        /* clean memory */
        rt_memset(ptr, 0, total_size);
    }

    return ptr;
}
RTM_EXPORT(rt_calloc);
```

- 即要求一塊 `count * size` 大的記憶體

---
## 釋放記憶體
- <i class="fa fa-code" aria-hidden="true"></i> Code: `rt_free`

| 功能 | 回傳值 |
| --- | ------ |
| 釋放一塊記憶體 | void |

| `*rmem` |
| ------- |
| 欲釋放的記憶體 |


```c =650
void rt_free(void *rmem)
{
    rt_memheap_free(rmem);
}
RTM_EXPORT(rt_free);
```

- 透過 `rt_memheap_free` 完成

---
- <i class="fa fa-code" aria-hidden="true"></i> Code: `rt_memheap_free`

| 功能 | 回傳值 |
| --- | ------ |
| 釋放一塊記憶體 | void |

| `*ptr` |
| ------- |
| 欲釋放的記憶體 |


```c =495
void rt_memheap_free(void *ptr)
{
    rt_err_t result;
    struct rt_memheap *heap;
    struct rt_memheap_item *header_ptr, *new_ptr;
    rt_uint32_t insert_header;

    /* NULL check */
    if (ptr == RT_NULL) return;
```

- 如果傳入 NULL，什麼事都不用做
- `return` 退出副程式

```c =504
    /* set initial status as OK */
    insert_header = 1;
    new_ptr       = RT_NULL;
    header_ptr    = (struct rt_memheap_item *)
                    ((rt_uint8_t *)ptr - RT_MEMHEAP_SIZE);
```

- 初始化一些參數，並找到傳入的 block 所屬的 header

```c =509
    RT_DEBUG_LOG(RT_DEBUG_MEMHEAP, ("free memory: memory[0x%08x], block[0x%08x]\n",
                                    ptr, header_ptr));

    /* check magic */
    RT_ASSERT((header_ptr->magic & RT_MEMHEAP_MASK) == RT_MEMHEAP_MAGIC);
    RT_ASSERT(header_ptr->magic & RT_MEMHEAP_USED);
    /* check whether this block of memory has been over-written. */
    RT_ASSERT((header_ptr->next->magic & RT_MEMHEAP_MASK) == RT_MEMHEAP_MAGIC);

    /* get pool ptr */
    heap = header_ptr->pool_ptr;
```

- 定址 heap

```c =520
    RT_ASSERT(heap);
    RT_ASSERT(rt_object_get_type(&heap->parent) == RT_Object_Class_MemHeap);

    /* lock memheap */
    result = rt_sem_take(&(heap->lock), RT_WAITING_FOREVER);
    if (result != RT_EOK)
    {
        rt_set_errno(result);

        return ;
    }
```

- 先取得 semaphore

```c =531
    /* Mark the memory as available. */
    header_ptr->magic &= ~RT_MEMHEAP_USED;
    /* Adjust the available number of bytes. */
    heap->available_size = heap->available_size + MEMITEM_SIZE(header_ptr);
```

- 將使用中的標記清除，更新可用大小

```c =535
    /* Determine if the block can be merged with the previous neighbor. */
    if (!RT_MEMHEAP_IS_USED(header_ptr->prev))
    {
        RT_DEBUG_LOG(RT_DEBUG_MEMHEAP, ("merge: left node 0x%08x\n",
                                        header_ptr->prev));

        /* adjust the available number of bytes. */
        heap->available_size = heap->available_size + RT_MEMHEAP_SIZE;
```

- 如果可以往前合併，更新可用大小（加一個 header 的大小）

```c =543
        /* yes, merge block with previous neighbor. */
        (header_ptr->prev)->next = header_ptr->next;
        (header_ptr->next)->prev = header_ptr->prev;
```

- 從 block list 移除此 block

```c =546
        /* move header pointer to previous. */
        header_ptr = header_ptr->prev;
        /* don't insert header to free list */
        insert_header = 0;
    }
```

- 重新定址 `header_ptr`
- 設定 `insert_header` 為 0，表示待會不需要將此 block 插回 free list（現在此 block 是與前一塊合併的，已經在 free list 上了）

```c =551
    /* determine if the block can be merged with the next neighbor. */
    if (!RT_MEMHEAP_IS_USED(header_ptr->next))
    {
        /* adjust the available number of bytes. */
        heap->available_size = heap->available_size + RT_MEMHEAP_SIZE;
```

- 如果可以往前合併，更新可用大小（加一個 header 的大小）

```c =556
        /* merge block with next neighbor. */
        new_ptr = header_ptr->next;

        RT_DEBUG_LOG(RT_DEBUG_MEMHEAP,
                     ("merge: right node 0x%08x, next_free 0x%08x, prev_free 0x%08x\n",
                      new_ptr, new_ptr->next_free, new_ptr->prev_free));

        new_ptr->next->prev = header_ptr;
        header_ptr->next    = new_ptr->next;
```

- 定址下一塊，並從 block list 移除下一塊

```c =565
        /* remove new ptr from free list */
        new_ptr->next_free->prev_free = new_ptr->prev_free;
        new_ptr->prev_free->next_free = new_ptr->next_free;
    }
```

- 一併從 free list 中移除

```c =569
    if (insert_header)
    {
        /* no left merge, insert to free list */
        header_ptr->next_free = heap->free_list->next_free;
        header_ptr->prev_free = heap->free_list;
        heap->free_list->next_free->prev_free = header_ptr;
        heap->free_list->next_free            = header_ptr;

        RT_DEBUG_LOG(RT_DEBUG_MEMHEAP,
                     ("insert to free list: next_free 0x%08x, prev_free 0x%08x\n",
                      header_ptr->next_free, header_ptr->prev_free));
    }
```

- 如果需要，插回 free list 上

```c =581
    /* release lock */
    rt_sem_release(&(heap->lock));
}
RTM_EXPORT(rt_memheap_free);
```

- 最後釋放 semaphore