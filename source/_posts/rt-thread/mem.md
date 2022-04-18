---
title: RT-Thread - Memory Pool
tag: [RT-Thread, 記憶體管理, kernel]
date: 2018-11-29 11:24:59
category: RT-Thread
summary: RTT 記憶體管理 1 pool，一種基本靜態的記憶體管理法。
---
>使用此管理方式： `#define RT_USING_MEMPOOL`

- 分配記憶體的時間需固定，而且可確定（可預測）的
- 分配記憶體同時也要盡量避免碎片化，才能減少系統需重啟的次數
- RT-Thread 使用了靜態與動態管理，其中動態又分為小記憶體管理，與大記憶體管理（SLAB)

---

- mempool 的管理方法（靜態管理）:
    - 從 RAM 中要一塊記憶體
    - 將此記憶體切成**固定大小**的區塊
    - 以間接定址的方式接起來，形成 free list

## 結構

{% alert success %}
**File:** redef.h
{% endalert %}

```c=736
#ifdef RT_USING_MEMPOOL
/**
 * Base structure of Memory pool object
 */
struct rt_mempool
{
    struct rt_object parent;                            /**< inherit from rt_object */

    void            *start_address;                     /**< memory pool start */
    rt_size_t        size;                              /**< size of memory pool */

    rt_size_t        block_size;                        /**< size of memory blocks */
    rt_uint8_t      *block_list;                        /**< memory blocks list */

    rt_size_t        block_total_count;                 /**< numbers of memory block */
    rt_size_t        block_free_count;                  /**< numbers of free memory block */

    rt_list_t        suspend_thread;                    /**< threads pended on this resource */
    rt_size_t        suspend_thread_count;              /**< numbers of thread pended on this resource */
};
typedef struct rt_mempool *rt_mp_t;
#endif
```

