---
title: Add Bootstrap badge Tags into Hexo 
tags: [Hexo, badge, bootstrap, tag plugin]
date: 2020-04-25 21:35:18
category: Hexo
---
## Boostrap badge
{% badge @badge content %}

So this is a badge given by [bootstrap](https://getbootstrap.com/docs/4.4/components/badge/), we can add this into hexo just simply write some {% badge warning @HTML %} like this.

```html
<span class="badge badge-secondary">badge content</span>
```

And yes, this is not like ++*Hexo*++ style, and hardly to modify.
So as [last post](/post/hexo/add-bs-card.html), let build a tags to insert a badge.

---
## Badge Tags
### badge.js
First things to do, new a file call *badge.js* and put into `scripts` directory in current theme's directory:

```shell
$ touch /path/to/your/current/theme/dir/script/badge.js
```

{% alert warning %}
if `scripts` directory is not existed, first new a directory.
{% codeblock lang:shell line_number:false %}
$ mkdir /path/to/your/current/theme/dir/script
{% endcodeblock %}
{% endalert%}

Then, write down this into lastest javascript file (badge.js).

```js =
/**
 * badge.js | global hexo script.
 *
 * Usage:
 *
 * {% badge [class]@Text %}
 *
 * [class] : primary | secondary | success | danger | warning | info | light | dark.
 *           If not defined, default class will be selected.
 */

function postBadge (args) {
    args = args.join(' ').split('@');
    var classes = args[0] || 'default';
    var text = args[1] || '';
  
    classes = classes.trim();
    !text && hexo.log.warn('badge text must be defined!');
  
    return '<span class="badge badge-' + classes + '">' + text + '</span>';
}
  
hexo.extend.tag.register('badge', postBadge, { ends: false });
```

In line 23, we registed a tag name badge, and next time we write something like below , it will show like bs badge.
```
{% badge @blah %}
```

### Different
Here is a trivial different point with card we wrote in `register`. 
```js
hexo.extend.tag.register('badge', postBadge, { ends: false });
```

In the third parameter, we write down `{ ends: false }`, this will tell hexo that this tag has no end tag. So our badge tag should write like this:
```
{% badge @blah %}
```

And carefully don't write something like this:
```js =
{% badge @blah %}
{% endbadge %}
```

{% alert danger %}
Line 2 should not be write down in post markdown file.
{% endalert%}

---
### Classes
badge has some [different styles](https://getbootstrap.com/docs/4.4/components/badge/#contextual-variations), we can use args to implement it.

{% card %}
Args is given by hexo tag api, we can pass argument to tag.
so we combine style name into bs classes, this is the code.
<!-- footer -->
{% codeblock lang:js %}
function postbadge (args) {
    args = args.join(' ').split('@');
    var classes = args[0] || 'secondary';
  
    ...
  
    return '<span class="badge badge-' + classes + '">' + text + '</span>';
}
{% endcodeblock %}
<!-- endfooter -->
{% endcard %}

{% card %}
{% badge primary @Primary %} {% badge secondary @Secondary %} {% badge success @Success %} {% badge danger @Danger %} {% badge warning @Warning %}  {% badge info @Info %} {% badge light @Light %}  {% badge dark @Dark %} 
<!-- footer-->
<figure class="highlight shell"><table><tbody><tr><td class="code"><pre><span class="line">{&#37; badge primary @Primary %}</span><br><span class="line">{&#37; badge secondary @Secondary %}</span><br><span class="line">{&#37; badge success @Success %}</span><br><span class="line">{&#37; badge danger @Danger %}</span><br><span class="line">{&#37; badge warning @Warning %}</span><br><span class="line">{&#37; badge info @Info %}</span><br><span class="line">{&#37; badge light @Light %}</span><br><span class="line">{&#37; badge dark @Dark %}</span><br></pre></td></tr></tbody></table></figure>
<!-- endfooter -->
{% endcard %}

---
### Content
This is how content show.

{% card %}
Content is given by hexo tag api, this is a string inside tag (of course markdown). We can use hexo render engine to show it.
<!-- footer -->
{% codeblock lang:js %}
function postbadge (args) {
    args = args.join(' ').split('@');

    ...

    var text = args[1] || '';

    ...
  
    return '<span class="badge badge-' + classes + '">' + text + '</span>';
}
{% endcodeblock %}
<!-- endfooter -->
{% endcard %}

---
### Remind
This badge styling is using [Bootstrap](https://getbootstrap.com/docs/4.4/) v4.4, so you need to include bs stylesheet in `<head>`.

```html
<!-- Latest compiled and minified CSS -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.0/css/bootstrap.min.css">
```

---
## Example
```md
{% badge @badge content %}
```

So this is the correct code I wrote in the top. badge class should given by first argument
```
{% badge primary @Stuff %} 
{% badge secondary @Stuff %} 
{% badge success @Stuff %} 
{% badge info @Stuff %} 
{% badge warning @Stuff %} 
{% badge danger @Stuff %}
{% badge light @Stuff %}
{% badge dark @Stuff %}
```

If not given classes, it will also show secondary classes. So this two are equal.
```
{% badge @Stuff %}
{% badge secondary @Stuff %}
```

And content should put after a `@`
```
{% badge @Content here %}
```
---

That is, how does a badge work on hexo by using tag plugins. You can find source code about this website in [github](https://github.com/luswdev/HackTeck/tree/master) if you want to learn more.