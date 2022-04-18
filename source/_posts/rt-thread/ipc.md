---
title: RT-Thread - IPC Sync
tag: [RT-Thread, kernel, IPC]
date: 2018-11-26 00:48:26
category: RT-Thread
summary: RTT IPC 1，一些同步的方式，包括 semaphore、mutex 及 event
---
- 兩個 thread 要溝通的方式，是透過共享的記憶體來完成；而如果此記憶體沒有排他性，這個記憶體有可能會不同步。
- 因此進入一塊共享的記憶體一次只允許一個 thread 來使用，這樣即可保證其資料的一致性
- 進入此共享記憶體則叫做 **critical region**
- RT-Thread 利用 7 種方式來完成同步：關閉中斷、scheduler lock、semaphore、互斥鎖、事件、mail box 及 message

---
## 關閉中斷
```c
level = rt_hw_interrupt_disable();
    /**
     *  critical region 
     */
rt_hw_interrupt_enable(level);
```

- 此方式是最強大的一種，但此 critical region 不可以佔用太多時間

---
## Scheduler lock
```c
rt_enter_critical()
    /**
    *  critical region 
    */
rt_exit_critical()
```

- 使用此方式可確保當前 thread 不會被 scheduler 踢出，但還是有可能會被中斷影響。

---
### 進入 scheuler 鎖

{% alert success %}
**File:** scheduler.c
{% endalert %}

| 功能 | 回傳值 |
| --- | ------ |
| 進入 scheuler 鎖 | void |

```c=360
/**
 * This function will lock the thread scheduler.
 */
void rt_enter_critical(void)
{
    register rt_base_t level;

    /* disable interrupt */
    level = rt_hw_interrupt_disable();

    /*
     * the maximal number of nest is RT_UINT16_MAX, which is big
     * enough and does not check here
     */
    rt_scheduler_lock_nest ++;

    /* enable interrupt */
    rt_hw_interrupt_enable(level);
}
RTM_EXPORT(rt_enter_critical);
```

- 即，將 `rt_scheduler_lock_nest` 加一

---
### 離開 scheduler 鎖

| 功能 | 回傳值 |
| --- | ------ |
| 離開 scheuler 鎖 | void |

```c=381
/**
 * This function will unlock the thread scheduler.
 */
void rt_exit_critical(void)
{
    register rt_base_t level;

    /* disable interrupt */
    level = rt_hw_interrupt_disable();

    rt_scheduler_lock_nest --;
```

- 即，將 `rt_scheduler_lock_nest` 減一

```c=392
    if (rt_scheduler_lock_nest <= 0)
    {
        rt_scheduler_lock_nest = 0;
        /* enable interrupt */
        rt_hw_interrupt_enable(level);

        rt_schedule();
    }
    else
    {
        /* enable interrupt */
        rt_hw_interrupt_enable(level);
    }
}
```

- 如果 `rt_scheduler_lock_nest` 被減至 0 或以下，進行一次調度

---

| 功能 | 回傳值 |
| --- | ------ |
| 回傳 scheuler 鎖的值 | scheuler 鎖的值 |

```c=409
/**
 * Get the scheduler lock level
 *
 * @return the level of the scheduler lock. 0 means unlocked.
 */
rt_uint16_t rt_critical_level(void)
{
    return rt_scheduler_lock_nest;
}
RTM_EXPORT(rt_critical_level);
```

- 即，回傳 `rt_scheduler_lock_nest` 值

---
## Semaphore

- 為一個值，代表同時可用的個數
- 不等於 0 時可用，取用時將值減 1
- 當不可用時，將 thread 掛在等待的鏈上

{% alert success %}
**File:** rtdef.h
{% endalert %}

### 結構
```c=591
#ifdef RT_USING_SEMAPHORE
/**
 * Semaphore structure
 */
struct rt_semaphore
{
    struct rt_ipc_object parent;                        /**< inherit from ipc_object */

    rt_uint16_t          value;                         /**< value of semaphore. */
};
typedef struct rt_semaphore *rt_sem_t;
#endif
```

---

### flags
```c=569
/**
 * IPC flags and control command definitions
 */
#define RT_IPC_FLAG_FIFO                0x00            /**< FIFOed IPC. @ref IPC. */
#define RT_IPC_FLAG_PRIO                0x01            /**< PRIOed IPC. @ref IPC. */

#define RT_IPC_CMD_UNKNOWN              0x00            /**< unknown IPC command */
#define RT_IPC_CMD_RESET                0x01            /**< reset IPC object */

#define RT_WAITING_FOREVER              -1              /**< Block forever until get resource. */
#define RT_WAITING_NO                   0               /**< Non-block. */
```

---

{% alert success %}
**File:** ipc.c
{% endalert %}

### 建立 semaphore
#### 動態記憶體管理

| 功能 | 回傳值 |
| --- | ------ |
| 建立 semaphore | semaphore |

| `*name` | `value` | `flag` |
| ------- | ------- | ------ |
| 名字 | semaphore 值，即最大可同時使用人數 | FIFO / PRIO |

```c=247
/**
 * This function will create a semaphore from system resource
 *
 * @param name the name of semaphore
 * @param value the init value of semaphore
 * @param flag the flag of semaphore
 *
 * @return the created semaphore, RT_NULL on error happen
 *
 * @see rt_sem_init
 */
rt_sem_t rt_sem_create(const char *name, rt_uint32_t value, rt_uint8_t flag)
{
    rt_sem_t sem;

    RT_DEBUG_NOT_IN_INTERRUPT;

    /* allocate object */
    sem = (rt_sem_t)rt_object_allocate(RT_Object_Class_Semaphore, name);
    if (sem == RT_NULL)
        return sem;

    /* init ipc object */
    rt_ipc_object_init(&(sem->parent));

    /* set init value */
    sem->value = value;

    /* set parent */
    sem->parent.parent.flag = flag;

    return sem;
}
RTM_EXPORT(rt_sem_create);
```

