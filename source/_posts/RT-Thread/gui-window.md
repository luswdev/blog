---
title: RT-Thread GUI - Window
tag: [RT-Thread, GUI, window]
date: 2019-01-29 20:53:02
category: RT-Thread GUI
summary: GUI 中的視窗元件，可以移動它、放大縮小、隱藏、著色等等
---

{% alert success %}
**File:** window.h
{% endalert %}

## 結構
```c=88
struct rtgui_win
{
    /* inherit from container */
    rtgui_container_t parent;

    /* update count */
    rt_base_t update;

    /* drawing count */
    rt_base_t drawing;
    struct rtgui_rect drawing_rect;

    /* parent window. RT_NULL if the window is a top level window */
    struct rtgui_win *parent_window;

    struct rtgui_region outer_clip;
    struct rtgui_rect outer_extent;

    /* the widget that will grab the focus in current window */
    struct rtgui_widget *focused_widget;

    /* which app I belong */
    struct rtgui_app *app;

    /* window style */
    rt_uint16_t style;

    /* window state flag */
    enum rtgui_win_flag flag;

    rtgui_modal_code_t modal_code;

    /* last mouse event handled widget */
    rtgui_widget_t *last_mevent_widget;

    /* window title */
    char *title;
    struct rtgui_wintitle *_title_wgt;

    /* call back */
    rt_bool_t (*on_activate)(struct rtgui_object *widget, struct rtgui_event *event);
    rt_bool_t (*on_deactivate)(struct rtgui_object *widget, struct rtgui_event *event);
    rt_bool_t (*on_close)(struct rtgui_object *widget, struct rtgui_event *event);
    /* the key is sent to the focused widget by default. If the focused widget
     * and all of it's parents didn't handle the key event, it will be handled
     * by @func on_key
     *
     * If you want to handle key event on your own, it's better to overload
     * this function other than handle EVENT_KBD in event_handler.
     */
    rt_bool_t (*on_key)(struct rtgui_object *widget, struct rtgui_event *event);

    /* reserved user data */
    void *user_data;

    /* Private data. */
    rt_base_t (*_do_show)(struct rtgui_win *win);

    /* app ref_count */
    rt_uint16_t app_ref_count;

    /* win magic flag, magic value is 0xA5A55A5A */
    rt_uint32_t	magic;
};
```

---
### 定義物件類型

{% alert success %}
**File:** window.c
{% endalert %}

```c=34
DEFINE_CLASS_TYPE(win, "win",
                  RTGUI_PARENT_TYPE(container),
                  _rtgui_win_constructor,
                  _rtgui_win_destructor,
                  sizeof(struct rtgui_win));
```

---
## 建立視窗

| 功能 | 回傳值 |
| --- | ------ |
| 建立視窗 | 視窗指標 |

| `*parent_window` | `*title` | `*rect` | `style` |
| ---------------- | -------- | ------- | ------- |
| 上層視窗 | 視窗標題 | 視窗的大小 | 一些風格 |

```c=246
rtgui_win_t *rtgui_win_create(struct rtgui_win *parent_window,
                              const char *title,
                              rtgui_rect_t *rect,
                              rt_uint16_t style)
{
    struct rtgui_win *win;

    /* allocate win memory */
    win = RTGUI_WIN(rtgui_widget_create(RTGUI_WIN_TYPE));
    if (win == RT_NULL)
        return RT_NULL;

    if (rtgui_win_init(win, parent_window, title, rect, style) != 0)
    {
        rtgui_widget_destroy(RTGUI_WIDGET(win));
        return RT_NULL;
    }

    return win;
}
RTM_EXPORT(rtgui_win_create);
```

透過 `rtgui_win_init` 完成設定

---

| 功能 | 回傳值 |
| --- | ------ |
| 初始化視窗 | 檢查碼 |

| `*win` | `*parent_window` | `*title` | `*rect` | `style` |
| ------ | ---------------- | -------- | ------- | ------- |
| 視窗本體 | 上層視窗 | 視窗標題 | 視窗的大小 | 一些風格 |


