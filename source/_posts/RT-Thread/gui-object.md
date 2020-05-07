---
title: RT-Thread GUI - Object
tag: [RT-Thread, GUI, kernel]
date: 2019-01-01 15:08:37
category: RT-Thread GUI
summary: GUI 中的物件最下層，為了實現如 C++ 的 class 所創造出來的產物
---
## RT-Thread GUI 物件架構
在 RTGUI 中，最小的物件為 widget，再來是 window，window 也是一個 widget；而每個 widget 也是一個 object，這是為了仿造 C++ 的物件導向所設計的，相同的概念我們在 RT-Thread 中已經看過許多次了，在 GUI engine 中也是相同的設計，其中在 object 結構中又串在 type 的結構上，type 中定義了兩個函式：`constructor` 與 `destructor`，在 C++ 的 class 中，常使用 `init` 函式來初始化新建的 class，這裡的 `constructor` 與 `destructor` 即用來初始化新建立的物件，及在刪除物件時，釋放該釋放的記憶體。[^1]

[^1]:[RTGUI粗讲（个人见解篇之三、RTGUI WIDGET （2））](https://blog.csdn.net/xuzhenglim/article/details/11883351)

---
## 結構

{% alert success %}
**File:** rtgui_object.h
{% endalert %}

```c=121
/* rtgui base object */
struct rtgui_object
{
    /* object type */
    const rtgui_type_t *type;

    /* the event handler */
    rtgui_event_handler_ptr event_handler;

    enum rtgui_object_flag flag;

    rt_uint32_t id;
};
```

```c=52
/* rtgui type structure */
struct rtgui_type
{
    /* type name */
    char *name;

    /* parent type link */
    const struct rtgui_type *parent;

    /* constructor and destructor */
    rtgui_constructor_t constructor;
    rtgui_destructor_t destructor;

    /* size of type */
    int size;
};
```

---
## 定義物件類型
RTGUI 設計了一個巨集函數來定義不同的物件，如下：

```c=76
#define DEFINE_CLASS_TYPE(type, name, parent, constructor, destructor, size) \
	const struct rtgui_type _rtgui_##type = { \
	name, \
	parent, \
	RTGUI_CONSTRUCTOR(constructor), \
	RTGUI_DESTRUCTOR(destructor), \
	size }; \
	const rtgui_type_t *_rtgui_##type##_get_type(void) { return &_rtgui_##type; } \
	RTM_EXPORT(_rtgui_##type##_get_type)
```

`##` 為連字符，在[RT-Thread 理解 RTM_EXPORT](/rt-thread-RTM-EXPORT)裡有提過了，基本上就是填入值進去結構體

---
## 建立物件

{% alert success %}
**File:** rtgui_object.c
{% endalert %}

| 功能 | 回傳值 | `*object_type` |
| --- | ------ | -------------- |
| 建立物件 | 物件指標 | 要建立的物件種類 |

```c=110
/**
 * @brief Creates a new object: it calls the corresponding constructors
 * (from the constructor of the base class to the constructor of the more
 * derived class) and then sets the values of the given properties
 *
 * @param object_type the type of object to create
 * @return the created object
 */
rtgui_object_t *rtgui_object_create(const rtgui_type_t *object_type)
{
    rtgui_object_t *new_object;

    if (!object_type)
        return RT_NULL;

    new_object = rtgui_malloc(object_type->size);
    if (new_object == RT_NULL) return RT_NULL;

#ifdef RTGUI_OBJECT_TRACE
    obj_info.objs_number ++;
    obj_info.allocated_size += object_type->size;
    if (obj_info.allocated_size > obj_info.max_allocated)
        obj_info.max_allocated = obj_info.allocated_size;
#endif

    new_object->type = object_type;

    rtgui_type_object_construct(object_type, new_object);

    return new_object;
}
RTM_EXPORT(rtgui_object_create);
```

建立物件相當簡單，透過欲建立的物件類型所定意義的 `construct` 函數來建立，其中 `rtgui_type_object_construct` 會呼叫正確的建立函式來初始化資料。

---

| 功能 | 回傳值 |
| --- | ------ |
| 呼叫正確的 `construct` 函式來初始化物件 | void |

| `*type` | `*object` |
| ------- | --------- |
| 欲初始化的物件類型 | 物件本體 |

```c=54
void rtgui_type_object_construct(const rtgui_type_t *type, rtgui_object_t *object)
{
    /* construct from parent to children */
    if (type->parent != RT_NULL)
        rtgui_type_object_construct(type->parent, object);

    if (type->constructor)
        type->constructor(object);
}
```

如果欲建立的物件類型在某一個物件類型的底下，如 window 之於 widget，則先呼叫在上層的 `construct`；接著呼叫自己的 `construct` 來完成建立的動作。

---
再仔細的看一下 "object" 的 `construct` 函式，其動作為：填入 vaild 的旗標，並將 id 填入 object 的記憶體指標；以上動作在 `_rtgui_object_constructor` 完成

| 功能 | 回傳值 | `*object` |
| --- | ------ | --------- |
| "object" 建立函式 | void | 要建立的物件 |

```c=28
static void _rtgui_object_constructor(rtgui_object_t *object)
{
    if (!object)
        return;

    object->flag = RTGUI_OBJECT_FLAG_VALID;
    object->id   = (rt_uint32_t)object;
}
```

---
## 刪除物件

| 功能 | 回傳值 | `*object` |
| --- | ------ | --------- |
| 刪除物件 | void | 要刪除的物件 |

```c=143
/**
 * @brief Destroys the object.
 *
 * The object destructors will be called in inherited type order.
 *
 * @param object the object to destroy
 */
void rtgui_object_destroy(rtgui_object_t *object)
{
    if (!object || object->flag & RTGUI_OBJECT_FLAG_STATIC)
        return;

#ifdef RTGUI_OBJECT_TRACE
    obj_info.objs_number --;
    obj_info.allocated_size -= object->type->size;
#endif

    /* call destructor */
    RT_ASSERT(object->type != RT_NULL);
    rtgui_type_destructors_call(object->type, object);

    /* release object */
    rtgui_free(object);
}
RTM_EXPORT(rtgui_object_destroy);
```

這裡一樣透過 `rtgui_type_destructors_call` 來呼叫正確的 `destruct` 函式，`destruct` 負責釋放該釋放的記憶體；最後透過 `rtgui_free` 釋放整個物件。`regui_free` 則簡單的呼叫 `rt_free` 釋放記憶體，我們在前幾篇文章有討論過了（[mempool](/rt-mem#Code-free)、[memheap](/rt-memheap#釋放記憶體)、[small mem](/rt-small-mem#釋放記憶體)、[slab](/rt-slab#釋放記憶體)）

---

| 功能 | 回傳值 |
| --- | ------ |
| 呼叫正確的 `destructor` 函式來清除物件 | void |

| `*type` | `*object` |
| ------- | --------- |
| 欲清除的物件類型 | 物件本體 |

```c=64
void rtgui_type_destructors_call(const rtgui_type_t *type, rtgui_object_t *object)
{
    /* destruct from children to parent */
    if (type->destructor)
        type->destructor(object);

    if (type->parent)
        rtgui_type_destructors_call(type->parent, object);
}
```

同樣的如果此物件類型是在某個物件類型的底下，先呼叫上層的 `destruct`；接著呼叫自己的 `desturct` 完成清除的動作。

---
最後來看一下 "object" 的刪除函式：填入 none 的旗標，並將物件種類設為 NULL；動作在 `_rtgui_object_destructor` 完成

| 功能 | 回傳值 | `*object` |
| --- | ------ | --------- |
| "object" 的刪除函式 | void | 欲清除的物件 |

```c=37
/* Destroys the object */
static void _rtgui_object_destructor(rtgui_object_t *object)
{
    /* Any valid objest should both have valid flag _and_ valid type. Only use
     * flag is not enough because the chunk of memory may be reallocted to other
     * object and thus the flag will become valid. */
    object->flag = RTGUI_OBJECT_FLAG_NONE;
    object->type = RT_NULL;
}
```
