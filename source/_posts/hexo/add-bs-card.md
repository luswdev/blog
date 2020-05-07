---
title: Add Bootstrap Card Tags into Hexo 
tags: [Hexo, card, bootstrap, tag plugin]
date: 2020-04-23 21:11:09
category: Hexo
---
## Bootstrap Card

{% card %}
<!-- header  -->
card header content
<!-- endheader -->
card content
<!-- footer -->
card footer
<!-- endfooter -->
{% endcard %}

So this is a card given by [bootstrap](https://getbootstrap.com/docs/4.4/components/card/), we can add this into hexo just simply write some {% badge warning @HTML %} like this.

```html
<div class="card card-default">
    <div class="card-header">
        <p>card header content</p>
    </div>
    <div class="card-body">
        <p>card content</p>
    </div>
    <div class="card-footer">
        <p>card footer</p>
    </div>
</div>
```

But this kind of things is not like ++*Hexo*++ style, and hardly to modify.
Luckly, Hexo is giving us some api call [**tag plugin**](https://hexo.io/api/tag), so let made a tag to insert a card!

---
## Tag Plugin
So what is actually tag plugin is? This is the answer of Hexo doc.

{% alert %}
A tag allows users to quickly and easily insert snippets into their posts.
{% endalert%}

As you see, we can simply build a tag and it will render a block our want. A example is above. This **Bs Alert** block is write down in `.md` like this:
```
{% alert %}
blah blah
blah more
{% endalert%}
```

And this tag is named by *note*. OK, let we get started to build card tag.

---
## card Tag
### card.js
First things to do, new a file call *card.js* and put into `scripts` directory in current theme's directory:

```shell
$ touch /path/to/your/current/theme/dir/script/card.js
```

{% alert warning %}
if `scripts` directory is not existed, first new a directory.
{% codeblock lang:shell line_number:false %}
$ mkdir /path/to/your/current/theme/dir/script
{% endcodeblock %}
{% endalert%}

Then, write down this into lastest javascript file (card.js).

```js =
/**
 * card.js | global hexo script.
 *
 * Usage:
 *
 * {% card class %}
 * <!-- header -->
 * Any content (support inline tags too).
 *  <!-- endheader -->
 * 
 * Any content (support inline tags too).
 * 
 * <!-- footer -->
 * Any content (support inline tags too).
 *  <!-- endfooter -->
 * {% endcard %}
 *
 */

function cardContent (args, content) {
    var classes = args[0] || 'default';
    var textClass = (classes != 'default' && classes != 'light' ) ? ' text-white' : '';
    var rHeading = /<!--\s*header\s*-->\n([\w\W\s\S]*?)<!--\s*endheader\s*-->/g;
    var rFooter = /<!--\s*footer\s*-->\n([\w\W\s\S]*?)<!--\s*endfooter\s*-->/g;
    var heading = '';
    var footer = '';

    var returnVal = '<div class="card bg-' + classes + ' ' + textClass + ' mt-3 mb-3">';

    if (heading = rHeading.exec(content)) {
        content = content.replace(rHeading, '');
        returnVal += '<div class="card-header">' 
        returnVal += hexo.render.renderSync({text: heading[1], engine: 'markdown'}).trim() + '</div>';
    }

    if (footer = rFooter.exec(content)) {
        content = content.replace(rFooter, '');
        returnVal += '<div class="card-body">' + hexo.render.renderSync({text: content, engine: 'markdown'}).trim() + '</div>';
        returnVal += '<div class="card-footer">' + hexo.render.renderSync({text: footer[1], engine: 'markdown'}).trim() + '</div></div>';
    } else {
        returnVal += '<div class="card-body">' + hexo.render.renderSync({text: content, engine: 'markdown'}).trim() + '</div></div>';
    }

    return returnVal;
}
  
hexo.extend.tag.register('card', cardContent, { ends: true });
```

In line 47, we registed a tag name card, and next time we write something like below , it will show like bs card.

```
{% card %}
card stuff
{% endcard %}
```

---
### Classes
card has some [different styles](https://getbootstrap.com/docs/4.4/components/card/#background-and-color), we can use args to implement it.

{% card %}
Args is given by hexo tag api, we can pass argument to tag.
so we combine style name into bs classes, this is the code.
<!-- footer -->
{% codeblock lang:js %}
function cardContent (args, content) {
    var classes = args[0] || 'default';
    var textClass = (classes != 'default' && classes != 'light' ) ? ' text-white' : '';
    ...

    var returnVal = '<div class="card bg-' + classes + ' ' + textClass + ' mt-3 mb-3">';
    ...
}
{% endcodeblock %}
<!-- endfooter -->
{% endcard %}

{% card %}

{% card default %}
<!-- header-->
Default card
<!-- endheader -->
card content
{% endcard %}

{% card primary %}
<!-- header-->
Primary card
<!-- endheader -->
card content
{% endcard %}

{% card secondary %}
<!-- header-->
Secondary card
<!-- endheader -->
card content
{% endcard %}

{% card success %}
<!-- header-->
Success card
<!-- endheader -->
card content
{% endcard %}

{% card danger %}
<!-- header-->
Danger card
<!-- endheader -->
card content
{% endcard %}

{% card warning %}
<!-- header-->
Warning card
<!-- endheader -->
card content
{% endcard %}

{% card info %}
<!-- header-->
Info card
<!-- endheader -->
card content
{% endcard %}

{% card light %}
<!-- header-->
Light card
<!-- endheader -->
card content
{% endcard %}

{% card dark %}
<!-- header-->
Dark card
<!-- endheader -->
card content
{% endcard %}
<!-- footer -->
<figure class="highlight shell"><table><tbody><tr><td class="code"><pre><span class="line">{&#37; card default %}...{&#37; endcard %}</span><br><span class="line">{&#37; card primary %}...{&#37; endcard %}</span><br><span class="line">{&#37; card secondary %}...{&#37; endcard %}</span><br><span class="line">{&#37; card success %}...{&#37; endcard %}</span><br><span class="line">{&#37; card danger %}...{&#37; endcard %}</span><br><span class="line">{&#37; card warning %}...{&#37; endcard %}</span><br><span class="line">{&#37; card info %}...{&#37; endcard %}</span><br><span class="line">{&#37; card light %}...{&#37; endcard %}</span><br><span class="line">{&#37; card dark %}...{&#37; endcard %}</span><br></pre></td></tr></tbody></table></figure>
<!-- endfooter -->
{% endcard %}


---
### Content
This is how content show.

{% card %}
Content is given by hexo tag api, this is a string inside tag (of course markdown). We can use hexo render engine to show it.
<!-- footer -->
{% codeblock lang:js %}
function cardContent (args, content) {
    ...

    var returnVal = '<div class="card bg-' + classes + ' ' + textClass + ' mt-3 mb-3">';

    ...
    
    returnVal += '<div class="card-body">' + hexo.render.renderSync({text: content, engine: 'markdown'}).trim() + '</div></div>';
    
    ...

    return returnVal;
}
{% endcodeblock %}
<!-- endfooter -->
{% endcard %}

---
### header and Footer
card header and footer is also given in bs, so we can implement it.

{% card %}
<!-- header-->
**Card Header**
<!-- endheader -->
This is how header work.

1. First, you must put your header content between `<!-- header -->` and `<!-- endheader -->`.
2. In *card.js*, we use RegExp to find correct header content.
3. RegExp for header is line 1.
4. After finding, we render this stuff by using hexo render engine.

<!-- footer -->
{% codeblock lang:js %}
    var rheader = /<!--\s*header\s*-->\n([\w\W\s\S]*?)<!--\s*endheader\s*-->/g;
    if (heading = rHeading.exec(content)) {
        content = content.replace(rHeading, '');
        returnVal += '<div class="card-header">' 
        returnVal += hexo.render.renderSync({text: heading[1], engine: 'markdown'}).trim() + '</div>';
    }
{% endcodeblock %}
<!-- endfooter -->
{% endcard %}


{% card %}
<!-- header-->
**Card Footer**
<!-- endheader -->
This is how footer work.

1. First, you must put your header content between `<!-- footer -->` and `<!-- endfooter -->`.
2. In *card.js*, we use RegExp to find currect footer content.
3. RegExp for footer is line 1.
4. After finding, we render this stuff by using hexo render engine.

<!-- footer -->
{% codeblock lang:js %}
    var rFooter = /<!--\s*footer\s*-->\n([\w\W\s\S]*?)<!--\s*endfooter\s*-->/g;

    if (footer = rFooter.exec(content)) {
        content = content.replace(rFooter, '');
        returnVal += '<div class="card-body">' + hexo.render.renderSync({text: content, engine: 'markdown'}).trim() + '</div>';
        returnVal += '<div class="card-footer">' + hexo.render.renderSync({text: footer[1], engine: 'markdown'}).trim() + '</div></div>';
    }
{% endcodeblock %}
<!-- endfooter -->
{% endcard %}

---
### Remind
This card styling is using [Bootstrap](https://getbootstrap.com/docs/4.4/) v4.4, so you need to include bs stylesheet in `<head>`.

```html
<!-- Latest compiled and minified CSS -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.0/css/bootstrap.min.css">
```

---
## Example
```md
{% card %}
<!-- header  -->
card header content
<!-- endheader -->
card content
<!-- footer -->
card footer
<!-- endfooter -->
{% endcard %}
```

So this is the correct code I wrote in the top. card box in the tag of:
```
{% card %}
...
{% endcard %}
```

And header should put in:
```
<!-- header -->
...
<!-- endheader -->
```

Also footer should put in:
```
<!-- footer -->
...
<!-- endfooter -->
```

And this is support classes:
```
{% card default %}...{% endcard %}
{% card primary %}...{% endcard %}
{% card secondary %}...{% endcard %}
{% card success %}...{% endcard %}
{% card danger %}...{% endcard %}
{% card warning %}...{% endcard %}
{% card info %}...{% endcard %}
{% card light %}...{% endcard %}
{% card dark %}...{% endcard %}
```

If not given classes, it will also show default classes. So this two are equal.
```
{% card default %}...{% endcard %}
{% card %}...{% endcard %}
```

---

That is, how does a card work on hexo by using tag plugins. You can find source code about this website in [github](https://github.com/luswdev/luswdev.github.io/tree/auto_bk_matery) if you want to learn more.
