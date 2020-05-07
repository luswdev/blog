---
title: Hexo Theme Clean Document
tags: [hexo, theme-clean]
mathjax: true
date: 2020-04-29 13:03:03
category: Hexo
pin: true
---
> This is a theme base on [Hexo Clean Blog Theme](https://github.com/klugjo/hexo-theme-clean-blog), and modfy for more feature and coding style.

{% alert info %}
Get theme source on [github](https://github.com/luswdev/hexo-theme-clean)
{% endalert %}

## Features
- Cover image for posts and pages
- Post toc
- Code syntax highlighting
- MathJax support
- Bootstrap Alert, Card and Label support
- Responsive Images, table and codeblock
- Light Gallery support
- Disqus and Gitalk
- Google Analytics, Busuanzi Statistics and Word counts support
- Tags, Categories and About support
- Pin post at home page support
- Hexo local search 

## Installation
- Clone into your hexo blog directory
```
$ git clone https://github.com/luswdev/hexo-theme-clean.git themes/clean
```

Then update your blog's main `_config.yml` to set the theme to `clean`:

```
# Extensions
## Plugins: http://hexo.io/plugins/
## Themes: http://hexo.io/themes/
theme: clean
```

---

## Configuration
### Top Left Label

The top left label is configured in the theme's `_config.yml`. When clicked it will lead to the Home Page.

```
# Title on top left of menu. Leave empty to use main blog title
menu_title: Configurable Title
```

### Home Page cover image
The Home Page cover is configured in the theme's `_config.yml`. It will be the same for all index type pages.

```
# URL of the Home page image
index_cover: /img/home-bg.jpg
```

### Favicon image
The favicon is configured in the theme's `_config.yml`.

```
# Set your own favicon
favicon: /img/favicon.png
```


### Start Year
This will set archives page button group starting year, if not set, it will start at current year.
Also is footer copyright start year, too.

```
# Site start year
# Default with current year
start_year: 
```

### Google Analytics
The Google Analytics Tracking ID is configured in the theme's `_config.yml`. It allow us to learn about blog visitors.

```
# Google Analytics Tracking ID
google_analytics:
```

### Busuanzi
The Busuanzi is configured in the theme's `_config.yml`. It can record site traffic and visitors count.

```
# Busuanzi Statistics
busuanzi:
  enable: false
  cdn: //busuanzi.ibruce.info/busuanzi/2.3/busuanzi.pure.mini.js
  site_views: false
  site_visitors: false
  post_views: false
```

### Social Account

Setup the links to your social pages in the theme's `_config.yml`. Links are in the footer.

```
footer:
  social_link:
    twitter_url:
    twitter_handle:
    facebook_url:
    github_url: https://github.com/luswdev/hexo-theme-clean
    gitlab_url:
    linkedin_url: 
    mailto:
```

### New Tags page.

> Follow these steps to add a `tags` page that contains all the tags in your site.

- Create a page named `tags`

```
$ hexo new page "tags"
```

- Edit the newly created page and set page type to `tags` in the front matter.

```
title: All tags
type: "tags"
```

- Add `tags` to the menu in the theme `_config.yml`:

```
footer:
  menu:
    Home: /
    Tags: /tags
```

### Categories page.

> Follow these steps to add a `categories` page that contains all the categories in your site.

- Create a page named `categories`

```
$ hexo new page "categories"
```

- Edit the newly created page and set page type to `categories` in the front matter.

```
title: All categories
type: "categories"
```

- Add `Categories` to the menu in the theme `_config.yml`:

```
footer:
  menu:
    Home: /
    Categories: /categories
```

### New About page.

> Follow these steps to add a `about` page that can write some information about you.

- Create a page named `about`

```
$ hexo new page "about"
```

- Edit the newly created page and set page type to `about` in the front matter.

```
title: About
type: "about"
```

- Add `About` to the menu in the theme `_config.yml`:

```
footer:
  menu:
    Home: /
    About: /about
```

---

## Writing
### Default post title
The default post title (used when no title is specified) is configured in the theme's `_config.yml`.

```
# Default post title
default_post_title: Untitled
```

### Post word count
The post word count can show a post's word count and read time, you need to install plugin:
```
npm i --save hexo-wordcount
```

And configured in the theme's `_config.yml`.
```
# Post meta
post_info:
  word_count: true
  read_time: false
```

### Post's Excerpt
This theme does not support traditional excerpts. To show excerpts on the index page, use `subtitle` in the front-matter:

```
---
title: Post title
date: 2007-08-05 07:08:05
tags: tags
subtitle: Standard Excerpts are not supported in Clean Blog but you can use subtitles in the front matter to display text in the index.
---
```

### Post's Cover Image
By default, posts will use the home page cover image. You can specify a custom cover in the front-matter:

```
---
title: Post title
date: 2007-08-05 07:08:05
tags: tags
cover: /assets/contact-bg.jpg
---
```

### Author
The post's author is specified in the posts front-matter:

```
---
title: Post title
date: 2007-08-05 07:08:05
tags: tags
author: Klug Jo
---
```

### TOC
We implement TOC and back to top on the TOC menu, which is a dropup menu on the bottom right. Enable it in the theme's `_config.yml`.

```
# Enable post toc
toc: true
```

### Card Tag
You can insert a bs card by using tags, just write something like this:
```
{% card %}
I am a card.
{% endcard %}
```

{% card %}
I am a card.
{% endcard %}

#### Card header
Also you can put some title into header, just write something like this:
```
{% card %}
<!-- header -->
I am header.
<!-- endheader -->
I am a card.
{% endcard %}
```

{% card %}
<!-- header -->
I am header.
<!-- endheader -->
I am a card.
{% endcard %}

#### Card footer
Also you can put some words into footer, just write something like this:
```
{% card %}
I am a card.
<!-- footer -->
I am footer.
<!-- endfooter -->
{% endcard %}
```

{% card %}
I am a card.
<!-- footer -->
I am footer.
<!-- endfooter -->
{% endcard %}

#### Card style
There have 8 style for card, we have:
- primary, secondary, success, danger, warning, info, light, dark.

Just put classes into tag like this:
```
{% card success %}
I am success!
{% endcard %}
```

{% card success %}
I am success!
{% endcard %}

You can see all style in [Bootstrap doc](https://getbootstrap.com/docs/4.4/components/card/#background-and-color).

### Alert Tag
You can insert a bs alert by using tags, just write something like this:
```
{% alert %}
I am a alert
{% endalert %}
```

{% alert %}
I am a alert
{% endalert %}

#### Alert style
There have 8 style for alert, we have:
- primary, secondary, success, danger, warning, info, light, dark.

Just put classes into tag like this:
```
{% alert success %}
I am success!
{% endalert %}
```

{% alert success %}
I am success!
{% endalert %}

You can see all style in [Bootstrap doc](https://getbootstrap.com/docs/4.4/components/alerts/#examples).

### Badge Tag
You can insert a bs badge by using tags, just write something like this:
```
{% badge @new! %}
```

{% badge @new! %}


#### Badge style
There have 8 style for badge, we have:
- primary, secondary, success, danger, warning, info, light, dark.

Just put classes into tag like this:
```
{% badge success @success! %}
```

{% badge success @success! %}

You can see all style in [Bootstrap doc](https://getbootstrap.com/docs/4.4/components/badge/#contextual-variations).

### Detail Tag
We impliment html detail tag into hexo, just write something like this:
```
{% spoiler %}
Something more information at here!
{% endspoiler %}
```

{% spoiler %}
Something more information at here!
{% endspoiler %}

#### Detail Title
You can replace `Details` title to special you want, just write something like this:
```
{% spoiler Click Me %}
Something more information at here!
{% endspoiler %}
```

{% spoiler Click Me %}
Something more information at here!
{% endspoiler %}

### MathJax
You can write `LaTeX` code in `Markdown` file and render on post. Just enable in the theme's `_config.yml`.

```
# Enable post mathjax
mathjax:
  enable: true
```

And set mathjax to `true` in the posts front-matter:

```
---
title: Post title
date: 2007-08-05 07:08:05
tags: tags
mathjax: true
---
```

And so on, you can now write inline `LaTeX` in post like this:
```latex
$(a + b)^2 = a^2 + 2ab + b^2$
```

$(a + b)^2 = a^2 + 2ab + b^2$

Or write a `LaTeX` block in post like this:
```latex
$$
\begin{array}{lll}
(a + b)^2 &=& a^2 + 2ab + b^2 \\\\
(a - b)^2 &=& a^2 - 2ab + b^2
\end{array}
$$
```

$$
\begin{array}{lll}
(a + b)^2 &=& a^2 + 2ab + b^2 \\\\
(a - b)^2 &=& a^2 - 2ab + b^2
\end{array}
$$

For more information, you can see [MathJax Doc](https://docs.mathjax.org/en/v2.7-latest/index.html).

### Comments
The comments provider is specified in the theme's `_config.yml`.

```
# Comments. Choose one by filling up the information
comments:
  # Disqus comments
  disqus:
    enable: false
    shortname: 
  # Gitalk
  gitalk:
    enable: false
    owner:
    repo:
    oauth:
      accessToken:
      clientId:
      clientSecret:
    admin:
```

You can too hide the comment in the posts front-matter:

```
---
title: Post title
date: 2007-08-05 07:08:05
tags: tags
comment: false
---
```

## Post Font-matter
This is all font-matter you can use.

| Setting |	Description	| Default |
| ------- | ----------- | ------- |
| title | Title | Filename (posts only) |
| subtitle | Sub Title | - |
| date | Published date	| File created date |
| tags | Post tags | - |
| category | Post category | - |
| mathjax | Use mathjax or not | `false` |
| comment | Show comment field or not | `true` |
| cover | Use special header cover | - |
| pin | Pin this post on home page | `false` |

## Custom
You can modify your blog yourself by writing `custom.styl`, it location at 
```
. 
`-- source
  `-- css
    `-- _custom
      `-- custom.styl
```

## Creator
This theme was created by [Blackrock Digital](https://github.com/BlackrockDigital), adapted for Hexo by [Jonathan Klughertz](http://www.codeblocq.com/) and modfy by [LuSkywalker](/)

## Version
- V1.0.0
    - Base fuction support.

## License
MIT
