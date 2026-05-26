(function($) {
    'use strict';

    function configuredItems() {
        return window.fanmAdminMenu && Array.isArray(window.fanmAdminMenu.items)
            ? window.fanmAdminMenu.items
            : [];
    }

    function pageSlugFromHref(href) {
        if (String(href || '') === '#collapse-menu') {
            return 'collapse-menu';
        }

        var match = String(href || '').match(/[?&]page=([^&#]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    function pathFromHref(href) {
        var path;

        if (String(href || '') === '#collapse-menu') {
            return '#collapse-menu';
        }

        try {
            var url = new URL(href, window.location.origin);
            path = url.pathname.replace(/^\//, '') + url.search;
        } catch (error) {
            path = String(href || '').replace(/^\//, '');
        }

        return path.replace(/^.*wp-admin\//, '');
    }

    function isGenericWooRoute(path) {
        return path === 'admin.php?page=wc-admin' ||
            path === 'admin.php?page=woocommerce' ||
            path === 'admin.php?page=wc-admin&path=/' ||
            path === 'admin.php?page=wc-admin&path=%2F' ||
            path === 'admin.php?page=wc-admin&path=/home' ||
            path === 'admin.php?page=wc-admin&path=%2Fhome';
    }

    function isRoutedWooAdmin(slug, path) {
        return slug === 'wc-admin' && path.indexOf('admin.php?page=wc-admin&path=') === 0;
    }

    function slugKeys(slug, path) {
        var keys = [];
        var aliasKey = wooAliasKey(slug, path);

        if (!slug) {
            return keys;
        }

        if (aliasKey) {
            keys.push(aliasKey);
        }

        if (!aliasKey && !isRoutedWooAdmin(slug, path)) {
            keys.push('slug:' + slug);
        }

        if (slug.indexOf('.php') !== -1) {
            keys.push('path:' + slug);
        }

        if (slug === 'woocommerce' && (!path || isGenericWooRoute(path))) {
            keys.push('slug:wc-admin');
            keys.push('path:admin.php?page=wc-admin');
        }

        if (slug === 'wc-admin' && (!path || isGenericWooRoute(path))) {
            keys.push('slug:woocommerce');
            keys.push('path:admin.php?page=woocommerce');
        }

        if (slug === 'collapse-menu') {
            keys.push('path:#collapse-menu');
        }

        return keys;
    }

    function urlKeys(url) {
        var path = pathFromHref(url);
        var keys = path ? ['path:' + path] : [];
        var aliasKey = wooAliasKey(pageSlugFromHref(url), path);

        if (aliasKey) {
            keys.unshift(aliasKey);
        }

        if (path === 'admin.php?page=woocommerce') {
            keys.push('path:admin.php?page=wc-admin');
            keys.push('slug:wc-admin');
        }

        if (path === 'admin.php?page=wc-admin') {
            keys.push('path:admin.php?page=woocommerce');
            keys.push('slug:woocommerce');
        }

        return keys;
    }

    function wooAliasKey(slug, path) {
        var isGenericWooHome = !path || isGenericWooRoute(path);

        if (slug === 'wc-orders' ||
            slug === 'admin.php?page=wc-orders' ||
            path === 'admin.php?page=wc-orders' ||
            slug === 'edit.php?post_type=shop_order' ||
            path === 'edit.php?post_type=shop_order') {
            return 'woo:orders';
        }

        if (slug === 'woocommerce-marketing' ||
            path === 'admin.php?page=woocommerce-marketing' ||
            path === 'admin.php?page=wc-admin&path=/marketing' ||
            path === 'admin.php?page=wc-admin&path=%2Fmarketing' ||
            slug === 'wc-admin&path=/marketing' ||
            slug === 'wc-admin&path=%2Fmarketing') {
            return 'woo:marketing';
        }

        if (((slug === 'wc-admin' || slug === 'woocommerce') && isGenericWooHome) ||
            path === 'admin.php?page=wc-admin' ||
            path === 'admin.php?page=woocommerce') {
            return 'woo:home';
        }

        return '';
    }

    function isDeprecatedMenuItem(item) {
        var path = pathFromHref(item.url);
        var slug = item.slug || pageSlugFromHref(item.url);

        return slug === 'wc-reports' ||
            path === 'admin.php?page=wc-reports' ||
            path.indexOf('admin.php?page=wc-reports&') === 0;
    }

    function itemKeys(item) {
        var pathKeys = urlKeys(item.url);
        var path = pathFromHref(item.url);

        if (pathKeys.length && !isGenericWooRoute(path)) {
            return pathKeys.concat(slugKeys(item.slug, path));
        }

        return slugKeys(item.slug, path).concat(pathKeys);
    }

    function indexAdminMenuItems() {
        var itemByKey = {
            all: {},
            top: {}
        };

        $('#adminmenu li').each(function() {
            var $item = $(this);
            var $control = $item.children('a, button').first();
            var href = $item.attr('id') === 'collapse-menu'
                ? '#collapse-menu'
                : ($control.attr('href') || '');
            var path = pathFromHref(href);
            var keys = ['path:' + path].concat(slugKeys(pageSlugFromHref(href), path));
            var isTopLevel = $item.parent().is('#adminmenu');

            if (!$control.length) {
                return;
            }

            keys.forEach(function(key) {
                if (!key) {
                    return;
                }

                if (isTopLevel && !itemByKey.top[key]) {
                    itemByKey.top[key] = $item;
                }

                if (!itemByKey.all[key]) {
                    itemByKey.all[key] = $item;
                }
            });
        });

        return itemByKey;
    }

    function isCollapseMenuItem(item) {
        return item && (item.slug === 'collapse-menu' || item.url === '#collapse-menu');
    }

    function findMenuItem(itemByKey, item, preferTopLevel) {
        var keys = itemKeys(item);
        var found = null;
        var maps = preferTopLevel ? [itemByKey.top, itemByKey.all] : [itemByKey.all, itemByKey.top];

        maps.some(function(map) {
            return keys.some(function(key) {
                if (map[key] && map[key].length) {
                    found = map[key];
                    return true;
                }

                return false;
            });
        });

        return found;
    }

    function childContainer($parent, useCoreSubmenu) {
        var $submenu = useCoreSubmenu ? $parent.children('.wp-submenu') : $parent.children('.fanm-admin-submenu');

        if ($submenu.length) {
            return $submenu;
        }

        $submenu = $('<ul class="fanm-admin-submenu"></ul>').appendTo($parent);
        $parent.addClass('fanm-has-children');

        return $submenu;
    }

    function normalizePromotedTopItem($item, item) {
        var $link = $item.children('a').first();
        var label = $.trim(item && item.title ? item.title : '');

        if (isCollapseMenuItem(item)) {
            $item.removeClass('fanm-demoted-child').addClass('fanm-promoted-top');

            if (label) {
                $item.find('.collapse-button-label').first().text(label);
            }

            return;
        }

        $item.addClass('menu-top fanm-promoted-top');
        $item.removeClass('fanm-demoted-child');
        $link.addClass('menu-top');

        if (!$link.children('.wp-menu-name').length) {
            if (!label) {
                label = $.trim($link.text());
            }

            $link.empty();
            $link.append('<div class="wp-menu-image fanm-empty-menu-image" aria-hidden="true"></div>');
            $link.append($('<div class="wp-menu-name"></div>').text(label));
            return;
        }

        if (label) {
            $link.children('.wp-menu-name').text(label);
        }
    }

    function removeDuplicateParentSubmenuItem($parent) {
        var $parentLink = $parent.children('a').first();
        var parentPath = pathFromHref($parentLink.attr('href') || '');
        var parentLabel = $.trim($parentLink.find('.wp-menu-name').first().text() || $parentLink.text());

        if (!parentPath) {
            return;
        }

        $parent.children('.wp-submenu').first().children('li').each(function() {
            var $submenuItem = $(this);
            var $submenuLink = $submenuItem.children('a').first();
            var href = $submenuLink.attr('href') || '';
            var label = $.trim($submenuLink.text());

            if (pathFromHref(href) === parentPath || (label === parentLabel && href.indexOf('page=') !== -1)) {
                $submenuItem.remove();
            }
        });
    }

    function removeDuplicateSiblingMenuItems($item) {
        var $link = $item.children('a').first();
        var itemPath = pathFromHref($link.attr('href') || '');

        if (!itemPath) {
            return;
        }

        $item.siblings('li').each(function() {
            var $sibling = $(this);
            var siblingPath = pathFromHref($sibling.children('a').first().attr('href') || '');

            if (siblingPath === itemPath) {
                $sibling.remove();
            }
        });
    }

    function currentAdminPath() {
        return pathFromHref(window.location.pathname + window.location.search);
    }

    function markCurrentTrail() {
        var currentPath = currentAdminPath();
        var $current = $();

        if (!currentPath) {
            return;
        }

        $('#adminmenu .fanm-current-item, #adminmenu .fanm-active-trail')
            .removeClass('fanm-current-item fanm-active-trail');
        $('#adminmenu .fanm-active-trail-submenu')
            .removeClass('fanm-active-trail-submenu');

        $('#adminmenu a').each(function() {
            var $link = $(this);

            if (pathFromHref($link.attr('href') || '') === currentPath) {
                $current = $link.closest('li');
                return false;
            }

            return true;
        });

        if (!$current.length) {
            return;
        }

        $current.addClass('fanm-current-item');

        if ($current.parent().is('#adminmenu') && $current.children('.fanm-admin-submenu').length) {
            $current.addClass('fanm-active-trail');
            $current.children('.fanm-admin-submenu').addClass('fanm-active-trail-submenu');
        }

        $current.parents('li').each(function() {
            var $ancestor = $(this);

            if (!$ancestor.closest('#adminmenu').length) {
                return;
            }

            $ancestor.addClass('fanm-active-trail');
            $ancestor.children('.fanm-admin-submenu').addClass('fanm-active-trail-submenu');
        });
    }

    function normalizeDemotedChildItem($item, item) {
        var $link = $item.children('a').first();
        var label = $.trim(item && item.title ? item.title : '');
        var $submenu;

        $item
            .removeClass('menu-top fanm-promoted-top wp-has-current-submenu wp-menu-open wp-not-current-submenu open-if-no-js current')
            .addClass('fanm-demoted-child');
        $link.removeClass('menu-top');

        if (!$link.children('.wp-menu-name').length) {
            if (label) {
                $link.text(label);
            }

            return;
        }

        if (!label) {
            label = $.trim($link.children('.wp-menu-name').text());
        }

        $link.empty().text(label);

        $submenu = $item.children('.wp-submenu').first();

        if ($submenu.length) {
            $submenu
                .removeClass('wp-submenu wp-submenu-wrap')
                .addClass('fanm-admin-submenu')
                .removeAttr('style');

            $submenu.children('li').removeClass('wp-first-item current wp-has-current-submenu wp-menu-open wp-not-current-submenu open-if-no-js');
            $item.addClass('fanm-has-children');
        }
    }

    function arrangeAdminMenu() {
        var items = configuredItems();

        if (!items.length) {
            return;
        }

        var itemByKey = indexAdminMenuItems();
        var $adminMenu = $('#adminmenu');

        $adminMenu.children('.wp-menu-separator').hide();
        $adminMenu.find('a[href*="page=wc-reports"]').closest('li').hide();

        items.sort(function(a, b) {
            if (a.depth !== b.depth) {
                return a.depth - b.depth;
            }

            return Number(a.order || 0) - Number(b.order || 0);
        });

        items.forEach(function(item) {
            var $item = findMenuItem(itemByKey, item, item.depth === 0);

            if (isDeprecatedMenuItem(item)) {
                if ($item && $item.length) {
                    $item.hide();
                }

                return;
            }

            if (!$item || !$item.length) {
                return;
            }

            if (item.hidden) {
                $item.hide();
                return;
            }

            $item.show();

            if (item.depth === 0) {
                normalizePromotedTopItem($item, item);
                removeDuplicateParentSubmenuItem($item);
                $adminMenu.append($item);
                removeDuplicateSiblingMenuItems($item);
                return;
            }

            var $parent = findMenuItem(itemByKey, {
                slug: item.parentSlug,
                url: item.parentUrl
            }, item.depth === 1);

            if (!$parent || !$parent.length || $parent.is($item)) {
                return;
            }

            removeDuplicateParentSubmenuItem($item);
            childContainer($parent, item.depth === 1).append($item);
            normalizeDemotedChildItem($item, item);
            removeDuplicateSiblingMenuItems($item);
            $parent.addClass('fanm-has-children');
        });

        markCurrentTrail();
    }

    $(arrangeAdminMenu);
})(jQuery);
