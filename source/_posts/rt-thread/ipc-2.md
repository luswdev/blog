---
title: RT-Thread - IPC Communicate
tag: [RT-Thread, kernel, IPC]
date: 2018-11-28 21:11:09
category: RT-Thread
summary: RTT IPC 2，一些通信的方式，包括 mail box 及 message queue
---

## Mail Box
- 類似於 pipe，主要用來傳輸資料
- 每一封郵件大小為 4 bytes（即 32 位元）

### 結構

{% alert success %}
**File:** rtdef.h
{% endalert %}

```c=642
#ifdef RT_USING_MAILBOX
/**
 * mailbox structure
 */
struct rt_mailbox
{
    struct rt_ipc_object parent;                        /**< inherit from ipc_object */

    rt_uint32_t         *msg_pool;                      /**< start address of message buffer */

    rt_uint16_t          size;                          /**< size of message pool */

    rt_uint16_t          entry;                         /**< index of messages in msg_pool */
    rt_uint16_t          in_offset;                     /**< input offset of the message buffer */
    rt_uint16_t          out_offset;                    /**< output offset of the message buffer */

    rt_list_t            suspend_sender_thread;         /**< sender thread suspended on this mailbox */
};
typedef struct rt_mailbox *rt_mailbox_t;
#endif
```

- `msg_pool` 指向郵件堆的起點，`entry` 紀錄總郵件的數量

---
{% alert success %}
**File:** ipc.c
{% endalert %}

### 建立 mail box
#### 動態記憶體管理

| 功能 | 回傳值 |
| --- | ------ |
| 建立 mail box | mail box |

| `*name` | `size` | `flag` |
| ------- | ------ | ------ |
| 名字 | mail box 大小 | FIFO / PRIO | 

```c=1348
/**
 * This function will create a mailbox object from system resource
 *
 * @param name the name of mailbox
 * @param size the size of mailbox
 * @param flag the flag of mailbox
 *
 * @return the created mailbox, RT_NULL on error happen
 */
rt_mailbox_t rt_mb_create(const char *name, rt_size_t size, rt_uint8_t flag)
{
    rt_mailbox_t mb;

    RT_DEBUG_NOT_IN_INTERRUPT;

    /* allocate object */
    mb = (rt_mailbox_t)rt_object_allocate(RT_Object_Class_MailBox, name);
    if (mb == RT_NULL)
        return mb;
```

- 首先 allocate 一塊給 mailbox

```c=1367
    /* set parent */
    mb->parent.parent.flag = flag;

    /* init ipc object */
    rt_ipc_object_init(&(mb->parent));
```

- 填入 flag 及初始化

```c=1372
    /* init mailbox */
    mb->size     = size;
    mb->msg_pool = RT_KERNEL_MALLOC(mb->size * sizeof(rt_uint32_t));
    if (mb->msg_pool == RT_NULL)
    {
        /* delete mailbox object */
        rt_object_delete(&(mb->parent.parent));

        return RT_NULL;
    }
```

- 因使用動態記憶體的緣故，需 allocate 一塊給郵件堆
- 大小為一封一件的大小 * size

```c=1382
    mb->entry      = 0;
    mb->in_offset  = 0;
    mb->out_offset = 0;

    /* init an additional list of sender suspend thread */
    rt_list_init(&(mb->suspend_sender_thread));

    return mb;
}
RTM_EXPORT(rt_mb_create);
```

- 最後初始化值及等待鏈

---
#### 靜態記憶體管理

| 功能 | 回傳值 |
| --- | ------ |
| 初始化 mail box | `RT_EOK` |

| `mb` | `*name` | `*msgpool` | `size` | `flag` |
| ---- | ------- | ---------- | ------ | ------ |
| mail box 本體 | 名字 | 存放郵件的地方 | mail box 大小 | FIFO / PRIO | 

```c=1278
/**
 * This function will initialize a mailbox and put it under control of resource
 * management.
 *
 * @param mb the mailbox object
 * @param name the name of mailbox
 * @param msgpool the begin address of buffer to save received mail
 * @param size the size of mailbox
 * @param flag the flag of mailbox
 *
 * @return the operation status, RT_EOK on successful
 */
rt_err_t rt_mb_init(rt_mailbox_t mb,
                    const char  *name,
                    void        *msgpool,
                    rt_size_t    size,
                    rt_uint8_t   flag)
{
    RT_ASSERT(mb != RT_NULL);

    /* init object */
    rt_object_init(&(mb->parent.parent), RT_Object_Class_MailBox, name);

    /* set parent flag */
    mb->parent.parent.flag = flag;

    /* init ipc object */
    rt_ipc_object_init(&(mb->parent));
```

- 這裡就不需要 allocate，可直接初始化來使用