- 首先需要一塊 semaphore 的大小，初始化 ipc 物件，再依序寫入初始值及 flag

---
#### 靜態記憶體管理

| 功能 | 回傳值 |
| --- | ------ |
| 初始化 semaphore | `RT_EOK` |

| `sem` | `*name` | `value` | `flag` |
| ----- | ------- | ------- | ------ |
| semaphore 本體 | 名字 | semaphore 值，即最大可同時使用人數 | FIFO / PRIO |

```c=186
/**
 * This function will initialize a semaphore and put it under control of
 * resource management.
 *
 * @param sem the semaphore object
 * @param name the name of semaphore
 * @param value the init value of semaphore
 * @param flag the flag of semaphore
 *
 * @return the operation status, RT_EOK on successful
 */
rt_err_t rt_sem_init(rt_sem_t    sem,
                     const char *name,
                     rt_uint32_t value,
                     rt_uint8_t  flag)
{
    RT_ASSERT(sem != RT_NULL);

    /* init object */
    rt_object_init(&(sem->parent.parent), RT_Object_Class_Semaphore, name);

    /* init ipc object */
    rt_ipc_object_init(&(sem->parent));

    /* set init value */
    sem->value = value;

    /* set parent */
    sem->parent.parent.flag = flag;

    return RT_EOK;
}
RTM_EXPORT(rt_sem_init);
```

- 由於使用靜態記憶體，這裡就不需要再 allocate。

---
### 刪除 semaphore
#### 動態記憶體管理

| 功能 | 回傳值 | `sem` |
| --- | ------ | ----- |
| 刪除 semaphore | `RT_EOK` | 欲刪除的 semaphore |

```c=282
/**
 * This function will delete a semaphore object and release the memory
 *
 * @param sem the semaphore object
 *
 * @return the error code
 *
 * @see rt_sem_detach
 */
rt_err_t rt_sem_delete(rt_sem_t sem)
{
    RT_DEBUG_NOT_IN_INTERRUPT;

    /* parameter check */
    RT_ASSERT(sem != RT_NULL);
    RT_ASSERT(rt_object_get_type(&sem->parent.parent) == RT_Object_Class_Semaphore);
    RT_ASSERT(rt_object_is_systemobject(&sem->parent.parent) == RT_FALSE);

    /* wakeup all suspend threads */
    rt_ipc_list_resume_all(&(sem->parent.suspend_thread));

    /* delete semaphore object */
    rt_object_delete(&(sem->parent.parent));

    return RT_EOK;
}
RTM_EXPORT(rt_sem_delete);
```

- 首先需把所有正在等待此 semaphore 的 thread 叫醒
- 接著呼叫 `rt_object_delete` 清除此物件（semaphore）

---
#### 靜態記憶體管理

| 功能 | 回傳值 | `sem` |
| --- | ------ | ----- |
| 刪除 semaphore | `RT_EOK` | 欲刪除的 semaphore |

```c=220
/**
 * This function will detach a semaphore from resource management
 *
 * @param sem the semaphore object
 *
 * @return the operation status, RT_EOK on successful
 *
 * @see rt_sem_delete
 */
rt_err_t rt_sem_detach(rt_sem_t sem)
{
    /* parameter check */
    RT_ASSERT(sem != RT_NULL);
    RT_ASSERT(rt_object_get_type(&sem->parent.parent) == RT_Object_Class_Semaphore);
    RT_ASSERT(rt_object_is_systemobject(&sem->parent.parent));

    /* wakeup all suspend threads */
    rt_ipc_list_resume_all(&(sem->parent.suspend_thread));

    /* detach semaphore object */
    rt_object_detach(&(sem->parent.parent));

    return RT_EOK;
}
RTM_EXPORT(rt_sem_detach);
```

- 這裡則透過 `rt_object_detach` 清除
---
### 使用 semaphore
- 呼叫 `rt_sem_take` 來取得 semaphore，傳入的 time 是等待時間

| 功能 | 回傳值 |
| --- | ------ |
| 要求 semaphore | `RT_EOK` |

| `sem` | `time` |
| ----- | ------ |
| 欲要求的 semaphore | 等待時間（如果需要）|

```c=311
/**
 * This function will take a semaphore, if the semaphore is unavailable, the
 * thread shall wait for a specified time.
 *
 * @param sem the semaphore object
 * @param time the waiting time
 *
 * @return the error code
 */
rt_err_t rt_sem_take(rt_sem_t sem, rt_int32_t time)
{
    register rt_base_t temp;
    struct rt_thread *thread;

    /* parameter check */
    RT_ASSERT(sem != RT_NULL);
    RT_ASSERT(rt_object_get_type(&sem->parent.parent) == RT_Object_Class_Semaphore);

    RT_OBJECT_HOOK_CALL(rt_object_trytake_hook, (&(sem->parent.parent)));

    /* disable interrupt */
    temp = rt_hw_interrupt_disable();
```

- 由於待會會修改 semaphore 的值，這裡先將中斷關閉

