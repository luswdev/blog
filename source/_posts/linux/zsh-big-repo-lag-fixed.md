---
title: 解決 ZSH 在大型 Repo 下會卡頓的問題
tags: [zsh, git, oh-my-zsh]
date: 2023-03-27 00:13:48
category: Linux
---
> 有些 oh-my-zsh theme 會顯示 git prompt，在大型 repo 下檢查是否有改變時會造成卡頓。

- 為解決此問題，設定 flags 給 oh-my-zsh [^1]
[^1]: [oh-my-zsh slow, but only for certain Git repo - Stack Overflow](https://stackoverflow.com/questions/12765344/oh-my-zsh-slow-but-only-for-certain-git-repo)

```bash
git config --add oh-my-zsh.hide-status 1
git config --add oh-my-zsh.hide-dirty 1
```

- 也可以直接設定 global，預設所有 repo 都不檢查

```bash
git config --global --add oh-my-zsh.hide-status 1
git config --global --add oh-my-zsh.hide-dirty 1
```

# Unset

- 如果要復原的話，移除這些 config 即可

```bash
git config --unset oh-my-zsh.hide-status 1
git config --unset oh-my-zsh.hide-dirty 1
```
