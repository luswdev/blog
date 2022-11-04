---
title: Git 查看未 Push 的 Commit
tags: [Linux, Git, Push, Commit]
date: 2022-11-05 00:29:29
category: Linux
---

# 查看數量

```bash
git status
```

```bash
On branch master
Your branch is ahead of 'origin/master' by 1 commit.
```

## 查看 Commit 資訊

```bash
git cherry -v
```

```bash
+ a342c7b8c62c167de7af8bb8814eff7439b6ae15 add. initial commit
```