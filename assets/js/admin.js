jQuery(document).ready(function($) {
    // Initialize Persian Datepicker
    $('.persian-datepicker').persianDatepicker({
        format: 'YYYY/MM/DD',
        autoClose: true
    });

    // Initialize Color Picker
    $('.wp-color-picker').wpColorPicker();

    // Function to show loading
    function showLoading($element) {
        $element.addClass('wpm-loading');
    }

    // Function to hide loading
    function hideLoading($element) {
        $element.removeClass('wpm-loading');
    }

    // Add Status (StatusManager.php)
    $('.wpm-add-status').on('click', function() {
        var $form = $('.wpm-statuses-form');
        var name = $('#wpm-status-name').val();
        var color = $('#wpm-status-color').val();

        if (!name || !color) {
            alert(wpmAdmin.i18n.requiredFields);
            return;
        }

        showLoading($form);
        $.ajax({
            url: wpmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpm_save_status',
                nonce: wpmAdmin.nonce,
                name: name,
                color: color
            },
            success: function(response) {
                hideLoading($form);
                if (response.success) {
                    var index = response.data.index;
                    var status = response.data.status;
                    var row = `
                        <tr data-index="${index}">
                            <td class="wpm-status-name">${status.name}</td>
                            <td class="wpm-status-color"><span style="background-color: ${status.color}; padding: 5px; color: #fff;">${status.color}</span></td>
                            <td>
                                <button class="button wpm-edit-status">${wpmAdmin.i18n.edit}</button>
                                <button class="button wpm-delete-status">${wpmAdmin.i18n.delete}</button>
                            </td>
                        </tr>
                    `;
                    $('#wpm-statuses-sortable').append(row);
                    $('#wpm-status-name').val('');
                    $('#wpm-status-color').val('#0073aa').wpColorPicker('color', '#0073aa');
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                hideLoading($form);
                alert(wpmAdmin.i18n.error);
            }
        });
    });

    // Edit Status
    $(document).on('click', '.wpm-edit-status', function() {
        var $row = $(this).closest('tr');
        var index = $row.data('index');
        var name = $row.find('.wpm-status-name').text();
        var color = $row.find('.wpm-status-color span').css('background-color');

        $row.find('.wpm-status-name').html(`<input type="text" class="edit-status-name" value="${name}">`);
        $row.find('.wpm-status-color').html(`<input type="text" class="edit-status-color wp-color-picker" value="${rgbToHex(color)}">`);
        $row.find('.wpm-edit-status').replaceWith(`<button class="button wpm-save-status">${wpmAdmin.i18n.save}</button>`);

        $row.find('.wp-color-picker').wpColorPicker();
    });

    // Save Edited Status
    $(document).on('click', '.wpm-save-status', function() {
        var $row = $(this).closest('tr');
        var index = $row.data('index');
        var name = $row.find('.edit-status-name').val();
        var color = $row.find('.edit-status-color').val();

        if (!name || !color) {
            alert(wpmAdmin.i18n.requiredFields);
            return;
        }

        showLoading($row);
        $.ajax({
            url: wpmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpm_update_status',
                nonce: wpmAdmin.nonce,
                index: index,
                name: name,
                color: color
            },
            success: function(response) {
                hideLoading($row);
                if (response.success) {
                    $row.find('.wpm-status-name').text(response.data.status.name);
                    $row.find('.wpm-status-color').html(`<span style="background-color: ${response.data.status.color}; padding: 5px; color: #fff;">${response.data.status.color}</span>`);
                    $row.find('.wpm-save-status').replaceWith(`<button class="button wpm-edit-status">${wpmAdmin.i18n.edit}</button>`);
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                hideLoading($row);
                alert(wpmAdmin.i18n.error);
            }
        });
    });

    // Delete Status
    $(document).on('click', '.wpm-delete-status', function() {
        if (!confirm(wpmAdmin.i18n.confirmDelete)) {
            return;
        }

        var $row = $(this).closest('tr');
        var index = $row.data('index');

        showLoading($row);
        $.ajax({
            url: wpmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpm_delete_status',
                nonce: wpmAdmin.nonce,
                index: index
            },
            success: function(response) {
                hideLoading($row);
                if (response.success) {
                    $row.remove();
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                hideLoading($row);
                alert(wpmAdmin.i18n.error);
            }
        });
    });

    // Add Holiday (Calendar.php)
    $('.wpm-add-holiday').on('click', function(e) {
        e.preventDefault();
        var $form = $('#wpm-holidays-form');
        var date = $('#wpm-holiday-date').val();
        var description = $('#wpm-holiday-description').val();

        if (!date) {
            alert(wpmAdmin.i18n.requiredFields);
            return;
        }

        showLoading($form);
        $.ajax({
            url: wpmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpm_save_holidays',
                nonce: wpmAdmin.nonce,
                date: date,
                description: description
            },
            success: function(response) {
                hideLoading($form);
                if (response.success) {
                    var index = response.data.index;
                    var holiday = response.data.holiday;
                    var row = `
                        <tr data-index="${index}">
                            <td class="wpm-holiday-date">${holiday.date}</td>
                            <td class="wpm-holiday-description">${holiday.description}</td>
                            <td>
                                <button class="button wpm-edit-holiday">${wpmAdmin.i18n.edit}</button>
                                <button class="button wpm-delete-holiday">${wpmAdmin.i18n.delete}</button>
                            </td>
                        </tr>
                    `;
                    $('#wpm-holidays-sortable').append(row);
                    $('#wpm-holiday-date').val('');
                    $('#wpm-holiday-description').val('');
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                hideLoading($form);
                alert(wpmAdmin.i18n.error);
            }
        });
    });

    // Edit Holiday
    $(document).on('click', '.wpm-edit-holiday', function() {
        var $row = $(this).closest('tr');
        var index = $row.data('index');
        var date = $row.find('.wpm-holiday-date').text();
        var description = $row.find('.wpm-holiday-description').text();

        $row.find('.wpm-holiday-date').html(`<input type="text" class="persian-datepicker edit-holiday-date" value="${date}">`);
        $row.find('.wpm-holiday-description').html(`<input type="text" class="edit-holiday-description" value="${description}">`);
        $row.find('.wpm-edit-holiday').replaceWith(`<button class="button wpm-save-holiday">${wpmAdmin.i18n.save}</button>`);

        $row.find('.persian-datepicker').persianDatepicker({
            format: 'YYYY/MM/DD',
            autoClose: true
        });
    });

    // Save Edited Holiday
    $(document).on('click', '.wpm-save-holiday', function() {
        var $row = $(this).closest('tr');
        var index = $row.data('index');
        var date = $row.find('.edit-holiday-date').val();
        var description = $row.find('.edit-holiday-description').val();

        showLoading($row);
        $.ajax({
            url: wpmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpm_update_holiday',
                nonce: wpmAdmin.nonce,
                index: index,
                date: date,
                description: description
            },
            success: function(response) {
                hideLoading($row);
                if (response.success) {
                    $row.find('.wpm-holiday-date').text(response.data.holiday.date);
                    $row.find('.wpm-holiday-description').text(response.data.holiday.description);
                    $row.find('.wpm-save-holiday').replaceWith(`<button class="button wpm-edit-holiday">${wpmAdmin.i18n.edit}</button>`);
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                hideLoading($row);
                alert(wpmAdmin.i18n.error);
            }
        });
    });

    // Delete Holiday
    $(document).on('click', '.wpm-delete-holiday', function() {
        if (!confirm(wpmAdmin.i18n.confirmDelete)) {
            return;
        }

        var $row = $(this).closest('tr');
        var index = $row.data('index');

        showLoading($row);
        $.ajax({
            url: wpmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpm_delete_holiday',
                nonce: wpmAdmin.nonce,
                index: index
            },
            success: function(response) {
                hideLoading($row);
                if (response.success) {
                    $row.remove();
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                hideLoading($row);
                alert(wpmAdmin.i18n.error);
            }
        });
    });

    // Initialize Sortable for Holidays
    $('#wpm-holidays-sortable').sortable({
        update: function(event, ui) {
            var $table = $(this).closest('table');
            showLoading($table);
            var order = $(this).sortable('toArray', {attribute: 'data-index'}).map(Number);
            $.ajax({
                url: wpmAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpm_reorder_holidays',
                    nonce: wpmAdmin.nonce,
                    order: order
                },
                success: function(response) {
                    hideLoading($table);
                    if (!response.success) {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    hideLoading($table);
                    alert(wpmAdmin.i18n.error);
                }
            });
        }
    }).disableSelection();
    
    // Initialize Sortable for Statuses
    $('#wpm-statuses-sortable').sortable({
        update: function(event, ui) {
            var $table = $(this).closest('table');
            showLoading($table);
            var order = $(this).sortable('toArray', {attribute: 'data-index'}).map(Number);
            $.ajax({
                url: wpmAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpm_reorder_statuses',
                    nonce: wpmAdmin.nonce,
                    order: order
                },
                success: function(response) {
                    hideLoading($table);
                    if (!response.success) {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    hideLoading($table);
                    alert(wpmAdmin.i18n.error);
                }
            });
        }
    }).disableSelection();

    // Dynamic Status Edit
    $(document).on('click', '.wpm-status-display', function() {
        var $this = $(this);
        var orderId = $this.data('order-id');
        var itemId = $this.data('item-id');
        var currentStatus = $this.text();

        var select = $('<select class="wpm-status-select"></select>');
        select.append('<option value="">' + wpmAdmin.i18n.selectStatus + '</option>');
        $.each(wpmAdmin.statuses, function(index, status) {
            select.append(`<option value="${status.name}" ${status.name === currentStatus ? 'selected' : ''}>${status.name}</option>`);
        });

        $this.replaceWith(select);

        select.focus().on('change', function() {
            var newStatus = $(this).val();
            var color = '';
            $.each(wpmAdmin.statuses, function(index, status) {
                if (status.name === newStatus) {
                    color = status.color;
                    return false;
                }
            });

            showLoading(select);
            $.ajax({
                url: wpmAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpm_update_order_item_status',
                    nonce: wpmAdmin.nonce,
                    order_id: orderId,
                    order_item_id: itemId,
                    status: newStatus
                },
                success: function(response) {
                    hideLoading(select);
                    if (response.success) {
                        select.replaceWith(`<span class="wpm-status-display" style="background-color: ${color || '#ccc'}; color: #fff;" data-item-id="${itemId}" data-order-id="${orderId}">${newStatus || wpmAdmin.i18n.selectStatus}</span>`);
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    hideLoading(select);
                    alert(wpmAdmin.i18n.error);
                }
            });
        });
    });

    // Dynamic Delivery Date Edit
    $(document).on('click', '.wpm-delivery-date-display', function() {
        var $this = $(this);
        var orderId = $this.data('order-id');
        var itemId = $this.data('item-id');
        var currentDate = $this.text();

        var input = $(`<input type="text" class="persian-datepicker wpm-delivery-date-input" value="${currentDate}">`);
        $this.replaceWith(input);

        input.persianDatepicker({
            format: 'YYYY/MM/DD',
            autoClose: true
        }).focus().on('change', function() {
            var newDate = $(this).val();

            showLoading(input);
            $.ajax({
                url: wpmAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpm_update_order_item_delivery_date',
                    nonce: wpmAdmin.nonce,
                    order_id: orderId,
                    order_item_id: itemId,
                    delivery_date: newDate
                },
                success: function(response) {
                    hideLoading(input);
                    if (response.success) {
                        input.replaceWith(`<span class="wpm-delivery-date-display" data-item-id="${itemId}" data-order-id="${orderId}">${newDate}</span>`);
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    hideLoading(input);
                    alert(wpmAdmin.i18n.error);
                }
            });
        });
    });

    // Image Hover Preview
    $(document).on('mouseenter', '.wpm-item-image', function(e) {
        var largeImage = $(this).data('large');
        if (largeImage) {
            var preview = $('<div class="wpm-image-preview"><img src="' + largeImage + '"></div>');
            $('body').append(preview);
            preview.css({
                left: e.pageX - 210,
                top: e.pageY + 10
            }).show();
        }
    }).on('mouseleave', '.wpm-item-image', function() {
        $('.wpm-image-preview').remove();
    }).on('mousemove', '.wpm-item-image', function(e) {
        $('.wpm-image-preview').css({
            left: e.pageX - 210,
            top: e.pageY + 10
        });
    });

    // Handle status change
    $('.wpm-status-select').on('change', function() {
        var $select = $(this);
        var orderItemId = $select.data('item-id');
        var status = $select.val();

        $.ajax({
            url: wpmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpm_update_order_item_status',
                nonce: wpmAdmin.nonce,
                order_item_id: orderItemId,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    alert(wpmAdmin.i18n.statusUpdated);
                } else {
                    alert(wpmAdmin.i18n.error);
                }
            }
        });
    });

    // Handle delivery date change
    $('.wpm-delivery-date-input').on('change', function() {
        var $input = $(this);
        var orderItemId = $input.data('item-id');
        var deliveryDate = $input.val();

        $.ajax({
            url: wpmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpm_update_order_item_delivery_date',
                nonce: wpmAdmin.nonce,
                order_item_id: orderItemId,
                delivery_date: deliveryDate
            },
            success: function(response) {
                if (response.success) {
                    alert(wpmAdmin.i18n.deliveryDateUpdated);
                } else {
                    alert(wpmAdmin.i18n.error);
                }
            }
        });
    });

    // Handle export order items
    $('.wpm-export-order-items').on('click', function() {
        var $button = $(this);
        $button.text(wpmAdmin.i18n.exporting);

        $.ajax({
            url: wpmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpm_export_order_items',
                nonce: wpmAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.url;
                } else {
                    alert(wpmAdmin.i18n.error);
                }
                $button.text(wpmAdmin.i18n.exportOrderItems);
            },
            error: function() {
                alert(wpmAdmin.i18n.error);
                $button.text(wpmAdmin.i18n.exportOrderItems);
            }
        });
    });

    // Handle export logs
    $('.wpm-export-logs').on('click', function() {
        var $button = $(this);
        $button.text(wpmAdmin.i18n.exporting);

        var params = {
            action: 'wpm_export_logs',
            nonce: wpmAdmin.nonce,
            order_item_id: $('input[name="order_item_id"]').val(),
            changed_by: $('input[name="changed_by"]').val(),
            date_from: $('input[name="date_from"]').val(),
            date_to: $('input[name="date_to"]').val()
        };

        $.ajax({
            url: wpmAdmin.ajaxUrl,
            type: 'POST',
            data: params,
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.url;
                } else {
                    alert(wpmAdmin.i18n.error);
                }
                $button.text(wpmAdmin.i18n.exportLogs);
            },
            error: function() {
                alert(wpmAdmin.i18n.error);
                $button.text(wpmAdmin.i18n.exportLogs);
            }
        });
    });

    // Convert RGB to Hex for color picker
    function rgbToHex(rgb) {
        if (rgb.indexOf('#') === 0) {
            return rgb;
        }
        var rgbMatch = rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
        if (!rgbMatch) {
            return '#000000';
        }
        return "#" + ((1 << 24) + (parseInt(rgbMatch[1]) << 16) + (parseInt(rgbMatch[2]) << 8) + parseInt(rgbMatch[3])).toString(16).slice(1).toUpperCase();
    }
});