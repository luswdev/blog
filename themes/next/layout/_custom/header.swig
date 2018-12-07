{% if theme.social %}
            <div class="links-of-author motion-element">
              {% for name, link in theme.social %}
                <span class="links-of-author-item">
                  {% if theme.exturl && theme.social_icons.exturl %}
                    <span class="exturl" data-url="{{ link.split('||')[0] | trim }}" title="{{ name }}">{#
                #}{% else %}{#
                  #}<a href="{{ link.split('||')[0] | trim }}" target="_blank" title="{{ name }}"{% if theme.seo %} rel="external nofollow"{% endif %}>{#
                #}{% endif %}{#
                  #}{% if theme.social_icons.enable %}{#
                    #}<i class="fa fa-fw fa-{{ link.split('||')[1] | trim | default('globe') }}"></i>{#
                  #}{% endif %}{#
                  #}{% if not theme.social_icons.icons_only %}{#
                    #}{{ name }}{#
                  #}{% endif %}{#
                #}{% if theme.exturl && theme.social_icons.exturl %}{#
                  #}</span>
                  {% else %}{#
                  #}</a>
                  {% endif %}
                </span>
              {% endfor %}
            </div>
{% endif %}
