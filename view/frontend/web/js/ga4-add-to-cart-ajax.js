/**
 * Emite add_to_cart al dataLayer cuando el producto se agrega por AJAX y NO hay
 * redirección al carrito (config de Magento "Redirigir al carrito" = No).
 *
 * En ese caso no hay una nueva carga de página, así que el evento que el observer
 * dejó en sesión nunca llegaría a gtm_container.phtml. Se consulta el endpoint
 * GetEvents justo después de que Magento dispara `ajax:addToCart`.
 *
 * Si la respuesta trae backUrl (redirección activa), no se hace nada: la página
 * destino emitirá el evento desde gtm_container.phtml.
 */
define([
    'jquery',
    'mage/url'
], function ($, url) {
    'use strict';

    return function (config) {
        config = config || {};

        var debug = !!config.debug;
        var endpoint = url.build('gdmexico_gtm/ajax/getEvents');

        function log() {
            if (debug && window.console) {
                window.console.log.apply(window.console, arguments);
            }
        }

        $(document).on('ajax:addToCart', function (event, data) {
            var response = data && data.response;

            if (response && response.backUrl) {
                log('GTM: add_to_cart se emitirá tras la redirección');
                return;
            }

            $.ajax({
                url: endpoint,
                type: 'GET',
                dataType: 'json',
                cache: false,
                data: { event: 'add_to_cart' }
            }).done(function (res) {
                if (!res || !res.add_to_cart) {
                    return;
                }
                try {
                    var payload = typeof res.add_to_cart === 'string' ?
                        JSON.parse(res.add_to_cart) : res.add_to_cart;

                    window.dataLayer = window.dataLayer || [];
                    window.dataLayer.push({ ecommerce: null });
                    window.dataLayer.push(payload);
                    log('GTM: Evento add_to_cart (AJAX) enviado', payload);
                } catch (e) {
                    log('GTM: error al procesar add_to_cart', e);
                }
            }).fail(function () {
                log('GTM: error al obtener add_to_cart');
            });
        });

        return {};
    };
});
