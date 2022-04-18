---
title: RT-Thread - Clock
date: 2018-11-25 20:32:18
tag: [RT-Thread, clock, kernel]
category: RT-Thread
summary: RTT 中負責 system tick 的程式，可以是取得現在的 tick，也可以處理時間中斷
---
{% alert success %}
**File:** clock.c
{% endalert %}

## 取得當前 tick

{% alert %}
即回傳全域變數 `rt_tick` 值
{% endalert %}

| 功能 | 回傳值 |
| --- | ------ |
| 取得當前的 system tick | tick 值 |

```c =41
/**
 * This function will return current tick from operating system startup
 *
 * @return current tick
 */
rt_tick_t rt_tick_get(void)
{
    /* return the global tick */
    return rt_tick;
}
RTM_EXPORT(rt_tick_get);
```

## 設定當前 tick

| 功能 | 回傳值 | `tick` |
| --- | ------ | ------ |
| 修改 system tick | void | 欲修改的結果 |

- 由於需要修改全域變數，因此這裡需要將中斷關閉進入 critical region

```c =53
/**
 * This function will set current tick
 */
void rt_tick_set(rt_tick_t tick)
{
    rt_base_t level;

    level = rt_hw_interrupt_disable();
    rt_tick = tick;
    rt_hw_interrupt_enable(level);
}
```

---
## 增加 tick
- 增加 tick 是由 ISR 所執行的動作，因此修改 `rt_tick` 值不需進入 critical region
- 由於增加 tick 需要發出中斷，所以所有的 ISR 都不可以佔用太多時間（如果執行超過一個 tick 的時間，clock ISR 就無法在正確的時間發生中斷，時間就會不準）

| 功能 | 回傳值 |
| --- | ------ |
| 增加 tick（clock ISR） | void |

```c =65
/**
 * This function will notify kernel there is one tick passed. Normally,
 * this function is invoked by clock ISR.
 */
void rt_tick_increase(void)
{
    struct rt_thread *thread;

    /* increase the global tick */
    ++ rt_tick;

    /* check time slice */
    thread = rt_thread_self();

    -- thread->remaining_tick;
    if (thread->remaining_tick == 0)
    {
        /* change to initialized tick */
        thread->remaining_tick = thread->init_tick;

        /* yield */
        rt_thread_yield();
    }

    /* check timer */
    rt_timer_check();
}
```

- 在增加 `rt_tick` 值的同時，也減少當前 thread 的剩餘 tick 值；當減至 0 時，重設剩餘 tick 並讓出處理器。
- （rt_timer_check()）