```c=1306
    /* init mailbox */
    mb->msg_pool   = msgpool;
    mb->size       = size;
    mb->entry      = 0;
    mb->in_offset  = 0;
    mb->out_offset = 0;

    /* init an additional list of sender suspend thread */
    rt_list_init(&(mb->suspend_sender_thread));

    return RT_EOK;
}
RTM_EXPORT(rt_mb_init);
```

- 一樣，郵件堆可直接拿來用，初始化值及等待鏈

---
### 刪除 mail box
#### 動態記憶體管理

| 功能 | 回傳值 | `mb` |
| --- | ------ | ---- |
| 刪除 mail box | `RT_EOK` | 欲刪除的 mail box |

```c=1395
/**
 * This function will delete a mailbox object and release the memory
 *
 * @param mb the mailbox object
 *
 * @return the error code
 */
rt_err_t rt_mb_delete(rt_mailbox_t mb)
{
    RT_DEBUG_NOT_IN_INTERRUPT;

    /* parameter check */
    RT_ASSERT(mb != RT_NULL);
    RT_ASSERT(rt_object_get_type(&mb->parent.parent) == RT_Object_Class_MailBox);
    RT_ASSERT(rt_object_is_systemobject(&mb->parent.parent) == RT_FALSE);

    /* resume all suspended thread */
    rt_ipc_list_resume_all(&(mb->parent.suspend_thread));

    /* also resume all mailbox private suspended thread */
    rt_ipc_list_resume_all(&(mb->suspend_sender_thread));
```

- 首先將正在等待郵件的，與正在等待傳送的 thread 叫醒

```c=1416
    /* free mailbox pool */
    RT_KERNEL_FREE(mb->msg_pool);

    /* delete mailbox object */
    rt_object_delete(&(mb->parent.parent));

    return RT_EOK;
}
RTM_EXPORT(rt_mb_delete);
```

- 歸還郵件堆，最後刪除 mail box

---
#### 靜態記憶體管理

| 功能 | 回傳值 | `mb` |
| --- | ------ | ---- |
| 刪除 mail box | `RT_EOK` | 欲刪除的 mail box |

```c=1321
/**
 * This function will detach a mailbox from resource management
 *
 * @param mb the mailbox object
 *
 * @return the operation status, RT_EOK on successful
 */
rt_err_t rt_mb_detach(rt_mailbox_t mb)
{
    /* parameter check */
    RT_ASSERT(mb != RT_NULL);
    RT_ASSERT(rt_object_get_type(&mb->parent.parent) == RT_Object_Class_MailBox);
    RT_ASSERT(rt_object_is_systemobject(&mb->parent.parent));

    /* resume all suspended thread */
    rt_ipc_list_resume_all(&(mb->parent.suspend_thread));
    /* also resume all mailbox private suspended thread */
    rt_ipc_list_resume_all(&(mb->suspend_sender_thread));
```

- 首先將正在等待郵件的，與正在等待傳送的 thread 叫醒

```c=1339
    /* detach mailbox object */
    rt_object_detach(&(mb->parent.parent));

    return RT_EOK;
}
RTM_EXPORT(rt_mb_detach);
```

- 最後刪除 mail box（使用 `detach`）

---
### 傳送郵件

| 功能 | 回傳值 |
| --- | ------ |
| 傳送郵件 | `RT_EOK` |

| `mb` | `value` | `timeout` |
| ---- | ------- | --------- |
| 欲傳送的 mailbox | 郵件內容 | 等待時間（如果需要）|

```c=1428
/**
 * This function will send a mail to mailbox object. If the mailbox is full,
 * current thread will be suspended until timeout.
 *
 * @param mb the mailbox object
 * @param value the mail
 * @param timeout the waiting time
 *
 * @return the error code
 */
rt_err_t rt_mb_send_wait(rt_mailbox_t mb,
                         rt_uint32_t  value,
                         rt_int32_t   timeout)
{
    struct rt_thread *thread;
    register rt_ubase_t temp;
    rt_uint32_t tick_delta;

    /* parameter check */
    RT_ASSERT(mb != RT_NULL);
    RT_ASSERT(rt_object_get_type(&mb->parent.parent) == RT_Object_Class_MailBox);

    /* initialize delta tick */
    tick_delta = 0;
    /* get current thread */
    thread = rt_thread_self();

    RT_OBJECT_HOOK_CALL(rt_object_put_hook, (&(mb->parent.parent)));

    /* disable interrupt */
    temp = rt_hw_interrupt_disable();

    /* for non-blocking call */
    if (mb->entry == mb->size && timeout == 0)
    {
        rt_hw_interrupt_enable(temp);

        return -RT_EFULL;
    }

    /* mailbox is full */
    while (mb->entry == mb->size)
    {
        /* reset error number in thread */
        thread->error = RT_EOK;

        /* no waiting, return timeout */
        if (timeout == 0)
        {
            /* enable interrupt */
            rt_hw_interrupt_enable(temp);

            return -RT_EFULL;
        }
```

