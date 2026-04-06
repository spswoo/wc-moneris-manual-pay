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

The **WooCommerce Manual Moneris Payment UI** plugin adds a **slide-out panel** in the WooCommerce order admin page, allowing site admins to **manually charge credit cards** through Moneris Direct API.  

> ⚠️ **Warning:** This plugin captures raw credit card data. Ensure your site is PCI compliant and uses HTTPS.

With this plugin, admins can:

- Process manual payments for **pending, on-hold, or failed orders**.
- Collect **credit card number, expiry, and CVC** in a secure admin-only interface.
- Automatically mark orders as **paid** after successful Moneris transaction.
- Add a detailed **order note** including masked credit card info, AVS/CVD results, and Moneris transaction details.

---

## Requirements

1. **WooCommerce** installed and active.  
2. **Moneris PHP SDK (`mpgClasses.php`)**: This plugin does **not include** Moneris’s proprietary library.  
   - Download the official SDK from [Moneris Developer Portal](https://developer.moneris.com/Documentation/PHP/Download).  
   - Place `mpgClasses.php` in the plugin directory (`wp-content/plugins/wc-moneris-manual-payment/`).  
   - The plugin will show an **admin notice** if the file is missing.  

> ⚠️ Without `mpgClasses.php`, manual payments cannot be processed.

---

## Installation

1. Upload the plugin files to `/wp-content/plugins/wc-moneris-manual-payment/` or install via the WordPress admin.
2. Activate the plugin through the 'Plugins' menu.
3. Download the **Moneris PHP SDK** and place `mpgClasses.php` in the plugin folder.
4. Edit the plugin to configure your **Moneris credentials**:

```php
$store_id  = 'store5';     // Replace with your Moneris store ID
$api_token = 'yesguy';     // Replace with your Moneris API token
$test_mode = true;         // Set false for live transactions