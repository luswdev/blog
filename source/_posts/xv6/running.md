---

title: "XV6 - Running"
date: 2018-08-27 14:15:18 +0800
tag: [XV6, makefile, kernel]
category: XV6
---
## Makefile 解析

```makefile =
OBJS = \
	bio.o\
	console.o\
	exec.o\
	file.o\
	fs.o\
	ide.o\
	ioapic.o\
	kalloc.o\
	kbd.o\
	lapic.o\
	main.o\
	mp.o\
	picirq.o\
	pipe.o\
	proc.o\
	spinlock.o\
	string.o\
	swtch.o\
	syscall.o\
	sysfile.o\
	sysproc.o\
	timer.o\
	trapasm.o\
	trap.o\
	uart.o\
	vectors.o\

# Cross-compiling (e.g., on Mac OS X)
TOOLPREFIX = /usr/bin/i386-jos-elf-
# Using native tools (e.g., on X86 Linux)
# TOOLPREFIX = 
CC = $(TOOLPREFIX)gcc
AS = $(TOOLPREFIX)gas
LD = $(TOOLPREFIX)ld
OBJCOPY = $(TOOLPREFIX)objcopy
OBJDUMP = $(TOOLPREFIX)objdump
CFLAGS = -fno-builtin -O2 -Wall -MD -ggdb -m32
```
- `-fno-builtin`：不使用 C 中的內建函數。
- `-O2`：`-O` 表示最佳化的程度，數字越大越好，但會增加編譯時間。
- `-Wall`：Warm all 的意思，打開所有的警告。
- `-MD`：生成 .d（directory），等同於 `-M -MF file`。

{% alert info %}
`gcc -M file.c`[^1] 會將 file.c 有 include 的 .h （包含標準函式庫）關聯起來，即輸出為：
`file.o: file.c header.h stdio.h`
`gcc -M -MF file.c` 將 `-M` 的輸出存入 file.d 裡。
**註**：`-M` 會自動帶 `-E`，如果使用 `-MD` 替代 `-M -MF` 時則不會帶 `-E`。
{% endalert %}


- `-ggdb`：為 GDB 生成更多的 debug 資訊。 
- `-m32`：生成 32 位元的程式碼。

