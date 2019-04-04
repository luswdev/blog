---
title: RT-Thread GUI 繪圖引擎 1 (硬體)
tag: [RT-Thread, GUI, dc, kernel]
date: 2019-03-13 11:27:41
category: RT-Thread GUI
summary: GUI 最重要的繪圖核心，第一部分：硬體；說明 GUI 是如何透過硬體的驅動來畫上點線面。
mathjax: true
top: true
---
Rtgui 中的 dc (drawable canvas) 也就是繪圖引擎，可以說是 rtgui 中最重要的一個部分，其中分成 3 個部分：給硬體的 dc_hw、給 buffer 的 dc_buffer 以及給 client 的。

接下來將會追蹤 dc_hw 中的程式碼，分析 rtgui 是如何在螢幕上面描繪點線面。

---
## 結構
>File: dc.h

### dc
```c
/*
 * The abstract device context
 *
 * Normally, a DC is a drawable canvas, user can draw point/line/cycle etc
 * on the DC.
 *
 * There are several kinds of DC:
 * - Hardware DC;
 * - Client DC;
 * - Buffer DC;
 */
struct rtgui_dc
{
    /* type of device context */
    rt_uint32_t type;

    /* dc engine */
    const struct rtgui_dc_engine *engine;
};
```

---
### dc_engine
```c
struct rtgui_dc_engine
{
    /* interface */
    void (*draw_point)(struct rtgui_dc *dc, int x, int y);
    void (*draw_color_point)(struct rtgui_dc *dc, int x, int y, rtgui_color_t color);
    void (*draw_vline)(struct rtgui_dc *dc, int x, int y1, int y2);
    void (*draw_hline)(struct rtgui_dc *dc, int x1, int x2, int y);
    void (*fill_rect)(struct rtgui_dc *dc, rtgui_rect_t *rect);
    void (*blit_line)(struct rtgui_dc *dc, int x1, int x2, int y, rt_uint8_t *line_data);
    void (*blit)(struct rtgui_dc *dc, struct rtgui_point *dc_point, struct rtgui_dc *dest, rtgui_rect_t *rect);

    rt_bool_t (*fini)(struct rtgui_dc *dc);
};
```

---
### dc_hw
```c
/*
 * The hardware device context
 *
 * The hardware DC is a context based on hardware device, for examle the
 * LCD device. The operations on the hardware DC are reflected to the real
 * hardware.
 *
 */
struct rtgui_dc_hw
{
    struct rtgui_dc parent;
    rtgui_widget_t *owner;
    const struct rtgui_graphic_driver *hw_driver;
};
```

---
### hw_engine
>File: dc_hw.c

```c
const struct rtgui_dc_engine dc_hw_engine =
{
    rtgui_dc_hw_draw_point,
    rtgui_dc_hw_draw_color_point,
    rtgui_dc_hw_draw_vline,
    rtgui_dc_hw_draw_hline,
    rtgui_dc_hw_fill_rect,
    rtgui_dc_hw_blit_line,
    rtgui_dc_hw_blit,

    rtgui_dc_hw_fini,
};
```

---
## 啟動 dc
我們可以從 rtgui 官方提供的範例發現，在使用 dc 前，需要先利用 `rtgui_dc_begin_drawing` 來啟動引擎，並在結束時呼叫 `rtgui_dc_end_drawing`；而啟動時，會判斷要使用哪種 dc，並啟動，如 1866 至 1871 行

```
/* create client or hardware DC */
    if ((rtgui_region_is_flat(&owner->clip) == RT_EOK) &&
            rtgui_rect_is_equal(&(owner->extent), &(owner->clip.extents)) == RT_EOK)
        dc = rtgui_dc_hw_create(owner);
    else
        dc = rtgui_dc_client_create(owner);
```

如果判斷為 hw，則進入 `rtgui_dc_hw_create` 

---
## 建立 dc
<i class="fa fa-code"></i> Code: `rtgui_dc_hw_create`

| 功能 | 回傳值 |
| --- | ------ |
| 建立 dc | dc 指標 |


| `*owner` |
| -------- |
| dc 擁有者 |


