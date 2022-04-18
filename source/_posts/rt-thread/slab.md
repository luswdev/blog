---
title: RT-Thread - SLAB
tag: [RT-Thread, 記憶體管理, kernel]
date: 2018-12-05 15:42:50
category: RT-Thread
summary: RTT 記憶體管理 4 slab，進階的動態的記憶體管理法，類似分頁管理。
---
>使用此管理方式： `#defined RT_USING_HEAP && #defined RT_USING_SLAB`

SLAB 將記憶體根據不同的對象切成不同的區 (zone)，對象通常是大小，也可看成是一個 zone 代表一個 pool，不同的 zone 放在一個 array 管理。

一個 zone 大小介於 32kB~128kB 之間，最多可以有 72 種 zone；zone 對象大小上上限 16kB，超過由頁分配器分配

- alloc：根據需要的大小，找到對應的 zone 取得記憶體；如假設需要 32kB，我們去尋找對象為 32kB 的 zone。
    - 若是該 zone 為空（找不到），直接向頁分配器分配一個新的 zone，取得第一塊 free chunk
    - 若非空，直接取得第一塊，如果拿完該 zone 已經沒有 free chunk 頁分配器須將此 zone 刪除
- free：找到對應的 zone 插入至 free list，如果該 zone 的所有 free chunk 都已經釋放完畢，則須將此 zone 整個釋放到分配器裡

