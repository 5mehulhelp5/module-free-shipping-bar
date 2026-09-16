/**
 * Minicart free shipping bar.
 *
 * Everything it renders comes from the free-shipping-bar customer data section, so it follows
 * whatever invalidates that section — including Ajax Cart Pro's add to cart, which goes through
 * the standard checkout/cart/add route.
 */
define([
    'uiComponent',
    'ko',
    'Magento_Customer/js/customer-data'
], function (Component, ko, customerData) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Swissup_FreeShippingBar/bar'
        },

        /**
         * @returns {Object} this
         */
        initialize: function () {
            this._super();

            this.bar = customerData.get('free-shipping-bar');

            this.isVisible = ko.computed(function () {
                return Boolean(this.bar() && this.bar().show);
            }, this);

            this.message = ko.computed(function () {
                return this.isVisible() ? this.bar().message : '';
            }, this);

            this.isQualified = ko.computed(function () {
                return this.isVisible() && Boolean(this.bar().qualified);
            }, this);

            this.percent = ko.computed(function () {
                var percent = this.isVisible() ? Number(this.bar().percent) : 0;

                if (isNaN(percent)) {
                    return 0;
                }

                return Math.max(0, Math.min(100, percent));
            }, this);

            this.width = ko.computed(function () {
                return this.percent() + '%';
            }, this);

            this.ariaValue = ko.computed(function () {
                return String(Math.round(this.percent()));
            }, this);

            return this;
        }
    });
});
