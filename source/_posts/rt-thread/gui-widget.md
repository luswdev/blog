---
title: RT-Thread GUI - Widget
tag: [RT-Thread, GUI, widget]
date: 2019-01-01 18:42:37
category: RT-Thread GUI
summary: GUI 中的元件，物件的上層，在 rtgui 中為基本單位，視窗是元件、按鈕也是元件
---
上一篇我們說明了 RTGUI 是如何透過 C 實現物件導向的設計邏輯，這篇將會說明在 RTGUI 中的最小物件 "widget" 是如何創建、運行及刪除的。

## 結構

{% alert success %}
**File:** widget.h
{% endalert %}

```c=86
/*
 * the base widget object
 */
struct rtgui_widget
{
    /* inherit from rtgui_object */
    struct rtgui_object object;

    /* the widget that contains this widget */
    struct rtgui_widget *parent;
    /* the window that contains this widget */
    struct rtgui_win *toplevel;
    /* the widget children and sibling */
    rtgui_list_t sibling;

    /* widget flag */
    rt_int32_t flag;

    /* hardware device context */
    rt_uint32_t dc_type;
    const struct rtgui_dc_engine *dc_engine;

    /* the graphic context of widget */
    rtgui_gc_t gc;

    /* the widget extent */
    rtgui_rect_t extent;
    /* the visiable extent (includes the rectangles of children) */
    rtgui_rect_t extent_visiable;
    /* the rect clip information */
    rtgui_region_t clip;

    /* minimal width and height of widget */
    rt_int16_t min_width, min_height;
    /* widget align */
    rt_int32_t align;
    rt_uint16_t border;
    rt_uint16_t border_style;

    /* call back */
    rt_bool_t (*on_focus_in)(struct rtgui_object *widget, struct rtgui_event *event);
    rt_bool_t (*on_focus_out)(struct rtgui_object *widget, struct rtgui_event *event);

    /* user private data */
    rt_uint32_t user_data;
};
typedef struct rtgui_widget rtgui_widget_t;
```

記錄包含他的 widget、所在的 window、一些屬性等

### 四方形結構體

{% alert success %}
**File:** rtgui.h
{% endalert %}

```c=79
/**
 * Rectangle structure
 */
struct rtgui_rect
{
    rt_int16_t x1, y1, x2, y2;
};
typedef struct rtgui_rect rtgui_rect_t;
```

對角線 (x1,y1)、(x2,y2)

---
### 定義物件類型

{% alert success %}
**File:** widget.c
{% endalert %}

```c=93
DEFINE_CLASS_TYPE(widget, "widget",
                  RTGUI_PARENT_TYPE(object),
                  _rtgui_widget_constructor,
                  _rtgui_widget_destructor,
                  sizeof(struct rtgui_widget));
RTM_EXPORT(_rtgui_widget);
```

---
## 建立 widget

| 功能 | 回傳值 | `*widget_type` |
| --- | ------ | -------------- |
| 建立 widget | widget 指標 | 欲建立的 widget 種類 |

```c=100
rtgui_widget_t *rtgui_widget_create(const rtgui_type_t *widget_type)
{
    struct rtgui_widget *widget;

    widget = RTGUI_WIDGET(rtgui_object_create(widget_type));

    return widget;
}
RTM_EXPORT(rtgui_widget_create);
```

呼叫 `rtgui_object_create`，型態為 widget 完成，並透過 `RTGUI_WIDGET` 檢查正確性

---
## 刪除 widget

| 功能 | 回傳值 | `*widget` |
| --- | ------ | --------- |
| 刪除 widget | void | 欲刪除的 widget |

```c=110
void rtgui_widget_destroy(rtgui_widget_t *widget)
{
    rtgui_object_destroy(RTGUI_OBJECT(widget));
}
RTM_EXPORT(rtgui_widget_destroy);
```

一樣透過 `rtgui_object_destroy` 來完成

---
## 設定 widget
RTT GUI 提供一些 API 給使用者去設定 widget 的樣式與行為

### 大小

| 功能 | 回傳值 |
| --- | ------ |
| 設定 widget 的大小 | void |

| `*widget` | `x` | `y` | `width` | `height` |
| --------- | --- | --- | ------- | -------- |
| 欲設定的 widget | 起始座標 x | y | 寬度 | 高度 |

