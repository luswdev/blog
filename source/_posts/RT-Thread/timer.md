---
title: RT-Thread - Timer
date: 2018-11-20 15:47:05
tag: [RT-Thread, kernel, timer]
category: RT-Thread
---
- timer 的作用：當時間到時，觸發一個事件；如文本的圖：
![](https://i.imgur.com/6ois2av.png)
- timer 的實作是一條鏈，即當前 tick 到達指定的 timer 時，會觸發該 timer 的 `timeout_func`，同時將該 timer 從鏈結移除
- 以上圖為例，當 `rt_tick` = 70 時，將會觸發 timer #1 的 `timeout_func`，並將 timer #1 移除

---
- 而在新增一個 timer 時，會按照 timeout 的大小排列插入，如圖：
![](https://i.imgur.com/yCkzoq0.png)
- 我們想要新增一個 timer #4，並希望在 300 個 tick 過後觸發事件，所以 timeout 設為 330（rt_tick + 300）
- 由於 timer 鏈需要由小到大排，所以將 timer #4 插在 #2 與 #3 之間

---
## 結構

{% alert success %}
**File:** rtdef.h
{% endalert %}

```c =426
/**
 * timer structure
 */
struct rt_timer
{
    struct rt_object parent;                            /**< inherit from rt_object */

    rt_list_t        row[RT_TIMER_SKIP_LIST_LEVEL];

    void (*timeout_func)(void *parameter);              /**< timeout function */
    void            *parameter;                         /**< timeout function's parameter */

    rt_tick_t        init_tick;                         /**< timer timeout tick */
    rt_tick_t        timeout_tick;                      /**< timeout tick */
};
typedef struct rt_timer *rt_timer_t;
```

- `timeout_func` 即為 timeout 時會觸發的函式
- `timeout_tick` = `init_tick` + 當前的 system tick

---

{% alert success %}
**File:** timer.c
{% endalert %}

## 初始化、建立 timer
- 在建立一個 thread 時，`_rt_thread_init` 會呼叫 `rt_timer_init` 來初始化 timer

### 靜態記憶體管理

| 功能 | 回傳值 | 
| --- | ------ | 
| 初始化 timer | void |

|`timer` | `*name` | `*timeout` | `*parameter` | `time` | `flag` |
| ------ | ------- | ------------ | ------------ | ------ | ------ |
| timer 結構 | 名字 | timeout function | func 的參數 | timeout 初始 tick | 狀態 |

| `*parameter` | `time` | `flag` |
| ------------ | ------ | ------ |
| func 的參數 | timeout 初始 tick | 狀態 |

```c =155
/**
 * This function will initialize a timer, normally this function is used to
 * initialize a static timer object.
 *
 * @param timer the static timer object
 * @param name the name of timer
 * @param timeout the timeout function
 * @param parameter the parameter of timeout function
 * @param time the tick of timer
 * @param flag the flag of timer
 */
void rt_timer_init(rt_timer_t  timer,
                   const char *name,
                   void (*timeout)(void *parameter),
                   void       *parameter,
                   rt_tick_t   time,
                   rt_uint8_t  flag)
{
    /* timer check */
    RT_ASSERT(timer != RT_NULL);

    /* timer object initialization */
    rt_object_init((rt_object_t)timer, RT_Object_Class_Timer, name);

    _rt_timer_init(timer, timeout, parameter, time, flag);
}
RTM_EXPORT(rt_timer_init);
```

- 與 thread 類似，使用 `_rt_timer_init` 完成初始化

```c =68
static void _rt_timer_init(rt_timer_t timer,
                           void (*timeout)(void *parameter),
                           void      *parameter,
                           rt_tick_t  time,
                           rt_uint8_t flag)
{
    int i;

    /* set flag */
    timer->parent.flag  = flag;

    /* set deactivated */
    timer->parent.flag &= ~RT_TIMER_FLAG_ACTIVATED;

    timer->timeout_func = timeout;
    timer->parameter    = parameter;

    timer->timeout_tick = 0;
    timer->init_tick    = time;

    /* initialize timer list */
    for (i = 0; i < RT_TIMER_SKIP_LIST_LEVEL; i++)
    {
        rt_list_init(&(timer->row[i]));
    }
}
```

- 設定 flag 為 decativated，設定 timeout_func、tick、timerlist

---
### 動態記憶體管理

| 功能 | 回傳值 |
| --- | ------ |
| 建立 timer | timer |

| `*name` | `*timeout` | `*parameter` | `time` | `flag` |
| ------- | ---------- | ------------ | ------ | ------ |
| 名字 | timeout function | func 的參數 | timeout 初始 tick | 狀態 |

```c =214
/**
 * This function will create a timer
 *
 * @param name the name of timer
 * @param timeout the timeout function
 * @param parameter the parameter of timeout function
 * @param time the tick of timer
 * @param flag the flag of timer
 *
 * @return the created timer object
 */
rt_timer_t rt_timer_create(const char *name,
                           void (*timeout)(void *parameter),
                           void       *parameter,
                           rt_tick_t   time,
                           rt_uint8_t  flag)
{
    struct rt_timer *timer;

    /* allocate a object */
    timer = (struct rt_timer *)rt_object_allocate(RT_Object_Class_Timer, name);
    if (timer == RT_NULL)
    {
        return RT_NULL;
    }

    _rt_timer_init(timer, timeout, parameter, time, flag);

    return timer;
}
RTM_EXPORT(rt_timer_create);
```

- 同樣也是透過 `_rt_timer_init` 完成動作

---
## 刪除 timer
### 動態記憶體管理

| 功能 | 回傳值 | `timer` |
| --- | ------ | ------- |
| 刪除 timer | `RT_EOK` | 欲刪除的 timer |

```c =246
/**
 * This function will delete a timer and release timer memory
 *
 * @param timer the timer to be deleted
 *
 * @return the operation status, RT_EOK on OK; RT_ERROR on error
 */
rt_err_t rt_timer_delete(rt_timer_t timer)
{
    register rt_base_t level;

    /* timer check */
    RT_ASSERT(timer != RT_NULL);
    RT_ASSERT(rt_object_get_type(&timer->parent) == RT_Object_Class_Timer);
    RT_ASSERT(rt_object_is_systemobject(&timer->parent) == RT_FALSE);

    /* disable interrupt */
    level = rt_hw_interrupt_disable();

    _rt_timer_remove(timer);

    /* enable interrupt */
    rt_hw_interrupt_enable(level);

    rt_object_delete((rt_object_t)timer);

    return RT_EOK;
}
RTM_EXPORT(rt_timer_delete);
```

- 透過 `_rt_timer_remove` 移除鏈結
- 透過 `rt_object_delete` 移除 timer

---
### 靜態記憶體管理

| 功能 | 回傳值 | `timer` |
| --- | ------ | ------- |
| 刪除 timer | tick 值 | 欲刪除的 timer |

```c =183
/**
 * This function will detach a timer from timer management.
 *
 * @param timer the static timer object
 *
 * @return the operation status, RT_EOK on OK; RT_ERROR on error
 */
rt_err_t rt_timer_detach(rt_timer_t timer)
{
    register rt_base_t level;

    /* timer check */
    RT_ASSERT(timer != RT_NULL);
    RT_ASSERT(rt_object_get_type(&timer->parent) == RT_Object_Class_Timer);
    RT_ASSERT(rt_object_is_systemobject(&timer->parent));

    /* disable interrupt */
    level = rt_hw_interrupt_disable();

    _rt_timer_remove(timer);

    /* enable interrupt */  rt_hw_interrupt_enable(level);

    rt_object_detach((rt_object_t)timer);

    return RT_EOK;
}
RTM_EXPORT(rt_timer_detach);
```

- 透過 `_rt_timer_remove` 移除鏈結
- 透過 `rt_object_detach` 移除 timer

---
## 啟動、停止 timer
### Code: rt_timer_start

| 功能 | 回傳值 | `timer` |
| --- | ------ | ------- |
| 啟動 timer| `RT_EOK` | 欲啟動的 timer |

```c =277
/**
 * This function will start the timer
 *
 * @param timer the timer to be started
 *
 * @return the operation status, RT_EOK on OK, -RT_ERROR on error
 */
rt_err_t rt_timer_start(rt_timer_t timer)
{
    unsigned int row_lvl;
    rt_list_t *timer_list;
    register rt_base_t level;
    rt_list_t *row_head[RT_TIMER_SKIP_LIST_LEVEL];
    unsigned int tst_nr;
    static unsigned int random_nr;

    /* timer check */
    RT_ASSERT(timer != RT_NULL);
    RT_ASSERT(rt_object_get_type(&timer->parent) == RT_Object_Class_Timer);

    /* stop timer firstly */
    level = rt_hw_interrupt_disable();
    /* remove timer from list */
    _rt_timer_remove(timer);
    /* change status of timer */
    timer->parent.flag &= ~RT_TIMER_FLAG_ACTIVATED;
    rt_hw_interrupt_enable(level);
```

- 如果需要，先停止 timer 

```c =304
    RT_OBJECT_HOOK_CALL(rt_object_take_hook, (&(timer->parent)));

    /*
     * get timeout tick,
     * the max timeout tick shall not great than RT_TICK_MAX/2
     */
    RT_ASSERT(timer->init_tick < RT_TICK_MAX / 2);
    timer->timeout_tick = rt_tick_get() + timer->init_tick;

    /* disable interrupt */
    level = rt_hw_interrupt_disable();
```

- 設定 timer 的 `timeout_tick`

```c =315
#ifdef RT_USING_TIMER_SOFT
    if (timer->parent.flag & RT_TIMER_FLAG_SOFT_TIMER)
    {
        /* insert timer to soft timer list */
        timer_list = rt_soft_timer_list;
    }
    else
#endif
    {
        /* insert timer to system timer list */
        timer_list = rt_timer_list;
    }

    row_head[0]  = &timer_list[0];
    for (row_lvl = 0; row_lvl < RT_TIMER_SKIP_LIST_LEVEL; row_lvl++)
    {
        for (; row_head[row_lvl] != timer_list[row_lvl].prev;
             row_head[row_lvl]  = row_head[row_lvl]->next)
        {
            struct rt_timer *t;
            rt_list_t *p = row_head[row_lvl]->next;

            /* fix up the entry pointer */
            t = rt_list_entry(p, struct rt_timer, row[row_lvl]);

            /* If we have two timers that timeout at the same time, it's
             * preferred that the timer inserted early get called early.
             * So insert the new timer to the end the the some-timeout timer
             * list.
             */
            if ((t->timeout_tick - timer->timeout_tick) == 0)
            {
                continue;
            }
            else if ((t->timeout_tick - timer->timeout_tick) < RT_TICK_MAX / 2)
            {
                break;
            }
        }
        if (row_lvl != RT_TIMER_SKIP_LIST_LEVEL - 1)
            row_head[row_lvl + 1] = row_head[row_lvl] + 1;
    }
```

- 尋找 timer 正確的位置

{% alert %}
如果有一樣的 timeout，將此 timer 插到最後
{% endalert %}

```c =357
    /* Interestingly, this super simple timer insert counter works very very
     * well on distributing the list height uniformly. By means of "very very
     * well", I mean it beats the randomness of timer->timeout_tick very easily
     * (actually, the timeout_tick is not random and easy to be attacked). */
    random_nr++;
    tst_nr = random_nr;

    rt_list_insert_after(row_head[RT_TIMER_SKIP_LIST_LEVEL - 1],
                         &(timer->row[RT_TIMER_SKIP_LIST_LEVEL - 1]));
    for (row_lvl = 2; row_lvl <= RT_TIMER_SKIP_LIST_LEVEL; row_lvl++)
    {
        if (!(tst_nr & RT_TIMER_SKIP_LIST_MASK))
            rt_list_insert_after(row_head[RT_TIMER_SKIP_LIST_LEVEL - row_lvl],
                                 &(timer->row[RT_TIMER_SKIP_LIST_LEVEL - row_lvl]));
        else
            break;
        /* Shift over the bits we have tested. Works well with 1 bit and 2
         * bits. */
        tst_nr >>= (RT_TIMER_SKIP_LIST_MASK + 1) >> 1;
    }

    timer->parent.flag |= RT_TIMER_FLAG_ACTIVATED;

    /* enable interrupt */
    rt_hw_interrupt_enable(level);

#ifdef RT_USING_TIMER_SOFT
    if (timer->parent.flag & RT_TIMER_FLAG_SOFT_TIMER)
    {
        /* check whether timer thread is ready */
        if ((timer_thread.stat & RT_THREAD_STAT_MASK) != RT_THREAD_READY)
        {
            /* resume timer thread to check soft timer */
            rt_thread_resume(&timer_thread);
            rt_schedule();
        }
    }
#endif

    return RT_EOK;
}
RTM_EXPORT(rt_timer_start);
```

- 接著插入 timer 並啟動

---
### Code: rt_timer_stop

| 功能 | 回傳值 | `timer` |
| --- | ------ | ------- |
| 停止 timer | `RT_EOK` | 欲刪除的 timer |

```c =403
/**
 * This function will stop the timer
 *
 * @param timer the timer to be stopped
 *
 * @return the operation status, RT_EOK on OK, -RT_ERROR on error
 */
rt_err_t rt_timer_stop(rt_timer_t timer)
{
    register rt_base_t level;

    /* timer check */
    RT_ASSERT(timer != RT_NULL);
    RT_ASSERT(rt_object_get_type(&timer->parent) == RT_Object_Class_Timer);

    if (!(timer->parent.flag & RT_TIMER_FLAG_ACTIVATED))
        return -RT_ERROR;

    RT_OBJECT_HOOK_CALL(rt_object_put_hook, (&(timer->parent)));

    /* disable interrupt */
    level = rt_hw_interrupt_disable();

    _rt_timer_remove(timer);

    /* enable interrupt */
    rt_hw_interrupt_enable(level);

    /* change stat */
    timer->parent.flag &= ~RT_TIMER_FLAG_ACTIVATED;

    return RT_EOK;
}
RTM_EXPORT(rt_timer_stop);
```

- 首先將 timer 從鏈結移出，再將 flag 設為 `RT_TIMER_FLAG_DEACTIVATED `

---
## 控制 timer

| 功能 | 回傳值 |
| --- | ------ |
| 控制 timer | tick 值 |

| `timer` | `cmd` | `*arg` |
| ------- | ----- | ----- |
| 欲控制的 timer | 動作 | 根據前面動作的參數 |

```c =438
/**
 * This function will get or set some options of the timer
 *
 * @param timer the timer to be get or set
 * @param cmd the control command
 * @param arg the argument
 *
 * @return RT_EOK
 */
rt_err_t rt_timer_control(rt_timer_t timer, int cmd, void *arg)
{
    /* timer check */
    RT_ASSERT(timer != RT_NULL);
    RT_ASSERT(rt_object_get_type(&timer->parent) == RT_Object_Class_Timer);

    switch (cmd)
    {
    case RT_TIMER_CTRL_GET_TIME:
        *(rt_tick_t *)arg = timer->init_tick;
        break;
```

- 如果需要尋找 timer 的值，將 `arg` 設為 `init_tick`

```c =458
    case RT_TIMER_CTRL_SET_TIME:
        timer->init_tick = *(rt_tick_t *)arg;
        break;
```

- 如果需要設定 tick，將 `init_tick` 設為 `arg`

```c =461
    case RT_TIMER_CTRL_SET_ONESHOT:
        timer->parent.flag &= ~RT_TIMER_FLAG_PERIODIC;
        break;
```

- 如果要設定 timer 為一次性的，添加 `RT_TIMER_FLAG_ONE_SHOT` 的 flag（即為 `~RT_TIMER_FLAG_PERIODIC`）

```c =464
    case RT_TIMER_CTRL_SET_PERIODIC:
        timer->parent.flag |= RT_TIMER_FLAG_PERIODIC;
        break;
    }

    return RT_EOK;
}
RTM_EXPORT(rt_timer_control);
```

- 如果要設定 timer 為週期性的，添加 `RT_TIMER_FLAG_PERIODIC`

---
## 檢查 timer

| 功能 | 回傳值 |
| --- | ------ |
| 檢查 timer list | void |

```c =476
/**
 * This function will check timer list, if a timeout event happens, the
 * corresponding timeout function will be invoked.
 *
 * @note this function shall be invoked in operating system timer interrupt.
 */
void rt_timer_check(void)
{
    struct rt_timer *t;
    rt_tick_t current_tick;
    register rt_base_t level;

    RT_DEBUG_LOG(RT_DEBUG_TIMER, ("timer check enter\n"));

    current_tick = rt_tick_get();

    /* disable interrupt */
    level = rt_hw_interrupt_disable();

    while (!rt_list_isempty(&rt_timer_list[RT_TIMER_SKIP_LIST_LEVEL - 1]))
    {
        t = rt_list_entry(rt_timer_list[RT_TIMER_SKIP_LIST_LEVEL - 1].next,
                          struct rt_timer, row[RT_TIMER_SKIP_LIST_LEVEL - 1]);

        /*
         * It supposes that the new tick shall less than the half duration of
         * tick max.
         */
        if ((current_tick - t->timeout_tick) < RT_TICK_MAX / 2)
        {
            RT_OBJECT_HOOK_CALL(rt_timer_timeout_hook, (t));

            /* remove timer from timer list firstly */
            _rt_timer_remove(t);

            /* call timeout function */
            t->timeout_func(t->parameter);

            /* re-get tick */
            current_tick = rt_tick_get();

            RT_DEBUG_LOG(RT_DEBUG_TIMER, ("current tick: %d\n", current_tick));

            if ((t->parent.flag & RT_TIMER_FLAG_PERIODIC) &&
                (t->parent.flag & RT_TIMER_FLAG_ACTIVATED))
            {
                /* start it */
                t->parent.flag &= ~RT_TIMER_FLAG_ACTIVATED;
                rt_timer_start(t);
            }
            else
            {
                /* stop timer */
                t->parent.flag &= ~RT_TIMER_FLAG_ACTIVATED;
            }
        }
        else
            break;
    }

    /* enable interrupt */
    rt_hw_interrupt_enable(level);

    RT_DEBUG_LOG(RT_DEBUG_TIMER, ("timer check leave\n"));
}
```