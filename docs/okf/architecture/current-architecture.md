---
type: Architecture
title: Current Plugin Architecture
description: Single-file WordPress admin workflow using WooCommerce product CRUD APIs.
tags:
  - wordpress
  - woocommerce
  - architecture
status: active
---

# Current Plugin Architecture

## Installable Unit
The plugin is one PHP file with a WordPress plugin header. It registers one submenu under WooCommerce and uses WordPress admin hooks and built-in HTML forms; it adds no JavaScript/CSS assets or third-party libraries. The installable ZIP contains only this file.

## Price Data
Published simple products and variable-product children are loaded through WooCommerce APIs. The administrator can filter by product type and category and can target regular prices, sale prices, or both. For a variable product, each variation is calculated independently using the store's configured price precision. Updates use product objects and `save()`, followed by synchronization and transient cleanup for changed variable-product parents.

## Workflow
The workflow filters published products, computes a preview, and stores a user-bound job for 24 hours. It applies absolute previewed values in batches of 25. A job is limited to 5,000 parent products and 10,000 price items so its preview and saved plan remain bounded. Each changed field is checked against its preview baseline; dependent unchanged price fields are checked too. A later edit is skipped instead of being overwritten. After completion, a guarded rollback restores only prices that still match this job's result.

## Security and Scope
Admin actions require WooCommerce management capability and WordPress nonces. The tool is intended for explicit administrator-triggered changes; it does not run in storefront requests or on a schedule. Product saves use normal WooCommerce hooks, so installed integration plugins may observe changes.