```c=168
void rtgui_widget_set_rectangle(rtgui_widget_t *widget, int x, int y, int width, int height)
{
    rtgui_rect_t rect;

    rect.x1 = x;
    rect.y1 = y;
    rect.x2 = x + width;
    rect.y2 = y + height;

    rtgui_widget_set_rect(widget, &rect);
}
RTM_EXPORT(rtgui_widget_set_rectangle);
```

填入正確的 (x1,y1)、(x2,y2)

---
### Parent

| 功能 | 回傳值 |
| --- | ------ |
| 設定 widget 的上層元素 | void |

| `*widget` | `parent` |
| --------- | -------- |
| 欲設定的 widget | 上層元素 (widget) |

```c=181
void rtgui_widget_set_parent(rtgui_widget_t *widget, rtgui_widget_t *parent)
{
    /* set parent and toplevel widget */
    widget->parent = parent;
}
RTM_EXPORT(rtgui_widget_set_parent);
```

---
### 大小下界

| 功能 | 回傳值 |
| --- | ------ |
| 設定 widget 的大小下界 | void |

| `*widget` | `width` | `height` |
| --------- | ------- | -------- |
| 欲設定的 widget | 寬度 | 高度 |

```c=197
void rtgui_widget_set_minsize(rtgui_widget_t *widget, int width, int height)
{
    RT_ASSERT(widget != RT_NULL);
    widget->min_width = width;
    widget->min_height = height;
}
RTM_EXPORT(rtgui_widget_set_minsize);
```

---
### 寬度下界

| 功能 | 回傳值 |
| --- | ------ |
| 設定 widget 的寬度下界 | void |

| `*widget` | `width` |
| --------- | ------- |
| 欲設定的 widget | 寬度 |

```c=205
void rtgui_widget_set_minwidth(rtgui_widget_t *widget, int width)
{
    RT_ASSERT(widget != RT_NULL);

    widget->min_width = width;
}
RTM_EXPORT(rtgui_widget_set_minwidth);
```

---
### 高度下界 

| 功能 | 回傳值 |
| --- | ------ |
| 設定 widget 的高度下界 | void |

| `*widget` | `height` |
| --------- | -------- |
| 欲設定的 widget | 高度 |

```c=213
void rtgui_widget_set_minheight(rtgui_widget_t *widget, int height)
{
    RT_ASSERT(widget != RT_NULL);

    widget->min_height = height;
}
RTM_EXPORT(rtgui_widget_set_minheight);
```

---
### 邊框風格

| 功能 | 回傳值 |
| --- | ------ |
| 設定 widget 的邊框風格 | void |

| `*widget` | `style` |
| --------- | -------- |
| 欲設定的 widget | 風格 |

```c=302
/**
 * set widget draw style
 */
void rtgui_widget_set_border(rtgui_widget_t *widget, rt_uint32_t style)
{
    RT_ASSERT(widget != RT_NULL);

    widget->border_style = style;
    switch (style)
    {
    case RTGUI_BORDER_NONE:
        widget->border = 0;
        break;
    case RTGUI_BORDER_SIMPLE:
    case RTGUI_BORDER_UP:
    case RTGUI_BORDER_DOWN:
        widget->border = 1;
        break;
    case RTGUI_BORDER_STATIC:
    case RTGUI_BORDER_RAISE:
    case RTGUI_BORDER_SUNKEN:
    case RTGUI_BORDER_BOX:
    case RTGUI_BORDER_EXTRA:
        widget->border = 2;
        break;
    default:
        widget->border = 2;
        break;
    }
}
RTM_EXPORT(rtgui_widget_set_border);
```

---
### Focus 函式

| 功能 | 回傳值 |
| --- | ------ |
| 設定 widget 的 focus func | void |

| `*widget` | `handler` |
| --------- | --------- |
| 欲設定的 widget | focus func |

```c=334
void rtgui_widget_set_onfocus(rtgui_widget_t *widget, rtgui_event_handler_ptr handler)
{
    RT_ASSERT(widget != RT_NULL);

    widget->on_focus_in = handler;
}
RTM_EXPORT(rtgui_widget_set_onfocus);
```

---
### Unfocus 函式