```c=144
int rtgui_win_init(struct rtgui_win *win, struct rtgui_win *parent_window,
                   const char *title,
                   rtgui_rect_t *rect,
                   rt_uint16_t style)
{
    if (win == RT_NULL) return -1;

    /* set parent window */
    win->parent_window = parent_window;

    /* set title, rect and style */
    if (title != RT_NULL)
        win->title = rt_strdup(title);
    else
        win->title = RT_NULL;

    rtgui_widget_set_rect(RTGUI_WIDGET(win), rect);
    win->style = style;

    if (!((style & RTGUI_WIN_STYLE_NO_TITLE) && (style & RTGUI_WIN_STYLE_NO_BORDER)))
    {
        struct rtgui_rect trect = *rect;

        win->_title_wgt = rtgui_wintitle_create(win);
        if (!win->_title_wgt)
            goto __on_err;

        if (!(style & RTGUI_WIN_STYLE_NO_BORDER))
        {
            rtgui_rect_inflate(&trect, WINTITLE_BORDER_SIZE);
        }
        if (!(style & RTGUI_WIN_STYLE_NO_TITLE))
        {
            trect.y1 -= WINTITLE_HEIGHT;
        }
        rtgui_widget_set_rect(RTGUI_WIDGET(win->_title_wgt), &trect);
        /* Update the clip of the wintitle manually. */
        rtgui_region_subtract_rect(&(RTGUI_WIDGET(win->_title_wgt)->clip),
                                   &(RTGUI_WIDGET(win->_title_wgt)->clip),
                                   &(RTGUI_WIDGET(win)->extent));

        /* The window title is always un-hidden for simplicity. */
        rtgui_widget_show(RTGUI_WIDGET(win->_title_wgt));
        rtgui_region_init_with_extents(&win->outer_clip, &trect);
        win->outer_extent = trect;
    }
    else
    {
        rtgui_region_init_with_extents(&win->outer_clip, rect);
        win->outer_extent = *rect;
    }

    if (_rtgui_win_create_in_server(win) == RT_FALSE)
    {
        goto __on_err;
    }

    win->app->window_cnt++;
    return 0;

__on_err:
    return -1;
}
RTM_EXPORT(rtgui_win_init);
```

### 建立主視窗

| 功能 | 回傳值 |
| --- | ------ |
| 建立主視窗 | 視窗指標 |

| `*parent_window` | `*title` | `style` |
| ---------------- | -------- | ------- |
| 上層視窗 | 視窗標題 | 一些風格 |

```c=268
rtgui_win_t *rtgui_mainwin_create(struct rtgui_win *parent_window, const char *title, rt_uint16_t style)
{
    struct rtgui_rect rect;

    /* get rect of main window */
    rtgui_get_mainwin_rect(&rect);

    return rtgui_win_create(parent_window, title, &rect, style);
}
RTM_EXPORT(rtgui_mainwin_create);
```

建立一個固定大小的視窗，這個大小被設定在 `_mainwin_rect` 這個全域變數裡面，可以透過 `rtgui_get_mainwin_rect` 來取得這個值。

---
## 刪除視窗

| 功能 | 回傳值 | `*win` |
| --- | ------ | ------ |
| 刪除視窗 | void | 目標視窗 |

```c=314
void rtgui_win_destroy(struct rtgui_win *win)
{
    /* close the window first if it's not. */
    if (!(win->flag & RTGUI_WIN_FLAG_CLOSED))
    {
        struct rtgui_event_win_close eclose;

        RTGUI_EVENT_WIN_CLOSE_INIT(&eclose);
        eclose.wid = win;

        if (win->style & RTGUI_WIN_STYLE_DESTROY_ON_CLOSE)
        {
            _rtgui_win_deal_close(win,
                                  (struct rtgui_event *)&eclose,
                                  RT_TRUE);
            return;
        }
        else
            _rtgui_win_deal_close(win,
                                  (struct rtgui_event *)&eclose,
                                  RT_TRUE);
    }

    if (win->flag & RTGUI_WIN_FLAG_MODAL)
    {
        /* set the RTGUI_WIN_STYLE_DESTROY_ON_CLOSE flag so the window will be
         * destroyed after the event_loop */
        win->style |= RTGUI_WIN_STYLE_DESTROY_ON_CLOSE;
        rtgui_win_end_modal(win, RTGUI_MODAL_CANCEL);
    }
    else
    {
        rtgui_widget_destroy(RTGUI_WIDGET(win));
    }
}
RTM_EXPORT(rtgui_win_destroy);
```

---
## 關閉視窗

| 功能 | 回傳值 | `*win` |
| --- | ------ | ------ |
| 關閉視窗 | void | 目標視窗 |

