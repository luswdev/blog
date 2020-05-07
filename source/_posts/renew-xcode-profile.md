---
title: 在 Xcode 中為免費開發者帳戶重新建立憑證
tags: Xcode
date: 2020-03-23 11:54:33
category: Note
summary:
---
## 免費帳戶憑證
免費版開發者帳戶的憑證有效期限只有 7 天，可以參考此連結。
[免費開發帳號的 iOS App 命中注定只能活七天 !](https://medium.com/%E5%BD%BC%E5%BE%97%E6%BD%98%E7%9A%84-swift-ios-app-%E9%96%8B%E7%99%BC%E5%95%8F%E9%A1%8C%E8%A7%A3%E7%AD%94%E9%9B%86/%E5%85%8D%E8%B2%BB%E9%96%8B%E7%99%BC%E5%B8%B3%E8%99%9F%E7%9A%84-ios-app-%E5%91%BD%E4%B8%AD%E6%B3%A8%E5%AE%9A%E5%8F%AA%E8%83%BD%E6%B4%BB%E4%B8%83%E5%A4%A9-8fd2cc849bfb)

理論上來說，超過期限只要重新從 Xcode 安裝 App 就會自動重簽 (renew)，但如果在期限內想要直接延期呢？

## 建立新的憑證
如果想要直接延期，唯一的方法只有重新建立一個憑證：
- 從 Preference 中的 Accounts
![Preference > Accounts](https://i.imgur.com/JfTJGet.png)

- 選擇右下角的 Manage Certificates...
![黃框處](https://i.imgur.com/r5PAFur.png)

- 按下去，選擇左下角的 `+`
![黃框處](https://i.imgur.com/Q59J2Tj.png)

- 選擇 Apple Development
![](https://i.imgur.com/bSelwuz.png)

之後就會新增一個憑證，可以回去 Targets 看憑證的確更新了。
![紅字](https://i.imgur.com/9MsNUqd.png)

