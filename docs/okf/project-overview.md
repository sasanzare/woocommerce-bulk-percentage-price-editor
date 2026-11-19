---
type: Project
title: WooCommerce Bulk Percentage Price Editor
description: A lightweight portable plugin for previewed percentage adjustments to simple and variable product prices.
tags:
  - woocommerce
  - wordpress
  - pricing
status: active
---

# Project Overview

## Purpose
Provide a small WordPress admin tool that adjusts prices across selected product scopes, including the individual variations of variable products.

## Boundaries
- The installable plugin contains one PHP file and uses no external runtime libraries or assets.
- It requires active WooCommerce and uses its product CRUD API.
- Administrators filter published simple and variable products by type and category, choose regular price, sale price, or both, and review a preview before applying a change.
- It changes eligible prices in resumable batches and offers guarded rollback for values that have not since been edited.
- Live-store installation and price changes are separate operational actions.

## Target Use
The initial use is Zho Boutique, while the plugin must remain portable to other WooCommerce sites. Site-specific credentials, configuration, and product data are not embedded in the plugin.

## User Documentation
Installation, operation, pricing rules, and package instructions are documented in the repository [README](../../README.md).