```c=351
/* send a close event to myself to get a consistent behavior */
rt_bool_t rtgui_win_close(struct rtgui_win *win)
{
    struct rtgui_event_win_close eclose;

    RTGUI_EVENT_WIN_CLOSE_INIT(&eclose);
    eclose.wid = win;
    return _rtgui_win_deal_close(win,
                                 (struct rtgui_event *)&eclose,
                                 RT_FALSE);
}
RTM_EXPORT(rtgui_win_close);
```

使用 `_rtgui_win_deal_close` 完成關閉動作

---

| 功能 | 回傳值 |
| --- | ------ |
| 刪除視窗 | void |

| `*win` | `*event` | `force_close` |
| ------ | -------- | ------------- |
| 目標視窗 | 關閉事件 | 是否要強致關閉 |

```c=279
static rt_bool_t _rtgui_win_deal_close(struct rtgui_win *win,
                                       struct rtgui_event *event,
                                       rt_bool_t force_close)
{
    if (win->on_close != RT_NULL)
    {
        if ((win->on_close(RTGUI_OBJECT(win), event) == RT_FALSE) && !force_close)
            return RT_FALSE;
    }

    rtgui_win_hide(win);

    win->flag |= RTGUI_WIN_FLAG_CLOSED;

    if (win->flag & RTGUI_WIN_FLAG_MODAL)
    {
        /* rtgui_win_end_modal cleared the RTGUI_WIN_FLAG_MODAL in win->flag so
         * we have to record it. */
        rtgui_win_end_modal(win, RTGUI_MODAL_CANCEL);
    }

    win->app->window_cnt--;
    if (win->app->window_cnt == 0 && !(win->app->state_flag & RTGUI_APP_FLAG_KEEP))
    {
        rtgui_app_exit(rtgui_app_self(), 0);
    }

    if (win->style & RTGUI_WIN_STYLE_DESTROY_ON_CLOSE)
    {
        rtgui_win_destroy(win);
    }

    return RT_TRUE;
}
```

---
## 設定視窗
### 大小

| 功能 | 回傳值 |
| --- | ------ |
| 設定視窗大小 | void |

| `*win` | `*rect` |
| ------ | ------- |
| 視窗本體 | 新大小 |

```c=842
void rtgui_win_set_rect(rtgui_win_t *win, rtgui_rect_t *rect)
{
    struct rtgui_event_win_resize event;

    if (win == RT_NULL || rect == RT_NULL) return;

    RTGUI_WIDGET(win)->extent = *rect;

    if (win->flag & RTGUI_WIN_FLAG_CONNECTED)
    {
        /* set window resize event to server */
        RTGUI_EVENT_WIN_RESIZE_INIT(&event);
        event.wid = win;
        event.rect = *rect;

        rtgui_server_post_event(&(event.parent), sizeof(struct rtgui_event_win_resize));
    }
}
RTM_EXPORT(rtgui_win_set_rect);
```

---
### OnActive 函式

| 功能 | 回傳值 |
| --- | ------ |
| 設定 OnActive 函式 | void |

| `*win` | `handler` |
| ------ | --------- |
| 視窗本體 | OnActive 函式 |

```c=862
void rtgui_win_set_onactivate(rtgui_win_t *win, rtgui_event_handler_ptr handler)
{
    if (win != RT_NULL)
    {
        win->on_activate = handler;
    }
}
RTM_EXPORT(rtgui_win_set_onactivate);
```

---
### OnDeactive 函式

| 功能 | 回傳值 |
| --- | ------ |
| 設定 OnDeactive 函式 | void |

| `*win` | `handler` |
| ------ | --------- |
| 視窗本體 | OnDeactive 函式 |

```c=871
void rtgui_win_set_ondeactivate(rtgui_win_t *win, rtgui_event_handler_ptr handler)
{
    if (win != RT_NULL)
    {
        win->on_deactivate = handler;
    }
}
RTM_EXPORT(rtgui_win_set_ondeactivate);
```

---
### OnClose 函式

| 功能 | 回傳值 |
| --- | ------ |
| 設定 OnClose 函式 | void |

| `*win` | `handler` |
| ------ | --------- |
| 視窗本體 | OnClose 函式 |

```c=880
void rtgui_win_set_onclose(rtgui_win_t *win, rtgui_event_handler_ptr handler)
{
    if (win != RT_NULL)
    {
        win->on_close = handler;
    }
}
RTM_EXPORT(rtgui_win_set_onclose);
```

