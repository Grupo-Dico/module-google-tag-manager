/**
 * Emite el evento GA4 add_payment_info al dataLayer cuando el usuario selecciona
 * un metodo de pago en el checkout (SPA Knockout).
 *
 * Los items + totales se obtienen del backend (mismo detalle que begin_checkout)
 * para mantener consistencia entre eventos del embudo. El payment_type usa el
 * codigo del metodo de pago seleccionado (estandar GA4).
 */
define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'mage/url'
], function ($, quote, url) {
    'use strict';

    return function (config) {
        config = config || {};

        var debug = !!config.debug;
        var endpoint = url.build('gdmexico_gtm/ajax/paymentInfo');
        var ecommerceCache = null;
        var ecommerceRequested = false;
        var trackedMethods = {};

        function log() {
            if (debug && window.console) {
                window.console.log.apply(window.console, arguments);
            }
        }

        function pushToDataLayer(paymentCode, ecommerce) {
            window.dataLayer = window.dataLayer || [];
            // Reset del objeto ecommerce previo para evitar merges entre eventos.
            window.dataLayer.push({ 'ecommerce': null });
            window.dataLayer.push({
                'event': 'add_payment_info',
                'ecommerce': $.extend({ 'payment_type': paymentCode }, ecommerce)
            });
            log('GTM: Evento add_payment_info enviado', paymentCode);
        }

        function handlePayment(paymentCode, ecommerce) {
            if (!ecommerce || !ecommerce.items || !ecommerce.items.length) {
                return;
            }
            // Evitar duplicar el evento para el mismo metodo de pago.
            if (trackedMethods[paymentCode]) {
                return;
            }
            trackedMethods[paymentCode] = true;
            pushToDataLayer(paymentCode, ecommerce);
        }

        function fetchEcommerce(callback) {
            if (ecommerceCache) {
                callback(ecommerceCache);
                return;
            }
            if (ecommerceRequested) {
                return;
            }
            ecommerceRequested = true;

            $.ajax({
                url: endpoint,
                type: 'GET',
                dataType: 'json',
                cache: false
            }).done(function (response) {
                ecommerceRequested = false;
                if (response && response.enabled && response.ecommerce) {
                    ecommerceCache = response.ecommerce;
                    callback(ecommerceCache);
                } else {
                    log('GTM: add_payment_info deshabilitado o sin items');
                }
            }).fail(function () {
                ecommerceRequested = false;
                log('GTM: error al obtener datos de add_payment_info');
            });
        }

        // Suscribirse al cambio de metodo de pago en el checkout.
        quote.paymentMethod.subscribe(function (method) {
            var code = method && method.method ? method.method : null;

            if (!code) {
                return;
            }

            fetchEcommerce(function (ecommerce) {
                handlePayment(code, ecommerce);
            });
        });

        // Si ya hay un metodo seleccionado al cargar (p. ej. un solo metodo
        // disponible preseleccionado), emitir el evento tambien.
        (function () {
            var current = quote.paymentMethod();
            if (current && current.method) {
                fetchEcommerce(function (ecommerce) {
                    handlePayment(current.method, ecommerce);
                });
            }
        })();

        return {};
    };
});
