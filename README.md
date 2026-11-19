# WooCommerce Bulk Percentage Price Editor

A small, site-independent WooCommerce plugin for previewing and applying percentage price changes to published simple products and the individual variations of variable products.

The installable plugin is a single PHP file. It adds no external libraries, JavaScript or CSS assets, build system, or site-specific credentials.

## Requirements

- WordPress 6.2 or newer
- PHP 7.4 or newer
- WooCommerce active
- An administrator or store manager account with WooCommerce management access

## Install

1. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
2. Choose `woocommerce-bulk-percentage-price-editor.zip` and select **Install Now**.
3. Activate **WooCommerce Bulk Percentage Price Editor**.
4. Open **WooCommerce → ویرایش گروهی قیمت**.

The ZIP contains only `woocommerce-bulk-percentage-price-editor.php`. The archive is kept out of Git by the root `.gitignore`; create it locally when you need to install or distribute the plugin.

## Use

1. Choose all supported products, simple products only, or variable products only.
2. Optionally choose a product category. With “all categories” selected, every matching published product is included.
3. Choose regular price, sale price, or both.
4. Choose an increase or decrease and enter a percentage. Persian and Arabic digits are accepted.
5. Select **نمایش پیش‌نمایش** and review the scope, eligible count, excluded items, and sample prices.
6. Select the apply button to process the first batch. If more items remain, select **ادامه اجرای تغییرات** until the job finishes.

For variable products, the plugin calculates and updates each variation's own price. It then synchronizes the variable parent so WooCommerce can refresh its displayed price range.

## Price behavior

- A regular-price change leaves an existing sale price untouched. If the new regular price would fall below that sale price, the item is skipped.
- A sale-price change requires both a regular and an existing sale price. The new sale price is skipped if it would exceed the regular price.
- When both are selected, the same percentage is applied independently to the regular price and each existing sale price. Variations without a sale price still receive the regular-price change.
- Prices are rounded using the decimal precision configured in WooCommerce. Values that round to no change are skipped.
- A decrease cannot exceed 100 percent. Increasing accepts values up to 100,000 percent.
- Scheduled sale dates and other product fields are not changed by the plugin.

## Preview, batches, and recovery

The plugin only targets published simple and variable products. It does not change prices on activation. Each preview is tied to the current administrator, remains available for up to 24 hours, and is limited to 5,000 parent products and 10,000 price items. Narrow the product-type or category filter if a selection exceeds a limit.

Each apply request handles at most 25 price items. Before saving, the plugin checks that the relevant price values still match the preview. If another edit changed a price or a dependent sale/regular price, that item is skipped rather than overwritten.

After a job completes, **بازگرداندن همین تغییرات** can restore prices for up to 24 hours. Rollback only restores fields that still match the values written by that job; newer edits are preserved and reported as conflicts. This is a guarded undo for the job, not a permanent price-history system or a substitute for a store backup.

Product updates use WooCommerce's normal save methods and hooks. Other installed WooCommerce extensions may respond to those updates.

## Package and development

There are no dependencies to install and no build step. The plugin source is:

```text
woocommerce-bulk-percentage-price-editor/
└── woocommerce-bulk-percentage-price-editor.php
```

To recreate the local installable ZIP on Windows PowerShell:

```powershell
Compress-Archive -LiteralPath .\woocommerce-bulk-percentage-price-editor\woocommerce-bulk-percentage-price-editor.php -DestinationPath .\woocommerce-bulk-percentage-price-editor.zip -Force
```

Check PHP syntax with:

```powershell
php -l .\woocommerce-bulk-percentage-price-editor\woocommerce-bulk-percentage-price-editor.php
```

## Documentation

- [Project handoff](HANDOFF.md)
- [Project instructions](AGENTS.md)
- [Project knowledge bundle](docs/okf/index.md)