---
### OnKey

| 功能 | 回傳值 |
| --- | ------ |
| 設定 OnKey 函式 | void |

| `*win` | `handler` |
| ------ | --------- |
| 視窗本體 | OnKey 函式 |

```c=889
void rtgui_win_set_onkey(rtgui_win_t *win, rtgui_event_handler_ptr handler)
{
    if (win != RT_NULL)
    {
        win->on_key = handler;
    }
}
RTM_EXPORT(rtgui_win_set_onkey);
```

---
## 視窗的行為
### 動態模式

| 功能 | 回傳值 | `*win` |
| --- | ------ | ------ |
| 進入動態模式 | 檢查碼 | 視窗本體 |

```c=364
rt_base_t rtgui_win_enter_modal(struct rtgui_win *win)
{
    rt_base_t exit_code = -1;
    struct rtgui_event_win_modal_enter emodal;

    RTGUI_EVENT_WIN_MODAL_ENTER_INIT(&emodal);
    emodal.wid = win;

    if (rtgui_server_post_event_sync((struct rtgui_event *)&emodal,
                                     sizeof(emodal)) != RT_EOK)
        return exit_code;

    win->flag |= RTGUI_WIN_FLAG_MODAL;
    win->app_ref_count = win->app->ref_count + 1;
    exit_code = rtgui_app_run(win->app);
    win->flag &= ~RTGUI_WIN_FLAG_MODAL;

    rtgui_win_hide(win);

    return exit_code;
}
RTM_EXPORT(rtgui_win_enter_modal);
```

---

| 功能 | 回傳值 |
| --- | ------ |
| 離開動態模式 | 檢查碼 |

| `*win` | `modal_code` |
| ------ | ------------ |
| 視窗本體 | 動態模式編號 |


```c=453
void rtgui_win_end_modal(struct rtgui_win *win, rtgui_modal_code_t modal_code)
{
    int i = 0;
    if (win == RT_NULL || !(win->flag & RTGUI_WIN_FLAG_MODAL))
        return;

    while (win->app_ref_count < win->app->ref_count)
    {
        rtgui_app_exit(win->app, 0);

        i ++;
        if (i >= 1000)
        {
            rt_kprintf(" =*=> rtgui_win_end_modal while (win->app_ref_count < win->app->ref_count) \n");
            RT_ASSERT(0);
        }
    }

    rtgui_app_exit(win->app, modal_code);

    /* remove modal mode */
    win->flag &= ~RTGUI_WIN_FLAG_MODAL;
}
RTM_EXPORT(rtgui_win_end_modal);
```

---
### 現身

| 功能 | 回傳值 |
| --- | ------ |
| 現身該視窗 | 檢查碼 |

| `*win` | `is_modal` |
| ------ | ---------- |
| 視窗本體 | 是否為動態模式 |

```c=439
rt_base_t rtgui_win_show(struct rtgui_win *win, rt_bool_t is_modal)
{
    RTGUI_WIDGET_UNHIDE(win);

    win->magic = RTGUI_WIN_MAGIC;

    if (is_modal)
        win->flag |= RTGUI_WIN_FLAG_MODAL;
    if (win->_do_show)
        return win->_do_show(win);
    return rtgui_win_do_show(win);
}
RTM_EXPORT(rtgui_win_show);
```

如果視窗本身有設定 `_do_show` 函式的話，則呼叫本身的；否則呼叫 `rtgui_win_do_show`

---

| 功能 | 回傳值 | `*win` |
| --- | ------ | ------ |
| 視窗現身 | 檢查碼 | 視窗本體 |