```c=333
    RT_DEBUG_LOG(RT_DEBUG_IPC, ("thread %s take sem:%s, which value is: %d\n",
                                rt_thread_self()->name,
                                ((struct rt_object *)sem)->name,
                                sem->value));

    if (sem->value > 0)
    {
        /* semaphore is available */
        sem->value --;

        /* enable interrupt */
        rt_hw_interrupt_enable(temp);
    }
```

- 如過 `sem->value` 值大於 0 代表可用，接著減一，並開啟中斷

```c=346
    else
    {
        /* no waiting, return with timeout */
        if (time == 0)
        {
            rt_hw_interrupt_enable(temp);

            return -RT_ETIMEOUT;
        }
```

- 如果 semaphore 不可用時：
- 且 time 為 0，表示不等待，直接開啟中斷並 return

```c=355
        else
        {
            /* current context checking */
            RT_DEBUG_IN_THREAD_CONTEXT;

            /* semaphore is unavailable, push to suspend list */
            /* get current thread */
            thread = rt_thread_self();

            /* reset thread error number */
            thread->error = RT_EOK;

            RT_DEBUG_LOG(RT_DEBUG_IPC, ("sem take: suspend thread - %s\n",
                                        thread->name));

            /* suspend thread */
            rt_ipc_list_suspend(&(sem->parent.suspend_thread),
                                thread,
                                sem->parent.parent.flag);
```

- 如果要等待，則將 thread 插入 suspend list

```c=374
            /* has waiting time, start thread timer */
            if (time > 0)
            {
                RT_DEBUG_LOG(RT_DEBUG_IPC, ("set thread:%s to timer list\n",
                                            thread->name));

                /* reset the timeout of thread timer and start it */
                rt_timer_control(&(thread->thread_timer),
                                 RT_TIMER_CTRL_SET_TIME,
                                 &time);
                rt_timer_start(&(thread->thread_timer));
            }
```

- 且如果等待時間大於 0，則啟動一個 timeout 為 time 的 timer

```c=386
            /* enable interrupt */
            rt_hw_interrupt_enable(temp);

            /* do schedule */
            rt_schedule();

            if (thread->error != RT_EOK)
            {
                return thread->error;
            }
        }
    }

    RT_OBJECT_HOOK_CALL(rt_object_take_hook, (&(sem->parent.parent)));

    return RT_EOK;
}
RTM_EXPORT(rt_sem_take);
```

- 最後開啟中斷，做一次調度

---
- 若是不想等待，可以呼叫 `rt_sem_trytake`
- 即呼叫 `rt_sem_take` 及傳入 `time` 為 0

| 功能 | 回傳值 | `sem` |
| --- | ------ | ----- |
| 要求 semaphore（不等待） | `RT_EOK` | 欲要求的 semaphore |

```c=408
/**
 * This function will try to take a semaphore and immediately return
 *
 * @param sem the semaphore object
 *
 * @return the error code
 */
rt_err_t rt_sem_trytake(rt_sem_t sem)
{
    return rt_sem_take(sem, 0);
}
RTM_EXPORT(rt_sem_trytake);
```

---
- 還 semaphore 則使用 `rt_sem_release`

| 功能 | 回傳值 | `sem` |
| --- | ------ | ----- |
| 釋放 semaphore | `RT_EOK` | 欲要求的 semaphore |

```c=421
/**
 * This function will release a semaphore, if there are threads suspended on
 * semaphore, it will be waked up.
 *
 * @param sem the semaphore object
 *
 * @return the error code
 */
rt_err_t rt_sem_release(rt_sem_t sem)
{
    register rt_base_t temp;
    register rt_bool_t need_schedule;

    /* parameter check */
    RT_ASSERT(sem != RT_NULL);
    RT_ASSERT(rt_object_get_type(&sem->parent.parent) == RT_Object_Class_Semaphore);

    RT_OBJECT_HOOK_CALL(rt_object_put_hook, (&(sem->parent.parent)));

    need_schedule = RT_FALSE;

    /* disable interrupt */
    temp = rt_hw_interrupt_disable();
```

- 首先將待會會遇到的 flag（`need_schedule`）設為 false
- 因為待會也會修改 semaphore 的值，這裡需要關閉中斷

```c=444
    RT_DEBUG_LOG(RT_DEBUG_IPC, ("thread %s releases sem:%s, which value is: %d\n",
                                rt_thread_self()->name,
                                ((struct rt_object *)sem)->name,
                                sem->value));

    if (!rt_list_isempty(&sem->parent.suspend_thread))
    {
        /* resume the suspended thread */
        rt_ipc_list_resume(&(sem->parent.suspend_thread));
        need_schedule = RT_TRUE;
    }
```

- 如果有人在等此 semaphore，先恢復他，並修改 `need_schedule` 為 true

```c=455
    else
        sem->value ++; /* increase value */
```

- 如果沒有人在等待，則加一

```c=457
    /* enable interrupt */
    rt_hw_interrupt_enable(temp);

    /* resume a thread, re-schedule */
    if (need_schedule == RT_TRUE)
        rt_schedule();

    return RT_EOK;
}
RTM_EXPORT(rt_sem_release);
```

- 最後開啟中斷，並根據 `need_schedule` 來決定需不需要執行一次調度

---
## 互斥鎖（mutex）
- 即一種值為 1 的特殊 semaphore，特別的是具有防止優先級翻轉的特性

{% alert success %}
**File:** rtdef.h
{% endalert %}

