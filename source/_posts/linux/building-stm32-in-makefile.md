---
title: STM32 Makefile 專案建置
tags: [STM32, Linux, makefile]
date: 2020-05-02 14:46:29
category: Linux
---
## 資料夾結構
第一步，建立三個資料夾 "System"、"OS" 及 "User"

```
.
|-- System
|
|-- OS
|
`-- User
```

- System 放驅動程式
- OS 就是作業系統（也可不用）
- User 放的是我們的專案

接著，在每個資料夾底下新增一個 makefile。

```
.
|-- System
|   `-- makefile
|
|-- OS
|   `-- makefile
|
|-- User
|   `-- makefile
|
`-- makefile
```

準備步驟的最後，將所有程式碼正確的擺放。如範例：

```
.
|-- System
|   |-- STM32
|   |   |-- src
|   |   `-- inc
|   |
|   |-- STM32F429
|   |   |-- src
|   |   `-- inc
|   |
|   |-- CMSIS
|   |   |-- src
|   |   `-- inc
|   |
|   `-- makefile
|
|-- OS
|   |-- src
|   |-- inc
|   `-- makefile
|
|-- User
|   |-- src
|   |-- inc
|   `-- makefile
|
`-- makefile
```

{% alert info %}
在這個例子，我們在 System 底下放了三個驅動，所以需要將程式碼分成三個資料夾。
當然你也可以直接全部放在一起是沒問題的。
{% endalert %}

最後的最後，別忘了在驅動的底下也加一個 makefile。

```
.
|-- System
|   |-- STM32
|   |   |-- src
|   |   |-- inc
|   |   `-- makefile
|   |
|   |-- STM32F429
|   |   |-- src
|   |   |-- inc
|   |   `-- makefile
|   |
|   |-- CMSIS
|   |   |-- src
|   |   |-- inc
|   |   `-- makefile
|   |
|   `-- makefile
|
|-- OS
|   |-- src
|   |-- inc
|   `-- makefile
|
|-- User
|   |-- src
|   |-- inc
|   `-- makefile
|
`-- makefile
```

---
下一步，來寫 makefile！

## GCC
在所有的 makefile（除了最上層）寫上以下程式碼：

```makefile =
TCPREFIX = arm-none-eabi-
CC       = $(TCPREFIX)gcc

CFLAGS 	= -c -Wall -fno-common -O0 -g -mthumb -mcpu=cortex-m4 -mfloat-abi=hard -mfpu=fpv4-sp-d16 --specs=nosys.specs

INCFLAG =\
-I. \
-Iinc

CFLAGS  += $(INCFLAG)

OBJDIR 	= obj

OBJS =\
$(OBJDIR)/src.o 

all: $(OBJS)

$(OBJDIR)/%.o: src/%.c | $(OBJDIR)
	@echo "bulid file: $<"
	$(CC) $(CFLAGS) -MMD -MF$(@:%.o=%.d) -o $@ $<

$(OBJDIR):
	@echo $(NOW) INFO Make new folder User/$(OBJDIR).
	mkdir -p $(OBJDIR)

clean:
	-rm -rf $(OBJDIR)/*.o 
	-rm -rf $(OBJDIR)/*.d
```

- 使用 `arm-none-eabi-gcc` 來進行編譯
- 為 `gcc` 加入一些設定，如浮點數處理器。
- 接著設定所有需要連結的 object file
- 將所有 `.c` 編譯成 `.o` 
- Target `all` 將會完成編譯所有檔案
- Target `clean` 可以清理所有 object file

接著告訴主要的 makefile 要去底下的 makefile 執行編譯

```makefile =
obj:
	$(MAKE) all -C System
	$(MAKE) all -C OS
	$(MAKE) all -C User
```

`-C` 意味著要去下層資料夾執行目標，所以第二行等同於：

```
cd ./System
make all
```

### Startup.o
Startup.o is needed, so we put in `User` directory, and don't put in `src`. Building code is similar:
```makefile
OBJS +=\
startup.o

Startup/startup.o: ./startup.s | $(OBJDIR)
	@echo "bulid file: $<"
	$(CC) $(CFLAGS) -MMD -MF$(@:%.o=%.d) -o $@ $<
```

And add a line at clean up.
```makefile
	-rm -rf *.o
```

This is a quick version about how object file build, next step we link them up into a `.bin` file.

## G++ (Linker)
We write linker field at `home` directory, so this code should put in `./makefile`
```makefile =
TCPREFIX = arm-none-eabi-
LD       = $(TCPREFIX)g++

LFLAGS  = -mcpu=cortex-m4 -mthumb -mfloat-abi=hard -mfpu=fpv4-sp-d16 -Os -T$(LDFILE) --specs=nosys.specs
LDFILE  = ./STM32F429ZI_FLASH.ld

OBJS =\
$(wildcard ./User/obj/*.o) \
$(wildcard ./System/*/obj/*.o) \
$(wildcard ./OS/obj/*.o) \
./User/startup.o

main.elf: $(OBJS) $(LDFILE)
	@echo "link file: $@"
	$(LD) $(LFLAGS) -o $@ $(OBJS)
```

- Line 6 to 10 combine all object files at different directories, we using `wildcard` to scan all files which match the regex.
- Line 12 to 14 linked things up.

### Objdump and Objcopy
And we dump a `.bin` file from `.elf`, this is which we flash into board.
```makefile =
TCPREFIX = arm-none-eabi-
CP       = $(TCPREFIX)objcopy
OD       = $(TCPREFIX)objdump

main.bin: obj main.elf
	@echo "copy file main.elf"
	$(CP) $(CPFLAGS) main.elf $@
	$(OD) $(ODFLAGS) main.elf > main.lst
```

## Openocd
Last things, flash binary file into board. We use [openocd](http://openocd.org/).
```makefile =
STM32FLASH 	= ./User/Startup/stm32.pl

run: build
	@echo "Flashing main.bin."
	$(STM32FLASH) main.bin 
	@echo "Finish flashing main.bin."
```

### stm32.pl
Openocd is using talnet to flash board, manually we can open a terminal and write down some commands. Or we can use a perl file like this. 
```pl =
#!/usr/local/bin/perl
# NOTE: needs libnet-telnet-perl package.
use Net::Telnet;
use Cwd 'abs_path';
 
my $numArgs = $#ARGV + 1;
if($numArgs != 1) {
    die( "Usage ./stm32.pl [main.bin] \n");
}

my $file = abs_path($ARGV[0]);

my $ip = '127.0.0.1';   # localhost
my $port = 4444;

my $telnet = new Net::Telnet (
    Port   => $port,
    Timeout=> 30,
    Errmode=> 'die',
    Prompt => '/>/');

$telnet->open($ip);

print $telnet->cmd('halt');
print $telnet->cmd('poll');
print $telnet->cmd('flash probe 0');
print $telnet->cmd('stm32f4x mass_erase 0');
print $telnet->cmd('flash write_image erase '.$file.' 0x08000000');
print $telnet->cmd('reset');
print $telnet->cmd('exit');

print "\n";
```

## All
Let combine together. This should write down at `./makefile`
```makefile
all: run

build: main.bin
```

So we write command `make all`, and it will call run, and run will call build, build will make all binary files. All things will be done.

---

This repo is a workspace build for my current project, you can learn more from this example.
- [GUI Workspace](https://github.com/luswdev/GUI-workspace)