```c=387
rt_base_t rtgui_win_do_show(struct rtgui_win *win)
{
    rt_base_t exit_code = -1;
    struct rtgui_app *app;
    struct rtgui_event_win_show eshow;

    RTGUI_EVENT_WIN_SHOW_INIT(&eshow);
    eshow.wid = win;

    if (win == RT_NULL)
        return exit_code;

    win->flag &= ~RTGUI_WIN_FLAG_CLOSED;
    win->flag &= ~RTGUI_WIN_FLAG_CB_PRESSED;

    /* if it does not register into server, create it in server */
    if (!(win->flag & RTGUI_WIN_FLAG_CONNECTED))
    {
        if (_rtgui_win_create_in_server(win) == RT_FALSE)
            return exit_code;
    }

    /* set window unhidden before notify the server */
    rtgui_widget_show(RTGUI_WIDGET(win));

    if (rtgui_server_post_event_sync(RTGUI_EVENT(&eshow),
                                     sizeof(struct rtgui_event_win_show)) != RT_EOK)
    {
        /* It could not be shown if a parent window is hidden. */
        rtgui_widget_hide(RTGUI_WIDGET(win));
        return exit_code;
    }

    if (win->focused_widget == RT_NULL)
        rtgui_widget_focus(RTGUI_WIDGET(win));

    app = win->app;
    RT_ASSERT(app != RT_NULL);

    /* set main window */
    if (app->main_object == RT_NULL)
        rtgui_app_set_main_win(app, win);

    if (win->flag & RTGUI_WIN_FLAG_MODAL)
    {
        exit_code = rtgui_win_enter_modal(win);
    }

    return exit_code;
}
RTM_EXPORT(rtgui_win_do_show);
```

---
### 隱藏

| 功能 | 回傳值 | `*win` |
| --- | ------ | ------ |
| 隱藏視窗 | 檢查碼 | 視窗本體 |

```c=478
void rtgui_win_hide(struct rtgui_win *win)
{
    RT_ASSERT(win != RT_NULL);

    if (!RTGUI_WIDGET_IS_HIDE(win) &&
            win->flag & RTGUI_WIN_FLAG_CONNECTED)
    {
        /* send hidden message to server */
        struct rtgui_event_win_hide ehide;
        RTGUI_EVENT_WIN_HIDE_INIT(&ehide);
        ehide.wid = win;

        if (rtgui_server_post_event_sync(RTGUI_EVENT(&ehide),
                                         sizeof(struct rtgui_event_win_hide)) != RT_EOK)
        {
            rt_kprintf("hide win: %s failed\n", win->title);
            return;
        }

        rtgui_widget_hide(RTGUI_WIDGET(win));
        win->flag &= ~RTGUI_WIN_FLAG_ACTIVATE;
    }
}
RTM_EXPORT(rtgui_win_hide);
```

---
### 啟動

| 功能 | 回傳值 | `*win` |
| --- | ------ | ------ |
| 啟動視窗 | 檢查碼 | 視窗本體 |

```c=503
rt_err_t rtgui_win_activate(struct rtgui_win *win)
{
    struct rtgui_event_win_activate eact;
    RTGUI_EVENT_WIN_ACTIVATE_INIT(&eact);
    eact.wid = win;

    return rtgui_server_post_event_sync(RTGUI_EVENT(&eact),
                                        sizeof(eact));
}
RTM_EXPORT(rtgui_win_activate);
```

---
### 移動

| 功能 | 回傳值 |
| --- | ------ |
| 進入動態模式 | 檢查碼 |

| `*win` | `x` | `y` |
| ------ | --- | --- |
| 視窗本體 | 目標 x | 目標 y |

```c=524
void rtgui_win_move(struct rtgui_win *win, int x, int y)
{
    struct rtgui_widget *wgt;
    struct rtgui_event_win_move emove;
    int dx, dy;
    RTGUI_EVENT_WIN_MOVE_INIT(&emove);

    if (win == RT_NULL)
        return;

    if (win->_title_wgt)
    {
        wgt = RTGUI_WIDGET(win->_title_wgt);
        dx = x - wgt->extent.x1;
        dy = y - wgt->extent.y1;
        rtgui_widget_move_to_logic(wgt, dx, dy);

        wgt = RTGUI_WIDGET(win);
        rtgui_widget_move_to_logic(wgt, dx, dy);
    }
    else
    {
        wgt = RTGUI_WIDGET(win);
        dx = x - wgt->extent.x1;
        dy = y - wgt->extent.y1;
        rtgui_widget_move_to_logic(wgt, dx, dy);
    }
    rtgui_rect_move(&win->outer_extent, dx, dy);

    if (win->flag & RTGUI_WIN_FLAG_CONNECTED)
    {
        rtgui_widget_hide(RTGUI_WIDGET(win));

        emove.wid   = win;
        emove.x     = x;
        emove.y     = y;
        if (rtgui_server_post_event_sync(RTGUI_EVENT(&emove),
                                         sizeof(struct rtgui_event_win_move)) != RT_EOK)
        {
            return;
        }
    }

    rtgui_widget_show(RTGUI_WIDGET(win));
    return;
}
RTM_EXPORT(rtgui_win_move);
```

