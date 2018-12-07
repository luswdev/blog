---
title: RT-Thread Scheduler
date: 2018-11-15 00:22:23
tag: [RT-Thread, scheduler, kernel]
category: RT-Thread
---
- <i class="fa fa-file-text-o" aria-hidden="true"></i> File: scheduler.c
- 於 *components.c* 中 的 `rtthread_startup()` 首先呼叫 `rt_system_scheduler_init()` 初始化 scheduler
- 於 `rtthread_startup()` 的最後呼叫 `rt_system_scheduler_start()` 開始 scheduler

---
## 初始化 scheduler
- <i class="fa fa-code" aria-hidden="true"></i> Code: `rt_system_scheduler_init`

| 功能 | 回傳值 |
| --- | ------ |
| 初始化 scheduler | void |

```c =102
/**
 * @ingroup SystemInit
 * This function will initialize the system scheduler
 */
void rt_system_scheduler_init(void)
{
    register rt_base_t offset;

    rt_scheduler_lock_nest = 0;
```

<!-- more -->

- `rt_scheduler_lock_nest` 為 scheduler 的鎖，在進入 critical region 時會 `++`，離開時會 `--`

```c =111
    RT_DEBUG_LOG(RT_DEBUG_SCHEDULER, ("start scheduler: max priority 0x%02x\n",
                                      RT_THREAD_PRIORITY_MAX));

    for (offset = 0; offset < RT_THREAD_PRIORITY_MAX; offset ++)
    {
        rt_list_init(&rt_thread_priority_table[offset]);
    }
```

- `RT_THREAD_PRIORITY_MAX` 根據不同的 *BSP* 可設定為不同的值，如 256；即優先級為 0~255，數字越小越等級越高
- 初始化 `rt_thread_priority_table`

```c =118
    rt_current_priority = RT_THREAD_PRIORITY_MAX - 1;
    rt_current_thread = RT_NULL;
```

- 設定當前的優先級為最低，及空。

```c =120
    /* initialize ready priority group */
    rt_thread_ready_priority_group = 0;

#if RT_THREAD_PRIORITY_MAX > 32
    /* initialize ready table */
    rt_memset(rt_thread_ready_table, 0, sizeof(rt_thread_ready_table));
#endif

    /* initialize thread defunct */
    rt_list_init(&rt_thread_defunct);
}
```

- 初始化 `rt_thread_ready_priority_group` 及 `rt_thread_defunct`

---
## 啟動 scheduler
- 此函數會找到一個 priorty 最高的 thread 並執行
- <i class="fa fa-code" aria-hidden="true"></i> Code: `rt_system_scheduler_start`

| 功能 | 回傳值 |
| --- | ------ |
| 啟動 scheduler | void |


```c :rt_system_scheduler_start =135
/**
 * @ingroup SystemInit
 * This function will startup scheduler. It will select one thread
 * with the highest priority level, then switch to it.
 */
void rt_system_scheduler_start(void)
{
    register struct rt_thread *to_thread;
    register rt_ubase_t highest_ready_priority;

#if RT_THREAD_PRIORITY_MAX > 32
    register rt_ubase_t number;

    number = __rt_ffs(rt_thread_ready_priority_group) - 1;
    highest_ready_priority = (number << 3) + __rt_ffs(rt_thread_ready_table[number]) - 1;
#else
    highest_ready_priority = __rt_ffs(rt_thread_ready_priority_group) - 1;
#endif
```

- 使用 `rt_ffs` 來尋找 priority 最高的鏈結

```c=153
    /* get switch to thread */
    to_thread = rt_list_entry(rt_thread_priority_table[highest_ready_priority].next,
                              struct rt_thread,
                              tlist);

    rt_current_thread = to_thread;

    /* switch to new thread */
    rt_hw_context_switch_to((rt_uint32_t)&to_thread->sp);

    /* never come back */
}
```

- 找到該鏈的第一顆，context switch 至該 thread
 
---
## Scheduler
- 呼叫此函式，系統會重新計算所有 thread 的 priority，如果存在更高的（與呼叫此函式的 thread 比較），系統將會 switch 至該 thread。
- <i class="fa fa-code" aria-hidden="true"></i> Code: `rt_schedule`