- 如果 mail box 滿了，且不等待 (`timeout==0`)，回傳 `FULL`

```c=1482
        RT_DEBUG_IN_THREAD_CONTEXT;
        /* suspend current thread */
        rt_ipc_list_suspend(&(mb->suspend_sender_thread),
                            thread,
                            mb->parent.parent.flag);

        /* has waiting time, start thread timer */
        if (timeout > 0)
        {
            /* get the start tick of timer */
            tick_delta = rt_tick_get();

            RT_DEBUG_LOG(RT_DEBUG_IPC, ("mb_send_wait: start timer of thread:%s\n",
                                        thread->name));

            /* reset the timeout of thread timer and start it */
            rt_timer_control(&(thread->thread_timer),
                             RT_TIMER_CTRL_SET_TIME,
                             &timeout);
            rt_timer_start(&(thread->thread_timer));
        }
```

- 若要等待，將 thread 掛上等待鏈，啟動一個 timer

```c=1503
        /* enable interrupt */
        rt_hw_interrupt_enable(temp);

        /* re-schedule */
        rt_schedule();
```

- 再做一次調度

```c=1508
        /* resume from suspend state */
        if (thread->error != RT_EOK)
        {
            /* return error */
            return thread->error;
        }

        /* disable interrupt */
        temp = rt_hw_interrupt_disable();

        /* if it's not waiting forever and then re-calculate timeout tick */
        if (timeout > 0)
        {
            tick_delta = rt_tick_get() - tick_delta;
            timeout -= tick_delta;
            if (timeout < 0)
                timeout = 0;
        }
    }
```

- 如跳回來，重新計算 `timeout`

```c=1527
    /* set ptr */
    mb->msg_pool[mb->in_offset] = value;
    /* increase input offset */
    ++ mb->in_offset;
    if (mb->in_offset >= mb->size)
        mb->in_offset = 0;
    /* increase message entry */
    mb->entry ++;
```

- 若可以寫入，將資料寫入，同時更新 `offset` 及 `entry`

```c=1535
    /* resume suspended thread */
    if (!rt_list_isempty(&mb->parent.suspend_thread))
    {
        rt_ipc_list_resume(&(mb->parent.suspend_thread));

        /* enable interrupt */
        rt_hw_interrupt_enable(temp);

        rt_schedule();

        return RT_EOK;
    }

    /* enable interrupt */
    rt_hw_interrupt_enable(temp);

    return RT_EOK;
}
RTM_EXPORT(rt_mb_send_wait);
```

- 如果有人在等待寄信，叫醒他，做一次調度

---
- 若是不想等待，可以使用 `rt_mb_send`

| 功能 | 回傳值 |
| --- | ------ |
| 傳送郵件（不等待）| `RT_EOK` |

| `mb` | `value` |
| ---- | ------- |
| 欲傳送的 mailbox | 郵件內容 |

```c=1560
/**
 * This function will send a mail to mailbox object, if there are threads
 * suspended on mailbox object, it will be waked up. This function will return
 * immediately, if you want blocking send, use rt_mb_send_wait instead.
 *
 * @param mb the mailbox object
 * @param value the mail
 *
 * @return the error code
 */
rt_err_t rt_mb_send(rt_mailbox_t mb, rt_uint32_t value)
{
    return rt_mb_send_wait(mb, value, 0);
}
RTM_EXPORT(rt_mb_send);
```

- 即 `timeout == 0`

---
### 接受郵件

| 功能 | 回傳值 |
| --- | ------ |
| 接受郵件 | `RT_EOK` |

| `mb` | `*value` | `timeout` |
| ---- | ------- | --------- |
| 欲收信的 mailbox | 郵件內容 | 等待時間（如果需要）|

```c=1576
/**
 * This function will receive a mail from mailbox object, if there is no mail
 * in mailbox object, the thread shall wait for a specified time.
 *
 * @param mb the mailbox object
 * @param value the received mail will be saved in
 * @param timeout the waiting time
 *
 * @return the error code
 */
rt_err_t rt_mb_recv(rt_mailbox_t mb, rt_uint32_t *value, rt_int32_t timeout)
{
    struct rt_thread *thread;
    register rt_ubase_t temp;
    rt_uint32_t tick_delta;

    /* parameter check */
    RT_ASSERT(mb != RT_NULL);
    RT_ASSERT(rt_object_get_type(&mb->parent.parent) == RT_Object_Class_MailBox);

    /* initialize delta tick */
    tick_delta = 0;
    /* get current thread */
    thread = rt_thread_self();

    RT_OBJECT_HOOK_CALL(rt_object_trytake_hook, (&(mb->parent.parent)));

    /* disable interrupt */
    temp = rt_hw_interrupt_disable();

    /* for non-blocking call */
    if (mb->entry == 0 && timeout == 0)
    {
        rt_hw_interrupt_enable(temp);

        return -RT_ETIMEOUT;
    }

    /* mailbox is empty */
    while (mb->entry == 0)
    {
        /* reset error number in thread */
        thread->error = RT_EOK;

        /* no waiting, return timeout */
        if (timeout == 0)
        {
            /* enable interrupt */
            rt_hw_interrupt_enable(temp);

            thread->error = -RT_ETIMEOUT;

            return -RT_ETIMEOUT;
        }
```

