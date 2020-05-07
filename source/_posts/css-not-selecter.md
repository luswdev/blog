---
title: CSS3選擇器 :not()
tags: css
date: 2020-01-29 14:03:34
category: Note
summary: 
---
## 用途
假設有多個一樣 `label` 的元素要套用一種樣式，但又有幾個需要排除在外時使用。

### 例子

{% card %}
<style>
.ex-box {
    text-align: center;
}
.ex {
    color: #f00;
    font-style: italic;
    font-weight: bold;
}
</style>

<div class="ex-box"><span class="ex">1</span>
    <span class="ex">2</span>
    <span class="ex">3</span>
    <span class="ex">4</span>
</div>

<!-- footer -->
{% codeblock lang:html line_number:false %}
<div class="ex-box">
    <span class="ex">1</span>
    <span class="ex">2</span>
    <span class="ex">3</span>
    <span class="ex">4</span>
</div>
{% endcodeblock %}

{% codeblock lang:css line_number:false %}
.ex-box {
    text-align: center;
}
.ex {
    color: #f00;
    font-style: italic;
    font-weight: bold;
}
{% endcodeblock %}

<!-- endfooter -->
{% endcard %}

{% alert info %}
可以看到上面：1~4 都有*斜體*跟**粗體**，為了方便觀察，這裡讓文字變為紅色
{% endalert%}

---
{% card %}
<!-- header -->
接著將 3 號加上 `not-ex-ignored` 的 id，並將此 id 略過（使用 `:not` 選擇器）
<!-- endheader -->

<style>
.not-ex-box {
    text-align: center;
}
.not-ex:not(#not-ex-ignored) {
    color: #f00;
    font-style: italic;
    font-weight: bold;
}
</style>

<div class="not-ex-box"><span class="not-ex">1</span>
    <span class="not-ex">2</span>
    <span class="not-ex" id="not-ex-ignored">3</span>
    <span class="not-ex">4</span>
</div>

<!-- footer -->
{% codeblock lang:html line_number:false %}
<div class="not-ex-box">
    <span class="not-ex">1</span>
    <span class="not-ex">2</span>
    <span class="not-ex" id="not-ex-ignored">3</span>
    <span class="not-ex">4</span>
</div>
{% endcodeblock %}

{% codeblock lang:css line_number:false %}
.not-ex-box {
    text-align: center;
}
.not-ex:not(#not-ex-ignored) {
    color: #f00;
    font-style: italic;
    font-weight: bold;
}
{% endcodeblock %}
<!-- endfooter -->
{% endcard %}

{% alert info %}
如此一來，就只有三號沒有套用到屬性。
{% endalert%}