| 功能 | 回傳值 |
| --- | ------ |
| 設定 widget 的 unfocus func | void |

| `*widget` | `handler` |
| --------- | --------- |
| 欲設定的 widget | focus func |

```c=342
void rtgui_widget_set_onunfocus(rtgui_widget_t *widget, rtgui_event_handler_ptr handler)
{
    RT_ASSERT(widget != RT_NULL);

    widget->on_focus_out = handler;
}
RTM_EXPORT(rtgui_widget_set_onunfocus);
```

{% alert info %}
這裡是用**指標函數**的方式將行為函式填入結構中，要使用時可直接呼叫結構中的元素使用。
{% endalert %}

---
## Widget 的行為
上一節整理了設定 widget 的風格，接下來整理 widget 的行為

### 移動到相對位置

| 功能 | 回傳值 |
| --- | ------ |
| 移動 widget 到相對位置 | void |

| `*widget` | `dx` | `dy` |
| :-------: | :--: | :--: |
| 欲移動的 widget | 位移量 x | y |

```c=251
/*
 * This function moves widget and its children to a logic point
 */
void rtgui_widget_move_to_logic(rtgui_widget_t *widget, int dx, int dy)
{
    rtgui_rect_t rect;
    rtgui_widget_t *parent;

    if (widget == RT_NULL)
        return;

    /* give clip of this widget back to parent */
    parent = widget->parent;
    if (parent != RT_NULL)
    {
        /* get the parent rect, even if it's a transparent parent. */
        rect = parent->extent_visiable;
    }

    /* we should find out the none-transparent parent */
    while (parent != RT_NULL && parent->flag & RTGUI_WIDGET_FLAG_TRANSPARENT) parent = parent->parent;
    if (parent != RT_NULL)
    {
        /* reset clip info */
        rtgui_region_init_with_extents(&(widget->clip), &(widget->extent));
        rtgui_region_intersect_rect(&(widget->clip), &(widget->clip), &rect);

        /* give back the extent */
        rtgui_region_union(&(parent->clip), &(parent->clip), &(widget->clip));
    }

    /* move this widget (and its children if it's a container) to destination point */
    _widget_move(widget, dx, dy);
    /* update this widget */
    rtgui_widget_update_clip(widget);
}
RTM_EXPORT(rtgui_widget_move_to_logic);
```

---
#### 移動 widget 

| 功能 | 回傳值 |
| --- | ------ |
| 移動 widget | void |

| `*widget` | `dx` | `dy` |
| --------- | ---- | ---- |
| 欲移動的 widget | 位移量 x | y |

```c=221
static void _widget_move(struct rtgui_widget* widget, int dx, int dy)
{
    struct rtgui_list_node *node;
    rtgui_widget_t *child, *parent;

	rtgui_rect_move(&(widget->extent), dx, dy);

    /* handle visiable extent */
    widget->extent_visiable = widget->extent;
    parent = widget->parent;
    /* we should find out the none-transparent parent */
    while (parent != RT_NULL && parent->flag & RTGUI_WIDGET_FLAG_TRANSPARENT) parent = parent->parent;
    if (widget->parent)
        rtgui_rect_intersect(&(widget->parent->extent_visiable), &(widget->extent_visiable));

    /* reset clip info */
    rtgui_region_init_with_extents(&(widget->clip), &(widget->extent));

    /* move each child */
    if (RTGUI_IS_CONTAINER(widget))
    {
        rtgui_list_foreach(node, &(RTGUI_CONTAINER(widget)->children))
        {
            child = rtgui_list_entry(node, rtgui_widget_t, sibling);

            _widget_move(child, dx, dy);
        }
    }
}
```

---
### Focus widget

| 功能 | 回傳值 | `*widget` |
| --- | ------ | --------- |
| focus widget | void | 欲 focus 的 widget |

