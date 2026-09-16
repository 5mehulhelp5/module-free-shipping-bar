/**
 * Checkout order summary free shipping bar.
 *
 * The threshold is resolved server side and published through window.checkoutConfig already
 * converted into the display currency, so this only has to do the arithmetic — which keeps the
 * bar in step with coupon, qty and shipping changes without a round trip.
 */
define([
    'uiComponent',
    'ko',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'mage/translate'
], function (Component, ko, quote, priceUtils, $t) {
    'use strict';

    var config = window.checkoutConfig && window.checkoutConfig.freeShippingBar || {};

    return Component.extend({
        defaults: {
            template: 'Swissup_FreeShippingBar/checkout-bar'
        },

        /**
         * @returns {Object} this
         */
        initialize: function () {
            this._super();

            this.threshold = Number(config.threshold) || 0;

            this.current = ko.computed(function () {
                return this.getCurrentAmount(quote.getTotals()());
            }, this);

            this.isQualified = ko.computed(function () {
                // Matches the 0.0001 tolerance Magento itself uses when comparing cart money.
                return this.current() + 0.0001 >= this.threshold;
            }, this);

            this.percent = ko.computed(function () {
                if (this.threshold <= 0) {
                    return 0;
                }

                if (this.isQualified()) {
                    return 100;
                }

                return Math.max(0, Math.min(100, this.current() / this.threshold * 100));
            }, this);

            this.isVisible = ko.computed(function () {
                if (!config.enabled || this.threshold <= 0) {
                    return false;
                }

                return !this.isQualified() || Boolean(config.showWhenQualified);
            }, this);

            this.message = ko.computed(function () {
                if (this.isQualified()) {
                    return $t(config.messageQualified || '');
                }

                var remaining = Math.max(0, this.threshold - this.current());

                return $t(config.messageProgress || '').replace('%1', this.formatPrice(remaining));
            }, this);

            this.width = ko.computed(function () {
                return this.percent() + '%';
            }, this);

            this.ariaValue = ko.computed(function () {
                return String(Math.round(this.percent()));
            }, this);

            return this;
        },

        /**
         * Totals arrive in the display currency, same as the threshold published above.
         *
         * @param {Object} totals
         * @returns {Number}
         */
        getCurrentAmount: function (totals) {
            var value;

            if (!totals) {
                return 0;
            }

            switch (config.basis) {
                case 'grand_total':
                    // Grand total always includes tax, so includeTax does not apply.
                    value = totals.grand_total;
                    break;

                case 'subtotal':
                    value = config.includeTax && totals.subtotal_incl_tax !== undefined
                        ? totals.subtotal_incl_tax
                        : totals.subtotal;
                    break;

                default:
                    value = totals.subtotal_with_discount;

                    if (config.includeTax) {
                        value = Number(value || 0) + Number(totals.tax_amount || 0);
                    }
            }

            value = Number(value);

            return isNaN(value) ? 0 : value;
        },

        /**
         * @param {Number} amount
         * @returns {String}
         */
        formatPrice: function (amount) {
            return priceUtils.formatPrice(amount, quote.getPriceFormat());
        }
    });
});
