# WooCommerce Manual Moneris Payment UI (Direct API)

**Contributors:** spswoo  
**Tags:** woocommerce, moneris, manual payment, credit card, admin  
**Requires at least:** 6.0  
**Tested up to:** 6.6  
**Requires PHP:** 7.4  
**Stable tag:** 2.3  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

---

## Description

The **WooCommerce Manual Moneris Payment UI** plugin adds a **slide-out panel** in the WooCommerce order admin page that allows site admins to **manually charge credit cards** through Moneris Direct API.  

> ⚠️ **Warning:** This plugin captures raw credit card data. Ensure your site is PCI compliant and uses HTTPS.

With this plugin, admins can:

- Process manual payments for **pending, on-hold, or failed orders**.
- Collect **credit card number, expiry, and CVC** in a secure admin-only interface.
- Automatically mark orders as **paid** after successful Moneris transaction.
- Add a detailed **order note** including masked credit card info, AVS/CVD results, and Moneris transaction details.

---

## Features

- **Admin-only manual credit card processing**
- Slide-out interface integrated with WooCommerce orders
- Supports AVS (Address Verification) and CVD (CVV) checks
- Automatic order completion and detailed notes
- Works with Moneris Direct API (Canada)
- Test mode support for sandbox environment

---

## Installation

1. Upload the plugin files to the `/wp-content/plugins/wc-moneris-manual-payment/` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Ensure **WooCommerce** is installed and activated.
4. Edit the plugin to configure your **Moneris credentials** in `wc_moneris_process_manual_payment()`:

```php
$store_id  = 'store5';     // Replace with your Moneris store ID
$api_token = 'yesguy';     // Replace with your Moneris API token
$test_mode = true;         // Set false for live transactions