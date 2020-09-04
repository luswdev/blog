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
Startup.o 是必須的，把它放在 `User` 理，但不要放進 `src`；編譯他的道理是類似的

```makefile
OBJS +=\
startup.o

Startup/startup.o: ./startup.s | $(OBJDIR)
	@echo "bulid file: $<"
	$(CC) $(CFLAGS) -MMD -MF$(@:%.o=%.d) -o $@ $<
```

以上建置完成後，能快速的編譯所有程式。接著我們將所有 object file 連結成一個二進位檔。

## G++ (Linker)
連結的動作放在主要的 makefile 中。

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

- 使用 wildcard 掃描所有 object file
- 連結成一個 `.elf`

### Objdump and Objcopy
再將 `.elf` 轉成 `.bin` 燒錄。

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
最後一件事，燒錄；這裡使用 [openocd](http://openocd.org/)。
```makefile =
run: main.bin
	@echo $(YELLOW)"Flash $< into board..."$(RST)
	openocd -f $(OCDCFG)  				\
			-c "init"                   \
            -c "reset init"             \
            -c "stm32f2x unlock 0"      \
            -c "flash probe 0"          \
            -c "flash info 0"           \
            -c "flash write_image erase $< 0x8000000" \
            -c "reset run" -c shutdown
	@echo $(GREEN)"Finish flash $< into board."$(RST)
	@echo ""
```

## 總結
在主 makefile 寫下以下片段，將所有東西整合吧。

```makefile
all: run

build: main.bin
```

如此以來，只要下指令 `make all` 就會將所有該編譯的程式碼編譯完成，連結成一個二進位檔，最後燒盡板子。

---

這是我的一個專案建置的範例，可以參考。
- [GUI Workspace](https://github.com/luswdev/GUI-workspace)