---
### OnDraw

| 功能 | 回傳值 | `*win` |
| --- | ------ | ------ |
| OnDraw | boolean | 視窗本體 |

```c=572
static rt_bool_t rtgui_win_ondraw(struct rtgui_win *win)
{
    struct rtgui_dc *dc;
    struct rtgui_rect rect;
    struct rtgui_event_paint event;

    /* begin drawing */
    dc = rtgui_dc_begin_drawing(RTGUI_WIDGET(win));
    if (dc == RT_NULL)
        return RT_FALSE;

    /* get window rect */
    rtgui_widget_get_rect(RTGUI_WIDGET(win), &rect);
    /* fill area */
    rtgui_dc_fill_rect(dc, &rect);

    /* widget drawing */

    /* paint each widget */
    RTGUI_EVENT_PAINT_INIT(&event);
    event.wid = RT_NULL;

    rtgui_container_dispatch_event(RTGUI_CONTAINER(win),
                                   (rtgui_event_t *)&event);

    rtgui_dc_end_drawing(dc, 1);

    return RT_FALSE;
}
```

---
### 更新重疊區域

| 功能 | 回傳值 | `*win` |
| --- | ------ | ------ |
| 更新重疊區域 | void | 視窗本體 |

```c=602
void rtgui_win_update_clip(struct rtgui_win *win)
{
    struct rtgui_container *cnt;
    struct rtgui_list_node *node;

    if (win == RT_NULL)
        return;

    if (win->flag & RTGUI_WIN_FLAG_CLOSED)
        return;

    if (win->_title_wgt)
    {
        /* Reset the inner clip of title. */
        RTGUI_WIDGET(win->_title_wgt)->extent = win->outer_extent;
        rtgui_region_copy(&RTGUI_WIDGET(win->_title_wgt)->clip, &win->outer_clip);
        rtgui_region_subtract_rect(&RTGUI_WIDGET(win->_title_wgt)->clip,
                                   &RTGUI_WIDGET(win->_title_wgt)->clip,
                                   &RTGUI_WIDGET(win)->extent);
        /* Reset the inner clip of window. */
        rtgui_region_intersect_rect(&RTGUI_WIDGET(win)->clip,
                                    &win->outer_clip,
                                    &RTGUI_WIDGET(win)->extent);
    }
    else
    {
        RTGUI_WIDGET(win)->extent = win->outer_extent;
        rtgui_region_copy(&RTGUI_WIDGET(win)->clip, &win->outer_clip);
    }

    /* update the clip info of each child */
    cnt = RTGUI_CONTAINER(win);
    rtgui_list_foreach(node, &(cnt->children))
    {
        rtgui_widget_t *child = rtgui_list_entry(node, rtgui_widget_t, sibling);

        rtgui_widget_update_clip(child);
    }
}
RTM_EXPORT(rtgui_win_update_clip);
```

---
## Event Handler
此 event handler 也就是 window 的函式進入點

| 功能 | 回傳值 |
| --- | ------ |
| window 函式進入點 | boolean |

| `*object` | `*event` |
| --------- | -------- |
| 物件本體 | 行為本體 |

