define(['jquery'], function ($) {
  'use strict';

  function pushToDL(obj) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(obj);
  }

  function itemFromCard($card) {
    return {
      item_id: $card.data('item_id'),
      item_name: $card.data('item_name'),
      affiliation: 'Tienda Online',
      currency: $card.data('currency') || $('body').attr('data-currency-code') || 'MXN',
      item_brand: $card.data('item_brand'),
      item_category: $card.data('item_category'),
      item_variant: $card.data('item_variant'),
      price: Number($card.data('price') || 0),
      index: Number($card.data('index') || 0),
      quantity: 1
    };
  }

  $(document).ready(function () {
    // select_item on product link click
    $(document).on('click', '#product-grid .product-item a, .product-item a.product-item-link', function (e) {
      var $card = $(this).closest('.product-item');
      if (!$card.length) return;
      var item = itemFromCard($card);
      pushToDL({
        event: 'select_item',
        ecommerce: {
          item_list_id: $card.closest('[data-list-id]').data('list-id') || null,
          item_list_name: $card.closest('[data-list-name]').data('list-name') || null,
          items: [item]
        }
      });
    });

    return {};
  });
});