- 如果 mail box 沒東西，且不等待，回傳 `TIMEOUT`

```c=1630
        RT_DEBUG_IN_THREAD_CONTEXT;
        /* suspend current thread */
        rt_ipc_list_suspend(&(mb->parent.suspend_thread),
                            thread,
                            mb->parent.parent.flag);

        /* has waiting time, start thread timer */
        if (timeout > 0)
        {
            /* get the start tick of timer */
            tick_delta = rt_tick_get();

            RT_DEBUG_LOG(RT_DEBUG_IPC, ("mb_recv: start timer of thread:%s\n",
                                        thread->name));

            /* reset the timeout of thread timer and start it */
            rt_timer_control(&(thread->thread_timer),
                             RT_TIMER_CTRL_SET_TIME,
                             &timeout);
            rt_timer_start(&(thread->thread_timer));
        }
```

- 若要等待，將 thread 掛上等待鏈，啟動一個 timer

```c=1651
        /* enable interrupt */
        rt_hw_interrupt_enable(temp);

        /* re-schedule */
        rt_schedule();
```

- 再做一次調度

```c=1656
        /* resume from suspend state */
        if (thread->error != RT_EOK)
        {
            /* return error */
            return thread->error;
        }

        /* disable interrupt */
        temp = rt_hw_interrupt_disable();

        /* if it's not waiting forever and then re-calculate timeout tick */
        if (timeout > 0)
        {
            tick_delta = rt_tick_get() - tick_delta;
            timeout -= tick_delta;
            if (timeout < 0)
                timeout = 0;
        }
    }
```

- 如跳回來，重新計算 `timeout`

```c=1675
    /* fill ptr */
    *value = mb->msg_pool[mb->out_offset];

    /* increase output offset */
    ++ mb->out_offset;
    if (mb->out_offset >= mb->size)
        mb->out_offset = 0;
    /* decrease message entry */
    mb->entry --;
```

- 若可以讀取，將資料寫入，同時更新 `offset` 及 `entry`

```c=1684
    /* resume suspended thread */
    if (!rt_list_isempty(&(mb->suspend_sender_thread)))
    {
        rt_ipc_list_resume(&(mb->suspend_sender_thread));

        /* enable interrupt */
        rt_hw_interrupt_enable(temp);

        RT_OBJECT_HOOK_CALL(rt_object_take_hook, (&(mb->parent.parent)));

        rt_schedule();

        return RT_EOK;
    }

    /* enable interrupt */
    rt_hw_interrupt_enable(temp);

    RT_OBJECT_HOOK_CALL(rt_object_take_hook, (&(mb->parent.parent)));

    return RT_EOK;
}
RTM_EXPORT(rt_mb_recv);
```

- 如果有人在等待收信，叫醒他，做一次調度

---
## Message Queue
- 特性：可接受不固定長度的訊息

### 結構

{% alert success %}
**File:** rtdef.h
{% endalert %}

```c=663
#ifdef RT_USING_MESSAGEQUEUE
/**
 * message queue structure
 */
struct rt_messagequeue
{
    struct rt_ipc_object parent;                        /**< inherit from ipc_object */

    void                *msg_pool;                      /**< start address of message queue */

    rt_uint16_t          msg_size;                      /**< message size of each message */
    rt_uint16_t          max_msgs;                      /**< max number of messages */

    rt_uint16_t          entry;                         /**< index of messages in the queue */

    void                *msg_queue_head;                /**< list head */
    void                *msg_queue_tail;                /**< list tail */
    void                *msg_queue_free;                /**< pointer indicated the free node of queue */
};
typedef struct rt_messagequeue *rt_mq_t;
#endif
```

---

{% alert success %}
**File:** ipc.c
{% endalert %}

### 建立 message queue
#### 動態記憶體管理

| 功能 | 回傳值 |
| --- | ------ |
| 建立 message queue | message queue |

| `*name` | `msg_size` | `max_msgs` | `flag` |
| ------- | ---------- | ---------- | ------ |
| 名字 | 一封訊息的大小 | 訊息數上限 | FIFO / PRIO |

