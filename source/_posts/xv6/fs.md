---
title: "XV6 - File System"
date: 2018-08-23 14:14:45 +0800
tag: [XV6, file system, kernel]
category: XV6
---
>本文沒有按照 XV6 官方文本的順序介紹

## 概觀 
- XV6 的檔案系統分為 6 層，本文將從上到下介紹。
![](https://i.imgur.com/yTmir5p.png "Layers of the XV6 file system")

---

## 檔案描述符

{% alert success %}
**File:** proc.h
{% endalert %}

- UNIX 大部分的資源都是一個文件，而此統一性由檔案描述符實現。
- 每個 process 都有一個表記錄著開啟的文件（行 13），一個開啟的文件即為 `struct file`

```c =37
// Per-process state
struct proc {
  uint sz;                     // Size of process memory (bytes)
  pde_t* pgdir;                // Page table
  char *kstack;                // Bottom of kernel stack for this process
  enum procstate state;        // Process state
  volatile int pid;            // Process ID
  struct proc *parent;         // Parent process
  struct trapframe *tf;        // Trap frame for current syscall
  struct context *context;     // swtch() here to run process
  void *chan;                  // If non-zero, sleeping on chan
  int killed;                  // If non-zero, have been killed
  struct file *ofile[NOFILE];  // Open files
  struct inode *cwd;           // Current directory
  char name[16];               // Process name (debugging)
};
```

{% alert success %}
**File:** file.h
{% endalert %}

```c =
struct file {
  enum { FD_NONE, FD_PIPE, FD_INODE } type;
  int ref; // reference count
  char readable;
  char writable;
  struct pipe *pipe;
  struct inode *ip;
  uint off;
};
```

{% alert success %}
**File:** proc.c
{% endalert %}

- 所有的 open files 皆被存放在 `ftable` 中：

```c
struct {
  struct spinlock lock;
  struct file file[NFILE];
} ftable;
```

---

{% alert success %}
**File:** file.c
{% endalert %}

### Code: filealloc、dup and close

| 功能 | 回傳值 |
| --- | ------ |
| 建立一個檔案 | 檔案結構 |

```c =25
// Allocate a file structure.
struct file*
filealloc(void)
{
  struct file *f;

  acquire(&ftable.lock);
  for(f = ftable.file; f < ftable.file + NFILE; f++){
    if(f->ref == 0){
      f->ref = 1;
      release(&ftable.lock);
      return f;
    }
  }
  release(&ftable.lock);
  return 0;
}
```

- `filealloc` 在 `ftable` 找到一個 `f->ref` 為零的，並返回。

---

| 功能 | 回傳值 | `*f` |
| --- | ------ | ---- |
| 再次一個檔案 | 開啟的檔案結構 | 欲開啟的檔案 |

```c =43
// Increment ref count for file f.
struct file*
filedup(struct file *f)
{
  acquire(&ftable.lock);
  if(f->ref < 1)
    panic("filedup");
  f->ref++;
  release(&ftable.lock);
  return f;
}
```

- `filedup` 增加引用次數。

---

| 功能 | 回傳值 | `*f` |
| --- | ------ | ---- |
| 關閉檔案 | void | 欲關閉的檔案 |

```c =55
// Close file f.  (Decrement ref count, close when reaches 0.)
void
fileclose(struct file *f)
{
  struct file ff;

  acquire(&ftable.lock);
  if(f->ref < 1)
    panic("fileclose");
  if(--f->ref > 0){
    release(&ftable.lock);
    return;
  }
```

- `fileclose` 減少引用次數。

```c =68
  ff = *f;
  f->ref = 0;
  f->type = FD_NONE;
  release(&ftable.lock);
  
  if(ff.type == FD_PIPE)
    pipeclose(ff.pipe, ff.writable);
  else if(ff.type == FD_INODE){
    begin_trans();
    iput(ff.ip);
    commit_trans();
  }
}
```

- 若引用次數降為 0 時，則依據類型的不同釋放當前的 pipe 或是 inode。

### Code: filestat、read and write

| 功能 | 回傳值 |
| --- | ------ |
| 讀取檔案狀態 | 0 (ok) / -1 (err) |

| `*f` | `*st` |
| ---- | ----- |
| 欲讀取的檔案 | 寫入狀態的結構 |

```c =83
// Get metadata about file f.
int
filestat(struct file *f, struct stat *st)
{
  if(f->type == FD_INODE){
    ilock(f->ip);
    stati(f->ip, st);
    iunlock(f->ip);
    return 0;
  }
  return -1;
}
```
- 須為 `inode` 才可使用 `filestat`，通過呼叫 `stati` 來實現操作。

---
<i class="fa fa-code"></i> Code: `fileread`

| 功能 | 回傳值 |
| --- | ------ |
| 讀取檔案 | 讀取大小 |

| `*f` | `*addr` | `n` |
| ---- | ------- | --- |
| 欲讀取的檔案 | 欲寫入資料的記憶體 | 欲寫入的大小 |

```c =95
// Read from file f.
int
fileread(struct file *f, char *addr, int n)
{
  int r;

  if(f->readable == 0)
    return -1;
  if(f->type == FD_PIPE)
    return piperead(f->pipe, addr, n);
  if(f->type == FD_INODE){
    ilock(f->ip);
    if((r = readi(f->ip, addr, f->off, n)) > 0)
      f->off += r;
    iunlock(f->ip);
    return r;
  }
  panic("fileread");
}
```

- `fileread` 針對不同類型有不同的操作：
    - pipe：呼叫 `piperead`，於 [ch5](https://omuskywalker.github.io/2018/08/14/ch5/#piperead) 介紹過。
    - inode：由 `readi` 完成動作，下面會介紹。

---

| 功能 | 回傳值 |
| --- | ------ |
| 寫入檔案 | 寫入大小 |

| `*f` | `*addr` | `n` |
| ---- | ------- | --- |
| 欲寫入的檔案 | 欲寫入的資料 | 欲寫入的大小 |

```c =115
//PAGEBREAK!
// Write to file f.
int
filewrite(struct file *f, char *addr, int n)
{
  int r;

  if(f->writable == 0)
    return -1;
  if(f->type == FD_PIPE)
    return pipewrite(f->pipe, addr, n);
  if(f->type == FD_INODE){
    // write a few blocks at a time to avoid exceeding
    // the maximum log transaction size, including
    // i-node, indirect block, allocation blocks,
    // and 2 blocks of slop for non-aligned writes.
    // this really belongs lower down, since writei()
    // might be writing a device like the console.
    int max = ((LOGSIZE-1-1-2) / 2) * 512;
    int i = 0;
    while(i < n){
      int n1 = n - i;
      if(n1 > max)
        n1 = max;

      begin_trans();
      ilock(f->ip);
      if ((r = writei(f->ip, addr + i, f->off, n1)) > 0)
        f->off += r;
      iunlock(f->ip);
      commit_trans();

      if(r < 0)
        break;
      if(r != n1)
        panic("short filewrite");
      i += r;
    }
    return i == n ? n : -1;
  }
  panic("filewrite");
}
```
- `filewrite` 針對不同類型有不同的操作：
    - pipe：呼叫 `pipewrite`，於 [ch5](https://omuskywalker.github.io/2018/08/14/ch5/#pipewrite) 介紹過。
    - inode：由 `writei` 完成動作，下面會介紹。

---

{% alert success %}
**File:** fs.c
{% endalert %}

| 功能 | 回傳值 |
| --- | ------ |
| 寫入檔案狀態 | void |

| `*ip` | `*st` |
| ---- | ----- |
| 欲讀取的檔案 | 寫入狀態的結構 |

```c =437
// Copy stat information from inode.
void
stati(struct inode *ip, struct stat *st)
{
  st->dev = ip->dev;
  st->ino = ip->inum;
  st->type = ip->type;
  st->nlink = ip->nlink;
  st->size = ip->size;
}
```

---

## Code: Path names
### namex

| 功能 | 回傳值 |
| --- | ------ |
| 從路徑尋找檔案 |  inode 結構 |

| `*path` | `nameiparent` | `*name` |
| ------- | ------------- | ------ |
| 路徑名 | - | - |

```c =620
// Look up and return the inode for a path name.
// If parent != 0, return the inode for the parent and copy the final
// path element into name, which must have room for DIRSIZ bytes.
static struct inode*
namex(char *path, int nameiparent, char *name)
{
  struct inode *ip, *next;

  if(*path == '/')
    ip = iget(ROOTDEV, ROOTINO);
  else
    ip = idup(myproc()->cwd);

  while((path = skipelem(path, name)) != 0){
    ilock(ip);
    if(ip->type != T_DIR){
      iunlockput(ip);
      return 0;
    }
    if(nameiparent && *path == '\0'){
      // Stop one level early.
      iunlock(ip);
      return ip;
    }
    if((next = dirlookup(ip, name, 0)) == 0){
      iunlockput(ip);
      return 0;
    }
    iunlockput(ip);
    ip = next;
  }
  if(nameiparent){
    iput(ip);
    return 0;
  }
  return ip;
}
```

---
## Code: 目錄
- 目錄的 inode type 為 `T_DIR`，存在 `struct dirent` 中。

{% alert success %}
**File:** file.
{% endalert %}

```c =53
struct dirent {
  ushort inum;
  char name[DIRSIZ];
};
```
- `inum` 為 inode 編號，為 0 的代表可用。

---

## Inode

---

## Logging

---

## Buffer
- buffer 有兩個任務：
    1. 同步硬碟，保證只會有一份拷貝放在記憶體，並只有一個 kernel thread 使用。
    2. 快取常用的 block 以提升性能 (*bio.c*);

### Code: Buffer cache

