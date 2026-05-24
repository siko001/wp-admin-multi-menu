(function($) {
    'use strict';

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

    function initNestedSortables() {
        $('#fanm-menu-tree ul').each(function() {
            var $ul = $(this);

            if ($ul.hasClass('ui-sortable')) {
                return;
            }

            $ul.sortable({
                items: 'li',
                placeholder: 'fanm-placeholder',
                tolerance: 'pointer',
                cursor: 'move',
                opacity: 0.6,
                connectWith: '#fanm-menu-tree ul',
                handle: '> .fanm-handle',
                update: updateMenuData,
                start: function(event, ui) {
                    ui.placeholder.height(ui.item.height());
                },
                stop: updateMenuData
            });
        });
    }

    function initializeExpandCollapse() {
        $('#fanm-menu-tree li').each(function() {
            var $li = $(this);
            var $children = $li.children('ul');

            if ($children.length > 0) {
                $li.children('.fanm-handle')
                    .addClass('has-children')
                    .attr('title', 'Click to expand/collapse children');
            }
        });
    }

    function updateMenuData() {
        window.fanmMenus = {};

        $('#fanm-menu-tree li[data-id]').each(function() {
            var $li = $(this);
            var $parentLi = $li.parent().closest('li[data-id]');
            var parentId = $parentLi.length > 0 ? $parentLi.data('id') : 0;
            var $fields = $li.children('.fanm-fields');

            window.fanmMenus[$li.data('id')] = {
                id: $li.data('id'),
                title: $fields.find('.title').val(),
                slug: $fields.find('.slug').val(),
                cap: $fields.find('.cap').val(),
                callback: $fields.find('.callback').val(),
                icon: $fields.find('.icon').val(),
                parent: parentId
            };
        });
    }

    function appendMenuItem($parent, html) {
        var $children = $parent.children('ul');

        if (!$children.length) {
            $children = $('<ul class="fanm-level-child" />').appendTo($parent);
        }

        $children.append(html);
        initNestedSortables();
        initializeExpandCollapse();
    }

    function showImportModal() {
        var template = document.getElementById('fanm-import-modal-template');

        if (!template) {
            return;
        }

        $('body').append($(template.innerHTML));
        $('#fanm-import-modal').fadeIn(200);
    }

    function bindEvents() {
        $('#fanm-add-root').on('click', function() {
            if (!ensureAjax()) {
                return;
            }

            $.post(ajaxUrl(), ajaxData({
                action: 'fanm_add_menu',
                parent: 0
            }), function(res) {
                if (res.success) {
                    $('#fanm-menu-tree > ul').append(res.data.html);
                    initNestedSortables();
                    initializeExpandCollapse();
                    return;
                }

                alert(messages('addError'));
            });
        });

        $(document).on('click', '.add-child', function() {
            if (!ensureAjax()) {
                return;
            }

            var $parentLi = $(this).closest('li');

            $.post(ajaxUrl(), ajaxData({
                action: 'fanm_add_menu',
                parent: $parentLi.data('id')
            }), function(res) {
                if (res.success) {
                    appendMenuItem($parentLi, res.data.html);
                    return;
                }

                alert(messages('addError'));
            });
        });

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

        $(document).on('click', '.delete-item', function() {
            if (!ensureAjax() || !confirm(messages('deleteConfirm'))) {
                return;
            }

            var $li = $(this).closest('li');

            $.post(ajaxUrl(), ajaxData({
                action: 'fanm_delete_menu',
                menu_id: $li.data('id')
            }), function(res) {
                if (res.success) {
                    $li.fadeOut(200, function() {
                        $(this).remove();
                    });
                    return;
                }

                alert(messages('deleteError'));
            });
        });

        $('#fanm-import-demo').on('click', function() {
            if (!ensureAjax() || !confirm(messages('demoConfirm'))) {
                return;
            }

            $.post(ajaxUrl(), ajaxData({
                action: 'fanm_import_demo'
            }), function(res) {
                if (res.success) {
                    window.location.reload();
                    return;
                }

                alert(messages('demoError'));
            });
        });

        $('#fanm-export-json').on('click', function() {
            if (!ensureAjax()) {
                return;
            }

            $.post(ajaxUrl(), ajaxData({
                action: 'fanm_export_json'
            }), function(res) {
                if (res.success) {
                    var dataStr = 'data:text/json;charset=utf-8,' + encodeURIComponent(res.data.data);
                    var downloadAnchorNode = document.createElement('a');
                    downloadAnchorNode.setAttribute('href', dataStr);
                    downloadAnchorNode.setAttribute('download', 'fanm-menus-export.json');
                    document.body.appendChild(downloadAnchorNode);
                    downloadAnchorNode.click();
                    downloadAnchorNode.remove();
                    return;
                }

                alert(messages('exportError'));
            });
        });

        $('#fanm-import-json').on('click', showImportModal);

        $(document).on('click', '.fanm-modal-close, #fanm-cancel-import', function() {
            $('.fanm-modal').fadeOut(200, function() {
                $(this).remove();
            });
        });

        $(document).on('click', '#fanm-do-import', function() {
            if (!ensureAjax()) {
                return;
            }

            var jsonData = $('#fanm-import-textarea').val();

            if (!jsonData.trim()) {
                alert(messages('jsonRequired'));
                return;
            }

            $.post(ajaxUrl(), ajaxData({
                action: 'fanm_import_json',
                json_data: jsonData
            }), function(res) {
                if (res.success) {
                    $('.fanm-modal').fadeOut(200, function() {
                        $(this).remove();
                    });
                    window.location.reload();
                    return;
                }

                alert(messages('importError') + ' ' + (res.data || 'Invalid JSON'));
            });
        });

        $(document).on('click', '.fanm-handle', function(event) {
            event.preventDefault();

            var $handle = $(this);
            var $children = $handle.closest('li').children('ul');

            if (!$children.length) {
                return;
            }

            $children.toggle();
            $handle.toggleClass('expanded');
        });
    }

    $(function() {
        initNestedSortables();
        initializeExpandCollapse();
        bindEvents();
    });
})(jQuery);