### 結構
```c=604
#ifdef RT_USING_MUTEX
/**
 * Mutual exclusion (mutex) structure
 */
struct rt_mutex
{
    struct rt_ipc_object parent;                        /**< inherit from ipc_object */

    rt_uint16_t          value;                         /**< value of mutex */

    rt_uint8_t           original_priority;             /**< priority of last thread hold the mutex */
    rt_uint8_t           hold;                          /**< numbers of thread hold the mutex */

    struct rt_thread    *owner;                         /**< current owner of mutex */
};
typedef struct rt_mutex *rt_mutex_t;
#endif
```
- 為了防止優先權翻轉，在持有鎖的過程中可能會被提升優先權，在結構中就需要紀錄原本的優先級。

---

{% alert success %}
**File:** ipc.c
{% endalert %}

### 建立 mutex
#### 動態記憶體管理

| 功能 | 回傳值 |
| --- | ------ |
| 建立 mutex | mutex |

| `*name` | `flag` |
| ------- | ------ |
| 名字 | FIFO / PRIO |

```c=576
/**
 * This function will create a mutex from system resource
 *
 * @param name the name of mutex
 * @param flag the flag of mutex
 *
 * @return the created mutex, RT_NULL on error happen
 *
 * @see rt_mutex_init
 */
rt_mutex_t rt_mutex_create(const char *name, rt_uint8_t flag)
{
    struct rt_mutex *mutex;

    RT_DEBUG_NOT_IN_INTERRUPT;

    /* allocate object */
    mutex = (rt_mutex_t)rt_object_allocate(RT_Object_Class_Mutex, name);
    if (mutex == RT_NULL)
        return mutex;
```

- 首先 allocate 一個物件，初始化

```c=596
    /* init ipc object */
    rt_ipc_object_init(&(mutex->parent));

    mutex->value              = 1;
    mutex->owner              = RT_NULL;
    mutex->original_priority  = 0xFF;
    mutex->hold               = 0;
```

- value 設為 1，擁有者為 NULL，原始權限最低（255），持有次數為 0

```c=603
    /* set flag */
    mutex->parent.parent.flag = flag;

    return mutex;
}
RTM_EXPORT(rt_mutex_create);
```

- 同時填入 flag

---
#### 靜態記憶體管理

| 功能 | 回傳值 |
| --- | ------ |
| 初始化 mutex | `RT_EOK` |

| `mutex` | `*name` | `flag` |
| ------- | ------- | ------ |
| mutex 本體 | 名字 | FIFO / PRIO |

```c=516
/**
 * This function will initialize a mutex and put it under control of resource
 * management.
 *
 * @param mutex the mutex object
 * @param name the name of mutex
 * @param flag the flag of mutex
 *
 * @return the operation status, RT_EOK on successful
 */
rt_err_t rt_mutex_init(rt_mutex_t mutex, const char *name, rt_uint8_t flag)
{
    /* parameter check */
    RT_ASSERT(mutex != RT_NULL);

    /* init object */
    rt_object_init(&(mutex->parent.parent), RT_Object_Class_Mutex, name);

    /* init ipc object */
    rt_ipc_object_init(&(mutex->parent));

    mutex->value = 1;
    mutex->owner = RT_NULL;
    mutex->original_priority = 0xFF;
    mutex->hold  = 0;

    /* set flag */
    mutex->parent.parent.flag = flag;

    return RT_EOK;
}
RTM_EXPORT(rt_mutex_init);
```

- 這裡不需要 allocate，只需要初始化物件

---
### 刪除 mutex
#### 動態記憶體管理

| 功能 | 回傳值 |
| --- | ------ |
| 刪除 mutex | `RT_EOK` |

| `mutex` |
| ------- |
| 欲刪除的 mutex |

```c =612
/**
 * This function will delete a mutex object and release the memory
 *
 * @param mutex the mutex object
 *
 * @return the error code
 *
 * @see rt_mutex_detach
 */
rt_err_t rt_mutex_delete(rt_mutex_t mutex)
{
    RT_DEBUG_NOT_IN_INTERRUPT;

    /* parameter check */
    RT_ASSERT(mutex != RT_NULL);
    RT_ASSERT(rt_object_get_type(&mutex->parent.parent) == RT_Object_Class_Mutex);
    RT_ASSERT(rt_object_is_systemobject(&mutex->parent.parent) == RT_FALSE);

    /* wakeup all suspend threads */
    rt_ipc_list_resume_all(&(mutex->parent.suspend_thread));

    /* delete semaphore object */
    rt_object_delete(&(mutex->parent.parent));

    return RT_EOK;
}
RTM_EXPORT(rt_mutex_delete);
```

- 與 semaphore 類似，先將正在等待此鎖的所有 thread 叫醒，接著透過 `rt_object_delete` 刪除 mutex

---
#### 靜態記憶體管理

| 功能 | 回傳值 |
| --- | ------ |
| 刪除 mutex | `RT_EOK` |

| `mutex` |
| ------- |
| 欲刪除的 mutex |

```c =549
/**
 * This function will detach a mutex from resource management
 *
 * @param mutex the mutex object
 *
 * @return the operation status, RT_EOK on successful
 *
 * @see rt_mutex_delete
 */
rt_err_t rt_mutex_detach(rt_mutex_t mutex)
{
    /* parameter check */
    RT_ASSERT(mutex != RT_NULL);
    RT_ASSERT(rt_object_get_type(&mutex->parent.parent) == RT_Object_Class_Mutex);
    RT_ASSERT(rt_object_is_systemobject(&mutex->parent.parent));

    /* wakeup all suspend threads */
    rt_ipc_list_resume_all(&(mutex->parent.suspend_thread));

    /* detach semaphore object */
    rt_object_detach(&(mutex->parent.parent));

    return RT_EOK;
}
RTM_EXPORT(rt_mutex_detach);
```

