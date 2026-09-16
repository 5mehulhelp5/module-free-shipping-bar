# Swissup_FreeShippingBar

Shows the customer how much more they need to spend to qualify for free shipping, as a message
and a progress bar in the minicart, on the cart page and in the checkout order summary.

Standalone: it does not require Ajax Cart Pro. When Ajax Cart Pro is installed its ajax add to
cart goes through the standard `checkout/cart/add` route, which already invalidates this module's
customer data section, so the bar refreshes with it.

## Installation

```bash
composer require swissup/module-free-shipping-bar
php bin/magento module:enable Swissup_FreeShippingBar
php bin/magento setup:upgrade
php bin/magento cache:flush
```

## Configuration

**Stores → Configuration → Swissup → Free Shipping Bar**

| Group | Setting | Notes |
| --- | --- | --- |
| General | Enabled | Website scope |
| General | Show On | Minicart, cart page, checkout summary — any combination |
| General | Measure Progress On | Subtotal after discount (default), subtotal before discount, or grand total |
| General | Include Tax | **Must match how your free shipping actually qualifies** — see below |
| Thresholds | Per Customer Group | Amount per group, in base currency. Always wins |
| Thresholds | Auto-detect From Shipping Configuration | Free Shipping carrier, then any carrier with a free shipping threshold |
| Thresholds | Auto-detect From Cart Price Rules | Experimental, off by default |
| Appearance | Messages, colours, whether the bar stays visible once qualified | |

### Thresholds

`ThresholdResolver` asks each provider in turn and takes the first answer:

| Order | Provider | Source |
| --- | --- | --- |
| 10 | `GroupOverrideProvider` | The admin table above, matched on the quote's customer group |
| 20 | `FreeShippingCarrierProvider` | `carriers/freeshipping/free_shipping_subtotal` |
| 30 | `CarrierFreeShippingProvider` | Any active carrier with `free_shipping_enable` + `free_shipping_subtotal`; lowest wins |
| 40 | `SalesRuleProvider` | Coupon-less cart price rules that grant free shipping. Experimental |

Add your own by implementing `Swissup\FreeShippingBar\Api\ThresholdProviderInterface` and
registering it in `di.xml`; `sortOrder` is read by the resolver, not by the DI framework.

The two auto-detecting carrier providers report the basis and tax treatment their source actually
uses, and that overrides the global **Measure Progress On** / **Include Tax** settings — so a
detected threshold is always compared the same way the carrier compares it.

### Getting the tax setting right

This is the one setting worth checking twice. If the bar measures the cart differently from the
rule that grants free shipping, it will tell a customer they qualify and checkout will disagree.

- Magento's **Free Shipping carrier** compares `base_subtotal_with_discount_incl_tax`, which
  Magento defines as `base_subtotal_with_discount + base_tax_amount`.
- **Online carriers** (UPS, USPS, FedEx, DHL) compare the package value with discount, excluding tax.
- **Cart price rules** compare whichever attribute the rule's condition names.

### Thresholds and currency

Thresholds are stored per website in the **base** currency and displayed converted into whatever
currency the customer is browsing in. The checkout config provider publishes the threshold already
converted, so the checkout bar never has to deal with an exchange rate.

## When the bar does not render

- The module or the placement is disabled.
- No threshold resolves, or it is zero or negative.
- The cart is empty.
- The cart is virtual or downloadable only — nothing ships, so there is nothing to earn.
- The cart already qualifies and **Keep Showing After Qualifying** is No.

A cart counts as qualified when it reaches the threshold, when a cart price rule has set free
shipping on the shipping address, or when every shippable item carries an item-level free shipping
flag. A mixed cart where only some items ship free is not qualified — the rest of the shipment
still costs money.

## Surfaces

| Surface | How |
| --- | --- |
| Minicart | `free-shipping-bar` customer data section + a KO component in the `extraInfo` region |
| Cart page | Server rendered block and ViewModel above the totals; the page reloads on qty change anyway |
| Checkout summary | Threshold published into `window.checkoutConfig`, recomputed client side from quote totals so it follows coupon and shipping changes without a round trip |

Nothing renders inside a cacheable block: minicart data is private customer data, and the cart and
checkout pages are not cached.

## Styling

`view/frontend/web/css/source/_module.less`, one `.fsbar` BEM block, no `!important`. Admin
colours arrive as the `--fsbar-track`, `--fsbar-fill` and `--fsbar-success` custom properties.
The fill transition honours `prefers-reduced-motion`.

## Tests

```bash
php ../../../../vendor/bin/phpunit -c phpunit.xml.dist
```
