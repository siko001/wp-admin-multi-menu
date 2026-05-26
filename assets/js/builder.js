(function($) {
    'use strict';

    var lastPointer = { x: 0, y: 0 };
    var isDragging = false;
    var activeNestTargetId = null;
    var treeChangeTimer = null;
    var menuDataTimer = null;
    var previewRenderTimer = null;
    var dragHighlightFrame = null;
    var dragHighlightItem = null;
    var lastHighlightPointer = { x: 0, y: 0 };
    var lastHighlightAt = 0;
    var lastDragStopAt = 0;
    var dragStartPointer = { x: 0, y: 0 };
    var sortableRefreshTimer = null;
    var sidebarImportSignature = '';
    var collapsePreferenceKey = 'fanmBuilderCollapseState';
    var previewPreferenceKey = 'fanmBuilderPreviewState';

    function messages(key) {
        return window.fanmBuilder && window.fanmBuilder.messages
            ? window.fanmBuilder.messages[key]
            : '';
    }

    function ajaxData(data) {
        return $.extend({}, data, {
            nonce: window.fanmBuilder ? window.fanmBuilder.nonce : ''
        });
    }

    function ajaxUrl() {
        return window.fanmBuilder ? window.fanmBuilder.ajaxUrl : '';
    }

    function ensureAjax() {
        if (!window.fanmBuilder || !window.fanmBuilder.ajaxUrl) {
            alert(messages('ajaxMissing'));
            return false;
        }

        return true;
    }

    function isDefaultMode() {
        return !!(window.fanmBuilder && window.fanmBuilder.defaultMode);
    }

    function ensureDropLists() {
        $('#fanm-menu-tree li[data-id]').each(function() {
            var $li = $(this);

            if (!$li.children('ul').length) {
                $li.append('<ul class="fanm-child-list"></ul>');
            }

            updateDropListLabel($li);
        });
    }

    function updateDropListLabel($li) {
        var title = $li.children('.fanm-item-row').find('.fanm-menu-title').text();

        $li.children('ul').attr('data-fanm-drop-label', 'Drop here to make child of ' + title);
    }

    function initNestedSortables() {
        ensureDropLists();

        $('#fanm-menu-tree ul').each(function() {
            initSortableList($(this));
        });
    }

    function shouldActivateSortable($ul) {
        return $ul.is('#fanm-menu-tree > ul') ||
            ($ul.is(':visible') && $ul.children('li[data-id]').length > 0);
    }

    function initSortableList($ul) {
        if (!shouldActivateSortable($ul)) {
            if ($ul.hasClass('ui-sortable')) {
                $ul.sortable('destroy');
            }

            return;
        }

        if ($ul.hasClass('ui-sortable')) {
            return;
        }

        $ul.sortable({
            items: '> li[data-id]',
            placeholder: 'fanm-placeholder',
            tolerance: 'pointer',
            cursor: 'move',
            opacity: 0.75,
            axis: 'y',
            containment: '#fanm-menu-tree',
            scroll: true,
            scrollSensitivity: 80,
            scrollSpeed: 16,
            handle: '> .fanm-item-row',
            cancel: 'button, input, label, .fanm-item-actions, .fanm-menu-title',
            forcePlaceholderSize: true,
            helper: function(event, item) {
                var $helper = item.clone();

                $helper
                    .children('ul')
                    .remove();

                return $helper
                    .addClass('fanm-drag-helper')
                    .width(item.outerWidth());
            },
            distance: 8,
            start: function(event, ui) {
                var $oldParent = ui.item.parent().closest('li[data-id]');

                isDragging = true;
                lastPointer = {
                    x: event.clientX,
                    y: event.clientY
                };
                dragStartPointer = {
                    x: event.clientX,
                    y: event.clientY
                };
                lastHighlightPointer = {
                    x: event.clientX,
                    y: event.clientY
                };
                lastHighlightAt = 0;
                ui.item.data('fanmDragOldParent', $oldParent);
                ui.item.data('fanmDragOldPrev', ui.item.prev('li[data-id]'));
                ui.item.data('fanmDragOldNext', ui.item.next('li[data-id]'));
                ui.placeholder.height(ui.helper.outerHeight());
                ui.helper.width(ui.item.outerWidth());
                $('#fanm-menu-tree').addClass('fanm-is-dragging');
            },
            sort: function(event, ui) {
                lastPointer = {
                    x: event.clientX,
                    y: event.clientY
                };
            },
            stop: function(event, ui) {
                var nested = false;

                cancelDropTargetHighlight();
                lastDragStopAt = Date.now();
                isDragging = false;
                $('#fanm-menu-tree').removeClass('fanm-is-dragging');
                clearDropTarget();
                rescueHiddenDrop(ui.item);
                syncAfterDrop(ui.item);
                queueMenuDataUpdate(nested ? 500 : 160);
                queuePreviewRender(nested ? 220 : 100);
                queueSortableRefresh(3000);
            }
        });
    }

    function rescueHiddenDrop($item) {
        var $list = $item.parent();
        var $parent = $list.closest('li[data-id]');

        if ($list.is('#fanm-menu-tree > ul') || $list.is(':visible')) {
            return;
        }

        if ($parent.length) {
            $item.insertAfter($parent);
            return;
        }

        $('#fanm-menu-tree > ul').first().append($item);
    }

    function refreshSortables() {
        deferIdle(function() {
            $('#fanm-menu-tree ul.ui-sortable').each(function() {
                var $ul = $(this);

                if (!shouldActivateSortable($ul)) {
                    $ul.sortable('destroy');
                    return;
                }

                $ul.sortable('refresh');
            });

            $('#fanm-menu-tree ul').each(function() {
                initSortableList($(this));
            });
        }, 1200);
    }

    function queueSortableRefresh(delay) {
        window.clearTimeout(sortableRefreshTimer);
        sortableRefreshTimer = window.setTimeout(refreshSortables, delay);
    }

    function syncAfterDrop($item) {
        var $parent = $item.parent().closest('li[data-id]');
        var $previous = $item.prev('li[data-id]');
        var $next = $item.next('li[data-id]');
        var $oldParent = $item.data('fanmDragOldParent') || $();
        var $oldPrevious = $item.data('fanmDragOldPrev') || $();
        var $oldNext = $item.data('fanmDragOldNext') || $();

        syncItemState($item);

        if ($parent.length) {
            syncItemState($parent);
        }

        if ($oldParent.length) {
            syncItemState($oldParent);
        }

        syncAffectedHierarchyActions(
            $item
                .add($parent)
                .add($previous)
                .add($next)
                .add($oldParent)
                .add($oldPrevious)
                .add($oldNext)
        );

        $item
            .removeData('fanmDragOldParent')
            .removeData('fanmDragOldPrev')
            .removeData('fanmDragOldNext');
    }

    function deferIdle(callback, timeout) {
        if (window.requestIdleCallback) {
            window.requestIdleCallback(callback, { timeout: timeout || 600 });
            return;
        }

        window.setTimeout(callback, 1);
    }

    function elementUnderPointer($draggedItem) {
        var element;
        var dragged = $draggedItem.get(0);
        var previousPointerEvents = dragged ? dragged.style.pointerEvents : '';

        if (dragged) {
            dragged.style.pointerEvents = 'none';
        }

        element = document.elementFromPoint(lastPointer.x, lastPointer.y);

        if (dragged) {
            dragged.style.pointerEvents = previousPointerEvents;
        }

        return $(element);
    }

    function scheduleDropTargetHighlight($draggedItem) {
        var now = Date.now();
        var movedEnough = Math.abs(lastPointer.x - lastHighlightPointer.x) > 8 ||
            Math.abs(lastPointer.y - lastHighlightPointer.y) > 8;

        if (!movedEnough && now - lastHighlightAt < 80) {
            return;
        }

        lastHighlightPointer = {
            x: lastPointer.x,
            y: lastPointer.y
        };
        lastHighlightAt = now;
        dragHighlightItem = $draggedItem;

        if (dragHighlightFrame) {
            return;
        }

        dragHighlightFrame = window.requestAnimationFrame(function() {
            dragHighlightFrame = null;

            if (dragHighlightItem && dragHighlightItem.length && isDragging) {
                highlightDropTarget(dragHighlightItem);
            }
        });
    }

    function cancelDropTargetHighlight() {
        if (dragHighlightFrame) {
            window.cancelAnimationFrame(dragHighlightFrame);
        }

        dragHighlightFrame = null;
        dragHighlightItem = null;
    }

    function validDropTarget($draggedItem, $targetItem) {
        if (!$targetItem || !$targetItem.length) {
            return $();
        }

        if (!$targetItem.length || $targetItem.is($draggedItem)) {
            return $();
        }

        if ($.contains($draggedItem[0], $targetItem[0])) {
            return $();
        }

        return $targetItem;
    }

    function menuItemById(id) {
        return $('#fanm-menu-tree li[data-id]').filter(function() {
            return String($(this).data('id')) === String(id);
        }).first();
    }

    function pointerIsInNestZone($row) {
        var rect;
        var topBoundary;
        var bottomBoundary;
        var indentBoundary;
        var movedRightEnough;

        if (!$row.length) {
            return false;
        }

        rect = $row[0].getBoundingClientRect();
        topBoundary = rect.top + Math.min(28, rect.height * 0.38);
        bottomBoundary = rect.bottom - Math.min(28, rect.height * 0.38);
        indentBoundary = rect.left + Math.min(220, Math.max(120, rect.width * 0.28));
        movedRightEnough = lastPointer.x - dragStartPointer.x >= 48;

        return bottomBoundary > topBoundary &&
            lastPointer.y >= topBoundary &&
            lastPointer.y <= bottomBoundary &&
            lastPointer.x >= indentBoundary &&
            movedRightEnough;
    }

    function nestingDropTarget($draggedItem) {
        var $element = elementUnderPointer($draggedItem);
        var $targetRow = $element.closest('#fanm-menu-tree .fanm-item-row');

        if ($targetRow.length && pointerIsInNestZone($targetRow)) {
            return validDropTarget($draggedItem, $targetRow.closest('li[data-id]'));
        }

        return $();
    }

    function clearDropTarget() {
        var $target = activeNestTargetId
            ? menuItemById(activeNestTargetId)
            : $();

        if ($target.length) {
            $target.removeClass('fanm-nest-target');
            $target.children('.fanm-item-row')
                .removeClass('fanm-drop-target')
                .removeAttr('data-fanm-drop-label');
        } else {
            $('.fanm-drop-target')
                .removeClass('fanm-drop-target')
                .removeAttr('data-fanm-drop-label');
            $('.fanm-nest-target').removeClass('fanm-nest-target');
        }

        activeNestTargetId = null;
        $('#fanm-menu-tree').removeClass('fanm-has-nest-target');
    }

    function highlightDropTarget($draggedItem) {
        var $target = nestingDropTarget($draggedItem);
        var title = '';
        var targetId = $target.length ? $target.data('id') : null;

        if (targetId === activeNestTargetId) {
            return;
        }

        clearDropTarget();

        if ($target.length) {
            title = $target.children('.fanm-item-row').find('.fanm-menu-title').text();
            activeNestTargetId = $target.data('id');
            $('#fanm-menu-tree').addClass('fanm-has-nest-target');
            $target.addClass('fanm-nest-target');
            $target.children('.fanm-item-row')
                .addClass('fanm-drop-target')
                .attr('data-fanm-drop-label', 'Drop to make child of ' + title);
        }
    }

    function nestItemUnderActiveTarget($draggedItem) {
        var $target = activeNestTargetId
            ? menuItemById(activeNestTargetId)
            : nestingDropTarget($draggedItem);
        var $children;
        var wasCollapsed = false;

        if (!$target.length || $target.is($draggedItem) || $.contains($draggedItem[0], $target[0])) {
            return false;
        }

        $children = $target.children('ul');
        wasCollapsed = $children.length > 0 && !$children.is(':visible');

        if (!$children.length) {
            $children = $('<ul class="fanm-child-list"></ul>').appendTo($target);
        }

        $children.append($draggedItem);
        initSortableList($children);

        if (wasCollapsed) {
            $children.hide();
        } else {
            $children.show();
        }

        syncItemState($target);

        return true;
    }

    function nestItemUnder($item, $target) {
        var $children;
        var $oldParent = $item.parent().closest('li[data-id]');

        if (!$item.length || !$target.length || $target.is($item) || $.contains($item[0], $target[0])) {
            return;
        }

        $children = $target.children('ul');

        if (!$children.length) {
            $children = $('<ul class="fanm-child-list"></ul>').appendTo($target);
        }

        $children.append($item);
        $children.show();
        syncItemState($target);
        syncItemState($item);
        syncAffectedHierarchyActions($item.add($target).add($oldParent));
        queueMenuDataUpdate(360);
        queuePreviewRender(140);
        queueSortableRefresh(1200);
    }

    function indentItem($item) {
        var $previous = $item.prev('li[data-id]');

        if (!$previous.length) {
            return;
        }

        nestItemUnder($item, $previous);
    }

    function outdentItem($item) {
        var $parent = $item.parent().closest('li[data-id]');
        var $oldList = $item.parent();

        if (!$parent.length) {
            return;
        }

        $item.insertAfter($parent);
        syncItemState($parent);
        syncItemState($item);
        syncAffectedHierarchyActions($item.add($parent).add($oldList.closest('li[data-id]')));
        queueMenuDataUpdate(360);
        queuePreviewRender(140);
        queueSortableRefresh(1200);
    }

    function sortButtonTarget($item) {
        var $children = $item.children('ul').children('li[data-id]');

        if ($children.length) {
            return $item.children('ul').first();
        }

        return $item.parent();
    }

    function cycleSort($item) {
        var $list = sortButtonTarget($item);
        var $items = $list.children('li[data-id]');
        var state = Number($list.data('fanmSortState') || 0);
        var original;
        var sorted;

        if ($items.length < 2) {
            return;
        }

        if (!$list.data('fanmOriginalOrder')) {
            $list.data('fanmOriginalOrder', $items.map(function() {
                return $(this).data('id');
            }).get());
        }

        original = $list.data('fanmOriginalOrder');

        if (state === 0) {
            sorted = $items.get().sort(sortItemsAsc);
            $list.data('fanmSortState', 1);
        } else if (state === 1) {
            sorted = $items.get().sort(sortItemsAsc).reverse();
            $list.data('fanmSortState', 2);
        } else {
            sorted = original.map(function(id) {
                return $items.filter('[data-id="' + id + '"]').get(0);
            }).filter(Boolean);
            $list.data('fanmSortState', 0);
        }

        $list.append(sorted);
        syncAffectedHierarchyActions($list.children('li[data-id]'));
        queueMenuDataUpdate(360);
        queuePreviewRender(140);
        queueSortableRefresh(900);
    }

    function sortItemsAsc(a, b) {
        var titleA = $(a).children('.fanm-item-row').find('.fanm-menu-title').text().toLowerCase();
        var titleB = $(b).children('.fanm-item-row').find('.fanm-menu-title').text().toLowerCase();

        return titleA.localeCompare(titleB);
    }

    function syncHierarchyActions() {
        $('#fanm-menu-tree li[data-id]').each(function() {
            syncHierarchyActionForItem($(this));
        });
    }

    function syncHierarchyActionForItem($item) {
        var title = $item.children('.fanm-item-row').find('.fanm-menu-title').text();
        var $previous = $item.prev('li[data-id]');
        var $parent = $item.parent().closest('li[data-id]');
        var $indent = $item.children('.fanm-item-row').find('.fanm-indent-item');
        var $outdent = $item.children('.fanm-item-row').find('.fanm-outdent-item');
        var $sort = $item.children('.fanm-item-row').find('.fanm-sort-item');
        var previousTitle = $previous.children('.fanm-item-row').find('.fanm-menu-title').text();
        var childCount = $item.children('ul').children('li[data-id]').length;
        var siblingCount = $item.parent().children('li[data-id]').length;

        if ($parent.length) {
            $outdent
                .show()
                .attr({
                    title: 'Make "' + title + '" parent item',
                    'aria-label': 'Make "' + title + '" parent item',
                    'data-fanm-tooltip': 'Make parent item'
                });
        } else {
            $outdent.hide().removeAttr('title aria-label data-fanm-tooltip');
        }

        if ($previous.length) {
            $indent
                .show()
                .attr({
                    title: 'Make "' + title + '" child of "' + previousTitle + '"',
                    'aria-label': 'Make "' + title + '" child of previous item',
                    'data-fanm-tooltip': 'Make child of ' + previousTitle
                });
        } else {
            $indent.hide().removeAttr('title aria-label data-fanm-tooltip');
        }

        if (childCount > 1 || siblingCount > 1) {
            $sort
                .show()
                .attr({
                    title: childCount > 1 ? 'Sort children of "' + title + '"' : 'Sort this level',
                    'aria-label': childCount > 1 ? 'Sort children of "' + title + '"' : 'Sort this level',
                    'data-fanm-tooltip': childCount > 1 ? 'Sort children A-Z / Z-A / original' : 'Sort this level A-Z / Z-A / original'
                });
        } else {
            $sort.hide().removeAttr('title aria-label data-fanm-tooltip');
        }
    }

    function syncAffectedHierarchyActions($items) {
        var seen = {};
        var $affected = $();

        $items.each(function() {
            var $item = $(this);
            var $parent = $item.parent().closest('li[data-id]');

            $affected = $affected
                .add($item)
                .add($item.prev('li[data-id]'))
                .add($item.next('li[data-id]'))
                .add($parent)
                .add($parent.prev('li[data-id]'))
                .add($parent.next('li[data-id]'));
        });

        $affected.each(function() {
            var id = $(this).data('id');

            if (!id || seen[id]) {
                return;
            }

            seen[id] = true;
            syncHierarchyActionForItem($(this));
        });
    }

    function importVisibleSidebarItems(force) {
        var $root = $('#fanm-menu-tree > ul').first();
        var existing = existingMenuItemsByKey();
        var imported = {};
        var topLevelKeys = {};
        var signature;

        if (!$root.length || !$('#adminmenu').length) {
            return;
        }

        signature = visibleSidebarSignature();

        if (!force && signature && signature === sidebarImportSignature) {
            return;
        }

        sidebarImportSignature = signature;

        $('#adminmenu > li:visible').each(function() {
            var item;

            if (shouldSkipSidebarItem($(this))) {
                return;
            }

            item = sidebarItemData($(this), '');
            preferredMenuKeys(item.slug, item.url).forEach(function(key) {
                topLevelKeys[key] = true;
            });
            importSidebarItem($(this), $root, existing, imported, '');
        });

        removeStaleDuplicateTopLevelItems($root, topLevelKeys);
        syncTopLevelOrderFromSidebar();
        initNestedSortables();
        handleTreeChange();
    }

    function importMissingVisibleSidebarItems() {
        var $root = $('#fanm-menu-tree > ul').first();
        var existing = existingMenuItemsByKey();
        var imported = {};
        var changed = false;

        if (!$root.length || !$('#adminmenu').length) {
            return;
        }

        $('#adminmenu > li:visible').each(function() {
            var item;

            if (shouldSkipSidebarItem($(this))) {
                return;
            }

            item = sidebarItemData($(this), '');

            if (existingBuilderItem(existing, item).length) {
                return;
            }

            importSidebarItem($(this), $root, existing, imported, '');
            changed = true;
        });

        if (!changed) {
            return;
        }

        initNestedSortables();
        handleTreeChange();
    }

    function visibleSidebarSignature() {
        var keys = [];

        $('#adminmenu > li:visible').each(function() {
            var item;

            if (shouldSkipSidebarItem($(this))) {
                return;
            }

            item = sidebarItemData($(this), '');
            keys.push(preferredMenuKeys(item.slug, item.url).join('|'));
        });

        return keys.join('||');
    }

    function syncTopLevelOrderFromSidebar() {
        var $root = $('#fanm-menu-tree > ul').first();
        var $items = $root.children('li[data-id]');
        var position = {};
        var fallback = 100000;

        $('#adminmenu > li:visible').each(function(index) {
            if (shouldSkipSidebarItem($(this))) {
                return;
            }

            var item = sidebarItemData($(this), '');

            preferredMenuKeys(item.slug, item.url).forEach(function(key) {
                position[key] = index;
            });
        });

        $items.get().sort(function(a, b) {
            return itemSidebarPosition(a, position, fallback) - itemSidebarPosition(b, position, fallback);
        }).forEach(function(item) {
            $root.append(item);
        });
    }

    function itemSidebarPosition(element, position, fallback) {
        var item = menuItemFromElement(element);
        var value = fallback;

        preferredMenuKeys(item.slug, item.url).some(function(key) {
            if (position[key] !== undefined) {
                value = position[key];
                return true;
            }

            return false;
        });

        return value;
    }

    function existingMenuItemsByKey() {
        var items = {};

        $('#fanm-menu-tree li[data-id]').each(function() {
            var $item = $(this);
            var item = menuItemFromElement(this);

            menuKeys(item.slug, item.url).forEach(function(key) {
                if (!items[key]) {
                    items[key] = $item;
                }
            });
        });

        return items;
    }

    function importSidebarItem($sourceItem, $targetList, existing, imported, parentKey) {
        var item = sidebarItemData($sourceItem, parentKey);
        var $targetItem;
        var importKey;

        if (shouldSkipSidebarItem($sourceItem)) {
            return;
        }

        if (!item.title || !item.slug) {
            return;
        }

        importKey = preferredMenuKeys(item.slug, item.url).join('|');

        if (imported[importKey]) {
            return;
        }

        $targetItem = existingBuilderItem(existing, item);

        if ($targetItem.length) {
            if (targetListIsInsideItem($targetList, $targetItem)) {
                return;
            }

            updateBuilderItemFromSidebar($targetItem, item);
            $targetList.append($targetItem);
        } else {
            $targetItem = createBuilderItem(item);
            $targetList.append($targetItem);

            menuKeys(item.slug, item.url).forEach(function(key) {
                if (!existing[key]) {
                    existing[key] = $targetItem;
                }
            });
        }

        imported[importKey] = true;

        $sourceItem.children('.wp-submenu, .fanm-admin-submenu').first().children('li').each(function() {
            importSidebarItem($(this), $targetItem.children('ul'), existing, imported, item.id);
        });
    }

    function targetListIsInsideItem($targetList, $item) {
        return $targetList.length && $item.length && $.contains($item[0], $targetList[0]);
    }

    function existingBuilderItem(existing, item) {
        var $item = $();

        preferredMenuKeys(item.slug, item.url).some(function(key) {
            if (existing[key] && existing[key].length) {
                $item = existing[key];
                return true;
            }

            return false;
        });

        return $item;
    }

    function removeStaleDuplicateTopLevelItems($root, topLevelKeys) {
        var liveTitles = {};

        $('#adminmenu > li:visible').each(function() {
            var item;

            if (shouldSkipSidebarItem($(this))) {
                return;
            }

            item = sidebarItemData($(this), '');
            liveTitles[normalizedTitle(item.title)] = true;
        });

        $root.children('li[data-id]').each(function() {
            var $item = $(this);
            var item = menuItemFromElement(this);
            var isLiveTopLevel = false;

            preferredMenuKeys(item.slug, item.url).some(function(key) {
                if (topLevelKeys[key]) {
                    isLiveTopLevel = true;
                    return true;
                }

                return false;
            });

            if (isLiveTopLevel || !liveTitles[normalizedTitle(item.title)]) {
                return;
            }

            $item.children('ul').children('li[data-id]').appendTo($root);
            $item.remove();
        });
    }

    function normalizedTitle(title) {
        return $.trim(String(title || '')).toLowerCase();
    }

    function isPreferredWooAliasItem(item) {
        var slug = canonicalSlug(item.slug);
        var path = canonicalSlug(pathFromUrl(item.url));
        var isGenericWooHome = !path || isGenericWooHomePath(path);

        return slug === 'wc-orders' ||
            slug === 'admin.php?page=wc-orders' ||
            path === 'admin.php?page=wc-orders' ||
            path === 'admin.php?page=wc-admin&path=/marketing' ||
            path === 'admin.php?page=wc-admin&path=%2Fmarketing' ||
            slug === 'wc-admin&path=/marketing' ||
            slug === 'wc-admin&path=%2Fmarketing' ||
            ((slug === 'wc-admin' || slug === 'woocommerce') && isGenericWooHome) ||
            path === 'admin.php?page=wc-admin';
    }

    function removeDuplicateBuilderItems() {
        var seen = {};

        $('#fanm-menu-tree li[data-id]').each(function() {
            var $item = $(this);
            var item = menuItemFromElement(this);
            var aliasKey = menuKeys(item.slug, item.url).filter(function(key) {
                return key.indexOf('woo:') === 0;
            })[0];
            var $existing;
            var existingItem;

            if (!aliasKey) {
                return;
            }

            if (!seen[aliasKey]) {
                seen[aliasKey] = $item;
                return;
            }

            $existing = seen[aliasKey];
            existingItem = menuItemFromElement($existing.get(0));

            if ($.contains($existing.get(0), $item.get(0))) {
                moveDuplicateChildren($item, $existing);
                $item.remove();
                return;
            }

            if ($.contains($item.get(0), $existing.get(0))) {
                moveDuplicateChildren($existing, $item);
                $existing.remove();
                seen[aliasKey] = $item;
                return;
            }

            if (isPreferredWooAliasItem(item) && !isPreferredWooAliasItem(existingItem)) {
                moveDuplicateChildren($existing, $item);
                $existing.remove();
                seen[aliasKey] = $item;
                return;
            }

            moveDuplicateChildren($item, $existing);
            $item.remove();
        });
    }

    function moveDuplicateChildren($from, $to) {
        var $targetList = $to.children('ul').first();

        if (!$targetList.length) {
            $targetList = $('<ul class="fanm-child-list"></ul>').appendTo($to);
        }

        $from.children('ul').first().children('li[data-id]').each(function() {
            var $child = $(this);

            if ($child.is($to) || $.contains($child.get(0), $to.get(0))) {
                return;
            }

            $targetList.append($child);
        });
    }

    function updateBuilderItemFromSidebar($item, item) {
        var $fields = $item.children('.fanm-item-row').find('.fanm-fields');
        var currentTitle = $.trim($fields.find('.title').val());
        var hasCustomTitle = $fields.find('.custom_title').val() === '1';

        if (!currentTitle || !hasCustomTitle) {
            $fields.find('.title').val(item.title);
            $item.children('.fanm-item-row').find('.fanm-menu-title').text(item.title);
        }

        $fields.find('.slug').val(item.slug);
        $fields.find('.icon').val(item.icon);
        $fields.find('.url').val(item.url);
        $item.data('fanmIconHtml', item.iconHtml || '');
        $item.children('.fanm-item-row').find('.fanm-menu-slug').text(item.slug);
    }

    function sidebarItemData($sourceItem, parentKey) {
        var $link = $sourceItem.children('a').first();
        var href = $link.attr('href') || '';
        var slug = slugFromHref(href);
        var title = $.trim($link.find('.wp-menu-name').first().text() || $link.text());
        var icon = iconFromSidebarItem($sourceItem);
        var key = parentKey ? parentKey + '|' + slug : slug;

        return {
            id: 'existing_dom_' + stableHash(key || title),
            title: title,
            slug: slug,
            cap: 'read',
            icon: icon,
            iconHtml: iconHtmlFromSidebarItem($sourceItem),
            source: 'existing',
            url: href,
            hidden: false,
            parent: 0
        };
    }

    function shouldSkipSidebarItem($sourceItem) {
        var id = $sourceItem.attr('id') || '';
        var $link = $sourceItem.children('a').first();
        var href = $link.attr('href') || '';
        var path = canonicalSlug(pathFromUrl(href));
        var slug = slugFromHref(href);

        if (id === 'collapse-menu' || href === '#collapse-menu') {
            return false;
        }

        if (slug === 'wc-reports' ||
            path === 'admin.php?page=wc-reports' ||
            path.indexOf('admin.php?page=wc-reports&') === 0) {
            return true;
        }

        return $sourceItem.hasClass('wp-menu-separator') ||
            $sourceItem.hasClass('hide-if-no-js') ||
            href === '#';
    }

    function menuKeys(slug, url) {
        var keys = [];
        var normalizedSlug = canonicalSlug(slug);
        var normalizedPath = canonicalSlug(pathFromUrl(url));
        var aliasKey = wooAliasKey(normalizedSlug, normalizedPath);

        if (aliasKey) {
            keys.push(aliasKey);
        }

        if (normalizedSlug && !aliasKey && !isGenericWooAdminSlug(normalizedSlug, normalizedPath)) {
            keys.push('slug:' + normalizedSlug);
        }

        if (normalizedPath && !aliasKey) {
            keys.push('path:' + normalizedPath);
        }

        return keys;
    }

    function wooAliasKey(slug, path) {
        var isGenericWooHome = !path || isGenericWooHomePath(path);

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

    function preferredMenuKeys(slug, url) {
        var keys = menuKeys(slug, url);
        var pathKeys = keys.filter(function(key) {
            return key.indexOf('path:') === 0;
        });

        return pathKeys.length ? pathKeys : keys;
    }

    function isGenericWooAdminSlug(slug, path) {
        return slug === 'wc-admin' && path.indexOf('admin.php?page=wc-admin&path=') === 0;
    }

    function isGenericWooHomePath(path) {
        return path === 'admin.php?page=wc-admin' ||
            path === 'admin.php?page=woocommerce' ||
            path === 'admin.php?page=wc-admin&path=/' ||
            path === 'admin.php?page=wc-admin&path=%2F' ||
            path === 'admin.php?page=wc-admin&path=/home' ||
            path === 'admin.php?page=wc-admin&path=%2Fhome';
    }

    function slugFromHref(href) {
        if (String(href || '') === '#collapse-menu') {
            return 'collapse-menu';
        }

        var page = String(href || '').match(/[?&]page=([^&#]+)/);

        if (page) {
            return canonicalSlug(decodeURIComponent(page[1]));
        }

        return canonicalSlug(pathFromUrl(href));
    }

    function keyMatches(keys, candidateKeys) {
        var matches = false;

        keys.some(function(key) {
            if (candidateKeys.indexOf(key) !== -1) {
                matches = true;
                return true;
            }

            return false;
        });

        return matches;
    }

    function matchingAdminMenuLink(item) {
        var keys = preferredMenuKeys(item.slug, item.url);
        var preferredTopLevel = item.parent === 0 || item.parent === '0';
        var $match = $();
        var $fallback = $();

        if (item.slug === 'collapse-menu' || item.url === '#collapse-menu') {
            return $('#collapse-menu').children('button').first();
        }

        if (!keys.length) {
            return $match;
        }

        $('#adminmenu a').each(function() {
            var $link = $(this);
            var href = $link.attr('href') || '';
            var candidateKeys = preferredMenuKeys(slugFromHref(href), href);

            if (keyMatches(keys, candidateKeys)) {
                if (!preferredTopLevel || $link.closest('li').parent().is('#adminmenu')) {
                    $match = $link;
                    return false;
                }

                if (!$fallback.length) {
                    $fallback = $link;
                }
            }

            return true;
        });

        return $match.length ? $match : $fallback;
    }

    function applyTitleToAdminSidebar(item, title) {
        var $link = matchingAdminMenuLink(item);

        if (!$link.length) {
            return;
        }

        if ($link.closest('#collapse-menu').length) {
            $link.find('.collapse-button-label').first().text(title);
            return;
        }

        if ($link.children('.wp-menu-name').length) {
            $link.children('.wp-menu-name').text(title);
            return;
        }

        $link.text(title);
    }

    function canonicalSlug(slug) {
        slug = String(slug || '').replace(/&amp;/g, '&').replace(/^\/+/, '');

        if (slug.indexOf('customize.php') === 0) {
            return 'customize.php';
        }

        return slug;
    }

    function pathFromUrl(url) {
        var parsed;

        if (String(url || '') === '#collapse-menu') {
            return '#collapse-menu';
        }

        try {
            parsed = new URL(url, window.location.origin);
            return (parsed.pathname.replace(/^.*\/wp-admin\//, '') + parsed.search).replace(/^\//, '');
        } catch (error) {
            return String(url || '').replace(/^.*\/wp-admin\//, '').replace(/^\//, '');
        }
    }

    function iconFromSidebarItem($sourceItem) {
        var icon = '';
        var classes = $sourceItem.children('a').first().find('.wp-menu-image').attr('class') || '';

        classes.split(/\s+/).some(function(className) {
            if (isRealDashiconClass(className)) {
                icon = className;
                return true;
            }

            return false;
        });

        return icon || classes || 'fanm-preview-empty-icon';
    }

    function isRealDashiconClass(className) {
        return className.indexOf('dashicons-') === 0 &&
            className !== 'dashicons-before' &&
            className !== 'dashicons';
    }

    function iconHtmlFromSidebarItem($sourceItem) {
        var $icon = $sourceItem.children('a').first().find('.wp-menu-image').first();
        var $image;
        var imageUrl = '';
        var style = '';

        if (!$icon.length) {
            return '';
        }

        $image = $icon.find('img').first();

        if ($image.length) {
            imageUrl = $image.attr('src') || '';
        }

        if (!imageUrl) {
            style = $icon.attr('style') || '';
            imageUrl = backgroundImageUrl(style);
        }

        return imageUrl;
    }

    function backgroundImageUrl(style) {
        var match = String(style || '').match(/url\((['"]?)(.*?)\1\)/);

        return match ? match[2] : '';
    }

    function stableHash(value) {
        var hash = 0;
        var i;

        value = String(value || '');

        for (i = 0; i < value.length; i++) {
            hash = ((hash << 5) - hash) + value.charCodeAt(i);
            hash |= 0;
        }

        return Math.abs(hash).toString(16);
    }

    function createBuilderItem(item) {
        var $li = $('<li></li>').attr({
            'data-id': item.id,
            'data-level': '0',
            'data-parent': '0'
        }).data('fanmIconHtml', item.iconHtml || '');
        var $row = $('<div class="fanm-item-row" role="button" tabindex="0"></div>');
        var $fields = $('<div class="fanm-fields"></div>');
        var $actions = $('<div class="fanm-item-actions" aria-label="Menu item hierarchy actions"></div>');

        $row.append('<span class="fanm-handle dashicons dashicons-menu" aria-hidden="true"></span>');
        $fields.append($('<span class="fanm-menu-title" tabindex="0" role="button"></span>').text(item.title));
        $fields.append($('<span class="fanm-menu-slug"></span>').text(item.slug));
        ['title', 'slug', 'cap', 'icon', 'source', 'url'].forEach(function(field) {
            $fields.append($('<input type="hidden">').addClass(field).val(item[field] || ''));
        });
        $fields.append($('<input type="hidden">').addClass('hidden').val(item.hidden ? '1' : '0'));
        $fields.append($('<input type="hidden">').addClass('custom_title').val(item.custom_title ? '1' : '0'));
        $actions.append(
            $('<label class="fanm-visibility-toggle" title="Hide this menu item"></label>')
                .append($('<input class="fanm-hidden-toggle" type="checkbox">').prop('checked', !!item.hidden))
                .append('<span class="dashicons dashicons-visibility" aria-hidden="true"></span>')
        );
        $actions.append('<button class="button-link fanm-sort-item" type="button"><span class="dashicons dashicons-sort" aria-hidden="true"></span></button>');
        $actions.append('<button class="button-link fanm-outdent-item" type="button"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button>');
        $actions.append('<button class="button-link fanm-indent-item" type="button"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>');
        $row.append($fields).append($actions);
        $li.append($row).append('<ul class="fanm-child-list"></ul>');

        return $li;
    }

    function renderAdminPreview() {
        var $preview = $('#fanm-admin-preview');
        var $menu;

        if (!$preview.length) {
            return;
        }

        $menu = $('<ul class="fanm-preview-menu"></ul>');
        renderPreviewItems($('#fanm-menu-tree > ul').children('li[data-id]'), $menu, 0);
        $preview.empty().append($menu);
    }

    function queuePreviewRender(delay) {
        window.clearTimeout(previewRenderTimer);
        previewRenderTimer = window.setTimeout(function() {
            deferIdle(renderAdminPreview, 500);
        }, delay === undefined ? 40 : delay);
    }

    function renderPreviewItems($items, $target, depth) {
        $items.each(function() {
            var $item = $(this);
            var $children = $item.children('ul').children('li[data-id]');
            var item = menuItemFromElement(this);
            var $previewItem = $('<li></li>');
            var $link = $('<a href="#"></a>').attr({
                tabindex: '-1',
                'aria-disabled': 'true'
            });

            if (item.hidden) {
                return;
            }

            $previewItem.toggleClass('fanm-has-children wp-has-submenu', $children.length > 0);

            if (depth === 0) {
                $previewItem.addClass('menu-top menu-icon-generic fanm-preview-top');
                $link.addClass('menu-top fanm-preview-link');
                $link.append(previewIcon(item.icon, item.iconHtml, item));
                $link.append($('<span class="fanm-preview-label"></span>').text(item.title));
            } else {
                $previewItem.addClass('fanm-preview-child');
                $link.addClass('fanm-preview-sub-link').text(item.title);
            }

            $previewItem.append($link);

            if ($children.length) {
                renderPreviewItems(
                    $children,
                    $('<ul></ul>')
                        .addClass(depth === 0 ? 'wp-submenu wp-submenu-wrap' : 'fanm-admin-submenu')
                        .appendTo($previewItem),
                    depth + 1
                );
            }

            $target.append($previewItem);
        });
    }

    function previewIcon(icon, iconHtml, item) {
        var $icon = $('<span class="fanm-preview-icon" aria-hidden="true"></span>');
        var $image;

        if (iconHtml) {
            $image = $('<img class="fanm-preview-source-icon" alt="">').attr('src', iconHtml);

            if (iconHtml.indexOf('data:image/svg+xml') === 0) {
                $image.css('filter', 'brightness(0) invert(0.72)');
            }

            $icon.append($image);
            return $icon;
        }

        if (icon && icon.indexOf('woocommerce') !== -1) {
            $icon.addClass('fanm-preview-letter-icon').text('W');
            return $icon;
        }

        if (icon && icon.indexOf('wp-mail-smtp') !== -1) {
            $icon.addClass('dashicons dashicons-email-alt');
            return $icon;
        }

        if (itemLooksLikeFilter(icon, item)) {
            $icon.addClass('dashicons dashicons-filter');
            return $icon;
        }

        if (icon && icon.indexOf('dashicons-') !== -1) {
            icon.split(/\s+/).some(function(className) {
                if (isRealDashiconClass(className)) {
                    $icon.addClass('dashicons ' + className);
                    return true;
                }

                return false;
            });

            if ($icon.hasClass('dashicons')) {
                return $icon;
            }
        }

        $icon.addClass('fanm-preview-empty-icon');

        return $icon;
    }

    function itemLooksLikeFilter(icon, item) {
        var haystack = [
            icon,
            item && item.title,
            item && item.slug,
            item && item.url
        ].join(' ').toLowerCase();

        return haystack.indexOf('filter') !== -1;
    }

    function preventPreviewNavigation() {
        $(document).on('click', '#fanm-admin-preview a', function(event) {
            event.preventDefault();
        });
    }

    function positionPreviewFlyout($item) {
        var $submenu = $item.children('.wp-submenu, .fanm-admin-submenu').first();
        var submenuWidth;
        var itemRect;
        var roomRight;

        if (!$submenu.length) {
            return;
        }

        $item.removeClass('fanm-preview-open-left');
        submenuWidth = $submenu.outerWidth() || 220;
        itemRect = $item[0].getBoundingClientRect();
        roomRight = window.innerWidth - itemRect.right;

        if (roomRight < submenuWidth + 12) {
            $item.addClass('fanm-preview-open-left');
        }
    }

    function bindPreviewFlyouts() {
        $(document)
            .on('mouseenter focusin', '#fanm-admin-preview .fanm-preview-menu li', function() {
                positionPreviewFlyout($(this));
            })
            .on('mouseleave focusout', '#fanm-admin-preview .fanm-preview-menu li', function() {
                $(this).removeClass('fanm-preview-open-left');
            });
    }

    function initializeExpandCollapse() {
        $('#fanm-menu-tree li').each(function() {
            syncItemState($(this));
        });

        syncHierarchyActions();
    }

    function syncItemState($li) {
        var $children = $li.children('ul').children('li[data-id]');
        var hidden = $li.children('.fanm-item-row').find('.hidden').val() === '1';

        $li.toggleClass('fanm-item-hidden', hidden).attr('data-hidden', hidden ? '1' : '0');
        $li.toggleClass('fanm-builder-has-children', $children.length > 0);
        $li.children('.fanm-item-row').find('.fanm-handle')
            .toggleClass('has-children', $children.length > 0)
            .attr('title', 'Drag to move. Click to expand/collapse children.');
    }

    function beginTitleEdit($title) {
        var currentTitle = $.trim($title.text());
        var currentCustomTitle = $title.closest('.fanm-item-row').find('.custom_title').val() === '1';
        var $input;

        if ($title.hasClass('fanm-title-editing')) {
            return;
        }

        $input = $('<input class="fanm-title-input" type="text">').val(currentTitle);
        $input.data('fanmOriginalCustomTitle', currentCustomTitle);
        $title
            .addClass('fanm-title-editing')
            .empty()
            .append($input);
        $input.trigger('focus').trigger('select');
    }

    function finishTitleEdit($input, shouldCancel) {
        var $title = $input.closest('.fanm-menu-title');
        var $item = $input.closest('li[data-id]');
        var previousTitle = $.trim($item.children('.fanm-item-row').find('.title').val());
        var nextTitle = $.trim($input.val());
        var item;

        if (shouldCancel || !nextTitle) {
            nextTitle = previousTitle;
        }

        $item.children('.fanm-item-row').find('.title').val(nextTitle);
        $item.children('.fanm-item-row').find('.custom_title').val(shouldCancel ? ($input.data('fanmOriginalCustomTitle') ? '1' : '0') : '1');
        $title.removeClass('fanm-title-editing').text(nextTitle);
        item = menuItemFromElement($item.get(0));
        applyTitleToAdminSidebar(item, nextTitle);
        syncAffectedHierarchyActions($item.add($item.next('li[data-id]')));
        queueMenuDataUpdate(160);
        queuePreviewRender(80);
    }

    function expandAll() {
        $('#fanm-menu-tree li > ul').show();
        $('#fanm-menu-tree .fanm-handle.has-children').addClass('expanded');
        saveCollapsePreference('expanded');
    }

    function collapseAll() {
        $('#fanm-menu-tree li > ul').each(function() {
            if ($(this).children('li[data-id]').length > 0) {
                $(this).hide();
            }
        });
        $('#fanm-menu-tree .fanm-handle.has-children').removeClass('expanded');
        saveCollapsePreference('collapsed');
    }

    function saveCollapsePreference(value) {
        try {
            window.localStorage.setItem(collapsePreferenceKey, value);
        } catch (error) {
            // Ignore unavailable storage.
        }
    }

    function restoreCollapsePreference() {
        var value = '';

        try {
            value = window.localStorage.getItem(collapsePreferenceKey) || '';
        } catch (error) {
            value = '';
        }

        if (value === 'collapsed') {
            collapseAll();
            return;
        }

        if (value === 'expanded') {
            expandAll();
        }
    }

    function setPreviewOpen(isOpen) {
        $('#fanm-preview-panel').prop('hidden', !isOpen);
        $('#fanm-open-preview').prop('hidden', isOpen);
        $('#fanm-builder-grid').toggleClass('fanm-preview-closed', !isOpen);

        try {
            window.localStorage.setItem(previewPreferenceKey, isOpen ? 'open' : 'closed');
        } catch (error) {
            // Ignore unavailable storage.
        }

        if (isOpen) {
            queuePreviewRender(20);
        }
    }

    function restorePreviewPreference() {
        var value = '';

        try {
            value = window.localStorage.getItem(previewPreferenceKey) || '';
        } catch (error) {
            value = '';
        }

        setPreviewOpen(value !== 'closed');
    }

    function menuItemFromElement(element, order) {
        var $li = $(element);
        var $parentLi = $li.parent().closest('li[data-id]');
        var parentId = $parentLi.length > 0 ? $parentLi.data('id') : 0;
        var $fields = $li.children('.fanm-item-row').find('.fanm-fields');

        return {
            id: $li.data('id'),
            title: $fields.find('.title').val(),
            slug: $fields.find('.slug').val(),
            cap: $fields.find('.cap').val(),
            icon: $fields.find('.icon').val(),
            iconHtml: $li.data('fanmIconHtml') || '',
            source: 'existing',
            url: $fields.find('.url').val() || '',
            hidden: $fields.find('.hidden').val() === '1',
            custom_title: $fields.find('.custom_title').val() === '1',
            parent: parentId,
            order: order === undefined ? 0 : order
        };
    }

    function currentMenuItems() {
        var items = {};

        $('#fanm-menu-tree li[data-id]').each(function(index) {
            var item = menuItemFromElement(this, index);
            items[item.id] = item;
        });

        return items;
    }

    function exportSidebarLayout() {
        var blob;
        var link;
        var exportData;

        updateMenuData();

        exportData = {
            plugin: 'wp-admin-multi-menu',
            exportedAt: new Date().toISOString(),
            version: window.fanmBuilder && window.fanmBuilder.version ? window.fanmBuilder.version : '',
            menus: window.fanmMenus
        };

        blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
        link = document.createElement('a');
        link.href = window.URL.createObjectURL(blob);
        link.download = messages('exportName') || 'admin-menu-sidebar-export.json';
        document.body.appendChild(link);
        link.click();
        window.URL.revokeObjectURL(link.href);
        link.remove();
    }

    function restoreSidebarLayoutFromFile(file) {
        var reader = new FileReader();

        reader.onload = function(event) {
            try {
                restoreSidebarLayout(JSON.parse(String(event.target.result || '')));
                alert(messages('importSuccess'));
            } catch (error) {
                alert(messages('importError'));
            }
        };

        reader.onerror = function() {
            alert(messages('importError'));
        };

        reader.readAsText(file);
    }

    function restoreSidebarLayout(data) {
        var menus = normalizeImportedMenus(data);
        var $root = $('#fanm-menu-tree > ul').first();
        var items = {};
        var ordered;

        if (!$root.length || !Object.keys(menus).length) {
            throw new Error('Invalid sidebar export.');
        }

        ordered = Object.keys(menus).sort(function(a, b) {
            return Number(menus[a].order || 0) - Number(menus[b].order || 0);
        });

        $root.empty();

        ordered.forEach(function(id) {
            var item = menus[id];
            item.id = item.id || id;
            items[id] = createBuilderItem(item);
        });

        ordered.forEach(function(id) {
            var item = menus[id];
            var parentId = item.parent && items[item.parent] ? item.parent : 0;
            var $target = parentId ? items[parentId].children('ul').first() : $root;

            $target.append(items[id]);
        });

        initNestedSortables();
        initializeExpandCollapse();
        updateMenuData();
        renderAdminPreview();
        queueSortableRefresh(500);
    }

    function normalizeImportedMenus(data) {
        var menus = data && data.menus ? data.menus : data;
        var normalized = {};

        if (!menus || typeof menus !== 'object') {
            throw new Error('Invalid sidebar export.');
        }

        if (Array.isArray(menus)) {
            menus.forEach(function(item, index) {
                if (!item || typeof item !== 'object') {
                    return;
                }

                item.order = item.order === undefined ? index : item.order;
                normalized[item.id || ('imported_' + index)] = item;
            });

            return normalized;
        }

        Object.keys(menus).forEach(function(id, index) {
            var item = menus[id];

            if (!item || typeof item !== 'object') {
                return;
            }

            item.id = item.id || id;
            item.order = item.order === undefined ? index : item.order;
            normalized[item.id] = item;
        });

        return normalized;
    }

    function updateMenuData() {
        removeDuplicateBuilderItems();
        window.fanmMenus = currentMenuItems();
    }

    function queueMenuDataUpdate(delay) {
        window.clearTimeout(menuDataTimer);
        menuDataTimer = window.setTimeout(function() {
            deferIdle(updateMenuData, 600);
        }, delay === undefined ? 120 : delay);
    }

    function handleTreeChange() {
        removeDuplicateBuilderItems();
        initializeExpandCollapse();
        updateMenuData();
        queuePreviewRender();
    }

    function queueTreeChange(delay) {
        window.clearTimeout(treeChangeTimer);
        treeChangeTimer = window.setTimeout(handleTreeChange, delay === undefined ? 80 : delay);
    }

    function bindEvents() {
        $('#fanm-save-all').on('click', function() {
            if (!ensureAjax()) {
                return;
            }

            updateMenuData();

            $.post(ajaxUrl(), ajaxData({
                action: 'fanm_save_menus',
                menus: JSON.stringify(window.fanmMenus)
            }), function(res) {
                if (res.success) {
                    alert(messages('saveSuccess'));
                    window.location.reload();
                    return;
                }

                alert(messages('saveError'));
            });
        });

        $('#fanm-restore-defaults').on('click', function(event) {
            if (!confirm(messages('restoreConfirm'))) {
                event.preventDefault();
            }
        });

        $('#fanm-export-sidebar').on('click', function(event) {
            event.preventDefault();
            exportSidebarLayout();
        });

        $('#fanm-restore-sidebar').on('click', function(event) {
            event.preventDefault();

            if (!confirm(messages('importConfirm'))) {
                return;
            }

            $('#fanm-restore-sidebar-file').trigger('click');
        });

        $('#fanm-restore-sidebar-file').on('change', function() {
            var file = this.files && this.files[0] ? this.files[0] : null;

            if (file) {
                restoreSidebarLayoutFromFile(file);
            }

            this.value = '';
        });

        $('#fanm-expand-all').on('click', function() {
            expandAll();
        });

        $('#fanm-collapse-all').on('click', function() {
            collapseAll();
        });

        $('#fanm-close-preview').on('click', function(event) {
            event.preventDefault();
            setPreviewOpen(false);
        });

        $('#fanm-open-preview').on('click', function(event) {
            event.preventDefault();
            setPreviewOpen(true);
        });

        $(document).on('click', '.fanm-indent-item', function(event) {
            event.preventDefault();
            event.stopPropagation();
            indentItem($(this).closest('li[data-id]'));
        });

        $(document).on('click', '.fanm-outdent-item', function(event) {
            event.preventDefault();
            event.stopPropagation();
            outdentItem($(this).closest('li[data-id]'));
        });

        $(document).on('click', '.fanm-sort-item', function(event) {
            event.preventDefault();
            event.stopPropagation();
            cycleSort($(this).closest('li[data-id]'));
        });

        $(document).on('change', '.fanm-hidden-toggle', function(event) {
            var $item = $(this).closest('li[data-id]');
            var hidden = $(this).is(':checked');

            event.stopPropagation();
            $item.children('.fanm-item-row').find('.hidden').val(hidden ? '1' : '0');
            queueTreeChange();
        });

        $(document).on('click', '.fanm-visibility-toggle', function(event) {
            event.stopPropagation();
        });

        $(document).on('click', '.fanm-menu-title', function(event) {
            event.preventDefault();
            event.stopPropagation();
            beginTitleEdit($(this));
        });

        $(document).on('keydown', '.fanm-menu-title', function(event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            beginTitleEdit($(this));
        });

        $(document).on('click keydown mousedown', '.fanm-title-input', function(event) {
            event.stopPropagation();
        });

        $(document).on('blur', '.fanm-title-input', function() {
            finishTitleEdit($(this), false);
        });

        $(document).on('keydown', '.fanm-title-input', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                finishTitleEdit($(this), false);
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                finishTitleEdit($(this), true);
            }
        });

        $(document).on('click', '.fanm-item-row', function(event) {
            if (isDragging || Date.now() - lastDragStopAt < 250) {
                return;
            }

            var $row = $(this);
            var $handle = $row.find('.fanm-handle');
            var $children = $handle.closest('li').children('ul');

            if (!$children.children('li[data-id]').length) {
                return;
            }

            event.preventDefault();
            $children.toggle();
            $handle.toggleClass('expanded');
            saveCollapsePreference('custom');
        });
    }

    $(function() {
        initNestedSortables();
        initializeExpandCollapse();
        restoreCollapsePreference();
        restorePreviewPreference();
        updateMenuData();
        renderAdminPreview();
        bindEvents();
        preventPreviewNavigation();
        bindPreviewFlyouts();

        if (isDefaultMode()) {
            window.setTimeout(function() {
                importVisibleSidebarItems(true);
            }, 300);
        } else {
            window.setTimeout(importMissingVisibleSidebarItems, 300);
        }
    });
})(jQuery);