- 這裡則運用 `rt_object_detach` 刪除

---
### 使用 mutex
- 呼叫 `rt_mutex_take` 來取得鎖

| 功能 | 回傳值 |
| --- | ------ |
| 要求 mutex | `RT_EOK` |

| `mutex` | `time` |
| ----- | ------ |
| 欲要求的 mutex | 等待時間（如果需要）|

```c =641
/**
 * This function will take a mutex, if the mutex is unavailable, the
 * thread shall wait for a specified time.
 *
 * @param mutex the mutex object
 * @param time the waiting time
 *
 * @return the error code
 */
rt_err_t rt_mutex_take(rt_mutex_t mutex, rt_int32_t time)
{
    register rt_base_t temp;
    struct rt_thread *thread;

    /* this function must not be used in interrupt even if time = 0 */
    RT_DEBUG_IN_THREAD_CONTEXT;

    /* parameter check */
    RT_ASSERT(mutex != RT_NULL);
    RT_ASSERT(rt_object_get_type(&mutex->parent.parent) == RT_Object_Class_Mutex);

    /* get current thread */
    thread = rt_thread_self();

    /* disable interrupt */
    temp = rt_hw_interrupt_disable();
```

- 下面將會修改 mutex 的一些資料，這裡先將中斷關閉

```c =667
    RT_OBJECT_HOOK_CALL(rt_object_trytake_hook, (&(mutex->parent.parent)));

    RT_DEBUG_LOG(RT_DEBUG_IPC,
                 ("mutex_take: current thread %s, mutex value: %d, hold: %d\n",
                  thread->name, mutex->value, mutex->hold));

    /* reset thread error */
    thread->error = RT_EOK;

    if (mutex->owner == thread)
    {
        /* it's the same thread */
        mutex->hold ++;
    }
```

- 若此 mutex 的擁有者與要求著相同，持有數加 1

```c =681
    else
    {
__again:
        /* The value of mutex is 1 in initial status. Therefore, if the
         * value is great than 0, it indicates the mutex is avaible.
         */
        if (mutex->value > 0)
        {
            /* mutex is available */
            mutex->value --;

            /* set mutex owner and original priority */
            mutex->owner             = thread;
            mutex->original_priority = thread->current_priority;
            mutex->hold ++;
        }
```

- 如果不同，且 mutex 可用，先將 value `--`
- 設定所有者，紀錄當前權限，持有數加 1

```c =697
        else
        {
            /* no waiting, return with timeout */
            if (time == 0)
            {
                /* set error as timeout */
                thread->error = -RT_ETIMEOUT;

                /* enable interrupt */
                rt_hw_interrupt_enable(temp);

                return -RT_ETIMEOUT;
            }
```

- 如果不可用，且不等待，則啟用中斷，`return -RT_ETIMEOUT`

```c =710
            else
            {
                /* mutex is unavailable, push to suspend list */
                RT_DEBUG_LOG(RT_DEBUG_IPC, ("mutex_take: suspend thread: %s\n",
                                            thread->name));

                /* change the owner thread priority of mutex */
                if (thread->current_priority < mutex->owner->current_priority)
                {
                    /* change the owner thread priority */
                    rt_thread_control(mutex->owner,
                                      RT_THREAD_CTRL_CHANGE_PRIORITY,
                                      &thread->current_priority);
                }
```

- 若需要等待：
- 為了避免優先權翻轉的情形發生，如需等待的 thread 的優先級大於持有 mutex 的優先級，提升持有者的

```c =724
                /* suspend current thread */
                rt_ipc_list_suspend(&(mutex->parent.suspend_thread),
                                    thread,
                                    mutex->parent.parent.flag);

                /* has waiting time, start thread timer */
                if (time > 0)
                {
                    RT_DEBUG_LOG(RT_DEBUG_IPC,
                                 ("mutex_take: start the timer of thread:%s\n",
                                  thread->name));

                    /* reset the timeout of thread timer and start it */
                    rt_timer_control(&(thread->thread_timer),
                                     RT_TIMER_CTRL_SET_TIME,
                                     &time);
                    rt_timer_start(&(thread->thread_timer));
                }
```

- 插入 suspend list，並啟動一個 timeout 為 time 的 timer

```c =742
                /* enable interrupt */
                rt_hw_interrupt_enable(temp);

                /* do schedule */
                rt_schedule();
```

- 開啟中斷，並做一次調度

```c =747
                if (thread->error != RT_EOK)
                {
                	/* interrupt by signal, try it again */
                	if (thread->error == -RT_EINTR) goto __again;

                    /* return error */
                    return thread->error;
                }
```

- 如果因為中斷再次回到此 thread，重新要一次 mutex

```c =755
                else
                {
                    /* the mutex is taken successfully. */
                    /* disable interrupt */
                    temp = rt_hw_interrupt_disable();
                }
            }
        }
    }
    /* enable interrupt */
    rt_hw_interrupt_enable(temp);

    RT_OBJECT_HOOK_CALL(rt_object_take_hook, (&(mutex->parent.parent)));

    return RT_EOK;
}
RTM_EXPORT(rt_mutex_take);
```

---
- 還鎖則使用 `rt_mutex_release`

| 功能 | 回傳值 | `mutex` |
| --- | ------ | ------- |
| 釋放 mutex | `RT_EOK` | 欲釋放的 mutex |

