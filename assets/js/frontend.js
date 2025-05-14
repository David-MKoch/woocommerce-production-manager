jQuery(document).ready(function($) {
    function loadDeliveryDate($container, productId, variationId, quantity) {
        $container.find('.wpm-delivery-date-text').text(wpmFrontend.i18n.loading);

        $.ajax({
            url: wpmFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpm_get_delivery_date',
                nonce: wpmFrontend.nonce,
                product_id: productId,
                variation_id: variationId || 0,
                quantity: quantity || 1
            },
            success: function(response) {
                if (response.success) {
                    $container.find('.wpm-delivery-date-text').text(response.data.delivery_date);
                } else {
                    $container.find('.wpm-delivery-date-text').text(wpmFrontend.i18n.error);
                }
            },
            error: function() {
                $container.find('.wpm-delivery-date-text').text(wpmFrontend.i18n.error);
            }
        });
    }

    // Load delivery date for simple products
    $('.wpm-delivery-date').each(function() {
        var $container = $(this);
        var productId = $container.data('product-id');
        loadDeliveryDate($container, productId);
    });

    // Handle variation change
    $('.variations_form').on('show_variation', function(event, variation) {
        var $container = $(this).closest('.product').find('.wpm-delivery-date');
        var productId = $container.data('product-id');
        var variationId = variation.variation_id;
        loadDeliveryDate($container, productId, variationId);
    });

    // Handle SMS notification setting
    $('#wpm-sms-notification').on('change', function() {
        var enabled = $(this).is(':checked');

        $.ajax({
            url: wpmFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpm_update_sms_notification',
                nonce: wpmFrontend.nonce,
                enabled: enabled
            },
            success: function(response) {
                if (response.success) {
                    alert(wpmFrontend.i18n.smsUpdated);
                } else {
                    alert(response.data.message);
                }
            }
        });
    });
});