我們從文本的圖來解釋結構：
![memory pool example](https://i.imgur.com/9KWhnUY.png "memory pool example")

- `start_address` 為每個 mempool 的起始位置，此圖為例則為*mempool 1* 的起始位置
- `size` 為 mempool 的大小，此圖為例則為*mempool 1* 的大小（灰色區塊）<br><br>
- `block_size` 為由 mempool 產出的空閒鏈表中，每一塊的大小，以*mempool 1* 為例，則為 32k
- `block_list` 為空閒鏈表，此圖為例則為*mempool 1* 旁邊的鏈結<br><br>
- `block_total_count` 為空閒鏈表創建時的總塊數，以*mempool 1* 為例，則為 128
- `block_free_count` 為為空閒鏈表現在可用的總塊數<br><br>
- `suspend_thread` 則為等待隊伍，此圖為例為最右邊的鏈結
- `suspend_thread_count` 則為等待隊伍的總排隊數，以此圖為例為 3

---

{% alert success %}
**File:** mempool.c
{% endalert %}

## 建立 memory pool
- 建立 memory pool 的方法一樣也可分為靜態的與動態的
- 這裡的動態是指從原先記憶體 heap 的區塊拿取記憶體

### 動態

| 功能 | 回傳值 |
| --- | ------ |
| 建立 mempool（使用 heap）| mempool | 

| `*name` | `block_count` | `block_size` |
| ------- | ------------- | ----------- |
| 名字 | 要切割的總塊數 | 一塊 free block 的大小 |

```c=174
/**
 * This function will create a mempool object and allocate the memory pool from
 * heap.
 *
 * @param name the name of memory pool
 * @param block_count the count of blocks in memory pool
 * @param block_size the size for each block
 *
 * @return the created mempool object
 */
rt_mp_t rt_mp_create(const char *name,
                     rt_size_t   block_count,
                     rt_size_t   block_size)
{
    rt_uint8_t *block_ptr;
    struct rt_mempool *mp;
    register rt_size_t offset;

    RT_DEBUG_NOT_IN_INTERRUPT;

    /* allocate object */
    mp = (struct rt_mempool *)rt_object_allocate(RT_Object_Class_MemPool, name);
    /* allocate object failed */
    if (mp == RT_NULL)
        return RT_NULL;
```

- 首先一樣先從 heap 取一塊記憶體作為 mempool 使用

```c=199
    /* initialize memory pool */
    block_size     = RT_ALIGN(block_size, RT_ALIGN_SIZE);
    mp->block_size = block_size;
    mp->size       = (block_size + sizeof(rt_uint8_t *)) * block_count;

    /* allocate memory */
    mp->start_address = rt_malloc((block_size + sizeof(rt_uint8_t *)) *
                                  block_count);
    if (mp->start_address == RT_NULL)
    {
        /* no memory, delete memory pool object */
        rt_object_delete(&(mp->parent));

        return RT_NULL;
    }
```

- 接著對齊 `block_size` 後填入結構中，一併計算 mempool 的大小
- 並從 heap 中取出一塊待會做成 free list

```c=214
    mp->block_total_count = block_count;
    mp->block_free_count  = mp->block_total_count;

    /* initialize suspended thread list */
    rt_list_init(&(mp->suspend_thread));
    mp->suspend_thread_count = 0;
```

- 填入總數，建立等待鏈

```c=220
    /* initialize free block list */
    block_ptr = (rt_uint8_t *)mp->start_address;
    for (offset = 0; offset < mp->block_total_count; offset ++)
    {
        *(rt_uint8_t **)(block_ptr + offset * (block_size + sizeof(rt_uint8_t *)))
            = block_ptr + (offset + 1) * (block_size + sizeof(rt_uint8_t *));
    }

    *(rt_uint8_t **)(block_ptr + (offset - 1) * (block_size + sizeof(rt_uint8_t *)))
        = RT_NULL;

    mp->block_list = block_ptr;

    return mp;
}
RTM_EXPORT(rt_mp_create);
```

- 最後製作 free list：
    - 一個 free block 分成兩部分：前 8-bit (rt_uint8_t *）與一個 block_size
    - 前 8-bit 存放下一個 free block 的位置

---
### 靜態
- 多傳了兩個參數 `size` 與 `*start`

| 功能 | 回傳值 | 
| --- | ------ |
| 建立 mempool | `RT_EOK` |

| `*mp` | `*name` | `*start` | 
| ----- | ------- | -------- | 
| 結構位址 | 名字 | 所要使用的記憶體位址 |

| `size` | `block_size` |
| ------ | ------------ |
| mempool 大小 | 一塊 free block 的大小 |

```c=65
/**
 * This function will initialize a memory pool object, normally which is used
 * for static object.
 *
 * @param mp the memory pool object
 * @param name the name of memory pool
 * @param start the star address of memory pool
 * @param size the total size of memory pool
 * @param block_size the size for each block
 *
 * @return RT_EOK
 */
rt_err_t rt_mp_init(struct rt_mempool *mp,
                    const char        *name,
                    void              *start,
                    rt_size_t          size,
                    rt_size_t          block_size)
{
    rt_uint8_t *block_ptr;
    register rt_size_t offset;

    /* parameter check */
    RT_ASSERT(mp != RT_NULL);

    /* initialize object */
    rt_object_init(&(mp->parent), RT_Object_Class_MemPool, name);

    /* initialize memory pool */
    mp->start_address = start;
    mp->size = RT_ALIGN_DOWN(size, RT_ALIGN_SIZE);

    /* align the block size */
    block_size = RT_ALIGN(block_size, RT_ALIGN_SIZE);
    mp->block_size = block_size;
```

- 可直接用 `rt_object_init` 初始化物件
- 同時用 `RT_ALIGN_DOWN` 對齊 size 
- 填入 `block_size`

`RT_ALIGN_DOWN` v.s. `RT_ALIGN`
- 當傳入 (13,4) 時：
- `RT_ALIGN_DOWN` 回傳 12，也就是在不超過 13 中，4 的倍數中最大的
- `RT_ALIGN` 回傳 16，也就是在大於等於 13 中，4 的倍數中最小的

```c=99
    /* align to align size byte */
    mp->block_total_count = mp->size / (mp->block_size + sizeof(rt_uint8_t *));
    mp->block_free_count  = mp->block_total_count;
```

- 接著手動算出 block 的總數

```c=102
    /* initialize suspended thread list */
    rt_list_init(&(mp->suspend_thread));
    mp->suspend_thread_count = 0;

    /* initialize free block list */
    block_ptr = (rt_uint8_t *)mp->start_address;
    for (offset = 0; offset < mp->block_total_count; offset ++)
    {
        *(rt_uint8_t **)(block_ptr + offset * (block_size + sizeof(rt_uint8_t *))) =
            (rt_uint8_t *)(block_ptr + (offset + 1) * (block_size + sizeof(rt_uint8_t *)));
    }

    *(rt_uint8_t **)(block_ptr + (offset - 1) * (block_size + sizeof(rt_uint8_t *))) =
        RT_NULL;

    mp->block_list = block_ptr;

    return RT_EOK;
}
RTM_EXPORT(rt_mp_init);
```

- 其他的動作皆相同

---
## 刪除 memory pool
### 動態

| 功能 | 回傳值 | `mp` |
| --- | ------ | ---- |
| 刪除 mempool（使用 heap）| `RT_EOK` | 欲刪除的 mempool |

```c=241
/**
 * This function will delete a memory pool and release the object memory.
 *
 * @param mp the memory pool object
 *
 * @return RT_EOK
 */
rt_err_t rt_mp_delete(rt_mp_t mp)
{
    struct rt_thread *thread;
    register rt_ubase_t temp;

    RT_DEBUG_NOT_IN_INTERRUPT;

    /* parameter check */
    RT_ASSERT(mp != RT_NULL);
    RT_ASSERT(rt_object_get_type(&mp->parent) == RT_Object_Class_MemPool);
    RT_ASSERT(rt_object_is_systemobject(&mp->parent) == RT_FALSE);

    /* wake up all suspended threads */
    while (!rt_list_isempty(&(mp->suspend_thread)))
    {
        /* disable interrupt */
        temp = rt_hw_interrupt_disable();

        /* get next suspend thread */
        thread = rt_list_entry(mp->suspend_thread.next, struct rt_thread, tlist);
        /* set error code to RT_ERROR */
        thread->error = -RT_ERROR;
```

- 當要把 mempool 刪除前，先將正在等待分配記憶體的 thread 一個一個叫醒
- 叫醒前，先將錯誤碼改成 `ERROR`

```c=270
        /*
         * resume thread
         * In rt_thread_resume function, it will remove current thread from
         * suspend list
         */
        rt_thread_resume(thread);
```

- 接著透過 `rt_thread_resume` 叫醒 thread

從等待鏈上移出的動作，在 `rt_thread_resume` 中會實現。
（code in [RT-Thread Thread](/2018/11/19/rt-thread-thread#暫停、復原-thread)）

```c=276
        /* decrease suspended thread count */
        mp->suspend_thread_count --;

        /* enable interrupt */
        rt_hw_interrupt_enable(temp);
    }
```

- 最後更新 `suspend_thread_count`

```c=282
    /* release allocated room */
    rt_free(mp->start_address);

    /* detach object */
    rt_object_delete(&(mp->parent));

    return RT_EOK;
}
RTM_EXPORT(rt_mp_delete);
```

- 叫醒完，free 掉建立 mempool 時所要的記憶體
- 再透過 `rt_object_delete` 刪除

---
### 靜態

| 功能 | 回傳值 | `*mp` |
| --- | ------ | ---- |
| 刪除 mempool | `RT_EOK` | 欲刪除的 mempool |

```c=125
/**
 * This function will detach a memory pool from system object management.
 *
 * @param mp the memory pool object
 *
 * @return RT_EOK
 */
rt_err_t rt_mp_detach(struct rt_mempool *mp)
{
    struct rt_thread *thread;
    register rt_ubase_t temp;

    /* parameter check */
    RT_ASSERT(mp != RT_NULL);
    RT_ASSERT(rt_object_get_type(&mp->parent) == RT_Object_Class_MemPool);
    RT_ASSERT(rt_object_is_systemobject(&mp->parent));

    /* wake up all suspended threads */
    while (!rt_list_isempty(&(mp->suspend_thread)))
    {
        /* disable interrupt */
        temp = rt_hw_interrupt_disable();

        /* get next suspend thread */
        thread = rt_list_entry(mp->suspend_thread.next, struct rt_thread, tlist);
        /* set error code to RT_ERROR */
        thread->error = -RT_ERROR;

        /*
         * resume thread
         * In rt_thread_resume function, it will remove current thread from
         * suspend list
         */
        rt_thread_resume(thread);

        /* decrease suspended thread count */
        mp->suspend_thread_count --;

        /* enable interrupt */
        rt_hw_interrupt_enable(temp);
    }

    /* detach object */
    rt_object_detach(&(mp->parent));

    return RT_EOK;
}
RTM_EXPORT(rt_mp_detach);
```

- 如果是靜態的，就不需要 free

---
## Code: allocate

| 功能 | 回傳值 |
| --- | ------ |
| 分配記憶體 | 一塊 free block |

| `mp`    | `time` |
| ------- | ------ |
| mempool | 等待時間 |

```c=296
/**
 * This function will allocate a block from memory pool
 *
 * @param mp the memory pool object
 * @param time the waiting time
 *
 * @return the allocated memory block or RT_NULL on allocated failed
 */
void *rt_mp_alloc(rt_mp_t mp, rt_int32_t time)
{
    rt_uint8_t *block_ptr;
    register rt_base_t level;
    struct rt_thread *thread;
    rt_uint32_t before_sleep = 0;

    /* get current thread */
    thread = rt_thread_self();

    /* disable interrupt */
    level = rt_hw_interrupt_disable();

    while (mp->block_free_count == 0)
    {
        /* memory block is unavailable. */
        if (time == 0)
        {
            /* enable interrupt */
            rt_hw_interrupt_enable(level);

            rt_set_errno(-RT_ETIMEOUT);

            return RT_NULL;
        }
```

- 如果目前無法取得記憶體，且等待時間為 0，回傳 NULL，並設置錯誤碼為 TIMEOUT

```c=329
        RT_DEBUG_NOT_IN_INTERRUPT;

        thread->error = RT_EOK;

        /* need suspend thread */
        rt_thread_suspend(thread);
        rt_list_insert_after(&(mp->suspend_thread), &(thread->tlist));
        mp->suspend_thread_count++;

        if (time > 0)
        {
            /* get the start tick of timer */
            before_sleep = rt_tick_get();

            /* init thread timer and start it */
            rt_timer_control(&(thread->thread_timer),
                             RT_TIMER_CTRL_SET_TIME,
                             &time);
            rt_timer_start(&(thread->thread_timer));
        }
```

- 如果需要等待，啟動一個 timer

```c=349
        /* enable interrupt */
        rt_hw_interrupt_enable(level);

        /* do a schedule */
        rt_schedule();
```

- 並做一次調度

```c=354
        if (thread->error != RT_EOK)
            return RT_NULL;

        if (time > 0)
        {
            time -= rt_tick_get() - before_sleep;
            if (time < 0)
                time = 0;
        }
        /* disable interrupt */
        level = rt_hw_interrupt_disable();
    }
```

- 最後更新 time 值

```c=366
    /* memory block is available. decrease the free block counter */
    mp->block_free_count--;
```

- 如果可以要記憶體，更新 `block_free_count`

```c=368
    /* get block from block list */
    block_ptr = mp->block_list;
    RT_ASSERT(block_ptr != RT_NULL);
```

- 取得第一顆

```c=371
    /* Setup the next free node. */
    mp->block_list = *(rt_uint8_t **)block_ptr;
```

- 並將 free list 往後一顆
- `block_list` 是使用間接定址，前 8-bit 是下一顆的位置

```c=373
    /* point to memory pool */
    *(rt_uint8_t **)block_ptr = (rt_uint8_t *)mp;
```

- 接著將前 8-bit 指向原來的 mempool

```c=375
    /* enable interrupt */
    rt_hw_interrupt_enable(level);

    RT_OBJECT_HOOK_CALL(rt_mp_alloc_hook,
                        (mp, (rt_uint8_t *)(block_ptr + sizeof(rt_uint8_t *))));

    return (rt_uint8_t *)(block_ptr + sizeof(rt_uint8_t *));
}
RTM_EXPORT(rt_mp_alloc);
```

- 最後回傳取得的 free block

- 注意這裡回傳的是 `block_ptr + 8`，也就是真正可以使用的位址
- 如果要尋找這個 block 所屬的 mempool 則需要 -8。

---
## Code: free

| 功能 | 回傳值 | `*block` |
| --- | -------- | -------- |
| 釋放記憶體 | void | 所要釋放的記憶體塊 |

```c=393
/**
 * This function will release a memory block
 *
 * @param block the address of memory block to be released
 */
void rt_mp_free(void *block)
{
    rt_uint8_t **block_ptr;
    struct rt_mempool *mp;
    struct rt_thread *thread;
    register rt_base_t level;

    /* get the control block of pool which the block belongs to */
    block_ptr = (rt_uint8_t **)((rt_uint8_t *)block - sizeof(rt_uint8_t *));
    mp        = (struct rt_mempool *)*block_ptr;
```

- 首先取得所屬的 mempool（-8）

```c=408
    RT_OBJECT_HOOK_CALL(rt_mp_free_hook, (mp, block));

    /* disable interrupt */
    level = rt_hw_interrupt_disable();

    /* increase the free block count */
    mp->block_free_count ++;

    /* link the block into the block list */
    *block_ptr = mp->block_list;
    mp->block_list = (rt_uint8_t *)block_ptr;
```

- 更新 `block_free_count`
- 接著定址到 free list，重新指定 block list
- 也就是將此 block 插到第一顆

```c=419
    if (mp->suspend_thread_count > 0)
    {
        /* get the suspended thread */
        thread = rt_list_entry(mp->suspend_thread.next,
                               struct rt_thread,
                               tlist);

        /* set error */
        thread->error = RT_EOK;

        /* resume thread */
        rt_thread_resume(thread);

        /* decrease suspended thread count */
        mp->suspend_thread_count --;

        /* enable interrupt */
        rt_hw_interrupt_enable(level);

        /* do a schedule */
        rt_schedule();

        return;
    }

    /* enable interrupt */
    rt_hw_interrupt_enable(level);
}
RTM_EXPORT(rt_mp_free);
```

- 如果有人在等資源，叫醒他，並做一次調度