| 功能 | 回傳值 |
| --- | ------ |
| 執行一次調度 | void |

```c =173
/**
 * This function will perform one schedule. It will select one thread
 * with the highest priority level, then switch to it.
 */
void rt_schedule(void)
{
    rt_base_t level;
    struct rt_thread *to_thread;
    struct rt_thread *from_thread;

    /* disable interrupt */
    level = rt_hw_interrupt_disable();
```

- 首先將中斷關閉

```c=185
    /* check the scheduler is enabled or not */
    if (rt_scheduler_lock_nest == 0)
    {
        register rt_ubase_t highest_ready_priority;

#if RT_THREAD_PRIORITY_MAX <= 32
        highest_ready_priority = __rt_ffs(rt_thread_ready_priority_group) - 1;
#else
        register rt_ubase_t number;

        number = __rt_ffs(rt_thread_ready_priority_group) - 1;
        highest_ready_priority = (number << 3) + __rt_ffs(rt_thread_ready_table[number]) - 1;
#endif
```

- 檢查鎖的狀態，並找到 priority 最高的鍊

```c=198
        /* get switch to thread */
        to_thread = rt_list_entry(rt_thread_priority_table[highest_ready_priority].next,
                                  struct rt_thread,
                                  tlist);
```

- 找到該鏈的第一顆

```c=202
        /* if the destination thread is not the same as current thread */
        if (to_thread != rt_current_thread)
        {
            rt_current_priority = (rt_uint8_t)highest_ready_priority;
            from_thread         = rt_current_thread;
            rt_current_thread   = to_thread;

            RT_OBJECT_HOOK_CALL(rt_scheduler_hook, (from_thread, to_thread));

            /* switch to new thread */
            RT_DEBUG_LOG(RT_DEBUG_SCHEDULER,
                         ("[%d]switch to priority#%d "
                          "thread:%.*s(sp:0x%p), "
                          "from thread:%.*s(sp: 0x%p)\n",
                          rt_interrupt_nest, highest_ready_priority,
                          RT_NAME_MAX, to_thread->name, to_thread->sp,
                          RT_NAME_MAX, from_thread->name, from_thread->sp));

#ifdef RT_USING_OVERFLOW_CHECK
            _rt_scheduler_stack_check(to_thread);
#endif

            if (rt_interrupt_nest == 0)
            {
                extern void rt_thread_handle_sig(rt_bool_t clean_state);

                rt_hw_context_switch((rt_uint32_t)&from_thread->sp,
                                     (rt_uint32_t)&to_thread->sp);

                /* enable interrupt */
                rt_hw_interrupt_enable(level);

#ifdef RT_USING_SIGNALS
                /* check signal status */
                rt_thread_handle_sig(RT_TRUE);
#endif
            }
```

- 如果找到的 thread 與當前的 thread 不相符，且 `rt_interrupt_nest == 0`，即這次調度不是在中斷下運作的，直接 switch 至該 thread 
- 最後恢復中斷

```c=239
            else
            {
                RT_DEBUG_LOG(RT_DEBUG_SCHEDULER, ("switch in interrupt\n"));

                rt_hw_context_switch_interrupt((rt_uint32_t)&from_thread->sp,
                                               (rt_uint32_t)&to_thread->sp);
                /* enable interrupt */
                rt_hw_interrupt_enable(level);
            }
        }
```

- 如果 `rt_interrupt_nest != 0`，即這次調度是在中斷下運作的，則用中斷 switch 至該 thread
- 最後恢復中斷

```c=249
        else
        {
            /* enable interrupt */
            rt_hw_interrupt_enable(level);
        }
    }
    else
    {
        /* enable interrupt */
        rt_hw_interrupt_enable(level);
    }
}
```

- 如果找到的一樣，或是沒要到鎖，直接開啟中斷，結束調度

---
## 插入 thread
- <i class="fa fa-code" aria-hidden="true"></i> Code: `rt_schedule_insert_thread `

| 功能 | 回傳值 |
| --- | ------ |
| 將 thread 插入 list | void |