```c=350
/**
 * @brief Focuses the widget. The focused widget is the widget which can receive the keyboard events
 * @param widget a widget
 * @note The widget has to be attached to a toplevel widget, otherwise it will have no effect
 */
void rtgui_widget_focus(rtgui_widget_t *widget)
{
    struct rtgui_widget *old_focus;

    RT_ASSERT(widget != RT_NULL);
    RT_ASSERT(widget->toplevel != RT_NULL);

    if (!RTGUI_WIDGET_IS_FOCUSABLE(widget) || !RTGUI_WIDGET_IS_ENABLE(widget))
        return;

    old_focus = RTGUI_WIN(widget->toplevel)->focused_widget;
    if (old_focus == widget)
        return; /* it's the same focused widget */

    /* unfocused the old widget */
    if (old_focus != RT_NULL)
        rtgui_widget_unfocus(old_focus);

    /* set widget as focused */
    widget->flag |= RTGUI_WIDGET_FLAG_FOCUS;
    RTGUI_WIN(widget->toplevel)->focused_widget = widget;

    /* invoke on focus in call back */
    if (widget->on_focus_in != RT_NULL)
        widget->on_focus_in(RTGUI_OBJECT(widget), RT_NULL);
}
RTM_EXPORT(rtgui_widget_focus);
```

---
### Unfocus widget

| 功能 | 回傳值 | `*widget` |
| --- | ------ | --------- |
| focus widget | void | 欲 unfocus 的 widget |

```c=383
/**
 * @brief Unfocused the widget
 * @param widget a widget
 */
void rtgui_widget_unfocus(rtgui_widget_t *widget)
{

    RT_ASSERT(widget != RT_NULL);

    if (!widget->toplevel || !RTGUI_WIDGET_IS_FOCUSED(widget))
        return;

    widget->flag &= ~RTGUI_WIDGET_FLAG_FOCUS;

    if (widget->on_focus_out != RT_NULL)
        widget->on_focus_out(RTGUI_OBJECT(widget), RT_NULL);

    RTGUI_WIN(widget->toplevel)->focused_widget = RT_NULL;

    /* Ergodic constituent widget, make child loss of focus */
    if (RTGUI_IS_CONTAINER(widget))
    {
        rtgui_list_t *node;
        rtgui_list_foreach(node, &(RTGUI_CONTAINER(widget)->children))
        {
            rtgui_widget_t *child = rtgui_list_entry(node, rtgui_widget_t, sibling);
            if (RTGUI_WIDGET_IS_HIDE(child)) continue;
            rtgui_widget_unfocus(child);
        }
    }
}
RTM_EXPORT(rtgui_widget_unfocus);
```

---
### 位移 widget
#### 點向上位移

| 功能 | 回傳值 |
| --- | ------ |
| 點向上位移 | void |

| `*widget` | `*point` |
| --------- | -------- |
| 目標 widget | 目標點   |

```c=416
void rtgui_widget_point_to_device(rtgui_widget_t *widget, rtgui_point_t *point)
{
    RT_ASSERT(widget != RT_NULL);

    if (point != RT_NULL)
    {
        point->x += widget->extent.x1;
        point->y += widget->extent.y1;
    }
}
RTM_EXPORT(rtgui_widget_point_to_device);
```

#### 點向下位移

| 功能 | 回傳值 |
| --- | ------ |
| 點向上位移 | void |

| `*widget` | `*point` |
| --------- | -------- |
| 目標 widget | 目標點   |

```c=443
void rtgui_widget_point_to_logic(rtgui_widget_t *widget, rtgui_point_t *point)
{
    RT_ASSERT(widget != RT_NULL);

    if (point != RT_NULL)
    {
        point->x -= widget->extent.x1;
        point->y -= widget->extent.y1;
    }
}
RTM_EXPORT(rtgui_widget_point_to_logic);
```

#### 矩形向上位移

| 功能 | 回傳值 |
| --- | ------ |
| 點向上位移 | void |

| `*widget` | `*rect` |
| --------- | ------- |
| 目標 widget | 目標矩形 |

```c=428
void rtgui_widget_rect_to_device(rtgui_widget_t *widget, rtgui_rect_t *rect)
{
    RT_ASSERT(widget != RT_NULL);

    if (rect != RT_NULL)
    {
        rect->x1 += widget->extent.x1;
        rect->x2 += widget->extent.x1;

        rect->y1 += widget->extent.y1;
        rect->y2 += widget->extent.y1;
    }
}
RTM_EXPORT(rtgui_widget_rect_to_device);
```

#### 矩形向下位移

| 功能 | 回傳值 |
| --- | ------ |
| 點向上位移 | void |

| `*widget` | `*rect` |
| --------- | ------- |
| 目標 widget | 目標矩形 |