```c
struct rtgui_dc *rtgui_dc_hw_create(rtgui_widget_t *owner)
{
    struct rtgui_dc_hw *dc;

    /* adjudge owner */
    if (owner == RT_NULL || owner->toplevel == RT_NULL) return RT_NULL;

    /* create DC */
    dc = (struct rtgui_dc_hw *) rtgui_malloc(sizeof(struct rtgui_dc_hw));
    if (dc)
    {
        dc->parent.type = RTGUI_DC_HW;
        dc->parent.engine = &dc_hw_engine;
        dc->owner = owner;
        dc->hw_driver = rtgui_graphic_driver_get_default();

        return &(dc->parent);
    }

    return RT_NULL;
}
```

---
## 運作 dc (畫圖)
### 點
<i class="fa fa-code"></i> Code: `rtgui_dc_hw_draw_point`

<!-- tab 性質 -->
| 功能 | 回傳值 |
| --- | ------ |
| 畫點 | void |
<!-- endtab -->
<!-- tab 元素 -->
| `*self` | `x` | `y` |
| ------- | :-: | :-: |
| dc 本體 | 座標 x | 座標 y |
<!-- endtab -->

```c
/*
 * draw a logic point on device
 */
static void rtgui_dc_hw_draw_point(struct rtgui_dc *self, int x, int y)
{
    struct rtgui_dc_hw *dc;

    RT_ASSERT(self != RT_NULL);
    dc = (struct rtgui_dc_hw *) self;

    if (x < 0 || y < 0)
        return;

    x = x + dc->owner->extent.x1;
    if (x >= dc->owner->extent.x2)
        return;
    y = y + dc->owner->extent.y1;
    if (y >= dc->owner->extent.y2)
        return;

    /* draw this point */
    dc->hw_driver->ops->set_pixel(&(dc->owner->gc.foreground), x, y);
}
```

首先傳進去的座標一律為邏輯位置，也就是以此 dc 所屬物件（有可能是視窗、元件等）的 $(x_1,y_1)$ 為原點之座標；由於 $(x_1,y_1)$ 為該物件（通常為矩形）的左下角，所以傳入的座標不會有負號。

接著將邏輯座標轉為實際座標（也就是螢幕上的真正位置），所以把 $(x,y)$ 轉成 $(x+x_1,y+y_1)$；由於 dc 是跟隨物件的，所以新座標不可超過 $(x_2,y_2)$，也就是右上角。

最後利用驅動中設定好的 `set_pixel` 函數來上色，這裡使用預設顏色。

---
### 彩色點
<i class="fa fa-code"></i> Code: `rtgui_dc_hw_draw_color_point`

<!-- tab 性質 -->
| 功能 | 回傳值 |
| --- | ------ |
| 畫彩色點 | void |
<!-- endtab -->
<!-- tab 元素 -->
| `*self` | `x` | `y` | `color` |
| ------- | :-: | :-: | ------- |
| dc 本體 | 座標 x | 座標 y | 所選的顏色 |
<!-- endtab -->

```c
static void rtgui_dc_hw_draw_color_point(struct rtgui_dc *self, int x, int y, rtgui_color_t color)
{
    struct rtgui_dc_hw *dc;

    RT_ASSERT(self != RT_NULL);
    dc = (struct rtgui_dc_hw *) self;

    if (x < 0 || y < 0)
        return;

    x = x + dc->owner->extent.x1;
    if (x >= dc->owner->extent.x2)
        return;
    y = y + dc->owner->extent.y1;
    if (y >= dc->owner->extent.y2)
        return;

    /* draw this point */
    dc->hw_driver->ops->set_pixel(&color, x, y);
}
```

跟上面最大的不同是可以選顏色 (131)。

---
### 水平線
```c
/*
 * draw a logic vertical line on device
 */
static void rtgui_dc_hw_draw_vline(struct rtgui_dc *self, int x, int y1, int y2)
{
    struct rtgui_dc_hw *dc;

    RT_ASSERT(self != RT_NULL);
    dc = (struct rtgui_dc_hw *) self;

    if (x < 0)
        return;
    x = x + dc->owner->extent.x1;
    if (x >= dc->owner->extent.x2)
        return;
    y1 = y1 + dc->owner->extent.y1;
    y2 = y2 + dc->owner->extent.y1;

    if (y1 > y2)
        _int_swap(y1, y2);
    if (y1 > dc->owner->extent.y2 || y2 < dc->owner->extent.y1)
        return;

    if (y1 < dc->owner->extent.y1)
        y1 = dc->owner->extent.y1;
    if (y2 > dc->owner->extent.y2)
        y2 = dc->owner->extent.y2;


    /* draw vline */
    dc->hw_driver->ops->draw_vline(&(dc->owner->gc.foreground), x, y1, y2);
}
```

