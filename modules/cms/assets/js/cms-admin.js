/**
 * Loopkit CMS admin JavaScript.
 *
 * Handles AJAX save/delete/reorder for collections, fields, and items.
 */
(function ($, window) {
    'use strict';

    $(document).ready(function () {

        // ── Confirm dialogs ───────────────────────────────────────────
        // Confirmation lives in the markup (inline onclick="return confirm…"),
        // so both the AJAX path and the plain form-POST fallback ask exactly
        // once. If the user declined, the inline handler cancelled the default
        // and e.defaultPrevented is already true — do nothing.
        // idKey is the parameter the matching wp_ajax handler reads, and the
        // row is only removed after the server confirms the delete.
        function ajaxDelete(action, id, idKey, msgSelector, $row) {
            var payload = { action: action, nonce: LoopkitCMS.nonce };
            payload[idKey] = id;
            $.post(LoopkitCMS.ajaxurl, payload)
            .done(function (r) {
                if (r && r.success) {
                    $(msgSelector).append('<div class="updated"><p>' + ((r.data && r.data.msg) || LoopkitCMS.messages.deleted) + '</p></div>');
                    if ($row && $row.length) {
                        $row.addClass('pending-delete').fadeOut(300, function () { $(this).remove(); });
                    }
                } else {
                    $(msgSelector).append('<div class="error"><p>' + ((r && r.data && r.data.msg) || LoopkitCMS.messages.error) + '</p></div>');
                    if ($row) $row.removeClass('pending-delete');
                }
            }).fail(function () {
                $(msgSelector).append('<div class="error"><p>' + LoopkitCMS.messages.error + '</p></div>');
                if ($row) $row.removeClass('pending-delete');
            });
        }

        $(document).on('click', '.delete-collection', function (e) {
            if (e.defaultPrevented) return; // confirm() was declined in the markup
            e.preventDefault();
            var $row = $(this).closest('tr');
            $row.addClass('pending-delete');
            ajaxDelete('lk_cms_delete_collection', $(this).data('id'), 'collection_id', '.cms-messages', $row);
        });

        $(document).on('click', '.delete-field', function (e) {
            if (e.defaultPrevented) return;
            e.preventDefault();
            var $row = $(this).closest('tr');
            $row.addClass('pending-delete');
            ajaxDelete('lk_cms_delete_field', $(this).data('id'), 'field_id', '.cms-messages', $row);
        });

        $(document).on('click', '.lk-items-table .delete-item', function (e) {
            if (e.defaultPrevented) return;
            e.preventDefault();
            var $row = $(this).closest('tr');
            $row.addClass('pending-delete');
            ajaxDelete('lk_cms_delete_item', $(this).data('id'), 'item_id', '.items-messages', $row);
        });

        // ── Inline field editor ───────────────────────────────────────
        $(document).on('click', '.edit-field', function (e) {
            e.preventDefault();
            var $el = $(this);
            var fieldId = $el.data('id');
            var type = $el.data('type') || 'text';
            var val = $el.data('value') || '';

            var $editor = $('<div class="field-editor"></div>');
            var $input;
            switch (type) {
                case 'textarea':
                    $input = $('<textarea class="widefat">' + val + '</textarea>');
                    break;
                case 'number':
                    $input = $('<input type="number" class="regular-text" value="' + val + '">');
                    break;
                case 'email':
                    $input = $('<input type="email" class="regular-text" value="' + val + '">');
                    break;
                case 'url':
                    $input = $('<input type="url" class="regular-text" value="' + val + '">');
                    break;
                case 'checkbox':
                    $input = $('<input type="checkbox" class="small-text">');
                    if (val == '1') $input.prop('checked', true);
                    break;
                default:
                    $input = $('<input type="text" class="regular-text" value="' + val + '">');
                    break;
            }
            $editor.append($input);
            $editor.append('<div class="field-editor-actions"><button class="button button-secondary" onclick="$.fn.fieldEditorCancel.call(this)">' + LoopkitCMS.messages.cancel + '</button> <button class="button button-primary" onclick="$.fn.fieldEditorSave.call(this)">' + LoopkitCMS.messages.save + '</button></div>');
            $el.replaceWith($editor);

            $(document).on('click', '.field-editor .button-primary', function () {
                var v = $editor.find('textarea, input').val();
                saveFieldValue(fieldId, v);
            });
            $(document).on('click', '.field-editor .button-secondary', function () {
                $('.field-editor').remove();
                $(document).off('click', '.field-editor .button-primary');
                $(document).off('click', '.field-editor .button-secondary');
            });

        });

        // ── Field editor helpers ──────────────────────────────────────
        function saveFieldValue(fieldId, val) {
            var $editor = $('.field-editor');
            if (!$editor.length) return;
            var $btn = $editor.find('.button-primary');
            $btn.prop('disabled', true).text(LoopkitCMS.messages.saving);
            $.post(LoopkitCMS.ajaxurl, {
                action: 'lk_cms_save_field',
                nonce: LoopkitCMS.nonce,
                field_id: fieldId,
                label: $editor.data('label'),
                type: $editor.data('type'),
                value: val
            }).done(function (r) {
                if (r && r.success) {
                    var saved = (r.data && typeof r.data.value !== 'undefined') ? r.data.value : val;
                    $editor.replaceWith($('<span class="field-value"></span>').text(saved));
                    $('.cms-messages').append('<div class="updated"><p>' + LoopkitCMS.messages.saved + '</p></div>');
                } else {
                    $editor.find('.button-primary').prop('disabled', false).text(LoopkitCMS.messages.save);
                    $('.cms-messages').append('<div class="error"><p>' + LoopkitCMS.messages.error + '</p></div>');
                }
            }).fail(function () {
                $editor.find('.button-primary').prop('disabled', false).text(LoopkitCMS.messages.save);
                $('.cms-messages').append('<div class="error"><p>' + LoopkitCMS.messages.error + '</p></div>');
            });
        }

        // ── Collection icon picker preview ─────────────────────────────
        $(document).on('change', '#lk-cms-icon', function () {
            $('#lk-cms-icon-preview').attr('class', 'dashicons ' + (this.value || 'dashicons-database'));
        });

        // ── Global AJAX helpers ───────────────────────────────────────
        window.loopkitCmsSave = function (action, data) {
            $.post(LoopkitCMS.ajaxurl, $.extend({ action: action, nonce: LoopkitCMS.nonce }, data))
            .done(function (r) {
                if (r && r.success) {
                    $('.cms-messages').append('<div class="updated"><p>' + r.data.msg + '</p></div>');
                    location.reload();
                } else {
                    $('.cms-messages').append('<div class="error"><p>' + (r.data.msg || LoopkitCMS.messages.error) + '</p></div>');
                }
            }).fail(function () {
                $('.cms-messages').append('<div class="error"><p>' + LoopkitCMS.messages.error + '</p></div>');
            });
        };

        window.loopkitCmsDelete = function (action, id) {
            $.post(LoopkitCMS.ajaxurl, { action: action, nonce: LoopkitCMS.nonce, id: id })
            .done(function (r) {
                if (r && r.success) {
                    $('.cms-messages').append('<div class="updated"><p>' + r.data.msg + '</p></div>');
                    location.reload();
                } else {
                    $('.cms-messages').append('<div class="error"><p>' + (r.data.msg || LoopkitCMS.messages.error) + '</p></div>');
                }
            }).fail(function () {
                $('.cms-messages').append('<div class="error"><p>' + LoopkitCMS.messages.error + '</p></div>');
            });
        };

        // ── Stage C: items table ────────────────────────────────────
        var $screen = $('.lk-cms-items-screen');
        if ($screen.length) {

            function itemsMsg(kind, text) {
                var $box = $('<div></div>').addClass(kind).append($('<p></p>').text(text));
                $('.items-messages').append($box);
            }

            // Row numbers follow DOM order (rows get added/duplicated live).
            function renumber() {
                var offset = ((parseInt($screen.data('page'), 10) || 1) - 1) * (parseInt($screen.data('perPage'), 10) || 50);
                $('.lk-items-table tbody tr[data-id]').each(function (i) {
                    $(this).find('.lk-cms-rownum').text(offset + i + 1);
                });
            }

            // ── Cell saving ─────────────────────────────────────────
            function cellValue($i) {
                if ($i.is(':checkbox')) return $i.prop('checked') ? '1' : '0';
                return String($i.val());
            }

            // The last server-confirmed value, so a failed save can put
            // the input back to something the database actually holds.
            function rememberCell($i) {
                $i.data('prev', cellValue($i));
            }

            function revertCell($i) {
                var prev = $i.data('prev');
                if (prev === undefined) return;
                if ($i.is(':checkbox')) $i.prop('checked', prev === '1');
                else $i.val(prev);
            }

            function saveCell($i) {
                var $td = $i.closest('td.lk-cms-cell');
                if (!$td.length) return;
                var val = cellValue($i);
                if ($i.data('prev') !== undefined && String($i.data('prev')) === val) return; // no change
                $td.removeClass('is-error').addClass('is-saving');
                $.post(LoopkitCMS.ajaxurl, {
                    action: 'lk_cms_inline_update',
                    nonce: LoopkitCMS.nonce,
                    item_id: $td.data('item'),
                    field_key: $td.data('key'),
                    value: val
                }).done(function (r) {
                    $td.removeClass('is-saving');
                    if (r && r.success) {
                        var saved = (r.data && r.data.value !== undefined && r.data.value !== null) ? String(r.data.value) : val;
                        $i.data('prev', saved);
                        if ($i.is(':checkbox')) $i.prop('checked', saved === '1');
                        else if (!$i.is(':hidden')) $i.val(saved);
                        $td.addClass('is-saved');
                        setTimeout(function () { $td.removeClass('is-saved'); }, 900);
                    } else {
                        revertCell($i);
                        $td.addClass('is-error');
                        itemsMsg('error', (r && r.data && r.data.msg) || LoopkitCMS.messages.error);
                        setTimeout(function () { $td.removeClass('is-error'); }, 2500);
                    }
                }).fail(function () {
                    $td.removeClass('is-saving');
                    revertCell($i);
                    $td.addClass('is-error');
                    itemsMsg('error', LoopkitCMS.messages.error);
                    setTimeout(function () { $td.removeClass('is-error'); }, 2500);
                });
            }

            // Capture the pristine value the first time a cell is touched
            // (covers rows injected after load as well as static ones).
            $(document).on('focus', '.lk-cms-items-screen .lk-cell-input', function () {
                if ($(this).data('prev') === undefined) rememberCell($(this));
            });

            $(document).on('change', '.lk-cms-items-screen .lk-cell-input', function () {
                saveCell($(this));
            });

            // Enter commits just the cell (spreadsheet habit) instead of
            // submitting the whole page form.
            $(document).on('keydown', '.lk-items-form input.lk-cell-input', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $(this).blur();
                }
            });

            // ── Filters: collection switch and page size apply at once ──
            $(document).on('change', '.lk-cms-items-screen #lk-items-collection, .lk-cms-items-screen #lk-items-perpage', function () {
                $(this).closest('form').trigger('submit');
            });

            // ── Add row (bottom) ─────────────────────────────────────
            // AJAX creates the row and drops it right above the button —
            // without JS the same button posts row_action="new:0".
            $(document).on('click', '.lk-cms-add-row', function (e) {
                if (e.defaultPrevented) return;
                e.preventDefault();
                var $btn = $(this).prop('disabled', true);
                $.post(LoopkitCMS.ajaxurl, {
                    action: 'lk_cms_save_item',
                    nonce: LoopkitCMS.nonce,
                    collection_id: $screen.data('collection'),
                    item_id: 0
                }).done(function (r) {
                    $btn.prop('disabled', false);
                    if (r && r.success && r.data && r.data.row_html) {
                        var $tbody = $('.lk-items-table tbody');
                        $tbody.find('.lk-cms-emptyrow').remove();
                        var $row = $(r.data.row_html);
                        $row.insertBefore($tbody.find('.lk-cms-addrow'));
                        renumber();
                        itemsMsg('updated', r.data.msg || LoopkitCMS.messages.saved);
                        $row.find('.lk-cell-input').first().focus();
                    } else {
                        itemsMsg('error', (r && r.data && r.data.msg) || LoopkitCMS.messages.error);
                    }
                }).fail(function () {
                    $btn.prop('disabled', false);
                    itemsMsg('error', LoopkitCMS.messages.error);
                });
            });

            // ── Duplicate row ────────────────────────────────────────
            // Without JS this posts row_action="duplicate:{id}".
            $(document).on('click', '.lk-items-table .lk-cms-dup', function (e) {
                if (e.defaultPrevented) return;
                e.preventDefault();
                var $btn = $(this).prop('disabled', true);
                var $row = $btn.closest('tr');
                $.post(LoopkitCMS.ajaxurl, {
                    action: 'lk_cms_duplicate_item',
                    nonce: LoopkitCMS.nonce,
                    item_id: $btn.data('id')
                }).done(function (r) {
                    $btn.prop('disabled', false);
                    if (r && r.success && r.data && r.data.row_html) {
                        $(r.data.row_html).insertAfter($row);
                        renumber();
                        itemsMsg('updated', r.data.msg || LoopkitCMS.messages.saved);
                    } else {
                        itemsMsg('error', (r && r.data && r.data.msg) || LoopkitCMS.messages.error);
                    }
                }).fail(function () {
                    $btn.prop('disabled', false);
                    itemsMsg('error', LoopkitCMS.messages.error);
                });
            });

            // ── Single-item editor (slide-over) ──────────────────────
            var $editor = $('#lk-cms-item-editor');
            if ($editor.length) {

                function openEditor() {
                    $editor.addClass('is-open');
                    $('body').addClass('lk-cms-editor-open');
                    if (window.tinymce) {
                        try { window.tinymce.triggerSave(); } catch (err) {}
                        $.each(window.tinymce.editors || [], function (i, ed) {
                            try { ed.execCommand('mceRepaint'); } catch (err2) {}
                        });
                    }
                    $(window).trigger('resize');
                    $editor.find('.lk-cms-editor-body').find(':input:visible').first().focus();
                }

                function closeEditor() {
                    $editor.removeClass('is-open');
                    $('body').removeClass('lk-cms-editor-open');
                }

                // Server opened it (no-JS / direct link) — lock the page too.
                if ($editor.hasClass('is-open')) $('body').addClass('lk-cms-editor-open');

                function setEditorImage($input, url) {
                    $input.val(url || '');
                    var $wrap = $input.closest('.lk-cms-editor-img');
                    $wrap.toggleClass('has-value', !!url);
                    var $prev = $wrap.find('.lk-cms-editor-img-preview');
                    paintMedia($prev, url, $prev.data('icon') || 'dashicons-format-image');
                }

                function setEditorValue(key, value) {
                    var $el = $editor.find('[name="data[' + key + ']"]');
                    if (!$el.length) return;
                    if ($el.is(':checkbox')) { $el.prop('checked', String(value) === '1'); return; }
                    if ($el.hasClass('lk-cms-editor-img-input')) { setEditorImage($el, value); return; }
                    $el.val(value);
                    var edId = $el.attr('id');
                    if (window.tinymce && edId && window.tinymce.get(edId)) {
                        try { window.tinymce.get(edId).setContent(String(value || '')); } catch (err) {}
                    }
                }

                // Open for a row: read every value straight out of that row's
                // cells (the long fields carry their full value in data-value).
                // One panel serves every row, so opening it has to fill in the
                // stepper and the row actions too — they are part of the row,
                // not part of the panel. Rendering them server-side only when
                // ?item_id= was in the URL meant they never showed for anyone
                // clicking Edit, which is how the panel is actually opened.
                function fillEditorFromRow($row) {
                    if (!$row || !$row.length) return;
                    var id = $row.data('id');
                    $editor.find('[name^="data["]').each(function () {
                        var name = $(this).attr('name') || '';
                        if (name.slice(0, 5) !== 'data[') return;
                        var key = name.slice(5, -1);
                        var $cell = $row.find('td.lk-cms-cell[data-key="' + key + '"]');
                        if (!$cell.length) { setEditorValue(key, ''); return; }
                        var $input = $cell.find('.lk-cell-input');
                        var value = $input.length
                            ? cellValue($input)
                            : String($cell.find('.lk-cms-cell-preview').attr('data-value') || '');
                        setEditorValue(key, value);
                    });
                    $editor.find('[name="item_id"]').val(id);
                    $editor.find('.lk-editor-title').text('Edit row #' + id);

                    // Duplicate and Delete act on whichever row is open.
                    $editor.find('.lk-editor-foot-right').prop('hidden', false);
                    $editor.find('.lk-cms-dup').attr('value', 'duplicate:' + id).data('id', id);
                    $editor.find('.delete-item').attr('value', 'delete:' + id).data('id', id);

                    // Previous and next follow the order on screen.
                    var $rows = $('.lk-items-table tbody tr[data-id]');
                    var at = $rows.index($row);
                    var $step = $editor.find('.lk-editor-step').prop('hidden', false);
                    $step.find('.lk-editor-step-pos').text((at + 1) + ' of ' + $rows.length);
                    $step.find('.lk-step-prev').toggleClass('is-off', at <= 0);
                    $step.find('.lk-step-next').toggleClass('is-off', at >= $rows.length - 1);
                }

                $(document).on('click', '.lk-cms-items-screen .lk-cms-edit-item, .lk-cms-items-screen .lk-cms-cell-preview, .lk-cms-items-screen .lk-cms-cell-expand', function (e) {
                    if (e.defaultPrevented) return;
                    e.preventDefault();
                    fillEditorFromRow($(this).closest('tr'));
                    openEditor();
                });

                // Stepping moves the panel to the next row rather than
                // reloading the page.
                $editor.on('click', '.lk-step-prev, .lk-step-next', function (e) {
                    e.preventDefault();
                    var $btn = $(this);
                    if ($btn.hasClass('is-off')) return;
                    var id = $editor.find('[name="item_id"]').val();
                    var $rows = $('.lk-items-table tbody tr[data-id]');
                    var $cur = $rows.filter('[data-id="' + id + '"]');
                    var at = $rows.index($cur);
                    var $to = $btn.hasClass('lk-step-prev') ? $rows.eq(at - 1) : $rows.eq(at + 1);
                    if (at < 0 || !$to.length) return;
                    fillEditorFromRow($to);
                });

                $editor.on('click', '.lk-cms-editor-close, .lk-cms-editor-overlay', function (e) {
                    e.preventDefault();
                    closeEditor();
                });
                $(document).on('keydown', function (e) {
                    if (e.key === 'Escape' && $editor.hasClass('is-open')) closeEditor();
                });

                // Save the whole row: one payload, validated server-side
                // all-or-nothing; the returned row HTML replaces the old one.
                $editor.on('submit', '.lk-cms-editor-form', function (e) {
                    e.preventDefault();
                    var $form = $(this);
                    if (window.tinymce) { try { window.tinymce.triggerSave(); } catch (err) {} }
                    var payload = {
                        action: 'lk_cms_save_item',
                        nonce: LoopkitCMS.nonce,
                        collection_id: $form.find('[name="collection_id"]').val(),
                        item_id: $form.find('[name="item_id"]').val() || 0,
                        data: {}
                    };
                    $form.find('[name^="data["]').each(function () {
                        var $el = $(this);
                        var name = $el.attr('name') || '';
                        if (name.slice(0, 5) !== 'data[') return;
                        payload.data[name.slice(5, -1)] = $el.is(':checkbox')
                            ? ($el.is(':checked') ? '1' : '0')
                            : String($el.val());
                    });
                    var $btn = $form.find('.lk-cms-editor-foot .button-primary');
                    var btnText = $btn.text();
                    $btn.prop('disabled', true).text(LoopkitCMS.messages.saving);
                    $.post(LoopkitCMS.ajaxurl, payload).done(function (r) {
                        $btn.prop('disabled', false).text(btnText);
                        if (r && r.success && r.data) {
                            if (r.data.row_html) {
                                var $new = $(r.data.row_html);
                                var $existing = $('.lk-items-table tbody tr[data-id="' + r.data.item_id + '"]');
                                if ($existing.length) $existing.replaceWith($new);
                                else $new.insertBefore($('.lk-items-table tbody .lk-cms-addrow'));
                                $('.lk-items-table tbody .lk-cms-emptyrow').remove();
                                renumber();
                            }
                            closeEditor();
                            itemsMsg('updated', r.data.msg || LoopkitCMS.messages.saved);
                        } else {
                            itemsMsg('error', (r && r.data && r.data.msg) || LoopkitCMS.messages.error);
                        }
                    }).fail(function () {
                        $btn.prop('disabled', false).text(btnText);
                        itemsMsg('error', LoopkitCMS.messages.error);
                    });
                });

                $(document).on('click', '.lk-cms-editor-media', function () {
                    var $wrap = $(this).closest('.lk-cms-editor-img');
                    var $input = $wrap.find('.lk-cms-editor-img-input');
                    openMedia(function (url) { setEditorImage($input, url); }, $wrap.data('media'));
                });
                $(document).on('click', '.lk-cms-editor-media-clear', function () {
                    setEditorImage($(this).closest('.lk-cms-editor-img').find('.lk-cms-editor-img-input'), '');
                });
            } // /#lk-cms-item-editor

            // ── Media library (image cells + editor) ─────────────────
            function openMedia(done, mime) {
                if (!window.wp || !window.wp.media) {
                    itemsMsg('error', LoopkitCMS.messages.error);
                    return;
                }
                // Filter the library to what the field actually takes —
                // an audio field should not be offering photographs.
                var frame = window.wp.media({
                    title: LoopkitCMS.messages.choose || 'Choose',
                    button: { text: LoopkitCMS.messages.choose || 'Choose' },
                    library: mime ? { type: mime } : {},
                    multiple: false
                });
                frame.on('select', function () {
                    var att = frame.state().get('selection').first().toJSON();
                    done(att.url || '');
                });
                frame.open();
            }

            // Image and SVG show the file; video and audio have nothing to
            // show, so they get the icon and the file name instead.
            function paintMedia($btn, url, icon) {
                $btn.empty();
                var pic = icon === 'dashicons-format-image' || icon === 'dashicons-media-code';
                if (!url) {
                    $('<span>').addClass('dashicons ' + icon).appendTo($btn);
                } else if (pic) {
                    $('<img>').attr('src', url).appendTo($btn);
                } else {
                    $('<span>').addClass('dashicons ' + icon).appendTo($btn);
                    $('<span>').addClass('lk-cms-media-name')
                        .text(String(url).split('?')[0].split('/').pop()).appendTo($btn);
                }
            }

            $(document).on('click', '.lk-cms-items-screen .lk-cms-img-btn', function () {
                var $wrap = $(this).closest('.lk-cms-img-cell');
                var $input = $wrap.find('.lk-cms-img-input');
                var icon = $(this).data('icon') || 'dashicons-format-image';
                openMedia(function (url) {
                    $input.val(url);
                    $wrap.toggleClass('has-value', !!url);
                    paintMedia($wrap.find('.lk-cms-img-btn'), url, icon);
                    $input.trigger('change'); // same save path as every other cell
                }, $wrap.data('media'));
            });

            $(document).on('click', '.lk-cms-items-screen .lk-cms-img-clear', function () {
                var $wrap = $(this).closest('.lk-cms-img-cell');
                var $input = $wrap.find('.lk-cms-img-input');
                $input.val('');
                $wrap.removeClass('has-value');
                paintMedia($wrap.find('.lk-cms-img-btn'), '', $wrap.find('.lk-cms-img-btn').data('icon') || 'dashicons-format-image');
                $input.trigger('change');
            });

            // Long and rich text cells grow with what is typed into them,
            // so the table stays compact until a cell actually needs room.
            function fitCell(el) {
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 180) + 'px';
            }
            $('.lk-cms-items-screen .lk-cms-cell-textarea').each(function () { fitCell(this); });
            $(document).on('input focus', '.lk-cms-items-screen .lk-cms-cell-textarea', function () { fitCell(this); });
            $(document).on('blur', '.lk-cms-items-screen .lk-cms-cell-textarea', function () { fitCell(this); });

        } // /.lk-cms-items-screen

    });

})(jQuery, window);
