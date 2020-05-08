---
title: 自訂網域名稱：Google Domain
tag: [hexo, CNAME]
date: 2018-12-13 11:17:28
category: Hexo
summary: 一整套的教學-在 google domain 上購買網域名稱，並自訂 github page 的網址。
---
## 購買網域
網路上已經有很多如何在 [Google Domain](https://domains.google.com/m/registrar/omuskywalker.com?hl=en#) 上買網域的文章了，在此就不特別贅述，放上我看的幾篇文章：
- [台灣用戶也能在 Google Domains 註冊購買網域名稱，詳細申請設定教學](https://free.com.tw/google-domains/)
- [[教學]如何用 Google Domains 買網址、註冊網域？ - 香腸炒魷魚](https://sofree.cc/google-domains/)

{% alert %}
比較特別的的地方是，現在只開放部分國家可使用，所以在填地址的時候，可以去 google map 搜尋隨便一個美國的地址，然後使用
{% endalert %}

## 設定 DNS
根據 Github 官方的說明，需設定 type `A` 的 IP 位址為
- 185.199.108.153
- 185.199.109.153
- 185.199.110.153
- 185.199.111.153

以及一個 CNAME，name 可以填任意字串，此字串就是你的 subdomain（像是我填 blog），如果不知道填什麼，可以填 www。最後你的畫面會長這樣：
![](https://i.imgur.com/OTJsgaX.png)
CNAME 的 data 請填 `你的 github ID`+`.github.io.`，注意最後有一個點

這些都設定完，之後你的網址就會變成 `subdomain.domain.com`

## 設定 github CNAME
Github 官方有提供 301 轉址功能，只要在網頁的 branch 下建立一個 `CNAME` 檔案，就會把舊網址轉址到新網址。你的 CNAME 應該要填以下內容：

```
subdomain.domain.com
```

其中 `subdomain` 與 `domain` 與自己的有關，像我的就是

```
blog.omuskywalker.com
```

如果你上面的 subdomain 設定為 www，而你的頂級網域（也就是你買的 domain 名字）沒有要給特別的網站用的話，也可以這樣寫：

```
domain.com
subdomain.domain.com
```

這麼一來不管是上面哪兩種，都會連到你的 blog

{% alert info %}
如果跟我一樣是用 hexo 的人，CNAME 請放在 /source 底下，這樣每次 `hexo d` 才不會被蓋掉
{% endalert %}

如果這些都有設定好，你的 github 應該會長這樣：
![](https://i.imgur.com/e5GzzZH.png)

大概過幾個小時，你的新網址就可以用了（大功告成）!

## 補充：HTTPS
Github page 有提供內建的 HTTPS，只要你的網站設定好一陣子（不會很久，一天內），會有這個選項可以按：
![](https://i.imgur.com/z9HdVbV.png)
按下去，就會獲得 HTTPS 了
