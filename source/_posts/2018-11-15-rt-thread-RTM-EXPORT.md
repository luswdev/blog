---
title: RT-Thread 理解 RTM_EXPORT
copyright: true
toc: true
date: 2018-11-15 00:24:05
categories: RT-Thread
tag: [RT-Thread, kernel, c, RTM_EXPORT, EXPORT_SYMBOL]
---
在 RT-Thread 的 kernel 中，許多副程式的結尾都有 `RTM_EXPORT`，如：

```c=
/** 
 * file: scheduler.c (360)
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
`RTM_EXPORT` 是在 *rtm.h* 中所定義的一個巨集。

<!-- more -->

## File: rtm.h
- `RTM_EXPORT` 可被定義的方式有三種：

### 1. _MSC_VER
```c=
#if defined(_MSC_VER)
#pragma section("RTMSymTab$f",read)
#define RTM_EXPORT(symbol)                                            \
__declspec(allocate("RTMSymTab$f"))const char __rtmsym_##symbol##_name[] = "__vs_rtm_"#symbol;
#pragma comment(linker, "/merge:RTMSymTab=mytext")

```

### 2. __MINGW32_
```c=
#elif defined(__MINGW32__)
#define RTM_EXPORT(symbol)
```

### 3. else
```c=
#else
#define RTM_EXPORT(symbol)                                            \
const char __rtmsym_##symbol##_name[] SECTION(".rodata.name") = #symbol;     \
const struct rt_module_symtab __rtmsym_##symbol SECTION("RTMSymTab")= \
{                                                                     \
    (void *)&symbol,                                                  \
    __rtmsym_##symbol##_name                                          \
};
#endif
```

- `##` 為連字符[^1]，作用是將指定文字帶到變數名稱裡；如：當傳進來的 `symbol` 值是 `rt_enter_critical` 時，此字串的變數名會被宣告成 `__rtmsym_rt_enter_critical_name`
- `SECTION` 為 `__attribute__((section))` 的巨集寫法
- `#` 為字串話操作符，作用是將後面的變數轉換成字串；如當傳進來的 `symbol` 值是 `rt_enter_critical` 時，`#symbol` 會被轉換成 `"rt_enter_critical"`
- 綜合以上，我們可以將原來的 `RTM_EXPORT(rt_enter_critical)` 透過 `define` 轉換成以下程式碼：

```c
const char __rtmsym_rt_enter_critical_name[] __attribute__((section(".rodata.name"))) \
= "rt_enter_critical";
const struct rt_module_symtab __rtmsym_rt_enter_critical  \
 __attribute__((section("RTMSymTab")))=
{                                                                     
    (void *)&rt_enter_critical,                                                  
    __rtmsym_rt_enter_critical_name                                          
};
```

[^1]:[C/C++ 的預處理定義 : # , #@ , ##](https://blog.xuite.net/jesonchung/scienceview/93554778-C%2FC%2B%2B+的預處理定義+%3A+%23+%2C++%23%40+%2C+%23%23)

```c struct rt_module_symtab
struct rt_module_symtab
{
    void       *addr;
    const char *name;
};
```
---

## 用意

- linux 系統中，有 `EXPORT_SYMBOL`，其中的用意是為了在撰寫程式時能夠方便呼叫這些副程式[^2]，即**模組化**

[^2]:[EXPORT_SYMBOL解析](http://www.cnblogs.com/dyllove98/p/3186967.html)