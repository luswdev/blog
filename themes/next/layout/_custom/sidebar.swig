{% if theme.recent_posts %}
  <div class="links-of-blogroll motion-element {{ "links-of-blogroll-" + theme.recent_posts_layout  }}">
    <div class="links-of-blogroll-title">
      <!-- modify icon to fire by szw -->
      <span class="links-of-blogroll-title-name">
        <i class="fa fa-history fa-{{ theme.recent_posts_icon | lower }}" aria-hidden="true"></i>
        {{ theme.recent_posts_title }}
          <i class="fa fa-history fa-{{ theme.recent_posts_icon | lower }}" aria-hidden="true"></i>
      </span>
    </div>
    <ul class="links-of-blogroll-list">
      {% set posts = site.posts.sort('-date') %}
      {% for post in posts.slice('0', '5') %}
        <li>
          <a href="{{ url_for(post.path) }}" title="{{ post.title }}">{{ post.title }}</a>
        </li>
      {% endfor %}
    </ul>
  </div>
{% endif %}
<div class="about-link">
  <a href="/hexo/about">關於</a>
  <a href="mailto:610721230@gms.ndhu.edu.tw" target="_blank" title="E-Mail">聯絡作者</a><br>
  <a href="/hexo/howto">網站導覽</a>  
</div>