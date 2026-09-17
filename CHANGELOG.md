# Changelog

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
