---
title: Building STM32 in Makefile
tags: [STM32, Linux, makefile]
date: 2020-05-02 14:46:29
category: Linux
---
## Directories Structure
First, we create 3 directories named "System", "OS" and "User".
```
.
|-- System
|
|-- OS
|
`-- User
```
- System is for driver
- OS of course for OS
- and User is for your own application

Second, we add 4 makefiles into each directory (obtained base directory).
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

And last step of preparing, put all source codes into correct place. This is example:
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
In this example, we put three driver for STM32, so we separate source codes into 3 directory. Also you don't need to do exactly same as me, just put those code into `src` and `inc` is fine.
{% endalert %}

Finally, don't forget add a makefile into each driver directory:
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
Next, let write some makefiles!

## GCC
In Every Source directory (which only contain `inc` and `src`), we write some makefile like this:
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

- Line 1 and 2 told makefile to use `arm-none-eabi-gcc` to complete object work.
- Line 4 told makefile which flag we use in `gcc`.
- Line 6 to 8 told which directory we need to include. (If other directory needed, add line here too.)
- Line 12 to 15 told which file to object, put all `.c` file location at `src` directory and rename to `.o`.
- Line 17 give `all` target to build all object file.
- Line 19 to 21 build all object file, this is a action after `all` called.
- If `obj` directory is not existed, new it at line 23 to 25.
- Line 27 to 29 helps us clean object files.

And next, we need to told main makefile to build all, which seperated at different directories. So we need to exec make all at correct directory.
```makefile =
obj:
	$(MAKE) all -C System
	$(MAKE) all -C OS
	$(MAKE) all -C User
```

This should write down at `./makefile`, `-C` flag tell makefile exec previous target at given directory. So line 2 is same as:
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