```c=678
rt_bool_t rtgui_win_event_handler(struct rtgui_object *object, struct rtgui_event *event)
{
    struct rtgui_win *win;

    RT_ASSERT(object != RT_NULL);
    RT_ASSERT(event != RT_NULL);

    win = RTGUI_WIN(object);

    switch (event->type)
    {
    case RTGUI_EVENT_WIN_SHOW:
        rtgui_win_do_show(win);
        break;

    case RTGUI_EVENT_WIN_HIDE:
        rtgui_win_hide(win);
        break;

    case RTGUI_EVENT_WIN_CLOSE:
        _rtgui_win_deal_close(win, event, RT_FALSE);
        /* don't broadcast WIN_CLOSE event to others */
        return RT_TRUE;

    case RTGUI_EVENT_WIN_MOVE:
    {
        struct rtgui_event_win_move *emove = (struct rtgui_event_win_move *)event;

        /* move window */
        rtgui_win_move(win, emove->x, emove->y);
    }
    break;

    case RTGUI_EVENT_WIN_ACTIVATE:
        if (win->flag & RTGUI_WIN_FLAG_UNDER_MODAL ||
                RTGUI_WIDGET_IS_HIDE(win))
        {
            /* activate a hide window */
            return RT_TRUE;
        }

        win->flag |= RTGUI_WIN_FLAG_ACTIVATE;
        /* There are many cases where the paint event will follow this activate
         * event and just repaint the title is not a big deal. So just repaint
         * the title if there is one. If you want to update the content of the
         * window, do it in the on_activate callback.*/
        if (win->_title_wgt)
            rtgui_widget_update(RTGUI_WIDGET(win->_title_wgt));

        if (win->on_activate != RT_NULL)
        {
            win->on_activate(RTGUI_OBJECT(object), event);
        }
        break;

    case RTGUI_EVENT_WIN_DEACTIVATE:
        win->flag &= ~RTGUI_WIN_FLAG_ACTIVATE;
        /* No paint event follow the deactive event. So we have to update
         * the title manually to reflect the change. */
        if (win->_title_wgt)
            rtgui_widget_update(RTGUI_WIDGET(win->_title_wgt));

        if (win->on_deactivate != RT_NULL)
            win->on_deactivate(RTGUI_OBJECT(object), event);

        break;

    case RTGUI_EVENT_WIN_UPDATE_END:
        break;

    case RTGUI_EVENT_CLIP_INFO:
        /* update win clip */
        rtgui_win_update_clip(win);
        break;

    case RTGUI_EVENT_PAINT:
        if (win->_title_wgt)
            rtgui_widget_update(RTGUI_WIDGET(win->_title_wgt));
        rtgui_win_ondraw(win);
        break;

#ifdef GUIENGIN_USING_VFRAMEBUFFER
    case RTGUI_EVENT_VPAINT_REQ:
    {
        struct rtgui_event_vpaint_req *req = (struct rtgui_event_vpaint_req *)event;
        struct rtgui_dc *dc;

        /* get drawing dc */
        dc = rtgui_win_get_drawing(win);

        req->sender->buffer = dc;
        rt_completion_done(req->sender->cmp);

        break;
    }
#endif

    case RTGUI_EVENT_MOUSE_BUTTON:
    {
        struct rtgui_event_mouse *emouse = (struct rtgui_event_mouse*)event;

        if (rtgui_rect_contains_point(&RTGUI_WIDGET(win)->extent,
                                      emouse->x, emouse->y) == RT_EOK)
            return _win_handle_mouse_btn(win, event);

        if (win->_title_wgt)
        {
            struct rtgui_object *tobj = RTGUI_OBJECT(win->_title_wgt);
            return tobj->event_handler(tobj, event);
        }
    }
    break;

    case RTGUI_EVENT_MOUSE_MOTION:
        return rtgui_container_dispatch_mouse_event(RTGUI_CONTAINER(win),
                (struct rtgui_event_mouse *)event);

    case RTGUI_EVENT_KBD:
        /* we should dispatch key event firstly */
        if (!(win->flag & RTGUI_WIN_FLAG_HANDLE_KEY))
        {
            struct rtgui_widget *widget;
            rt_bool_t res = RT_FALSE;
            /* we should dispatch the key event just once. Once entered the
             * dispatch mode, we should swtich to key handling mode. */
            win->flag |= RTGUI_WIN_FLAG_HANDLE_KEY;

            /* dispatch the key event */
            for (widget = win->focused_widget;
                    widget && !res;
                    widget = widget->parent)
            {
                if (RTGUI_OBJECT(widget)->event_handler != RT_NULL)
                    res = RTGUI_OBJECT(widget)->event_handler(
                              RTGUI_OBJECT(widget), event);
            }

            win->flag &= ~RTGUI_WIN_FLAG_HANDLE_KEY;
            return res;
        }
        else
        {
            /* in key handling mode(it may reach here in
             * win->focused_widget->event_handler call) */
            if (win->on_key != RT_NULL)
                return win->on_key(RTGUI_OBJECT(win), event);
        }
        break;

    case RTGUI_EVENT_COMMAND:
        if (rtgui_container_dispatch_event(RTGUI_CONTAINER(object), event) != RT_TRUE)
        {
        }
        else return RT_TRUE;
        break;

    default:
        return rtgui_container_event_handler(object, event);
    }

    return RT_FALSE;
}
RTM_EXPORT(rtgui_win_event_handler);
```