```c=1852
/**
 * This function will create a message queue object from system resource
 *
 * @param name the name of message queue
 * @param msg_size the size of message
 * @param max_msgs the maximum number of message in queue
 * @param flag the flag of message queue
 *
 * @return the created message queue, RT_NULL on error happen
 */
rt_mq_t rt_mq_create(const char *name,
                     rt_size_t   msg_size,
                     rt_size_t   max_msgs,
                     rt_uint8_t  flag)
{
    struct rt_messagequeue *mq;
    struct rt_mq_message *head;
    register rt_base_t temp;

    RT_DEBUG_NOT_IN_INTERRUPT;

    /* allocate object */
    mq = (rt_mq_t)rt_object_allocate(RT_Object_Class_MessageQueue, name);
    if (mq == RT_NULL)
        return mq;

    /* set parent */
    mq->parent.parent.flag = flag;

    /* init ipc object */
    rt_ipc_object_init(&(mq->parent));
```

- 首先要一塊物件給 message queue，並同時填入 flag 及初始化

```c=1883
    /* init message queue */

    /* get correct message size */
    mq->msg_size = RT_ALIGN(msg_size, RT_ALIGN_SIZE);
    mq->max_msgs = max_msgs;
```

- 設定訊息的大小，與訊息數量的上限

{% alert info %}
`RT_ALGIN` 目的在對齊訊息的大小，根據不同板子所定義不同的 `RT_ALIGN_SIZE` 會有所差別<br>
`#define RT_ALIGN(size, align)           (((size) + (align) - 1) & ~((align) - 1))`
- 如傳進來的是 `RT_ALGIN(7,8)` 則結果是 8
- 如傳進來的是 `RT_ALGIN(13,4)` 則結果是 16
- 即結果為大於後值的**最小倍數**
{% endalert %}


```c=1888
    /* allocate message pool */
    mq->msg_pool = RT_KERNEL_MALLOC((mq->msg_size + sizeof(struct rt_mq_message)) * mq->max_msgs);
    if (mq->msg_pool == RT_NULL)
    {
        rt_mq_delete(mq);

        return RT_NULL;
    }
```

- 接著需 allocate 適當的記憶體存放訊息

```c=1896
    /* init message list */
    mq->msg_queue_head = RT_NULL;
    mq->msg_queue_tail = RT_NULL;

    /* init message empty list */
    mq->msg_queue_free = RT_NULL;
    for (temp = 0; temp < mq->max_msgs; temp ++)
    {
        head = (struct rt_mq_message *)((rt_uint8_t *)mq->msg_pool +
                                        temp * (mq->msg_size + sizeof(struct rt_mq_message)));
        head->next = mq->msg_queue_free;
        mq->msg_queue_free = head;
    }
```

- 先將頭尾設為空，再一塊一塊的將 `msg_pool` 插在 free list 的第一顆

```c=1909
    /* the initial entry is zero */
    mq->entry = 0;

    return mq;
}
RTM_EXPORT(rt_mq_create);
```

- 最後設定 `entry` 為 0

---
#### 靜態記憶體管理

| 功能 | 回傳值 |
| --- | ------ |
| 初始化 message queue | `RT_EOK` |

| `mq` | `*name` | `*msgpool` | 
| ---- | ------- | ---------- | 
| message queue 本體 | 名字 | 存放訊息的位址 |

| `msg_size` | `pool_size` | `flag` |
| ---------- | ----------- | ------ |
| 一封訊息的大小 | 存放訊息的大小 | FIFO / PRIO |

```c=1764
/**
 * This function will initialize a message queue and put it under control of
 * resource management.
 *
 * @param mq the message object
 * @param name the name of message queue
 * @param msgpool the beginning address of buffer to save messages
 * @param msg_size the maximum size of message
 * @param pool_size the size of buffer to save messages
 * @param flag the flag of message queue
 *
 * @return the operation status, RT_EOK on successful
 */
rt_err_t rt_mq_init(rt_mq_t     mq,
                    const char *name,
                    void       *msgpool,
                    rt_size_t   msg_size,
                    rt_size_t   pool_size,
                    rt_uint8_t  flag)
{
    struct rt_mq_message *head;
    register rt_base_t temp;

    /* parameter check */
    RT_ASSERT(mq != RT_NULL);

    /* init object */
    rt_object_init(&(mq->parent.parent), RT_Object_Class_MessageQueue, name);

    /* set parent flag */
    mq->parent.parent.flag = flag;

    /* init ipc object */
    rt_ipc_object_init(&(mq->parent));
```

- 這裡就不需要去要一塊物件，直接拿來用即可

```c=1798
    /* set messasge pool */
    mq->msg_pool = msgpool;

    /* get correct message size */
    mq->msg_size = RT_ALIGN(msg_size, RT_ALIGN_SIZE);
    mq->max_msgs = pool_size / (mq->msg_size + sizeof(struct rt_mq_message));

    /* init message list */
    mq->msg_queue_head = RT_NULL;
    mq->msg_queue_tail = RT_NULL;

    /* init message empty list */
    mq->msg_queue_free = RT_NULL;
    for (temp = 0; temp < mq->max_msgs; temp ++)
    {
        head = (struct rt_mq_message *)((rt_uint8_t *)mq->msg_pool +
                                        temp * (mq->msg_size + sizeof(struct rt_mq_message)));
        head->next = mq->msg_queue_free;
        mq->msg_queue_free = head;
    }

    /* the initial entry is zero */
    mq->entry = 0;

    return RT_EOK;
}
RTM_EXPORT(rt_mq_init);
```

