(function($) {
    'use strict';

    function slugFromHref(href) {
        var match = String(href || '').match(/[?&]page=([^&#]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    function buildNestedMenu() {
        var nestedItems = window.fanmAdminMenu && Array.isArray(window.fanmAdminMenu.items)
            ? window.fanmAdminMenu.items
            : [];
        var itemBySlug = {};

        $('#adminmenu a[href*="page="]').each(function() {
            var slug = slugFromHref($(this).attr('href'));

            if (slug && !itemBySlug[slug]) {
                itemBySlug[slug] = $(this).closest('li');
            }
        });

        nestedItems.sort(function(a, b) {
            return a.depth - b.depth;
        });

        nestedItems.forEach(function(item) {
            if (item.depth < 2) {
                return;
            }

            var $child = itemBySlug[item.slug];
            var $parent = itemBySlug[item.parentSlug];

            if (!$child || !$child.length || !$parent || !$parent.length || $child.is($parent)) {
                return;
            }

            var $submenu = $parent.children('.fanm-admin-submenu');

            if (!$submenu.length) {
                $submenu = $('<ul class="fanm-admin-submenu" />').appendTo($parent);
                $parent.addClass('fanm-has-children');
            }

            $child.appendTo($submenu);
        });
    }

    $(buildNestedMenu);
})(jQuery);

