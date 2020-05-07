---
title: C 語言 - 位元欄位
tags: [C語言, Bit field]
date: 2020-04-10 17:28:52
category: C語言
---
在 C 語言中，如果我們要對特定的 bit(s) 做操作的話，最直覺的方式是用 bit and（`&`）跟 bit or（`|`）：

```c
int bit_sample = 0x0123; /* 0000 0001 0010 0011 */

/* 將第 2 個 bit 改成 1 */
bit_samplee |= (0x1 << 2); /* 0000 0001 0010 0011 */

/* 將第 5 個 bit 改成 1 */
bit_samplee |= ~(0x1 << 5); /* 0000 0001 0010 0011 */
```

從上面的例子可以看到如何使用 bit and/or 來操作特定的 bit，這種方式對於單一個 bit 並不會太麻煩，但有以下缺點：
- 無法覆用：這種方式不能快速地建立一個方法，也比較不好理解
- 對於區間上就不好使了

如果要解決上述缺點，有一個方式是使用 `union`，一個在嵌入式、驅動程式裡常常用到的方法。

---

## Union
`union` 是 C 語言裡面可以對一個結構裡面的元素，可以有不同的資料型態去理解，如以下例子：

```c
union sample {
    int sample_int;
    char sample_str[4];
};
```

>需要注意的是：同一時間內只能存取一個屬性，準確來說他們是共用一個記憶體區塊，所以改第一個值第二個值會同時更改。[^1]

[^1]:[[C 語言] 程式設計教學：如何使用聯合 (Union)](https://michaelchen.tech/c-programming/union/)

### struct
奇怪，不是在講 `union` 嗎，怎麼會提到 `struct` 呢？那是因為 C 裡面有一個有趣的東西叫做位元欄位[^2]，這個東西必須搭配結構使用；先看例子：

```c
struct bit_row {
    unsigned short row1 : 1;
    unsigned int row2 : 6;
    unsigned short row3 : 1;
};
```

乍看之下好像跟一般的結構差不多，可是我們注意到在每個元素宣告的結尾多了一個 `: 數字`，這是什麼意思？
1. 首先，`unsigned short` 在 64 位元裡大小是 4位，`unsigned int` 則是 8位
2. 加上 `: 數字`，這個東西就叫位元欄位，我們可以限制當前元素的大小
3. 因此，元素 1 的大小就被我們縮至 1 位，依此類推

[^2]:[位元欄](https://zh.wikipedia.org/wiki/%E4%BD%8D%E6%AE%B5)

---

有了上述的**工具**就可以建立一個好用而且好理解的位元操作方法！

```c
union method_ex {
    int real_val;
    struct bits {
        short bit0to2 : 3;
        short bit3and4 : 2;
        short bit5to7 : 3;
    };
}
```

建立好上面這個 union 後，如果要將某一個整數的第 3 到 4 位 的值改掉，可以這樣寫

```c
union method_ex int_ex;
int_ex.real_val = 0x0123; /* 0000 0001 0010 0011 */

/* change 3 and 4 bits to 01 */
int_ex.bits.bit3and4 = B01; /* 0000 0001 0010 1011 */
```

這樣的寫法，更簡單，更易懂。

---
### 剩下的空間
值得注意的一件事，我們沿用上面的例子；`method_ex` 有一個元素叫*真正的值*，他是一個整數（8 位），剛好另外一個元素我們使用位元欄位的技巧也控制在 8 位；但，如果我們沒有這麼做呢？

{% alert warning %}
答案是：你可以這麼寫，不會有什麼問題，但不建議。
{% endalert %}

#### 更大
如果 `bits` 結構今天大於 8 位，那我們就沒辦法透過更改*真正的值*來改變到高於 8 位的值，因此這麼做是**沒意義**的。

#### 更小
如果 `bits` 結構今天小於 8 位，這麼做完全不會有任何問題，但習慣上，我們會把它補齊，像是我們在 [attribute](/c-attribute) 篇裡提到的 padding。

```c
union method_ex {
    int real_val;
    struct bits {
        short bit0to2 : 3;
        short bit3and4 : 2;
        short bit5and6 : 2;
    };
}
```

{% alert info %}
沒有定義第 7 位
{% endalert %}

```c
union method_ex {
    int real_val;
    struct bits {
        short bit0to2 : 3;
        short bit3and4 : 2;
        short bit5and6 : 2;
        short notused : 1;
    };
}
```

{% alert info %}
隨便取名，只要有定義就好
{% endalert %}