- 其餘的動作皆與上面相同

---
### 刪除 message queue
#### 動態記憶體管理

| 功能 | 回傳值 | `mq` |
| --- | ------ | ---- |
| 刪除 message queue | `RT_EOK` | 欲刪除的 message queue |

```c=1920
/**
 * This function will delete a message queue object and release the memory
 *
 * @param mq the message queue object
 *
 * @return the error code
 */
rt_err_t rt_mq_delete(rt_mq_t mq)
{
    RT_DEBUG_NOT_IN_INTERRUPT;

    /* parameter check */
    RT_ASSERT(mq != RT_NULL);
    RT_ASSERT(rt_object_get_type(&mq->parent.parent) == RT_Object_Class_MessageQueue);
    RT_ASSERT(rt_object_is_systemobject(&mq->parent.parent) == RT_FALSE);

    /* resume all suspended thread */
    rt_ipc_list_resume_all(&(mq->parent.suspend_thread));
```

- 先把正在等待收訊息的 thread 叫醒

```c=1938
    /* free message queue pool */
    RT_KERNEL_FREE(mq->msg_pool);

    /* delete message queue object */
    rt_object_delete(&(mq->parent.parent));

    return RT_EOK;
}
RTM_EXPORT(rt_mq_delete);
```

- 接著 free `msg_pool`，並刪除物件

---
#### 靜態記憶體管理

| 功能 | 回傳值 | `mq` |
| --- | ------ | ---- |
| 刪除 message queue | `RT_EOK` | 欲刪除的 message queue |

```c=1827
/**
 * This function will detach a message queue object from resource management
 *
 * @param mq the message queue object
 *
 * @return the operation status, RT_EOK on successful
 */
rt_err_t rt_mq_detach(rt_mq_t mq)
{
    /* parameter check */
    RT_ASSERT(mq != RT_NULL);
    RT_ASSERT(rt_object_get_type(&mq->parent.parent) == RT_Object_Class_MessageQueue);
    RT_ASSERT(rt_object_is_systemobject(&mq->parent.parent));

    /* resume all suspended thread */
    rt_ipc_list_resume_all(&mq->parent.suspend_thread);

    /* detach message queue object */
    rt_object_detach(&(mq->parent.parent));

    return RT_EOK;
}
RTM_EXPORT(rt_mq_detach);
```

- 這裡的 `msg_pool` 就不需要 free

---
### 傳送訊息

| 功能 | 回傳值 |
| --- | ------ |
| 傳送訊息 | `RT_EOK`|

| `mq` | `*buffer` | `size` |
| ---- | --------- | ------ |
| 欲傳送的 message queue | 訊息資料 | 訊息大小 |

```c=1950
/**
 * This function will send a message to message queue object, if there are
 * threads suspended on message queue object, it will be waked up.
 *
 * @param mq the message queue object
 * @param buffer the message
 * @param size the size of buffer
 *
 * @return the error code
 */
rt_err_t rt_mq_send(rt_mq_t mq, void *buffer, rt_size_t size)
{
    register rt_ubase_t temp;
    struct rt_mq_message *msg;

    /* parameter check */
    RT_ASSERT(mq != RT_NULL);
    RT_ASSERT(rt_object_get_type(&mq->parent.parent) == RT_Object_Class_MessageQueue);
    RT_ASSERT(buffer != RT_NULL);
    RT_ASSERT(size != 0);

    /* greater than one message size */
    if (size > mq->msg_size)
        return -RT_ERROR;

    RT_OBJECT_HOOK_CALL(rt_object_put_hook, (&(mq->parent.parent)));

    /* disable interrupt */
    temp = rt_hw_interrupt_disable();

    /* get a free list, there must be an empty item */
    msg = (struct rt_mq_message *)mq->msg_queue_free;
    /* message queue is full */
    if (msg == RT_NULL)
    {
        /* enable interrupt */
        rt_hw_interrupt_enable(temp);

        return -RT_EFULL;
    }
```

- 首先確定 message queue 沒滿（即 free list 不為空）
- 如果滿了，回傳 `FULL`

```c=1990
    /* move free list pointer */
    mq->msg_queue_free = msg->next;
```

- 接著 free list 往下一顆走

```c=1992
    /* enable interrupt */
    rt_hw_interrupt_enable(temp);

    /* the msg is the new tailer of list, the next shall be NULL */
    msg->next = RT_NULL;
    /* copy buffer */
    rt_memcpy(msg + 1, buffer, size);
```