```c=455
void rtgui_widget_rect_to_logic(rtgui_widget_t *widget, rtgui_rect_t *rect)
{
    RT_ASSERT(widget != RT_NULL);

    if (rect != RT_NULL)
    {
        rect->x1 -= widget->extent.x1;
        rect->x2 -= widget->extent.x1;

        rect->y1 -= widget->extent.y1;
        rect->y2 -= widget->extent.y1;
    }
}
RTM_EXPORT(rtgui_widget_rect_to_logic);
```

---
### 更新重疊區域

| 功能 | 回傳值 | `*widget` |
| --- | ------ | --------- |
| 更新重疊區域 | void | 目標 widget |

```c=531
/*
 * This function updates the clip info of widget
 */
void rtgui_widget_update_clip(rtgui_widget_t *widget)
{
    rtgui_rect_t rect;
    struct rtgui_list_node *node;
    rtgui_widget_t *parent;

    /* no widget or widget is hide, no update clip */
    if (widget == RT_NULL || RTGUI_WIDGET_IS_HIDE(widget) || widget->parent == RT_NULL)
        return;

    parent = widget->parent;
    /* reset visiable extent */
    widget->extent_visiable = widget->extent;
    rtgui_rect_intersect(&(parent->extent_visiable), &(widget->extent_visiable));

    rect = parent->extent_visiable;
    /* reset clip to extent */
    rtgui_region_reset(&(widget->clip), &(widget->extent));
    /* limit widget extent in parent extent */
    rtgui_region_intersect_rect(&(widget->clip), &(widget->clip), &rect);

    /* get the no transparent parent */
    while (parent != RT_NULL && parent->flag & RTGUI_WIDGET_FLAG_TRANSPARENT)
    {
        parent = parent->parent;
    }
    if (parent != RT_NULL)
    {
        /* give my clip back to parent */
        rtgui_region_union(&(parent->clip), &(parent->clip), &(widget->clip));

        /* subtract widget clip in parent clip */
        if (!(widget->flag & RTGUI_WIDGET_FLAG_TRANSPARENT) && RTGUI_IS_CONTAINER(parent))
        {
            rtgui_region_subtract_rect(&(parent->clip), &(parent->clip), &(widget->extent_visiable));
        }
    }

    /*
     * note: since the layout widget introduction, the sibling widget should not intersect.
     */

    /* if it's a container object, update the clip info of children */
    if (RTGUI_IS_CONTAINER(widget))
    {
        rtgui_widget_t *child;
        rtgui_list_foreach(node, &(RTGUI_CONTAINER(widget)->children))
        {
            child = rtgui_list_entry(node, rtgui_widget_t, sibling);

            rtgui_widget_update_clip(child);
        }
    }
}
RTM_EXPORT(rtgui_widget_update_clip);
```

---
### 顯示 widget

| 功能 | 回傳值 | `*widget` | 
| --- | ------ | --------- | 
| 顯示 widget | void | 目標 widget |

```c=590
void rtgui_widget_show(struct rtgui_widget *widget)
{
    struct rtgui_event_show eshow;
    RT_ASSERT(widget != RT_NULL);

    if (!RTGUI_WIDGET_IS_HIDE(widget))
        return;

    RTGUI_WIDGET_UNHIDE(widget);

    if (widget->toplevel != RT_NULL)
    {
        RTGUI_EVENT_SHOW_INIT(&eshow);
        if (RTGUI_OBJECT(widget)->event_handler != RT_NULL)
        {
            RTGUI_OBJECT(widget)->event_handler(
                RTGUI_OBJECT(widget),
                &eshow);
        }
    }
}
RTM_EXPORT(rtgui_widget_show);
```

---
### 隱藏 widget

| 功能 | 回傳值 | `*widget` |
| --- | ------ | --------- |
| 隱藏 widget | void | 目標 widget |

```c=613
void rtgui_widget_hide(struct rtgui_widget *widget)
{
    struct rtgui_event_hide ehide;
    RT_ASSERT(widget != RT_NULL);

    if (RTGUI_WIDGET_IS_HIDE(widget))
        return;

    if (widget->toplevel != RT_NULL)
    {
        RTGUI_EVENT_HIDE_INIT(&ehide);
        if (RTGUI_OBJECT(widget)->event_handler != RT_NULL)
        {
            RTGUI_OBJECT(widget)->event_handler(
                RTGUI_OBJECT(widget),
                &ehide);
        }
    }

    RTGUI_WIDGET_HIDE(widget);
}
RTM_EXPORT(rtgui_widget_hide);
```