![](https://i.imgur.com/GZdBl7V.png "SLAB example")

---
## 結構
{% alert success %}
**File:** slab.c
{% endalert %}

### Zone
```c=166
/*
 * The IN-BAND zone header is placed at the beginning of each zone.
 */
typedef struct slab_zone
{
    rt_int32_t  z_magic;        /* magic number for sanity check */
    rt_int32_t  z_nfree;        /* total free chunks / ualloc space in zone */
    rt_int32_t  z_nmax;         /* maximum free chunks */
    struct slab_zone *z_next;   /* zoneary[] link if z_nfree non-zero */
    rt_uint8_t  *z_baseptr;     /* pointer to start of chunk array */
    rt_int32_t  z_uindex;       /* current initial allocation index */
    rt_int32_t  z_chunksize;    /* chunk size for validation */
    rt_int32_t  z_zoneindex;    /* zone index */
    slab_chunk  *z_freechunk;   /* free chunk list */
} slab_zone;
```

```c=158
/*
 * Chunk structure for free elements
 */
typedef struct slab_chunk
{
    struct slab_chunk *c_next;
} slab_chunk;
```

---
### Page Allocator
```c=224
/* page allocator */
struct rt_page_head
{
    struct rt_page_head *next;      /* next valid page */
    rt_size_t page;                 /* number of page  */
    /* dummy */
    char dummy[RT_MM_PAGE_SIZE - (sizeof(struct rt_page_head *) + sizeof(rt_size_t))];
};
```

---
### Descriptor
```c=207
/*
 * Array of descriptors that describe the contents of each page
 */
#define PAGE_TYPE_FREE      0x00
#define PAGE_TYPE_SMALL     0x01
#define PAGE_TYPE_LARGE     0x02
struct memusage
{
    rt_uint32_t type: 2 ;       /* page type */
    rt_uint32_t size: 30;       /* pages allocated or offset from zone */
};
```

---
## 初始化 heap

| 功能 | 回傳值 |
| --- | ------ |
| 初始化 heap | void |

| `*begin_addr` | `*end_addr` |
| ------------- | ----------- |
| 記憶體起始位址 | 結束位址 |

```c=337
/**
 * @ingroup SystemInit
 *
 * This function will init system heap
 *
 * @param begin_addr the beginning address of system page
 * @param end_addr the end address of system page
 */
void rt_system_heap_init(void *begin_addr, void *end_addr)
{
    rt_uint32_t limsize, npages;

    RT_DEBUG_NOT_IN_INTERRUPT;

    /* align begin and end addr to page */
    heap_start = RT_ALIGN((rt_uint32_t)begin_addr, RT_MM_PAGE_SIZE);
    heap_end   = RT_ALIGN_DOWN((rt_uint32_t)end_addr, RT_MM_PAGE_SIZE);

    if (heap_start >= heap_end)
    {
        rt_kprintf("rt_system_heap_init, wrong address[0x%x - 0x%x]\n",
                   (rt_uint32_t)begin_addr, (rt_uint32_t)end_addr);

        return;
    }
```

- 向上對齊起始位址，向下對其結束位址
- 檢查是否合法

```c=362
    limsize = heap_end - heap_start;
    npages  = limsize / RT_MM_PAGE_SIZE;
```

- 計算最大的 size，設定頁數量

```c=364
    /* initialize heap semaphore */
    rt_sem_init(&heap_sem, "heap", 1, RT_IPC_FLAG_FIFO);

    RT_DEBUG_LOG(RT_DEBUG_SLAB, ("heap[0x%x - 0x%x], size 0x%x, 0x%x pages\n",
                                 heap_start, heap_end, limsize, npages));
```

- 初始化 semaphore，值為 1

```c=369
    /* init pages */
    rt_page_init((void *)heap_start, npages);
```

- 初始化 page

```c=371
    /* calculate zone size */
    zone_size = ZALLOC_MIN_ZONE_SIZE;
    while (zone_size < ZALLOC_MAX_ZONE_SIZE && (zone_size << 1) < (limsize / 1024))
        zone_size <<= 1;

    zone_limit = zone_size / 4;
    if (zone_limit > ZALLOC_ZONE_LIMIT)
        zone_limit = ZALLOC_ZONE_LIMIT;

    zone_page_cnt = zone_size / RT_MM_PAGE_SIZE;
```

- 計算 zone 的大小、對象大小的上限及總頁數

```c=381
    RT_DEBUG_LOG(RT_DEBUG_SLAB, ("zone size 0x%x, zone page count 0x%x\n",
                                 zone_size, zone_page_cnt));

    /* allocate memusage array */
    limsize  = npages * sizeof(struct memusage);
    limsize  = RT_ALIGN(limsize, RT_MM_PAGE_SIZE);
    memusage = rt_page_alloc(limsize / RT_MM_PAGE_SIZE);

    RT_DEBUG_LOG(RT_DEBUG_SLAB, ("memusage 0x%x, size 0x%x\n",
                                 (rt_uint32_t)memusage, limsize));
}
```

- 最後建立一個陣列紀錄頁的資訊

---
### rt_page_init

| 功能 | 回傳值 |
| --- | ------ |
| 初始化頁分配器 | void |

| `*addr` | `npages` |
| -------- | ------- |
| 存放頁的記憶體位址 | 頁的總數 |

```c=324
/*
 * Initialize the page allocator
 */
static void rt_page_init(void *addr, rt_size_t npages)
{
    RT_ASSERT(addr != RT_NULL);
    RT_ASSERT(npages != 0);

    rt_page_list = RT_NULL;
    rt_page_free(addr, npages);
}
```

- 將 page list 設為空，釋放所有的 page

---
## 分配記憶體
### rt_malloc

| 功能 | 回傳值 | `size` |
| --- | ------ | ------ |
| 要求記憶體 | 記憶體位址 | 欲要求的大小 |

```c=467
/**
 * This function will allocate a block from system heap memory.
 * - If the nbytes is less than zero,
 * or
 * - If there is no nbytes sized memory valid in system,
 * the RT_NULL is returned.
 *
 * @param size the size of memory to be allocated
 *
 * @return the allocated memory
 */
void *rt_malloc(rt_size_t size)
{
    slab_zone *z;
    rt_int32_t zi;
    slab_chunk *chunk;
    struct memusage *kup;

    /* zero size, return RT_NULL */
    if (size == 0)
        return RT_NULL;
```

- 如果 size = 0，回傳 NULL

```c=488
    /*
     * Handle large allocations directly.  There should not be very many of
     * these so performance is not a big issue.
     */
    if (size >= zone_limit)
    {
        size = RT_ALIGN(size, RT_MM_PAGE_SIZE);

        chunk = rt_page_alloc(size >> RT_MM_PAGE_BITS);
        if (chunk == RT_NULL)
            return RT_NULL;
```

- 如果 size 超過一個 chunk 的上限，則透過頁分配器來分配
- 且如果失敗了，直接回傳 `NULL`

```c=499
        /* set kup */
        kup = btokup(chunk);
        kup->type = PAGE_TYPE_LARGE;
        kup->size = size >> RT_MM_PAGE_BITS;
```

- 設定頁的資訊：
    - type：`PAGE_TYPE_LARGE`
    - size：用了幾頁
- btokup：`&memusage[((rt_uint32_t)(addr) - heap_start) >> RT_MM_PAGE_BITS]`
    - 找到陣列中與起始位置的差值，位移 12-bit，即除一頁的大小

```c=503
        RT_DEBUG_LOG(RT_DEBUG_SLAB,
                     ("malloc a large memory 0x%x, page cnt %d, kup %d\n",
                      size,
                      size >> RT_MM_PAGE_BITS,
                      ((rt_uint32_t)chunk - heap_start) >> RT_MM_PAGE_BITS));

        /* lock heap */
        rt_sem_take(&heap_sem, RT_WAITING_FOREVER);

#ifdef RT_MEM_STATS
        used_mem += size;
        if (used_mem > max_mem)
            max_mem = used_mem;
#endif
        goto done;
    }
```

- 要鎖，更新使用大小，跳到 `__done`

```c=519
    /* lock heap */
    rt_sem_take(&heap_sem, RT_WAITING_FOREVER);

    /*
     * Attempt to allocate out of an existing zone.  First try the free list,
     * then allocate out of unallocated space.  If we find a good zone move
     * it to the head of the list so later allocations find it quickly
     * (we might have thousands of zones in the list).
     *
     * Note: zoneindex() will panic of size is too large.
     */
    zi = zoneindex(&size);
    RT_ASSERT(zi < NZONES);
```

- 如果 size 小於一個 chunk 的上限，尋找此大小對應的 zone

```c=532
    RT_DEBUG_LOG(RT_DEBUG_SLAB, ("try to malloc 0x%x on zone: %d\n", size, zi));

    if ((z = zone_array[zi]) != RT_NULL)
    {
        RT_ASSERT(z->z_nfree > 0);

        /* Remove us from the zone_array[] when we become empty */
        if (--z->z_nfree == 0)
        {
            zone_array[zi] = z->z_next;
            z->z_next = RT_NULL;
        }
```

- 如果該 zone 不為空，且此 zone 剩最後一顆可用時，將此 zone 刪除

```c=544
        /*
         * No chunks are available but nfree said we had some memory, so
         * it must be available in the never-before-used-memory area
         * governed by uindex.  The consequences are very serious if our zone
         * got corrupted so we use an explicit rt_kprintf rather then a KASSERT.
         */
        if (z->z_uindex + 1 != z->z_nmax)
        {
            z->z_uindex = z->z_uindex + 1;
            chunk = (slab_chunk *)(z->z_baseptr + z->z_uindex * size);
        }
        else
        {
            /* find on free chunk list */
            chunk = z->z_freechunk;

            /* remove this chunk from list */
            z->z_freechunk = z->z_freechunk->c_next;
        }

#ifdef RT_MEM_STATS
        used_mem += z->z_chunksize;
        if (used_mem > max_mem)
            max_mem = used_mem;
#endif

        goto done;
    }
```

- 取得一塊，跳至 done
    - 從 `uindex` 找，這種方式取得的屬於此 zone 最初的 chunk
    - 如果不行，從 free list 中取得，並從 free list 移除此 chunk；這種的 chunk 是已經被要過，又還回來的

```c=572
    /*
     * If all zones are exhausted we need to allocate a new zone for this
     * index.
     *
     * At least one subsystem, the tty code (see CROUND) expects power-of-2
     * allocations to be power-of-2 aligned.  We maintain compatibility by
     * adjusting the base offset below.
     */
    {
        rt_int32_t off;

        if ((z = zone_free) != RT_NULL)
        {
            /* remove zone from free zone list */
            zone_free = z->z_next;
            -- zone_free_cnt;
        }
```

- 如果找到的 zone 為空，且 `zone_free` 不為空：代表有可用的空 zone 可以使用

```c=589
        else
        {
            /* unlock heap, since page allocator will think about lock */
            rt_sem_release(&heap_sem);

            /* allocate a zone from page */
            z = rt_page_alloc(zone_size / RT_MM_PAGE_SIZE);
            if (z == RT_NULL)
            {
                chunk = RT_NULL;
                goto __exit;
            }
```

- 否則需要重新與頁分配器要一個 zone

```c=601
            /* lock heap */
            rt_sem_take(&heap_sem, RT_WAITING_FOREVER);

            RT_DEBUG_LOG(RT_DEBUG_SLAB, ("alloc a new zone: 0x%x\n",
                                         (rt_uint32_t)z));

            /* set message usage */
            for (off = 0, kup = btokup(z); off < zone_page_cnt; off ++)
            {
                kup->type = PAGE_TYPE_SMALL;
                kup->size = off;

                kup ++;
            }
        }
```

- 接著設定每一頁的資訊

```c=616
        /* clear to zero */
        rt_memset(z, 0, sizeof(slab_zone));
```

- 清空整個 zone

```c=618
        /* offset of slab zone struct in zone */
        off = sizeof(slab_zone);

        /*
         * Guarentee power-of-2 alignment for power-of-2-sized chunks.
         * Otherwise just 8-byte align the data.
         */
        if ((size | (size - 1)) + 1 == (size << 1))
            off = (off + size - 1) & ~(size - 1);
        else
            off = (off + MIN_CHUNK_MASK) & ~MIN_CHUNK_MASK;
```

- 計算我們要用的對齊法：
    - 如果 size 是二的次方，將 off (zone 的頭) 與 size 向上對齊
    - 否則直接與 8 向上對齊

```c=629
        z->z_magic     = ZALLOC_SLAB_MAGIC;
        z->z_zoneindex = zi;
        z->z_nmax      = (zone_size - off) / size;
        z->z_nfree     = z->z_nmax - 1;
        z->z_baseptr   = (rt_uint8_t *)z + off;
        z->z_uindex    = 0;
        z->z_chunksize = size;
```

- 設定 magic、對應 `zone_array` 的 index
    - 最大數量為 `zone_size` - off 再除以一個 chunk 的大小
    - 目前可用的數量則為最大數量減 1，因為待會會拿走一塊
    - 基址為起始位址加上 `off，uindex` 為 0，這是之後 alloc 時可直接使用這兩個來找到 free chunk
    - 最後設定 chunk size

```c=636
        chunk = (slab_chunk *)(z->z_baseptr + z->z_uindex * size);

        /* link to zone array */
        z->z_next = zone_array[zi];
        zone_array[zi] = z;

#ifdef RT_MEM_STATS
        used_mem += z->z_chunksize;
        if (used_mem > max_mem)
            max_mem = used_mem;
#endif
    }
```

- 拿走第一塊，並將這個 zone 插上對應的 zone array entry

```c=648
done:
    rt_sem_release(&heap_sem);
    RT_OBJECT_HOOK_CALL(rt_malloc_hook, ((char *)chunk, size));

__exit:
    return chunk;
}
RTM_EXPORT(rt_malloc);
```

- 最後回傳找到的 chunk

---
#### zoneindex

| 功能 | 回傳值 | `*bytes` |
| --- | ------ | -------- |
| 尋找傳入的 size 對應 zone array 的 index | index | 傳入的大小 |

```c=397
/*
 * Calculate the zone index for the allocation request size and set the
 * allocation request size to that particular zone's chunk size.
 */
rt_inline int zoneindex(rt_uint32_t *bytes)
{
    /* unsigned for shift opt */
    rt_uint32_t n = (rt_uint32_t) * bytes;

    if (n < 128)
    {
        *bytes = n = (n + 7) & ~7;

        /* 8 byte chunks, 16 zones */
        return (n / 8 - 1);
    }
    if (n < 256)
    {
        *bytes = n = (n + 15) & ~15;

        return (n / 16 + 7);
    }
    if (n < 8192)
    {
        if (n < 512)
        {
            *bytes = n = (n + 31) & ~31;

            return (n / 32 + 15);
        }
        if (n < 1024)
        {
            *bytes = n = (n + 63) & ~63;

            return (n / 64 + 23);
        }
        if (n < 2048)
        {
            *bytes = n = (n + 127) & ~127;

            return (n / 128 + 31);
        }
        if (n < 4096)
        {
            *bytes = n = (n + 255) & ~255;

            return (n / 256 + 39);
        }
        *bytes = n = (n + 511) & ~511;

        return (n / 512 + 47);
    }
    if (n < 16384)
    {
        *bytes = n = (n + 1023) & ~1023;

        return (n / 1024 + 55);
    }

    rt_kprintf("Unexpected byte count %d", n);

    return 0;
}
```

根據不同的 range，將傳入的大小對齊，並平均分配每個 range 有 16 個 zone index

---
#### rt_page_alloc

| 功能 | 回傳值 | `npages` |
| --- | ------ | -------- |
| 要求頁記憶體 | 頁 | 欲要求的頁數 |

```c=236
void *rt_page_alloc(rt_size_t npages)
{
    struct rt_page_head *b, *n;
    struct rt_page_head **prev;

    if (npages == 0)
        return RT_NULL;

    /* lock heap */
    rt_sem_take(&heap_sem, RT_WAITING_FOREVER);
    for (prev = &rt_page_list; (b = *prev) != RT_NULL; prev = &(b->next))
    {
        if (b->page > npages)
        {
            /* splite pages */
            n       = b + npages;
            n->next = b->next;
            n->page = b->page - npages;
            *prev   = n;
            break;
        }
```

- 如果找到一個頁數大於需求的，選擇此頁，並分割

```c=257
        if (b->page == npages)
        {
            /* this node fit, remove this node */
            *prev = b->next;
            break;
        }
    }

    /* unlock heap */
    rt_sem_release(&heap_sem);

    return b;
}
```

- 如果有一個剛剛好，選擇此頁
- 最後回傳選擇的頁

---
### rt_realloc

| 功能 | 回傳值 |
| --- | ------ |
| 增長/縮減記憶體 | 記憶體位址 |

| `*rmem` | `newsize` |
| ------- | --------- |
| 欲增長/縮減的記憶體位址 | 新的大小 |

```c=670
/**
 * This function will change the size of previously allocated memory block.
 *
 * @param ptr the previously allocated memory block
 * @param size the new size of memory block
 *
 * @return the allocated memory
 */
void *rt_realloc(void *ptr, rt_size_t size)
{
    void *nptr;
    slab_zone *z;
    struct memusage *kup;

    if (ptr == RT_NULL)
        return rt_malloc(size);
    if (size == 0)
    {
        rt_free(ptr);

        return RT_NULL;
    }
```

- 如果傳入的 `ptr` 為空，`malloc(size)`
- 如果傳入的 `size` 為 0，`free(ptr)`

```c=692
    /*
     * Get the original allocation's zone.  If the new request winds up
     * using the same chunk size we do not have to do anything.
     */
    kup = btokup((rt_uint32_t)ptr & ~RT_MM_PAGE_MASK);
    if (kup->type == PAGE_TYPE_LARGE)
    {
        rt_size_t osize;

        osize = kup->size << RT_MM_PAGE_BITS;
        if ((nptr = rt_malloc(size)) == RT_NULL)
            return RT_NULL;
        rt_memcpy(nptr, ptr, size > osize ? osize : size);
        rt_free(ptr);

        return nptr;
    }
```

- 接著檢查此 `ptr` 所在的頁資訊，如果是 LARGE，代表原來的 `ptr` 是由頁分配器所分配的
- 新 `malloc(size)`，並還原資料，釋放舊的記憶體

```c=709
    else if (kup->type == PAGE_TYPE_SMALL)
    {
        z = (slab_zone *)(((rt_uint32_t)ptr & ~RT_MM_PAGE_MASK) -
                          kup->size * RT_MM_PAGE_SIZE);
        RT_ASSERT(z->z_magic == ZALLOC_SLAB_MAGIC);

        zoneindex(&size);
        if (z->z_chunksize == size)
            return (ptr); /* same chunk */
```

- 如果是 SMALL，首先找到歸屬得 zone：
    - 透過減掉頁資訊上的 size 乘以頁的大小，即可找到zone的初始位址
    - 在 `malloc` 中，建立 zone 時 size 是從 0 開始填，一頁一頁加一
    - 如果新的大小與原本的 chunk 相同，不做事

```c=718
        /*
         * Allocate memory for the new request size.  Note that zoneindex has
         * already adjusted the request size to the appropriate chunk size, which
         * should optimize our bcopy().  Then copy and return the new pointer.
         */
        if ((nptr = rt_malloc(size)) == RT_NULL)
            return RT_NULL;

        rt_memcpy(nptr, ptr, size > z->z_chunksize ? z->z_chunksize : size);
        rt_free(ptr);

        return nptr;
    }

    return RT_NULL;
}
RTM_EXPORT(rt_realloc);
```

- 如果不同，`malloc(size)`，並還原資料，釋放舊的記憶體

---
### rt_calloc

| 功能 | 回傳值 |
| --- | ------ |
| 要求一段連續的記憶體 | 記憶體位址 |

| `count` | `size` |
| ------- | ------ |
| 欲要求的數量 | 一塊的大小 |

```c=738
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

- 與 memheap 相同，一次要一塊 `count` 乘 `size` 的記憶體
- 清 0 並回傳起始位址

---
## 釋放記憶體

| 功能 | 回傳值 | `*ptr` |
| --- | ------ | ------ |
| 釋放記憶體 | void | 欲釋放的記憶體 |

```c=765
/**
 * This function will release the previous allocated memory block by rt_malloc.
 * The released memory block is taken back to system heap.
 *
 * @param ptr the address of memory which will be released
 */
void rt_free(void *ptr)
{
    slab_zone *z;
    slab_chunk *chunk;
    struct memusage *kup;

    /* free a RT_NULL pointer */
    if (ptr == RT_NULL)
        return ;

    RT_OBJECT_HOOK_CALL(rt_free_hook, (ptr));

    /* get memory usage */
#if RT_DEBUG_SLAB
    {
        rt_uint32_t addr = ((rt_uint32_t)ptr & ~RT_MM_PAGE_MASK);
        RT_DEBUG_LOG(RT_DEBUG_SLAB,
                     ("free a memory 0x%x and align to 0x%x, kup index %d\n",
                      (rt_uint32_t)ptr,
                      (rt_uint32_t)addr,
                      ((rt_uint32_t)(addr) - heap_start) >> RT_MM_PAGE_BITS));
    }
#endif

    kup = btokup((rt_uint32_t)ptr & ~RT_MM_PAGE_MASK);
    /* release large allocation */
    if (kup->type == PAGE_TYPE_LARGE)
    {
        rt_uint32_t size;

        /* lock heap */
        rt_sem_take(&heap_sem, RT_WAITING_FOREVER);
        /* clear page counter */
        size = kup->size;
        kup->size = 0;

#ifdef RT_MEM_STATS
        used_mem -= size * RT_MM_PAGE_SIZE;
#endif
        rt_sem_release(&heap_sem);

        RT_DEBUG_LOG(RT_DEBUG_SLAB,
                     ("free large memory block 0x%x, page count %d\n",
                      (rt_uint32_t)ptr, size));

        /* free this page */
        rt_page_free(ptr, size);

        return;
    }
```

- 如果要釋放的記憶體是由頁分配器分配的，根據頁資訊中的 size 來釋放，並清 0
- 實際呼叫 `rt_page_free(ptr, size)` 來完成

```c=821
    /* lock heap */
    rt_sem_take(&heap_sem, RT_WAITING_FOREVER);

    /* zone case. get out zone. */
    z = (slab_zone *)(((rt_uint32_t)ptr & ~RT_MM_PAGE_MASK) -
                      kup->size * RT_MM_PAGE_SIZE);
    RT_ASSERT(z->z_magic == ZALLOC_SLAB_MAGIC);

    chunk          = (slab_chunk *)ptr;
    chunk->c_next  = z->z_freechunk;
    z->z_freechunk = chunk;

#ifdef RT_MEM_STATS
    used_mem -= z->z_chunksize;
#endif
```

- 如果是由 zone 分配，找到歸屬的 zone，並將需要釋放的 chunk 插到 free list 上

```c=836
    /*
     * Bump the number of free chunks.  If it becomes non-zero the zone
     * must be added back onto the appropriate list.
     */
    if (z->z_nfree++ == 0)
    {
        z->z_next = zone_array[z->z_zoneindex];
        zone_array[z->z_zoneindex] = z;
    }
```

- 更新 `nfree`，如果本來為 0 ，則需要將此 zone 插回 zone array

```c=845
    /*
     * If the zone becomes totally free, and there are other zones we
     * can allocate from, move this zone to the FreeZones list.  Since
     * this code can be called from an IPI callback, do *NOT* try to mess
     * with kernel_map here.  Hysteresis will be performed at malloc() time.
     */
    if (z->z_nfree == z->z_nmax &&
        (z->z_next || zone_array[z->z_zoneindex] != z))
    {
        slab_zone **pz;

        RT_DEBUG_LOG(RT_DEBUG_SLAB, ("free zone 0x%x\n",
                                     (rt_uint32_t)z, z->z_zoneindex));

        /* remove zone from zone array list */
        for (pz = &zone_array[z->z_zoneindex]; z != *pz; pz = &(*pz)->z_next)
            ;
        *pz = z->z_next;
```

- 如果釋放完這個 chunk 後整個 zone 都釋放完了，我們需要釋放整個 zone
- 這裡還同時確保在同一個 zone array entry 中還有其他的 zone 可以分配
- 接著我們把這個 zone 從 zone array 移除

```c=863
        /* reset zone */
        z->z_magic = -1;

        /* insert to free zone list */
        z->z_next = zone_free;
        zone_free = z;

        ++ zone_free_cnt;
```

- 重設 magic，將這個 zone 插上 free zone，free count 加一

```c=871
        /* release zone to page allocator */
        if (zone_free_cnt > ZONE_RELEASE_THRESH)
        {
            register rt_base_t i;

            z         = zone_free;
            zone_free = z->z_next;
            -- zone_free_cnt;

            /* set message usage */
            for (i = 0, kup = btokup(z); i < zone_page_cnt; i ++)
            {
                kup->type = PAGE_TYPE_FREE;
                kup->size = 0;
                kup ++;
            }

            /* unlock heap */
            rt_sem_release(&heap_sem);

            /* release pages */
            rt_page_free(z, zone_size / RT_MM_PAGE_SIZE);

            return;
        }
    }
    /* unlock heap */
    rt_sem_release(&heap_sem);
}
RTM_EXPORT(rt_free);
```

- 如果已經有 `ZONE_RELEASE_THRESH` (2) 個以上的 free zone，完全釋放一個 zone 給頁分配器
    - 從 free zone 中移除，free count 減一
    - 重設頁資訊：type free、size 0
    - 透過 `rt_page_free` 完成

---
### rt_page_free

| 功能 | 回傳值 |
| --- | ------ |
| 釋放頁記憶體 | void |

| `*addr` | `pages` |
| ------- | ------- |
| 欲釋放的頁 | 欲釋放的大小 |

```c=272
void rt_page_free(void *addr, rt_size_t npages)
{
    struct rt_page_head *b, *n;
    struct rt_page_head **prev;
    RT_ASSERT(addr != RT_NULL);
    RT_ASSERT((rt_uint32_t)addr % RT_MM_PAGE_SIZE == 0);
    RT_ASSERT(npages != 0);
    n = (struct rt_page_head *)addr;
    /* lock heap */
    rt_sem_take(&heap_sem, RT_WAITING_FOREVER);
    for (prev = &rt_page_list; (b = *prev) != RT_NULL; prev = &(b->next))
    {
        RT_ASSERT(b->page > 0);
        RT_ASSERT(b > n || b + b->page <= n);
        if (b + b->page == n)
        {
            if (b + (b->page += npages) == b->next)
            {
                b->page += b->next->page;
                b->next  = b->next->next;
            }
            goto _return;
        }
        if (b == n + npages)
        {
            n->page = b->page + npages;
            n->next = b->next;
            *prev   = n;
            goto _return;
        }
        if (b > n + npages)
            break;
    }
    n->page = npages;
    n->next = b;
    *prev   = n;
_return:
    /* unlock heap */
    rt_sem_release(&heap_sem);
}
```