- 將訊息填入從 free list 拿的一顆（`msg`），這顆待會是新的尾巴（設定 `next = NULL`）

```c=1999
    /* disable interrupt */
    temp = rt_hw_interrupt_disable();
    /* link msg to message queue */
    if (mq->msg_queue_tail != RT_NULL)
    {
        /* if the tail exists, */
        ((struct rt_mq_message *)mq->msg_queue_tail)->next = msg;
    }
```

- 如果尾巴不為空（也就是 message queue 有東西），將原本的尾巴指向 `msg`

```c=2007
    /* set new tail */
    mq->msg_queue_tail = msg;
    /* if the head is empty, set head */
    if (mq->msg_queue_head == RT_NULL)
        mq->msg_queue_head = msg;
```

- 設定新的尾巴
- 如果頭為空（也就是 message queue 為空），設定新的頭

```c=2012
    /* increase message entry */
    mq->entry ++;
```

- 最後更新 `entry` 

```c=2014
    /* resume suspended thread */
    if (!rt_list_isempty(&mq->parent.suspend_thread))
    {
        rt_ipc_list_resume(&(mq->parent.suspend_thread));

        /* enable interrupt */
        rt_hw_interrupt_enable(temp);

        rt_schedule();

        return RT_EOK;
    }

    /* enable interrupt */
    rt_hw_interrupt_enable(temp);

    return RT_EOK;
}
RTM_EXPORT(rt_mq_send);
```

- 如果有人在等待接收訊息，叫醒他

---
#### 傳送緊急訊息
- 與上面不同的是：這裡將新訊息插入**第一顆**

| 功能 | 回傳值 |
| --- | ------ |
| 傳送緊急訊息 | `RT_EOK`|

| `mq` | `*buffer` | `size` |
| ---- | --------- | ------ |
| 欲傳送的 message queue | 訊息資料 | 訊息大小 |

```c=2039
/**
 * This function will send an urgent message to message queue object, which
 * means the message will be inserted to the head of message queue. If there
 * are threads suspended on message queue object, it will be waked up.
 *
 * @param mq the message queue object
 * @param buffer the message
 * @param size the size of buffer
 *
 * @return the error code
 */
rt_err_t rt_mq_urgent(rt_mq_t mq, void *buffer, rt_size_t size)
{
    register rt_ubase_t temp;
    struct rt_mq_message *msg;

    /* parameter check */
    RT_ASSERT(mq != RT_NULL);
    RT_ASSERT(rt_object_get_type(&mq->parent.parent) == RT_Object_Class_MessageQueue);
    RT_ASSERT(buffer != RT_NULL);
    RT_ASSERT(size != 0);

    /* greater than one message size */
    if (size > mq->msg_size)
        return -RT_ERROR;

    RT_OBJECT_HOOK_CALL(rt_object_put_hook, (&(mq->parent.parent)));

    /* disable interrupt */
    temp = rt_hw_interrupt_disable();

    /* get a free list, there must be an empty item */
    msg = (struct rt_mq_message *)mq->msg_queue_free;
    /* message queue is full */
    if (msg == RT_NULL)
    {
        /* enable interrupt */
        rt_hw_interrupt_enable(temp);

        return -RT_EFULL;
    }
    /* move free list pointer */
    mq->msg_queue_free = msg->next;

    /* enable interrupt */
    rt_hw_interrupt_enable(temp);

    /* copy buffer */
    rt_memcpy(msg + 1, buffer, size);
```

- 因為要插在第一顆，`next` 就不用設定為空了

```c=2088
    /* disable interrupt */
    temp = rt_hw_interrupt_disable();

    /* link msg to the beginning of message queue */
    msg->next = mq->msg_queue_head;
    mq->msg_queue_head = msg;
```

- 這裡就將新訊息插在第一顆

```c=2097
    /* if there is no tail */
    if (mq->msg_queue_tail == RT_NULL)
        mq->msg_queue_tail = msg;
```

- 如果原本的 message queue 為空，設定新的尾巴

```c=2100
    /* increase message entry */
    mq->entry ++;

    /* resume suspended thread */
    if (!rt_list_isempty(&mq->parent.suspend_thread))
    {
        rt_ipc_list_resume(&(mq->parent.suspend_thread));

        /* enable interrupt */
        rt_hw_interrupt_enable(temp);

        rt_schedule();

        return RT_EOK;
    }

    /* enable interrupt */
    rt_hw_interrupt_enable(temp);

    return RT_EOK;
}
RTM_EXPORT(rt_mq_urgent);
```

- 其他的動作皆相同

---
### 接收訊息

| 功能 | 回傳值 |
| --- | ------ |
| 接收訊息| `RT_EOK` |

