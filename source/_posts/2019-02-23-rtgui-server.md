---
title: RT-Thread GUI Server
tag: [RT-Thread, GUI, server, kernel]
date: 2019-02-23 15:08:11
category: RT-Thread GUI
summary: server 掌管整個 GUI system 的所有事件 (event)，所有硬體的工作都需要他來完成溝通。
---
## 基本事件結構
server 掌管整個 GUI system 的所有事件 (event)，而根據不同的事件定義不同的結構；在每個不同的結構中都有基本的欄位 `_RTGUI_EVENT_WIN_ELEMENTS`：

>File: event.c

```c
/*
 * RTGUI Window Event
 */
#define _RTGUI_EVENT_WIN_ELEMENTS \
    struct rtgui_event parent; \
    struct rtgui_win *wid;

```
`rtgui_event` 即為事件的基本結構：
```c
struct rtgui_event
{
    /* the event type */
    enum _rtgui_event_type type;
    /* user field of event */
    rt_uint16_t user;

    /* the event sender */
    struct rtgui_app *sender;

    /* mailbox to acknowledge request */
    rt_mailbox_t ack;
};
typedef struct rtgui_event rtgui_event_t;
```

---
### 基本結構設定
```c
#define RTGUI_EVENT_INIT(e, t)  do      \
{                                       \
    (e)->type = (t);                    \
    (e)->user = 0;                      \
    (e)->sender = rtgui_app_self();     \
    (e)->ack = RT_NULL;                 \
} while (0)
```

---
## 啟動 server
>File: server.c

首先，定義一個 app 名叫 server :
```c
static struct rtgui_app *rtgui_server_app = RT_NULL;
```

接著透過 `rtgui_server_entry` 啟動 app，也就是 server:<br>
<i class="fa fa-code"></i> Code: `rtgui_server_entry`

| 功能 | 回傳值 |
| --- | ------ |
| 啟動 server | void |


| `*parameter` |
| :----------: |
| 未使用 |


```c
/**
 * rtgui server thread's entry
 */
static void rtgui_server_entry(void *parameter)
{
#ifdef _WIN32_NATIVE
    /* set the server thread to highest */
    HANDLE hCurrentThread = GetCurrentThread();
    SetThreadPriority(hCurrentThread, THREAD_PRIORITY_HIGHEST);
#endif

    /* create rtgui server application */
    rtgui_server_app = rtgui_app_create("rtgui");
    if (rtgui_server_app == RT_NULL)
    {
        rt_kprintf("Create GUI server failed.\n");
        return;
    }

    rtgui_object_set_event_handler(RTGUI_OBJECT(rtgui_server_app),
                                   rtgui_server_event_handler);
    /* init mouse and show */
    rtgui_mouse_init();
#ifdef RTGUI_USING_MOUSE_CURSOR
    rtgui_mouse_show_cursor();
#endif

    rtgui_app_run(rtgui_server_app);

    rtgui_app_destroy(rtgui_server_app);
    rtgui_server_app = RT_NULL;
}
```

最後會進入 `rtgui_app_run` 並正式開始執行 server，也就是進入所屬的 event handler

---
## Event Handler
Event handler 也就是 server 的進入點
