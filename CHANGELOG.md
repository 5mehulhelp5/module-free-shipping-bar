# Changelog

## 1.1.1

- Fix a Knockout binding error introduced in 1.1.0 that broke every binding on pages rendering
  the minicart: `Unable to parse bindings ... Unexpected token '+'`. Magento's template renderer
  wraps an attribute binding value in curly braces when it contains a colon and no closing brace,
  so the inline ternary added to `css=""` became `css: {expr}`. Replaced with an object literal.
- Added `Test/Js/check-ko-bindings.js`, which applies the same wrapping rule and parses the
  result, so an unparseable binding fails before it reaches a storefront.

## 1.1.0

- New placement: the Ajax Cart Pro "added to cart" popup, covering all four of its popup styles.
  The popup builds its own component tree and two styles remove `cart.summary`, so neither the
  minicart nor the cart page placement reached it.
- The customer data section now reports which placements are enabled, so the popup and the header
  minicart can be switched on independently.

## 1.0.0

- Free shipping progress bar in the minicart, on the cart page and in the checkout order summary.
- Threshold provider chain: per customer group override, Free Shipping carrier, any carrier with a
  free shipping threshold, and experimental cart price rule parsing.
- Configurable basis (subtotal after discount, subtotal, grand total) and tax treatment, with
  detected thresholds reporting the basis their own source uses.
- Multi-currency: thresholds stored in base currency, displayed converted.
- English, Spanish (Spain) and Spanish (Mexico) translations.