```c =778
/**
 * This function will release a mutex, if there are threads suspended on mutex,
 * it will be waked up.
 *
 * @param mutex the mutex object
 *
 * @return the error code
 */
rt_err_t rt_mutex_release(rt_mutex_t mutex)
{
    register rt_base_t temp;
    struct rt_thread *thread;
    rt_bool_t need_schedule;

    /* parameter check */
    RT_ASSERT(mutex != RT_NULL);
    RT_ASSERT(rt_object_get_type(&mutex->parent.parent) == RT_Object_Class_Mutex);

    need_schedule = RT_FALSE;

    /* only thread could release mutex because we need test the ownership */
    RT_DEBUG_IN_THREAD_CONTEXT;

    /* get current thread */
    thread = rt_thread_self();

    /* disable interrupt */
    temp = rt_hw_interrupt_disable();
```

- 下面將會修改 mutex 的一些資料，這裡先將中斷關閉

```c =806
    RT_DEBUG_LOG(RT_DEBUG_IPC,
                 ("mutex_release:current thread %s, mutex value: %d, hold: %d\n",
                  thread->name, mutex->value, mutex->hold));

    RT_OBJECT_HOOK_CALL(rt_object_put_hook, (&(mutex->parent.parent)));

    /* mutex only can be released by owner */
    if (thread != mutex->owner)
    {
        thread->error = -RT_ERROR;

        /* enable interrupt */
        rt_hw_interrupt_enable(temp);

        return -RT_ERROR;
    }
```

- 檢查歸還者是否為擁有者

```c =822
    /* decrease hold */
    mutex->hold --;
```

- 持有數減 1

```c =824
    /* if no hold */
    if (mutex->hold == 才會
    {
        /* change the owner thread to original priority */
        if (mutex->original_priority != mutex->owner->current_priority)
        {
            rt_thread_control(mutex->owner,
                              RT_THREAD_CTRL_CHANGE_PRIORITY,
                              &(mutex->original_priority));
        }
```

- 若已不再擁有此 mutex，且優先權有被更改過，調整回來

```c =834
        /* wakeup suspended thread */
        if (!rt_list_isempty(&mutex->parent.suspend_thread))
        {
            /* get suspended thread */
            thread = rt_list_entry(mutex->parent.suspend_thread.next,
                                   struct rt_thread,
                                   tlist);

            RT_DEBUG_LOG(RT_DEBUG_IPC, ("mutex_release: resume thread: %s\n",
                                        thread->name));

            /* set new owner and priority */
            mutex->owner             = thread;
            mutex->original_priority = thread->current_priority;
            mutex->hold ++;

            /* resume thread */
            rt_ipc_list_resume(&(mutex->parent.suspend_thread));

            need_schedule = RT_TRUE;
        }
```

- 若有人在等待此 mutex，將 mutex 傳遞給第一個正在等待的 thread

```c =855
        else
        {
            /* increase value */
            mutex->value ++;

            /* clear owner */
            mutex->owner             = RT_NULL;
            mutex->original_priority = 0xff;
        }
    }
```

- 如果沒有人在等，value 加 1，將資料初始化

```c =865
    /* enable interrupt */
    rt_hw_interrupt_enable(temp);

    /* perform a schedule */
    if (need_schedule == RT_TRUE)
        rt_schedule();

    return RT_EOK;
}
RTM_EXPORT(rt_mutex_release);
```

- 開啟中斷，並視情況做一次調度

---
## 事件
- 可實現一對多，多對多
- 僅用來同步，無傳輸的功能

### 實作
- thread 的結構中有一個 32 位的事件標記，一個事件的資訊

{% alert success %}
**File:** rtdef.h
{% endalert %}

```c=530
#if defined(RT_USING_EVENT)
    /* thread event */
    rt_uint32_t event_set;
    rt_uint8_t  event_info;
#endif
```

- 標記的每一位代表一個事件，資訊包含 AND、OR 及 CLEAR
- 當事件標記的第 2、4 位為 1，其餘為 0，代表此 thread 設置第 2、4 個事件
    - AND：即需同時接收到 2 號與 4 號事件才會被喚醒
    - OR：只需接收到一個
    - CLEAR：表示接收完事件喚醒後，是否須將標記清除

### 結構
```c=630
#ifdef RT_USING_EVENT
/**
 * flag defintions in event
 */
#define RT_EVENT_FLAG_AND               0x01            /**< logic and */
#define RT_EVENT_FLAG_OR                0x02            /**< logic or */
#define RT_EVENT_FLAG_CLEAR             0x04            /**< clear flag */

/*
 * event structure
 */
struct rt_event
{
    struct rt_ipc_object parent;                        /**< inherit from ipc_object */

    rt_uint32_t          set;                           /**< event set */
};
typedef struct rt_event *rt_event_t;
#endif
```

---

{% alert success %}
**File:** ipc.c
{% endalert %}

### 建立事件
#### 動態記憶體管理

| 功能 | 回傳值 |
| --- | ------ |
| 建立事件 | 事件 |

| `*name` | `flag` |
| ------- | ------ |
| 名字 | FIFO / PRIO |

```c =957
/**
 * This function will create an event object from system resource
 *
 * @param name the name of event
 * @param flag the flag of event
 *
 * @return the created event, RT_NULL on error happen
 */
rt_event_t rt_event_create(const char *name, rt_uint8_t flag)
{
    rt_event_t event;

    RT_DEBUG_NOT_IN_INTERRUPT;

    /* allocate object */
    event = (rt_event_t)rt_object_allocate(RT_Object_Class_Event, name);
    if (event == RT_NULL)
        return event;

    /* set parent */
    event->parent.parent.flag = flag;

    /* init ipc object */
    rt_ipc_object_init(&(event->parent));

    /* init event */
    event->set = 0;

    return event;
}
RTM_EXPORT(rt_event_create);
```

