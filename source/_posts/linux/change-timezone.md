---
title: How to Change Time Zone in Linux
tags: [Linux, timezone, timedatectl]
date: 2020-05-07 21:51:50
category: Linux
---

For default, time zone in linux is `UTC`. And if we rent a VPS, it will set to where mechine location.

But we want to show time as we location, thus we need to change time zone. So this is how we use: `timedatectl`.

{% alert info %}
This tool is used for Ubuntu, if your OS is not Ubuntu, maybe there will something different.
{% endalert %}

## timedatectl
This is a tool that can show, list, and change time zone in linux. 

### Listing
We can use `timedatectl` to list all time zone code like this.

```bash
$ timedatectl list-timezones
```

- Result will like this:
```bash
Aferica/...
...
```

We can use `grep` to find we want. For example, we can just show Asia result like this.

```bash
$ timedatectl list-timezones | grep Asia
```

### Show
`timedatectl` also can show current time zone detail.

```bash
$ timedatectl
```

- Result will like this

```bash	
Local time: Thu 2020-05-07 22:08:01 CST
Universal time: Thu 2020-05-07 14:08:01 UST
RTC time: Thu 2020-05-07 22:08:01
Time zone: Asia/Taipei (CST +8000)
System clock synchronized: yes
NTP service: active
RTC in local TZ: no
```

If we want to show setting, use `status`.

```bash
$ timedatectl status
```

- Result will like this

```bash
Timezone=Asia/Taipei
LocalRTC=no
CanNTP=yes
NTP=yes
NTPSynchronized=yes
TimeUSec=Thu 2020-05-07 22:08:01 CST
RTCTimeUSec=Thu 2020-05-07 22:08:01 CTS
```

### Changing
Changing time zone use `set-timezone`.

```bash
$ timedatectl set-timezone Zone
```

`Zone` should replace as you wonder to change.

After setting, you can show if success.
```bash
$ timedatectl show
```