| `*thread` |
| ------- |
| 欲插入的 thread |

```c =265
/**
 * This function will insert a thread to system ready queue. The state of
 * thread will be set as READY and remove from suspend queue.
 *
 * @param thread the thread to be inserted
 * @note Please do not invoke this function in user application.
 */
void rt_schedule_insert_thread(struct rt_thread * thread)
{
    register rt_base_t temp;

    RT_ASSERT(thread != RT_NULL);

    /* disable interrupt */
    temp = rt_hw_interrupt_disable();

    /* change stat */
    thread->stat = RT_THREAD_READY | (thread->stat & ~RT_THREAD_STAT_MASK);
```

- 首先關閉中斷，及更改 thread 的狀態為 `RT_THREAD_READY`

```c=283
    /* insert thread to ready list */
    rt_list_insert_before(&(rt_thread_priority_table[thread->current_priority]),
                          &(thread->tlist));
```

- 接著呼叫 `rt_list_insert_before` 將 thread 插到第一顆

```c=286
    /* set priority mask */
#if RT_THREAD_PRIORITY_MAX <= 32
    RT_DEBUG_LOG(RT_DEBUG_SCHEDULER, ("insert thread[%.*s], the priority: %d\n",
                                      RT_NAME_MAX, thread->name, thread->current_priority));
#else
    RT_DEBUG_LOG(RT_DEBUG_SCHEDULER,
                 ("insert thread[%.*s], the priority: %d 0x%x %d\n",
                  RT_NAME_MAX,
                  thread->name,
                  thread->number,
                  thread->number_mask,
                  thread->high_mask));
#endif

#if RT_THREAD_PRIORITY_MAX > 32
    rt_thread_ready_table[thread->number] |= thread->high_mask;
#endif
    rt_thread_ready_priority_group |= thread->number_mask;

    /* enable interrupt */
    rt_hw_interrupt_enable(temp);
}
```

- 最後恢復中斷

---
## 移除 thread
- <i class="fa fa-code" aria-hidden="true"></i> Code: `rt_schedule_remove_thread `

| 功能 | 回傳值 |
| --- | ------ |
| 從 list 中移除 thread | void |

| `*thread` |
| ------- |
| 欲移除的 thread |

```c =311
/**
 * This function will remove a thread from system ready queue.
 *
 * @param thread the thread to be removed
 *
 * @note Please do not invoke this function in user application.
 */
void rt_schedule_remove_thread(struct rt_thread *thread)
{
    register rt_base_t temp;

    RT_ASSERT(thread != RT_NULL);

    /* disable interrupt */
    temp = rt_hw_interrupt_disable();

#if RT_THREAD_PRIORITY_MAX <= 32
    RT_DEBUG_LOG(RT_DEBUG_SCHEDULER, ("remove thread[%.*s], the priority: %d\n",
                                      RT_NAME_MAX, thread->name,
                                      thread->current_priority));
#else
    RT_DEBUG_LOG(RT_DEBUG_SCHEDULER,
                 ("remove thread[%.*s], the priority: %d 0x%x %d\n",
                  RT_NAME_MAX,
                  thread->name,
                  thread->number,
                  thread->number_mask,
                  thread->high_mask));
#endif

    /* remove thread from ready list */
    rt_list_remove(&(thread->tlist));
```

- 先關閉中斷，再呼叫 `rt_list_remove` 來刪除第一顆

```c=343
    if (rt_list_isempty(&(rt_thread_priority_table[thread->current_priority])))
    {
#if RT_THREAD_PRIORITY_MAX > 32
        rt_thread_ready_table[thread->number] &= ~thread->high_mask;
        if (rt_thread_ready_table[thread->number] == 0)
        {
            rt_thread_ready_priority_group &= ~thread->number_mask;
        }
#else
        rt_thread_ready_priority_group &= ~thread->number_mask;
#endif
    }

    /* enable interrupt */
    rt_hw_interrupt_enable(temp);
}
```

- 如果刪除後，原本的鏈為空，就修改一些參數（在 thread 會討論）
- 最後開啟中斷