- 一樣 allocate 記憶體，填入 flag，初始化，最後設定值為 0

---
#### 靜態記憶體管理

| 功能 | 回傳值 |
| --- | ------ |
| 初始化事件 | `RT_EOK` |

| `event` | `*name` | `flag` |
| ------- | ------- | ------ |
| 事件本體 | 名字 | FIFO / PRIO |

```c =901
/**
 * This function will initialize an event and put it under control of resource
 * management.
 *
 * @param event the event object
 * @param name the name of event
 * @param flag the flag of event
 *
 * @return the operation status, RT_EOK on successful
 */
rt_err_t rt_event_init(rt_event_t event, const char *name, rt_uint8_t flag)
{
    /* parameter check */
    RT_ASSERT(event != RT_NULL);

    /* init object */
    rt_object_init(&(event->parent.parent), RT_Object_Class_Event, name);

    /* set parent flag */
    event->parent.parent.flag = flag;

    /* init ipc object */
    rt_ipc_object_init(&(event->parent));

    /* init event */
    event->set = 0;

    return RT_EOK;
}
RTM_EXPORT(rt_event_init);
```

- 這裡則不需要 allocate

---
### 刪除事件
#### 靜態記憶體管理

| 功能 | 回傳值 | `event` |
| --- | ------ | ------- |
| 刪除事件 | `RT_EOK` | 欲刪除的事件 |

```c =989
/**
 * This function will delete an event object and release the memory
 *
 * @param event the event object
 *
 * @return the error code
 */
rt_err_t rt_event_delete(rt_event_t event)
{
    /* parameter check */
    RT_ASSERT(event != RT_NULL);
    RT_ASSERT(rt_object_get_type(&event->parent.parent) == RT_Object_Class_Event);
    RT_ASSERT(rt_object_is_systemobject(&event->parent.parent) == RT_FALSE);

    RT_DEBUG_NOT_IN_INTERRUPT;

    /* resume all suspended thread */
    rt_ipc_list_resume_all(&(event->parent.suspend_thread));

    /* delete event object */
    rt_object_delete(&(event->parent.parent));

    return RT_EOK;
}
RTM_EXPORT(rt_event_delete);
```

- 相同的，需要先將正在等待此事件的 thread 叫醒，再刪除

---
#### 靜態記憶體管理

| 功能 | 回傳值 | `event` |
| --- | ------ | ------- |
| 刪除事件 | `RT_EOK` | 欲刪除的事件 |

```c =932
/**
 * This function will detach an event object from resource management
 *
 * @param event the event object
 *
 * @return the operation status, RT_EOK on successful
 */
rt_err_t rt_event_detach(rt_event_t event)
{
    /* parameter check */
    RT_ASSERT(event != RT_NULL);
    RT_ASSERT(rt_object_get_type(&event->parent.parent) == RT_Object_Class_Event);
    RT_ASSERT(rt_object_is_systemobject(&event->parent.parent));

    /* resume all suspended thread */
    rt_ipc_list_resume_all(&(event->parent.suspend_thread));

    /* detach event object */
    rt_object_detach(&(event->parent.parent));

    return RT_EOK;
}
RTM_EXPORT(rt_event_detach);
```

- 這裡則用 `rt_object_detach`

---
### 傳遞事件

| 功能 | 回傳值 |
| --- | ------ |
| 傳遞事件 | `RT_EOK` |

| `event` | `set` |
| ------- | ----- |
| 欲傳遞的事件 | 事件編號 |

```c =1016
/**
 * This function will send an event to the event object, if there are threads
 * suspended on event object, it will be waked up.
 *
 * @param event the event object
 * @param set the event set
 *
 * @return the error code
 */
rt_err_t rt_event_send(rt_event_t event, rt_uint32_t set)
{
    struct rt_list_node *n;
    struct rt_thread *thread;
    register rt_ubase_t level;
    register rt_base_t status;
    rt_bool_t need_schedule;

    /* parameter check */
    RT_ASSERT(event != RT_NULL);
    RT_ASSERT(rt_object_get_type(&event->parent.parent) == RT_Object_Class_Event);

    if (set == 0)
        return -RT_ERROR;

    need_schedule = RT_FALSE;

    /* disable interrupt */
    level = rt_hw_interrupt_disable();
```

- 下面會修改事件的資料，這裡先將中斷關閉

```c =1044
    /* set event */
    event->set |= set;
```

- 設定事件編號

```c =1046
    RT_OBJECT_HOOK_CALL(rt_object_put_hook, (&(event->parent.parent)));
    
    if (!rt_list_isempty(&event->parent.suspend_thread))
    {
        /* search thread list to resume thread */
        n = event->parent.suspend_thread.next;
        while (n != &(event->parent.suspend_thread))
        {
            /* get thread */
            thread = rt_list_entry(n, struct rt_thread, tlist);

            status = -RT_ERROR;
            if (thread->event_info & RT_EVENT_FLAG_AND)
            {
                if ((thread->event_set & event->set) == thread->event_set)
                {
                    /* received an AND event */
                    status = RT_EOK;
                }
            }
```

- 如果有人在等待此事件，且滿足條件時，設定為 OK
- 這裡為 AND，即事件編號應與 thread 所設定的一致

```c =1066
            else if (thread->event_info & RT_EVENT_FLAG_OR)
            {
                if (thread->event_set & event->set)
                {
                    /* save recieved event set */
                    thread->event_set = thread->event_set & event->set;

                    /* received an OR event */
                    status = RT_EOK;
                }
            }
```