---
## 取得 widget 資訊
最後整理一些取得 widget 資訊的 API

### Top Level

| 功能 | 回傳值 | `*widget` |
| --- | ------ | --------- |
| 取得 top level | 所在 window | 目標 widget |

```c=470
struct rtgui_win *rtgui_widget_get_toplevel(rtgui_widget_t *widget)
{
    rtgui_widget_t *r;

    RT_ASSERT(widget != RT_NULL);

    if (widget->toplevel)
        return widget->toplevel;

    rt_kprintf("widget->toplevel not properly set\n");
    r = widget;
    /* get the toplevel widget */
    while (r->parent != RT_NULL)
        r = r->parent;

    /* set toplevel */
    widget->toplevel = RTGUI_WIN(r);

    return RTGUI_WIN(r);
}
RTM_EXPORT(rtgui_widget_get_toplevel);
```

---
### 上層前景

| 功能 | 回傳值 | `*widget` |
| --- | ------ | --------- |
| 取得上層前景 | 顏色 | 目標 widget |

```c=667
rtgui_color_t rtgui_widget_get_parent_foreground(rtgui_widget_t *widget)
{
    rtgui_widget_t *parent;

    /* get parent widget */
    parent = widget->parent;
    if (parent == RT_NULL)
        return RTGUI_WIDGET_FOREGROUND(widget);

    while (parent->parent != RT_NULL && (RTGUI_WIDGET_FLAG(parent) & RTGUI_WIDGET_FLAG_TRANSPARENT))
        parent = parent->parent;

    /* get parent's color */
    return RTGUI_WIDGET_FOREGROUND(parent);
}
RTM_EXPORT(rtgui_widget_get_parent_foreground);
```

---
### 上層背景

| 功能 | 回傳值 | `*widget` |
| --- | ------ | --------- |
| 取得上層背景 | 顏色 | 目標 widget |

```c=684
rtgui_color_t rtgui_widget_get_parent_background(rtgui_widget_t *widget)
{
    rtgui_widget_t *parent;

    /* get parent widget */
    parent = widget->parent;
    if (parent == RT_NULL)
        return RTGUI_WIDGET_BACKGROUND(widget);

    while (parent->parent != RT_NULL && (RTGUI_WIDGET_FLAG(parent) & RTGUI_WIDGET_FLAG_TRANSPARENT))
        parent = parent->parent;

    /* get parent's color */
    return RTGUI_WIDGET_BACKGROUND(parent);
}
RTM_EXPORT(rtgui_widget_get_parent_background);
```

---
### 下一個兄弟

| 功能 | 回傳值 | `*widget` |
| --- | ------ | -------- |
| 取得下一個兄弟 | void | 目標 widget |

```c=744
rtgui_widget_t *rtgui_widget_get_next_sibling(rtgui_widget_t *widget)
{
    rtgui_widget_t *sibling = RT_NULL;

    if (widget->sibling.next != RT_NULL)
    {
        sibling = rtgui_list_entry(widget->sibling.next, rtgui_widget_t, sibling);
    }

    return sibling;
}
RTM_EXPORT(rtgui_widget_get_next_sibling);
```

---
### 上一個兄弟

| 功能 | 回傳值 | `*widget` |
| --- | ------ | --------- |
| 取得上一個兄弟 | void | 目標 widget |

```c=757
rtgui_widget_t *rtgui_widget_get_prev_sibling(rtgui_widget_t *widget)
{
    struct rtgui_list_node *node;
    rtgui_widget_t *sibling, *parent;

    node = RT_NULL;
    sibling = RT_NULL;
    parent = widget->parent;
    if (parent != RT_NULL)
    {
        rtgui_list_foreach(node, &(RTGUI_CONTAINER(parent)->children))
        {
            if (node->next == &(widget->sibling))
                break;
        }
    }

    if (node != RT_NULL)
        sibling = rtgui_list_entry(node, rtgui_widget_t, sibling);

    return sibling;
}
```