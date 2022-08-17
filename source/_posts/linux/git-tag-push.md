---
title: Git Tag Push
tags: [Linux, Git, Tags]
date: 2022-08-17 14:12:01
category: Linux
---

# New

- New a tag with current HEAD [^1]
[^1]:[Git - 標籤 (git-scm.com)](https://git-scm.com/book/zh-tw/v2/Git-%E5%9F%BA%E7%A4%8E-%E6%A8%99%E7%B1%A4)

```bash
git tag -a 'tag_name'
git tag -a v1.0.0
```

- New a tag with specific commit

```bash
git tag -a 'tag_name' commit_SHA
git tag -a v1.0.0 fc30d37b
```

# Push

```bash
git push origin 'tag_name'
git push origin v1.0.0
```

- Push all local tag to remote

```bash
git push origin --tags
```

# Show

- List

```bash
git tag
```

- Check tag note and commits

```bash
git show 'tag_name'
git show v1.0.0
```