- 若為 OR，則只需有一位相同即可

```c =1077
            /* move node to the next */
            n = n->next;
```

- 接著走向下一顆

```c =1079
            /* condition is satisfied, resume thread */
            if (status == RT_EOK)
            {
                /* clear event */
                if (thread->event_info & RT_EVENT_FLAG_CLEAR)
                    event->set &= ~thread->event_set;
```

- 如有人滿足條件，且被設定 CLEAR，清除其標記位

```c =1085
                /* resume thread, and thread list breaks out */
                rt_thread_resume(thread);

                /* need do a scheduling */
                need_schedule = RT_TRUE;
            }
        }
    }
```

- 並恢復此 thread，設定待會需要調度

```c =1093
    /* enable interrupt */
    rt_hw_interrupt_enable(level);

    /* do a schedule */
    if (need_schedule == RT_TRUE)
        rt_schedule();

    return RT_EOK;
}
RTM_EXPORT(rt_event_send);
```

- 最後開啟中斷，視情況做一次調度

---
### 接收事件

| 功能 | 回傳值 |
| --- | ------ |
| 接收事件 | `RT_EOK` |

| `event` | `set` | `option` | `timeout` | `*recved` |
| ------- | ----- | -------- | --------- | --------- |
| 欲接收的事件 | 事件編號 | AND /OR | 等待時間（如果需要）| 傳遞成功的事件號碼 |

```c =1110
/**
 * This function will receive an event from event object, if the event is
 * unavailable, the thread shall wait for a specified time.
 *
 * @param event the fast event object
 * @param set the interested event set
 * @param option the receive option, either RT_EVENT_FLAG_AND or
 *        RT_EVENT_FLAG_OR should be set.
 * @param timeout the waiting time
 * @param recved the received event, if you don't care, RT_NULL can be set.
 *
 * @return the error code
 */
rt_err_t rt_event_recv(rt_event_t   event,
                       rt_uint32_t  set,
                       rt_uint8_t   option,
                       rt_int32_t   timeout,
                       rt_uint32_t *recved)
{
    struct rt_thread *thread;
    register rt_ubase_t level;
    register rt_base_t status;

    RT_DEBUG_IN_THREAD_CONTEXT;

    /* parameter check */
    RT_ASSERT(event != RT_NULL);
    RT_ASSERT(rt_object_get_type(&event->parent.parent) == RT_Object_Class_Event);

    if (set == 0)
        return -RT_ERROR;

    /* init status */
    status = -RT_ERROR;
    /* get current thread */
    thread = rt_thread_self();
    /* reset thread error */
    thread->error = RT_EOK;

    RT_OBJECT_HOOK_CALL(rt_object_trytake_hook, (&(event->parent.parent)));

    /* disable interrupt */
    level = rt_hw_interrupt_disable();
```

- 下面會修改事件的資料，這裡先將中斷關閉

```c =1153
    /* check event set */
    if (option & RT_EVENT_FLAG_AND)
    {
        if ((event->set & set) == set)
            status = RT_EOK;
    }
    else if (option & RT_EVENT_FLAG_OR)
    {
        if (event->set & set)
            status = RT_EOK;
    }
    else
    {
        /* either RT_EVENT_FLAG_AND or RT_EVENT_FLAG_OR should be set */
        RT_ASSERT(0);
    }
```

- 如果滿足條件，表示已接收到事件，設定為 OK

```c =1169
    if (status == RT_EOK)
    {
        /* set received event */
        if (recved)
            *recved = (event->set & set);

        /* received event */
        if (option & RT_EVENT_FLAG_CLEAR)
            event->set &= ~set;
    }
```

- 如果已接收到事件，設定 recved 參數
- 視情況看需不需要清除標記

```c =1179
    else if (timeout == 0)
    {
        /* no waiting */
        thread->error = -RT_ETIMEOUT;
    }
```

- 若需等待事件，但 timeout 為 0
- 即不等待，將錯誤碼設為 TIMEOUT

```c =1184
    else
    {
        /* fill thread event info */
        thread->event_set  = set;
        thread->event_info = option;
```

- 如欲等待，將資訊掛在 thread 的結構上

```c =1189
        /* put thread to suspended thread list */
        rt_ipc_list_suspend(&(event->parent.suspend_thread),
                            thread,
                            event->parent.parent.flag);
```

- 並插入等待的鏈上
 
```c =1193
        /* if there is a waiting timeout, active thread timer */
        if (timeout > 0)
        {
            /* reset the timeout of thread timer and start it */
            rt_timer_control(&(thread->thread_timer),
                             RT_TIMER_CTRL_SET_TIME,
                             &timeout);
            rt_timer_start(&(thread->thread_timer));
        }
```

- 啟動一個 timer

```c =1202
        /* enable interrupt */
        rt_hw_interrupt_enable(level);

        /* do a schedule */
        rt_schedule();
```

- 最後開啟中斷，並做一次調度

```c =1207
        if (thread->error != RT_EOK)
        {
            /* return error */
            return thread->error;
        }

        /* received an event, disable interrupt to protect */
        level = rt_hw_interrupt_disable();

        /* set received event */
        if (recved)
            *recved = thread->event_set;
    }

    /* enable interrupt */
    rt_hw_interrupt_enable(level);

    RT_OBJECT_HOOK_CALL(rt_object_take_hook, (&(event->parent.parent)));

    return thread->error;
}
RTM_EXPORT(rt_event_recv);
```

- 最終接收到事件，一樣設定 recved 參數，回傳錯誤碼