| `mq` | `*buffer` | `size` | `timeout` |
| ---- | --------- | ------ | --------- |
| 欲訊息 message queue | 訊息存放處 | 訊息存放處大小 | 等待時間（如果需要）|

```c=2123
/**
 * This function will receive a message from message queue object, if there is
 * no message in message queue object, the thread shall wait for a specified
 * time.
 *
 * @param mq the message queue object
 * @param buffer the received message will be saved in
 * @param size the size of buffer
 * @param timeout the waiting time
 *
 * @return the error code
 */
rt_err_t rt_mq_recv(rt_mq_t    mq,
                    void      *buffer,
                    rt_size_t  size,
                    rt_int32_t timeout)
{
    struct rt_thread *thread;
    register rt_ubase_t temp;
    struct rt_mq_message *msg;
    rt_uint32_t tick_delta;

    /* parameter check */
    RT_ASSERT(mq != RT_NULL);
    RT_ASSERT(rt_object_get_type(&mq->parent.parent) == RT_Object_Class_MessageQueue);
    RT_ASSERT(buffer != RT_NULL);
    RT_ASSERT(size != 0);

    /* initialize delta tick */
    tick_delta = 0;
    /* get current thread */
    thread = rt_thread_self();
    RT_OBJECT_HOOK_CALL(rt_object_trytake_hook, (&(mq->parent.parent)));

    /* disable interrupt */
    temp = rt_hw_interrupt_disable();

    /* for non-blocking call */
    if (mq->entry == 0 && timeout == 0)
    {
        rt_hw_interrupt_enable(temp);

        return -RT_ETIMEOUT;
    }

    /* message queue is empty */
    while (mq->entry == 0)
    {
        RT_DEBUG_IN_THREAD_CONTEXT;

        /* reset error number in thread */
        thread->error = RT_EOK;

        /* no waiting, return timeout */
        if (timeout == 0)
        {
            /* enable interrupt */
            rt_hw_interrupt_enable(temp);

            thread->error = -RT_ETIMEOUT;

            return -RT_ETIMEOUT;
        }
```

- 如果 message queue 為空，且不等待，回傳 `TIMEOUT`

```c=2186
        /* suspend current thread */
        rt_ipc_list_suspend(&(mq->parent.suspend_thread),
                            thread,
                            mq->parent.parent.flag);
```

- 如要等待，將 thread 掛在等待鏈上

```c=2190
        /* has waiting time, start thread timer */
        if (timeout > 0)
        {
            /* get the start tick of timer */
            tick_delta = rt_tick_get();

            RT_DEBUG_LOG(RT_DEBUG_IPC, ("set thread:%s to timer list\n",
                                        thread->name));

            /* reset the timeout of thread timer and start it */
            rt_timer_control(&(thread->thread_timer),
                             RT_TIMER_CTRL_SET_TIME,
                             &timeout);
            rt_timer_start(&(thread->thread_timer));
        }
```

- 並啟動一個 timer

```c=2205
        /* enable interrupt */
        rt_hw_interrupt_enable(temp);

        /* re-schedule */
        rt_schedule();
```

- 開始等待，做一次調度

```c=2210
        /* recv message */
        if (thread->error != RT_EOK)
        {
            /* return error */
            return thread->error;
        }

        /* disable interrupt */
        temp = rt_hw_interrupt_disable();

        /* if it's not waiting forever and then re-calculate timeout tick */
        if (timeout > 0)
        {
            tick_delta = rt_tick_get() - tick_delta;
            timeout -= tick_delta;
            if (timeout < 0)
                timeout = 0;
        }
    }
```

- 如跳回來，重新計算 `timeout`

```c=2229
    /* get message from queue */
    msg = (struct rt_mq_message *)mq->msg_queue_head;

    /* move message queue head */
    mq->msg_queue_head = msg->next;
    /* reach queue tail, set to NULL */
    if (mq->msg_queue_tail == msg)
        mq->msg_queue_tail = RT_NULL;
```

- 如果 message queue 有資料，拿第一顆，同時更新 head（tail，如果需要）

```c=2237
    /* decrease message entry */
    mq->entry --;
```

- 更新 entry

```c=2239
    /* enable interrupt */
    rt_hw_interrupt_enable(temp);

    /* copy message */
    rt_memcpy(buffer, msg + 1, size > mq->msg_size ? mq->msg_size : size);
```

- 接著複製找到的訊息

```c=2244
    /* disable interrupt */
    temp = rt_hw_interrupt_disable();
    /* put message to free list */
    msg->next = (struct rt_mq_message *)mq->msg_queue_free;
    mq->msg_queue_free = msg;
    /* enable interrupt */
    rt_hw_interrupt_enable(temp);

    RT_OBJECT_HOOK_CALL(rt_object_take_hook, (&(mq->parent.parent)));

    return RT_EOK;
}
RTM_EXPORT(rt_mq_recv);
```

- 最後將 `msg` 插入 free list 的頭