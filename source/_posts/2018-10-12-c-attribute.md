---
title: C語言 attribute
date: 2018-10-12 13:11:49
tag: [C, attribute, note]
category: Note
---
有分三種： ① 對副函式的　② 對資料的　③ 對結構的

<i class="fa fa-bell" aria-hidden="true"></i> 註：**attribute** 是給 compiler 看的。

## 對結構的
### packed
C語言在宣告結構的時候，會對裡面的元素作對齊，給個例子：

```c
struct sample {
    int  memberA;
    char memberB[3];
    int  memberC;
}
```

<!-- more -->

如果我們手算此結構的大小的話，會是：
- A: 4 bytes;
- B: 3 bytes;
- C: 4 bytes;
- sample : 4 + 3 + 4 = 11 bytes

但實際上 compiler 出來 `sizeof(struct sample) = 12`<br>
這是因為 compiler 所有元素對齊，也就是把每一格切成 4 bytes，B 就會自動對齊成 4 bytes。<br>
也可以說是寫成：

```c
struct sample {
    int  memberA;
    char memberB[3];
    char padding;
    int  memberC;
};
```

在嵌入式系統中，不可浪費太多記憶體，所以需要使用 packed 屬性來告訴 compiler 不要幫我們對齊，`sizeof` 出來的結果就會如我們預期。

```c
struct sample {
    int  memberA;
    char memberB[3];
    int  memberC;
}__attribute__((packed));
```

### aligned
相反的，`aligned` 屬性就是告訴 compiler 幫我們對齊資料，可以指定對其的大小，如：
- `__attribute__((aligned(8)))`
- `__attribute__((aligned(16)))`

如果我們在上一個例子加上此屬性的話，`sizeof` 的結果將會不一樣。

```c
struct sample {
    int  memberA;
    char memberB[3];
    int  memberC;
}__attribute__((aligned(8)));
```

此時 `sizeof(struct sample) = 16`
也就是 A+B=7 bytes，沒有超過我們給定的 8，但加上 C 就會超過了，所以在 B 跟 C 中間塞個 1 byte 來對齊；然後在 C 的後面塞 4 bytes；也就是：

```c
struct sample {
    int  memberA;
    char memberB[3];
    char padding1;
    int  memberC;
    int  padding2;
}__attribute__((aligned(8)));
```

如果沒有加數字，compiler 會進行最佳化的對齊。
以此例子將以 4 對齊。

## 對資料的
- `__attribute__((aligned))`
- `__attribute__((packed))`

與結構道理相同，也是決定要不要對齊。

## aligned v.s. packed
- aligned: 速度快
- packed: 省記憶體

## 對副程式的

### noreturn
對於 void 的副程式，如果放在某個需要回傳值的副程式，理論上會需要回傳值，也就是 `return void`

```c
void exit();

int sample(int a){
    return a==0 ? a : exit();
}
```

但這是無效的操作，所以需要加上 `__attribute__((noreturn))`

```c
void exit()__attribute__((noreturn));

int sample(int a){
    return a==0? a : exit();
}
```

## 通用
### section
如果需要將特定的變數、結構或是副程式放到指定的記憶體位置，即可使用此屬性，用法如下：

```c
struct sample{
    int  some;
    char members;
    int  here;
}__attribute__((section("name")));
```

上述例子我們指定將 sample 結構放置於指定的記憶體區塊，名為 `"name"`。<br>
而 `"name"` 這個記憶體區塊則在 *linker script* 中命名。

```
SECTION
{
    . = 0x1000;
    .name : {
        AT(0x100000);
    }
}
```

對變數、副程式我們可以一樣指定連結的位址：

```c
int link_sample __attribute__((section("data")));

void sample_fun( )__attribute__((section("text")));
void sample_fun(){
    int do_nothing = 0;
    return do_nothing;
}
```

### section 補充
