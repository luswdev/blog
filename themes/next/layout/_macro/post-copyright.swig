 
<div class="note default post-copyright-div">
  <ul class="post-copyright">
    <li class="post-copyright-author">
      <strong>{{ __('post.copyright.author') + __('symbol.colon') }} </strong>{#
    #}{{ post.author | default(author) }}{#
  #}</li>
   <li class="post-copyright-title">
        <strong>{{ __('post.copyright.title') + __('symbol.colon') }} </strong>{#
      #}{{ page.title }}{#
    #}</li>
    <li class="post-copyright-posted">
        <strong>{{ __('post.copyright.posted') + __('symbol.colon') }} </strong>{#
      #}{{ page.date.format("YYYY年MM月DD日 - HH:MM") }}{#
    #}</li>
     <li class="post-copyright-updated">
        <strong>{{ __('post.copyright.updated') + __('symbol.colon') }} </strong>{#
      #}{{ page.updated.format("YYYY年MM月DD日 - HH:MM") }}{#
    #}</li>
    <li class="post-copyright-link">
      <strong>{{ __('post.copyright.link') + __('symbol.colon') }}</strong>
      <a href="{{ post.url | default(post.permalink) }}" title="{{ post.title }}">{{ post.url | default(post.permalink) }}</a>
    </li>
    <li class="post-copyright-license">
      <strong>{{ __('post.copyright.license_title') + __('symbol.colon') }} </strong>{#
    #}{{ __('post.copyright.license_content', theme.post_copyright.license) }}{#
  #}</li>
  </ul>
</div>