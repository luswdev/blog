---
title: Hexo 註腳美化
copyright: true
date: 2018-08-31 20:52:05
categories: Hexo
tag: [Hexo, footnote, 註腳, 美化, note]
description: 為 Hexo 加入註腳，並將註腳區塊套用 note 樣式。
images: https://i.imgur.com/e1vVA1u.png
---
## 安裝套件
首先， hexo 預設不支援註腳，因此需安裝套件 [hexo-reference](https://github.com/kchen0x/hexo-reference)

## 修改 Javascript
修改 /node_modules/hexo-reference/src/footnotes.js</br>
在倒數 15 行左右，找到 `if (footnotes.length)` ，加入以下程式碼。

```javascript diff:true :/node_modules/hexo-reference/src/footnotes.js
    // add footnotes at the end of the content
    if (footnotes.length) {
        text += '<div id="footnotes">';
        text += '<hr>';
        text += '<div id="footnotelist">';
    +   text += '<i class="fa fa-plus-circle footnotes-before"></i>';
    +   text += '<strong>參考資料</strong>';
        text += '<ol style="list-style: none; padding-left: 0; margin-left: 40px">' + html + '</ol>';
        text += '</div></div>';
    }
```

接著修改 custom.styl，新增以下描述

```stylus :custom.styl
#footnotelist {
  background-color:  ighten($gainsboro, 65%);
  background-color:  $note-primary-bg;
  border:            initial;
  padding:           15px;
  border-left:       3px solid $gainsboro;
  border-radius:     unit(hexo-config('note.border_radius'), px) if hexo-config('note.border_radius') is a 'unit';
  border-left-color: $note-primary-border;
}

#footnotelist i {
  color :            $note-primary-text;
  top:               13px;
  left:              15px;
  padding-right:     15px;
  font-size:         larger;
}
```
### 完成！
![result](https://i.imgur.com/e1vVA1u.png "註腳區塊")