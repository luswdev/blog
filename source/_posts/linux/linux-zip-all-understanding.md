---
title: Linux ZIP 全理解
tags: [Linux, Zip]
date: 2024-01-24 15:41:11
category: Linux
---

# 1. 初始化

首先，先建立基礎的 zip 檔

```bash
$ mkdir zip_root
$ echo "test" > zip_root/test.txt
$ zip -ry test.zip zip_root
	adding: zip_root/ (stored 0%)
  adding: zip_root/test.txt (stored 0%)
```

先來看一下 zip_root 裡面長怎樣:

```bash
$ tree zip_root
zip_root
└── test.txt

0 directories, 1 file
```

包出來的 test.zip:

```bash
$ unzip test.zip -d test
Archive:  test.zip
   creating: test/zip_root/
 extracting: test/zip_root/test.txt
```

```bash
$ tree test
test
└── zip_root
    └── test.txt

1 directory, 1 file
```

目前看起來就如預期，將整個 zip_root 包進 zip 裡了

# 2. 建立新檔案

這時，我們再建立一個 test.1.txt，來看看會發生什麼事

```bash
$ echo "test.1" > zip_root/test.1.txt
$ zip -ry test.zip zip_root
updating: zip_root/ (stored 0%)
updating: zip_root/test.txt (stored 0%)
  adding: zip_root/test.1.txt (stored 0%)
```

檢查一下 zip_root 與 test.zip:

```bash
$ tree zip_root
zip_root
├── test.1.txt
└── test.txt

0 directories, 2 files
```

```bash
$ rm -rf test
$ unzip test.zip -d test
Archive:  test.zip
   creating: test/zip_root/
 extracting: test/zip_root/test.txt
 extracting: test/zip_root/test.1.txt
```

```bash
$ tree test
test
└── zip_root
    ├── test.1.txt
    └── test.txt

1 directory, 2 files
```

So far, so good.

# 3. 修改 test.txt 的內容

接著嘗試修改其中一個檔案的內容:

```bash
$ echo "new test" > zip_root/test.txt
$ cat zip_root/test.txt
new test
```

```bash
$ zip -ry test.zip zip_root
updating: zip_root/ (stored 0%)
updating: zip_root/test.txt (stored 0%)
updating: zip_root/test.1.txt (stored 0%)
```

檢查一下 test.zip:

```bash
$ rm -rf test
$ unzip test.zip -d test
Archive:  test.zip
   creating: test/zip_root/
 extracting: test/zip_root/test.txt
 extracting: test/zip_root/test.1.txt
```

```bash
$ cat test/zip_root/test.txt
new test
```

Perfect.

# 4. 刪除一個檔案

最後來測試一下刪除 zip_root 的檔案，zip 裡面會不會也被刪除

```bash
$ rm zip_root/test.txt
$ zip -ry test.zip zip_root
updating: zip_root/ (stored 0%)
updating: zip_root/test.1.txt (stored 0%)
```

檢查一下 zip_root 與 test.zip:

```bash
$ tree zip_root
zip_root
└── test.1.txt

0 directories, 1 file
```

```bash
$ rm -rf test
$ unzip test.zip -d test
Archive:  test.zip
   creating: test/zip_root/
 extracting: test/zip_root/test.txt
 extracting: test/zip_root/test.1.txt
```

```bash
$ tree test
test
└── zip_root
    ├── test.1.txt
    └── test.txt

1 directory, 2 files
```

test.txt 並沒有被移除!!

看一下 zip 的說明:

```bash
$ zip -h2

Extended Help for Zip

See the Zip Manual for more detailed help

Zip stores files in zip archives.  The default action is to add or replace
zipfile entries.
```

- **The default action is to add or replace zipfile entries.** → 因此不會刪除也是正常的

再仔細看一下 zip 的說明

```bash
Deletion, File Sync:
  -d        delete files
  Delete archive entries matching internal archive paths in list
    zip archive -d pattern pattern ...
  Can use -t and -tt to select files in archive, but NOT -x or -i, so
    zip archive -d "*" -t 2005-12-27
  deletes all files from archive.zip with date of 27 Dec 2005 and later
  Note the * (escape as "*" on Unix) to select all files in archive

  -FS       file sync
  Similar to update, but files updated if date or size of entry does not
  match file on OS.  Also deletes entry from archive if no matching file
  on OS.
    zip archive_to_update -FS -r dir_used_before
  Result generally same as creating new archive, but unchanged entries
  are copied instead of being read and compressed so can be faster.
      WARNING:  -FS deletes entries so make backup copy of archive first
```

- `-FS` 似乎可以解決此問題: **Also deletes entry from archive if no matching file on OS.**

讓我們來試看看 `-FS`:

```bash
$ zip -ry -FS test.zip zip_root
deleting: zip_root/test.txt
```

```bash
$ rm -rf test
$ unzip test.zip -d test
Archive:  test.zip
   creating: test/zip_root/
 extracting: test/zip_root/test.1.txt
```

```bash
$ tree test
test
└── zip_root
    └── test.1.txt

1 directory, 1 file
```

`-FS` 完美的解決我們的問題!

# 結論

如果有刪除檔案的需求，而且不想要移除舊的 zip 檔的話，記得帶 `-FS`

{% alert info %}
不移除舊 zip 檔可以加快 zip pack 的速度，因此帶 `-FS` 看起來是比較好的做法
{% endalert %}
