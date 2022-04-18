---
title: "計概 - 抽象資料型態與副程式"
date: 2018-02-08 10:18:16 +0800
tag: 計算機概論
category: 計算機概論
---
##  抽象資料型態
Abstract data type, ADT，就是一種容器。

##  堆疊 Stack
為LIFO(Last In First Out)，後進先出，即當我們要放資料時，放在最上面（也就是最後一個），要拿資料的時候也是拿最上面。

![stack](https://i.imgur.com/5WjOSt2.png "堆疊")

- 插入 Push 
- 刪除 Pop



---
##  佇列 Queue
為FIFO(First In First Out)，先進先出，即當我們要放資料時，排在最後面 rear，拿資料時拿第一個 front；類似於排隊。

![queue](https://i.imgur.com/TT68nAV.png "佇列")

---
##  串列
項目是同性質的、線性的、可變長度的；可由陣列實現。

- 也可視為鍵結結構，基於節點 node的概念，一個接著下一個。

---
##  樹 Tree
### 二元樹
每個node有兩個後繼節點，稱作子結點 children，可一直延續下去。起始節點稱作樹根 root。<br>
一個node 可能有0至2個節點，左邊的稱作 left child，右邊的稱作 right child；如有一個節點沒有子結點，則稱作樹葉 leaf。

![二元樹](https://i.imgur.com/B9nhAz7.png "二元樹")

### 二元搜尋樹 BST
如果 left child < node < right child，則此數為BST。

![二元搜尋樹](https://i.imgur.com/YJ2kFFZ.png "二元搜尋樹")

---
##  圖形 Graph
G=(V,E)，V 為圖形中的點集合，E 為編集合。<br>
如果邊有方向，稱作有向圖，反之，則為無向圖。<br>
若兩個邊之間存在一條邊，則稱作相鄰頂點。<br>
兩點之間的路徑由一連串的相鄰頂點組成。

### 圖形演算法
- 深度優先搜索 BFS
- 廣度優先搜索 DFS
- 最短路徑搜索

---
##  副程式
```c 
swap(num1,num2)
temp ← num1
num1 ← num2
num2 ← temp
```