---
### 鉛直線

```c
/*
 * draw a logic horizontal line on device
 */
static void rtgui_dc_hw_draw_hline(struct rtgui_dc *self, int x1, int x2, int y)
{
    struct rtgui_dc_hw *dc;

    RT_ASSERT(self != RT_NULL);
    dc = (struct rtgui_dc_hw *) self;

    if (y < 0)
        return;
    y = y + dc->owner->extent.y1;
    if (y >= dc->owner->extent.y2)
        return;

    /* convert logic to device */
    x1 = x1 + dc->owner->extent.x1;
    x2 = x2 + dc->owner->extent.x1;
    if (x1 > x2)
        _int_swap(x1, x2);
    if (x1 > dc->owner->extent.x2 || x2 < dc->owner->extent.x1)
        return;

    if (x1 < dc->owner->extent.x1)
        x1 = dc->owner->extent.x1;
    if (x2 > dc->owner->extent.x2)
        x2 = dc->owner->extent.x2;

    /* draw hline */
    dc->hw_driver->ops->draw_hline(&(dc->owner->gc.foreground), x1, x2, y);
}
```

---
### 矩形

```c
static void rtgui_dc_hw_fill_rect(struct rtgui_dc *self, struct rtgui_rect *rect)
{
    rtgui_color_t color;
    register rt_base_t y1, y2, x1, x2;
    struct rtgui_dc_hw *dc;

    RT_ASSERT(self != RT_NULL);
    RT_ASSERT(rect);
    dc = (struct rtgui_dc_hw *) self;

    /* get background color */
    color = dc->owner->gc.background;

    /* convert logic to device */
    x1 = rect->x1 + dc->owner->extent.x1;
    if (x1 > dc->owner->extent.x2)
        return;
    if (x1 < dc->owner->extent.x1)
        x1 = dc->owner->extent.x1;
    x2 = rect->x2 + dc->owner->extent.x1;
    if (x2 < dc->owner->extent.x1)
        return;
    if (x2 > dc->owner->extent.x2)
        x2 = dc->owner->extent.x2;

    y1 = rect->y1 + dc->owner->extent.y1;
    if (y1 > dc->owner->extent.y2)
        return;
    if (y1 < dc->owner->extent.y1)
        y1 = dc->owner->extent.y1;
    y2 = rect->y2 + dc->owner->extent.y1;
    if (y2 < dc->owner->extent.y1)
        return;
    if (y2 > dc->owner->extent.y2)
        y2 = dc->owner->extent.y2;

    /* fill rect */
    for (; y1 < y2; y1++)
    {
        dc->hw_driver->ops->draw_hline(&color, x1, x2, y1);
    }
}
```

---
### blit(?)

```c
static void rtgui_dc_hw_blit_line(struct rtgui_dc *self, int x1, int x2, int y, rt_uint8_t *line_data)
{
    struct rtgui_dc_hw *dc;

    RT_ASSERT(self != RT_NULL);
    dc = (struct rtgui_dc_hw *) self;

    /* convert logic to device */
    if (y < 0)
        return;
    y = y + dc->owner->extent.y1;
    if (y > dc->owner->extent.y2)
        return;

    x1 = x1 + dc->owner->extent.x1;
    x2 = x2 + dc->owner->extent.x1;
    if (x1 > x2)
        _int_swap(x1, x2);

    if (x1 > dc->owner->extent.x2 || x2 < dc->owner->extent.x1)
        return;
    if (x1 < dc->owner->extent.x1)
        x1 = dc->owner->extent.x1;
    if (x2 > dc->owner->extent.x2)
        x2 = dc->owner->extent.x2;

    dc->hw_driver->ops->draw_raw_hline(line_data, x1, x2, y);
}
```

---

```c
static void rtgui_dc_hw_blit(struct rtgui_dc *dc,
                             struct rtgui_point *dc_point,
                             struct rtgui_dc *dest,
                             rtgui_rect_t *rect)
{
    /* not blit in hardware dc */
    return ;
}
```