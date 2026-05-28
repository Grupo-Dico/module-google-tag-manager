define(['jquery'], function ($) {
  'use strict';

  function pushToDL(obj) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(obj);
  }

  function collectPdpItem() {
    var $form = $('#product_addtocart_form');
    var baseSku = $form.data('product-sku') || $('body').data('product-sku') || null;
    var currency = $('body').attr('data-currency-code') || 'MXN';
    var name = $('h1.page-title span').text().trim();
    var price = Number($('.price-wrapper[data-price-amount]').first().attr('data-price-amount') || 0);

    var variantSku = null;
    try {
      if (window.simpleSkuMap && typeof window.simpleSkuMap === 'object') {
        // lógica para resolver simple sku si el tema rellena selected options
        // el tema puede exponer window.selectedOptionsKey o similar
        var key = window.selectedOptionsKey || null;
        if (key && window.simpleSkuMap[key]) variantSku = window.simpleSkuMap[key];
      }
    } catch (e) {}

    var itemId = variantSku || baseSku;

    return {
      item_id: itemId,
      item_name: name,
      affiliation: 'Tienda Online',
      item_brand: window.productBrand || '',
      item_category: window.productCat1 || '',
      item_variant: window.productVariantLabel || '',
      price: price,
      currency: currency,
      quantity: 1
    };
  }

  return {};
});
