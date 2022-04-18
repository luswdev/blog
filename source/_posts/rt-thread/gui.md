---
title: RT-Thread GUI - Framework
date: 2018-11-10 21:57:14
tag: [RT-Thread, GUI]
category: RT-Thread
---
RT-Thread 的 GUI 放在 package 中（[git 原始碼](https://github.com/RT-Thread-packages/gui_engine.git)），本文將簡單將此 GUI engine 分工。

## Font
處理文字編碼，及字型相關的

```
. 
|-- src
| |-- asc12font.c
| |-- asc16font.c
| |-- font_bmp.c
| |-- font_fnt.c
| |-- font_freetype.c
| |-- font_hz_bmp.c
| |-- font_hz_file.c
| |-- font.c
| |-- hz12font.c
| |-- hz16font.c
| ˋ-- gb2312.c
|
ˋ-- include
  ˋ-- rtgui
    |-- font_fnt.h
    |-- font_freetype.h
    |-- font.h
    ˋ-- gb2312.h
```

## Image
處理圖片格式相關的

```
.
|-- src
| |-- blit.c
| |-- image_bmp.c
| |-- image_jpg.c
| |-- image_png.c
| |-- image_xpm.c
| ˋ-- image.c
|
ˋ-- include
  ˋ-- rtgui
    |-- bilt.h
    |-- image_bmp.h
    |-- image_container.h
    |-- image_hdc.h
    ˋ-- image.h
```

## Draw
協助使用者繪製一些圖形等

```
.
|-- src
| |-- color.c
| |-- dc_blend.c
| |-- dc_duffer.c
| |-- dc_client.c
| |-- dc_hw.c
| |-- dc_rotozoom.c
| |-- dc_trans.c
| ˋ-- dc.c
|
ˋ-- include
  ˋ-- rtgui
    |-- color.h
    |-- dc_draw.h
    |-- dc_trans.h
    ˋ-- dc.h
```

## Widgets
一些相關的 widgets，如按鈕、視窗等

``` 
.
|-- src
| |-- box.c
| |-- container.c
| |-- matrix.c
| |-- region.c
| |-- title.c
| |-- topwin.c
| |-- topwin.h
| |-- widgets.c
| ˋ-- window.c
|
ˋ-- include
  ˋ-- rtgui
    ˋ-- widgets
      |-- box.h
      |-- container.h
      |-- matrix.h
      |-- region.h
      |-- title.h
      |-- widget.h
      ˋ-- window.h
```

## System
系統層面的工作、及協助外部硬體，如鍵盤等。

```
.
|-- src
| |-- filerw.c
| |-- mouse.c
| |-- mouse.h
| |-- server.c
| |-- rtgui_app.c
| |-- rtgui_driver.c
| |-- rtgui_object.c
| ˋ-- rtgui_system.c
|
ˋ-- include
  ˋ-- rtgui
    |-- driver.h
    |-- event.h
    |-- filerw.h
    |-- kbddef.h
    |-- list.h
    |-- rtgui_app.h
    |-- rtgui_config.h
    |-- rtgui_object.h
    |-- rtgui_server.h
    |-- rtgui_system.h
    |-- rtgui.h
    ˋ-- tree.h
```