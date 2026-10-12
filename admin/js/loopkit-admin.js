/**
 * Loopkit Admin JavaScript
 * Handles AJAX interactions for Tools, Templates, and System Info pages.
 * Styled modals replace native alert, confirm, and prompt dialogs.
 *
 * @package Loopkit
 */

(function ($) {
    'use strict';

    var LK = window.loopkitAdmin || {};

    /**
     * Initialize color pickers.
     */
    function initColorPickers() {
        if ($.fn.wpColorPicker) {
            $('.lk-color-picker').wpColorPicker();
        }
    }

    /**
     * Tools: Clear Cache button.
     */
    function initClearCache() {
        $('#lk-clear-cache').on('click', function () {
            var $btn = $(this);
            var $result = $('#lk-cache-result');

            $btn.prop('disabled', true);
            $result.removeClass('lk-result-error lk-result-success').text('Clearing cache...');

            $.post(LK.ajaxUrl, {
                action: 'loopkit_clear_cache',
                nonce: LK.toolsNonce
            })
            .done(function (response) {
                if (response.success) {
                    $result
                        .removeClass('lk-result-error')
                        .addClass('lk-result-success')
                        .text(response.data.message);
                } else {
                    $result
                        .removeClass('lk-result-success')
                        .addClass('lk-result-error')
                        .text(response.data.message || 'An error occurred.');
                }
            })
            .fail(function () {
                $result
                    .removeClass('lk-result-success')
                    .addClass('lk-result-error')
                    .text('An error occurred.');
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
        });
    }

    /**
     * Tools: Replace URL button.
     */
    function initReplaceUrl() {
        $('#lk-replace-url').on('click', function () {
            var oldUrl = $('#lk-old-url').val().trim();
            var newUrl = $('#lk-new-url').val().trim();
            var $result = $('#lk-replace-result');

            if (!oldUrl || !newUrl) {
                $result
                    .removeClass('lk-result-success')
                    .addClass('lk-result-error')
                    .text('Both URLs are required.');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true);

            $.post(LK.ajaxUrl, {
                action: 'loopkit_replace_url',
                nonce: LK.toolsNonce,
                old_url: oldUrl,
                new_url: newUrl
            })
            .done(function (response) {
                if (response.success) {
                    $result
                        .removeClass('lk-result-error')
                        .addClass('lk-result-success')
                        .text(response.data.message);
                } else {
                    $result
                        .removeClass('lk-result-success')
                        .addClass('lk-result-error')
                        .text(response.data.message || 'An error occurred.');
                }
            })
            .fail(function () {
                $result
                    .removeClass('lk-result-success')
                    .addClass('lk-result-error')
                    .text('An error occurred.');
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
        });
    }

    /**
     * Modal helper functions.
     */
    function openModal($modal) {
        $modal.fadeIn(150);
        $('body').addClass('lk-modal-open');
    }

    function closeModal($modal) {
        $modal.fadeOut(150);
        $('body').removeClass('lk-modal-open');
    }

    function initModals() {
        // Close modal on X button or Cancel button
        $(document).on('click', '.lk-admin-modal-close, .lk-modal-cancel', function () {
            var $modal = $(this).closest('.lk-admin-modal-backdrop');
            closeModal($modal);
        });

        // Close on clicking backdrop outside the modal dialog
        $(document).on('click', '.lk-admin-modal-backdrop', function (e) {
            if ($(e.target).hasClass('lk-admin-modal-backdrop')) {
                closeModal($(this));
            }
        });

        // Close on Escape key
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                var $openModal = $('.lk-admin-modal-backdrop:visible');
                if ($openModal.length) {
                    closeModal($openModal);
                }
            }
        });
    }

    /**
     * Templates page interactions: Create, Rename, Duplicate, Delete modals.
     */
    function initTemplates() {
        var $createModal = $('#lk-create-modal');
        var $renameModal = $('#lk-rename-modal');
        var $deleteModal = $('#lk-delete-modal');

        // 1. Add New Template: Open styled modal
        $('#lk-template-type-select').on('change', function () {
            var t = $(this).val();
            if (t === 'section' || t === 'container') {
                $('#lk-make-global-group').show();
            } else {
                $('#lk-make-global-group').hide();
                $('#lk-template-is-global').prop('checked', false);
            }
        });

        $('#lk-add-template').on('click', function (e) {
            e.preventDefault();
            $('#lk-template-name-input').val('');
            $('#lk-create-modal-error').hide().text('');
            var curType = $('#lk-template-type-select').val();
            if (curType === 'section' || curType === 'container') {
                $('#lk-make-global-group').show();
            } else {
                $('#lk-make-global-group').hide();
            }
            $('#lk-template-is-global').prop('checked', false);
            openModal($createModal);
            setTimeout(function () {
                $('#lk-template-name-input').focus();
            }, 160);
        });

        // Submit template creation
        $('#lk-confirm-create-template').on('click', function () {
            var title = $('#lk-template-name-input').val().trim();
            var type = $('#lk-template-type-select').val();
            var isGlobal = ($('#lk-template-is-global').is(':visible') && $('#lk-template-is-global').is(':checked')) ? 1 : 0;
            var $btn = $(this);
            var $error = $('#lk-create-modal-error');

            if (!title) {
                $error.text('Please enter a template name.').show();
                $('#lk-template-name-input').focus();
                return;
            }

            $btn.prop('disabled', true).addClass('lk-btn-loading');
            $error.hide();

            $.post(LK.ajaxUrl, {
                action: 'loopkit_create_template',
                nonce: LK.templatesNonce,
                title: title,
                template_type: type,
                is_global: isGlobal
            })
            .done(function (response) {
                if (response.success && response.data && response.data.edit_url) {
                    // Navigate directly to the Loopkit editor
                    window.location.href = response.data.edit_url;
                } else {
                    $error.text(response.data && response.data.message ? response.data.message : 'Error creating template.').show();
                    $btn.prop('disabled', false).removeClass('lk-btn-loading');
                }
            })
            .fail(function () {
                $error.text('Server communication error. Please try again.').show();
                $btn.prop('disabled', false).removeClass('lk-btn-loading');
            });
        });

        // Allow Enter key to submit inside create input
        $('#lk-template-name-input').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#lk-confirm-create-template').trigger('click');
            }
        });

        // Make Global action on existing template
        $(document).on('click', '.lk-make-global-template', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');

            $btn.prop('disabled', true).text('Updating...');

            $.post(LK.ajaxUrl, {
                action: 'loopkit_make_global_template',
                nonce: LK.templatesNonce,
                template_id: id
            })
            .done(function (response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alertModal(response.data && response.data.message ? response.data.message : 'Error converting template to global.');
                    $btn.prop('disabled', false).text('Make Global');
                }
            })
            .fail(function () {
                alertModal('Server communication error.');
                $btn.prop('disabled', false).text('Make Global');
            });
        });

        // 2. Rename Template
        $(document).on('click', '.lk-rename-template', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');
            var title = $btn.data('title');

            $('#lk-rename-id-input').val(id);
            $('#lk-rename-name-input').val(title);
            $('#lk-rename-modal-error').hide().text('');
            openModal($renameModal);
            setTimeout(function () {
                $('#lk-rename-name-input').focus().select();
            }, 160);
        });

        $('#lk-confirm-rename-template').on('click', function () {
            var id = $('#lk-rename-id-input').val();
            var title = $('#lk-rename-name-input').val().trim();
            var $btn = $(this);
            var $error = $('#lk-rename-modal-error');

            if (!title) {
                $error.text('Template name cannot be empty.').show();
                $('#lk-rename-name-input').focus();
                return;
            }

            $btn.prop('disabled', true).addClass('lk-btn-loading');
            $error.hide();

            $.post(LK.ajaxUrl, {
                action: 'loopkit_rename_template',
                nonce: LK.templatesNonce,
                template_id: id,
                title: title
            })
            .done(function (response) {
                if (response.success) {
                    // Update table row
                    var $row = $('tr[data-template-id="' + id + '"]');
                    $row.find('.lk-template-title-link strong').text(title);
                    $row.find('.lk-rename-template').data('title', title);
                    $row.find('.lk-delete-template').data('title', title);
                    $row.attr('data-template-title', title);
                    closeModal($renameModal);
                } else {
                    $error.text(response.data && response.data.message ? response.data.message : 'Error renaming template.').show();
                }
            })
            .fail(function () {
                $error.text('Server communication error. Please try again.').show();
            })
            .always(function () {
                $btn.prop('disabled', false).removeClass('lk-btn-loading');
            });
        });

        $('#lk-rename-name-input').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#lk-confirm-rename-template').trigger('click');
            }
        });

        // 3. Duplicate Template
        $(document).on('click', '.lk-duplicate-template', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');

            $btn.prop('disabled', true);

            $.post(LK.ajaxUrl, {
                action: 'loopkit_duplicate_template',
                nonce: LK.templatesNonce,
                template_id: id
            })
            .done(function (response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    $btn.prop('disabled', false);
                }
            })
            .fail(function () {
                $btn.prop('disabled', false);
            });
        });

        // 4. Delete Template: Open styled confirmation modal
        $(document).on('click', '.lk-delete-template', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');
            var title = $btn.data('title') || $btn.closest('tr').find('.lk-template-title-link strong').text();

            $('#lk-delete-id-input').val(id);
            $('#lk-delete-item-title').text(title);
            $('#lk-delete-modal-error').hide().text('');
            openModal($('#lk-delete-modal'));
        });

        $('#lk-confirm-delete-template').on('click', function () {
            var id = $('#lk-delete-id-input').val();
            var $btn = $(this);
            var $error = $('#lk-delete-modal-error');
            var $deleteModal = $('#lk-delete-modal');

            $error.hide().text('');
            $btn.prop('disabled', true).addClass('lk-btn-loading');

            $.ajax({
                url: LK.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'loopkit_delete_template',
                    nonce: LK.templatesNonce,
                    template_id: id
                },
                dataType: 'text'
            })
            .done(function (raw) {
                var response;
                try {
                    var cleaned = typeof raw === 'string' ? raw.trim().replace(/^\uFEFF/, '') : raw;
                    response = typeof cleaned === 'object' ? cleaned : JSON.parse(cleaned);
                } catch (err) {
                    $error.text(LK.strings.error || 'An error occurred. Please try again.').show();
                    return;
                }

                if (response.success) {
                    closeModal($deleteModal);
                    var $row = $('tr[data-template-id="' + id + '"]');
                    $row.fadeOut(250, function () {
                        $row.remove();
                        if ($('#lk-templates-table tbody tr').length === 0) {
                            $('#lk-templates-table').addClass('lk-hidden');
                            var emptyHtml = '<div class="lk-empty-state" id="lk-templates-empty">';
                            emptyHtml += '<span class="dashicons dashicons-media-document"></span>';
                            emptyHtml += '<p>No templates found. Click "Add New" to create your first template.</p>';
                            emptyHtml += '</div>';
                            $('#lk-templates-card').prepend(emptyHtml);
                        }
                    });

                    // Update tab counts if returned
                    if (response.data && response.data.counts) {
                        var counts = response.data.counts;
                        $('.lk-templates-nav a').each(function () {
                            var href = $(this).attr('href') || '';
                            var match = href.match(/type=([a-z]+)/);
                            var type = match ? match[1] : 'all';
                            if (typeof counts[type] !== 'undefined') {
                                $(this).find('.lk-tab-count').text('(' + counts[type] + ')');
                            }
                        });
                    }
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : (LK.strings.error || 'Failed to delete template.');
                    $error.text(msg).show();
                }
            })
            .fail(function (xhr, status, error) {
                $error.text(LK.strings.error || 'An error occurred. Please try again.').show();
            })
            .always(function () {
                $btn.prop('disabled', false).removeClass('lk-btn-loading');
            });
        });

        // 5. Edit Conditions Modal
        var $conditionsModal = $('#lk-admin-conditions-modal');
        var $conditionsList = $('#lk-admin-conditions-list');
        var $conditionsNotice = $('#lk-admin-conditions-conflict-notice');

        function createAdminConditionRow(data) {
            data = data || { type: 'include', name: 'general', sub: 'all', id: '' };
            var type = data.type || 'include';
            var name = data.name || 'general';
            var sub = data.sub || 'all';
            var id = data.id || data.item || '';

            var $row = $('<div class="lk-cond-row"></div>');

            // Type select
            var $typeSelect = $('<select class="lk-cond-select lk-cond-type"></select>');
            $typeSelect.append('<option value="include">INCLUDE</option>');
            $typeSelect.append('<option value="exclude">EXCLUDE</option>');
            $typeSelect.val(type);
            $typeSelect.addClass(type === 'exclude' ? 'lk-type-exclude' : 'lk-type-include');
            $typeSelect.on('change', function() {
                var v = $(this).val();
                $(this).removeClass('lk-type-include lk-type-exclude')
                       .addClass(v === 'exclude' ? 'lk-type-exclude' : 'lk-type-include');
            });

            // Name select
            var $nameSelect = $('<select class="lk-cond-select lk-cond-name"></select>');
            $nameSelect.append('<option value="general">Entire Site</option>');
            $nameSelect.append('<option value="singular">Singular</option>');
            $nameSelect.append('<option value="archive">Archives</option>');
            $nameSelect.val(name);

            // Sub select
            var $subSelect = $('<select class="lk-cond-select lk-cond-sub"></select>');

            // Item select
            var $itemSelect = $('<select class="lk-cond-select lk-cond-item"></select>');

            function updateSubOptions() {
                var n = $nameSelect.val();
                $subSelect.empty();
                if (n === 'general') {
                    $subSelect.hide();
                    $itemSelect.hide();
                } else if (n === 'singular') {
                    $subSelect.show();
                    $subSelect.append('<option value="all">All Singular</option>');
                    $subSelect.append('<option value="front_page">Front Page</option>');
                    $subSelect.append('<option value="post">Posts</option>');
                    $subSelect.append('<option value="page">Pages</option>');
                    $subSelect.append('<option value="404">404 Page</option>');
                    if (sub) $subSelect.val(sub);
                    updateItemOptions();
                } else if (n === 'archive') {
                    $subSelect.show();
                    $subSelect.append('<option value="all">All Archives</option>');
                    $subSelect.append('<option value="posts_archive">Posts Archive</option>');
                    $subSelect.append('<option value="category">Categories</option>');
                    $subSelect.append('<option value="post_tag">Tags</option>');
                    $subSelect.append('<option value="author">Author</option>');
                    $subSelect.append('<option value="date">Date</option>');
                    $subSelect.append('<option value="search">Search Results</option>');
                    if (sub) $subSelect.val(sub);
                    updateItemOptions();
                }
            }

            function updateItemOptions() {
                var s = $subSelect.val();
                if (s === 'page' || s === 'post' || s === 'category' || s === 'post_tag' || s === 'author') {
                    $itemSelect.show().empty().append('<option value="">All</option>');
                    $.get(LK.ajaxUrl, {
                        action: 'loopkit_search_condition_items',
                        nonce: LK.templatesNonce,
                        sub: s,
                        q: ''
                    }, function(res) {
                        if (res.success && res.data && res.data.results) {
                            res.data.results.forEach(function(item) {
                                var $opt = $('<option></option>').val(item.id).text(item.text);
                                if (id && parseInt(id, 10) === parseInt(item.id, 10)) {
                                    $opt.prop('selected', true);
                                }
                                $itemSelect.append($opt);
                            });
                            if (id) {
                                $itemSelect.val(id);
                            }
                        }
                    });
                } else {
                    $itemSelect.hide().empty();
                }
            }

            $nameSelect.on('change', function() {
                sub = 'all';
                id = '';
                updateSubOptions();
            });

            $subSelect.on('change', function() {
                id = '';
                updateItemOptions();
            });

            updateSubOptions();

            // Remove button
            var $removeBtn = $('<button type="button" class="lk-cond-remove-btn" title="Remove condition">&times;</button>');
            $removeBtn.on('click', function() {
                $row.remove();
                if ($conditionsList.children().length === 0) {
                    $conditionsList.append(createAdminConditionRow({ type: 'include', name: 'general' }));
                }
            });

            $row.append($typeSelect).append($nameSelect).append($subSelect).append($itemSelect).append($removeBtn);
            return $row;
        }

        $(document).on('click', '.lk-edit-conditions', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');
            var type = $btn.data('type');
            var title = $btn.data('title');

            $('#lk-admin-cond-template-id').val(id);
            $('#lk-admin-cond-template-type').val(type);
            $('#lk-admin-conditions-title').text('Display Conditions — ' + title);
            $conditionsNotice.hide().empty();
            $conditionsList.html('<p class="lk-muted">Loading conditions...</p>');
            openModal($conditionsModal);

            $.get(LK.ajaxUrl, {
                action: 'loopkit_get_conditions',
                nonce: LK.templatesNonce,
                template_id: id
            }).done(function(res) {
                $conditionsList.empty();
                if (res.success && res.data) {
                    var conditions = res.data.conditions || [];
                    if (conditions.length === 0) {
                        conditions = [{ type: 'include', name: 'general' }];
                    }
                    conditions.forEach(function(cond) {
                        $conditionsList.append(createAdminConditionRow(cond));
                    });

                    if (res.data.conflicts && res.data.conflicts.length > 0) {
                        var noticeHtml = '';
                        res.data.conflicts.forEach(function(c) {
                            noticeHtml += '<p><span class="dashicons dashicons-warning"></span> ' + c + '</p>';
                        });
                        $conditionsNotice.html(noticeHtml).show();
                    }
                } else {
                    $conditionsList.append(createAdminConditionRow({ type: 'include', name: 'general' }));
                }
            }).fail(function() {
                $conditionsList.empty();
                $conditionsList.append(createAdminConditionRow({ type: 'include', name: 'general' }));
            });
        });

        $('#lk-admin-add-condition-btn').on('click', function(e) {
            e.preventDefault();
            $conditionsList.append(createAdminConditionRow({ type: 'include', name: 'general' }));
        });

        $('#lk-admin-save-conditions-btn').on('click', function() {
            var $btn = $(this);
            var id = $('#lk-admin-cond-template-id').val();
            var conditions = [];

            $conditionsList.find('.lk-cond-row').each(function() {
                var type = $(this).find('.lk-cond-type').val();
                var name = $(this).find('.lk-cond-name').val();
                var sub = $(this).find('.lk-cond-sub').val();
                var item = $(this).find('.lk-cond-item').val();

                var row = { type: type, name: name };
                if (name !== 'general') {
                    row.sub = sub || 'all';
                    if (item) {
                        row.id = parseInt(item, 10);
                        row.item = parseInt(item, 10);
                    }
                }
                conditions.push(row);
            });

            if (conditions.length === 0) {
                conditions = [{ type: 'include', name: 'general' }];
            }

            $btn.prop('disabled', true).addClass('lk-btn-loading');

            $.post(LK.ajaxUrl, {
                action: 'loopkit_save_conditions',
                nonce: LK.templatesNonce,
                template_id: id,
                conditions: conditions,
                publish: 1
            }).done(function(res) {
                if (res.success && res.data) {
                    var summary = res.data.summary || 'Entire Site';
                    var $row = $('tr[data-template-id="' + id + '"]');
                    $row.find('.lk-conditions-text').text(summary).removeClass('lk-cond-draft');
                    closeModal($conditionsModal);
                } else {
                    $conditionsNotice.html('<p><span class="dashicons dashicons-warning"></span> ' + (res.data && res.data.message ? res.data.message : 'Error saving conditions.') + '</p>').show();
                }
            }).fail(function() {
                $conditionsNotice.html('<p><span class="dashicons dashicons-warning"></span> Server error saving conditions. Please try again.</p>').show();
            }).always(function() {
                $btn.prop('disabled', false).removeClass('lk-btn-loading');
            });
        });
    }

    /**
     * System Info: Copy to clipboard.
     */
    function initSystemInfo() {
        $('#lk-copy-sysinfo').on('click', function () {
            var text = $('#lk-sysinfo-text').val();
            var $btn = $(this);

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    showCopied($btn);
                });
            } else {
                // Fallback
                var $textarea = $('#lk-sysinfo-text');
                $textarea.removeClass('lk-hidden');
                $textarea[0].select();
                document.execCommand('copy');
                $textarea.addClass('lk-hidden');
                showCopied($btn);
            }
        });

        function showCopied($btn) {
            var originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-yes"></span> ' + LK.strings.copied);
            setTimeout(function () {
                $btn.html(originalHtml);
            }, 2000);
        }
    }

    /**
     * DOM ready.
     */
    $(function () {
        initColorPickers();
        initClearCache();
        initReplaceUrl();
        initModals();
        initTemplates();
        initSystemInfo();
    });

})(jQuery);
