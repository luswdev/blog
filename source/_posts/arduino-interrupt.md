---
title: 在 Arduino 上使用中斷
tags: [Arduino, interrupt]
date: 2019-12-26 23:36:34
category: Note
summary: 學習在 Arduino 上對一個 pin 建立中斷服務程式 (ISR)
---
## 新增中斷

```arduino
attachInterrupt(digitalPinToInterrupt(pin), ISR, mode);
attachInterrupt(interrupt, ISR, mode);
attachInterrupt(pin, ISR, mode);
```

- 有三種可選：
    * 第一個參數代表幾號中斷或是幾號 pin，通常用第一種寫法最保險
    * 第二個參數放 ISR，當中斷發生時要做的事
    * 第三個參數為發生中斷的模式，下面有詳細介紹

### 模式
- LOW：當 pin 處於低電位的時候觸發中斷
- RISING：當 pin 從低電位轉為高電位時觸發中斷
- FALLING：當 pin 從高電位轉為高電位時觸發中斷
- CHANGE：當 pin 的電位發生改變時觸發中斷
- HIGH：當 pin 處於高電位時觸發中斷（只適用 arduino due）

## 移除中斷

```arduino
detachInterrupt(digitalPinToInterrupt(pin));
detachInterrupt(interrupt);
detachInterrupt(pin);
```

- 一樣有三個寫法，與新增中斷的第一個參數相同。

## 關閉/開啟中斷

```arduino
noInterrupts();
interrupts();
```

{% alert warning %}
`noInterrupts` 不會將 `reset` 中斷關閉。
{% endalert %}
