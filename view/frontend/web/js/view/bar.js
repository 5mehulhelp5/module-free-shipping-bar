/**
 * Free shipping bar for the header minicart and for the Ajax Cart Pro popup.
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
            template: 'Swissup_FreeShippingBar/bar',
            // Overridden from layout for the Ajax Cart Pro popup, which renders its own
            // component tree and is switched on independently of the header minicart.
            placement: 'minicart'
        },

        /**
         * @returns {Object} this
         */
        initialize: function () {
            this._super();

            this.bar = customerData.get('free-shipping-bar');

            this.isVisible = ko.computed(function () {
                var data = this.bar();

                if (!data || !data.show) {
                    return false;
                }

                return Boolean(data.placements && data.placements[this.placement]);
            }, this);

            this.modifierClass = ko.computed(function () {
                return 'fsbar--' + this.placement.replace(/_/g, '-');
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