[^1]:[Linux Makefile 生成 .d 依赖文件及 gcc -M -MF -MP 等相关选项说明](https://blog.csdn.net/QQ1452008/article/details/50855810)

```makefile =39
CFLAGS += $(shell $(CC) -fno-stack-protector -E -x c /dev/null >/dev/null 2>&1 && echo -fno-stack-protector)
ASFLAGS = -m32
# FreeBSD ld wants ``elf_i386_fbsd''
LDFLAGS += -m $(shell $(LD) -V | grep elf_i386 2>/dev/null)
```

---

```makefile =43
XV6.img: bootblock kernel fs.img
	dd if=/dev/zero of=XV6.img count=10000
	dd if=bootblock of=XV6.img conv=notrunc
	dd if=kernel of=XV6.img seek=1 conv=notrunc
```
- 產生 XV6.img
- `dd` 指令將 `if`(input file) 複製到 `of`(output file)
- `count` 限制輸入的大小，單位為 blocks，一個 block 的大小由 `ibs=BYTES` 宣告（預設為 512）。
- `notrunc` 在輸出檔案時會比對輸入的檔案，只會輸出與輸入檔案不一樣的地方。
- `seek` 會略過數個 blocks 之後再輸出，block 大小由 `obs=BTYES` 宣告（預設為 512）。

{% alert warning %}
/dev/zero 為一個特殊檔案，讀取時會提供無限的空字元，44 行為生成一個大小為 10000（個 block）的空白檔案 XV6.img（或是把 XV6.img 的前 10000 個 block 清除。）
{% endalert %}

```makefile =47
bootblock: bootasm.S bootmain.c
	$(CC) $(CFLAGS) -O -nostdinc -I. -c bootmain.c
	$(CC) $(CFLAGS) -nostdinc -I. -c bootasm.S
	$(LD) $(LDFLAGS) -N -e start -Ttext 0x7C00 -o bootblock.o bootasm.o bootmain.o
	$(OBJDUMP) -S bootblock.o > bootblock.asm
	$(OBJCOPY) -S -O binary bootblock.o bootblock
	./sign.pl bootblock
```
- 建立 bootblock


|GCC||
| ---------|--|
| `-nostdinc` | 不要從根目錄開始搜尋檔案，要從 `-I` 指定的目錄開始|
| `-I.` | 指定現在的資料夾|
| `-c` | 對後面的檔案編譯（或組譯），但不做連接|

|LD||
|-|-|
| `-N` ||
| `-e` | 使用後面的名稱（start）作為入口|
| `-Ttext` | 將後面的位置作為輸出文件的起始位置（須為 16 進位）|
| `-o` | 用來指定 ld 生成的名稱|

|OBJDUMP||
|-|-|
| `-S` | 反組譯目標文件|

|OBJCOPY||
|-|-|
| `-S` |去除所有符號資訊|
| `-O binary` |輸出為二進位的文件|

{% alert info %}
反組譯的用意是為了 debug
{% endalert %}

```makefile =54
bootother: bootother.S
	$(CC) $(CFLAGS) -nostdinc -I. -c bootother.S
	$(LD) $(LDFLAGS) -N -e start -Ttext 0x7000 -o bootother.out bootother.o
	$(OBJCOPY) -S -O binary bootother.out bootother
	$(OBJDUMP) -S bootother.o > bootother.asm
```
- 啟動其他的 CPU

```makefile =59
initcode: initcode.S
	$(CC) $(CFLAGS) -nostdinc -I. -c initcode.S
	$(LD) $(LDFLAGS) -N -e start -Ttext 0 -o initcode.out initcode.o
	$(OBJCOPY) -S -O binary initcode.out initcode
	$(OBJDUMP) -S initcode.o > initcode.asm
```
- 建立 initcode

```makefile =64
kernel: $(OBJS) bootother initcode
	$(LD) $(LDFLAGS) -Ttext 0x100000 -e main -o kernel $(OBJS) -b binary initcode bootother
	$(OBJDUMP) -S kernel > kernel.asm
	$(OBJDUMP) -t kernel | sed '1,/SYMBOL TABLE/d; s/ .* / /; /^$$/d' > kernel.sym
```
- 建立 kernel

```makefile =68
tags: $(OBJS) bootother.S _init
	etags *.S *.c

vectors.S: vectors.pl
	perl vectors.pl > vectors.S
```
- 使用 vectors.pl 生成 vectors.S

```makefile =73
ULIB = ulib.o usys.o printf.o umalloc.o

_%: %.o $(ULIB)
	$(LD) $(LDFLAGS) -N -e main -Ttext 0 -o $@ $^
	$(OBJDUMP) -S $@ > $*.asm
	$(OBJDUMP) -t $@ | sed '1,/SYMBOL TABLE/d; s/ .* / /; /^$$/d' > $*.sym
```
|特殊符號| |
|-|-|
|%|萬用字元，如 `_a` 需對應 `a.o`|
|$@|代表工作目標，即 `_%`|
|$^|代表所有必要條件，即 `%.o $(ULIB)`|
|$*|代表第一個必要條件，但不包含副檔名|

```makefile =79
_forktest: forktest.o $(ULIB)
	# forktest has less library code linked in - needs to be small
	# in order to be able to max out the proc table.
	$(LD) $(LDFLAGS) -N -e main -Ttext 0 -o _forktest forktest.o ulib.o usys.o
	$(OBJDUMP) -S _forktest > forktest.asm

mkfs: mkfs.c fs.h
	gcc $(CFLAGS) -Wall -o mkfs mkfs.c

UPROGS=\
	_cat\
	_echo\
	_forktest\
	_grep\
	_init\
	_kill\
	_ln\
	_ls\
	_mkdir\
	_rm\
	_sh\
	_usertests\
	_wc\
	_zombie\

fs.img: mkfs README $(UPROGS)
	./mkfs fs.img README $(UPROGS)
```

- fs 指的是 file system

```makefile =106
-include *.d

clean: 
	rm -f *.tex *.dvi *.idx *.aux *.log *.ind *.ilg \
	*.o *.d *.asm *.sym vectors.S parport.out \
	bootblock kernel XV6.img fs.img mkfs \
	$(UPROGS)

# make a printout
FILES = $(shell grep -v '^\#' runoff.list)
PRINT = runoff.list $(FILES)

XV6.pdf: $(PRINT)
	./runoff

print: XV6.pdf

# run in emulators

bochs : fs.img XV6.img
	if [ ! -e .bochsrc ]; then ln -s dot-bochsrc .bochsrc; fi
	bochs -q

qemu: fs.img XV6.img
	qemu -parallel stdio -hdb fs.img XV6.img

qemutty: fs.img XV6.img
	qemu -nographic -smp 2 -hdb fs.img XV6.img

# CUT HERE
# prepare dist for students
# after running make dist, probably want to
# rename it to rev0 or rev1 or so on and then
# check in that version.

EXTRA=\
	mkfs.c ulib.c user.h cat.c echo.c forktest.c grep.c\
	kill.c ln.c ls.c mkdir.c rm.c usertests.c wc.c zombie.c\
	printf.c umalloc.c \
	README dot-bochsrc *.pl toc.* runoff runoff1 runoff.list\

dist:
	rm -rf dist
	mkdir dist
	for i in $(FILES); \
	do \
		grep -v PAGEBREAK $$i >dist/$$i; \
	done
	sed '/CUT HERE/,$$d' Makefile >dist/Makefile
	echo >dist/runoff.spec
	cp $(EXTRA) dist

dist-test:
	rm -rf dist
	make dist
	rm -rf dist-test
	mkdir dist-test
	cp dist/* dist-test
	cd dist-test; ../m print
	cd dist-test; ../m bochs || true
	cd dist-test; ../m qemu

# update this rule (change rev1) when it is time to
# make a new revision.
tar:
	rm -rf /tmp/XV6
	mkdir -p /tmp/XV6
	cp dist/* /tmp/XV6
	(cd /tmp; tar cf - XV6) | gzip >XV6-rev2.tar.gz
```

---
## Make
> 我本來是打算在 MacOX 的環境下 make XV6 的，但是如果要 make 的話需要有 `i386-jos-elf` 的相關工具，需另外安裝（[安裝方法](https://pdos.csail.mit.edu/6.828/2016/tools.html)）。由於我一直沒辦法安裝，最後只好裝 ubuntu 來 make。

- 在主目錄下輸入 `make` 指令
- 接著會多出兩個檔案：XV6.img、fs.img，我們使用 qemu 來運行 XV6。

---
## QEMU 

- 輸入以下代碼[^2]

```shell 
qemu-system-i386 -serial mon:stdio -hdb fs.img XV6.img -smp 1 -m 512
```
![QEMU](https://i.imgur.com/Fz4WKO1.png "Running XV6 in QEMU")

[^2]:[【學習 XV6 】在 Mac OSX 下運行 XV6](http://leenjewel.github.io/blog/2014/07/24/%5B%28xue-xi-xv6-%29%5D-zai-mac-osx-xia-yun-xing-xv6/)

{% alert warning %}
註：make 好了以後，在 MacOS 的環境下使用 QEMU 模擬器即可運行。
{% endalert %}

---
## File: Kernel.ld

```armasm =
/* Simple linker script for the JOS kernel.
   See the GNU ld 'info' manual ("info ld") to learn the syntax. */

OUTPUT_FORMAT("elf32-i386", "elf32-i386", "elf32-i386")
OUTPUT_ARCH(i386)
ENTRY(_start)

SECTIONS
{
	/* Link the kernel at this address: "." means the current address */
        /* Must be equal to KERNLINK */
	. = 0x80100000;
```
- 設定記憶體位置到 `0x8010000`（虛擬地址，分頁硬體會映射至實體位置 `0x0010000`）
- *bootmain.c* 將 ELF 指標指向 `0x100000` 接著將 kernel 讀入。

```armasm =13
	.text : AT(0x100000) {
		*(.text .stub .text.* .gnu.linkonce.t.*)
	}

	PROVIDE(etext = .);	/* Define the 'etext' symbol to this value */

	.rodata : {
		*(.rodata .rodata.* .gnu.linkonce.r.*)
	}

	/* Include debugging information in kernel memory */
	.stab : {
		PROVIDE(__STAB_BEGIN__ = .);
		*(.stab);
		PROVIDE(__STAB_END__ = .);
		BYTE(0)		/* Force the linker to allocate space
				   for this section */
	}

	.stabstr : {
		PROVIDE(__STABSTR_BEGIN__ = .);
		*(.stabstr);
		PROVIDE(__STABSTR_END__ = .);
		BYTE(0)		/* Force the linker to allocate space
				   for this section */
	}

	/* Adjust the address for the data segment to the next page */
	. = ALIGN(0x1000);

	/* Conventionally, Unix linkers provide pseudo-symbols
	 * etext, edata, and end, at the end of the text, data, and bss.
	 * For the kernel mapping, we need the address at the beginning
	 * of the data section, but that's not one of the conventional
	 * symbols, because the convention started before there was a
	 * read-only rodata section between text and data. */
	PROVIDE(data = .);

	/* The data segment */
	.data : {
		*(.data)
	}

	PROVIDE(edata = .);

	.bss : {
		*(.bss)
	}

	PROVIDE(end = .);

	/DISCARD/ : {
		*(.eh_frame .note.GNU-stack)
	}
}
```